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
            'parent_category',
            'category',
            'stock',
            'safety_stock',
            'koli_qty',
            'address',
            'lane',
            'rack',
            'column',
            'row',
            'description',
        ];
    }
}
