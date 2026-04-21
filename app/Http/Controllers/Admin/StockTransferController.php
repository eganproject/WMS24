<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Warehouse;
use App\Support\BundleService;
use App\Support\StockService;
use App\Support\WarehouseService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StockTransferController extends Controller
{
    public function index()
    {
        $items = Item::query()
            ->where('item_type', Item::TYPE_SINGLE)
            ->orderBy('name')
            ->get(['id', 'sku', 'name']);
        $warehouses = Warehouse::orderBy('name')->get(['id', 'code', 'name']);

        return view('admin.inventory.stock-transfers.index', [
            'items' => $items,
            'warehouses' => $warehouses,
            'dataUrl' => route('admin.inventory.stock-transfers.data'),
            'storeUrl' => route('admin.inventory.stock-transfers.store'),
            'showUrlTpl' => route('admin.inventory.stock-transfers.show', ':id'),
            'detailUrlTpl' => route('admin.inventory.stock-transfers.detail', ':id'),
            'qcUrlTpl' => route('admin.inventory.stock-transfers.qc', ':id'),
            'cancelUrlTpl' => route('admin.inventory.stock-transfers.cancel', ':id'),
            'defaultFrom' => WarehouseService::defaultWarehouseId(),
            'defaultTo' => WarehouseService::displayWarehouseId(),
        ]);
    }

    public function data(Request $request)
    {
        $query = StockTransfer::query()
            ->with(['items.item', 'fromWarehouse', 'toWarehouse', 'creator'])
            ->orderBy('transacted_at', 'desc');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('note', 'like', "%{$search}%")
                    ->orWhereHas('fromWarehouse', function ($wq) use ($search) {
                        $wq->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    })
                    ->orWhereHas('toWarehouse', function ($wq) use ($search) {
                        $wq->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    })
                    ->orWhereHas('items.item', function ($itemQ) use ($search) {
                        $itemQ->where('sku', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    });
            });
        }

        $this->applyDateFilter($query, $request);

        $recordsTotal = StockTransfer::count();
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $data = $query->get()->map(function ($row) {
            $items = $row->items ?? collect();
            $labels = $items->map(function ($it) {
                $sku = trim($it->item?->sku ?? '');
                if ($sku === '') {
                    return '';
                }
                $qty = (int) ($it->qty ?? 0);
                return sprintf('%s (%d)', $sku, $qty);
            })->filter()->values();
            $itemLabel = $labels->implode(', ');
            $ts = $row->transacted_at ? Carbon::parse($row->transacted_at)->format('Y-m-d H:i') : '';
            $totalQty = (int) $items->sum('qty');
            return [
                'id' => $row->id,
                'code' => $row->code,
                'from' => $row->fromWarehouse?->name ?? '-',
                'to' => $row->toWarehouse?->name ?? '-',
                'status' => $row->status ?? 'qc_pending',
                'transacted_at' => $ts,
                'submit_by' => $row->creator?->name ?? '-',
                'item' => $itemLabel ?: '-',
                'qty' => $totalQty,
                'note' => $row->note ?? '',
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function show(int $id)
    {
        $transfer = StockTransfer::with(['items.item', 'fromWarehouse', 'toWarehouse'])
            ->findOrFail($id);

        return response()->json([
            'id' => $transfer->id,
            'code' => $transfer->code,
            'from_warehouse_id' => $transfer->from_warehouse_id,
            'to_warehouse_id' => $transfer->to_warehouse_id,
            'note' => $transfer->note,
            'status' => $transfer->status ?? 'qc_pending',
            'transacted_at' => $transfer->transacted_at?->format('Y-m-d\TH:i'),
            'items' => $transfer->items->map(function ($row) {
                return [
                    'item_id' => $row->item_id,
                    'qty' => (int) $row->qty,
                    'qty_ok' => (int) $row->qty_ok,
                    'qty_reject' => (int) $row->qty_reject,
                    'note' => $row->note ?? '',
                    'qc_note' => $row->qc_note ?? '',
                    'label' => trim(($row->item?->sku ?? '').' - '.($row->item?->name ?? '')),
                ];
            })->values(),
        ]);
    }

    public function detail(int $id)
    {
        $transfer = StockTransfer::with(['items.item', 'fromWarehouse', 'toWarehouse', 'creator', 'qcBy'])
            ->findOrFail($id);

        return view('admin.inventory.stock-transfers.detail', [
            'transfer' => $transfer,
            'backUrl' => route('admin.inventory.stock-transfers.index'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        $code = $this->generateCode('TRF');
        $transactedAt = $validated['transacted_at'] ?? now();

        DB::beginTransaction();
        try {
            $transfer = StockTransfer::create([
                'code' => $code,
                'from_warehouse_id' => $validated['from_warehouse_id'],
                'to_warehouse_id' => $validated['to_warehouse_id'],
                'note' => $validated['note'] ?? null,
                'transacted_at' => $transactedAt,
                'created_by' => auth()->id(),
                'status' => 'qc_pending',
            ]);

            foreach ($validated['items'] as $row) {
                StockTransferItem::create([
                    'stock_transfer_id' => $transfer->id,
                    'item_id' => $row['item_id'],
                    'qty' => $row['qty'],
                    'note' => $row['note'] ?? null,
                ]);

                StockService::mutate([
                    'item_id' => $row['item_id'],
                    'warehouse_id' => $validated['from_warehouse_id'],
                    'direction' => 'out',
                    'qty' => $row['qty'],
                    'source_type' => 'transfer',
                    'source_subtype' => 'send',
                    'source_id' => $transfer->id,
                    'source_code' => $transfer->code,
                    'note' => $row['note'] ?? null,
                    'occurred_at' => $transactedAt,
                    'created_by' => auth()->id(),
                ]);
            }

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menyimpan transfer gudang',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Transfer gudang berhasil disimpan',
        ]);
    }

    public function qc(Request $request, int $id)
    {
        $transfer = StockTransfer::with('items')->findOrFail($id);
        if (($transfer->status ?? 'qc_pending') !== 'qc_pending') {
            return response()->json(['message' => 'Transfer sudah diproses QC'], 422);
        }

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer'],
            'items.*.qty_ok' => ['required', 'integer', 'min:0'],
            'items.*.qty_reject' => ['required', 'integer', 'min:0'],
            'items.*.qc_note' => ['nullable', 'string'],
        ]);

        $itemMap = $transfer->items->keyBy('item_id');

        foreach ($validated['items'] as $row) {
            $itemId = (int) $row['item_id'];
            $transferItem = $itemMap->get($itemId);
            if (!$transferItem) {
                throw ValidationException::withMessages([
                    'items' => 'Item tidak ditemukan di transfer',
                ]);
            }
            $ok = (int) $row['qty_ok'];
            $reject = (int) $row['qty_reject'];
            $total = $ok + $reject;
            $transferQty = (int) $transferItem->qty;
            if ($total !== $transferQty) {
                throw ValidationException::withMessages([
                    'items' => 'Qty OK + reject harus sama dengan qty transfer',
                ]);
            }
        }

        DB::beginTransaction();
        try {
            foreach ($validated['items'] as $row) {
                $itemId = (int) $row['item_id'];
                $transferItem = $itemMap->get($itemId);
                $ok = (int) $row['qty_ok'];
                $reject = (int) $row['qty_reject'];

                $transferItem->update([
                    'qty_ok' => $ok,
                    'qty_reject' => $reject,
                    'qc_note' => $row['qc_note'] ?? null,
                ]);

                if ($ok > 0) {
                    StockService::mutate([
                        'item_id' => $itemId,
                        'warehouse_id' => $transfer->to_warehouse_id,
                        'direction' => 'in',
                        'qty' => $ok,
                        'source_type' => 'transfer',
                        'source_subtype' => 'qc_ok',
                        'source_id' => $transfer->id,
                        'source_code' => $transfer->code,
                        'note' => $row['qc_note'] ?? null,
                        'occurred_at' => now(),
                        'created_by' => auth()->id(),
                    ]);
                }

                if ($reject > 0) {
                    StockService::mutate([
                        'item_id' => $itemId,
                        'warehouse_id' => $transfer->from_warehouse_id,
                        'direction' => 'in',
                        'qty' => $reject,
                        'source_type' => 'transfer',
                        'source_subtype' => 'qc_reject',
                        'source_id' => $transfer->id,
                        'source_code' => $transfer->code,
                        'note' => $row['qc_note'] ?? null,
                        'occurred_at' => now(),
                        'created_by' => auth()->id(),
                    ]);
                }
            }

            $transfer->update([
                'status' => 'completed',
                'qc_at' => now(),
                'qc_by' => auth()->id(),
            ]);

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal memproses QC transfer',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'QC transfer berhasil diproses',
        ]);
    }

    public function cancel(Request $request, int $id)
    {
        $transfer = StockTransfer::with('items')->findOrFail($id);
        if (($transfer->status ?? 'qc_pending') !== 'qc_pending') {
            return response()->json([
                'message' => 'Transfer hanya bisa dibatalkan sebelum QC diproses.',
            ], 422);
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        DB::beginTransaction();
        try {
            StockService::rollbackBySource('transfer', $transfer->id);

            $reason = trim((string) ($validated['reason'] ?? ''));
            $cancelNote = 'Transfer dibatalkan sebelum QC';
            if ($reason !== '') {
                $cancelNote .= ': '.$reason;
            }

            $transfer->update([
                'status' => 'canceled',
                'note' => $this->mergeCancelNote($transfer->note, $cancelNote),
                'qc_at' => null,
                'qc_by' => null,
            ]);

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal membatalkan transfer gudang',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Transfer gudang berhasil dibatalkan',
        ]);
    }

    private function validatePayload(Request $request): array
    {
        $validated = $request->validate([
            'from_warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'to_warehouse_id' => ['required', 'integer', 'different:from_warehouse_id', 'exists:warehouses,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.note' => ['nullable', 'string'],
            'note' => ['nullable', 'string'],
            'transacted_at' => ['required', 'date'],
        ]);

        $items = collect($validated['items'] ?? [])
            ->filter(fn ($row) => (int) ($row['qty'] ?? 0) > 0 && (int) ($row['item_id'] ?? 0) > 0)
            ->map(function ($row) {
                return [
                    'item_id' => (int) $row['item_id'],
                    'qty' => (int) $row['qty'],
                    'note' => $row['note'] ?? null,
                ];
            })->values();

        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Minimal 1 item diperlukan',
            ]);
        }

        $duplicates = $items->groupBy('item_id')->filter(fn ($rows) => $rows->count() > 1);
        if ($duplicates->isNotEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Item tidak boleh duplikat pada transfer',
            ]);
        }

        BundleService::assertPhysicalItems(
            $items->pluck('item_id')->all(),
            'Bundle tidak bisa digunakan pada transfer gudang karena tidak memiliki stok fisik.'
        );

        $normalized = $items->groupBy('item_id')->map(function ($rows, $itemId) {
            $qty = $rows->sum('qty');
            $note = $rows->pluck('note')->first(fn ($n) => $n !== null && $n !== '') ?? null;
            return [
                'item_id' => (int) $itemId,
                'qty' => $qty,
                'note' => $note,
            ];
        })->values()->all();

        $validated['items'] = $normalized;
        $validated['transacted_at'] = !empty($validated['transacted_at'])
            ? Carbon::parse($validated['transacted_at'])
            : null;

        return $validated;
    }

    private function applyDateFilter($query, Request $request): void
    {
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        try {
            if ($dateFrom) {
                $from = Carbon::parse($dateFrom)->startOfDay();
                $query->where('transacted_at', '>=', $from);
            }
            if ($dateTo) {
                $to = Carbon::parse($dateTo)->endOfDay();
                $query->where('transacted_at', '<=', $to);
            }
        } catch (\Throwable) {
            // ignore invalid date filters
        }
    }

    private function generateCode(string $prefix): string
    {
        return $prefix.'-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));
    }

    private function mergeCancelNote(?string $existingNote, string $cancelNote): string
    {
        $current = trim((string) $existingNote);
        if ($current === '') {
            return $cancelNote;
        }

        return $current.PHP_EOL.'[CANCELED] '.$cancelNote;
    }
}
