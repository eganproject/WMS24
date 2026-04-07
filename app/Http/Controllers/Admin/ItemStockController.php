<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Exports\ItemStocksExport;
use App\Models\Item;
use App\Support\WarehouseService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ItemStockController extends Controller
{
    public function index()
    {
        return view('admin.inventory.item-stocks.index');
    }

    public function data(Request $request)
    {
        $defaultId = WarehouseService::defaultWarehouseId();
        $displayId = WarehouseService::displayWarehouseId();
        $query = Item::with(['stocks' => function ($q) use ($defaultId, $displayId) {
            $q->whereIn('warehouse_id', [$defaultId, $displayId]);
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

        $data = $query->get()->map(function ($i) use ($defaultId, $displayId) {
            $stocks = $i->stocks?->keyBy('warehouse_id') ?? collect();
            $stockMain = (int) ($stocks->get($defaultId)?->stock ?? 0);
            $stockDisplay = (int) ($stocks->get($displayId)?->stock ?? 0);
            return [
                'id' => $i->id,
                'sku' => $i->sku,
                'name' => $i->name,
                'stock_main' => $stockMain,
                'stock_display' => $stockDisplay,
                'stock_total' => $stockMain + $stockDisplay,
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
}
