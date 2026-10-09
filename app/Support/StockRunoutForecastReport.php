<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Item;
use App\Models\StockMutation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sumber data tunggal Forecast Ketahanan Stok, dipakai halaman dan export Excel agar angkanya selalu sama.
 *
 * Permintaan harian hanya dihitung dari barang keluar untuk pesanan (QC resi marketplace dan outbound manual).
 * Transfer, penyesuaian, dan barang rusak sengaja tidak dihitung sebagai permintaan.
 */
class StockRunoutForecastReport
{
    /** Item dengan sisa ketahanan stok sampai batas ini dianggap kritis. */
    public const CRITICAL_DAYS = 7;

    public const STATUS_EMPTY = 'empty';
    public const STATUS_CRITICAL = 'critical';
    public const STATUS_WARNING = 'warning';

    public const STATUS_LABELS = [
        self::STATUS_EMPTY => 'Stok Habis',
        self::STATUS_CRITICAL => 'Kritis',
        self::STATUS_WARNING => 'Perlu Restock',
    ];

    public const SORT_LABELS = [
        'runout' => 'Estimasi Habis',
        'restock_need' => 'Perlu Restock',
        'daily_average' => 'Rata-rata Penjualan / Hari',
        'stock' => 'Stok Gabungan',
        'forecast_demand' => 'Kebutuhan Periode',
        'forecast_stock' => 'Sisa Proyeksi',
        'sku' => 'SKU',
    ];

    public readonly int $historyDays;
    public readonly int $forecastDays;
    public readonly string $search;
    public readonly ?int $categoryId;
    public readonly ?string $status;
    public readonly string $sort;
    public readonly string $direction;
    public readonly Carbon $periodStart;
    public readonly Carbon $periodEnd;

    private string $stockExpr = 'COALESCE(stock_rows.stock, 0)';
    private string $outboundExpr = 'COALESCE(outbound_usage.total_outbound, 0)';
    private string $averageExpr;
    private string $demandExpr;
    private string $forecastExpr;
    private string $runoutDaysExpr;
    private string $restockExpr;

    public function __construct(array $filters)
    {
        $this->historyDays = max(1, min(365, (int) ($filters['history_days'] ?? 30)));
        $this->forecastDays = max(1, min(365, (int) ($filters['forecast_days'] ?? 14)));
        $this->search = trim((string) ($filters['q'] ?? ''));
        $category = $filters['category_id'] ?? null;
        $this->categoryId = $category === null || $category === '' ? null : max(0, (int) $category);
        $this->status = array_key_exists((string) ($filters['status'] ?? ''), self::STATUS_LABELS) ? (string) $filters['status'] : null;
        $this->sort = array_key_exists((string) ($filters['sort'] ?? ''), self::SORT_LABELS) ? (string) $filters['sort'] : 'runout';
        $this->direction = strtolower((string) ($filters['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

        $this->periodEnd = today()->endOfDay();
        $this->periodStart = today()->subDays($this->historyDays - 1)->startOfDay();

        // Perkalian dilakukan sebelum pembagian agar pembulatan desimal MySQL tidak menggeser hasil di batas bilangan bulat.
        $this->averageExpr = "({$this->outboundExpr} / {$this->historyDays})";
        $this->demandExpr = "({$this->outboundExpr} * {$this->forecastDays} / {$this->historyDays})";
        $this->forecastExpr = "({$this->stockExpr} - {$this->demandExpr})";
        // Aman dibagi karena query dasar hanya memuat item dengan penjualan > 0.
        $this->runoutDaysExpr = "({$this->stockExpr} * {$this->historyDays} / {$this->outboundExpr})";
        $this->restockExpr = "CEILING({$this->demandExpr} - {$this->stockExpr})";
    }

    public static function fromRequest(Request $request): self
    {
        return new self($request->only(['history_days', 'forecast_days', 'q', 'category_id', 'status', 'sort', 'dir']));
    }

    public function count(): int
    {
        return $this->query()->count('items.id');
    }

    /** Ringkasan untuk item yang lolos semua filter (termasuk filter status). */
    public function summary(): array
    {
        $totals = $this->query()->toBase()->selectRaw(implode(', ', [
            'COUNT(items.id) as total_items',
            "COALESCE(SUM({$this->restockExpr}), 0) as total_restock_need",
            "COALESCE(SUM({$this->outboundExpr}), 0) as total_outbound",
            "COALESCE(SUM(CASE WHEN {$this->stockExpr} > 0 THEN {$this->stockExpr} ELSE 0 END), 0) as total_stock",
            "MIN({$this->runoutDaysExpr}) as nearest_runout_days",
        ]))->first();

        $nearest = isset($totals->nearest_runout_days) ? max(0, round((float) $totals->nearest_runout_days, 1)) : null;

        return [
            'total_items' => (int) ($totals->total_items ?? 0),
            'total_restock_need' => (int) ($totals->total_restock_need ?? 0),
            'total_stock' => (int) ($totals->total_stock ?? 0),
            'total_outbound' => (int) ($totals->total_outbound ?? 0),
            'total_daily_average' => round(((float) ($totals->total_outbound ?? 0)) / $this->historyDays, 2),
            'nearest_runout_days' => $nearest,
            'nearest_runout_date' => $nearest === null ? null : $this->runoutDate($nearest),
        ];
    }

    /** Jumlah SKU dan unit restock per status, tanpa filter status agar bisa dipakai sebagai pilihan filter. */
    public function statusBreakdown(): array
    {
        $statusExpr = $this->statusExpr();
        $rows = $this->query(false)->toBase()
            ->selectRaw("{$statusExpr} as status, COUNT(items.id) as items, COALESCE(SUM({$this->restockExpr}), 0) as restock_need")
            ->groupByRaw($statusExpr)
            ->get()
            ->keyBy('status');

        $result = [];
        foreach (self::STATUS_LABELS as $key => $label) {
            $result[$key] = [
                'label' => $label,
                'items' => (int) ($rows[$key]->items ?? 0),
                'restock_need' => (int) ($rows[$key]->restock_need ?? 0),
            ];
        }

        return $result;
    }

    public function categoryBreakdown(): Collection
    {
        return $this->query()->toBase()
            ->selectRaw(implode(', ', [
                "COALESCE(categories.name, 'Tanpa Kategori') as category",
                'COUNT(items.id) as items',
                "SUM(CASE WHEN {$this->stockExpr} <= 0 OR {$this->runoutDaysExpr} <= ".self::CRITICAL_DAYS.' THEN 1 ELSE 0 END) as urgent_items',
                "COALESCE(SUM({$this->outboundExpr}), 0) as total_outbound",
                "COALESCE(SUM({$this->restockExpr}), 0) as restock_need",
            ]))
            ->groupByRaw("COALESCE(categories.name, 'Tanpa Kategori')")
            ->orderByDesc('restock_need')
            ->get()
            ->map(fn ($row) => (object) [
                'category' => $row->category,
                'items' => (int) $row->items,
                'urgent_items' => (int) $row->urgent_items,
                'daily_average' => round(((float) $row->total_outbound) / $this->historyDays, 2),
                'restock_need' => (int) $row->restock_need,
            ]);
    }

    /** Baris detail sesuai urutan yang dipilih; tanpa offset/limit mengambil semua baris (untuk export). */
    public function rows(?int $offset = null, ?int $limit = null, ?string $sort = null, ?string $direction = null): Collection
    {
        $sortExpr = $this->sortExpr($sort ?? $this->sort);

        return $this->query()
            ->select([
                'items.sku', 'items.name', 'categories.name as category_name',
                DB::raw("{$this->stockExpr} as stock"),
                DB::raw("{$this->outboundExpr} as total_outbound"),
                DB::raw('COALESCE(outbound_usage.qc_outbound, 0) as qc_outbound'),
                DB::raw('COALESCE(outbound_usage.manual_outbound, 0) as manual_outbound'),
            ])
            ->orderByRaw("{$sortExpr} ".($direction ?? $this->direction))
            ->orderBy('items.sku')
            ->when($offset !== null, fn ($query) => $query->offset($offset))
            ->when($limit !== null, fn ($query) => $query->limit($limit))
            ->get()
            ->map(fn ($item) => $this->mapRow($item))
            ->values();
    }

    public function statusLabel(?string $status = null): string
    {
        return self::STATUS_LABELS[$status ?? $this->status] ?? 'Semua status';
    }

    public function categoryLabel(): string
    {
        if ($this->categoryId === null) {
            return 'Semua kategori';
        }
        if ($this->categoryId === 0) {
            return 'Tanpa kategori';
        }

        return Category::whereKey($this->categoryId)->value('name') ?? 'Kategori tidak ditemukan';
    }

    public function filterSummary(): string
    {
        $parts = [
            sprintf('Histori penjualan: %s s.d. %s (%d hari)', $this->periodStart->format('d/m/Y'), $this->periodEnd->format('d/m/Y'), $this->historyDays),
            sprintf('Forecast: %d hari ke depan', $this->forecastDays),
            'Kategori: '.$this->categoryLabel(),
            'Status: '.$this->statusLabel(),
        ];
        if ($this->search !== '') {
            $parts[] = 'Pencarian: "'.$this->search.'"';
        }

        return implode(' | ', $parts);
    }

    private function query(bool $withStatus = true): Builder
    {
        $outboundQuery = StockMutation::query()
            ->select(
                'item_id',
                DB::raw('SUM(qty) as total_outbound'),
                DB::raw("SUM(CASE WHEN source_type = 'qc_shipment' THEN qty ELSE 0 END) as qc_outbound"),
                DB::raw("SUM(CASE WHEN source_type = 'outbound' THEN qty ELSE 0 END) as manual_outbound"),
            )
            ->where('direction', 'out')
            ->where(function ($query) {
                $query->where('source_type', 'qc_shipment')
                    ->orWhere(fn ($manualQuery) => $manualQuery->where('source_type', 'outbound')->where('source_subtype', 'manual'));
            })
            ->whereBetween('occurred_at', [$this->periodStart, $this->periodEnd]);

        // Database lama belum memiliki kolom void; pada database baru mutasi yang dibatalkan tidak boleh dihitung.
        if (Schema::hasColumn('stock_mutations', 'is_void')) {
            $outboundQuery->where('is_void', false);
        }
        $outboundQuery->groupBy('item_id');

        // Stok gabungan gudang besar + gudang kecil; gudang barang rusak tidak dihitung.
        $stockQuery = DB::table('item_stocks as stock')
            ->join('warehouses as warehouse', 'warehouse.id', '=', 'stock.warehouse_id')
            ->where(fn ($query) => $query->whereNull('warehouse.type')->orWhere('warehouse.type', '!=', 'damaged'))
            ->select('stock.item_id', DB::raw('SUM(stock.stock) as stock'))
            ->groupBy('stock.item_id');

        return Item::query()
            ->leftJoinSub($stockQuery, 'stock_rows', fn ($join) => $join->on('stock_rows.item_id', '=', 'items.id'))
            ->leftJoinSub($outboundQuery, 'outbound_usage', fn ($join) => $join->on('outbound_usage.item_id', '=', 'items.id'))
            ->leftJoin('categories', 'categories.id', '=', 'items.category_id')
            ->where('items.status', Item::STATUS_ACTIVE)
            ->where(fn ($query) => $query->whereNull('item_type')->orWhere('item_type', '!=', Item::TYPE_BUNDLE))
            ->when($this->categoryId !== null, function ($query) {
                if ($this->categoryId === 0) {
                    $query->where(fn ($categoryQuery) => $categoryQuery->whereNull('items.category_id')->orWhere('items.category_id', 0));

                    return;
                }
                $query->where('items.category_id', $this->categoryId);
            })
            ->when($this->search !== '', function ($query) {
                $query->where(fn ($searchQuery) => $searchQuery
                    ->where('items.sku', 'like', "%{$this->search}%")
                    ->orWhere('items.name', 'like', "%{$this->search}%"));
            })
            // Hanya item yang terjual dan diproyeksikan habis dalam periode forecast.
            ->whereRaw("{$this->averageExpr} > 0")
            ->whereRaw("{$this->forecastExpr} < 0")
            ->when($withStatus && $this->status !== null, fn ($query) => $query->whereRaw("{$this->statusExpr()} = ?", [$this->status]));
    }

    private function statusExpr(): string
    {
        return sprintf(
            "(CASE WHEN %s <= 0 THEN '%s' WHEN %s <= %d THEN '%s' ELSE '%s' END)",
            $this->stockExpr, self::STATUS_EMPTY,
            $this->runoutDaysExpr, self::CRITICAL_DAYS, self::STATUS_CRITICAL,
            self::STATUS_WARNING
        );
    }

    private function sortExpr(string $sort): string
    {
        return match ($sort) {
            'restock_need' => $this->restockExpr,
            'daily_average' => $this->averageExpr,
            'stock' => $this->stockExpr,
            'forecast_demand' => $this->demandExpr,
            'forecast_stock' => $this->forecastExpr,
            'sku' => 'items.sku',
            default => $this->runoutDaysExpr,
        };
    }

    private function mapRow($item): array
    {
        $stock = (int) $item->stock;
        $totalOutbound = (int) $item->total_outbound;
        $average = $totalOutbound / $this->historyDays;
        $demand = $totalOutbound * $this->forecastDays / $this->historyDays;
        $runoutDays = $stock * $this->historyDays / $totalOutbound;
        $daysUntilRunout = max(0, round($runoutDays, 1));
        $status = match (true) {
            $stock <= 0 => self::STATUS_EMPTY,
            $runoutDays <= self::CRITICAL_DAYS => self::STATUS_CRITICAL,
            default => self::STATUS_WARNING,
        };

        return [
            'sku' => $item->sku,
            'name' => $item->name,
            'category' => $item->category_name ?? 'Tanpa Kategori',
            'status' => $status,
            'status_label' => self::STATUS_LABELS[$status],
            'stock' => $stock,
            'total_outbound' => $totalOutbound,
            'qc_outbound' => (int) $item->qc_outbound,
            'manual_outbound' => (int) $item->manual_outbound,
            'daily_average' => round($average, 2),
            'forecast_demand' => round($demand, 2),
            'forecast_stock' => round($stock - $demand, 2),
            'days_until_runout' => $daysUntilRunout,
            'runout_date' => $this->runoutDate($daysUntilRunout),
            // Dihitung dari nilai tanpa pembulatan agar sama dengan total di ringkasan.
            'restock_need' => max(0, (int) ceil(round($demand - $stock, 4))),
        ];
    }

    private function runoutDate(float $days): string
    {
        return Carbon::today()->addDays((int) ceil($days))->toDateString();
    }
}
