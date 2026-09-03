<?php

namespace App\Support;

use App\Models\Item;
use App\Models\StockMutation;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sumber data bersama untuk tabel mutasi dan seluruh sheet export.
 * Menjaga agar satu kombinasi filter selalu menghasilkan dataset yang sama.
 */
class StockMutationReport
{
    private ?Collection $rows = null;

    private ?object $totals = null;

    private ?Collection $dailySummary = null;

    private ?Collection $sourceSummary = null;

    private ?Collection $warehouseSummary = null;

    private ?Collection $itemWarehouseSummary = null;

    private ?int $count = null;

    public function __construct(private array $filters = [])
    {
    }

    public function query(bool $withRelations = true, bool $ordered = true): Builder
    {
        $query = StockMutation::query()->where('is_void', false);

        if ($withRelations) {
            $query->with([
                'item:id,sku,name',
                'referenceItem:id,sku,name',
                'creator:id,name',
                'warehouse:id,code,name',
            ]);
        }

        $this->applySearch($query);
        $this->applyDateFilter($query);
        $this->applyDirectionFilter($query);
        $this->applySourceTypeFilter($query);
        $this->applyItemFilter($query);
        $this->applyWarehouseFilter($query);

        if ($ordered) {
            $query->orderByDesc('occurred_at')->orderByDesc('id');
        }

        return $query;
    }

    public function baseQuery(): Builder
    {
        return $this->query(false, false);
    }

    public function rows(): Collection
    {
        return $this->rows ??= $this->query()->get();
    }

    public function count(): int
    {
        return $this->count ??= $this->baseQuery()->count();
    }

    public function totals(): object
    {
        if ($this->totals !== null) {
            return $this->totals;
        }

        return $this->totals = $this->baseQuery()
            ->selectRaw('COUNT(*) as mutation_count')
            ->selectRaw('COUNT(DISTINCT item_id) as sku_count')
            ->selectRaw('COUNT(DISTINCT '.$this->documentExpression().') as document_count')
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN qty ELSE 0 END), 0) as qty_in")
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'out' THEN qty ELSE 0 END), 0) as qty_out")
            ->selectRaw($this->anomalyExpression().' as anomaly_count')
            ->first();
    }

    public function dailySummary(): Collection
    {
        if ($this->dailySummary !== null) {
            return $this->dailySummary;
        }

        return $this->dailySummary = $this->baseQuery()
            ->selectRaw('DATE(occurred_at) as period_date')
            ->selectRaw('COUNT(*) as mutation_count')
            ->selectRaw('COUNT(DISTINCT '.$this->documentExpression().') as document_count')
            ->selectRaw('COUNT(DISTINCT item_id) as sku_count')
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN qty ELSE 0 END), 0) as qty_in")
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'out' THEN qty ELSE 0 END), 0) as qty_out")
            ->selectRaw($this->anomalyExpression().' as anomaly_count')
            ->groupByRaw('DATE(occurred_at)')
            ->orderByDesc('period_date')
            ->get();
    }

    public function sourceSummary(): Collection
    {
        if ($this->sourceSummary !== null) {
            return $this->sourceSummary;
        }

        return $this->sourceSummary = $this->baseQuery()
            ->select(['source_type', 'source_subtype'])
            ->selectRaw('COUNT(*) as mutation_count')
            ->selectRaw('COUNT(DISTINCT '.$this->documentExpression().') as document_count')
            ->selectRaw('COUNT(DISTINCT item_id) as sku_count')
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN qty ELSE 0 END), 0) as qty_in")
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'out' THEN qty ELSE 0 END), 0) as qty_out")
            ->groupBy('source_type', 'source_subtype')
            ->orderByRaw('SUM(qty) DESC')
            ->get();
    }

    public function warehouseSummary(): Collection
    {
        if ($this->warehouseSummary !== null) {
            return $this->warehouseSummary;
        }

        return $this->warehouseSummary = $this->baseQuery()
            ->leftJoin('warehouses as report_warehouses', 'report_warehouses.id', '=', 'stock_mutations.warehouse_id')
            ->select(['stock_mutations.warehouse_id', 'report_warehouses.code as warehouse_code', 'report_warehouses.name as warehouse_name'])
            ->selectRaw('COUNT(*) as mutation_count')
            ->selectRaw('COUNT(DISTINCT '.$this->documentExpression('stock_mutations.').') as document_count')
            ->selectRaw('COUNT(DISTINCT stock_mutations.item_id) as sku_count')
            ->selectRaw("COALESCE(SUM(CASE WHEN stock_mutations.direction = 'in' THEN stock_mutations.qty ELSE 0 END), 0) as qty_in")
            ->selectRaw("COALESCE(SUM(CASE WHEN stock_mutations.direction = 'out' THEN stock_mutations.qty ELSE 0 END), 0) as qty_out")
            ->selectRaw($this->anomalyExpression('stock_mutations.').' as anomaly_count')
            ->groupBy('stock_mutations.warehouse_id', 'report_warehouses.code', 'report_warehouses.name')
            ->orderByRaw('SUM(stock_mutations.qty) DESC')
            ->get();
    }

    public function itemWarehouseSummary(): Collection
    {
        if ($this->itemWarehouseSummary !== null) {
            return $this->itemWarehouseSummary;
        }

        return $this->itemWarehouseSummary = $this->baseQuery()
            ->leftJoin('warehouses as report_warehouses', 'report_warehouses.id', '=', 'stock_mutations.warehouse_id')
            ->leftJoin('items as report_items', 'report_items.id', '=', 'stock_mutations.item_id')
            ->select([
                'stock_mutations.warehouse_id',
                'stock_mutations.item_id',
                'report_warehouses.code as warehouse_code',
                'report_warehouses.name as warehouse_name',
                'report_items.sku',
                'report_items.name as item_name',
            ])
            ->selectRaw('COUNT(*) as mutation_count')
            ->selectRaw('COUNT(DISTINCT '.$this->documentExpression('stock_mutations.').') as document_count')
            ->selectRaw("COALESCE(SUM(CASE WHEN stock_mutations.direction = 'in' THEN stock_mutations.qty ELSE 0 END), 0) as qty_in")
            ->selectRaw("COALESCE(SUM(CASE WHEN stock_mutations.direction = 'out' THEN stock_mutations.qty ELSE 0 END), 0) as qty_out")
            ->selectRaw('MAX(stock_mutations.qty) as maximum_qty')
            ->selectRaw('MAX(stock_mutations.occurred_at) as last_mutation_at')
            ->selectRaw('SUM(CASE WHEN stock_mutations.stock_before IS NOT NULL AND stock_mutations.stock_after IS NOT NULL THEN 1 ELSE 0 END) as snapshot_count')
            ->selectRaw($this->anomalyExpression('stock_mutations.').' as anomaly_count')
            ->groupBy(
                'stock_mutations.warehouse_id',
                'stock_mutations.item_id',
                'report_warehouses.code',
                'report_warehouses.name',
                'report_items.sku',
                'report_items.name'
            )
            ->orderByRaw('SUM(stock_mutations.qty) DESC')
            ->get();
    }

    public function filters(): array
    {
        return $this->filters;
    }

    public function filterSummary(): string
    {
        $parts = [];
        $dateFrom = trim((string) ($this->filters['date_from'] ?? ''));
        $dateTo = trim((string) ($this->filters['date_to'] ?? ''));
        $parts[] = ($dateFrom !== '' || $dateTo !== '')
            ? 'Periode: '.($dateFrom !== '' ? $dateFrom : 'awal').' s/d '.($dateTo !== '' ? $dateTo : 'akhir')
            : 'Periode: Semua tanggal';

        $warehouseId = $this->resolvedWarehouseFilter();
        $parts[] = $warehouseId === 'all'
            ? 'Gudang: Semua Gudang'
            : 'Gudang: '.(Warehouse::whereKey((int) $warehouseId)->value('name') ?? $warehouseId);

        $direction = trim((string) ($this->filters['direction'] ?? ''));
        if (in_array($direction, ['in', 'out'], true)) {
            $parts[] = 'Arah: '.strtoupper($direction);
        }

        $sourceType = trim((string) ($this->filters['source_type'] ?? ''));
        if ($sourceType !== '') {
            $parts[] = 'Sumber: '.strtoupper(str_replace('_', ' ', $sourceType));
        }

        $itemId = $this->itemFilter();
        if ($itemId !== null) {
            $item = Item::query()->find($itemId, ['sku', 'name']);
            $parts[] = 'Item: '.($item ? trim($item->sku.' - '.$item->name) : '#'.$itemId);
        }

        $search = trim((string) ($this->filters['q'] ?? ''));
        if ($search !== '') {
            $parts[] = 'Pencarian: '.$search;
        }

        return implode(' | ', $parts);
    }

    public function sourceLabel(StockMutation $mutation): string
    {
        return $this->formatSourceLabel($mutation->source_type, $mutation->source_subtype);
    }

    public function formatSourceLabel(?string $sourceType, ?string $sourceSubtype): string
    {
        $type = strtoupper(str_replace('_', ' ', (string) $sourceType));
        $subtype = strtoupper(str_replace('_', ' ', (string) $sourceSubtype));

        return trim($type.($subtype !== '' ? ' / '.$subtype : ''));
    }

    public function balanceCheck(StockMutation $mutation): string
    {
        if ($mutation->stock_before === null || $mutation->stock_after === null) {
            return 'Snapshot tidak tersedia';
        }

        $expected = $mutation->direction === 'in' ? (int) $mutation->qty : -(int) $mutation->qty;
        $actual = (int) $mutation->stock_after - (int) $mutation->stock_before;

        return $actual === $expected ? 'Cocok' : 'Perlu diperiksa';
    }

    private function applySearch(Builder $query): void
    {
        $search = trim((string) ($this->filters['q'] ?? ''));
        if ($search === '') {
            return;
        }

        $exact = trim(strtolower((string) ($this->filters['search_mode'] ?? ''))) === 'exact';
        $query->where(function (Builder $q) use ($search, $exact) {
            $this->applyTextSearch($q, 'source_code', $search, $exact);
            $this->applyTextSearch($q, 'source_type', $search, $exact, 'or');
            $this->applyTextSearch($q, 'source_subtype', $search, $exact, 'or');
            $this->applyTextSearch($q, 'reference_sku', $search, $exact, 'or');
            $this->applyTextSearch($q, 'note', $search, $exact, 'or');
            $q->orWhereHas('creator', function (Builder $creatorQ) use ($search, $exact) {
                $this->applyTextSearch($creatorQ, 'name', $search, $exact);
                $this->applyTextSearch($creatorQ, 'email', $search, $exact, 'or');
            });
            $q->orWhereHas('item', function (Builder $itemQ) use ($search, $exact) {
                $this->applyTextSearch($itemQ, 'sku', $search, $exact);
                $this->applyTextSearch($itemQ, 'name', $search, $exact, 'or');
            });
            $q->orWhereHas('referenceItem', function (Builder $itemQ) use ($search, $exact) {
                $this->applyTextSearch($itemQ, 'sku', $search, $exact);
                $this->applyTextSearch($itemQ, 'name', $search, $exact, 'or');
            });
            $q->orWhereHas('warehouse', function (Builder $warehouseQ) use ($search, $exact) {
                $this->applyTextSearch($warehouseQ, 'name', $search, $exact);
                $this->applyTextSearch($warehouseQ, 'code', $search, $exact, 'or');
            });
        });
    }

    private function applyDateFilter(Builder $query): void
    {
        try {
            if (!empty($this->filters['date_from'])) {
                $query->where('occurred_at', '>=', Carbon::parse($this->filters['date_from'])->startOfDay());
            }
            if (!empty($this->filters['date_to'])) {
                $query->where('occurred_at', '<=', Carbon::parse($this->filters['date_to'])->endOfDay());
            }
        } catch (\Throwable) {
            // Filter tanggal yang tidak valid diabaikan, sama seperti tabel sebelumnya.
        }
    }

    private function applyDirectionFilter(Builder $query): void
    {
        $direction = $this->filters['direction'] ?? null;
        if (in_array($direction, ['in', 'out'], true)) {
            $query->where('direction', $direction);
        }
    }

    private function applySourceTypeFilter(Builder $query): void
    {
        $sourceType = trim((string) ($this->filters['source_type'] ?? ''));
        if ($sourceType !== '') {
            $query->where('source_type', $sourceType);
        }
    }

    private function applyItemFilter(Builder $query): void
    {
        $itemId = $this->itemFilter();
        if ($itemId === null) {
            return;
        }

        $query->where(function (Builder $q) use ($itemId) {
            $q->where('item_id', $itemId)
                ->orWhere('reference_item_id', $itemId);
        });
    }

    private function applyWarehouseFilter(Builder $query): void
    {
        $warehouse = $this->resolvedWarehouseFilter();
        if ($warehouse !== 'all') {
            $query->where('warehouse_id', (int) $warehouse);
        }
    }

    private function resolvedWarehouseFilter(): int|string
    {
        $warehouse = $this->filters['warehouse_id'] ?? null;
        if ($warehouse === null || $warehouse === '') {
            return $this->itemFilter() !== null ? 'all' : WarehouseService::defaultWarehouseId();
        }

        return $warehouse === 'all' ? 'all' : (int) $warehouse;
    }

    private function itemFilter(): ?int
    {
        $value = $this->filters['item_id'] ?? null;
        if ($value === null || $value === '' || (int) $value <= 0) {
            return null;
        }

        return (int) $value;
    }

    private function applyTextSearch(Builder $query, string $column, string $search, bool $exact, string $boolean = 'and'): void
    {
        if ($exact) {
            $method = $boolean === 'or' ? 'orWhereRaw' : 'whereRaw';
            $query->{$method}('LOWER('.$column.') = ?', [mb_strtolower($search)]);

            return;
        }

        $method = $boolean === 'or' ? 'orWhere' : 'where';
        $query->{$method}($column, 'like', '%'.$search.'%');
    }

    private function documentExpression(string $prefix = ''): string
    {
        $sourceType = $prefix.'source_type';
        $sourceId = $prefix.'source_id';

        if (DB::connection()->getDriverName() === 'sqlite') {
            return "COALESCE({$sourceType}, '') || '|' || COALESCE(CAST({$sourceId} AS TEXT), '')";
        }

        return "CONCAT(COALESCE({$sourceType}, ''), '|', COALESCE({$sourceId}, ''))";
    }

    private function anomalyExpression(string $prefix = ''): string
    {
        $before = $prefix.'stock_before';
        $after = $prefix.'stock_after';
        $direction = $prefix.'direction';
        $qty = $prefix.'qty';

        return "COALESCE(SUM(CASE WHEN {$before} IS NOT NULL AND {$after} IS NOT NULL AND ({$after} - {$before}) <> CASE WHEN {$direction} = 'in' THEN {$qty} ELSE -{$qty} END THEN 1 ELSE 0 END), 0)";
    }
}
