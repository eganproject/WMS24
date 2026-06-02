<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ItemBarcodeTemplateExport implements FromCollection, WithHeadings
{
    public function headings(): array
    {
        return ['sku', 'barcode', 'source_name', 'note'];
    }

    public function collection(): Collection
    {
        return new Collection([
            ['SKU-001', '8991234567890', 'Supplier A', 'Barcode dus'],
            ['SKU-001', 'EXT-QR-001', 'Marketplace B', 'QR produk'],
        ]);
    }
}
