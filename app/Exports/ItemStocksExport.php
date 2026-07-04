<?php

namespace App\Exports;

use App\Models\Item;
use App\Models\Warehouse;
use App\Support\BundleService;
use App\Support\ItemTextSearch;
use App\Support\WarehouseService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ItemStocksExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(
        private string $search = '',
        private bool $exact = false,
        private string $status = 'active',
        private string $safetyFilter = 'all'
    )
    {
    }

    public function collection(): Collection
    {
        $defaultId = WarehouseService::defaultWarehouseId();
        $displayId = WarehouseService::displayWarehouseId();
        $damagedId = WarehouseService::damagedWarehouseId();
        $query = Item::with(['stocks' => function ($q) use ($defaultId, $displayId, $damagedId) {
            $q->whereIn('warehouse_id', [$defaultId, $displayId, $damagedId]);
        }])
            ->leftJoin('item_stocks as stock_main_sort', function ($join) use ($defaultId) {
                $join->on('stock_main_sort.item_id', '=', 'items.id')
                    ->where('stock_main_sort.warehouse_id', '=', $defaultId);
            })
            ->leftJoin('item_stocks as stock_display_sort', function ($join) use ($displayId) {
                $join->on('stock_display_sort.item_id', '=', 'items.id')
                    ->where('stock_display_sort.warehouse_id', '=', $displayId);
            })
            ->select('items.*')
            ->orderBy('name');
        $search = trim($this->search);
        if ($search !== '') {
            ItemTextSearch::apply($query, $search, $this->exact);
        }
        if ($this->status === Item::STATUS_INACTIVE) {
            $query->where('status', Item::STATUS_INACTIVE);
        } elseif ($this->status !== 'all') {
            $query->where('status', Item::STATUS_ACTIVE);
        }
        $this->applySafetyFilter($query);
        return $query->get();
    }

    public function headings(): array
    {
        $defaultLabel = Warehouse::where('id', WarehouseService::defaultWarehouseId())->value('name') ?? 'Gudang Besar';
        $displayLabel = Warehouse::where('id', WarehouseService::displayWarehouseId())->value('name') ?? 'Gudang Display';
        $damagedLabel = Warehouse::where('id', WarehouseService::damagedWarehouseId())->value('name') ?? 'Gudang Rusak';

        return [
            'ID',
            'SKU',
            'Nama',
            'Tipe',
            'Status',
            "Stok {$defaultLabel}",
            "Koli {$defaultLabel}",
            "Sisa Pcs {$defaultLabel}",
            'Isi/Koli',
            "Safety {$defaultLabel}",
            "Stok {$displayLabel}",
            "Safety {$displayLabel}",
            "Stok {$damagedLabel}",
            'Total Stok Baik',
            'Total Fisik',
        ];
    }

    public function map($row): array
    {
        $defaultId = WarehouseService::defaultWarehouseId();
        $displayId = WarehouseService::displayWarehouseId();
        $damagedId = WarehouseService::damagedWarehouseId();
        $stocks = $row->stocks?->keyBy('warehouse_id') ?? collect();
        $isBundle = $row->isBundle();
        $stockMain = $isBundle ? BundleService::virtualAvailableQty($row, $defaultId) : (int) ($stocks->get($defaultId)?->stock ?? 0);
        $stockDisplay = $isBundle ? BundleService::virtualAvailableQty($row, $displayId) : (int) ($stocks->get($displayId)?->stock ?? 0);
        $stockDamaged = $isBundle ? 0 : (int) ($stocks->get($damagedId)?->stock ?? 0);
        $baseSafety = (int) ($row->safety_stock ?? 0);
        $safetyMainRaw = $stocks->get($defaultId)?->safety_stock;
        $safetyDisplayRaw = $stocks->get($displayId)?->safety_stock;
        $safetyMain = $safetyMainRaw !== null ? (int) $safetyMainRaw : $baseSafety;
        $safetyDisplay = $safetyDisplayRaw !== null ? (int) $safetyDisplayRaw : $baseSafety;
        $stockGoodTotal = $stockMain + $stockDisplay;
        $koliQty = $isBundle ? 0 : max(0, (int) ($row->koli_qty ?? 0));
        $mainKoli = (!$isBundle && $koliQty > 0) ? intdiv((int) $stockMain, $koliQty) : null;
        $mainKoliRemainder = (!$isBundle && $koliQty > 0) ? ((int) $stockMain % $koliQty) : null;

        return [
            $row->id,
            $row->sku,
            $row->name,
            $isBundle ? 'bundle (virtual)' : 'single',
            ($row->status ?: Item::STATUS_ACTIVE) === Item::STATUS_ACTIVE ? 'Aktif' : 'Nonaktif',
            $stockMain,
            $mainKoli ?? '-',
            $mainKoliRemainder ?? '-',
            $koliQty > 0 ? $koliQty : '-',
            $safetyMain,
            $stockDisplay,
            $safetyDisplay,
            $stockDamaged,
            $stockGoodTotal,
            $isBundle ? $stockGoodTotal : ($stockGoodTotal + $stockDamaged),
        ];
    }

    private function applySafetyFilter($query): void
    {
        if ($this->safetyFilter === '' || $this->safetyFilter === 'all') {
            return;
        }

        $mainStock = 'COALESCE(stock_main_sort.stock, 0)';
        $mainSafety = 'COALESCE(stock_main_sort.safety_stock, items.safety_stock, 0)';
        $mainMonitored = 'COALESCE(stock_main_sort.is_stock_monitored, 1)';
        $displayStock = 'COALESCE(stock_display_sort.stock, 0)';
        $displaySafety = 'COALESCE(stock_display_sort.safety_stock, items.safety_stock, 0)';
        $displayMonitored = 'COALESCE(stock_display_sort.is_stock_monitored, 1)';

        $query->where(function ($q) {
            $q->whereNull('items.item_type')
                ->orWhere('items.item_type', '!=', Item::TYPE_BUNDLE);
        });

        match ($this->safetyFilter) {
            'below_main' => $query
                ->whereRaw("{$mainMonitored} = 1")
                ->whereRaw("{$mainSafety} > 0")
                ->whereRaw("{$mainStock} < {$mainSafety}"),
            'below_display' => $query
                ->whereRaw("{$displayMonitored} = 1")
                ->whereRaw("{$displaySafety} > 0")
                ->whereRaw("{$displayStock} < {$displaySafety}"),
            'below_any' => $query->where(function ($q) use ($mainStock, $mainSafety, $mainMonitored, $displayStock, $displaySafety, $displayMonitored) {
                $q->where(function ($sub) use ($mainStock, $mainSafety, $mainMonitored) {
                    $sub->whereRaw("{$mainMonitored} = 1")
                        ->whereRaw("{$mainSafety} > 0")
                        ->whereRaw("{$mainStock} < {$mainSafety}");
                })->orWhere(function ($sub) use ($displayStock, $displaySafety, $displayMonitored) {
                    $sub->whereRaw("{$displayMonitored} = 1")
                        ->whereRaw("{$displaySafety} > 0")
                        ->whereRaw("{$displayStock} < {$displaySafety}");
                });
            }),
            'normal' => $query
                ->whereRaw("({$mainMonitored} = 0 OR {$mainSafety} <= 0 OR {$mainStock} >= {$mainSafety})")
                ->whereRaw("({$displayMonitored} = 0 OR {$displaySafety} <= 0 OR {$displayStock} >= {$displaySafety})"),
            'unmonitored' => $query->where(function ($q) use ($mainMonitored, $displayMonitored) {
                $q->whereRaw("{$mainMonitored} = 0")
                    ->orWhereRaw("{$displayMonitored} = 0");
            }),
            default => null,
        };
    }
}
