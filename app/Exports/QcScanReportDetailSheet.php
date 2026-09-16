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
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class QcScanReportDetailSheet implements FromArray, WithHeadings, WithTitle, WithEvents
{
    use SanitizesSpreadsheetText;

    public function __construct(private array $report)
    {
    }

    public function title(): string
    {
        return 'Detail QC';
    }

    public function headings(): array
    {
        return ['Tanggal', 'Jam', 'Petugas', 'ID Pesanan', 'No. Resi', 'Ekspedisi', 'Mulai QC', 'Selesai QC', 'Durasi (menit)', 'SKU', 'Qty Ekspektasi', 'Qty Scan', 'Reset', 'Substitusi', 'Qty Substitusi', 'Double Scan', 'Tipe Scan', 'Kode Scan'];
    }

    public function array(): array
    {
        return array_map(fn (array $row) => [
            $row['date'], $row['hour_label'], $this->spreadsheetText($row['operator']), $this->spreadsheetText($row['id_pesanan']),
            $this->spreadsheetText($row['no_resi']), $this->spreadsheetText($row['expedition']), $row['started_at'], $row['completed_at'],
            $row['duration_minutes'], $row['total_sku'], $row['expected_qty'], $row['total_qty'], $row['reset_count'],
            $row['substitution_count'], $row['substitution_qty'], $row['duplicate_attempts'], $this->spreadsheetText($row['scan_type']),
            $this->spreadsheetText($row['scan_code']),
        ], $this->report['details']);
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $sheet = $event->sheet->getDelegate();
            $lastRow = max(1, count($this->report['details']) + 1);
            $sheet->getStyle('A1:R1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
            $sheet->getStyle('A1:R1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1B84FF');
            $sheet->getStyle("A1:R{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E4E6EF');
            $sheet->getStyle("D2:E{$lastRow}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
            $sheet->getStyle("J2:P{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("R2:R{$lastRow}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
            $sheet->freezePane('A2');
            $sheet->setAutoFilter("A1:R{$lastRow}");
            foreach (range('A', 'R') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }
        }];
    }
}
