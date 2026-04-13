<?php

namespace App\Http\Controllers\Picker;

use App\Http\Controllers\Controller;
use App\Models\InboundScanSession;
use App\Models\InboundScanSessionItem;
use App\Models\InboundTransaction;
use App\Support\InboundScanStatus;
use App\Support\StockService;
use App\Support\WarehouseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InboundScanController extends Controller
{
    public function index()
    {
        return view('picker.inbound-scan', [
            'routes' => [
                'dashboard' => route('picker.dashboard'),
                'search' => route('picker.inbound-scan.transactions'),
                'open' => route('picker.inbound-scan.open'),
                'scanSku' => route('picker.inbound-scan.scan-sku'),
                'complete' => route('picker.inbound-scan.complete'),
                'reset' => route('picker.inbound-scan.reset'),
                'logout' => route('logout'),
            ],
        ]);
    }

    public function transactions(Request $request)
    {
        $query = trim((string) $request->input('query', ''));

        $builder = InboundTransaction::query()
            ->with(['items.item', 'scanSession.items'])
            ->where('warehouse_id', WarehouseService::defaultWarehouseId())
            ->whereIn('status', [
                InboundScanStatus::PENDING_SCAN,
                InboundScanStatus::SCANNING,
            ])
            ->orderByDesc('transacted_at')
            ->limit(12);

        if ($query !== '') {
            $builder->where(function ($q) use ($query) {
                $q->where('code', 'like', "%{$query}%")
                    ->orWhere('ref_no', 'like', "%{$query}%")
                    ->orWhere('surat_jalan_no', 'like', "%{$query}%");
            });
        }

        $transactions = $builder->get();

        return response()->json([
            'transactions' => $transactions->map(fn (InboundTransaction $transaction) => $this->serializeTransactionSummary($transaction))->values(),
        ]);
    }

    public function open(Request $request)
    {
        $validated = $request->validate([
            'transaction_id' => ['required', 'integer', 'exists:inbound_transactions,id'],
        ]);

        DB::beginTransaction();
        try {
            $transaction = InboundTransaction::with(['items.item', 'scanSession.items'])
                ->where('id', (int) $validated['transaction_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $transaction->warehouse_id !== WarehouseService::defaultWarehouseId()) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Inbound ini bukan untuk gudang besar.',
                ], 422);
            }

            if (($transaction->status ?? '') === InboundScanStatus::COMPLETED) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Inbound sudah selesai discan.',
                ], 422);
            }

            if ($transaction->items->isEmpty()) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Item inbound belum tersedia.',
                ], 422);
            }

            $session = $transaction->scanSession;
            if (!$session) {
                $session = InboundScanSession::create([
                    'inbound_transaction_id' => $transaction->id,
                    'started_by' => auth()->id(),
                    'started_at' => now(),
                ]);
                $this->syncSessionItems($session, $transaction);
            } elseif ($session->items->isEmpty()) {
                $this->syncSessionItems($session, $transaction);
            }

            $transaction->status = InboundScanStatus::SCANNING;
            $transaction->save();

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal membuka inbound scan.',
                'error' => $e->getMessage(),
            ], 500);
        }

        $transaction = InboundTransaction::with([
            'items.item',
            'scanSession.items',
            'scanSession.starter:id,name',
            'scanSession.lastScanner:id,name',
            'scanSession.completer:id,name',
            'scanSession.resetter:id,name',
        ])->findOrFail((int) $validated['transaction_id']);

        return response()->json([
            'message' => 'Inbound siap discan.',
            'transaction' => $this->serializeTransactionDetail($transaction),
        ]);
    }

    public function scanSku(Request $request)
    {
        $validated = $request->validate([
            'session_id' => ['required', 'integer', 'exists:inbound_scan_sessions,id'],
            'code' => ['required', 'string'],
            'allow_over_scan' => ['sometimes', 'boolean'],
        ]);

        $code = trim((string) $validated['code']);
        if ($code === '') {
            return response()->json([
                'message' => 'SKU tidak boleh kosong.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $session = InboundScanSession::where('id', (int) $validated['session_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $transaction = InboundTransaction::where('id', $session->inbound_transaction_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (($transaction->status ?? '') === InboundScanStatus::COMPLETED) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Inbound sudah selesai discan.',
                ], 422);
            }

            $items = InboundScanSessionItem::where('inbound_scan_session_id', $session->id)
                ->lockForUpdate()
                ->get();

            $target = $items->first(function (InboundScanSessionItem $row) use ($code) {
                return strtolower((string) $row->sku) === strtolower($code);
            });

            if (!$target) {
                DB::rollBack();
                return response()->json([
                    'message' => 'SKU tidak ada pada inbound ini.',
                ], 422);
            }

            $nextScannedKoli = (int) $target->scanned_koli + 1;
            $willOverScan = $nextScannedKoli > (int) $target->expected_koli;
            if ($willOverScan && empty($validated['allow_over_scan'])) {
                DB::rollBack();
                return response()->json([
                    'message' => 'SKU sudah mencapai target surat jalan. Scan lagi akan dianggap terima lebih. Lanjutkan?',
                    'action' => 'confirm_over_scan',
                    'details' => [[
                        'sku' => $target->sku,
                        'expected_koli' => (int) $target->expected_koli,
                        'scanned_koli' => (int) $target->scanned_koli,
                        'expected_qty' => (int) $target->expected_qty,
                        'scanned_qty' => (int) $target->scanned_qty,
                        'next_scanned_koli' => $nextScannedKoli,
                        'next_scanned_qty' => (int) $target->scanned_qty + (int) $target->qty_per_koli,
                    ]],
                ], 409);
            }

            $target->scanned_koli = $nextScannedKoli;
            $target->scanned_qty = (int) $target->scanned_qty + (int) $target->qty_per_koli;
            $target->save();

            $session->last_scanned_by = auth()->id();
            $session->last_scanned_at = now();
            $session->save();

            if (($transaction->status ?? '') !== InboundScanStatus::SCANNING) {
                $transaction->status = InboundScanStatus::SCANNING;
                $transaction->save();
            }

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal memproses scan inbound.',
                'error' => $e->getMessage(),
            ], 500);
        }

        $transaction = InboundTransaction::with([
            'items.item',
            'scanSession.items',
            'scanSession.starter:id,name',
            'scanSession.lastScanner:id,name',
            'scanSession.completer:id,name',
            'scanSession.resetter:id,name',
        ])->findOrFail($session->inbound_transaction_id);

        return response()->json([
            'message' => 'SKU inbound berhasil discan.',
            'transaction' => $this->serializeTransactionDetail($transaction),
        ]);
    }

    public function complete(Request $request)
    {
        $validated = $request->validate([
            'session_id' => ['required', 'integer', 'exists:inbound_scan_sessions,id'],
            'confirm_variance' => ['sometimes', 'boolean'],
        ]);

        DB::beginTransaction();
        try {
            $session = InboundScanSession::where('id', (int) $validated['session_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $transaction = InboundTransaction::where('id', $session->inbound_transaction_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (($transaction->status ?? '') === InboundScanStatus::COMPLETED) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Inbound sudah selesai sebelumnya.',
                ], 422);
            }

            $items = InboundScanSessionItem::where('inbound_scan_session_id', $session->id)
                ->lockForUpdate()
                ->get();

            $totalScannedKoli = (int) $items->sum('scanned_koli');
            if ($totalScannedKoli <= 0) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Belum ada scan koli. Scan minimal 1 koli sebelum complete.',
                ], 422);
            }

            $variance = [];
            foreach ($items as $item) {
                if ((int) $item->scanned_koli !== (int) $item->expected_koli || (int) $item->scanned_qty !== (int) $item->expected_qty) {
                    $variance[] = [
                        'sku' => $item->sku,
                        'expected_koli' => (int) $item->expected_koli,
                        'scanned_koli' => (int) $item->scanned_koli,
                        'expected_qty' => (int) $item->expected_qty,
                        'scanned_qty' => (int) $item->scanned_qty,
                        'diff_koli' => (int) $item->scanned_koli - (int) $item->expected_koli,
                        'diff_qty' => (int) $item->scanned_qty - (int) $item->expected_qty,
                    ];
                }
            }

            if (!empty($variance) && empty($validated['confirm_variance'])) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Ada selisih antara surat jalan dan hasil scan. Jika dilanjutkan, stok akan masuk sesuai hasil scan.',
                    'action' => 'confirm_variance',
                    'details' => $variance,
                ], 409);
            }

            $completedAt = now();
            foreach ($items as $item) {
                if (!(int) $item->item_id) {
                    throw ValidationException::withMessages([
                        'transaction' => "Item untuk SKU {$item->sku} sudah tidak valid.",
                    ]);
                }

                $qty = (int) $item->scanned_qty;
                if ($qty > 0) {
                    StockService::mutate([
                        'item_id' => $item->item_id,
                        'warehouse_id' => $transaction->warehouse_id,
                        'direction' => 'in',
                        'qty' => $qty,
                        'source_type' => 'inbound',
                        'source_subtype' => $transaction->type,
                        'source_id' => $transaction->id,
                        'source_code' => $transaction->code,
                        'note' => $item->note,
                        'occurred_at' => $completedAt,
                        'created_by' => auth()->id(),
                    ]);
                }
            }

            $transaction->status = InboundScanStatus::COMPLETED;
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
                'message' => 'Gagal menyelesaikan scan inbound.',
                'error' => $e->getMessage(),
            ], 500);
        }

        $transaction = InboundTransaction::with([
            'items.item',
            'scanSession.items',
            'scanSession.starter:id,name',
            'scanSession.lastScanner:id,name',
            'scanSession.completer:id,name',
            'scanSession.resetter:id,name',
        ])->findOrFail($session->inbound_transaction_id);

        return response()->json([
            'message' => 'Scan inbound selesai dan stok sudah masuk.',
            'transaction' => $this->serializeTransactionDetail($transaction),
        ]);
    }

    public function reset(Request $request)
    {
        $validated = $request->validate([
            'session_id' => ['required', 'integer', 'exists:inbound_scan_sessions,id'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        DB::beginTransaction();
        try {
            $session = InboundScanSession::where('id', (int) $validated['session_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $transaction = InboundTransaction::where('id', $session->inbound_transaction_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (($transaction->status ?? '') === InboundScanStatus::COMPLETED) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Inbound yang sudah selesai tidak bisa direset.',
                ], 422);
            }

            InboundScanSessionItem::where('inbound_scan_session_id', $session->id)->update([
                'scanned_qty' => 0,
                'scanned_koli' => 0,
            ]);

            $session->reset_count = (int) ($session->reset_count ?? 0) + 1;
            $session->reset_by = auth()->id();
            $session->reset_at = now();
            $session->reset_reason = trim((string) $validated['reason']);
            $session->save();

            if (($transaction->status ?? '') !== InboundScanStatus::SCANNING) {
                $transaction->status = InboundScanStatus::SCANNING;
                $transaction->save();
            }

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal mereset scan inbound.',
                'error' => $e->getMessage(),
            ], 500);
        }

        $transaction = InboundTransaction::with([
            'items.item',
            'scanSession.items',
            'scanSession.starter:id,name',
            'scanSession.lastScanner:id,name',
            'scanSession.completer:id,name',
            'scanSession.resetter:id,name',
        ])->findOrFail($session->inbound_transaction_id);

        return response()->json([
            'message' => 'Scan inbound berhasil direset.',
            'transaction' => $this->serializeTransactionDetail($transaction),
        ]);
    }

    private function syncSessionItems(InboundScanSession $session, InboundTransaction $transaction): void
    {
        foreach ($transaction->items as $row) {
            $item = $row->item;
            if (!$item) {
                throw ValidationException::withMessages([
                    'transaction' => 'Ada item inbound yang tidak valid.',
                ]);
            }

            $qty = (int) $row->qty;
            $koli = (int) ($row->koli ?? 0);
            $qtyPerKoli = $koli > 0 ? (int) ($qty / $koli) : 0;
            if ($qty <= 0 || $koli <= 0 || $qtyPerKoli <= 0 || ($koli * $qtyPerKoli) !== $qty) {
                throw ValidationException::withMessages([
                    'transaction' => "Data inbound untuk SKU {$item->sku} tidak konsisten. Periksa qty dan koli.",
                ]);
            }

            InboundScanSessionItem::create([
                'inbound_scan_session_id' => $session->id,
                'item_id' => $row->item_id,
                'sku' => $item->sku,
                'item_name' => $item->name,
                'qty_per_koli' => $qtyPerKoli,
                'expected_qty' => $qty,
                'expected_koli' => $koli,
                'scanned_qty' => 0,
                'scanned_koli' => 0,
                'note' => $row->note,
            ]);
        }
    }

    private function serializeTransactionSummary(InboundTransaction $transaction): array
    {
        $expectedQty = (int) $transaction->items->sum('qty');
        $expectedKoli = (int) $transaction->items->sum(fn ($row) => (int) ($row->koli ?? 0));
        $scanItems = $transaction->scanSession?->items ?? collect();
        $scannedQty = (int) $scanItems->sum('scanned_qty');
        $scannedKoli = (int) $scanItems->sum('scanned_koli');

        return [
            'id' => $transaction->id,
            'code' => $transaction->code,
            'type' => $transaction->type,
            'ref_no' => $transaction->ref_no,
            'surat_jalan_no' => $transaction->surat_jalan_no,
            'surat_jalan_at' => $transaction->surat_jalan_at?->format('Y-m-d'),
            'transacted_at' => $transaction->transacted_at?->format('Y-m-d H:i'),
            'status' => $transaction->status ?? InboundScanStatus::PENDING_SCAN,
            'summary' => [
                'expected_qty' => $expectedQty,
                'expected_koli' => $expectedKoli,
                'scanned_qty' => $scannedQty,
                'scanned_koli' => $scannedKoli,
                'remaining_koli' => max(0, $expectedKoli - $scannedKoli),
            ],
        ];
    }

    private function serializeTransactionDetail(InboundTransaction $transaction): array
    {
        $session = $transaction->scanSession;
        $items = $session?->items ?? collect();
        $expectedQty = (int) $items->sum('expected_qty');
        $expectedKoli = (int) $items->sum('expected_koli');
        $scannedQty = (int) $items->sum('scanned_qty');
        $scannedKoli = (int) $items->sum('scanned_koli');

        return [
            'id' => $transaction->id,
            'code' => $transaction->code,
            'type' => $transaction->type,
            'ref_no' => $transaction->ref_no,
            'surat_jalan_no' => $transaction->surat_jalan_no,
            'surat_jalan_at' => $transaction->surat_jalan_at?->format('Y-m-d'),
            'transacted_at' => $transaction->transacted_at?->format('Y-m-d H:i'),
            'status' => $transaction->status ?? InboundScanStatus::PENDING_SCAN,
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
                'expected_koli' => $expectedKoli,
                'scanned_qty' => $scannedQty,
                'scanned_koli' => $scannedKoli,
                'remaining_koli' => max(0, $expectedKoli - $scannedKoli),
            ],
            'items' => $items->map(function (InboundScanSessionItem $item) {
                return [
                    'sku' => $item->sku,
                    'item_name' => $item->item_name,
                    'qty_per_koli' => (int) $item->qty_per_koli,
                    'expected_qty' => (int) $item->expected_qty,
                    'expected_koli' => (int) $item->expected_koli,
                    'scanned_qty' => (int) $item->scanned_qty,
                    'scanned_koli' => (int) $item->scanned_koli,
                ];
            })->values(),
        ];
    }
}
