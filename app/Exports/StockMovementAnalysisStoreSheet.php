<?php

namespace App\Exports;

use App\Exports\Concerns\SanitizesSpreadsheetText;
use App\Support\StockMovementExportReport;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class StockMovementAnalysisStoreSheet implements FromArray, WithHeadings, WithTitle, WithEvents
{
    use SanitizesSpreadsheetText;

    private ?array $rows = null;

    public function __construct(private StockMovementExportReport $report) {}

    public function title(): string
    {
        return 'Per Toko';
    }

    public function headings(): array
    {
        return ['Toko', 'Fast Moving (SKU)', 'Medium Moving (SKU)', 'Slow Moving (SKU)', 'Total SKU Terjual', 'Qty Keluar QC Resi'];
    }

    public function array(): array
    {
        return array_map(fn (array $row) => [
            $this->spreadsheetText($row['store']),
            $row['fast_items'],
            $row['medium_items'],
            $row['slow_items'],
            $row['moving_items'],
            $row['demand_out'],
        ], $this->rows());
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $sheet = $event->sheet->getDelegate();
            $lastRow = max(1, count($this->rows()) + 1);
            $sheet->getStyle('A1:F1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
            $sheet->getStyle('A1:F1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1B84FF');
            $sheet->getStyle("A1:F{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E4E6EF');
            $sheet->getStyle("B2:F{$lastRow}")->getNumberFormat()->setFormatCode('#,##0');
            $sheet->freezePane('A2');
            foreach (range('A', 'F') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }
        }];
    }

    private function rows(): array
    {
        return $this->rows ??= $this->report->storeRows();
    }
}
