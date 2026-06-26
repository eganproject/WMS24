<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Exports\ItemStocksExport;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\Warehouse;
use App\Support\BundleService;
use App\Support\ItemTextSearch;
use App\Support\WarehouseService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class ItemStockController extends Controller
{
    public function index()
    {
        $defaultId = WarehouseService::defaultWarehouseId();
        $displayId = WarehouseService::displayWarehouseId();
        $damagedId = WarehouseService::damagedWarehouseId();
        $defaultLabel = Warehouse::where('id', $defaultId)->value('name') ?? 'Gudang Besar';
        $displayLabel = Warehouse::where('id', $displayId)->value('name') ?? 'Gudang Display';
        $damagedLabel = Warehouse::where('id', $damagedId)->value('name') ?? 'Gudang Rusak';
        $warehouses = Warehouse::orderBy('name')->get(['id', 'name']);

        return view('admin.inventory.item-stocks.index', [
            'defaultWarehouseLabel' => $defaultLabel,
            'displayWarehouseLabel' => $displayLabel,
            'damagedWarehouseLabel' => $damagedLabel,
            'defaultWarehouseId'   => $defaultId,
            'displayWarehouseId'   => $displayId,
            'damagedWarehouseId'   => $damagedId,
            'warehouses'           => $warehouses,
            'updateSafetyUrl'      => route('admin.inventory.item-stocks.update-safety'),
        ]);
    }

    public function data(Request $request)
    {
        $defaultId = WarehouseService::defaultWarehouseId();
        $displayId = WarehouseService::displayWarehouseId();
        $damagedId = WarehouseService::damagedWarehouseId();
        $query = Item::with([
            'category',
            'location.area',
            'area',
            'bundleComponents.component',
            'stocks' => function ($q) use ($defaultId, $displayId, $damagedId) {
                $q->whereIn('warehouse_id', [$defaultId, $displayId, $damagedId]);
            },
        ])
            ->leftJoin('item_stocks as stock_main_sort', function ($join) use ($defaultId) {
                $join->on('stock_main_sort.item_id', '=', 'items.id')
                    ->where('stock_main_sort.warehouse_id', '=', $defaultId);
            })
            ->leftJoin('item_stocks as stock_display_sort', function ($join) use ($displayId) {
                $join->on('stock_display_sort.item_id', '=', 'items.id')
                    ->where('stock_display_sort.warehouse_id', '=', $displayId);
            })
            ->leftJoin('item_stocks as stock_damaged_sort', function ($join) use ($damagedId) {
                $join->on('stock_damaged_sort.item_id', '=', 'items.id')
                    ->where('stock_damaged_sort.warehouse_id', '=', $damagedId);
            })
            ->select('items.*');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            ItemTextSearch::apply($query, $search, $this->isExactSearch($request));
        }

        $recordsTotal = Item::count();
        $recordsFiltered = (clone $query)->count();

        $this->applyDataTableOrder($query, $request);

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $data = $query->get()->map(function ($i) use ($defaultId, $displayId, $damagedId) {
            $stocks = $i->stocks?->keyBy('warehouse_id') ?? collect();
            $baseSafety = (int) ($i->safety_stock ?? 0);
            $isBundle = $i->isBundle();
            $stockMain = $isBundle ? null : (int) ($stocks->get($defaultId)?->stock ?? 0);
            $stockDisplay = $isBundle ? null : (int) ($stocks->get($displayId)?->stock ?? 0);
            $stockDamaged = $isBundle ? null : (int) ($stocks->get($damagedId)?->stock ?? 0);
            $safetyMainRaw = $stocks->get($defaultId)?->safety_stock;
            $safetyDisplayRaw = $stocks->get($displayId)?->safety_stock;
            $safetyMain = $safetyMainRaw !== null ? (int) $safetyMainRaw : $baseSafety;
            $safetyDisplay = $safetyDisplayRaw !== null ? (int) $safetyDisplayRaw : $baseSafety;
            $virtualMain = $isBundle ? BundleService::virtualAvailableQty($i, $defaultId) : null;
            $virtualDisplay = $isBundle ? BundleService::virtualAvailableQty($i, $displayId) : null;
            $stockGoodTotal = $isBundle ? null : ($stockMain + $stockDisplay);
            $koliQty = $isBundle ? 0 : max(0, (int) ($i->koli_qty ?? 0));
            $mainKoliFull = $koliQty > 0 ? intdiv($stockMain, $koliQty) : null;
            $mainKoliRemainder = $koliQty > 0 ? ($stockMain % $koliQty) : null;
            $location = $i->location;
            $area = $i->resolvedArea();

            return [
                'id' => $i->id,
                'sku' => $i->sku,
                'name' => $i->name,
                'item_type' => $i->item_type,
                'category' => $i->category?->name ?? '-',
                'address' => $i->resolvedAddress(),
                'area_code' => $area?->code ?? '',
                'rack_code' => $location?->rack_code ?? '',
                'column_no' => $location?->column_no ?? '',
                'row_no' => $location?->row_no ?? '',
                'description' => $i->description ?? '',
                'bundle_summary' => $isBundle ? BundleService::summarize($i) : '',
                'bundle_components' => $isBundle ? $i->bundleComponents->map(fn ($row) => [
                    'component_sku' => $row->component?->sku,
                    'component_name' => $row->component?->name,
                    'required_qty' => (int) $row->required_qty,
                ])->values()->all() : [],
                'koli_qty' => $koliQty,
                'stock_main' => $stockMain,
                'stock_main_koli' => $mainKoliFull,
                'stock_main_koli_remainder' => $mainKoliRemainder,
                'stock_display' => $stockDisplay,
                'stock_damaged' => $stockDamaged,
                'stock_good_total' => $stockGoodTotal,
                'stock_total' => $isBundle ? null : ($stockGoodTotal + $stockDamaged),
                'virtual_main' => $virtualMain,
                'virtual_display' => $virtualDisplay,
                'virtual_total' => $isBundle ? (($virtualMain ?? 0) + ($virtualDisplay ?? 0)) : null,
                'safety_main' => $safetyMain,
                'safety_display' => $safetyDisplay,
                'safety_base' => $baseSafety,
                'safety_main_raw' => $safetyMainRaw,
                'safety_display_raw' => $safetyDisplayRaw,
                'is_main_below_safety' => !$isBundle && $safetyMain > 0 && $stockMain < $safetyMain,
                'is_display_below_safety' => !$isBundle && $safetyDisplay > 0 && $stockDisplay < $safetyDisplay,
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function export(Request $request)
    {
        $search = trim((string) $request->input('q', ''));
        $filename = 'item-stocks-'.now()->format('YmdHis').'.xlsx';

        return Excel::download(new ItemStocksExport($search, $this->isExactSearch($request)), $filename);
    }

    public function updateSafety(Request $request)
    {
        $validated = $request->validate([
            'item_id' => ['nullable', 'integer', 'exists:items,id'],
            'item_ids' => ['nullable', 'array', 'min:1'],
            'item_ids.*' => ['integer', 'distinct', 'exists:items,id'],
            'safety_main' => ['nullable', 'integer', 'min:0'],
            'safety_display' => ['nullable', 'integer', 'min:0'],
        ]);

        $itemIds = collect($validated['item_ids'] ?? [])
            ->push($validated['item_id'] ?? null)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($itemIds->isEmpty()) {
            throw ValidationException::withMessages([
                'item_id' => 'Pilih minimal satu item.',
            ]);
        }

        $items = Item::query()->whereIn('id', $itemIds)->get(['id', 'item_type']);
        $bundleCount = $items->filter(fn (Item $item) => $item->isBundle())->count();
        if ($bundleCount > 0) {
            throw ValidationException::withMessages([
                'item_ids' => 'Bundle tidak memiliki safety stock fisik.',
            ]);
        }

        $defaultId = WarehouseService::defaultWarehouseId();
        $displayId = WarehouseService::displayWarehouseId();

        $updateMain = $request->has('safety_main');
        $updateDisplay = $request->has('safety_display');
        if (!$updateMain && !$updateDisplay) {
            throw ValidationException::withMessages([
                'safety_display' => 'Isi safety stock yang akan diubah.',
            ]);
        }

        $mainVal = $validated['safety_main'] ?? null;
        $displayVal = $validated['safety_display'] ?? null;

        $mainVal = ($mainVal === '' || $mainVal === null) ? null : (int) $mainVal;
        $displayVal = ($displayVal === '' || $displayVal === null) ? null : (int) $displayVal;

        DB::beginTransaction();
        try {
            foreach ($itemIds as $itemId) {
                if ($updateMain) {
                    $mainStock = ItemStock::firstOrCreate(
                        ['item_id' => $itemId, 'warehouse_id' => $defaultId],
                        ['stock' => 0]
                    );
                    $mainStock->safety_stock = $mainVal;
                    $mainStock->save();
                }

                if ($updateDisplay) {
                    $displayStock = ItemStock::firstOrCreate(
                        ['item_id' => $itemId, 'warehouse_id' => $displayId],
                        ['stock' => 0]
                    );
                    $displayStock->safety_stock = $displayVal;
                    $displayStock->save();
                }
            }

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menyimpan safety stock',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => $itemIds->count() > 1
                ? 'Safety stock berhasil disimpan untuk '.$itemIds->count().' item'
                : 'Safety stock berhasil disimpan',
        ]);
    }

    private function applyDataTableOrder($query, Request $request): void
    {
        $columns = [
            1 => 'items.id',
            2 => 'items.sku',
            3 => 'items.name',
            4 => 'items.item_type',
            5 => 'COALESCE(stock_main_sort.stock, 0)',
            6 => 'COALESCE(stock_main_sort.safety_stock, items.safety_stock, 0)',
            7 => 'COALESCE(stock_display_sort.stock, 0)',
            8 => 'COALESCE(stock_display_sort.safety_stock, items.safety_stock, 0)',
            9 => 'COALESCE(stock_damaged_sort.stock, 0)',
            10 => '(COALESCE(stock_main_sort.stock, 0) + COALESCE(stock_display_sort.stock, 0))',
            11 => '(COALESCE(stock_main_sort.stock, 0) + COALESCE(stock_display_sort.stock, 0) + COALESCE(stock_damaged_sort.stock, 0))',
        ];

        $orderColumn = (int) $request->input('order.0.column', 3);
        $direction = strtolower((string) $request->input('order.0.dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        $expression = $columns[$orderColumn] ?? 'items.name';

        $query->orderByRaw($expression.' '.$direction)
            ->orderBy('items.name')
            ->orderBy('items.id');
    }
}
