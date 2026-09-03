<?php

namespace App\Support;

use App\Models\Item;
use App\Models\StockMutation;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Sumber data bersama untuk tabel mutasi dan seluruh sheet export.
 * Menjaga agar satu kombinasi filter selalu menghasilkan dataset yang sama.
 */
class StockMutationReport
{
    private ?Collection $rows = null;

    public function __construct(private array $filters = [])
    {
    }

    public function query(): Builder
    {
        $query = StockMutation::query()
            ->with(['item', 'referenceItem', 'creator', 'warehouse'])
            ->where('is_void', false)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');

        $this->applySearch($query);
        $this->applyDateFilter($query);
        $this->applyDirectionFilter($query);
        $this->applySourceTypeFilter($query);
        $this->applyItemFilter($query);
        $this->applyWarehouseFilter($query);

        return $query;
    }

    public function rows(): Collection
    {
        return $this->rows ??= $this->query()->get();
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
        $type = strtoupper(str_replace('_', ' ', (string) ($mutation->source_type ?? '')));
        $subtype = strtoupper(str_replace('_', ' ', (string) ($mutation->source_subtype ?? '')));

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
}
