<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Item;
use App\Models\PickingList;
use App\Models\Warehouse;
use App\Support\WarehouseService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DailyStockForecastController extends Controller
{
    public function index()
    {
        $displayWarehouseId = WarehouseService::displayWarehouseId();
        $defaultWarehouseId = WarehouseService::defaultWarehouseId();

        return view('admin.reports.daily-stock-forecast.index', [
            'dataUrl' => route('admin.reports.daily-stock-forecast.data'),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'forecastDate' => today()->toDateString(),
            'displayWarehouse' => Warehouse::find($displayWarehouseId),
            'defaultWarehouse' => Warehouse::find($defaultWarehouseId),
        ]);
    }

    public function data(Request $request)
    {
        $forecastDate = Carbon::parse($request->input('date') ?: today()->toDateString())->startOfDay();

        $displayWarehouseId = WarehouseService::displayWarehouseId();
        $defaultWarehouseId = WarehouseService::defaultWarehouseId();
        $search = trim((string) $request->input('q', ''));
        $categoryId = $request->input('category_id');
        $status = (string) $request->input('status', 'all');
        $limit = max(1, min(500, (int) $request->input('limit', 100)));

        $items = Item::query()
            ->with([
                'category:id,name',
                'area:id,code,name',
                'location.area:id,code,name',
                'stocks' => fn ($query) => $query->whereIn('warehouse_id', [$displayWarehouseId, $defaultWarehouseId]),
            ])
            ->where('status', Item::STATUS_ACTIVE)
            ->when($categoryId !== null && $categoryId !== '', function ($query) use ($categoryId) {
                if ((string) $categoryId === '0') {
                    $query->where(function ($categoryQuery) {
                        $categoryQuery->whereNull('category_id')->orWhere('category_id', 0);
                    });
                    return;
                }

                $query->where('category_id', (int) $categoryId);
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery
                        ->where('sku', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->orderBy('sku')
            ->get();

        $skuList = $items->pluck('sku')->filter()->values();
        $dailyRemaining = $this->dailyRemainingBySku($skuList, $forecastDate);

        $rows = $items
            ->map(fn (Item $item) => $this->mapForecastRow($item, $dailyRemaining, $forecastDate, $displayWarehouseId, $defaultWarehouseId))
            ->filter(fn (array $row) => $this->matchesStatus($row, $status))
            ->sortBy([
                ['forecast_stock', 'asc'],
                ['total_remaining', 'desc'],
                ['sku', 'asc'],
            ])
            ->values();

        $summaryRows = $rows;
        $rows = $rows->take($limit)->values();

        return response()->json([
            'summary' => [
                'total_items' => $summaryRows->count(),
                'out_stock' => $summaryRows->where('status', 'out')->count(),
                'low_stock' => $summaryRows->where('status', 'low')->count(),
                'safe_stock' => $summaryRows->where('status', 'safe')->count(),
                'high_stock' => $summaryRows->where('status', 'high')->count(),
                'total_remaining' => (int) $summaryRows->sum('total_remaining'),
                'total_display_stock' => (int) $summaryRows->sum('display_stock'),
                'total_default_stock' => (int) $summaryRows->sum('default_stock'),
            ],
            'date' => $forecastDate->toDateString(),
            'data' => $rows,
        ]);
    }

    private function dailyRemainingBySku(Collection $skuList, Carbon $forecastDate): Collection
    {
        if ($skuList->isEmpty()) {
            return collect();
        }

        return PickingList::query()
            ->selectRaw('sku, SUM(GREATEST(remaining_qty, 0)) as remaining_qty')
            ->whereIn('sku', $skuList)
            ->whereDate('list_date', $forecastDate->toDateString())
            ->where('remaining_qty', '>', 0)
            ->groupBy('sku')
            ->get()
            ->keyBy('sku');
    }

    private function mapForecastRow(
        Item $item,
        Collection $dailyRemaining,
        Carbon $forecastDate,
        int $displayWarehouseId,
        int $defaultWarehouseId
    ): array {
        $stocks = $item->stocks->keyBy('warehouse_id');
        $displayStock = (int) ($stocks->get($displayWarehouseId)?->stock ?? 0);
        $defaultStock = (int) ($stocks->get($defaultWarehouseId)?->stock ?? 0);
        $safetyStock = (int) ($stocks->get($displayWarehouseId)?->safety_stock ?? $item->safety_stock ?? 0);
        $totalRemaining = (int) ($dailyRemaining->get($item->sku)?->remaining_qty ?? 0);

        $forecastStock = $displayStock - $totalRemaining;
        $status = $this->forecastStatus($forecastStock, $safetyStock, $totalRemaining, $displayStock);

        return [
            'sku' => $item->sku,
            'name' => $item->name,
            'category' => $item->category?->name ?? 'Tanpa Kategori',
            'area' => $item->resolvedArea()?->code ?? '-',
            'address' => $item->resolvedAddress() ?: '-',
            'display_stock' => $displayStock,
            'default_stock' => $defaultStock,
            'safety_stock' => $safetyStock,
            'total_remaining' => $totalRemaining,
            'forecast_stock' => $forecastStock,
            'forecast_date' => $forecastDate->toDateString(),
            'status' => $status['key'],
            'status_label' => $status['label'],
            'status_class' => $status['class'],
            'replenishment_need' => max(0, $safetyStock - $forecastStock),
        ];
    }

    private function forecastStatus(int $forecastStock, int $safetyStock, int $totalRemaining, int $displayStock): array
    {
        if ($forecastStock < 0) {
            return ['key' => 'out', 'label' => 'Kurang', 'class' => 'danger'];
        }

        if ($forecastStock <= $safetyStock) {
            return ['key' => 'low', 'label' => 'Menipis', 'class' => 'warning'];
        }

        if ($safetyStock > 0 && $forecastStock >= ($safetyStock * 2)) {
            return ['key' => 'high', 'label' => 'Banyak', 'class' => 'success'];
        }

        if ($safetyStock <= 0 && $totalRemaining > 0 && $displayStock > 0 && $forecastStock >= ($totalRemaining * 2)) {
            return ['key' => 'high', 'label' => 'Banyak', 'class' => 'success'];
        }

        return ['key' => 'safe', 'label' => 'Aman', 'class' => 'primary'];
    }

    private function matchesStatus(array $row, string $status): bool
    {
        if ($status === '' || $status === 'all') {
            return true;
        }

        return $row['status'] === $status;
    }
}
