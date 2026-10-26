<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GoodRework;
use App\Models\GoodReworkItem;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\ReworkRecipe;
use App\Models\Warehouse;
use App\Support\BundleService;
use App\Support\StockService;
use App\Support\WarehouseService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Rework dari stok bagus: bahan keluar dari gudang asal, hasil masuk ke gudang hasil.
 * Terpisah dari rework barang rusak (Alokasi Barang Rusak) dan tidak menyentuh saldo barang rusak.
 */
class GoodReworkController extends Controller
{
    public const SOURCE_TYPE = 'good_rework';

    public function index()
    {
        $warehouses = $this->allowedWarehouses();
        $recipes = ReworkRecipe::with(['inputItems', 'outputItems'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (ReworkRecipe $recipe) => [
                'id' => $recipe->id,
                'label' => trim($recipe->code.' - '.$recipe->name),
                'target_warehouse_id' => $recipe->target_warehouse_id,
                'input_items' => $recipe->inputItems->map(fn ($row) => [
                    'item_id' => (int) $row->item_id,
                    'qty' => (int) $row->qty,
                    'note' => $row->note ?? '',
                ])->values(),
                'output_items' => $recipe->outputItems->map(fn ($row) => [
                    'item_id' => (int) $row->item_id,
                    'qty' => (int) $row->qty,
                    'note' => $row->note ?? '',
                ])->values(),
            ])
            ->values();

        $defaultWarehouseId = WarehouseService::displayWarehouseId() ?: WarehouseService::defaultWarehouseId();

        return view('admin.inventory.good-reworks.index', [
            'items' => Item::query()
                ->where('item_type', Item::TYPE_SINGLE)
                ->orderBy('name')
                ->get(['id', 'sku', 'name', 'koli_qty']),
            'warehouses' => $warehouses,
            'recipes' => $recipes,
            'defaultWarehouseId' => $defaultWarehouseId,
            'mainWarehouseId' => WarehouseService::defaultWarehouseId(),
            'dataUrl' => route('admin.inventory.good-reworks.data'),
            'storeUrl' => route('admin.inventory.good-reworks.store'),
        ]);
    }

    public function data(Request $request)
    {
        $query = GoodRework::query()
            ->with(['inputItems.item', 'outputItems.item', 'sourceWarehouse', 'targetWarehouse', 'recipe', 'creator'])
            ->orderByDesc('id');

        $status = trim((string) $request->input('status', ''));
        if (in_array($status, [GoodRework::STATUS_PENDING, GoodRework::STATUS_APPROVED], true)) {
            $query->where('status', $status);
        }

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $exact = $this->isExactSearch($request);
            $query->where(function ($q) use ($search, $exact) {
                $this->applyTextSearch($q, 'good_reworks.code', $search, $exact);
                $this->applyTextSearch($q, 'good_reworks.source_ref', $search, $exact, 'or');
                $this->applyTextSearch($q, 'good_reworks.note', $search, $exact, 'or');
                $q->orWhereHas('items.item', function ($itemQ) use ($search, $exact) {
                    $this->applyTextSearch($itemQ, 'sku', $search, $exact);
                    $this->applyTextSearch($itemQ, 'name', $search, $exact, 'or');
                });
            });
        }

        $recordsTotal = GoodRework::count();
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $formatLines = fn (Collection $rows) => $rows
            ->map(fn ($row) => sprintf('%s (%d)', $row->item?->sku ?? '-', (int) $row->qty))
            ->implode(', ') ?: '-';

        $data = $query->get()->map(fn (GoodRework $row) => [
            'id' => $row->id,
            'code' => $row->code,
            'transacted_at' => $row->transacted_at?->format('Y-m-d H:i'),
            'source_warehouse' => $row->sourceWarehouse?->name ?? '-',
            'target_warehouse' => $row->targetWarehouse?->name ?? '-',
            'recipe' => $row->recipe ? trim($row->recipe->code.' - '.$row->recipe->name) : '-',
            'inputs' => $formatLines($row->inputItems),
            'outputs' => $formatLines($row->outputItems),
            'status' => $row->status,
            'note' => $row->note ?? '',
            'created_by' => $row->creator?->name ?? '-',
        ]);

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function show(int $id)
    {
        $rework = GoodRework::with(['inputItems.item', 'outputItems.item'])->findOrFail($id);

        $serializeLines = fn (Collection $rows) => $rows->map(fn ($row) => [
            'item_id' => (int) $row->item_id,
            'qty' => (int) $row->qty,
            'note' => $row->note ?? '',
            'item_label' => trim(($row->item?->sku ?? '').' - '.($row->item?->name ?? '')),
        ])->values();

        return response()->json([
            'id' => $rework->id,
            'code' => $rework->code,
            'status' => $rework->status,
            'source_warehouse_id' => $rework->source_warehouse_id,
            'target_warehouse_id' => $rework->target_warehouse_id,
            'recipe_id' => $rework->recipe_id,
            'recipe_multiplier' => $rework->recipe_multiplier,
            'source_ref' => $rework->source_ref,
            'transacted_at' => $rework->transacted_at?->format('Y-m-d\TH:i'),
            'note' => $rework->note,
            'input_items' => $serializeLines($rework->inputItems),
            'output_items' => $serializeLines($rework->outputItems),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        DB::beginTransaction();
        try {
            $rework = GoodRework::create([
                'code' => $this->generateCode('GRW'),
                'source_warehouse_id' => $validated['source_warehouse_id'],
                'target_warehouse_id' => $validated['target_warehouse_id'],
                'recipe_id' => $validated['recipe_id'],
                'recipe_multiplier' => $validated['recipe_multiplier'],
                'source_ref' => $validated['source_ref'],
                'transacted_at' => $validated['transacted_at'],
                'note' => $validated['note'],
                'status' => GoodRework::STATUS_PENDING,
                'created_by' => auth()->id(),
            ]);
            $this->persistItems($rework, $validated);

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Gagal menyimpan rework barang bagus.',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Rework barang bagus berhasil disimpan dan menunggu approval.',
            'id' => $rework->id,
            'code' => $rework->code,
            'status' => $rework->status,
        ]);
    }

    public function update(Request $request, int $id)
    {
        $validated = $this->validatePayload($request);

        DB::beginTransaction();
        try {
            $rework = GoodRework::lockForUpdate()->findOrFail($id);
            if ($rework->isApproved()) {
                throw ValidationException::withMessages([
                    'status' => 'Rework yang sudah disetujui tidak bisa diubah.',
                ]);
            }

            $rework->update([
                'source_warehouse_id' => $validated['source_warehouse_id'],
                'target_warehouse_id' => $validated['target_warehouse_id'],
                'recipe_id' => $validated['recipe_id'],
                'recipe_multiplier' => $validated['recipe_multiplier'],
                'source_ref' => $validated['source_ref'],
                'transacted_at' => $validated['transacted_at'],
                'note' => $validated['note'],
            ]);
            GoodReworkItem::where('good_rework_id', $rework->id)->delete();
            $this->persistItems($rework, $validated);

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Gagal memperbarui rework barang bagus.',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json(['message' => 'Rework barang bagus berhasil diperbarui.']);
    }

    public function destroy(int $id)
    {
        $rework = GoodRework::findOrFail($id);
        if ($rework->isApproved()) {
            return response()->json([
                'message' => 'Rework yang sudah disetujui tidak bisa dihapus.',
            ], 422);
        }

        $rework->delete();

        return response()->json(['message' => 'Rework barang bagus berhasil dihapus.']);
    }

    public function approve(int $id)
    {
        DB::beginTransaction();
        try {
            $rework = GoodRework::with(['inputItems.item', 'outputItems'])
                ->lockForUpdate()
                ->findOrFail($id);

            if ($rework->isApproved()) {
                DB::rollBack();

                return response()->json(['message' => 'Rework sudah disetujui.']);
            }

            $this->approveRework($rework);

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Gagal menyetujui rework barang bagus.',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json(['message' => 'Rework barang bagus berhasil disetujui. Stok sudah diperbarui.']);
    }

    private function approveRework(GoodRework $rework): void
    {
        $inputs = $rework->inputItems ?? collect();
        $outputs = $rework->outputItems ?? collect();
        if ($inputs->isEmpty() || $outputs->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Rework harus memiliki minimal 1 bahan dan 1 hasil.',
            ]);
        }

        $sourceWarehouseId = (int) $rework->source_warehouse_id;
        $stockMap = ItemStock::query()
            ->where('warehouse_id', $sourceWarehouseId)
            ->whereIn('item_id', $inputs->pluck('item_id')->all())
            ->lockForUpdate()
            ->pluck('stock', 'item_id');

        $shortages = $inputs
            ->filter(fn ($row) => (int) ($stockMap[$row->item_id] ?? 0) < (int) $row->qty)
            ->map(fn ($row) => sprintf(
                '%s butuh %d, stok %d',
                $row->item?->sku ?? 'item',
                (int) $row->qty,
                (int) ($stockMap[$row->item_id] ?? 0)
            ))
            ->values();
        if ($shortages->isNotEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Stok bahan di gudang asal tidak mencukupi: '.$shortages->implode('; ').'.',
            ]);
        }

        $approvedAt = now();
        foreach ($inputs as $row) {
            StockService::mutate([
                'item_id' => $row->item_id,
                'warehouse_id' => $sourceWarehouseId,
                'direction' => 'out',
                'qty' => (int) $row->qty,
                'source_type' => self::SOURCE_TYPE,
                'source_subtype' => 'rework_input',
                'source_id' => $rework->id,
                'source_code' => $rework->code,
                'note' => $row->note,
                'occurred_at' => $approvedAt,
                'created_by' => auth()->id(),
            ]);
        }

        foreach ($outputs as $row) {
            StockService::mutate([
                'item_id' => $row->item_id,
                'warehouse_id' => (int) $rework->target_warehouse_id,
                'direction' => 'in',
                'qty' => (int) $row->qty,
                'source_type' => self::SOURCE_TYPE,
                'source_subtype' => 'rework_output',
                'source_id' => $rework->id,
                'source_code' => $rework->code,
                'note' => $row->note,
                'occurred_at' => $approvedAt,
                'created_by' => auth()->id(),
            ]);
        }

        $rework->status = GoodRework::STATUS_APPROVED;
        $rework->approved_at = $approvedAt;
        $rework->approved_by = auth()->id();
        $rework->save();
    }

    private function persistItems(GoodRework $rework, array $validated): void
    {
        foreach (['input' => 'input_items', 'output' => 'output_items'] as $lineType => $key) {
            foreach ($validated[$key] as $row) {
                GoodReworkItem::create([
                    'good_rework_id' => $rework->id,
                    'line_type' => $lineType,
                    'item_id' => $row['item_id'],
                    'qty' => $row['qty'],
                    'note' => $row['note'],
                ]);
            }
        }
    }

    private function validatePayload(Request $request): array
    {
        $allowedWarehouseIds = $this->allowedWarehouses()->pluck('id')->all();

        $validated = $request->validate([
            'source_warehouse_id' => ['required', 'integer', Rule::in($allowedWarehouseIds)],
            'target_warehouse_id' => ['required', 'integer', Rule::in($allowedWarehouseIds)],
            'recipe_id' => ['nullable', 'integer', 'exists:rework_recipes,id'],
            'recipe_multiplier' => ['nullable', 'integer', 'min:1'],
            'source_ref' => ['nullable', 'string', 'max:100'],
            'transacted_at' => ['required', 'date'],
            'note' => ['nullable', 'string'],
            'input_items' => ['required', 'array', 'min:1'],
            'input_items.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'input_items.*.qty' => ['required', 'integer', 'min:1'],
            'input_items.*.note' => ['nullable', 'string'],
            'output_items' => ['required', 'array', 'min:1'],
            'output_items.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'output_items.*.qty' => ['required', 'integer', 'min:1'],
            'output_items.*.note' => ['nullable', 'string'],
        ], [
            'source_warehouse_id.in' => 'Gudang asal tidak valid. Gudang Rusak tidak bisa dipakai di rework barang bagus.',
            'target_warehouse_id.in' => 'Gudang hasil tidak valid. Gudang Rusak tidak bisa dipakai di rework barang bagus.',
        ]);

        foreach (['input_items' => 'bahan', 'output_items' => 'hasil'] as $key => $label) {
            $rows = collect($validated[$key])->map(fn ($row) => [
                'item_id' => (int) $row['item_id'],
                'qty' => (int) $row['qty'],
                'note' => $row['note'] ?? null,
            ])->values();

            if ($rows->groupBy('item_id')->filter(fn ($group) => $group->count() > 1)->isNotEmpty()) {
                throw ValidationException::withMessages([
                    $key => "Item {$label} tidak boleh duplikat.",
                ]);
            }

            $validated[$key] = $rows->all();
        }

        BundleService::assertPhysicalItems(
            collect($validated['input_items'])->merge($validated['output_items'])->pluck('item_id')->unique()->all(),
            'Bundle tidak bisa dipakai di rework karena tidak memiliki stok fisik.'
        );

        $this->assertWholeKoliForMainWarehouse($validated);

        $validated['recipe_id'] = !empty($validated['recipe_id']) ? (int) $validated['recipe_id'] : null;
        $validated['recipe_multiplier'] = $validated['recipe_id'] ? max(1, (int) ($validated['recipe_multiplier'] ?? 1)) : null;
        $validated['source_ref'] = $validated['source_ref'] ?? null;
        $validated['note'] = $validated['note'] ?? null;
        $validated['transacted_at'] = Carbon::parse($validated['transacted_at']);

        return $validated;
    }

    /**
     * Gudang Besar menyimpan stok per koli. Sama seperti penyesuaian stok dan outbound manual,
     * qty bahan/hasil yang keluar atau masuk Gudang Besar wajib kelipatan isi per koli.
     */
    private function assertWholeKoliForMainWarehouse(array $validated): void
    {
        $mainWarehouseId = WarehouseService::defaultWarehouseId();
        $sides = [
            'input_items' => ['warehouse_id' => (int) $validated['source_warehouse_id'], 'label' => 'bahan'],
            'output_items' => ['warehouse_id' => (int) $validated['target_warehouse_id'], 'label' => 'hasil'],
        ];

        $itemIds = collect($validated['input_items'])->merge($validated['output_items'])->pluck('item_id')->unique()->all();
        $items = Item::whereIn('id', $itemIds)->get(['id', 'sku', 'koli_qty'])->keyBy('id');

        $errors = [];
        foreach ($sides as $key => $side) {
            if ($side['warehouse_id'] !== $mainWarehouseId) {
                continue;
            }

            foreach ($validated[$key] as $idx => $row) {
                $item = $items->get($row['item_id']);
                $qtyPerKoli = (int) ($item?->koli_qty ?? 0);
                $sku = $item?->sku ?? 'item';
                if ($qtyPerKoli < 1) {
                    $errors["{$key}.{$idx}.qty"] = "Isi/koli {$sku} belum diset. Item {$side['label']} di Gudang Besar wajib per koli.";
                    continue;
                }
                if ((int) $row['qty'] % $qtyPerKoli !== 0) {
                    $errors["{$key}.{$idx}.qty"] = "Qty {$side['label']} {$sku} di Gudang Besar harus kelipatan isi/koli ({$qtyPerKoli} pcs).";
                }
            }
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function allowedWarehouses(): Collection
    {
        return Warehouse::query()
            ->where('id', '!=', WarehouseService::damagedWarehouseId())
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
    }

    private function generateCode(string $prefix): string
    {
        return $prefix.'-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));
    }
}
