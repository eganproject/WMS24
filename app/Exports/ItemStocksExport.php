<?php

namespace App\Exports;

use App\Models\Item;
use App\Models\Warehouse;
use App\Support\BundleService;
use App\Support\WarehouseService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ItemStocksExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(private string $search = '')
    {
    }

    public function collection(): Collection
    {
        $defaultId = WarehouseService::defaultWarehouseId();
        $displayId = WarehouseService::displayWarehouseId();
        $damagedId = WarehouseService::damagedWarehouseId();
        $query = Item::with(['stocks' => function ($q) use ($defaultId, $displayId, $damagedId) {
            $q->whereIn('warehouse_id', [$defaultId, $displayId, $damagedId]);
        }])->orderBy('name');
        $search = trim($this->search);
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('sku', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }
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
}
