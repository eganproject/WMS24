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

class InboundLeadTimeReportDetailSheet implements FromArray, WithEvents, WithHeadings, WithTitle
{
    use SanitizesSpreadsheetText;

    public function __construct(private array $report) {}

    public function title(): string
    {
        return 'Detail Operasional';
    }

    public function headings(): array
    {
        return [
            'Role', 'Definisi Lead Time', 'Kode Dokumen', 'Referensi', 'Status', 'Tahap Saat Ini',
            'Waktu Mulai', 'Waktu Selesai', 'Lead Time (menit)', 'Aging Terbuka (menit)',
            'PIC', 'Jabatan', 'Pelaku Awal', 'Pelaku Akhir', 'Jumlah SKU', 'Jumlah Qty', 'Status Sumber',
        ];
    }

    public function array(): array
    {
        return array_map(fn (array $row) => [
            $this->spreadsheetText($row['role_label']), $this->spreadsheetText($row['process_label']),
            $this->spreadsheetText($row['code']), $this->spreadsheetText($row['reference']),
            $this->spreadsheetText($row['status_label']), $this->spreadsheetText($row['stage_label']),
            $row['started_at'], $row['completed_at'], $row['lead_minutes'], $row['aging_minutes'],
            $this->spreadsheetText($row['pic']), $this->spreadsheetText($row['position']),
            $this->spreadsheetText($row['start_actor']), $this->spreadsheetText($row['end_actor']),
            $row['total_sku'], $row['total_qty'], $this->spreadsheetText($row['source_status']),
        ], $this->report['details']);
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $sheet = $event->sheet->getDelegate();
            $lastRow = max(1, count($this->report['details']) + 1);
            $sheet->getStyle('A1:Q1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
            $sheet->getStyle('A1:Q1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1B84FF');
            $sheet->getStyle("A1:Q{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E4E6EF');
            $sheet->getStyle("C2:D{$lastRow}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
            $sheet->getStyle("I2:P{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->freezePane('A2');
            $sheet->setAutoFilter("A1:Q{$lastRow}");
            foreach (range('A', 'Q') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }
        }];
    }
}
