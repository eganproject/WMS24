<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Warehouse;
use App\Support\WarehouseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LowStockReportController extends Controller
{
    public function index()
    {
        $categories = Category::orderBy('name')->get(['id', 'name']);
        $warehouseId = WarehouseService::defaultWarehouseId();
        $warehouseLabel = Warehouse::where('id', $warehouseId)->value('name') ?? 'Gudang Besar';
        $warehouses = Warehouse::orderBy('name')->get(['id', 'name', 'code']);

        return view('admin.reports.low-stock.index', [
            'dataUrl' => route('admin.reports.low-stock.data'),
            'categories' => $categories,
            'warehouseLabel' => $warehouseLabel,
            'warehouses' => $warehouses,
            'defaultWarehouseId' => $warehouseId,
            'displayWarehouseId' => WarehouseService::displayWarehouseId(),
        ]);
    }

    public function data(Request $request)
    {
        $warehouseFilter = $request->input('warehouse_id');
        if ($warehouseFilter === null || $warehouseFilter === '') {
            $warehouseFilter = WarehouseService::defaultWarehouseId();
        }
        $warehouseId = $warehouseFilter !== 'all'
            ? (int) $warehouseFilter
            : WarehouseService::defaultWarehouseId();
        $safetyExpr = 'COALESCE(s.safety_stock, i.safety_stock, 0)';
        $stockExpr = 'COALESCE(s.stock, 0)';
        $baseQuery = DB::table('items as i')
            ->leftJoin('item_stocks as s', function ($join) use ($warehouseId) {
                $join->on('s.item_id', '=', 'i.id')
                    ->where('s.warehouse_id', '=', $warehouseId);
            })
            ->leftJoin('categories as c', 'c.id', '=', 'i.category_id')
            ->whereRaw("{$safetyExpr} > 0")
            ->whereRaw("{$stockExpr} < {$safetyExpr}");

        $catFilter = $request->input('category_id');
        if ($catFilter !== null && $catFilter !== '') {
            if ((int) $catFilter === 0) {
                $baseQuery->where('i.category_id', 0);
            } else {
                $baseQuery->where('i.category_id', (int) $catFilter);
            }
        }

        $statusFilter = $request->input('status');
        if ($statusFilter === 'out') {
            $baseQuery->whereRaw("{$stockExpr} <= 0");
        } elseif ($statusFilter === 'low') {
            $baseQuery->whereRaw("{$stockExpr} > 0");
        }

        $recordsTotalQuery = clone $baseQuery;

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $baseQuery->where(function ($q) use ($search) {
                $q->where('i.sku', 'like', "%{$search}%")
                    ->orWhere('i.name', 'like', "%{$search}%")
                    ->orWhere('i.address', 'like', "%{$search}%")
                    ->orWhere('i.description', 'like', "%{$search}%");
            });
        }

        $recordsTotal = (clone $recordsTotalQuery)->count();
        $recordsFiltered = (clone $baseQuery)->count();

        $summaryQuery = clone $baseQuery;
        $summaryTotal = $recordsFiltered;
        $summaryOutOfStock = (clone $summaryQuery)
            ->whereRaw("{$stockExpr} <= 0")
            ->count();
        $summaryGap = (int) ((clone $summaryQuery)
            ->selectRaw("COALESCE(SUM({$safetyExpr} - {$stockExpr}), 0) as gap")
            ->value('gap') ?? 0);

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);

        $dataQuery = clone $baseQuery;
        $dataQuery->select([
            'i.id',
            'i.sku',
            'i.name',
            'i.address',
            DB::raw("{$safetyExpr} as safety_stock"),
            DB::raw("{$stockExpr} as stock"),
            DB::raw("CASE WHEN i.category_id = 0 THEN 'Tanpa Kategori' ELSE COALESCE(c.name, '-') END as category"),
        ])
        ->orderByRaw("({$safetyExpr} - {$stockExpr}) desc")
        ->orderBy('i.sku');

        if ($length > 0) {
            $dataQuery->skip($start)->take($length);
        }

        $data = $dataQuery->get()->map(function ($row) {
            $stock = (int) ($row->stock ?? 0);
            $safety = (int) ($row->safety_stock ?? 0);
            $gap = max(0, $safety - $stock);

            return [
                'id' => $row->id,
                'sku' => $row->sku ?? '-',
                'name' => $row->name ?? '-',
                'category' => $row->category ?? '-',
                'address' => $row->address ?? '-',
                'stock' => $stock,
                'safety_stock' => $safety,
                'gap' => $gap,
                'status' => $stock <= 0 ? 'Out of Stock' : 'Low Stock',
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'summary' => [
                'total_low' => $summaryTotal,
                'out_of_stock' => $summaryOutOfStock,
                'total_gap' => $summaryGap,
            ],
            'data' => $data,
        ]);
    }
}
