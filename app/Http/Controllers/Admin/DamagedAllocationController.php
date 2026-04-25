<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DamagedAllocation;
use App\Models\DamagedAllocationItem;
use App\Models\DamagedGoodItem;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\ReworkRecipe;
use App\Models\StockMutation;
use App\Models\Supplier;
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

class DamagedAllocationController extends Controller
{
    public function index()
    {
        $items = Item::query()
            ->where('item_type', Item::TYPE_SINGLE)
            ->orderBy('name')
            ->get(['id', 'sku', 'name']);
        $suppliers = Supplier::orderBy('name')->get(['id', 'name']);
        $damagedWarehouseId = WarehouseService::damagedWarehouseId();
        $damagedWarehouseLabel = Warehouse::where('id', $damagedWarehouseId)->value('name') ?? 'Gudang Rusak';
        $targetWarehouses = Warehouse::query()
            ->where('id', '!=', $damagedWarehouseId)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return view('admin.inventory.damaged-allocations.index', [
            'items' => $items,
            'suppliers' => $suppliers,
            'targetWarehouses' => $targetWarehouses,
            'damagedWarehouseLabel' => $damagedWarehouseLabel,
            'defaultTargetWarehouseId' => WarehouseService::defaultWarehouseId(),
            'dataUrl' => route('admin.inventory.damaged-allocations.data'),
            'sourceItemsUrl' => route('admin.inventory.damaged-allocations.source-items'),
            'recipeOptionsUrl' => route('admin.inventory.rework-recipes.options'),
            'storeUrl' => route('admin.inventory.damaged-allocations.store'),
        ]);
    }

    public function sourceItems(Request $request)
    {
        $search = trim((string) $request->input('q', ''));
        $items = DamagedStockService::availableSourceLines($search, null, $this->isExactSearch($request));

        return response()->json([
            'data' => $items->all(),
        ]);
    }

    public function data(Request $request)
    {
        $query = DamagedAllocation::query()
            ->with([
                'sourceItems.item',
                'outputItems.item',
                'supplier',
                'targetWarehouse',
                'recipe',
                'creator',
            ])
            ->orderBy('transacted_at', 'desc');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $exact = $this->isExactSearch($request);
            $query->where(function ($q) use ($search, $exact) {
                $this->applyTextSearch($q, 'damaged_allocations.code', $search, $exact);
                $this->applyTextSearch($q, 'damaged_allocations.source_ref', $search, $exact, 'or');
                $this->applyTextSearch($q, 'damaged_allocations.note', $search, $exact, 'or');
                $q->orWhereHas('supplier', function ($supplierQ) use ($search, $exact) {
                    $this->applyTextSearch($supplierQ, 'name', $search, $exact);
                })->orWhereHas('targetWarehouse', function ($warehouseQ) use ($search, $exact) {
                    $this->applyTextSearch($warehouseQ, 'name', $search, $exact);
                    $this->applyTextSearch($warehouseQ, 'code', $search, $exact, 'or');
                })->orWhereHas('recipe', function ($recipeQ) use ($search, $exact) {
                    $this->applyTextSearch($recipeQ, 'name', $search, $exact);
                    $this->applyTextSearch($recipeQ, 'code', $search, $exact, 'or');
                })->orWhereHas('sourceItems.item', function ($itemQ) use ($search, $exact) {
                    $this->applyTextSearch($itemQ, 'sku', $search, $exact);
                    $this->applyTextSearch($itemQ, 'name', $search, $exact, 'or');
                })->orWhereHas('outputItems.item', function ($itemQ) use ($search, $exact) {
                    $this->applyTextSearch($itemQ, 'sku', $search, $exact);
                    $this->applyTextSearch($itemQ, 'name', $search, $exact, 'or');
                });
            });
        }

        $typeFilter = trim((string) $request->input('type', ''));
        if (in_array($typeFilter, ['return_supplier', 'disposal', 'rework'], true)) {
            $query->where('type', $typeFilter);
        }

        $statusFilter = trim((string) $request->input('status', ''));
        if (in_array($statusFilter, ['pending', 'approved'], true)) {
            $query->where('status', $statusFilter);
        }

        $this->applyDateFilter($query, $request);

        $recordsTotal = DamagedAllocation::count();
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $typeLabels = $this->typeLabels();
        $data = $query->get()->map(function (DamagedAllocation $row) use ($typeLabels) {
            $sourceLabel = ($row->sourceItems ?? collect())->map(function ($item) {
                $sku = trim((string) ($item->item?->sku ?? ''));
                if ($sku === '') {
                    return '';
                }

                return sprintf('%s (%d)', $sku, (int) ($item->qty ?? 0));
            })->filter()->values()->implode(', ');

            $outputLabel = ($row->outputItems ?? collect())->map(function ($item) {
                $sku = trim((string) ($item->item?->sku ?? ''));
                if ($sku === '') {
                    return '';
                }

                return sprintf('%s (%d)', $sku, (int) ($item->qty ?? 0));
            })->filter()->values()->implode(', ');

            $targetLabel = match ($row->type) {
                'return_supplier' => $row->supplier?->name ?? '-',
                'rework' => $row->recipe
                    ? trim(($row->recipe->code ?? '').' - '.($row->recipe->name ?? '')).($row->targetWarehouse ? ' | '.$row->targetWarehouse->name : '')
                    : ($row->targetWarehouse?->name ?? '-'),
                default => '-',
            };

            return [
                'id' => $row->id,
                'code' => $row->code,
                'type' => $typeLabels[$row->type] ?? $row->type,
                'type_raw' => $row->type ?? '',
                'status' => $row->status ?? 'pending',
                'transacted_at' => $row->transacted_at?->format('Y-m-d H:i') ?? '',
                'submit_by' => $row->creator?->name ?? '-',
                'source_items' => $sourceLabel ?: '-',
                'output_items' => $outputLabel ?: '-',
                'target' => $targetLabel,
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
        $allocation = DamagedAllocation::with([
            'sourceItems.item',
            'sourceItems.damagedGoodItem.damagedGood.sourceWarehouse',
            'outputItems.item',
            'supplier',
            'targetWarehouse',
            'recipe.inputItems.item',
            'recipe.outputItems.item',
            'recipe.targetWarehouse',
        ])->findOrFail($id);

        $remainingMap = DamagedStockService::remainingQtyMap(
            $allocation->sourceItems->pluck('damaged_good_item_id')->all()
        );

        return response()->json([
            'id' => $allocation->id,
            'code' => $allocation->code,
            'type' => $allocation->type,
            'recipe_id' => $allocation->recipe_id,
            'recipe_multiplier' => (int) ($allocation->recipe_multiplier ?? 1),
            'recipe' => $this->serializeRecipe($allocation->recipe),
            'supplier_id' => $allocation->supplier_id,
            'target_warehouse_id' => $allocation->target_warehouse_id,
            'source_ref' => $allocation->source_ref,
            'note' => $allocation->note,
            'status' => $allocation->status ?? 'pending',
            'transacted_at' => $allocation->transacted_at?->format('Y-m-d H:i'),
            'source_items' => $allocation->sourceItems->map(function ($row) use ($remainingMap) {
                $state = $remainingMap[(int) ($row->damaged_good_item_id ?? 0)] ?? [
                    'received_qty' => (int) $row->qty,
                    'allocated_qty' => 0,
                    'remaining_qty' => (int) $row->qty,
                ];
                $damage = $row->damagedGoodItem?->damagedGood;
                $sourceWarehouse = $damage?->sourceWarehouse?->name ?? '-';

                return [
                    'damaged_good_item_id' => $row->damaged_good_item_id,
                    'item_id' => $row->item_id,
                    'item_label' => trim(($row->item?->sku ?? '').' - '.($row->item?->name ?? '')),
                    'qty' => (int) $row->qty,
                    'note' => $row->note ?? '',
                    'damage_code' => $damage?->code,
                    'source_warehouse' => $sourceWarehouse,
                    'received_qty' => (int) ($state['received_qty'] ?? 0),
                    'allocated_qty' => (int) ($state['allocated_qty'] ?? 0),
                    'remaining_qty' => (int) ($state['remaining_qty'] ?? 0),
                    'option_label' => sprintf(
                        '%s | Intake %s | Sisa %d',
                        trim(($row->item?->sku ?? '').' - '.($row->item?->name ?? '')),
                        $damage?->code ?? '-',
                        (int) ($state['remaining_qty'] ?? 0)
                    ),
                ];
            })->values(),
            'output_items' => $allocation->outputItems->map(function ($row) {
                return [
                    'item_id' => $row->item_id,
                    'qty' => (int) $row->qty,
                    'note' => $row->note ?? '',
                ];
            })->values(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        DB::beginTransaction();
        try {
            $allocation = DamagedAllocation::create([
                'code' => $this->generateCode('DGA'),
                'type' => $validated['type'],
                'recipe_id' => $validated['recipe_id'],
                'recipe_multiplier' => $validated['recipe_multiplier'],
                'supplier_id' => $validated['supplier_id'],
                'target_warehouse_id' => $validated['target_warehouse_id'],
                'source_ref' => $validated['source_ref'] ?? null,
                'note' => $validated['note'] ?? null,
                'transacted_at' => $validated['transacted_at'] ?? now(),
                'created_by' => auth()->id(),
                'status' => 'pending',
            ]);

            $this->persistItems($allocation, $validated);

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Gagal menyimpan alokasi barang rusak',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Alokasi barang rusak berhasil disimpan dan menunggu approval.',
        ]);
    }

    public function update(Request $request, int $id)
    {
        $validated = $this->validatePayload($request);

        DB::beginTransaction();
        try {
            $allocation = DamagedAllocation::findOrFail($id);
            if (($allocation->status ?? 'pending') === 'approved') {
                DB::rollBack();
                return response()->json(['message' => 'Data sudah disetujui dan tidak bisa diubah'], 422);
            }

            StockService::rollbackBySource('damaged_allocation', $allocation->id);
            StockMutation::where('source_type', 'damaged_allocation')
                ->where('source_id', $allocation->id)
                ->delete();
            DamagedAllocationItem::where('damaged_allocation_id', $allocation->id)->delete();

            $allocation->update([
                'type' => $validated['type'],
                'recipe_id' => $validated['recipe_id'],
                'recipe_multiplier' => $validated['recipe_multiplier'],
                'supplier_id' => $validated['supplier_id'],
                'target_warehouse_id' => $validated['target_warehouse_id'],
                'source_ref' => $validated['source_ref'] ?? null,
                'note' => $validated['note'] ?? null,
                'transacted_at' => $validated['transacted_at'] ?? now(),
            ]);

            $this->persistItems($allocation, $validated);

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Gagal memperbarui alokasi barang rusak',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Alokasi barang rusak berhasil diperbarui',
        ]);
    }

    public function destroy(int $id)
    {
        DB::beginTransaction();
        try {
            $allocation = DamagedAllocation::findOrFail($id);
            if (($allocation->status ?? 'pending') === 'approved') {
                DB::rollBack();
                return response()->json(['message' => 'Data sudah disetujui dan tidak bisa dihapus'], 422);
            }

            StockService::rollbackBySource('damaged_allocation', $allocation->id);
            StockMutation::where('source_type', 'damaged_allocation')
                ->where('source_id', $allocation->id)
                ->delete();
            $allocation->delete();

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Gagal menghapus alokasi barang rusak',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Alokasi barang rusak berhasil dihapus',
        ]);
    }

    public function approve(int $id)
    {
        DB::beginTransaction();
        try {
            $allocation = DamagedAllocation::with([
                'sourceItems.item',
                'sourceItems.damagedGoodItem.damagedGood',
                'outputItems.item',
                'supplier',
                'targetWarehouse',
                'recipe.inputItems.item',
                'recipe.outputItems.item',
                'recipe.targetWarehouse',
            ])->lockForUpdate()->findOrFail($id);

            if (($allocation->status ?? 'pending') === 'approved') {
                DB::rollBack();
                return response()->json(['message' => 'Data sudah disetujui']);
            }

            $damagedWarehouseId = WarehouseService::damagedWarehouseId();
            $damagedWarehouse = $damagedWarehouseId > 0 ? Warehouse::find($damagedWarehouseId) : null;
            if (!$damagedWarehouse) {
                throw ValidationException::withMessages([
                    'source' => 'Gudang Rusak belum tersedia. Jalankan migrasi terbaru terlebih dahulu.',
                ]);
            }

            $sourceItems = $allocation->sourceItems ?? collect();
            if ($sourceItems->isEmpty()) {
                throw ValidationException::withMessages([
                    'source_items' => 'Minimal 1 item sumber barang rusak diperlukan.',
                ]);
            }

            $remainingMap = DamagedStockService::remainingQtyMap($sourceItems->pluck('damaged_good_item_id')->all());
            foreach ($sourceItems as $row) {
                $damagedGoodItem = $row->damagedGoodItem;
                if (!$damagedGoodItem || ($damagedGoodItem->damagedGood?->status ?? null) !== 'approved') {
                    throw ValidationException::withMessages([
                        'source_items' => 'Sumber barang rusak tidak valid atau belum disetujui.',
                    ]);
                }

                $remaining = (int) ($remainingMap[(int) $damagedGoodItem->id]['remaining_qty'] ?? 0);
                if ((int) $row->qty > $remaining) {
                    throw ValidationException::withMessages([
                        'source_items' => sprintf(
                            'Sisa stok rusak untuk %s tidak mencukupi. Sisa saat ini %d.',
                            $row->item?->sku ?? 'item',
                            $remaining
                        ),
                    ]);
                }
            }

            if ($allocation->type === 'return_supplier' && !$allocation->supplier_id) {
                throw ValidationException::withMessages([
                    'supplier_id' => 'Supplier wajib dipilih untuk retur supplier.',
                ]);
            }

            if ($allocation->type === 'rework') {
                [$targetWarehouseId] = $this->resolveReworkContext(
                    $allocation->recipe,
                    (int) ($allocation->recipe_multiplier ?? 1),
                    $sourceItems,
                    $allocation->outputItems ?? collect(),
                    (int) ($allocation->target_warehouse_id ?? 0),
                    true
                );
                if ((int) ($allocation->target_warehouse_id ?? 0) !== $targetWarehouseId) {
                    $allocation->target_warehouse_id = $targetWarehouseId;
                }
            }

            $this->assertDamagedWarehouseStockAvailable($sourceItems, $damagedWarehouseId);

            $hasMutations = StockMutation::where('source_type', 'damaged_allocation')
                ->where('source_id', $allocation->id)
                ->exists();

            $approvedAt = now();
            if (!$hasMutations) {
                foreach ($sourceItems as $row) {
                    StockService::mutate([
                        'item_id' => $row->item_id,
                        'warehouse_id' => $damagedWarehouseId,
                        'direction' => 'out',
                        'qty' => (int) $row->qty,
                        'source_type' => 'damaged_allocation',
                        'source_subtype' => $this->sourceMutationSubtype((string) $allocation->type),
                        'source_id' => $allocation->id,
                        'source_code' => $allocation->code,
                        'note' => $row->note,
                        'occurred_at' => $approvedAt,
                        'created_by' => auth()->id(),
                    ]);
                }

                if ($allocation->type === 'rework') {
                    foreach ($allocation->outputItems as $row) {
                        StockService::mutate([
                            'item_id' => $row->item_id,
                            'warehouse_id' => $allocation->target_warehouse_id,
                            'direction' => 'in',
                            'qty' => (int) $row->qty,
                            'source_type' => 'damaged_allocation',
                            'source_subtype' => 'rework_output',
                            'source_id' => $allocation->id,
                            'source_code' => $allocation->code,
                            'note' => $row->note,
                            'occurred_at' => $approvedAt,
                            'created_by' => auth()->id(),
                        ]);
                    }
                }
            }

            $allocation->status = 'approved';
            $allocation->approved_at = $approvedAt;
            $allocation->approved_by = auth()->id();
            $allocation->save();

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Gagal menyetujui alokasi barang rusak',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Alokasi barang rusak berhasil disetujui',
        ]);
    }

    private function sourceMutationSubtype(string $allocationType): string
    {
        return match ($allocationType) {
            'return_supplier' => 'supplier_source',
            'disposal' => 'disposal_source',
            'rework' => 'rework_source',
            default => 'source',
        };
    }

    private function assertDamagedWarehouseStockAvailable(Collection $sourceItems, int $damagedWarehouseId): void
    {
        $requirements = $sourceItems
            ->groupBy('item_id')
            ->map(fn (Collection $rows) => (int) $rows->sum('qty'))
            ->filter(fn (int $qty) => $qty > 0);

        if ($requirements->isEmpty()) {
            return;
        }

        $stocks = ItemStock::query()
            ->where('warehouse_id', $damagedWarehouseId)
            ->whereIn('item_id', $requirements->keys()->all())
            ->lockForUpdate()
            ->get()
            ->keyBy('item_id');

        $itemLabels = $sourceItems
            ->mapWithKeys(function ($row) {
                $sku = trim((string) ($row->item?->sku ?? ''));
                $name = trim((string) ($row->item?->name ?? ''));

                return [
                    (int) $row->item_id => trim($sku.($name !== '' ? ' - '.$name : '')),
                ];
            });

        foreach ($requirements as $itemId => $requiredQty) {
            $availableQty = (int) ($stocks->get((int) $itemId)?->stock ?? 0);
            if ($availableQty >= $requiredQty) {
                continue;
            }

            $label = $itemLabels->get((int) $itemId) ?: 'item';
            throw ValidationException::withMessages([
                'source_items' => sprintf(
                    'Stok fisik Gudang Rusak untuk %s tidak mencukupi. Dibutuhkan %d, tersedia %d. Cek mutasi stok atau jalankan migrasi backfill stok rusak.',
                    $label,
                    $requiredQty,
                    $availableQty
                ),
            ]);
        }
    }

    private function persistItems(DamagedAllocation $allocation, array $validated): void
    {
        foreach ($validated['source_items'] as $row) {
            DamagedAllocationItem::create([
                'damaged_allocation_id' => $allocation->id,
                'line_type' => 'source',
                'damaged_good_item_id' => $row['damaged_good_item_id'],
                'item_id' => $row['item_id'],
                'qty' => $row['qty'],
                'note' => $row['note'] ?? null,
            ]);
        }

        foreach ($validated['output_items'] as $row) {
            DamagedAllocationItem::create([
                'damaged_allocation_id' => $allocation->id,
                'line_type' => 'output',
                'damaged_good_item_id' => null,
                'item_id' => $row['item_id'],
                'qty' => $row['qty'],
                'note' => $row['note'] ?? null,
            ]);
        }
    }

    private function validatePayload(Request $request): array
    {
        $typeLabels = array_keys($this->typeLabels());
        $damagedWarehouseId = WarehouseService::damagedWarehouseId();

        $validated = $request->validate([
            'type' => ['required', 'string', Rule::in($typeLabels)],
            'recipe_id' => ['nullable', 'integer', 'exists:rework_recipes,id'],
            'recipe_multiplier' => ['nullable', 'integer', 'min:1'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'target_warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'source_ref' => ['nullable', 'string', 'max:100'],
            'source_items' => ['required', 'array', 'min:1'],
            'source_items.*.damaged_good_item_id' => ['required', 'integer', 'exists:damaged_good_items,id'],
            'source_items.*.qty' => ['required', 'integer', 'min:1'],
            'source_items.*.note' => ['nullable', 'string'],
            'output_items' => ['nullable', 'array'],
            'output_items.*.item_id' => ['required_with:output_items', 'integer', 'exists:items,id'],
            'output_items.*.qty' => ['required_with:output_items', 'integer', 'min:1'],
            'output_items.*.note' => ['nullable', 'string'],
            'note' => ['nullable', 'string'],
            'transacted_at' => ['required', 'date'],
        ]);

        $sourceItems = collect($validated['source_items'] ?? [])
            ->filter(fn ($row) => (int) ($row['damaged_good_item_id'] ?? 0) > 0 && (int) ($row['qty'] ?? 0) > 0)
            ->map(fn ($row) => [
                'damaged_good_item_id' => (int) $row['damaged_good_item_id'],
                'qty' => (int) $row['qty'],
                'note' => $row['note'] ?? null,
            ])->values();

        if ($sourceItems->isEmpty()) {
            throw ValidationException::withMessages([
                'source_items' => 'Minimal 1 item sumber barang rusak diperlukan.',
            ]);
        }

        if ($sourceItems->groupBy('damaged_good_item_id')->filter(fn ($rows) => $rows->count() > 1)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'source_items' => 'Sumber barang rusak tidak boleh duplikat.',
            ]);
        }

        $damagedItems = DamagedGoodItem::with(['damagedGood', 'item'])
            ->whereIn('id', $sourceItems->pluck('damaged_good_item_id')->all())
            ->get()
            ->keyBy('id');
        if ($damagedItems->count() !== $sourceItems->count()) {
            throw ValidationException::withMessages([
                'source_items' => 'Ada sumber barang rusak yang tidak valid.',
            ]);
        }

        $remainingMap = DamagedStockService::remainingQtyMap($sourceItems->pluck('damaged_good_item_id')->all());
        $sourceItems = $sourceItems->map(function ($row) use ($damagedItems, $remainingMap) {
            $damagedItem = $damagedItems->get($row['damaged_good_item_id']);
            if (!$damagedItem || ($damagedItem->damagedGood?->status ?? null) !== 'approved') {
                throw ValidationException::withMessages([
                    'source_items' => 'Sumber barang rusak harus berasal dari intake yang sudah disetujui.',
                ]);
            }

            $remainingQty = (int) ($remainingMap[$damagedItem->id]['remaining_qty'] ?? 0);
            if ((int) $row['qty'] > $remainingQty) {
                throw ValidationException::withMessages([
                    'source_items' => sprintf(
                        'Sisa stok rusak untuk %s tidak mencukupi. Sisa saat ini %d.',
                        $damagedItem->item?->sku ?? 'item',
                        $remainingQty
                    ),
                ]);
            }

            return [
                'damaged_good_item_id' => (int) $damagedItem->id,
                'item_id' => (int) $damagedItem->item_id,
                'qty' => (int) $row['qty'],
                'note' => $row['note'] ?? null,
            ];
        })->values();

        $outputItems = collect($validated['output_items'] ?? [])
            ->filter(fn ($row) => (int) ($row['item_id'] ?? 0) > 0 && (int) ($row['qty'] ?? 0) > 0)
            ->map(fn ($row) => [
                'item_id' => (int) $row['item_id'],
                'qty' => (int) $row['qty'],
                'note' => $row['note'] ?? null,
            ])->values();

        if ($outputItems->isNotEmpty()) {
            BundleService::assertPhysicalItems(
                $outputItems->pluck('item_id')->all(),
                'Bundle tidak bisa digunakan sebagai hasil alokasi barang rusak karena tidak memiliki stok fisik.',
                'output_items'
            );
        }

        $type = $validated['type'];
        $recipe = $this->loadRecipe(!empty($validated['recipe_id']) ? (int) $validated['recipe_id'] : null);
        $recipeMultiplier = !empty($validated['recipe_multiplier']) ? (int) $validated['recipe_multiplier'] : 1;
        $targetWarehouseId = !empty($validated['target_warehouse_id']) ? (int) $validated['target_warehouse_id'] : null;

        if ($type === 'return_supplier') {
            if (empty($validated['supplier_id'])) {
                throw ValidationException::withMessages([
                    'supplier_id' => 'Supplier wajib dipilih untuk retur supplier.',
                ]);
            }
            if ($targetWarehouseId) {
                throw ValidationException::withMessages([
                    'target_warehouse_id' => 'Gudang hasil hanya digunakan untuk rework SKU.',
                ]);
            }
            if ($outputItems->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'output_items' => 'Retur supplier tidak boleh memiliki item hasil.',
                ]);
            }
            if ($recipe) {
                throw ValidationException::withMessages([
                    'recipe_id' => 'Resep rework hanya digunakan untuk tipe rework SKU.',
                ]);
            }
            $recipeMultiplier = null;
        }

        if ($type === 'disposal') {
            if (!empty($validated['supplier_id'])) {
                throw ValidationException::withMessages([
                    'supplier_id' => 'Supplier tidak digunakan untuk disposal.',
                ]);
            }
            if ($targetWarehouseId) {
                throw ValidationException::withMessages([
                    'target_warehouse_id' => 'Gudang hasil hanya digunakan untuk rework SKU.',
                ]);
            }
            if ($outputItems->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'output_items' => 'Disposal tidak boleh memiliki item hasil.',
                ]);
            }
            if ($recipe) {
                throw ValidationException::withMessages([
                    'recipe_id' => 'Resep rework hanya digunakan untuk tipe rework SKU.',
                ]);
            }
            $recipeMultiplier = null;
        }

        if ($type === 'rework') {
            if (!empty($validated['supplier_id'])) {
                throw ValidationException::withMessages([
                    'supplier_id' => 'Supplier tidak digunakan untuk rework SKU.',
                ]);
            }

            [$resolvedWarehouseId, $resolvedOutputs] = $this->resolveReworkContext(
                $recipe,
                $recipeMultiplier,
                $sourceItems,
                $outputItems,
                $targetWarehouseId ?? 0,
                false
            );

            $targetWarehouseId = $resolvedWarehouseId;
            $outputItems = collect($resolvedOutputs);

            if ($targetWarehouseId === $damagedWarehouseId) {
                throw ValidationException::withMessages([
                    'target_warehouse_id' => 'Hasil rework tidak boleh kembali ke Gudang Rusak.',
                ]);
            }
        } else {
            $recipe = null;
        }

        $validated['source_items'] = $sourceItems->all();
        $validated['output_items'] = $outputItems->all();
        $validated['recipe_id'] = $recipe?->id;
        $validated['recipe_multiplier'] = $recipe ? $recipeMultiplier : null;
        $validated['supplier_id'] = !empty($validated['supplier_id']) ? (int) $validated['supplier_id'] : null;
        $validated['target_warehouse_id'] = $targetWarehouseId;
        $validated['transacted_at'] = !empty($validated['transacted_at'])
            ? Carbon::parse($validated['transacted_at'])
            : null;

        return $validated;
    }

    private function resolveReworkContext(
        ?ReworkRecipe $recipe,
        int $recipeMultiplier,
        Collection $sourceItems,
        Collection $outputItems,
        int $targetWarehouseId,
        bool $fromApproval
    ): array {
        if ($recipe) {
            if (!$fromApproval && !$recipe->is_active) {
                throw ValidationException::withMessages([
                    'recipe_id' => 'Resep rework yang dipilih sudah nonaktif.',
                ]);
            }

            if (($recipe->inputItems ?? collect())->isEmpty() || ($recipe->outputItems ?? collect())->isEmpty()) {
                throw ValidationException::withMessages([
                    'recipe_id' => 'Resep rework harus memiliki BOM input dan output.',
                ]);
            }

            $this->validateRecipeComposition($recipe, $recipeMultiplier, $sourceItems, $outputItems, $fromApproval);
            $targetWarehouseId = $targetWarehouseId > 0
                ? $targetWarehouseId
                : (int) ($recipe->target_warehouse_id ?? 0);
            if ($targetWarehouseId <= 0) {
                throw ValidationException::withMessages([
                    'target_warehouse_id' => 'Gudang hasil rework wajib dipilih atau diisi di resep.',
                ]);
            }

            return [$targetWarehouseId, $this->deriveRecipeOutputs($recipe, $recipeMultiplier)];
        }

        if ($targetWarehouseId <= 0) {
            throw ValidationException::withMessages([
                'target_warehouse_id' => 'Gudang hasil rework wajib dipilih.',
            ]);
        }
        if ($outputItems->isEmpty()) {
            throw ValidationException::withMessages([
                'output_items' => 'Minimal 1 item hasil rework diperlukan.',
            ]);
        }
        if ($outputItems->groupBy('item_id')->filter(fn ($rows) => $rows->count() > 1)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'output_items' => 'Item hasil rework tidak boleh duplikat.',
            ]);
        }

        return [$targetWarehouseId, $outputItems->values()->all()];
    }

    private function validateRecipeComposition(
        ReworkRecipe $recipe,
        int $recipeMultiplier,
        Collection $sourceItems,
        Collection $outputItems,
        bool $fromApproval
    ): void {
        $expectedInputs = $recipe->inputItems->map(fn ($row) => [
            'item_id' => (int) $row->item_id,
            'qty' => (int) $row->qty * $recipeMultiplier,
        ]);
        $expectedInputMap = $this->normalizeItemQtyMap($expectedInputs);
        $selectedInputMap = $this->normalizeItemQtyMap($sourceItems);
        if ($expectedInputMap !== $selectedInputMap) {
            throw ValidationException::withMessages([
                'source_items' => 'Komposisi sumber barang rusak tidak sesuai resep rework yang dipilih.',
            ]);
        }

        if ($fromApproval && $outputItems->isNotEmpty()) {
            $expectedOutputMap = $this->normalizeItemQtyMap($this->deriveRecipeOutputsCollection($recipe, $recipeMultiplier));
            $storedOutputMap = $this->normalizeItemQtyMap($outputItems);
            if ($expectedOutputMap !== $storedOutputMap) {
                throw ValidationException::withMessages([
                    'output_items' => 'Output rework tidak sesuai dengan resep yang tersimpan.',
                ]);
            }
        }
    }

    private function deriveRecipeOutputs(ReworkRecipe $recipe, int $recipeMultiplier): array
    {
        return $this->deriveRecipeOutputsCollection($recipe, $recipeMultiplier)->values()->all();
    }

    private function deriveRecipeOutputsCollection(ReworkRecipe $recipe, int $recipeMultiplier): Collection
    {
        return ($recipe->outputItems ?? collect())->map(function ($row) use ($recipeMultiplier, $recipe) {
            return [
                'item_id' => (int) $row->item_id,
                'qty' => (int) $row->qty * $recipeMultiplier,
                'note' => $row->note ?: 'Derived from recipe '.$recipe->code,
            ];
        });
    }

    private function normalizeItemQtyMap(Collection $rows): array
    {
        return $rows->groupBy('item_id')
            ->map(fn ($group) => (int) $group->sum('qty'))
            ->sortKeys()
            ->all();
    }

    private function loadRecipe(?int $recipeId): ?ReworkRecipe
    {
        if (!$recipeId || $recipeId <= 0) {
            return null;
        }

        return ReworkRecipe::with(['inputItems.item', 'outputItems.item', 'targetWarehouse'])->find($recipeId);
    }

    private function serializeRecipe(?ReworkRecipe $recipe): ?array
    {
        if (!$recipe) {
            return null;
        }

        return [
            'id' => $recipe->id,
            'code' => $recipe->code,
            'name' => $recipe->name,
            'target_warehouse_id' => $recipe->target_warehouse_id,
            'target_warehouse' => $recipe->targetWarehouse?->name,
            'is_active' => (bool) $recipe->is_active,
            'input_items' => $recipe->inputItems->map(fn ($row) => [
                'item_id' => $row->item_id,
                'qty' => (int) $row->qty,
                'item_label' => trim(($row->item?->sku ?? '').' - '.($row->item?->name ?? '')),
            ])->values(),
            'output_items' => $recipe->outputItems->map(fn ($row) => [
                'item_id' => $row->item_id,
                'qty' => (int) $row->qty,
                'item_label' => trim(($row->item?->sku ?? '').' - '.($row->item?->name ?? '')),
            ])->values(),
            'label' => trim($recipe->code.' - '.$recipe->name),
        ];
    }

    private function typeLabels(): array
    {
        return [
            'return_supplier' => 'Retur Supplier',
            'disposal' => 'Disposal',
            'rework' => 'Rework SKU',
        ];
    }

    private function generateCode(string $prefix): string
    {
        return $prefix.'-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));
    }

    private function applyDateFilter($query, Request $request): void
    {
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        try {
            if ($dateFrom) {
                $query->where('damaged_allocations.transacted_at', '>=', Carbon::parse($dateFrom)->startOfDay());
            }
            if ($dateTo) {
                $query->where('damaged_allocations.transacted_at', '<=', Carbon::parse($dateTo)->endOfDay());
            }
        } catch (\Throwable) {
            // ignore invalid date filters
        }
    }
}
