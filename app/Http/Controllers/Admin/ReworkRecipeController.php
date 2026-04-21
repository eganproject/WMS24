<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DamagedAllocation;
use App\Models\Item;
use App\Models\ReworkRecipe;
use App\Models\ReworkRecipeItem;
use App\Models\Warehouse;
use App\Support\BundleService;
use App\Support\WarehouseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReworkRecipeController extends Controller
{
    public function index()
    {
        $items = Item::query()
            ->where('item_type', Item::TYPE_SINGLE)
            ->orderBy('name')
            ->get(['id', 'sku', 'name']);
        $damagedWarehouseId = WarehouseService::damagedWarehouseId();
        $targetWarehouses = Warehouse::query()
            ->where('id', '!=', $damagedWarehouseId)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return view('admin.inventory.rework-recipes.index', [
            'items' => $items,
            'targetWarehouses' => $targetWarehouses,
            'defaultTargetWarehouseId' => WarehouseService::defaultWarehouseId(),
            'dataUrl' => route('admin.inventory.rework-recipes.data'),
            'storeUrl' => route('admin.inventory.rework-recipes.store'),
        ]);
    }

    public function options()
    {
        $recipes = ReworkRecipe::with(['inputItems.item', 'outputItems.item', 'targetWarehouse'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $recipes->map(fn (ReworkRecipe $recipe) => $this->serializeRecipe($recipe))->values(),
        ]);
    }

    public function data(Request $request)
    {
        $query = ReworkRecipe::query()
            ->with(['inputItems.item', 'outputItems.item', 'targetWarehouse', 'creator'])
            ->orderBy('name');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('rework_recipes.code', 'like', "%{$search}%")
                    ->orWhere('rework_recipes.name', 'like', "%{$search}%")
                    ->orWhere('rework_recipes.note', 'like', "%{$search}%")
                    ->orWhereHas('inputItems.item', function ($itemQ) use ($search) {
                        $itemQ->where('sku', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('outputItems.item', function ($itemQ) use ($search) {
                        $itemQ->where('sku', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('targetWarehouse', function ($warehouseQ) use ($search) {
                        $warehouseQ->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    });
            });
        }

        $recordsTotal = ReworkRecipe::count();
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $data = $query->get()->map(function (ReworkRecipe $recipe) {
            $inputs = $recipe->inputItems->map(function ($row) {
                return sprintf('%s (%d)', $row->item?->sku ?? '-', (int) $row->qty);
            })->implode(', ');
            $outputs = $recipe->outputItems->map(function ($row) {
                return sprintf('%s (%d)', $row->item?->sku ?? '-', (int) $row->qty);
            })->implode(', ');

            return [
                'id' => $recipe->id,
                'code' => $recipe->code,
                'name' => $recipe->name,
                'status' => $recipe->is_active ? 'active' : 'inactive',
                'target_warehouse' => $recipe->targetWarehouse?->name ?? '-',
                'inputs' => $inputs ?: '-',
                'outputs' => $outputs ?: '-',
                'note' => $recipe->note ?? '',
                'created_by' => $recipe->creator?->name ?? '-',
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
        $recipe = ReworkRecipe::with(['inputItems.item', 'outputItems.item', 'targetWarehouse'])->findOrFail($id);

        return response()->json($this->serializeRecipe($recipe));
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        DB::beginTransaction();
        try {
            $recipe = ReworkRecipe::create([
                'code' => $this->generateCode('RWR'),
                'name' => $validated['name'],
                'target_warehouse_id' => $validated['target_warehouse_id'],
                'note' => $validated['note'] ?? null,
                'is_active' => $validated['is_active'],
                'created_by' => auth()->id(),
            ]);

            $this->persistItems($recipe, $validated);

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Gagal menyimpan resep rework',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Resep rework berhasil disimpan.',
        ]);
    }

    public function update(Request $request, int $id)
    {
        $validated = $this->validatePayload($request);

        DB::beginTransaction();
        try {
            $recipe = ReworkRecipe::findOrFail($id);
            ReworkRecipeItem::where('rework_recipe_id', $recipe->id)->delete();

            $recipe->update([
                'name' => $validated['name'],
                'target_warehouse_id' => $validated['target_warehouse_id'],
                'note' => $validated['note'] ?? null,
                'is_active' => $validated['is_active'],
            ]);

            $this->persistItems($recipe, $validated);

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Gagal memperbarui resep rework',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Resep rework berhasil diperbarui.',
        ]);
    }

    public function destroy(int $id)
    {
        DB::beginTransaction();
        try {
            $recipe = ReworkRecipe::findOrFail($id);
            $used = DamagedAllocation::where('recipe_id', $recipe->id)->exists();
            if ($used) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Resep sudah digunakan pada alokasi barang rusak dan tidak bisa dihapus.',
                ], 422);
            }

            $recipe->delete();
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Gagal menghapus resep rework',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Resep rework berhasil dihapus.',
        ]);
    }

    private function validatePayload(Request $request): array
    {
        $damagedWarehouseId = WarehouseService::damagedWarehouseId();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'target_warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'is_active' => ['nullable'],
            'note' => ['nullable', 'string'],
            'input_items' => ['required', 'array', 'min:1'],
            'input_items.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'input_items.*.qty' => ['required', 'integer', 'min:1'],
            'input_items.*.note' => ['nullable', 'string'],
            'output_items' => ['required', 'array', 'min:1'],
            'output_items.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'output_items.*.qty' => ['required', 'integer', 'min:1'],
            'output_items.*.note' => ['nullable', 'string'],
        ]);

        if (!empty($validated['target_warehouse_id']) && (int) $validated['target_warehouse_id'] === $damagedWarehouseId) {
            throw ValidationException::withMessages([
                'target_warehouse_id' => 'Gudang hasil resep tidak boleh Gudang Rusak.',
            ]);
        }

        $inputItems = collect($validated['input_items'] ?? [])
            ->filter(fn ($row) => (int) ($row['item_id'] ?? 0) > 0 && (int) ($row['qty'] ?? 0) > 0)
            ->map(fn ($row) => [
                'item_id' => (int) $row['item_id'],
                'qty' => (int) $row['qty'],
                'note' => $row['note'] ?? null,
            ])->values();
        if ($inputItems->isEmpty()) {
            throw ValidationException::withMessages([
                'input_items' => 'Minimal 1 item input diperlukan.',
            ]);
        }
        if ($inputItems->groupBy('item_id')->filter(fn ($rows) => $rows->count() > 1)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'input_items' => 'Item input tidak boleh duplikat.',
            ]);
        }

        BundleService::assertPhysicalItems(
            $inputItems->pluck('item_id')->all(),
            'Bundle tidak bisa digunakan sebagai input resep rework karena tidak memiliki stok fisik.',
            'input_items'
        );

        $outputItems = collect($validated['output_items'] ?? [])
            ->filter(fn ($row) => (int) ($row['item_id'] ?? 0) > 0 && (int) ($row['qty'] ?? 0) > 0)
            ->map(fn ($row) => [
                'item_id' => (int) $row['item_id'],
                'qty' => (int) $row['qty'],
                'note' => $row['note'] ?? null,
            ])->values();
        if ($outputItems->isEmpty()) {
            throw ValidationException::withMessages([
                'output_items' => 'Minimal 1 item output diperlukan.',
            ]);
        }
        if ($outputItems->groupBy('item_id')->filter(fn ($rows) => $rows->count() > 1)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'output_items' => 'Item output tidak boleh duplikat.',
            ]);
        }

        BundleService::assertPhysicalItems(
            $outputItems->pluck('item_id')->all(),
            'Bundle tidak bisa digunakan sebagai output resep rework karena tidak memiliki stok fisik.',
            'output_items'
        );

        $validated['input_items'] = $inputItems->all();
        $validated['output_items'] = $outputItems->all();
        $validated['target_warehouse_id'] = !empty($validated['target_warehouse_id']) ? (int) $validated['target_warehouse_id'] : null;
        $validated['is_active'] = $request->boolean('is_active', true);

        return $validated;
    }

    private function persistItems(ReworkRecipe $recipe, array $validated): void
    {
        foreach ($validated['input_items'] as $row) {
            ReworkRecipeItem::create([
                'rework_recipe_id' => $recipe->id,
                'line_type' => 'input',
                'item_id' => $row['item_id'],
                'qty' => $row['qty'],
                'note' => $row['note'] ?? null,
            ]);
        }

        foreach ($validated['output_items'] as $row) {
            ReworkRecipeItem::create([
                'rework_recipe_id' => $recipe->id,
                'line_type' => 'output',
                'item_id' => $row['item_id'],
                'qty' => $row['qty'],
                'note' => $row['note'] ?? null,
            ]);
        }
    }

    private function serializeRecipe(ReworkRecipe $recipe): array
    {
        return [
            'id' => $recipe->id,
            'code' => $recipe->code,
            'name' => $recipe->name,
            'target_warehouse_id' => $recipe->target_warehouse_id,
            'target_warehouse' => $recipe->targetWarehouse?->name,
            'note' => $recipe->note,
            'is_active' => (bool) $recipe->is_active,
            'input_items' => $recipe->inputItems->map(fn ($row) => [
                'item_id' => $row->item_id,
                'qty' => (int) $row->qty,
                'note' => $row->note ?? '',
                'item_label' => trim(($row->item?->sku ?? '').' - '.($row->item?->name ?? '')),
            ])->values(),
            'output_items' => $recipe->outputItems->map(fn ($row) => [
                'item_id' => $row->item_id,
                'qty' => (int) $row->qty,
                'note' => $row->note ?? '',
                'item_label' => trim(($row->item?->sku ?? '').' - '.($row->item?->name ?? '')),
            ])->values(),
            'label' => trim($recipe->code.' - '.$recipe->name),
        ];
    }

    private function generateCode(string $prefix): string
    {
        return $prefix.'-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));
    }
}
