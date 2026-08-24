<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Item;
use App\Models\StockMutation;
use App\Models\Warehouse;
use App\Support\WarehouseService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StockRunoutForecastController extends Controller
{
    /**
     * Source mutasi yang benar-benar merepresentasikan barang keluar untuk pesanan.
     * Transfer, penyesuaian, dan barang rusak sengaja tidak dihitung sebagai permintaan harian.
     */
    private const DEMAND_SOURCE_TYPES = ['outbound', 'qc_shipment'];

    public function index()
    {
        $defaultWarehouseId = WarehouseService::defaultWarehouseId();

        return view('admin.reports.stock-runout-forecast.index', [
            'dataUrl' => route('admin.reports.stock-runout-forecast.data'),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'warehouses' => Warehouse::orderBy('name')->get(['id', 'code', 'name']),
            'defaultWarehouseId' => $defaultWarehouseId,
        ]);
    }

    public function data(Request $request)
    {
        $historyDays = max(1, min(365, (int) $request->input('history_days', 30)));
        $forecastDays = max(1, min(365, (int) $request->input('forecast_days', 14)));
        $warehouseId = (int) $request->input('warehouse_id', WarehouseService::defaultWarehouseId());
        $search = trim((string) $request->input('q', ''));
        $categoryId = $request->input('category_id');
        $status = (string) $request->input('status', 'all');

        $warehouse = Warehouse::findOrFail($warehouseId);
        $periodEnd = today()->endOfDay();
        $periodStart = today()->subDays($historyDays - 1)->startOfDay();

        $outboundQuery = StockMutation::query()
            ->select('item_id', DB::raw('SUM(qty) as total_outbound'))
            ->where('warehouse_id', $warehouseId)
            ->where('direction', 'out')
            ->whereIn('source_type', self::DEMAND_SOURCE_TYPES)
            ->whereBetween('occurred_at', [$periodStart, $periodEnd]);

        // Database lama belum memiliki kolom void; pada database baru mutasi yang dibatalkan tidak boleh dihitung.
        if (Schema::hasColumn('stock_mutations', 'is_void')) {
            $outboundQuery->where('is_void', false);
        }

        $outboundQuery->groupBy('item_id');

        $stockExpr = 'COALESCE(stock_rows.stock, 0)';
        $safetyExpr = 'COALESCE(stock_rows.safety_stock, items.safety_stock, 0)';
        $outboundExpr = 'COALESCE(outbound_usage.total_outbound, 0)';
        $averageExpr = "({$outboundExpr} / {$historyDays})";
        $forecastExpr = "({$stockExpr} - ({$averageExpr} * {$forecastDays}))";

        $baseQuery = Item::query()
            ->leftJoin('item_stocks as stock_rows', function ($join) use ($warehouseId) {
                $join->on('stock_rows.item_id', '=', 'items.id')
                    ->where('stock_rows.warehouse_id', '=', $warehouseId);
            })
            ->leftJoinSub($outboundQuery, 'outbound_usage', fn ($join) => $join->on('outbound_usage.item_id', '=', 'items.id'))
            ->leftJoin('categories', 'categories.id', '=', 'items.category_id')
            ->where('items.status', Item::STATUS_ACTIVE)
            ->where(function ($query) {
                $query->whereNull('item_type')->orWhere('item_type', '!=', Item::TYPE_BUNDLE);
            })
            ->when($categoryId !== null && $categoryId !== '', function ($query) use ($categoryId) {
                if ((string) $categoryId === '0') {
                    $query->where(fn ($categoryQuery) => $categoryQuery->whereNull('items.category_id')->orWhere('items.category_id', 0));
                    return;
                }

                $query->where('items.category_id', (int) $categoryId);
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(fn ($searchQuery) => $searchQuery
                    ->where('items.sku', 'like', "%{$search}%")
                    ->orWhere('items.name', 'like', "%{$search}%"));
            });

        $summary = $this->summary($baseQuery, $stockExpr, $safetyExpr, $averageExpr, $forecastExpr);
        $dataQuery = clone $baseQuery;
        $this->applyStatusFilter($dataQuery, $status, $stockExpr, $safetyExpr, $averageExpr, $forecastExpr);
        $recordsFiltered = (clone $dataQuery)->count('items.id');
        $start = max(0, (int) $request->input('start', 0));
        $length = min(100, max(1, (int) $request->input('length', 25)));

        $rows = $dataQuery
            ->select([
                'items.sku', 'items.name', 'categories.name as category_name',
                DB::raw("{$stockExpr} as stock"), DB::raw("{$safetyExpr} as safety_stock"),
                DB::raw("{$outboundExpr} as total_outbound"),
            ])
            ->orderByRaw("CASE WHEN {$forecastExpr} <= 0 OR {$stockExpr} <= 0 THEN 0 WHEN {$forecastExpr} <= {$safetyExpr} THEN 1 WHEN {$averageExpr} <= 0 THEN 3 ELSE 2 END")
            ->orderByRaw("CASE WHEN {$averageExpr} > 0 THEN {$stockExpr} / {$averageExpr} ELSE 999999 END")
            ->orderBy('items.sku')
            ->offset($start)
            ->limit($length)
            ->get()
            ->map(function ($item) use ($historyDays, $forecastDays) {
            $stock = (int) $item->stock;
            $safetyStock = (int) $item->safety_stock;
            $totalOutbound = (int) $item->total_outbound;
            $dailyAverage = round($totalOutbound / $historyDays, 2);
            $forecastStock = round($stock - ($dailyAverage * $forecastDays), 2);
            $daysUntilSafety = $dailyAverage > 0 ? max(0, round(($stock - $safetyStock) / $dailyAverage, 1)) : null;
            $daysUntilRunout = $dailyAverage > 0 ? max(0, round($stock / $dailyAverage, 1)) : null;
            $runoutDate = $daysUntilRunout === null
                ? null
                : Carbon::today()->addDays((int) ceil($daysUntilRunout))->toDateString();
            $rowStatus = $this->status($stock, $safetyStock, $dailyAverage, $forecastStock);

            return [
                'sku' => $item->sku,
                'name' => $item->name,
                'category' => $item->category_name ?? 'Tanpa Kategori',
                'stock' => $stock,
                'safety_stock' => $safetyStock,
                'total_outbound' => $totalOutbound,
                'daily_average' => $dailyAverage,
                'forecast_stock' => $forecastStock,
                'days_until_safety' => $daysUntilSafety,
                'days_until_runout' => $daysUntilRunout,
                'runout_date' => $runoutDate,
                'replenishment_need' => max(0, (int) ceil($safetyStock - $forecastStock)),
                'status' => $rowStatus['key'],
                'status_label' => $rowStatus['label'],
                'status_class' => $rowStatus['class'],
            ];
        })->values();

        return response()->json([
            'period' => [
                'history_days' => $historyDays,
                'forecast_days' => $forecastDays,
                'start' => $periodStart->toDateString(),
                'end' => $periodEnd->toDateString(),
                'warehouse' => $warehouse->name,
            ],
            'summary' => [
                'total_items' => $summary['total_items'],
                'runout' => $summary['runout'],
                'critical' => $summary['critical'],
                'safe' => $summary['safe'],
                'no_demand' => $summary['no_demand'],
            ],
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsFiltered,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows,
        ]);
    }

    private function applyStatusFilter($query, string $status, string $stockExpr, string $safetyExpr, string $averageExpr, string $forecastExpr): void
    {
        if ($status === '' || $status === 'all') return;

        match ($status) {
            'runout' => $query->whereRaw("{$stockExpr} <= 0 OR {$forecastExpr} <= 0"),
            'critical' => $query->whereRaw("{$averageExpr} > 0 AND {$forecastExpr} > 0 AND {$forecastExpr} <= {$safetyExpr}"),
            'safe' => $query->whereRaw("{$averageExpr} > 0 AND {$forecastExpr} > {$safetyExpr}"),
            'no_demand' => $query->whereRaw("{$stockExpr} > 0 AND {$averageExpr} <= 0"),
            default => null,
        };
    }

    private function summary($query, string $stockExpr, string $safetyExpr, string $averageExpr, string $forecastExpr): array
    {
        $count = function (string $status) use ($query, $stockExpr, $safetyExpr, $averageExpr, $forecastExpr): int {
            $filteredQuery = clone $query;
            $this->applyStatusFilter($filteredQuery, $status, $stockExpr, $safetyExpr, $averageExpr, $forecastExpr);

            return $filteredQuery->count('items.id');
        };

        return [
            'total_items' => (clone $query)->count('items.id'),
            'runout' => $count('runout'), 'critical' => $count('critical'),
            'safe' => $count('safe'), 'no_demand' => $count('no_demand'),
        ];
    }

    private function status(int $stock, int $safetyStock, float $dailyAverage, float $forecastStock): array
    {
        if ($stock <= 0 || $forecastStock <= 0) {
            return ['key' => 'runout', 'label' => 'Akan Habis', 'class' => 'danger'];
        }

        if ($dailyAverage > 0 && $forecastStock <= $safetyStock) {
            return ['key' => 'critical', 'label' => 'Di Bawah Safety', 'class' => 'warning'];
        }

        if ($dailyAverage <= 0) {
            return ['key' => 'no_demand', 'label' => 'Belum Ada Keluar', 'class' => 'secondary'];
        }

        return ['key' => 'safe', 'label' => 'Aman', 'class' => 'success'];
    }
}
