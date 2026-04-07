<?php

namespace App\Exports;

use App\Models\Item;
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
        $query = Item::with(['stocks' => function ($q) use ($defaultId, $displayId) {
            $q->whereIn('warehouse_id', [$defaultId, $displayId]);
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
        return ['ID', 'SKU', 'Nama', 'Stok Gudang Besar', 'Stok Gudang Display', 'Total'];
    }

    public function map($row): array
    {
        $defaultId = WarehouseService::defaultWarehouseId();
        $displayId = WarehouseService::displayWarehouseId();
        $stocks = $row->stocks?->keyBy('warehouse_id') ?? collect();
        $stockMain = (int) ($stocks->get($defaultId)?->stock ?? 0);
        $stockDisplay = (int) ($stocks->get($displayId)?->stock ?? 0);
        return [
            $row->id,
            $row->sku,
            $row->name,
            $stockMain,
            $stockDisplay,
            $stockMain + $stockDisplay,
        ];
    }
}
