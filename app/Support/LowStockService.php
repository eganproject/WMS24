<?php

namespace App\Support;

use App\Models\Item;
use App\Models\LowStockSnapshot;
use App\Models\Warehouse;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LowStockService
{
    public function query(string|int|null $warehouseFilter = null): Builder
    {
        $isAllWarehouses = $warehouseFilter === null || $warehouseFilter === '' || $warehouseFilter === 'all';
        $warehouseId = !$isAllWarehouses && is_numeric($warehouseFilter)
            ? (int) $warehouseFilter
            : WarehouseService::defaultWarehouseId();

        $allowedWarehouseIds = $this->safetyReportWarehouses()->pluck('id');
        if (!$isAllWarehouses && !$allowedWarehouseIds->contains($warehouseId)) {
            $warehouseId = WarehouseService::defaultWarehouseId();
        }

        $safetyExpr = $this->safetyExpr();
        $stockExpr = $this->stockExpr();

        return DB::table('items as i')
            ->join('warehouses as w', function ($join) use ($warehouseId, $isAllWarehouses) {
                $join->where(function ($query) {
                    $query->whereNull('w.type')
                        ->orWhere('w.type', '!=', 'damaged');
                });

                if (!$isAllWarehouses) {
                    $join->where('w.id', '=', $warehouseId);
                }
            })
            ->leftJoin('item_stocks as s', function ($join) {
                $join->on('s.item_id', '=', 'i.id')
                    ->on('s.warehouse_id', '=', 'w.id');
            })
            ->leftJoin('categories as c', 'c.id', '=', 'i.category_id')
            ->where(function ($query) {
                $query->whereNull('i.item_type')
                    ->orWhere('i.item_type', '!=', Item::TYPE_BUNDLE);
            })
            ->where('i.status', Item::STATUS_ACTIVE)
            ->whereRaw('COALESCE(s.is_stock_monitored, 1) = 1')
            ->whereRaw("{$safetyExpr} > 0")
            ->whereRaw("{$stockExpr} < {$safetyExpr}");
    }

    public function rows(string|int|null $warehouseFilter = null, array $filters = []): Collection
    {
        $query = $this->query($warehouseFilter);
        $this->applyFilters($query, $filters);

        $safetyExpr = $this->safetyExpr();
        $stockExpr = $this->stockExpr();

        return $query->select([
            'i.id',
            'i.sku',
            'i.name',
            'i.address',
            'w.id as warehouse_id',
            'w.name as warehouse',
            DB::raw("{$safetyExpr} as safety_stock"),
            DB::raw("{$stockExpr} as stock"),
            DB::raw("CASE WHEN s.safety_stock IS NOT NULL THEN 'Per gudang' ELSE 'Default item' END as safety_source"),
            DB::raw("CASE WHEN i.category_id = 0 THEN 'Tanpa Kategori' ELSE COALESCE(c.name, '-') END as category"),
        ])
            ->orderByRaw("({$safetyExpr} - {$stockExpr}) desc")
            ->orderBy('w.name')
            ->orderBy('i.sku')
            ->get()
            ->map(fn ($row) => $this->mapRow($row));
    }

    public function summary(Builder $query): array
    {
        $stockExpr = $this->stockExpr();
        $safetyExpr = $this->safetyExpr();

        return [
            'total_low' => (clone $query)->count(),
            'out_of_stock' => (clone $query)->whereRaw("{$stockExpr} <= 0")->count(),
            'total_gap' => (int) ((clone $query)
                ->selectRaw("COALESCE(SUM({$safetyExpr} - {$stockExpr}), 0) as gap")
                ->value('gap') ?? 0),
        ];
    }

    public function createSnapshot(string|int|null $warehouseFilter = null, string $source = 'manual', ?int $createdBy = null): LowStockSnapshot
    {
        $isAllWarehouses = $warehouseFilter === null || $warehouseFilter === '' || $warehouseFilter === 'all';
        $warehouseId = null;
        if (!$isAllWarehouses) {
            $warehouseId = is_numeric($warehouseFilter) ? (int) $warehouseFilter : WarehouseService::defaultWarehouseId();
            if (!$this->safetyReportWarehouses()->whereKey($warehouseId)->exists()) {
                $warehouseId = WarehouseService::defaultWarehouseId();
            }
        }

        $normalizedFilter = $isAllWarehouses ? 'all' : $warehouseId;
        $rows = $this->rows($normalizedFilter);
        $warehouse = $warehouseId ? Warehouse::query()->find($warehouseId) : null;

        return DB::transaction(function () use ($rows, $isAllWarehouses, $warehouseId, $warehouse, $source, $createdBy) {
            $snapshot = LowStockSnapshot::create([
                'snapshot_at' => now(),
                'scope' => $isAllWarehouses ? 'all' : 'warehouse',
                'warehouse_id' => $warehouseId,
                'warehouse_name' => $warehouse?->name,
                'total_low' => $rows->count(),
                'total_out_of_stock' => $rows->where('status', 'Out of Stock')->count(),
                'total_gap' => $rows->sum('gap'),
                'source' => $source,
                'created_by' => $createdBy,
            ]);

            foreach ($rows->chunk(500) as $chunk) {
                $snapshot->items()->createMany($chunk->map(fn ($row) => [
                    'item_id' => $row['id'],
                    'warehouse_id' => $row['warehouse_id'],
                    'sku' => $row['sku'],
                    'name' => $row['name'],
                    'warehouse' => $row['warehouse'],
                    'category' => $row['category'],
                    'address' => $row['address'],
                    'stock' => $row['stock'],
                    'safety_stock' => $row['safety_stock'],
                    'gap' => $row['gap'],
                    'status' => $row['status'],
                    'safety_source' => $row['safety_source'],
                ])->all());
            }

            return $snapshot;
        });
    }

    public function applyFilters(Builder $query, array $filters): void
    {
        $catFilter = $filters['category_id'] ?? null;
        if ($catFilter !== null && $catFilter !== '') {
            if ((int) $catFilter === 0) {
                $query->where('i.category_id', 0);
            } else {
                $query->where('i.category_id', (int) $catFilter);
            }
        }

        $stockExpr = $this->stockExpr();
        $statusFilter = $filters['status'] ?? null;
        if ($statusFilter === 'out') {
            $query->whereRaw("{$stockExpr} <= 0");
        } elseif ($statusFilter === 'low') {
            $query->whereRaw("{$stockExpr} > 0");
        }

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $exact = (bool) ($filters['exact'] ?? false);
            $query->where(function ($q) use ($search, $exact) {
                $this->applyTextSearch($q, 'i.sku', $search, $exact);
                $this->applyTextSearch($q, 'i.name', $search, $exact, 'or');
                $this->applyTextSearch($q, 'i.address', $search, $exact, 'or');
                $this->applyTextSearch($q, 'i.description', $search, $exact, 'or');
            });
        }
    }

    public function safetyReportWarehouses()
    {
        return Warehouse::query()->where(function ($query) {
            $query->whereNull('type')
                ->orWhere('type', '!=', 'damaged');
        });
    }

    public function safetyExpr(): string
    {
        return 'COALESCE(s.safety_stock, i.safety_stock, 0)';
    }

    public function stockExpr(): string
    {
        return 'COALESCE(s.stock, 0)';
    }

    public function mapRow(object $row): array
    {
        $stock = (int) ($row->stock ?? 0);
        $safety = (int) ($row->safety_stock ?? 0);

        return [
            'id' => (int) $row->id,
            'sku' => $row->sku ?? '-',
            'name' => $row->name ?? '-',
            'warehouse_id' => (int) $row->warehouse_id,
            'warehouse' => $row->warehouse ?? '-',
            'category' => $row->category ?? '-',
            'address' => $row->address ?? '-',
            'stock' => $stock,
            'safety_stock' => $safety,
            'safety_source' => $row->safety_source ?? 'Default item',
            'gap' => max(0, $safety - $stock),
            'status' => $stock <= 0 ? 'Out of Stock' : 'Low Stock',
        ];
    }

    private function applyTextSearch($query, string $column, string $term, bool $exact, string $boolean = 'and'): void
    {
        $method = $boolean === 'or' ? 'orWhere' : 'where';
        if ($exact) {
            $query->{$method}(DB::raw('LOWER('.$column.')'), mb_strtolower($term));
            return;
        }

        $query->{$method}($column, 'like', '%'.$term.'%');
    }
}
