<?php

namespace App\Exports;

use App\Exports\Concerns\SanitizesSpreadsheetText;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class InboundLeadTimeReportSummarySheet implements FromArray, WithTitle, WithEvents
{
    use SanitizesSpreadsheetText;

    public function __construct(private array $report)
    {
    }

    public function title(): string
    {
        return 'Ringkasan';
    }

    public function array(): array
    {
        $summary = $this->report['summary'];
        $period = $this->report['period'];
        $rows = [
            ['LAPORAN LEAD TIME INBOUND'],
            [$this->periodLabel($period)],
            [],
            ['INDIKATOR', 'NILAI'],
            ['Total dokumen dibuat', $summary['total_documents']],
            ['Dokumen selesai', $summary['completed_documents']],
            ['Menunggu scan', $summary['pending_documents']],
            ['Sedang scan', $summary['scanning_documents']],
            ['Completion rate (%)', $summary['completion_rate']],
            ['Rata-rata menunggu scan (menit)', $summary['avg_waiting_minutes']],
            ['Rata-rata proses scan (menit)', $summary['avg_scan_minutes']],
            ['Rata-rata dibuat sampai selesai (menit)', $summary['avg_lead_minutes']],
            ['Lead time terlama (menit)', $summary['max_lead_minutes']],
            ['Aging dokumen terbuka terlama (menit)', $summary['oldest_open_minutes']],
            ['Total qty dokumen', $summary['total_expected_qty']],
            ['Total qty hasil scan', $summary['total_scanned_qty']],
            ['Dokumen selesai berselisih', $summary['variance_documents']],
            ['Total reset scan', $summary['reset_count']],
            [],
            ['RINGKASAN PER TANGGAL DIBUAT'],
            ['Tanggal', 'Dibuat', 'Selesai', 'Belum Selesai', 'Rata-rata Lead Time (menit)'],
        ];

        foreach ($this->report['charts']['daily'] as $row) {
            $rows[] = [$row['date'], $row['created'], $row['completed'], $row['open'], $row['avg_lead_minutes']];
        }

        return $rows;
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $sheet = $event->sheet->getDelegate();
            $lastRow = max(21, 21 + count($this->report['charts']['daily']));
            $sheet->mergeCells('A1:E1');
            $sheet->mergeCells('A2:E2');
            $sheet->mergeCells('A20:E20');
            $sheet->getStyle('A1:E1')->getFont()->setBold(true)->setSize(16);
            $sheet->getStyle('A2:E2')->getFont()->getColor()->setRGB('7E8299');
            $sheet->getStyle('A20:E20')->getFont()->setBold(true)->setSize(13);
            foreach ([4, 21] as $headerRow) {
                $sheet->getStyle("A{$headerRow}:E{$headerRow}")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                $sheet->getStyle("A{$headerRow}:E{$headerRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1B84FF');
            }
            $sheet->getStyle('A4:B18')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E4E6EF');
            $sheet->getStyle("A21:E{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E4E6EF');
            $sheet->getStyle("B5:E{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->freezePane('A22');
            $sheet->setAutoFilter("A21:E{$lastRow}");
            foreach (range('A', 'E') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }
        }];
    }

    private function periodLabel(array $period): string
    {
        $from = $period['date_from'] ?: 'Semua tanggal';
        $to = $period['date_to'] ?: 'Semua tanggal';
        $type = $period['type'] ?: 'Semua jenis';
        $status = $period['status'] ?: 'Semua status';
        $search = $period['search'] ? ' | Pencarian: '.$this->spreadsheetText($period['search']) : '';

        return "Tanggal dokumen dibuat: {$from} s.d. {$to} | Jenis: {$type} | Status: {$status}{$search}";
    }
}
