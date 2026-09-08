<?php

namespace App\Support;

use App\Models\Warehouse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class StockMovementExportReport
{
    private ?Collection $rows = null;

    public function __construct(private array $filters)
    {
    }

    public function rows(): Collection
    {
        if ($this->rows !== null) {
            return $this->rows;
        }

        return $this->rows = app(StockMovementAnalysisService::class)
            ->query($this->filters)
            ->orderByRaw("CASE movement_category WHEN 'fast' THEN 1 WHEN 'medium' THEN 2 WHEN 'slow' THEN 3 WHEN 'dead_stock' THEN 4 ELSE 5 END")
            ->orderByDesc('demand_out')
            ->orderBy('sku')
            ->get();
    }

    public function attentionRows(): Collection
    {
        return $this->rows()->filter(function ($row) {
            $category = (string) $row->movement_category;
            $coverageDays = $row->stock_coverage_days !== null ? (float) $row->stock_coverage_days : null;

            return in_array($category, ['slow', 'dead_stock', 'no_stock'], true)
                || (int) $row->ending_stock <= 0
                || ($coverageDays !== null && $coverageDays <= 14);
        })->values();
    }

    public function categoryBreakdown(): Collection
    {
        $rows = $this->rows();
        $total = $rows->count();

        return collect(StockMovementAnalysisService::categories())->map(function (string $category) use ($rows, $total) {
            $categoryRows = $rows->where('movement_category', $category);

            return (object) [
                'category' => $category,
                'label' => $this->categoryLabel($category),
                'items' => $categoryRows->count(),
                'percentage' => $total > 0 ? $categoryRows->count() / $total : 0,
                'demand_out' => (int) $categoryRows->sum('demand_out'),
                'ending_stock' => (int) $categoryRows->sum('ending_stock'),
            ];
        });
    }

    public function summary(): object
    {
        $rows = $this->rows();

        return (object) [
            'total_items' => $rows->count(),
            'demand_out' => (int) $rows->sum('demand_out'),
            'ending_stock' => (int) $rows->sum('ending_stock'),
            'attention_items' => $this->attentionRows()->count(),
            'dead_stock_units' => (int) $rows->where('movement_category', 'dead_stock')->sum('ending_stock'),
            'slow_stock_units' => (int) $rows->where('movement_category', 'slow')->sum('ending_stock'),
            'demand_items_without_stock' => $rows
                ->whereIn('movement_category', ['fast', 'medium', 'slow'])
                ->filter(fn ($row) => (int) $row->ending_stock <= 0)
                ->count(),
            'low_coverage_items' => $rows
                ->filter(fn ($row) => $row->stock_coverage_days !== null && (float) $row->stock_coverage_days <= 14)
                ->count(),
        ];
    }

    public function filterSummary(): string
    {
        $warehouseIds = array_values(array_filter(array_map(
            'intval',
            (array) ($this->filters['warehouse_ids'] ?? [])
        )));
        $warehouse = $warehouseIds === []
            ? 'Seluruh Gudang'
            : Warehouse::query()->whereIn('id', $warehouseIds)->orderBy('name')->pluck('name')->implode(', ');
        $search = trim((string) ($this->filters['q'] ?? ''));
        $category = trim((string) ($this->filters['movement_category'] ?? ''));

        return sprintf(
            'Periode %s s.d. %s (%s hari) | Gudang: %s | Kategori: %s%s',
            $this->filters['date_from'],
            $this->filters['date_to'],
            $this->periodDays(),
            $warehouse,
            $category !== '' ? $this->categoryLabel($category) : 'Semua Kategori',
            $search !== '' ? ' | Pencarian: '.$search : ''
        );
    }

    public function periodDays(): int
    {
        return (int) Carbon::parse($this->filters['date_from'])
            ->diffInDays(Carbon::parse($this->filters['date_to'])) + 1;
    }

    public function categoryLabel(string $category): string
    {
        return match ($category) {
            'fast' => 'Fast Moving',
            'medium' => 'Medium Moving',
            'slow' => 'Slow Moving',
            'dead_stock' => 'Dead Stock',
            'no_stock' => 'Tanpa Stok',
            default => $category,
        };
    }

    public function recommendation(object $row): string
    {
        $category = (string) $row->movement_category;
        $endingStock = (int) $row->ending_stock;
        $coverageDays = $row->stock_coverage_days !== null ? (float) $row->stock_coverage_days : null;

        if (in_array($category, ['fast', 'medium'], true) && $endingStock <= 0) {
            return 'Restock segera; item masih memiliki permintaan tetapi saldo telah habis.';
        }
        if (in_array($category, ['fast', 'medium'], true) && $coverageDays !== null && $coverageDays <= 14) {
            return 'Prioritaskan restock; estimasi ketahanan stok maksimal 14 hari.';
        }
        if ($category === 'dead_stock') {
            return 'Evaluasi promo, redistribusi, retur supplier, atau penghentian pembelian.';
        }
        if ($category === 'slow') {
            return 'Review jumlah pembelian dan pertimbangkan promosi untuk mempercepat perputaran.';
        }
        if ($category === 'no_stock') {
            return 'Validasi status item dan tentukan apakah perlu replenishment atau dinonaktifkan.';
        }

        return 'Pertahankan ketersediaan dan pantau ketahanan stok secara berkala.';
    }

    public function priority(object $row): string
    {
        $category = (string) $row->movement_category;
        $coverageDays = $row->stock_coverage_days !== null ? (float) $row->stock_coverage_days : null;

        if (in_array($category, ['fast', 'medium'], true) && (int) $row->ending_stock <= 0) {
            return 'Kritis';
        }
        if (in_array($category, ['fast', 'medium'], true) && $coverageDays !== null && $coverageDays <= 14) {
            return 'Tinggi';
        }
        if ($category === 'dead_stock') {
            return 'Tinggi';
        }
        if (in_array($category, ['slow', 'no_stock'], true)) {
            return 'Menengah';
        }

        return 'Normal';
    }
}
