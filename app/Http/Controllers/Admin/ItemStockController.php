<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Exports\ItemStocksExport;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\Warehouse;
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

        return view('admin.inventory.item-stocks.index', [
            'defaultWarehouseLabel' => $defaultLabel,
            'displayWarehouseLabel' => $displayLabel,
            'damagedWarehouseLabel' => $damagedLabel,
            'updateSafetyUrl' => route('admin.inventory.item-stocks.update-safety'),
        ]);
    }

    public function data(Request $request)
    {
        $defaultId = WarehouseService::defaultWarehouseId();
        $displayId = WarehouseService::displayWarehouseId();
        $damagedId = WarehouseService::damagedWarehouseId();
        $query = Item::with(['stocks' => function ($q) use ($defaultId, $displayId, $damagedId) {
            $q->whereIn('warehouse_id', [$defaultId, $displayId, $damagedId]);
        }])->orderBy('name');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('sku', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $recordsTotal = Item::count();
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $data = $query->get()->map(function ($i) use ($defaultId, $displayId, $damagedId) {
            $stocks = $i->stocks?->keyBy('warehouse_id') ?? collect();
            $baseSafety = (int) ($i->safety_stock ?? 0);
            $stockMain = (int) ($stocks->get($defaultId)?->stock ?? 0);
            $stockDisplay = (int) ($stocks->get($displayId)?->stock ?? 0);
            $stockDamaged = (int) ($stocks->get($damagedId)?->stock ?? 0);
            $safetyMainRaw = $stocks->get($defaultId)?->safety_stock;
            $safetyDisplayRaw = $stocks->get($displayId)?->safety_stock;
            $safetyMain = $safetyMainRaw !== null ? (int) $safetyMainRaw : $baseSafety;
            $safetyDisplay = $safetyDisplayRaw !== null ? (int) $safetyDisplayRaw : $baseSafety;
            $stockGoodTotal = $stockMain + $stockDisplay;
            return [
                'id' => $i->id,
                'sku' => $i->sku,
                'name' => $i->name,
                'stock_main' => $stockMain,
                'stock_display' => $stockDisplay,
                'stock_damaged' => $stockDamaged,
                'stock_good_total' => $stockGoodTotal,
                'stock_total' => $stockGoodTotal + $stockDamaged,
                'safety_main' => $safetyMain,
                'safety_display' => $safetyDisplay,
                'safety_base' => $baseSafety,
                'safety_main_raw' => $safetyMainRaw,
                'safety_display_raw' => $safetyDisplayRaw,
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

        return Excel::download(new ItemStocksExport($search), $filename);
    }

    public function updateSafety(Request $request)
    {
        $validated = $request->validate([
            'item_id' => ['required', 'integer', 'exists:items,id'],
            'safety_main' => ['nullable', 'integer', 'min:0'],
            'safety_display' => ['nullable', 'integer', 'min:0'],
        ]);

        $itemId = (int) $validated['item_id'];
        $defaultId = WarehouseService::defaultWarehouseId();
        $displayId = WarehouseService::displayWarehouseId();

        $mainVal = $validated['safety_main'];
        $displayVal = $validated['safety_display'];

        $mainVal = ($mainVal === '' || $mainVal === null) ? null : (int) $mainVal;
        $displayVal = ($displayVal === '' || $displayVal === null) ? null : (int) $displayVal;

        DB::beginTransaction();
        try {
            $mainStock = ItemStock::firstOrCreate(
                ['item_id' => $itemId, 'warehouse_id' => $defaultId],
                ['stock' => 0]
            );
            $mainStock->safety_stock = $mainVal;
            $mainStock->save();

            $displayStock = ItemStock::firstOrCreate(
                ['item_id' => $itemId, 'warehouse_id' => $displayId],
                ['stock' => 0]
            );
            $displayStock->safety_stock = $displayVal;
            $displayStock->save();

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
            'message' => 'Safety stock berhasil disimpan',
        ]);
    }
}
