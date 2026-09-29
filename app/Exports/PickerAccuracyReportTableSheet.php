<?php

namespace App\Exports;

use App\Exports\Concerns\SanitizesSpreadsheetText;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class PickerAccuracyReportTableSheet implements FromArray, WithHeadings, WithTitle, WithEvents
{
    use SanitizesSpreadsheetText;

    /**
     * @param  array<int, int>  $textColumns  indeks kolom (0-based) berisi teks bebas yang perlu disanitasi.
     */
    public function __construct(
        private string $title,
        private array $headings,
        private array $rows,
        private array $textColumns = [],
    ) {
    }

    public function title(): string
    {
        return $this->title;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function array(): array
    {
        return array_map(function (array $row) {
            foreach ($this->textColumns as $index) {
                $row[$index] = $this->spreadsheetText((string) ($row[$index] ?? ''));
            }

            return $row;
        }, $this->rows);
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $sheet = $event->sheet->getDelegate();
            $lastColumn = Coordinate::stringFromColumnIndex(count($this->headings));
            $lastRow = max(1, count($this->rows) + 1);
            $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
            $sheet->getStyle("A1:{$lastColumn}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1B84FF');
            $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E4E6EF');
            $sheet->freezePane('A2');
            $sheet->setAutoFilter("A1:{$lastColumn}{$lastRow}");
            for ($column = 1; $column <= count($this->headings); $column++) {
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setAutoSize(true);
            }
        }];
    }
}
