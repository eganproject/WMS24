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

        $outboundByItem = $outboundQuery->groupBy('item_id')->pluck('total_outbound', 'item_id');

        $items = Item::query()
            ->with([
                'category:id,name',
                'stocks' => fn ($query) => $query->where('warehouse_id', $warehouseId),
            ])
            ->where('status', Item::STATUS_ACTIVE)
            ->where(function ($query) {
                $query->whereNull('item_type')->orWhere('item_type', '!=', Item::TYPE_BUNDLE);
            })
            ->when($categoryId !== null && $categoryId !== '', function ($query) use ($categoryId) {
                if ((string) $categoryId === '0') {
                    $query->where(fn ($categoryQuery) => $categoryQuery->whereNull('category_id')->orWhere('category_id', 0));
                    return;
                }

                $query->where('category_id', (int) $categoryId);
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(fn ($searchQuery) => $searchQuery
                    ->where('sku', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%"));
            })
            ->orderBy('sku')
            ->get();

        $rows = $items->map(function (Item $item) use ($outboundByItem, $historyDays, $forecastDays) {
            $itemStock = $item->stocks->first();
            $stock = (int) ($itemStock?->stock ?? 0);
            $safetyStock = (int) ($itemStock?->safety_stock ?? $item->safety_stock ?? 0);
            $totalOutbound = (int) ($outboundByItem->get($item->id) ?? 0);
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
                'category' => $item->category?->name ?? 'Tanpa Kategori',
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
        })->filter(fn (array $row) => $status === '' || $status === 'all' || $row['status'] === $status)
            ->sortBy([
                ['days_until_runout', 'asc'],
                ['daily_average', 'desc'],
                ['sku', 'asc'],
            ])->values();

        return response()->json([
            'period' => [
                'history_days' => $historyDays,
                'forecast_days' => $forecastDays,
                'start' => $periodStart->toDateString(),
                'end' => $periodEnd->toDateString(),
                'warehouse' => $warehouse->name,
            ],
            'summary' => [
                'total_items' => $rows->count(),
                'runout' => $rows->where('status', 'runout')->count(),
                'critical' => $rows->where('status', 'critical')->count(),
                'safe' => $rows->where('status', 'safe')->count(),
                'no_demand' => $rows->where('status', 'no_demand')->count(),
            ],
            'data' => $rows,
        ]);
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
