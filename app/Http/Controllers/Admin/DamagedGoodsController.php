<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DamagedGood;
use App\Models\DamagedGoodItem;
use App\Models\Item;
use App\Models\StockMutation;
use App\Models\Warehouse;
use App\Support\BundleService;
use App\Support\DamagedStockService;
use App\Support\StockService;
use App\Support\WarehouseService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DamagedGoodsController extends Controller
{
    public function index()
    {
        $items = Item::query()
            ->where('item_type', Item::TYPE_SINGLE)
            ->orderBy('name')
            ->get(['id', 'sku', 'name']);
        $damagedWarehouseId = WarehouseService::damagedWarehouseId();
        $damagedWarehouseLabel = Warehouse::where('id', $damagedWarehouseId)->value('name') ?? 'Gudang Rusak';
        $sourceWarehouses = Warehouse::query()
            ->where('id', '!=', $damagedWarehouseId)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return view('admin.inventory.damaged-goods.index', [
            'items' => $items,
            'sourceWarehouses' => $sourceWarehouses,
            'sourceTypeOptions' => DamagedGood::creatableSourceLabels(),
            'reasonOptions' => DamagedGoodItem::reasonLabels(),
            'damagedWarehouseLabel' => $damagedWarehouseLabel,
            'defaultSourceWarehouseId' => WarehouseService::displayWarehouseId(),
            'dataUrl' => route('admin.inventory.damaged-goods.data'),
            'summaryUrl' => route('admin.inventory.damaged-goods.summary-by-sku'),
            'agingUrl' => route('admin.inventory.damaged-goods.aging-summary'),
            'storeUrl' => route('admin.inventory.damaged-goods.store'),
        ]);
    }

    public function data(Request $request)
    {
        $query = DamagedGood::query()
            ->with(['items.item', 'creator', 'sourceWarehouse'])
            ->orderBy('transacted_at', 'desc');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $exact = $this->isExactSearch($request);
            $query->where(function ($q) use ($search, $exact) {
                $this->applyTextSearch($q, 'damaged_goods.code', $search, $exact);
                $this->applyTextSearch($q, 'damaged_goods.source_ref', $search, $exact, 'or');
                $q->orWhereHas('sourceWarehouse', function ($warehouseQ) use ($search, $exact) {
                    $this->applyTextSearch($warehouseQ, 'name', $search, $exact);
                    $this->applyTextSearch($warehouseQ, 'code', $search, $exact, 'or');
                })->orWhereHas('items', function ($itemQ) use ($search, $exact) {
                    $this->applyTextSearch($itemQ, 'reason_code', $search, $exact);
                })->orWhereHas('items.item', function ($itemQ) use ($search, $exact) {
                    $this->applyTextSearch($itemQ, 'sku', $search, $exact);
                    $this->applyTextSearch($itemQ, 'name', $search, $exact, 'or');
                });
            });
        }

        $reasonCode = trim((string) $request->input('reason_code', ''));
        if ($reasonCode !== '') {
            $query->whereHas('items', function ($itemQ) use ($reasonCode) {
                $itemQ->where('reason_code', $reasonCode);
            });
        }

        $recordsTotal = DamagedGood::count();
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $sourceLabels = $this->sourceLabels();
        $rows = $query->get();
        $remainingMap = DamagedStockService::remainingQtyMap(
            $rows->flatMap(fn ($row) => ($row->items ?? collect())->pluck('id'))->all()
        );

        $data = $rows->map(function ($row) use ($sourceLabels, $remainingMap) {
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
            $note = $row->note ?? '';
            $totalQty = (int) $items->sum('qty');
            $allocatedQty = (int) $items->sum(function ($item) use ($remainingMap) {
                return (int) ($remainingMap[(int) $item->id]['allocated_qty'] ?? 0);
            });
            $remainingQty = (int) $items->sum(function ($item) use ($remainingMap) {
                return (int) ($remainingMap[(int) $item->id]['remaining_qty'] ?? (int) $item->qty);
            });
            $reasonSummary = $this->formatReasonSummary($items);
            return [
                'id' => $row->id,
                'code' => $row->code,
                'source' => $sourceLabels[$row->source_type] ?? $row->source_type,
                'source_warehouse' => $row->sourceWarehouse?->name ?? $this->sourceWarehouseLabel($row),
                'source_ref' => $row->source_ref ?? '',
                'transacted_at' => $ts,
                'submit_by' => $row->creator?->name ?? '-',
                'item' => $itemLabel ?: '-',
                'qty' => $totalQty,
                'allocated_qty' => $allocatedQty,
                'remaining_qty' => $remainingQty,
                'reason_summary' => $reasonSummary,
                'note' => $note,
                'status' => $row->status ?? 'pending',
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function summaryBySku(Request $request)
    {
        $reasonCode = trim((string) $request->input('reason_code', ''));
        $search = trim((string) $request->input('q', ''));
        $exact = $this->isExactSearch($request);

        $baseRows = $this->aggregateSummaryBySku(
            DamagedStockService::remainingSourceLines(
                search: null,
                exact: false,
                reasonCode: $reasonCode !== '' ? $reasonCode : null
            )
        );

        $filteredRows = $this->aggregateSummaryBySku(
            DamagedStockService::remainingSourceLines(
                search: $search !== '' ? $search : null,
                exact: $exact,
                reasonCode: $reasonCode !== '' ? $reasonCode : null
            )
        );

        $recordsTotal = $baseRows->count();
        $recordsFiltered = $filteredRows->count();

        $start = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $filteredRows = $filteredRows->slice($start, $length)->values();
        }

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $filteredRows->values(),
        ]);
    }

    public function agingSummary(Request $request)
    {
        $reasonCode = trim((string) $request->input('reason_code', ''));
        $search = trim((string) $request->input('q', ''));

        $lines = DamagedStockService::remainingSourceLines(
            search: $search !== '' ? $search : null,
            exact: $this->isExactSearch($request),
            reasonCode: $reasonCode !== '' ? $reasonCode : null
        );

        $buckets = collect(DamagedStockService::ageBucketLabels())
            ->mapWithKeys(function ($label, $code) {
                return [
                    $code => [
                        'code' => $code,
                        'label' => $label,
                        'qty' => 0,
                        'lines' => 0,
                    ],
                ];
            });

        foreach ($lines as $line) {
            $bucketCode = $line['age_bucket'];
            $bucket = $buckets[$bucketCode] ?? null;
            if (!$bucket) {
                continue;
            }

            $bucket['qty'] += (int) $line['remaining_qty'];
            $bucket['lines'] += 1;
            $buckets[$bucketCode] = $bucket;
        }

        return response()->json([
            'total_remaining_qty' => (int) $lines->sum('remaining_qty'),
            'total_lines' => (int) $lines->count(),
            'total_skus' => (int) $lines->pluck('item_id')->unique()->count(),
            'buckets' => $buckets->values(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        $code = $this->generateCode('DMG');
        $transactedAt = $validated['transacted_at'] ?? now();

        DB::beginTransaction();
        try {
            $damage = DamagedGood::create([
                'code' => $code,
                'source_type' => $validated['source_type'],
                'source_warehouse_id' => $validated['source_warehouse_id'],
                'source_ref' => $validated['source_ref'] ?? null,
                'note' => $validated['note'] ?? null,
                'transacted_at' => $transactedAt,
                'created_by' => auth()->id(),
                'status' => 'pending',
            ]);

            foreach ($validated['items'] as $row) {
                DamagedGoodItem::create([
                    'damaged_good_id' => $damage->id,
                    'item_id' => $row['item_id'],
                    'qty' => $row['qty'],
                    'reason_code' => $row['reason_code'],
                    'note' => $row['note'] ?? null,
                ]);
            }

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menyimpan barang rusak',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Barang rusak berhasil disimpan dan menunggu approval.',
        ]);
    }

    public function show(int $id)
    {
        $damage = DamagedGood::with(['items', 'sourceWarehouse'])
            ->findOrFail($id);
        $remainingMap = DamagedStockService::remainingQtyMap($damage->items->pluck('id')->all());

        return response()->json([
            'id' => $damage->id,
            'code' => $damage->code,
            'source_type' => $damage->source_type,
            'source_warehouse_id' => $damage->source_warehouse_id,
            'source_warehouse' => $damage->sourceWarehouse?->name ?? $this->sourceWarehouseLabel($damage),
            'source_ref' => $damage->source_ref,
            'note' => $damage->note,
            'status' => $damage->status ?? 'pending',
            'transacted_at' => $damage->transacted_at?->format('Y-m-d H:i'),
            'items' => $damage->items->map(function ($row) use ($remainingMap) {
                $state = $remainingMap[(int) $row->id] ?? [
                    'allocated_qty' => 0,
                    'remaining_qty' => (int) $row->qty,
                ];
                return [
                    'item_id' => $row->item_id,
                    'qty' => (int) $row->qty,
                    'reason_code' => $row->reason_code ?: DamagedGoodItem::REASON_OTHER,
                    'reason_label' => DamagedGoodItem::reasonLabel($row->reason_code),
                    'note' => $row->note,
                    'allocated_qty' => (int) ($state['allocated_qty'] ?? 0),
                    'remaining_qty' => (int) ($state['remaining_qty'] ?? (int) $row->qty),
                ];
            })->values(),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $validated = $this->validatePayload($request);

        DB::beginTransaction();
        try {
            $damage = DamagedGood::findOrFail($id);
            if (($damage->status ?? 'pending') === 'approved') {
                DB::rollBack();
                return response()->json(['message' => 'Data sudah disetujui dan tidak bisa diubah'], 422);
            }

            StockService::rollbackBySource('damaged', $damage->id);
            StockMutation::where('source_type', 'damaged')
                ->where('source_id', $damage->id)
                ->delete();
            DamagedGoodItem::where('damaged_good_id', $damage->id)->delete();

            $damage->update([
                'source_type' => $validated['source_type'],
                'source_warehouse_id' => $validated['source_warehouse_id'],
                'source_ref' => $validated['source_ref'] ?? null,
                'note' => $validated['note'] ?? null,
                'transacted_at' => $validated['transacted_at'] ?? now(),
            ]);

            foreach ($validated['items'] as $row) {
                DamagedGoodItem::create([
                    'damaged_good_id' => $damage->id,
                    'item_id' => $row['item_id'],
                    'qty' => $row['qty'],
                    'reason_code' => $row['reason_code'],
                    'note' => $row['note'] ?? null,
                ]);
            }

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal memperbarui barang rusak',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Barang rusak berhasil diperbarui',
        ]);
    }

    public function destroy(int $id)
    {
        DB::beginTransaction();
        try {
            $damage = DamagedGood::findOrFail($id);
            if (($damage->status ?? 'pending') === 'approved') {
                DB::rollBack();
                return response()->json(['message' => 'Data sudah disetujui dan tidak bisa dihapus'], 422);
            }
            StockService::rollbackBySource('damaged', $damage->id);
            StockMutation::where('source_type', 'damaged')
                ->where('source_id', $damage->id)
                ->delete();
            DamagedGoodItem::where('damaged_good_id', $damage->id)->delete();
            $damage->delete();

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menghapus barang rusak',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Barang rusak berhasil dihapus',
        ]);
    }

    public function approve(int $id)
    {
        $successMessage = 'Barang rusak berhasil disetujui dan dipindahkan ke Gudang Rusak';

        DB::beginTransaction();
        try {
            $damage = DamagedGood::with(['items', 'sourceWarehouse'])
                ->lockForUpdate()
                ->findOrFail($id);

            if (($damage->status ?? 'pending') === 'approved') {
                DB::rollBack();
                return response()->json(['message' => 'Data sudah disetujui']);
            }

            $approvedAt = now();
            $sourceWarehouseId = $this->resolveSourceWarehouseId($damage);
            $damagedWarehouseId = WarehouseService::damagedWarehouseId();
            $damagedWarehouse = $damagedWarehouseId > 0 ? Warehouse::find($damagedWarehouseId) : null;
            if ($sourceWarehouseId <= 0) {
                throw ValidationException::withMessages([
                    'source_warehouse_id' => 'Gudang asal barang rusak tidak valid.',
                ]);
            }
            if (!$damagedWarehouse) {
                throw ValidationException::withMessages([
                    'source' => 'Gudang Rusak belum tersedia. Jalankan migrasi terbaru terlebih dahulu.',
                ]);
            }
            if ($sourceWarehouseId === $damagedWarehouseId) {
                throw ValidationException::withMessages([
                    'source_warehouse_id' => 'Gudang asal tidak boleh sama dengan Gudang Rusak.',
                ]);
            }

            $hasMutations = StockMutation::where('source_type', 'damaged')
                ->where('source_id', $damage->id)
                ->exists();

            if (!$hasMutations) {
                foreach ($damage->items as $row) {
                    StockService::mutate([
                        'item_id' => $row->item_id,
                        'warehouse_id' => $sourceWarehouseId,
                        'direction' => 'out',
                        'qty' => (int) $row->qty,
                        'source_type' => 'damaged',
                        'source_subtype' => 'intake_out',
                        'source_id' => $damage->id,
                        'source_code' => $damage->code,
                        'note' => $row->note,
                        'occurred_at' => $approvedAt,
                        'created_by' => auth()->id(),
                    ]);

                    StockService::mutate([
                        'item_id' => $row->item_id,
                        'warehouse_id' => $damagedWarehouseId,
                        'direction' => 'in',
                        'qty' => (int) $row->qty,
                        'source_type' => 'damaged',
                        'source_subtype' => 'intake_in',
                        'source_id' => $damage->id,
                        'source_code' => $damage->code,
                        'note' => $row->note,
                        'occurred_at' => $approvedAt,
                        'created_by' => auth()->id(),
                    ]);
                }
            }

            $damage->status = 'approved';
            $damage->approved_at = $approvedAt;
            $damage->approved_by = auth()->id();
            $damage->save();
            $successMessage = 'Barang rusak berhasil disetujui dan dipindahkan ke '.$damagedWarehouse->name;

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Gagal menyetujui barang rusak',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json(['message' => $successMessage]);
    }

    private function validatePayload(Request $request): array
    {
        $damagedWarehouseId = WarehouseService::damagedWarehouseId();
        $validated = $request->validate([
            'source_type' => ['required', 'string', Rule::in(DamagedGood::creatableSourceTypes())],
            'source_warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'source_ref' => ['nullable', 'string', 'max:100'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.reason_code' => ['required', 'string', Rule::in(array_keys(DamagedGoodItem::reasonLabels()))],
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
                    'reason_code' => (string) ($row['reason_code'] ?? DamagedGoodItem::REASON_OTHER),
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
                'items' => 'Item tidak boleh duplikat pada barang rusak',
            ]);
        }

        if ((int) $validated['source_warehouse_id'] === $damagedWarehouseId) {
            throw ValidationException::withMessages([
                'source_warehouse_id' => 'Gudang asal tidak boleh sama dengan Gudang Rusak.',
            ]);
        }

        BundleService::assertPhysicalItems(
            $items->pluck('item_id')->all(),
            'Bundle tidak bisa digunakan pada intake barang rusak karena tidak memiliki stok fisik.'
        );

        $validated['items'] = $items->all();
        $validated['source_warehouse_id'] = (int) $validated['source_warehouse_id'];
        if (!empty($validated['transacted_at'])) {
            $validated['transacted_at'] = Carbon::parse($validated['transacted_at']);
        } else {
            $validated['transacted_at'] = null;
        }

        return $validated;
    }

    private function generateCode(string $prefix): string
    {
        return $prefix.'-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));
    }

    private function sourceLabels(): array
    {
        return DamagedGood::sourceLabels();
    }

    private function resolveSourceWarehouseId(DamagedGood $damage): int
    {
        $warehouseId = (int) ($damage->source_warehouse_id ?? 0);
        if ($warehouseId > 0) {
            return $warehouseId;
        }

        return match ($damage->source_type) {
            DamagedGood::SOURCE_LEGACY_DISPLAY => WarehouseService::displayWarehouseId(),
            DamagedGood::SOURCE_CUSTOMER_RETURN => 0,
            default => WarehouseService::defaultWarehouseId(),
        };
    }

    private function sourceWarehouseLabel(DamagedGood $damage): string
    {
        if ((string) $damage->source_type === DamagedGood::SOURCE_CUSTOMER_RETURN) {
            return 'Retur Customer';
        }

        $warehouseId = $this->resolveSourceWarehouseId($damage);

        return Warehouse::where('id', $warehouseId)->value('name') ?? '-';
    }

    private function aggregateSummaryBySku(Collection $lines): Collection
    {
        return $lines
            ->groupBy('item_id')
            ->map(function (Collection $rows) {
                $first = $rows->first();
                $oldestLine = $rows->sortBy('damage_transacted_at')->first();
                $oldestAt = $oldestLine['damage_transacted_at'] ?? null;
                $oldestAt = $oldestAt instanceof Carbon ? $oldestAt : ($oldestAt ? Carbon::parse($oldestAt) : null);
                $ageDays = $oldestLine['age_days'] ?? 0;

                return [
                    'item_id' => (int) $first['item_id'],
                    'sku' => (string) $first['item_sku'],
                    'item_name' => (string) $first['item_name'],
                    'doc_count' => (int) $rows->pluck('damaged_good_id')->unique()->count(),
                    'intake_qty' => (int) $rows->sum('received_qty'),
                    'allocated_qty' => (int) $rows->sum('allocated_qty'),
                    'remaining_qty' => (int) $rows->sum('remaining_qty'),
                    'reason_summary' => $this->formatReasonSummary($rows),
                    'oldest_transacted_at' => $oldestAt?->format('Y-m-d H:i') ?? '-',
                    'age_days' => (int) $ageDays,
                    'age_bucket' => DamagedStockService::ageBucketLabels()[$oldestLine['age_bucket'] ?? DamagedStockService::AGE_BUCKET_0_7] ?? '-',
                ];
            })
            ->sortByDesc('remaining_qty')
            ->values();
    }

    private function formatReasonSummary(iterable $rows): string
    {
        $labels = collect($rows)
            ->map(function ($row) {
                if ($row instanceof DamagedGoodItem) {
                    return DamagedGoodItem::reasonLabel($row->reason_code);
                }

                return DamagedGoodItem::reasonLabel($row['reason_code'] ?? null);
            })
            ->filter()
            ->unique()
            ->values();

        if ($labels->isEmpty()) {
            return '-';
        }

        $summary = $labels->take(2)->implode(', ');
        if ($labels->count() > 2) {
            $summary .= ' +'.($labels->count() - 2).' lainnya';
        }

        return $summary;
    }
}
