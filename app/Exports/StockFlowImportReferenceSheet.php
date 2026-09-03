<?php

namespace App\Exports;

use App\Models\Item;
use App\Models\Warehouse;
use Generator;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\DefaultValueBinder;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class StockFlowImportReferenceSheet extends DefaultValueBinder implements FromGenerator, WithCustomValueBinder, WithEvents, WithTitle
{
    public function __construct(private readonly array $definition) {}

    public function title(): string
    {
        return 'Referensi Master';
    }

    public function generator(): Generator
    {
        $itemQuery = Item::query()->orderBy('sku');
        if ($this->definition['reference_item_type'] === 'single') {
            $itemQuery->where('item_type', Item::TYPE_SINGLE);
        }
        $warehouses = Warehouse::query()->orderBy('name')->get(['code', 'name', 'type'])->values();
        yield ['REFERENSI ITEM', '', '', '', '', 'REFERENSI GUDANG'];
        yield ['SKU', 'Nama Item', 'Isi per Koli', 'Tipe', '', 'Kode Gudang', 'Nama Gudang', 'Tipe'];

        $index = 0;
        foreach ($itemQuery->cursor() as $item) {
            $warehouse = $warehouses->get($index);
            yield [
                $item->sku ?? '',
                $item->name ?? '',
                (int) ($item->koli_qty ?? 0),
                $item->item_type ?? '',
                '',
                $warehouse?->code ?? '',
                $warehouse?->name ?? '',
                $warehouse?->type ?? '',
            ];
            $index++;
        }

        for (; $index < $warehouses->count(); $index++) {
            $warehouse = $warehouses->get($index);
            yield ['', '', null, '', '', $warehouse?->code ?? '', $warehouse?->name ?? '', $warehouse?->type ?? ''];
        }
    }

    public function bindValue(Cell $cell, $value)
    {
        if (is_string($value) && preg_match('/^[=+\-@]/', $value) === 1) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $sheet = $event->sheet->getDelegate();
            $sheet->mergeCells('A1:D1');
            $sheet->mergeCells('F1:H1');
            $sheet->getStyle('A1:D1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EAF4FF');
            $sheet->getStyle('F1:H1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E8FFF3');
            $sheet->getStyle('A1:H1')->getFont()->setBold(true)->setSize(13)->getColor()->setRGB('181C32');
            $sheet->getStyle('A2:D2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('009EF7');
            $sheet->getStyle('F2:H2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('50CD89');
            $sheet->getStyle('A2:D2')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
            $sheet->getStyle('F2:H2')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
            $lastRow = max(2, $sheet->getHighestDataRow());
            $sheet->getStyle('A1:D'.$lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E4E6EF');
            $sheet->getStyle('F1:H'.$lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E4E6EF');
            $sheet->getStyle('A1:H'.$lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
            foreach (['A' => 20, 'B' => 38, 'C' => 15, 'D' => 14, 'E' => 3, 'F' => 22, 'G' => 30, 'H' => 16] as $column => $width) {
                $sheet->getColumnDimension($column)->setWidth($width);
            }
            $sheet->freezePane('A3');
            $sheet->getTabColor()->setRGB('7239EA');
            $sheet->setSelectedCell('A1');
        }];
    }
}
