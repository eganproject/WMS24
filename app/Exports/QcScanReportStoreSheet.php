<?php

namespace App\Exports;

use App\Exports\Concerns\SanitizesSpreadsheetText;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class QcScanReportStoreSheet implements FromArray, WithHeadings, WithTitle, WithEvents
{
    use SanitizesSpreadsheetText;

    public function __construct(private array $report)
    {
    }

    public function title(): string
    {
        return 'Per Toko';
    }

    public function headings(): array
    {
        return ['Toko', 'Resi QC', 'Porsi (%)', 'Petugas', 'Total SKU', 'Total Qty', 'Durasi Rata-rata (menit)', 'Reset', 'Substitusi', 'Double Scan'];
    }

    public function array(): array
    {
        return array_map(fn (array $row) => [
            $this->spreadsheetText($row['store']), $row['total_resi'], $row['percentage'], $row['total_operators'],
            $row['total_sku'], $row['total_qty'], $row['avg_duration_minutes'], $row['reset_count'],
            $row['substitution_count'], $row['duplicate_attempts'],
        ], $this->report['stores'] ?? []);
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $sheet = $event->sheet->getDelegate();
            $lastRow = max(1, count($this->report['stores'] ?? []) + 1);
            $sheet->getStyle('A1:J1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
            $sheet->getStyle('A1:J1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1B84FF');
            $sheet->getStyle("A1:J{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E4E6EF');
            $sheet->getStyle("B2:J{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->freezePane('A2');
            $sheet->setAutoFilter("A1:J{$lastRow}");
            foreach (range('A', 'J') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }
        }];
    }
}
