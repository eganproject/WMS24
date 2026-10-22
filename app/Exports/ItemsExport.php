<?php

namespace App\Exports;

use App\Models\Item;
use App\Support\WarehouseService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Export data items dari halaman master items.
 * Kolom sama dengan export stok item, ditambah alamat gudang besar (GB)
 * dan gudang kecil/display (GK) di kolom B dan C.
 */
class ItemsExport extends ItemStocksExport
{
    public function __construct(private Builder $query)
    {
    }

    public function collection(): Collection
    {
        $warehouseIds = [
            WarehouseService::defaultWarehouseId(),
            WarehouseService::displayWarehouseId(),
            WarehouseService::damagedWarehouseId(),
        ];

        return (clone $this->query)
            ->with(['stocks' => fn ($q) => $q->whereIn('warehouse_id', $warehouseIds)])
            ->get();
    }

    public function headings(): array
    {
        $headings = parent::headings();
        array_splice($headings, 1, 0, ['ADDRESS GB', 'ADDRESS GK']);

        return $headings;
    }

    /**
     * @param Item $row
     */
    public function map($row): array
    {
        $values = parent::map($row);
        array_splice($values, 1, 0, [$row->mainAddressLabel(), $row->displayAddressLabel()]);

        return $values;
    }
}
