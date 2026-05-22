<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class StockAdjustmentTemplateExport implements FromCollection, WithHeadings
{
    public function collection(): Collection
    {
        return new Collection([]);
    }

    public function headings(): array
    {
        return [
            'sku',
            'qty',
            'direction',
            'warehouse',
            'note',
            'item_note',
            'transacted_at',
        ];
    }
}
