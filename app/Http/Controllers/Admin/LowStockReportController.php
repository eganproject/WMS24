<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Warehouse;
use App\Support\LowStockService;
use App\Support\WarehouseService;
use Illuminate\Http\Request;

class LowStockReportController extends Controller
{
    public function index()
    {
        $categories = Category::orderBy('name')->get(['id', 'name']);
        $warehouseId = WarehouseService::defaultWarehouseId();
        $warehouseLabel = Warehouse::where('id', $warehouseId)->value('name') ?? 'Gudang Besar';
        $warehouses = app(LowStockService::class)->safetyReportWarehouses()
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

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
        $service = app(LowStockService::class);
        $warehouseFilter = $request->input('warehouse_id');
        $baseQuery = $service->query($warehouseFilter);

        $service->applyFilters($baseQuery, [
            'category_id' => $request->input('category_id'),
            'status' => $request->input('status'),
        ]);

        $recordsTotalQuery = clone $baseQuery;

        $search = trim((string) $request->input('q', ''));
        $service->applyFilters($baseQuery, [
            'q' => $search,
            'exact' => $this->isExactSearch($request),
        ]);

        $recordsTotal = (clone $recordsTotalQuery)->count();
        $recordsFiltered = (clone $baseQuery)->count();
        $summary = $service->summary(clone $baseQuery);

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);

        $dataQuery = clone $baseQuery;
        $safetyExpr = $service->safetyExpr();
        $stockExpr = $service->stockExpr();
        $dataQuery->select([
            'i.id',
            'i.sku',
            'i.name',
            'i.address',
            'w.id as warehouse_id',
            'w.name as warehouse',
            \Illuminate\Support\Facades\DB::raw("{$safetyExpr} as safety_stock"),
            \Illuminate\Support\Facades\DB::raw("{$stockExpr} as stock"),
            \Illuminate\Support\Facades\DB::raw($service->mainStockExpr().' as main_stock'),
            \Illuminate\Support\Facades\DB::raw($service->displayStockExpr().' as display_stock'),
            \Illuminate\Support\Facades\DB::raw($service->totalStockExpr().' as total_stock'),
            \Illuminate\Support\Facades\DB::raw("CASE WHEN s.safety_stock IS NOT NULL THEN 'Per gudang' ELSE 'Default item' END as safety_source"),
            \Illuminate\Support\Facades\DB::raw("CASE WHEN i.category_id = 0 THEN 'Tanpa Kategori' ELSE COALESCE(c.name, '-') END as category"),
        ])
        ->orderByRaw("({$safetyExpr} - {$stockExpr}) desc")
        ->orderBy('w.name')
        ->orderBy('i.sku');

        if ($length > 0) {
            $dataQuery->skip($start)->take($length);
        }

        $data = $dataQuery->get()->map(fn ($row) => $service->mapRow($row));

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'summary' => [
                'total_low' => $summary['total_low'],
                'out_of_stock' => $summary['out_of_stock'],
                'total_gap' => $summary['total_gap'],
            ],
            'data' => $data,
        ]);
    }
}
