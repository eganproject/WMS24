<?php

namespace App\Support;

use App\Models\Item;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class StockBalanceReportService
{
    /**
     * Membentuk laporan saldo dari stok terkini dengan menarik mundur seluruh
     * mutasi sejak awal periode. Cara ini tetap mempertahankan saldo awal yang
     * pernah diimpor langsung ke item_stocks sebelum histori mutasi tersedia.
     */
    public function query(array $filters): Builder
    {
        $dateFrom = (string) $filters['date_from'].' 00:00:00';
        $dateTo = (string) $filters['date_to'].' 23:59:59';
        $warehouseId = (int) ($filters['warehouse_id'] ?? 0);

        $movements = DB::table('stock_mutations')
            ->select(['item_id', 'warehouse_id'])
            ->selectRaw(
                "SUM(CASE WHEN occurred_at >= ? AND direction = 'in' THEN qty ELSE 0 END) AS movement_in_since_start",
                [$dateFrom]
            )
            ->selectRaw(
                "SUM(CASE WHEN occurred_at >= ? AND direction = 'out' THEN qty ELSE 0 END) AS movement_out_since_start",
                [$dateFrom]
            )
            ->selectRaw(
                "SUM(CASE WHEN occurred_at BETWEEN ? AND ? AND direction = 'in' THEN qty ELSE 0 END) AS period_in",
                [$dateFrom, $dateTo]
            )
            ->selectRaw(
                "SUM(CASE WHEN occurred_at BETWEEN ? AND ? AND direction = 'out' THEN qty ELSE 0 END) AS period_out",
                [$dateFrom, $dateTo]
            )
            ->where('is_void', false)
            ->where('occurred_at', '>=', $dateFrom)
            ->groupBy('item_id', 'warehouse_id');

        if ($warehouseId > 0) {
            $movements->where('warehouse_id', $warehouseId);
        }

        $openingExpression = '(COALESCE(item_stocks.stock, 0) - COALESCE(movements.movement_in_since_start, 0) + COALESCE(movements.movement_out_since_start, 0))';
        $endingExpression = "({$openingExpression} + COALESCE(movements.period_in, 0) - COALESCE(movements.period_out, 0))";

        $query = DB::table('item_stocks')
            ->join('items', 'items.id', '=', 'item_stocks.item_id')
            ->join('warehouses', 'warehouses.id', '=', 'item_stocks.warehouse_id')
            ->leftJoinSub($movements, 'movements', function ($join) {
                $join->on('movements.item_id', '=', 'item_stocks.item_id')
                    ->on('movements.warehouse_id', '=', 'item_stocks.warehouse_id');
            })
            ->where(function ($query) {
                $query->whereNull('items.item_type')
                    ->orWhere('items.item_type', '!=', Item::TYPE_BUNDLE);
            })
            ->select([
                'items.id as item_id',
                'items.sku',
                'items.name as item_name',
                'items.status as item_status',
                'warehouses.id as warehouse_id',
                'warehouses.code as warehouse_code',
                'warehouses.name as warehouse_name',
            ])
            ->selectRaw("{$openingExpression} AS opening_stock")
            ->selectRaw('COALESCE(movements.period_in, 0) AS stock_in')
            ->selectRaw('COALESCE(movements.period_out, 0) AS stock_out')
            ->selectRaw("{$endingExpression} AS ending_stock");

        if ($warehouseId > 0) {
            $query->where('item_stocks.warehouse_id', $warehouseId);
        }

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $query->where(function ($query) use ($search) {
                $like = '%'.$search.'%';
                $query->where('items.sku', 'like', $like)
                    ->orWhere('items.name', 'like', $like)
                    ->orWhere('warehouses.name', 'like', $like)
                    ->orWhere('warehouses.code', 'like', $like);
            });
        }

        return $query;
    }

    public function summary(Builder $query): object
    {
        return DB::query()
            ->fromSub((clone $query)->reorder(), 'stock_balance_report')
            ->selectRaw('COUNT(*) AS total_rows')
            ->selectRaw('COUNT(DISTINCT item_id) AS total_items')
            ->selectRaw('COUNT(DISTINCT warehouse_id) AS total_warehouses')
            ->selectRaw('COALESCE(SUM(opening_stock), 0) AS opening_stock')
            ->selectRaw('COALESCE(SUM(stock_in), 0) AS stock_in')
            ->selectRaw('COALESCE(SUM(stock_out), 0) AS stock_out')
            ->selectRaw('COALESCE(SUM(ending_stock), 0) AS ending_stock')
            ->first();
    }
}
