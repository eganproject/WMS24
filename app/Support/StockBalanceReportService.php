<?php

namespace App\Support;

use App\Models\Item;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
        $warehouseIds = array_values(array_unique(array_filter(
            array_map('intval', (array) ($filters['warehouse_ids'] ?? [])),
            fn (int $id) => $id > 0
        )));

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
            ->where('occurred_at', '>=', $dateFrom)
            ->groupBy('item_id', 'warehouse_id');

        if (Schema::hasColumn('stock_mutations', 'is_void')) {
            $movements->where('is_void', false);
        }

        if ($warehouseIds !== []) {
            $movements->whereIn('warehouse_id', $warehouseIds);
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

        if ($warehouseIds !== []) {
            $query->whereIn('item_stocks.warehouse_id', $warehouseIds);
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

    /**
     * Saldo stok konsolidasi per SKU untuk Gudang Besar + Gudang Display.
     *
     * - Masuk: hanya dokumen inbound yang masuk ke Gudang Besar.
     * - Keluar: hanya outbound manual dan QC scan hasil import resi.
     * - Mutasi lain (net): seluruh mutasi lain pada kedua gudang (retur pelanggan,
     *   opname, penyesuaian, barang rusak, transfer ke/dari gudang lain, dll).
     *   Transfer antara Gudang Besar dan Gudang Display saling meniadakan.
     *
     * Dengan begitu saldo akhir = saldo awal + masuk - keluar + mutasi lain selalu seimbang.
     */
    public function consolidatedQuery(array $filters): Builder
    {
        $dateFrom = (string) $filters['date_from'].' 00:00:00';
        $dateTo = (string) $filters['date_to'].' 23:59:59';
        $warehouseIds = WarehouseService::sellableWarehouseIds();
        $mainWarehouseId = (int) (WarehouseService::warehouseIdByCode(WarehouseService::defaultWarehouseCode()) ?? 0);
        $scopeIds = $warehouseIds !== [] ? $warehouseIds : [0];

        $inCondition = "direction = 'in' AND source_type = 'inbound' AND warehouse_id = ?";
        $outCondition = "direction = 'out' AND (source_type = 'qc_shipment' OR (source_type = 'outbound' AND source_subtype = 'manual'))";
        $signedQty = "CASE WHEN direction = 'in' THEN qty ELSE -qty END";

        $movements = DB::table('stock_mutations')
            ->select('item_id')
            ->selectRaw("SUM({$signedQty}) AS net_since_start")
            ->selectRaw("SUM(CASE WHEN occurred_at <= ? THEN {$signedQty} ELSE 0 END) AS period_net", [$dateTo])
            ->selectRaw(
                "SUM(CASE WHEN occurred_at <= ? AND {$inCondition} THEN qty ELSE 0 END) AS period_in",
                [$dateTo, $mainWarehouseId]
            )
            ->selectRaw(
                "SUM(CASE WHEN occurred_at <= ? AND {$outCondition} THEN qty ELSE 0 END) AS period_out",
                [$dateTo]
            )
            ->where('occurred_at', '>=', $dateFrom)
            ->whereIn('warehouse_id', $scopeIds)
            ->groupBy('item_id');

        if (Schema::hasColumn('stock_mutations', 'is_void')) {
            $movements->where('is_void', false);
        }

        $stocks = DB::table('item_stocks')
            ->select('item_id')
            ->selectRaw('SUM(stock) AS current_stock')
            ->whereIn('warehouse_id', $scopeIds)
            ->groupBy('item_id');

        $openingExpression = '(COALESCE(stocks.current_stock, 0) - COALESCE(movements.net_since_start, 0))';
        $inExpression = 'COALESCE(movements.period_in, 0)';
        $outExpression = 'COALESCE(movements.period_out, 0)';
        $otherExpression = "(COALESCE(movements.period_net, 0) - {$inExpression} + {$outExpression})";
        $endingExpression = "({$openingExpression} + COALESCE(movements.period_net, 0))";

        $query = DB::table('items')
            ->joinSub($stocks, 'stocks', 'stocks.item_id', '=', 'items.id')
            ->leftJoinSub($movements, 'movements', 'movements.item_id', '=', 'items.id')
            ->where(function ($query) {
                $query->whereNull('items.item_type')
                    ->orWhere('items.item_type', '!=', Item::TYPE_BUNDLE);
            })
            ->select([
                'items.id as item_id',
                'items.sku',
                'items.name as item_name',
                'items.status as item_status',
            ])
            ->selectRaw("{$openingExpression} AS opening_stock")
            ->selectRaw("{$inExpression} AS stock_in")
            ->selectRaw("{$outExpression} AS stock_out")
            ->selectRaw("{$otherExpression} AS other_net")
            ->selectRaw("{$endingExpression} AS ending_stock");

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $query->where(function ($query) use ($search) {
                $like = '%'.$search.'%';
                $query->where('items.sku', 'like', $like)
                    ->orWhere('items.name', 'like', $like);
            });
        }

        return $query;
    }

    public function consolidatedSummary(Builder $query): object
    {
        return DB::query()
            ->fromSub((clone $query)->reorder(), 'stock_balance_report')
            ->selectRaw('COUNT(*) AS total_items')
            ->selectRaw('COALESCE(SUM(opening_stock), 0) AS opening_stock')
            ->selectRaw('COALESCE(SUM(stock_in), 0) AS stock_in')
            ->selectRaw('COALESCE(SUM(stock_out), 0) AS stock_out')
            ->selectRaw('COALESCE(SUM(other_net), 0) AS other_net')
            ->selectRaw('COALESCE(SUM(ending_stock), 0) AS ending_stock')
            ->first();
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
