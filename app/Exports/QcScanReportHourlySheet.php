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

class QcScanReportHourlySheet implements FromArray, WithHeadings, WithTitle, WithEvents
{
    use SanitizesSpreadsheetText;

    public function __construct(private array $report)
    {
    }

    public function title(): string
    {
        return 'Produktivitas Per Jam';
    }

    public function headings(): array
    {
        return ['Tanggal', 'Jam', 'Petugas', 'Resi QC', 'SKU', 'Qty', 'Resi / Jam', 'Durasi Rata-rata (menit)', 'Reset', 'Substitusi', 'Double Scan', 'Selesai Pertama', 'Selesai Terakhir'];
    }

    public function array(): array
    {
        return array_map(fn (array $row) => [
            $row['date'], $row['hour_label'], $this->spreadsheetText($row['operator']), $row['total_resi'], $row['total_sku'],
            $row['total_qty'], $row['total_resi'], $row['avg_duration_minutes'], $row['reset_count'],
            $row['substitution_count'], $row['duplicate_attempts'], $row['first_completion'], $row['last_completion'],
        ], $this->report['hourly']);
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $sheet = $event->sheet->getDelegate();
            $lastRow = max(1, count($this->report['hourly']) + 1);
            $sheet->getStyle('A1:M1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
            $sheet->getStyle('A1:M1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1B84FF');
            $sheet->getStyle("A1:M{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E4E6EF');
            $sheet->getStyle("D2:K{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->freezePane('A2');
            $sheet->setAutoFilter("A1:M{$lastRow}");
            foreach (range('A', 'M') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }
        }];
    }
}
