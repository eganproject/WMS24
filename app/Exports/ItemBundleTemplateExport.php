<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ItemBundleTemplateExport implements FromCollection, WithHeadings
{
    public function headings(): array
    {
        return ['bundle_sku', 'bundle_name', 'component_sku', 'required_qty'];
    }

    public function collection(): Collection
    {
        return new Collection([
            ['BUNDLE-001', 'Bundle Paket 001', 'ITEM-A', 2],
            ['BUNDLE-001', 'Bundle Paket 001', 'ITEM-B', 1],
            ['BUNDLE-002', 'Bundle Paket 002', 'ITEM-C', 3],
            ['BUNDLE-002', 'Bundle Paket 002', 'ITEM-D', 1],
        ]);
    }
}
