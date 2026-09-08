<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StockMovementAnalysisService
{
    public const CATEGORY_FAST = 'fast';

    public const CATEGORY_MEDIUM = 'medium';

    public const CATEGORY_SLOW = 'slow';

    public const CATEGORY_DEAD_STOCK = 'dead_stock';

    public const CATEGORY_NO_STOCK = 'no_stock';

    public function __construct(private StockBalanceReportService $stockBalanceReport) {}

    /**
     * Analisis per SKU memakai permintaan keluar aktual, bukan seluruh mutasi OUT.
     * Transfer, opname, barang rusak, dan penyesuaian tidak dianggap sebagai demand.
     */
    public function query(array $filters): Builder
    {
        $dateFrom = (string) $filters['date_from'].' 00:00:00';
        $dateTo = (string) $filters['date_to'].' 23:59:59';
        $periodDays = (int) Carbon::parse($filters['date_from'])
            ->diffInDays(Carbon::parse($filters['date_to'])) + 1;
        $warehouseIds = $this->warehouseIds($filters);

        $balanceFilters = $filters;
        $balanceFilters['q'] = '';

        $itemBalances = DB::query()
            ->fromSub($this->stockBalanceReport->query($balanceFilters)->reorder(), 'stock_rows')
            ->select([
                'stock_rows.item_id',
                'stock_rows.sku',
                'stock_rows.item_name',
                'stock_rows.item_status',
            ])
            ->selectRaw('SUM(stock_rows.opening_stock) AS opening_stock')
            ->selectRaw('SUM(stock_rows.stock_in) AS stock_in')
            ->selectRaw('SUM(stock_rows.stock_out) AS stock_out')
            ->selectRaw('SUM(stock_rows.ending_stock) AS ending_stock')
            ->groupBy(
                'stock_rows.item_id',
                'stock_rows.sku',
                'stock_rows.item_name',
                'stock_rows.item_status'
            );

        // Sintaks CONCAT berbeda antara SQLite dan MySQL; subquery ini menghitung
        // pasangan tipe dan ID dokumen secara akurat pada kedua database.
        $periodDemandDocuments = $this->demandQuery($warehouseIds)
            ->whereBetween('occurred_at', [$dateFrom, $dateTo])
            ->select(['item_id', 'source_type', 'source_id'])
            ->distinct();

        $documentCounts = DB::query()
            ->fromSub($periodDemandDocuments, 'demand_document_rows')
            ->select(['item_id'])
            ->selectRaw('COUNT(*) AS demand_documents')
            ->groupBy('item_id');

        $periodDemand = $this->demandQuery($warehouseIds)
            ->whereBetween('occurred_at', [$dateFrom, $dateTo])
            ->select(['item_id'])
            ->selectRaw('SUM(qty) AS demand_out')
            ->groupBy('item_id');

        $lastDemand = $this->demandQuery($warehouseIds)
            ->where('occurred_at', '<=', $dateTo)
            ->select(['item_id'])
            ->selectRaw('MAX(occurred_at) AS last_out_at')
            ->groupBy('item_id');

        $demandExpression = 'COALESCE(period_demand.demand_out, 0)';
        $endingExpression = 'item_balances.ending_stock';
        $averageDailyExpression = "({$demandExpression} * 1.0 / {$periodDays})";
        $averageInventoryExpression = '((item_balances.opening_stock + item_balances.ending_stock) * 1.0 / 2)';
        $categoryExpression = $this->categoryExpression($demandExpression, $endingExpression, $periodDays);

        $metrics = DB::query()
            ->fromSub($itemBalances, 'item_balances')
            ->leftJoinSub($periodDemand, 'period_demand', 'period_demand.item_id', '=', 'item_balances.item_id')
            ->leftJoinSub($documentCounts, 'document_counts', 'document_counts.item_id', '=', 'item_balances.item_id')
            ->leftJoinSub($lastDemand, 'last_demand', 'last_demand.item_id', '=', 'item_balances.item_id')
            ->select([
                'item_balances.item_id',
                'item_balances.sku',
                'item_balances.item_name',
                'item_balances.item_status',
                'item_balances.opening_stock',
                'item_balances.stock_in',
                'item_balances.stock_out',
                'item_balances.ending_stock',
                'last_demand.last_out_at',
            ])
            ->selectRaw("{$demandExpression} AS demand_out")
            ->selectRaw('COALESCE(document_counts.demand_documents, 0) AS demand_documents')
            ->selectRaw("{$averageDailyExpression} AS average_daily_out")
            ->selectRaw("CASE WHEN {$averageInventoryExpression} > 0 THEN {$demandExpression} * 1.0 / {$averageInventoryExpression} ELSE NULL END AS turnover_rate")
            ->selectRaw("CASE WHEN {$demandExpression} > 0 AND {$endingExpression} > 0 THEN {$endingExpression} / {$averageDailyExpression} ELSE NULL END AS stock_coverage_days")
            ->selectRaw("{$categoryExpression} AS movement_category");

        $query = DB::query()->fromSub($metrics, 'movement_analysis')->select('movement_analysis.*');

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $query) use ($search) {
                $like = '%'.$search.'%';
                $query->where('movement_analysis.sku', 'like', $like)
                    ->orWhere('movement_analysis.item_name', 'like', $like);
            });
        }

        $category = trim((string) ($filters['movement_category'] ?? ''));
        if (in_array($category, self::categories(), true)) {
            $query->where('movement_analysis.movement_category', $category);
        }

        return $query;
    }

    public function summary(Builder $query): object
    {
        return DB::query()
            ->fromSub((clone $query)->reorder(), 'movement_summary')
            ->selectRaw('COUNT(*) AS total_items')
            ->selectRaw("SUM(CASE WHEN movement_category = 'fast' THEN 1 ELSE 0 END) AS fast_items")
            ->selectRaw("SUM(CASE WHEN movement_category = 'medium' THEN 1 ELSE 0 END) AS medium_items")
            ->selectRaw("SUM(CASE WHEN movement_category = 'slow' THEN 1 ELSE 0 END) AS slow_items")
            ->selectRaw("SUM(CASE WHEN movement_category = 'dead_stock' THEN 1 ELSE 0 END) AS dead_stock_items")
            ->selectRaw("SUM(CASE WHEN movement_category = 'no_stock' THEN 1 ELSE 0 END) AS no_stock_items")
            ->selectRaw('COALESCE(SUM(demand_out), 0) AS demand_out')
            ->selectRaw('COALESCE(SUM(ending_stock), 0) AS ending_stock')
            ->first();
    }

    public static function categories(): array
    {
        return [
            self::CATEGORY_FAST,
            self::CATEGORY_MEDIUM,
            self::CATEGORY_SLOW,
            self::CATEGORY_DEAD_STOCK,
            self::CATEGORY_NO_STOCK,
        ];
    }

    private function demandQuery(array $warehouseIds): Builder
    {
        $query = DB::table('stock_mutations')
            ->where('direction', 'out')
            ->where(function (Builder $query) {
                $query->where('source_type', 'qc_shipment')
                    ->orWhere(function (Builder $manualQuery) {
                        $manualQuery->where('source_type', 'outbound')
                            ->where('source_subtype', 'manual');
                    });
            })
            ->when($warehouseIds !== [], fn (Builder $query) => $query->whereIn('warehouse_id', $warehouseIds));

        if (Schema::hasColumn('stock_mutations', 'is_void')) {
            $query->where('is_void', false);
        }

        return $query;
    }

    private function warehouseIds(array $filters): array
    {
        return array_values(array_unique(array_filter(
            array_map('intval', (array) ($filters['warehouse_ids'] ?? [])),
            fn (int $id) => $id > 0
        )));
    }

    private function categoryExpression(string $demandExpression, string $endingExpression, int $periodDays): string
    {
        return "CASE
            WHEN {$demandExpression} >= {$periodDays} THEN 'fast'
            WHEN {$demandExpression} > 0 AND ({$demandExpression} * 7) >= {$periodDays} THEN 'medium'
            WHEN {$demandExpression} > 0 THEN 'slow'
            WHEN {$endingExpression} > 0 THEN 'dead_stock'
            ELSE 'no_stock'
        END";
    }
}
