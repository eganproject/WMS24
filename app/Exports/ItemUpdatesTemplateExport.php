<?php

namespace App\Exports;

use App\Models\Item;
use App\Support\ItemUpdateFields;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ItemUpdatesTemplateExport implements FromCollection, WithHeadings, WithCustomStartCell, ShouldAutoSize, WithStyles, WithEvents, WithTitle
{
    private array $fields;

    public function __construct(array $fields)
    {
        $this->fields = ItemUpdateFields::normalize($fields);
    }

    public function fields(): array
    {
        return $this->fields;
    }

    public function title(): string
    {
        return 'Update Items';
    }

    public function startCell(): string
    {
        return 'A4';
    }

    public function headings(): array
    {
        return ItemUpdateFields::headings($this->fields);
    }

    public function collection(): Collection
    {
        return Item::query()
            ->with(['category.parent', 'location.area'])
            ->orderBy('sku')
            ->get()
            ->map(function (Item $item) {
                $row = [$item->sku];
                foreach ($this->fields as $field) {
                    $values = match ($field) {
                        'name' => [$item->name],
                        'status' => [$item->status ?: Item::STATUS_ACTIVE],
                        'category' => [$item->category?->parent?->name ?? '', $item->category?->name ?? ''],
                        'address' => [$item->resolvedAddress()],
                        'description' => [$item->description ?? ''],
                        'safety_stock' => [(int) ($item->safety_stock ?? 0)],
                        default => [],
                    };
                    $row = array_merge($row, $values);
                }

                return $row;
            });
    }

    public function styles(Worksheet $sheet): array
    {
        $lastColumn = Coordinate::stringFromColumnIndex(count($this->headings()));
        $sheet->mergeCells("A1:{$lastColumn}1");
        $sheet->mergeCells("A2:{$lastColumn}2");
        $sheet->mergeCells("A3:{$lastColumn}3");
        $sheet->setCellValue('A1', 'Template Update Data Items');
        $sheet->setCellValue('A2', 'SKU hanya sebagai kunci pencarian dan tidak akan diubah. Hapus baris yang tidak ingin diproses.');
        $sheet->setCellValue('A3', 'Field aktif: '.implode(', ', ItemUpdateFields::labels($this->fields)));

        return [
            1 => ['font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '181C32']]],
            2 => ['font' => ['color' => ['rgb' => '7E8299']]],
            3 => ['font' => ['bold' => true, 'color' => ['rgb' => '3F4254']]],
            4 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1B84FF']],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $headings = $this->headings();
                $lastColumn = Coordinate::stringFromColumnIndex(count($headings));
                $lastRow = max(5, $sheet->getHighestDataRow());
                $range = "A4:{$lastColumn}{$lastRow}";

                $sheet->freezePane('A5');
                $sheet->setAutoFilter($range);
                $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E4E6EF');
                $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle("A4:{$lastColumn}4")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getColumnDimension('A')->setWidth(24);

                $statusIndex = array_search('status', $headings, true);
                if ($statusIndex !== false) {
                    $column = Coordinate::stringFromColumnIndex($statusIndex + 1);
                    $validation = $sheet->getCell("{$column}5")->getDataValidation();
                    $validation->setType(DataValidation::TYPE_LIST);
                    $validation->setErrorStyle(DataValidation::STYLE_STOP);
                    $validation->setAllowBlank(false);
                    $validation->setShowErrorMessage(true);
                    $validation->setShowDropDown(true);
                    $validation->setErrorTitle('Status tidak valid');
                    $validation->setError('Pilih active atau inactive.');
                    $validation->setFormula1('"active,inactive"');
                    for ($row = 5; $row <= max($lastRow, 500); $row++) {
                        $sheet->getCell("{$column}{$row}")->setDataValidation(clone $validation);
                    }
                }
            },
        ];
    }
}
