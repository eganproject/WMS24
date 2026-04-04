<?php

namespace App\Http\Controllers\Admin\Laporan;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\ItemCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class LaporanStokController extends Controller
{
    public function index(Request $request)
    {
        // Datatable request
        if ($request->ajax() && $request->has('draw')) {
            $userWarehouseId = auth()->user()->warehouse_id;
            $searchValue = $request->input('search.value', '');
            $start = $request->input('start', 0);
            $length = $request->input('length', 10);
            $draw = $request->input('draw', 0);
            $warehouseFilter = $request->input('warehouse_filter', $userWarehouseId);
            $categoryFilter = $request->input('category_filter');

            $query = Inventory::with(['item.uom', 'item.itemCategory', 'warehouse'])
                ->select('inventories.*');

            if ($warehouseFilter && $warehouseFilter !== 'semua') {
                $query->where('inventories.warehouse_id', $warehouseFilter);
            }

            if ($categoryFilter && $categoryFilter !== 'semua') {
                $query->whereHas('item', function ($q) use ($categoryFilter) {
                    $q->where('item_category_id', $categoryFilter);
                });
            }
            
            $totalRecords = (clone $query)->count();

            if (!empty($searchValue)) {
                $query->whereHas('item', function ($itemQuery) use ($searchValue) {
                    $itemQuery->where('nama_barang', 'like', "%{$searchValue}%")
                              ->orWhere('sku', 'like', "%{$searchValue}%");
                });
            }

            $totalFiltered = (clone $query)->count();

            $data = $query->orderBy('id', 'desc')
                ->offset($start)
                ->limit($length)
                ->get();

            return response()->json([
                'draw' => intval($draw),
                'recordsTotal' => intval($totalRecords),
                'recordsFiltered' => intval($totalFiltered),
                'data' => $data,
            ]);
        }

        // Summary data request
        if ($request->ajax()) {
            $userWarehouseId = auth()->user()->warehouse_id;
            $warehouseFilter = $request->input('warehouse_filter', $userWarehouseId);
            $categoryFilter = $request->input('category_filter');
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');

            if (empty($startDate) || empty($endDate)) {
                $endDate = Carbon::now()->format('Y-m-d');
                $startDate = Carbon::now()->subDays(29)->format('Y-m-d');
            }

            $totalUniqueItems = Inventory::when($warehouseFilter && $warehouseFilter !== 'semua', function ($q) use ($warehouseFilter) {
                $q->where('warehouse_id', $warehouseFilter);
            })
            ->when($categoryFilter && $categoryFilter !== 'semua', function($q) use ($categoryFilter) {
                $q->whereHas('item', function($itemQuery) use ($categoryFilter) {
                    $itemQuery->where('item_category_id', $categoryFilter);
                });
            })
            ->distinct('item_id')->count();

            $totalStockQuantity = Inventory::when($warehouseFilter && $warehouseFilter !== 'semua', function ($q) use ($warehouseFilter) {
                $q->where('warehouse_id', $warehouseFilter);
            })
            ->when($categoryFilter && $categoryFilter !== 'semua', function($q) use ($categoryFilter) {
                $q->whereHas('item', function($itemQuery) use ($categoryFilter) {
                    $itemQuery->where('item_category_id', $categoryFilter);
                });
            })
            ->sum('quantity');

            $lowStockItems = Inventory::when($warehouseFilter && $warehouseFilter !== 'semua', function ($q) use ($warehouseFilter) {
                $q->where('warehouse_id', $warehouseFilter);
            })
            ->when($categoryFilter && $categoryFilter !== 'semua', function($q) use ($categoryFilter) {
                $q->whereHas('item', function($itemQuery) use ($categoryFilter) {
                    $itemQuery->where('item_category_id', $categoryFilter);
                });
            })
            ->where('quantity', '<=', 10)->count();

            $stockMovements = StockMovement::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(CASE WHEN type IN ("stock_in", "transfer_in", "adjustment") THEN quantity ELSE 0 END) as stock_in'),
                DB::raw('SUM(CASE WHEN type IN ("stock_out", "transfer_out") THEN quantity ELSE 0 END) as stock_out')
            )
                ->when($warehouseFilter && $warehouseFilter !== 'semua', function ($q) use ($warehouseFilter) {
                    $q->where('warehouse_id', $warehouseFilter);
                })
                ->when($categoryFilter && $categoryFilter !== 'semua', function($q) use ($categoryFilter) {
                    $q->whereHas('item', function($itemQuery) use ($categoryFilter) {
                        $itemQuery->where('item_category_id', $categoryFilter);
                    });
                })
                ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->groupBy(DB::raw('DATE(created_at)'))
                ->orderBy(DB::raw('DATE(created_at)'), 'asc')
                ->get();

            $trendData = [
                'dates' => $stockMovements->pluck('date')->map(fn($date) => Carbon::parse($date)->format('d M')),
                'stock_in' => $stockMovements->pluck('stock_in'),
                'stock_out' => $stockMovements->pluck('stock_out'),
            ];

            $topItems = Inventory::select('item_id', DB::raw('SUM(quantity) as total_quantity'))
                ->with('item:id,nama_barang')
                ->when($warehouseFilter && $warehouseFilter !== 'semua', function ($q) use ($warehouseFilter) {
                    $q->where('warehouse_id', $warehouseFilter);
                })
                ->when($categoryFilter && $categoryFilter !== 'semua', function($q) use ($categoryFilter) {
                    $q->whereHas('item', function($itemQuery) use ($categoryFilter) {
                        $itemQuery->where('item_category_id', $categoryFilter);
                    });
                })
                ->groupBy('item_id')
                ->orderBy('total_quantity', 'desc')
                ->take(5)
                ->get();

            $topItemsData = [
                'names' => $topItems->map(function($inventory) { return $inventory->item->nama_barang ?? 'N/A'; }),
                'quantities' => $topItems->pluck('total_quantity'),
            ];

            return response()->json([
                'totalUniqueItems' => $totalUniqueItems,
                'totalStockQuantity' => number_format($totalStockQuantity, 2, ",", "."),
                'lowStockItems' => $lowStockItems,
                'trendData' => $trendData,
                'topItemsData' => $topItemsData,
            ]);
        }

        $userWarehouseId = auth()->user()->warehouse_id;
        $hideWarehouseFilter = !is_null($userWarehouseId);
        $warehouses = $userWarehouseId ? Warehouse::where('id', $userWarehouseId)->get() : Warehouse::all();
        $categories = ItemCategory::all();

        return view('admin.laporan.laporan-stok.index', compact(
            'warehouses',
            'categories',
            'hideWarehouseFilter'
        ));
    }

    public function lowStockReport(Request $request)
    {
        $userWarehouseId = auth()->user()->warehouse_id;
        $hideWarehouseFilter = !is_null($userWarehouseId);

        $warehouses = $userWarehouseId ? Warehouse::where('id', $userWarehouseId)->get() : Warehouse::all();
        $selectedWarehouseId = $request->input('warehouse_filter', $userWarehouseId);
        $searchQuery = $request->input('search');

        $inventories = Inventory::with(['item.uom', 'item.itemCategory', 'warehouse'])
            ->where('quantity', '<=', 10) // Core logic for this page
            ->when($selectedWarehouseId, function ($q) use ($selectedWarehouseId) {
                $q->where('warehouse_id', $selectedWarehouseId);
            })
            ->when($searchQuery, function ($q) use ($searchQuery) {
                $q->whereHas('item', function ($itemQuery) use ($searchQuery) {
                    $itemQuery->where('nama_barang', 'like', "%{$searchQuery}%")
                              ->orWhere('sku', 'like', "%{$searchQuery}%");
                });
            })
            ->select('inventories.*')
            ->paginate(10);

        return view('admin.laporan.laporan-stok.menipis', compact(
            'inventories',
            'warehouses',
            'selectedWarehouseId',
            'hideWarehouseFilter'
        ));
    }
}
