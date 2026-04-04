<?php


namespace App\Http\Controllers\Admin\Laporan;

use App\Http\Controllers\Controller;
use App\Models\StockMovement;
use App\Models\Item;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class LaporanController extends Controller
{
    public function laporanPergerakanBarang(Request $request)
    {
        // --- Date Range Filter ---
        $startDate = Carbon::now()->subDays(29)->startOfDay();
        $endDate = Carbon::now()->endOfDay();
        $dateRange = $request->input('date_range');

        if ($dateRange) {
            $dates = explode(' - ', $dateRange);
            if(count($dates) == 2) {
                $startDate = Carbon::createFromFormat('d/m/Y', $dates[0])->startOfDay();
                $endDate = Carbon::createFromFormat('d/m/Y', $dates[1])->endOfDay();
            }
        }

        // --- Base Query for all data ---
        $baseQuery = StockMovement::whereBetween('date', [$startDate, $endDate]);

        // If user tied to specific warehouse, enforce it
        if (auth()->user() && auth()->user()->warehouse_id) {
            $baseQuery->where('warehouse_id', auth()->user()->warehouse_id);
        }

        if ($request->filled('type_filter') && $request->input('type_filter') !== 'semua') {
            $baseQuery->where('type', $request->type_filter);
        }
        if ($request->filled('warehouse_filter')) {
            $baseQuery->where('warehouse_id', $request->warehouse_filter);
        }
        if ($request->filled('item_filter')) {
            $baseQuery->where('item_id', $request->item_filter);
        }

        // Table now uses DataTables (AJAX). No need to fetch paginated data here.

        // --- Data for Statistic Widgets ---
        $stats = [
            'total_movements' => (clone $baseQuery)->count(),
            'total_in' => (clone $baseQuery)->whereIn('type', ['stock_in', 'transfer_in', 'adjustment'])->where('quantity', '>', 0)->sum('quantity'),
            'total_out' => (clone $baseQuery)->whereIn('type', ['stock_out', 'transfer_out', 'adjustment'])->where('quantity', '<', 0)->sum('quantity') * -1,
        ];

        // --- Data for Line Chart (Trend) ---
        $trendResult = (clone $baseQuery)
            ->select(
                DB::raw('DATE(date) as movement_date'),
                DB::raw("SUM(CASE WHEN type IN ('stock_in', 'transfer_in') OR (type = 'adjustment' AND quantity > 0) THEN quantity ELSE 0 END) as total_in"),
                DB::raw("SUM(CASE WHEN type IN ('stock_out', 'transfer_out') OR (type = 'adjustment' AND quantity < 0) THEN ABS(quantity) ELSE 0 END) as total_out")
            )
            ->groupBy('movement_date')
            ->orderBy('movement_date', 'asc')
            ->get();

        $trendData = [
            'dates' => $trendResult->pluck('movement_date')->map(function ($date) {
                return Carbon::parse($date)->format('d M');
            }),
            'stock_in' => $trendResult->pluck('total_in'),
            'stock_out' => $trendResult->pluck('total_out'),
        ];

        // --- Data for Pie Chart (Movement Type) ---
        $pieChartResult = (clone $baseQuery)
            ->select('type', DB::raw('count(*) as count'))
            ->groupBy('type')
            ->get();

        $pieChartData = [
            'labels' => $pieChartResult->pluck('type')->map(function($type) {
                return ucwords(str_replace('_', ' ', $type));
            }),
            'series' => $pieChartResult->pluck('count'),
        ];
        
        $warehouses = auth()->user() && auth()->user()->warehouse_id
            ? Warehouse::where('id', auth()->user()->warehouse_id)->get()
            : Warehouse::all();
        $items = Item::orderBy('nama_barang')->get(['id','sku','nama_barang']);

        return view('admin.laporan.pergerakan-barang', compact(
            'stats',
            'trendData',
            'pieChartData',
            'warehouses',
            'items',
            'dateRange'
        ));
    }

    public function pergerakanBarangData(Request $request)
    {
        $searchValue = $request->input('search.value', '');
        $start = $request->input('start', 0);
        $length = $request->input('length', 10);
        $draw = $request->input('draw', 0);

        // Filters
        $dateRange = $request->input('date_range');
        $typeFilter = $request->input('type_filter');
        $warehouseFilter = $request->input('warehouse_filter');
        $itemFilter = $request->input('item_filter');

        // Base query with joins
        $query = StockMovement::query()->from('stock_movements as sm')->select([
            'sm.date',
            'i.nama_barang as item_name',
            'i.sku as sku',
            'w.name as warehouse_name',
            'sm.type',
            'sm.description',
            'sm.stock_before',
            'sm.quantity',
            'sm.stock_after',
            'u.name as user_name',
        ])
        ->leftJoin('items as i', 'sm.item_id', '=', 'i.id')
        ->leftJoin('warehouses as w', 'sm.warehouse_id', '=', 'w.id')
        ->leftJoin('users as u', 'sm.user_id', '=', 'u.id');

        // Enforce user warehouse if any
        if (auth()->user() && auth()->user()->warehouse_id) {
            $query->where('sm.warehouse_id', auth()->user()->warehouse_id);
        }

        // Date range
        if ($dateRange) {
            $dates = explode(' - ', $dateRange);
            if (count($dates) == 2) {
                try {
                    $startDate = Carbon::createFromFormat('d/m/Y', $dates[0])->startOfDay();
                    $endDate = Carbon::createFromFormat('d/m/Y', $dates[1])->endOfDay();
                    $query->whereBetween('sm.date', [$startDate, $endDate]);
                } catch (\Exception $e) {
                    // ignore invalid date format
                }
            }
        }

        // Other filters
        if ($typeFilter && $typeFilter !== 'semua') {
            $query->where('sm.type', $typeFilter);
        }
        if ($warehouseFilter) {
            $query->where('sm.warehouse_id', $warehouseFilter);
        }
        if ($itemFilter) {
            $query->where('sm.item_id', $itemFilter);
        }

        $totalRecords = (clone $query)->count();

        // Search
        if (!empty($searchValue)) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('i.nama_barang', 'LIKE', "%{$searchValue}%")
                  ->orWhere('i.sku', 'LIKE', "%{$searchValue}%")
                  ->orWhere('w.name', 'LIKE', "%{$searchValue}%")
                  ->orWhere('sm.description', 'LIKE', "%{$searchValue}%")
                  ->orWhere('sm.type', 'LIKE', "%{$searchValue}%")
                  ->orWhere('u.name', 'LIKE', "%{$searchValue}%");
            });
        }

        $totalFiltered = (clone $query)->count();

        // Ordering
        $orderColumnIndex = $request->input('order.0.column', 0);
        $orderDir = $request->input('order.0.dir', 'desc');
        $columnsMap = [
            0 => 'sm.date',
            1 => 'i.nama_barang',
            2 => 'w.name',
            3 => 'sm.type',
            4 => 'sm.description',
            5 => 'sm.stock_before',
            6 => 'sm.quantity',
            7 => 'sm.stock_after',
            8 => 'u.name',
        ];
        $orderBy = $columnsMap[$orderColumnIndex] ?? 'sm.date';

        $data = $query->orderBy($orderBy, $orderDir)
            ->offset($start)
            ->limit($length)
            ->get()
            ->map(function ($row) {
                $row->date = Carbon::parse($row->date)->format('d M Y, H:i');
                return $row;
            });

        return response()->json([
            'draw' => intval($draw),
            'recordsTotal' => intval($totalRecords),
            'recordsFiltered' => intval($totalFiltered),
            'data' => $data,
        ]);
    }
}
