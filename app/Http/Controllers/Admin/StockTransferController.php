<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DamagedGood;
use App\Models\DamagedGoodItem;
use App\Models\InboundKoliUnit;
use App\Models\Item;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\StockTransferKoliScan;
use App\Models\Warehouse;
use App\Support\BundleService;
use App\Support\InboundScanStatus;
use App\Support\StockService;
use App\Support\WarehouseService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StockTransferController extends Controller
{
    public function index()
    {
        $items = Item::query()
            ->where('item_type', Item::TYPE_SINGLE)
            ->orderBy('name')
            ->get(['id', 'sku', 'name', 'koli_qty']);
        $warehouses = Warehouse::orderBy('name')->get(['id', 'code', 'name']);

        return view('admin.inventory.stock-transfers.index', [
            'items' => $items,
            'warehouses' => $warehouses,
            'dataUrl' => route('admin.inventory.stock-transfers.data'),
            'storeUrl' => route('admin.inventory.stock-transfers.store'),
            'showUrlTpl' => route('admin.inventory.stock-transfers.show', ':id'),
            'detailUrlTpl' => route('admin.inventory.stock-transfers.detail', ':id'),
            'scanKoliUrlTpl' => route('admin.inventory.stock-transfers.scan-koli', ':id'),
            'qcUrlTpl' => route('admin.inventory.stock-transfers.qc', ':id'),
            'cancelUrlTpl' => route('admin.inventory.stock-transfers.cancel', ':id'),
            'defaultFrom' => WarehouseService::defaultWarehouseId(),
            'defaultTo' => WarehouseService::displayWarehouseId(),
            'defaultWarehouseId' => WarehouseService::defaultWarehouseId(),
            'displayWarehouseId' => WarehouseService::displayWarehouseId(),
        ]);
    }

    public function data(Request $request)
    {
        $query = StockTransfer::query()
            ->with(['items.item', 'fromWarehouse', 'toWarehouse', 'creator'])
            ->orderBy('transacted_at', 'desc');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $exact = $this->isExactSearch($request);
            $query->where(function ($q) use ($search, $exact) {
                $this->applyTextSearch($q, 'code', $search, $exact);
                $this->applyTextSearch($q, 'note', $search, $exact, 'or');
                $q->orWhereHas('fromWarehouse', function ($wq) use ($search, $exact) {
                    $this->applyTextSearch($wq, 'name', $search, $exact);
                    $this->applyTextSearch($wq, 'code', $search, $exact, 'or');
                })->orWhereHas('toWarehouse', function ($wq) use ($search, $exact) {
                    $this->applyTextSearch($wq, 'name', $search, $exact);
                    $this->applyTextSearch($wq, 'code', $search, $exact, 'or');
                })->orWhereHas('items.item', function ($itemQ) use ($search, $exact) {
                    $this->applyTextSearch($itemQ, 'sku', $search, $exact);
                    $this->applyTextSearch($itemQ, 'name', $search, $exact, 'or');
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
                $koliLabel = $this->formatKoliBreakdown($qty, (int) ($it->item?->koli_qty ?? 0));
                return $koliLabel !== ''
                    ? sprintf('%s (%d pcs | %s)', $sku, $qty, $koliLabel)
                    : sprintf('%s (%d pcs)', $sku, $qty);
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
        $transfer = StockTransfer::with([
            'items.item',
            'items.koliScans.koliUnit.transaction',
            'fromWarehouse',
            'toWarehouse',
        ])
            ->findOrFail($id);

        return response()->json([
            'id' => $transfer->id,
            'code' => $transfer->code,
            'from_warehouse_id' => $transfer->from_warehouse_id,
            'to_warehouse_id' => $transfer->to_warehouse_id,
            'note' => $transfer->note,
            'status' => $transfer->status ?? 'qc_pending',
            'traceability_mode' => $transfer->traceability_mode,
            'legacy_reason' => $transfer->legacy_reason,
            'transacted_at' => $transfer->transacted_at?->format('Y-m-d\TH:i'),
            'items' => $transfer->items->map(function ($row) {
                return [
                    'item_id' => $row->item_id,
                    'qty' => (int) $row->qty,
                    'qty_ok' => (int) $row->qty_ok,
                    'qty_reject' => (int) $row->qty_reject,
                    'qty_short' => (int) ($row->qty_short ?? 0),
                    'koli_label' => $this->formatKoliBreakdown((int) $row->qty, (int) ($row->item?->koli_qty ?? 0)),
                    'qty_ok_koli_label' => $this->formatKoliBreakdown((int) $row->qty_ok, (int) ($row->item?->koli_qty ?? 0)),
                    'qty_reject_koli_label' => $this->formatKoliBreakdown((int) $row->qty_reject, (int) ($row->item?->koli_qty ?? 0)),
                    'qty_short_koli_label' => $this->formatKoliBreakdown((int) ($row->qty_short ?? 0), (int) ($row->item?->koli_qty ?? 0)),
                    'qty_per_koli' => (int) ($row->item?->koli_qty ?? 0),
                    'note' => $row->note ?? '',
                    'qc_note' => $row->qc_note ?? '',
                    'label' => trim(($row->item?->sku ?? '').' - '.($row->item?->name ?? '')),
                    'scans' => $row->koliScans
                        ->sortBy(fn ($scan) => $scan->koliUnit?->code ?? '')
                        ->map(fn ($scan) => $this->serializeKoliScan($scan))
                        ->values(),
                ];
            })->values(),
            'requires_koli_scan' => $this->requiresKoliScan($transfer),
        ]);
    }

    public function scanKoli(Request $request, int $id)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:160'],
        ]);

        $code = trim((string) $validated['code']);
        if ($code === '') {
            throw ValidationException::withMessages([
                'code' => 'Kode QR dus wajib diisi.',
            ]);
        }

        DB::beginTransaction();
        try {
            $transfer = StockTransfer::whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            if (($transfer->status ?? 'qc_pending') !== 'qc_pending') {
                DB::rollBack();
                return response()->json(['message' => 'Transfer sudah diproses QC'], 422);
            }

            if (!$this->requiresKoliScan($transfer)) {
                DB::rollBack();
                return response()->json(['message' => 'Scan QR dus hanya dipakai untuk transfer Gudang Besar ke Gudang Display.'], 422);
            }

            $unit = InboundKoliUnit::with(['transaction', 'item'])
                ->where('code', $code)
                ->lockForUpdate()
                ->first();

            if (!$unit) {
                DB::rollBack();
                return response()->json(['message' => 'QR dus inbound tidak ditemukan.'], 422);
            }

            if (!in_array((string) ($unit->transaction?->status ?? ''), [InboundScanStatus::COMPLETED, 'approved'], true)) {
                DB::rollBack();
                return response()->json(['message' => 'Inbound asal QR dus belum selesai diterima.'], 422);
            }

            if (($unit->status ?? '') === InboundKoliUnit::STATUS_NOT_RECEIVED) {
                DB::rollBack();
                return response()->json(['message' => 'QR dus inbound ini tidak tercatat diterima pada proses scan inbound.'], 422);
            }

            if (($unit->status ?? InboundKoliUnit::STATUS_AVAILABLE) !== InboundKoliUnit::STATUS_AVAILABLE) {
                DB::rollBack();
                return response()->json(['message' => 'QR dus inbound sudah dipakai pada transfer lain.'], 422);
            }

            $transferItem = StockTransferItem::where('stock_transfer_id', $transfer->id)
                ->where('item_id', $unit->item_id)
                ->lockForUpdate()
                ->first();

            if (!$transferItem) {
                DB::rollBack();
                return response()->json(['message' => 'SKU pada QR tidak ada di transfer ini.'], 422);
            }

            $scannedQty = (int) StockTransferKoliScan::where('stock_transfer_item_id', $transferItem->id)
                ->lockForUpdate()
                ->sum('qty');
            $nextQty = $scannedQty + (int) $unit->qty;
            if ($nextQty > (int) $transferItem->qty) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Jumlah dus yang discan melebihi qty transfer untuk SKU ini.',
                    'details' => [
                        'qty_transfer' => (int) $transferItem->qty,
                        'qty_scanned' => $scannedQty,
                        'qty_next' => $nextQty,
                    ],
                ], 422);
            }

            $scan = StockTransferKoliScan::create([
                'stock_transfer_id' => $transfer->id,
                'stock_transfer_item_id' => $transferItem->id,
                'inbound_koli_unit_id' => $unit->id,
                'item_id' => $unit->item_id,
                'qty' => (int) $unit->qty,
                'qty_ok' => (int) $unit->qty,
                'qty_reject' => 0,
                'qty_short' => 0,
                'scanned_by' => auth()->id(),
                'scanned_at' => now(),
            ]);

            $unit->status = InboundKoliUnit::STATUS_RESERVED;
            $unit->reserved_transfer_id = $transfer->id;
            $unit->save();

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal scan QR dus inbound',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'QR dus inbound berhasil discan.',
            'scan' => $this->serializeKoliScan($scan->fresh(['koliUnit.transaction', 'koliUnit.item'])),
        ]);
    }

    public function detail(int $id)
    {
        $transfer = StockTransfer::with([
            'items.item',
            'items.koliScans.koliUnit.transaction',
            'fromWarehouse',
            'toWarehouse',
            'creator',
            'qcBy',
        ])
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
        $this->assertQcSchemaReady();

        $transfer = StockTransfer::with(['items.koliScans'])->findOrFail($id);
        if (($transfer->status ?? 'qc_pending') !== 'qc_pending') {
            return response()->json(['message' => 'Transfer sudah diproses QC'], 422);
        }

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer'],
            'items.*.qty_ok' => ['required', 'integer', 'min:0'],
            'items.*.qty_reject' => ['required', 'integer', 'min:0'],
            'items.*.qty_short' => ['nullable', 'integer', 'min:0'],
            'items.*.qc_note' => ['nullable', 'string'],
            'scans' => ['nullable', 'array'],
            'scans.*.id' => ['required', 'integer'],
            'scans.*.qty_ok' => ['required', 'integer', 'min:0'],
            'scans.*.qty_reject' => ['required', 'integer', 'min:0'],
            'scans.*.qty_short' => ['nullable', 'integer', 'min:0'],
            'scans.*.qc_note' => ['nullable', 'string'],
            'traceability_mode' => ['nullable', 'string', 'in:qr,legacy'],
            'legacy_reason' => ['nullable', 'string', 'max:500'],
        ]);

        DB::beginTransaction();
        try {
            $transfer = StockTransfer::with(['items.koliScans'])
                ->whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            if (($transfer->status ?? 'qc_pending') !== 'qc_pending') {
                DB::rollBack();
                return response()->json(['message' => 'Transfer sudah diproses QC'], 422);
            }

            $requiresKoliScan = $this->requiresKoliScan($transfer);
            $traceabilityMode = $requiresKoliScan
                ? (string) ($validated['traceability_mode'] ?? 'qr')
                : null;
            $legacyReason = trim((string) ($validated['legacy_reason'] ?? ''));

            if ($requiresKoliScan && $traceabilityMode === 'legacy' && $legacyReason === '') {
                throw ValidationException::withMessages([
                    'legacy_reason' => 'Alasan wajib diisi untuk QC tanpa QR inbound.',
                ]);
            }

            if ($requiresKoliScan && $traceabilityMode !== 'legacy') {
                $validated['items'] = $this->validateKoliScanQcPayload($transfer, $validated['scans'] ?? []);
            }
            if ($requiresKoliScan && $traceabilityMode === 'legacy') {
                InboundKoliUnit::where('reserved_transfer_id', $transfer->id)
                    ->update([
                        'status' => InboundKoliUnit::STATUS_AVAILABLE,
                        'reserved_transfer_id' => null,
                    ]);
                StockTransferKoliScan::where('stock_transfer_id', $transfer->id)->delete();
            }

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
                $short = (int) ($row['qty_short'] ?? 0);
                $total = $ok + $reject + $short;
                $transferQty = (int) $transferItem->qty;
                if ($total !== $transferQty) {
                    throw ValidationException::withMessages([
                        'items' => 'Qty OK + reject + kurang harus sama dengan qty transfer',
                    ]);
                }
            }

            $hasReject = collect($validated['items'])->sum(fn ($row) => (int) ($row['qty_reject'] ?? 0)) > 0;
            $damagedWarehouseId = WarehouseService::damagedWarehouseId();
            if ($hasReject && $damagedWarehouseId <= 0) {
                throw ValidationException::withMessages([
                    'items' => 'Gudang Rusak belum dikonfigurasi.',
                ]);
            }

            $damagedGood = null;
            if ($hasReject) {
                $damagedGood = DamagedGood::create([
                    'code' => $this->generateCode('DMG'),
                    'source_type' => DamagedGood::SOURCE_TRANSFER_REJECT,
                    'source_warehouse_id' => $transfer->from_warehouse_id,
                    'source_ref' => $transfer->code,
                    'note' => 'Reject QC transfer gudang '.$transfer->code,
                    'transacted_at' => now(),
                    'created_by' => auth()->id(),
                    'status' => 'approved',
                    'approved_at' => now(),
                    'approved_by' => auth()->id(),
                ]);
            }

            foreach ($validated['items'] as $row) {
                $itemId = (int) $row['item_id'];
                $transferItem = $itemMap->get($itemId);
                $ok = (int) $row['qty_ok'];
                $reject = (int) $row['qty_reject'];
                $short = (int) ($row['qty_short'] ?? 0);

                $transferItem->update([
                    'qty_ok' => $ok,
                    'qty_reject' => $reject,
                    'qty_short' => $short,
                    'qc_note' => $row['qc_note'] ?? null,
                ]);

                if ($requiresKoliScan && $traceabilityMode !== 'legacy') {
                    foreach (($row['scan_updates'] ?? []) as $scanUpdate) {
                        StockTransferKoliScan::whereKey($scanUpdate['id'])->update([
                            'qty_ok' => $scanUpdate['qty_ok'],
                            'qty_reject' => $scanUpdate['qty_reject'],
                            'qty_short' => $scanUpdate['qty_short'],
                            'qc_note' => $scanUpdate['qc_note'],
                        ]);
                    }
                }

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
                    DamagedGoodItem::create([
                        'damaged_good_id' => $damagedGood->id,
                        'item_id' => $itemId,
                        'qty' => $reject,
                        'reason_code' => DamagedGoodItem::REASON_OTHER,
                        'note' => $row['qc_note'] ?? null,
                    ]);

                    StockService::mutate([
                        'item_id' => $itemId,
                        'warehouse_id' => $damagedWarehouseId,
                        'direction' => 'in',
                        'qty' => $reject,
                        'source_type' => 'transfer',
                        'source_subtype' => 'qc_reject',
                        'source_id' => $transfer->id,
                        'source_code' => $transfer->code,
                        'note' => trim('Reject QC transfer dari '.$transfer->fromWarehouse?->name.' ke '.$transfer->toWarehouse?->name.'. '.($row['qc_note'] ?? '')),
                        'occurred_at' => now(),
                        'created_by' => auth()->id(),
                    ]);
                }
            }

            $transfer->update([
                'status' => 'completed',
                'qc_at' => now(),
                'qc_by' => auth()->id(),
                'traceability_mode' => $requiresKoliScan ? $traceabilityMode : null,
                'legacy_reason' => $requiresKoliScan && $traceabilityMode === 'legacy' ? $legacyReason : null,
            ]);

            if ($requiresKoliScan && $traceabilityMode !== 'legacy') {
                InboundKoliUnit::where('reserved_transfer_id', $transfer->id)
                    ->update(['status' => InboundKoliUnit::STATUS_COMPLETED]);
            }

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
            InboundKoliUnit::where('reserved_transfer_id', $transfer->id)
                ->update([
                    'status' => InboundKoliUnit::STATUS_AVAILABLE,
                    'reserved_transfer_id' => null,
                ]);
            StockTransferKoliScan::where('stock_transfer_id', $transfer->id)->delete();

            $reason = trim((string) ($validated['reason'] ?? ''));
            $cancelNote = 'Transfer dibatalkan sebelum QC';
            if ($reason !== '') {
                $cancelNote .= ': '.$reason;
            }

            $transfer->update([
                'status' => 'canceled',
                'traceability_mode' => null,
                'legacy_reason' => null,
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
            'items.*.koli' => ['nullable', 'integer', 'min:1'],
            'items.*.note' => ['nullable', 'string'],
            'note' => ['nullable', 'string'],
            'transacted_at' => ['required', 'date'],
        ]);

        $defaultWarehouseId = WarehouseService::defaultWarehouseId();
        $displayWarehouseId = WarehouseService::displayWarehouseId();
        if ((int) $validated['to_warehouse_id'] === $defaultWarehouseId) {
            throw ValidationException::withMessages([
                'to_warehouse_id' => 'Transfer stok ke Gudang Besar tidak diperbolehkan.',
            ]);
        }

        $requiresKoli = (int) $validated['from_warehouse_id'] === $defaultWarehouseId
            && (int) $validated['to_warehouse_id'] === $displayWarehouseId;
        $itemDefinitions = collect();
        if ($requiresKoli) {
            $itemDefinitions = Item::query()
                ->whereIn('id', collect($validated['items'] ?? [])->pluck('item_id')->filter()->unique()->all())
                ->get(['id', 'sku', 'koli_qty'])
                ->keyBy('id');
        }

        $items = collect($validated['items'] ?? [])
            ->filter(fn ($row) => (int) ($row['qty'] ?? 0) > 0 && (int) ($row['item_id'] ?? 0) > 0)
            ->map(function ($row, $idx) use ($requiresKoli, $itemDefinitions) {
                $itemId = (int) $row['item_id'];
                $qty = (int) $row['qty'];
                $koli = isset($row['koli']) && $row['koli'] !== '' ? (int) $row['koli'] : null;
                if ($requiresKoli) {
                    $item = $itemDefinitions->get($itemId);
                    $qtyPerKoli = (int) ($item?->koli_qty ?? 0);
                    if ($koli === null || $koli < 1) {
                        throw ValidationException::withMessages([
                            "items.{$idx}.koli" => 'Koli wajib diisi untuk transfer Gudang Besar ke Gudang Display.',
                        ]);
                    }
                    if ($qtyPerKoli < 1) {
                        throw ValidationException::withMessages([
                            "items.{$idx}.koli" => 'Isi/koli item belum diset.',
                        ]);
                    }
                    if ($qty !== $koli * $qtyPerKoli) {
                        throw ValidationException::withMessages([
                            "items.{$idx}.qty" => "Qty harus sama dengan koli x isi/koli ({$koli} x {$qtyPerKoli} = ".($koli * $qtyPerKoli).').',
                        ]);
                    }
                }

                return [
                    'item_id' => $itemId,
                    'qty' => $qty,
                    'koli' => $koli,
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

    private function validateKoliScanQcPayload(StockTransfer $transfer, array $scanRows): array
    {
        if (empty($scanRows)) {
            throw ValidationException::withMessages([
                'scans' => 'Scan QR dus inbound wajib dilakukan sebelum QC transfer Gudang Besar ke Gudang Display.',
            ]);
        }

        $scans = StockTransferKoliScan::with(['koliUnit.transaction'])
            ->where('stock_transfer_id', $transfer->id)
            ->get()
            ->keyBy('id');

        $posted = collect($scanRows)->keyBy(fn ($row) => (int) ($row['id'] ?? 0));
        if ($posted->count() !== count($scanRows)) {
            throw ValidationException::withMessages([
                'scans' => 'Data scan QR dus duplikat pada form QC.',
            ]);
        }

        foreach ($posted->keys() as $scanId) {
            if (!$scans->has($scanId)) {
                throw ValidationException::withMessages([
                    'scans' => 'Ada QR dus yang tidak terdaftar pada transfer ini.',
                ]);
            }
        }

        $updates = [];
        foreach ($scans as $scan) {
            $row = $posted->get($scan->id);
            if (!$row) {
                throw ValidationException::withMessages([
                    'scans' => 'Semua QR dus yang sudah discan wajib masuk ke form QC.',
                ]);
            }

            $ok = (int) ($row['qty_ok'] ?? 0);
            $reject = (int) ($row['qty_reject'] ?? 0);
            $short = (int) ($row['qty_short'] ?? 0);
            if ($ok + $reject + $short !== (int) $scan->qty) {
                throw ValidationException::withMessages([
                    'scans' => 'Qty OK + reject + kurang pada setiap QR dus harus sama dengan qty dus.',
                ]);
            }

            $updates[] = [
                'id' => (int) $scan->id,
                'item_id' => (int) $scan->item_id,
                'qty_ok' => $ok,
                'qty_reject' => $reject,
                'qty_short' => $short,
                'qc_note' => $row['qc_note'] ?? null,
            ];
        }

        $scanQtyByItem = $scans->groupBy('item_id')->map(fn ($rows) => (int) $rows->sum('qty'));
        foreach ($transfer->items as $item) {
            if ((int) ($scanQtyByItem[$item->item_id] ?? 0) !== (int) $item->qty) {
                throw ValidationException::withMessages([
                    'scans' => 'Qty dus yang discan harus sama dengan qty transfer untuk setiap SKU.',
                ]);
            }
        }

        return collect($updates)
            ->groupBy('item_id')
            ->map(function ($rows, $itemId) {
                $firstNote = $rows->pluck('qc_note')->first(fn ($note) => trim((string) $note) !== '') ?? null;

                return [
                    'item_id' => (int) $itemId,
                    'qty_ok' => (int) $rows->sum('qty_ok'),
                    'qty_reject' => (int) $rows->sum('qty_reject'),
                    'qty_short' => (int) $rows->sum('qty_short'),
                    'qc_note' => $firstNote,
                    'scan_updates' => $rows->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    private function assertQcSchemaReady(): void
    {
        $missing = [];
        foreach ([
            'stock_transfer_items.qty_short' => Schema::hasColumn('stock_transfer_items', 'qty_short'),
            'stock_transfers.traceability_mode' => Schema::hasColumn('stock_transfers', 'traceability_mode'),
            'stock_transfers.legacy_reason' => Schema::hasColumn('stock_transfers', 'legacy_reason'),
            'inbound_koli_units' => Schema::hasTable('inbound_koli_units'),
            'stock_transfer_koli_scans' => Schema::hasTable('stock_transfer_koli_scans'),
        ] as $name => $exists) {
            if (!$exists) {
                $missing[] = $name;
            }
        }

        if (!empty($missing)) {
            throw ValidationException::withMessages([
                'database' => 'Database belum diperbarui untuk QC transfer. Jalankan migration terlebih dahulu. Bagian yang belum ada: '.implode(', ', $missing).'.',
            ]);
        }
    }

    private function serializeKoliScan(StockTransferKoliScan $scan): array
    {
        $unit = $scan->koliUnit;
        $transaction = $unit?->transaction;

        return [
            'id' => (int) $scan->id,
            'code' => (string) ($unit?->code ?? ''),
            'inbound_code' => (string) ($transaction?->code ?? ''),
            'sku' => (string) ($unit?->sku ?? ''),
            'koli_no' => (int) ($unit?->koli_no ?? 0),
            'qty' => (int) $scan->qty,
            'qty_ok' => (int) $scan->qty_ok,
            'qty_reject' => (int) $scan->qty_reject,
            'qty_short' => (int) $scan->qty_short,
            'qc_note' => (string) ($scan->qc_note ?? ''),
        ];
    }

    private function requiresKoliScan(StockTransfer $transfer): bool
    {
        return (int) $transfer->from_warehouse_id === WarehouseService::defaultWarehouseId()
            && (int) $transfer->to_warehouse_id === WarehouseService::displayWarehouseId();
    }

    private function formatKoliBreakdown(int $qty, int $qtyPerKoli): string
    {
        if ($qty <= 0 || $qtyPerKoli <= 0) {
            return '';
        }

        $fullKoli = intdiv($qty, $qtyPerKoli);
        $remainder = $qty % $qtyPerKoli;
        $label = $fullKoli.' koli';
        if ($remainder > 0) {
            $label .= ' + '.$remainder.' pcs';
        }

        return $label.' x '.$qtyPerKoli;
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
