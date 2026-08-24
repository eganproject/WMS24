<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Item;
use App\Models\StockMutation;
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
    public function index()
    {
        return view('admin.reports.stock-runout-forecast.index', [
            'dataUrl' => route('admin.reports.stock-runout-forecast.data'),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function data(Request $request)
    {
        $historyDays = max(1, min(365, (int) $request->input('history_days', 30)));
        $forecastDays = max(1, min(365, (int) $request->input('forecast_days', 14)));
        $runoutWithinDays = $request->filled('runout_within_days')
            ? max(0, min(365, (int) $request->input('runout_within_days')))
            : null;
        $search = trim((string) $request->input('q', ''));
        $categoryId = $request->input('category_id');

        $periodEnd = today()->endOfDay();
        $periodStart = today()->subDays($historyDays - 1)->startOfDay();

        $outboundQuery = StockMutation::query()
            ->select('item_id', DB::raw('SUM(qty) as total_outbound'))
            ->where('direction', 'out')
            ->where(function ($query) {
                $query->where('source_type', 'qc_shipment')
                    ->orWhere(function ($manualQuery) {
                        $manualQuery->where('source_type', 'outbound')
                            ->where('source_subtype', 'manual');
                    });
            })
            ->whereBetween('occurred_at', [$periodStart, $periodEnd]);

        // Database lama belum memiliki kolom void; pada database baru mutasi yang dibatalkan tidak boleh dihitung.
        if (Schema::hasColumn('stock_mutations', 'is_void')) {
            $outboundQuery->where('is_void', false);
        }

        $outboundQuery->groupBy('item_id');

        $stockQuery = DB::table('item_stocks as stock')
            ->join('warehouses as warehouse', 'warehouse.id', '=', 'stock.warehouse_id')
            ->where(function ($query) {
                $query->whereNull('warehouse.type')->orWhere('warehouse.type', '!=', 'damaged');
            })
            ->select('stock.item_id', DB::raw('SUM(stock.stock) as stock'))
            ->groupBy('stock.item_id');

        $stockExpr = 'COALESCE(stock_rows.stock, 0)';
        $outboundExpr = 'COALESCE(outbound_usage.total_outbound, 0)';
        $averageExpr = "({$outboundExpr} / {$historyDays})";
        $forecastExpr = "({$stockExpr} - ({$averageExpr} * {$forecastDays}))";

        $baseQuery = Item::query()
            ->leftJoinSub($stockQuery, 'stock_rows', fn ($join) => $join->on('stock_rows.item_id', '=', 'items.id'))
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
            })
            ->whereRaw("{$averageExpr} > 0")
            ->whereRaw("{$forecastExpr} < 0")
            ->when($runoutWithinDays !== null, fn ($query) => $query->whereRaw("({$stockExpr} / {$averageExpr}) <= ?", [$runoutWithinDays]));

        $summary = $this->summary($baseQuery, $stockExpr, $outboundExpr, $averageExpr, $forecastDays, $historyDays);
        $recordsFiltered = (clone $baseQuery)->count('items.id');
        $start = max(0, (int) $request->input('start', 0));
        $length = min(100, max(1, (int) $request->input('length', 25)));

        $rows = $baseQuery
            ->select([
                'items.sku', 'items.name', 'categories.name as category_name',
                DB::raw("{$stockExpr} as stock"),
                DB::raw("{$outboundExpr} as total_outbound"),
            ])
            ->orderByRaw("CASE WHEN {$forecastExpr} <= 0 OR {$stockExpr} <= 0 THEN 0 WHEN {$averageExpr} <= 0 THEN 2 ELSE 1 END")
            ->orderByRaw("CASE WHEN {$averageExpr} > 0 THEN {$stockExpr} / {$averageExpr} ELSE 999999 END")
            ->orderBy('items.sku')
            ->offset($start)
            ->limit($length)
            ->get()
            ->map(function ($item) use ($historyDays, $forecastDays) {
            $stock = (int) $item->stock;
            $totalOutbound = (int) $item->total_outbound;
            $dailyAverage = round($totalOutbound / $historyDays, 2);
            $forecastDemand = round($dailyAverage * $forecastDays, 2);
            $forecastStock = round($stock - ($dailyAverage * $forecastDays), 2);
            $daysUntilRunout = $dailyAverage > 0 ? max(0, round($stock / $dailyAverage, 1)) : null;
            $runoutDate = $daysUntilRunout === null
                ? null
                : Carbon::today()->addDays((int) ceil($daysUntilRunout))->toDateString();
            return [
                'sku' => $item->sku,
                'name' => $item->name,
                'category' => $item->category_name ?? 'Tanpa Kategori',
                'stock' => $stock,
                'total_outbound' => $totalOutbound,
                'daily_average' => $dailyAverage,
                'forecast_demand' => $forecastDemand,
                'forecast_stock' => $forecastStock,
                'days_until_runout' => $daysUntilRunout,
                'runout_date' => $runoutDate,
                'restock_need' => max(0, (int) ceil($forecastDemand - $stock)),
            ];
        })->values();

        return response()->json([
            'period' => [
                'history_days' => $historyDays,
                'forecast_days' => $forecastDays,
                'runout_within_days' => $runoutWithinDays,
                'start' => $periodStart->toDateString(),
                'end' => $periodEnd->toDateString(),
                'stock_scope' => 'Gabungan gudang besar dan gudang kecil',
            ],
            'summary' => [
                'total_items' => $summary['total_items'],
                'total_restock_need' => $summary['total_restock_need'],
                'total_daily_average' => $summary['total_daily_average'],
                'nearest_runout_days' => $summary['nearest_runout_days'],
            ],
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsFiltered,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows,
        ]);
    }

    private function summary($query, string $stockExpr, string $outboundExpr, string $averageExpr, int $forecastDays, int $historyDays): array
    {
        $totals = (clone $query)->selectRaw(implode(', ', [
            'COUNT(items.id) as total_items',
            "COALESCE(SUM(CEILING(({$averageExpr} * {$forecastDays}) - {$stockExpr})), 0) as total_restock_need",
            "COALESCE(SUM({$outboundExpr}), 0) as total_outbound",
            "MIN({$stockExpr} / {$averageExpr}) as nearest_runout_days",
        ]))->first();

        return [
            'total_items' => (int) ($totals->total_items ?? 0),
            'total_restock_need' => (int) ($totals->total_restock_need ?? 0),
            'total_daily_average' => round(((float) ($totals->total_outbound ?? 0)) / $historyDays, 2),
            'nearest_runout_days' => isset($totals->nearest_runout_days) ? round((float) $totals->nearest_runout_days, 1) : null,
        ];
    }
}
