<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OutboundQcSession;
use App\Models\OutboundQcSessionItem;
use App\Models\OutboundTransaction;
use App\Models\StockMutation;
use App\Support\OutboundManualQcStatus;
use App\Support\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OutboundManualQcController extends Controller
{
    public function index()
    {
        return view('admin.outbound.manual-qc.index', [
            'routes' => [
                'transactions' => route('admin.outbound.manual-qc.transactions'),
                'open' => route('admin.outbound.manual-qc.open'),
                'scanSku' => route('admin.outbound.manual-qc.scan-sku'),
                'complete' => route('admin.outbound.manual-qc.complete'),
                'reset' => route('admin.outbound.manual-qc.reset'),
            ],
        ]);
    }

    public function transactions(Request $request)
    {
        $query = trim((string) $request->input('query', ''));
        $limit = $query !== '' ? 50 : 15;
        $statuses = $query === ''
            ? [OutboundManualQcStatus::PENDING_QC, OutboundManualQcStatus::QC_SCANNING]
            : [OutboundManualQcStatus::PENDING_QC, OutboundManualQcStatus::QC_SCANNING, OutboundManualQcStatus::APPROVED];

        $builder = OutboundTransaction::query()
            ->with(['items.item', 'warehouse', 'qcSession.items'])
            ->where('type', 'manual')
            ->whereIn('status', $statuses)
            ->orderByDesc('transacted_at')
            ->limit($limit);

        if ($query !== '') {
            $builder->where(function ($q) use ($query) {
                $like = "%{$query}%";
                $loose = '%'.preg_replace('/[^A-Za-z0-9]+/', '%', $query).'%';
                $q->where('code', 'like', $like)
                    ->orWhere('code', 'like', $loose)
                    ->orWhere('ref_no', 'like', $like)
                    ->orWhere('ref_no', 'like', $loose);

                if (ctype_digit($query)) {
                    $q->orWhere('id', (int) $query);
                }
            });
        }

        return response()->json([
            'transactions' => $builder->get()
                ->map(fn (OutboundTransaction $transaction) => $this->serializeTransactionSummary($transaction))
                ->values(),
        ]);
    }

    public function open(Request $request)
    {
        $validated = $request->validate([
            'transaction_id' => ['required', 'integer', 'exists:outbound_transactions,id'],
        ]);

        DB::beginTransaction();
        try {
            $transaction = OutboundTransaction::with(['items.item', 'qcSession.items'])
                ->where('id', (int) $validated['transaction_id'])
                ->where('type', 'manual')
                ->lockForUpdate()
                ->firstOrFail();

            if (($transaction->status ?? '') === OutboundManualQcStatus::APPROVED) {
                DB::commit();
            } else {
                if (!in_array($transaction->status ?? '', [OutboundManualQcStatus::PENDING_QC, OutboundManualQcStatus::QC_SCANNING], true)) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Outbound manual belum masuk tahap QC.',
                    ], 422);
                }

                if ($transaction->items->isEmpty()) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Item outbound belum tersedia.',
                    ], 422);
                }

                $session = $transaction->qcSession;
                if (!$session) {
                    $session = OutboundQcSession::create([
                        'outbound_transaction_id' => $transaction->id,
                        'started_by' => auth()->id(),
                        'started_at' => now(),
                    ]);
                    $this->syncSessionItems($session, $transaction);
                } elseif ($session->items->isEmpty()) {
                    $this->syncSessionItems($session, $transaction);
                }

                if (($transaction->status ?? '') !== OutboundManualQcStatus::QC_SCANNING) {
                    $transaction->status = OutboundManualQcStatus::QC_SCANNING;
                    $transaction->save();
                }

                DB::commit();
            }
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal membuka QC outbound manual.',
                'error' => $e->getMessage(),
            ], 500);
        }

        $transaction = $this->loadTransaction((int) $validated['transaction_id']);

        return response()->json([
            'message' => ($transaction->status ?? '') === OutboundManualQcStatus::APPROVED
                ? 'Outbound manual sudah selesai QC.'
                : 'Outbound manual siap discan.',
            'transaction' => $this->serializeTransactionDetail($transaction),
        ]);
    }

    public function scanSku(Request $request)
    {
        $validated = $request->validate([
            'session_id' => ['required', 'integer', 'exists:outbound_qc_sessions,id'],
            'code' => ['required', 'string'],
            'qty' => ['nullable', 'integer', 'min:1'],
        ]);

        $code = trim((string) $validated['code']);
        $qty = (int) ($validated['qty'] ?? 1);
        if ($code === '') {
            return response()->json(['message' => 'SKU tidak boleh kosong.'], 422);
        }

        DB::beginTransaction();
        try {
            $session = OutboundQcSession::where('id', (int) $validated['session_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $transaction = OutboundTransaction::where('id', $session->outbound_transaction_id)
                ->where('type', 'manual')
                ->lockForUpdate()
                ->firstOrFail();

            if (($transaction->status ?? '') === OutboundManualQcStatus::APPROVED) {
                DB::rollBack();
                return response()->json(['message' => 'QC outbound manual sudah selesai.'], 422);
            }

            $items = OutboundQcSessionItem::where('outbound_qc_session_id', $session->id)
                ->lockForUpdate()
                ->get();

            $target = $items->first(function (OutboundQcSessionItem $row) use ($code) {
                return strtolower((string) $row->sku) === strtolower($code);
            });

            if (!$target) {
                DB::rollBack();
                return response()->json(['message' => 'SKU tidak ada pada outbound manual ini.'], 422);
            }

            $expected = (int) $target->expected_qty;
            $scanned = (int) $target->scanned_qty;
            if ($scanned + $qty > $expected) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Qty scan melebihi kebutuhan.',
                    'details' => [[
                        'sku' => $target->sku,
                        'required' => $expected,
                        'scanned' => $scanned,
                        'attempt' => $qty,
                    ]],
                ], 422);
            }

            $target->scanned_qty = $scanned + $qty;
            $target->save();

            $session->last_scanned_by = auth()->id();
            $session->last_scanned_at = now();
            $session->save();

            if (($transaction->status ?? '') !== OutboundManualQcStatus::QC_SCANNING) {
                $transaction->status = OutboundManualQcStatus::QC_SCANNING;
                $transaction->save();
            }

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal memproses scan SKU outbound manual.',
                'error' => $e->getMessage(),
            ], 500);
        }

        $transaction = $this->loadTransaction($session->outbound_transaction_id);

        return response()->json([
            'message' => 'SKU berhasil discan.',
            'transaction' => $this->serializeTransactionDetail($transaction),
        ]);
    }

    public function complete(Request $request)
    {
        $validated = $request->validate([
            'session_id' => ['required', 'integer', 'exists:outbound_qc_sessions,id'],
        ]);

        DB::beginTransaction();
        try {
            $session = OutboundQcSession::where('id', (int) $validated['session_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $transaction = OutboundTransaction::with('items')
                ->where('id', $session->outbound_transaction_id)
                ->where('type', 'manual')
                ->lockForUpdate()
                ->firstOrFail();

            if (($transaction->status ?? '') === OutboundManualQcStatus::APPROVED) {
                DB::rollBack();
                return response()->json(['message' => 'QC outbound manual sudah selesai sebelumnya.'], 422);
            }

            $items = OutboundQcSessionItem::where('outbound_qc_session_id', $session->id)
                ->lockForUpdate()
                ->get();

            $missing = [];
            foreach ($items as $item) {
                if ((int) $item->scanned_qty !== (int) $item->expected_qty) {
                    $missing[] = [
                        'sku' => $item->sku,
                        'required' => (int) $item->expected_qty,
                        'scanned' => (int) $item->scanned_qty,
                    ];
                }
            }

            if (!empty($missing)) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Masih ada SKU yang belum sesuai.',
                    'details' => $missing,
                ], 422);
            }

            $hasMutations = StockMutation::where('source_type', 'outbound')
                ->where('source_id', $transaction->id)
                ->exists();

            $completedAt = now();
            if (!$hasMutations) {
                StockService::depleteSellableRows($transaction->items, (int) $transaction->warehouse_id, [
                    'source_type' => 'outbound',
                    'source_subtype' => $transaction->type,
                    'source_id' => $transaction->id,
                    'source_code' => $transaction->code,
                    'note' => 'Outbound manual completed by QC',
                    'occurred_at' => $completedAt,
                    'created_by' => auth()->id(),
                ]);
            }

            $transaction->status = OutboundManualQcStatus::APPROVED;
            $transaction->approved_at = $completedAt;
            $transaction->approved_by = auth()->id();
            $transaction->save();

            $session->completed_by = auth()->id();
            $session->completed_at = $completedAt;
            $session->last_scanned_by = auth()->id();
            $session->last_scanned_at = $completedAt;
            $session->save();

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menyelesaikan QC outbound manual.',
                'error' => $e->getMessage(),
            ], 500);
        }

        $transaction = $this->loadTransaction($session->outbound_transaction_id);

        return response()->json([
            'message' => 'QC outbound manual selesai dan stok sudah keluar.',
            'transaction' => $this->serializeTransactionDetail($transaction),
        ]);
    }

    public function reset(Request $request)
    {
        $validated = $request->validate([
            'session_id' => ['required', 'integer', 'exists:outbound_qc_sessions,id'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $reason = trim((string) $validated['reason']);
        if ($reason === '') {
            return response()->json(['message' => 'Alasan reset wajib diisi.'], 422);
        }

        DB::beginTransaction();
        try {
            $session = OutboundQcSession::where('id', (int) $validated['session_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $transaction = OutboundTransaction::where('id', $session->outbound_transaction_id)
                ->where('type', 'manual')
                ->lockForUpdate()
                ->firstOrFail();

            if (($transaction->status ?? '') === OutboundManualQcStatus::APPROVED) {
                DB::rollBack();
                return response()->json(['message' => 'QC yang sudah selesai tidak bisa direset.'], 422);
            }

            OutboundQcSessionItem::where('outbound_qc_session_id', $session->id)
                ->update(['scanned_qty' => 0]);

            $session->reset_count = (int) ($session->reset_count ?? 0) + 1;
            $session->reset_by = auth()->id();
            $session->reset_at = now();
            $session->reset_reason = $reason;
            $session->save();

            if (($transaction->status ?? '') !== OutboundManualQcStatus::QC_SCANNING) {
                $transaction->status = OutboundManualQcStatus::QC_SCANNING;
                $transaction->save();
            }

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal reset QC outbound manual.',
                'error' => $e->getMessage(),
            ], 500);
        }

        $transaction = $this->loadTransaction($session->outbound_transaction_id);

        return response()->json([
            'message' => 'QC outbound manual berhasil direset.',
            'transaction' => $this->serializeTransactionDetail($transaction),
        ]);
    }

    private function syncSessionItems(OutboundQcSession $session, OutboundTransaction $transaction): void
    {
        foreach ($transaction->items as $row) {
            $item = $row->item;
            if (!$item) {
                throw ValidationException::withMessages([
                    'transaction' => 'Ada item outbound yang tidak valid.',
                ]);
            }

            OutboundQcSessionItem::create([
                'outbound_qc_session_id' => $session->id,
                'item_id' => $row->item_id,
                'sku' => $item->sku,
                'item_name' => $item->name,
                'expected_qty' => (int) $row->qty,
                'scanned_qty' => 0,
                'note' => $row->note,
            ]);
        }
    }

    private function loadTransaction(int $id): OutboundTransaction
    {
        return OutboundTransaction::with([
            'items.item',
            'warehouse',
            'qcSession.items',
            'qcSession.starter:id,name',
            'qcSession.lastScanner:id,name',
            'qcSession.completer:id,name',
            'qcSession.resetter:id,name',
        ])->findOrFail($id);
    }

    private function serializeTransactionSummary(OutboundTransaction $transaction): array
    {
        $expectedQty = (int) $transaction->items->sum('qty');
        $sessionItems = $transaction->qcSession?->items ?? collect();
        $scannedQty = (int) $sessionItems->sum('scanned_qty');
        if (($transaction->status ?? '') === OutboundManualQcStatus::APPROVED && $sessionItems->isEmpty()) {
            $scannedQty = $expectedQty;
        }

        return [
            'id' => $transaction->id,
            'code' => $transaction->code,
            'ref_no' => $transaction->ref_no,
            'warehouse' => $transaction->warehouse?->name ?? '-',
            'transacted_at' => $transaction->transacted_at?->format('Y-m-d H:i'),
            'status' => $transaction->status ?? OutboundManualQcStatus::PENDING,
            'summary' => [
                'expected_qty' => $expectedQty,
                'scanned_qty' => $scannedQty,
                'remaining_qty' => max(0, $expectedQty - $scannedQty),
            ],
        ];
    }

    private function serializeTransactionDetail(OutboundTransaction $transaction): array
    {
        $session = $transaction->qcSession;
        $sessionItems = $session?->items ?? collect();
        $hasSessionItems = $sessionItems->isNotEmpty();
        $items = $hasSessionItems ? $sessionItems : $transaction->items;
        $expectedQty = (int) ($hasSessionItems ? $items->sum('expected_qty') : $items->sum('qty'));
        $scannedQty = (int) ($hasSessionItems ? $items->sum('scanned_qty') : 0);
        if (($transaction->status ?? '') === OutboundManualQcStatus::APPROVED && !$hasSessionItems) {
            $scannedQty = $expectedQty;
        }

        return [
            'id' => $transaction->id,
            'code' => $transaction->code,
            'ref_no' => $transaction->ref_no,
            'warehouse' => $transaction->warehouse?->name ?? '-',
            'transacted_at' => $transaction->transacted_at?->format('Y-m-d H:i'),
            'status' => $transaction->status ?? OutboundManualQcStatus::PENDING,
            'session' => [
                'id' => $session?->id,
                'started_at' => $session?->started_at?->format('Y-m-d H:i'),
                'completed_at' => $session?->completed_at?->format('Y-m-d H:i'),
                'audit' => [
                    'started_by' => $session?->starter?->name ?? '-',
                    'last_scanned_by' => $session?->lastScanner?->name ?? '-',
                    'last_scanned_at' => $session?->last_scanned_at?->format('Y-m-d H:i'),
                    'completed_by' => $session?->completer?->name ?? '-',
                    'reset_count' => (int) ($session?->reset_count ?? 0),
                    'reset_by' => $session?->resetter?->name ?? '-',
                    'reset_at' => $session?->reset_at?->format('Y-m-d H:i'),
                    'reset_reason' => $session?->reset_reason,
                ],
            ],
            'summary' => [
                'expected_qty' => $expectedQty,
                'scanned_qty' => $scannedQty,
                'remaining_qty' => max(0, $expectedQty - $scannedQty),
            ],
            'items' => $items->map(function ($row) use ($hasSessionItems, $transaction) {
                if ($hasSessionItems) {
                    return [
                        'sku' => $row->sku,
                        'item_name' => $row->item_name,
                        'expected_qty' => (int) $row->expected_qty,
                        'scanned_qty' => (int) $row->scanned_qty,
                    ];
                }

                $isCompleted = ($transaction->status ?? '') === OutboundManualQcStatus::APPROVED;
                return [
                    'sku' => $row->item?->sku ?? null,
                    'item_name' => $row->item?->name ?? null,
                    'expected_qty' => (int) $row->qty,
                    'scanned_qty' => $isCompleted ? (int) $row->qty : 0,
                ];
            })->values(),
        ];
    }
}
