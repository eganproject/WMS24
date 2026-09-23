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

class InboundLeadTimeReportDetailSheet implements FromArray, WithHeadings, WithTitle, WithEvents
{
    use SanitizesSpreadsheetText;

    public function __construct(private array $report)
    {
    }

    public function title(): string
    {
        return 'Detail Dokumen';
    }

    public function headings(): array
    {
        return [
            'Kode Inbound', 'Jenis', 'Status', 'Referensi', 'Surat Jalan', 'Supplier', 'Gudang',
            'Pembuat', 'Petugas Mulai', 'Petugas Selesai', 'Tanggal Transaksi', 'Dibuat', 'Mulai Scan',
            'Completed', 'Menunggu Scan (menit)', 'Proses Scan (menit)', 'Total Lead Time (menit)',
            'Aging Terbuka (menit)', 'SKU', 'Expected Qty', 'Scanned Qty', 'Selisih Qty',
            'Expected Koli/Unit', 'Scanned Koli/Unit', 'Progress (%)', 'Reset',
        ];
    }

    public function array(): array
    {
        return array_map(fn (array $row) => [
            $this->spreadsheetText($row['code']), $this->spreadsheetText($row['type_label']), $this->spreadsheetText($row['status_label']),
            $this->spreadsheetText($row['ref_no']), $this->spreadsheetText($row['surat_jalan_no']), $this->spreadsheetText($row['supplier']),
            $this->spreadsheetText($row['warehouse']), $this->spreadsheetText($row['creator']), $this->spreadsheetText($row['starter']),
            $this->spreadsheetText($row['completer']), $row['transaction_at'], $row['created_at'], $row['started_at'], $row['completed_at'],
            $row['waiting_minutes'], $row['scan_minutes'], $row['lead_minutes'], $row['aging_minutes'], $row['total_sku'],
            $row['expected_qty'], $row['scanned_qty'], $row['qty_variance'], $row['expected_koli'], $row['scanned_koli'],
            $row['progress_percent'], $row['reset_count'],
        ], $this->report['details']);
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $sheet = $event->sheet->getDelegate();
            $lastRow = max(1, count($this->report['details']) + 1);
            $sheet->getStyle('A1:Z1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
            $sheet->getStyle('A1:Z1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1B84FF');
            $sheet->getStyle("A1:Z{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E4E6EF');
            $sheet->getStyle("A2:A{$lastRow}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
            $sheet->getStyle("D2:E{$lastRow}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
            $sheet->getStyle("O2:Z{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->freezePane('A2');
            $sheet->setAutoFilter("A1:Z{$lastRow}");
            foreach (range('A', 'Z') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }
        }];
    }
}
