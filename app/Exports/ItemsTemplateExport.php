<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ItemsTemplateExport implements FromCollection, WithHeadings
{
    public function collection(): Collection
    {
        return new Collection([]);
    }

    public function headings(): array
    {
        return [
            'sku',
            'name',
            'item_type',
            'status',
            'parent_category',
            'category',
            'stock_gudang_besar',
            'stock_gudang_display',
            'stock',
            'safety_stock_gudang_besar',
            'safety_stock_gudang_display',
            'safety_stock',
            'koli_qty',
            'external_barcodes',
            'area',
            'rack',
            'column',
            'row',
            'description',
        ];
    }
}
