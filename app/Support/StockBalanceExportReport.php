<?php

namespace App\Support;

use Illuminate\Support\Collection;

class StockBalanceExportReport
{
    public const CONDITION_MINUS = 'Minus';
    public const CONDITION_EMPTY = 'Habis';
    public const CONDITION_AVAILABLE = 'Tersedia';

    private ?Collection $rows = null;
    private ?Collection $otherMutations = null;

    public function __construct(private array $filters) {}

    public function rows(): Collection
    {
        if ($this->rows !== null) {
            return $this->rows;
        }

        return $this->rows = app(StockBalanceReportService::class)
            ->consolidatedQuery($this->filters)
            ->orderBy('items.name')
            ->orderBy('items.sku')
            ->get()
            ->map(function ($row) {
                foreach (['opening_stock', 'stock_in', 'stock_out', 'other_net', 'ending_stock', 'main_ending_stock', 'display_ending_stock'] as $field) {
                    $row->{$field} = (int) ($row->{$field} ?? 0);
                }
                $row->change = $row->ending_stock - $row->opening_stock;
                $row->condition = $this->condition($row);
                $row->note = $this->note($row);

                return $row;
            });
    }

    /**
     * SKU yang perlu ditindaklanjuti: saldo minus, habis setelah ada penjualan,
     * atau masih ada stok tetapi sama sekali tidak bergerak selama periode.
     */
    public function attentionRows(): Collection
    {
        return $this->rows()
            ->filter(fn ($row) => $this->attentionPriority($row) !== null)
            ->sortBy([
                fn ($a, $b) => $this->attentionPriority($a) <=> $this->attentionPriority($b),
                fn ($a, $b) => $b->stock_out <=> $a->stock_out,
                fn ($a, $b) => $b->ending_stock <=> $a->ending_stock,
            ])
            ->values();
    }

    public function attentionPriority(object $row): ?int
    {
        return match (true) {
            $row->ending_stock < 0 => 1,
            $row->ending_stock === 0 && $row->stock_out > 0 => 2,
            $row->ending_stock > 0 && $this->isIdle($row) => 3,
            default => null,
        };
    }

    public function attentionLabel(object $row): string
    {
        return match ($this->attentionPriority($row)) {
            1 => 'Saldo minus',
            2 => 'Habis setelah terjual',
            3 => 'Stok tidak bergerak',
            default => '-',
        };
    }

    public function attentionAction(object $row): string
    {
        return match ($this->attentionPriority($row)) {
            1 => 'Cek mutasi dan lakukan stok opname; saldo fisik tidak mungkin minus.',
            2 => 'Prioritaskan restock / transfer agar penjualan tidak terhenti.',
            3 => 'Evaluasi promo, pindahkan ke display, atau tahan pembelian.',
            default => '',
        };
    }

    public function summary(): object
    {
        $rows = $this->rows();

        return (object) [
            'total_items' => $rows->count(),
            'opening_stock' => (int) $rows->sum('opening_stock'),
            'stock_in' => (int) $rows->sum('stock_in'),
            'stock_out' => (int) $rows->sum('stock_out'),
            'other_net' => (int) $rows->sum('other_net'),
            'ending_stock' => (int) $rows->sum('ending_stock'),
            'main_ending_stock' => (int) $rows->sum('main_ending_stock'),
            'display_ending_stock' => (int) $rows->sum('display_ending_stock'),
            'items_in' => $rows->filter(fn ($row) => $row->stock_in > 0)->count(),
            'items_out' => $rows->filter(fn ($row) => $row->stock_out > 0)->count(),
            'items_idle' => $rows->filter(fn ($row) => $this->isIdle($row))->count(),
            'attention_items' => $this->attentionRows()->count(),
        ];
    }

    /** @return Collection<int, object{label:string, items:int, units:int}> */
    public function conditionBreakdown(): Collection
    {
        $rows = $this->rows();

        return collect([self::CONDITION_AVAILABLE, self::CONDITION_EMPTY, self::CONDITION_MINUS])
            ->map(fn (string $condition) => (object) [
                'label' => $condition,
                'items' => $rows->where('condition', $condition)->count(),
                'units' => (int) $rows->where('condition', $condition)->sum('ending_stock'),
            ]);
    }

    /** @return Collection<int, object{label:string, items:int}> */
    public function changeBreakdown(): Collection
    {
        $rows = $this->rows();

        return collect([
            (object) ['label' => 'Stok naik', 'items' => $rows->filter(fn ($row) => $row->change > 0)->count()],
            (object) ['label' => 'Stok turun', 'items' => $rows->filter(fn ($row) => $row->change < 0)->count()],
            (object) ['label' => 'Stok tetap', 'items' => $rows->filter(fn ($row) => $row->change === 0)->count()],
        ]);
    }

    public function otherMutations(): Collection
    {
        return $this->otherMutations ??= app(StockBalanceReportService::class)->otherMutationBreakdown($this->filters);
    }

    public function topBy(string $field, int $limit = 10): Collection
    {
        return $this->rows()
            ->filter(fn ($row) => $row->{$field} > 0)
            ->sortByDesc($field)
            ->take($limit)
            ->values();
    }

    public function filterSummary(): string
    {
        $search = trim((string) ($this->filters['q'] ?? ''));

        return sprintf(
            'Periode %s s.d. %s | Gudang: Gudang Besar + Gudang Display%s',
            $this->filters['date_from'],
            $this->filters['date_to'],
            $search !== '' ? ' | Pencarian: '.$search : ''
        );
    }

    private function isIdle(object $row): bool
    {
        return $row->stock_in === 0 && $row->stock_out === 0 && $row->other_net === 0;
    }

    private function condition(object $row): string
    {
        return match (true) {
            $row->ending_stock < 0 => self::CONDITION_MINUS,
            $row->ending_stock === 0 => self::CONDITION_EMPTY,
            default => self::CONDITION_AVAILABLE,
        };
    }

    private function note(object $row): string
    {
        if ($this->isIdle($row)) {
            return 'Tidak ada pergerakan';
        }

        $notes = [];
        if ($row->stock_out > 0 && $row->ending_stock <= 0) {
            $notes[] = 'Habis setelah terjual';
        }
        if ($row->other_net !== 0) {
            $notes[] = 'Ada mutasi lain';
        }

        return $notes === [] ? '-' : implode('; ', $notes);
    }
}
