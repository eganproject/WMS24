<?php

namespace App\Exports;

use App\Exports\Concerns\SanitizesSpreadsheetText;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class QcScanReportSummarySheet implements FromArray, WithTitle, WithEvents
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
            ['LAPORAN PRODUKTIVITAS QC SCAN'],
            [$this->periodLabel($period)],
            [],
            ['INDIKATOR', 'NILAI'],
            ['Resi lolos QC', $summary['total_resi']],
            ['Petugas aktif', $summary['total_operators']],
            ['Jam petugas aktif', $summary['active_operator_hours']],
            ['Rata-rata resi / jam aktif', $summary['avg_resi_per_hour']],
            ['Rata-rata durasi QC (menit)', $summary['avg_duration_minutes']],
            ['Total SKU', $summary['total_sku']],
            ['Total qty', $summary['total_qty']],
            ['Reset', $summary['reset_count']],
            ['Substitusi SKU', $summary['substitution_count']],
            ['Percobaan double scan', $summary['duplicate_attempts']],
            ['Aktivitas hold', $summary['hold_events']],
            ['Jam puncak', $summary['peak_hour'].' ('.$summary['peak_hour_resi'].' resi)'],
            [],
            ['RINGKASAN PETUGAS'],
            ['Petugas', 'Resi QC', 'Jam Aktif', 'Resi / Jam', 'Total SKU', 'Total Qty', 'Durasi Rata-rata (menit)', 'Reset', 'Substitusi', 'Double Scan', 'QC Pertama', 'QC Terakhir'],
        ];

        foreach ($this->report['operators'] as $row) {
            $rows[] = [
                $this->spreadsheetText($row['operator']), $row['total_resi'], $row['active_hours'], $row['avg_resi_per_hour'],
                $row['total_sku'], $row['total_qty'], $row['avg_duration_minutes'], $row['reset_count'],
                $row['substitution_count'], $row['duplicate_attempts'], $row['first_completion'], $row['last_completion'],
            ];
        }

        return $rows;
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $sheet = $event->sheet->getDelegate();
            $lastRow = max(19, 19 + count($this->report['operators']));
            $sheet->mergeCells('A1:L1');
            $sheet->mergeCells('A2:L2');
            $sheet->mergeCells('A18:L18');
            $sheet->getStyle('A1:L1')->getFont()->setBold(true)->setSize(16);
            $sheet->getStyle('A2:L2')->getFont()->getColor()->setRGB('7E8299');
            foreach ([4, 19] as $headerRow) {
                $sheet->getStyle("A{$headerRow}:L{$headerRow}")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                $sheet->getStyle("A{$headerRow}:L{$headerRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1B84FF');
            }
            $sheet->getStyle('A18:L18')->getFont()->setBold(true)->setSize(13);
            $sheet->getStyle('A4:B16')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E4E6EF');
            $sheet->getStyle("A19:L{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E4E6EF');
            $sheet->freezePane('A20');
            $sheet->setAutoFilter("A19:L{$lastRow}");
            $sheet->getStyle("B5:B16")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("B20:J{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("A1:L{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            foreach (range('A', 'L') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }
        }];
    }

    private function periodLabel(array $period): string
    {
        $from = $period['date_from'] ?: 'Semua tanggal';
        $to = $period['date_to'] ?: 'Semua tanggal';
        $operator = $period['operator_name'] ?: 'Semua petugas';
        $search = $period['search'] ? ' | Pencarian: '.$period['search'] : '';

        return "Periode selesai QC: {$from} s.d. {$to} | Petugas: {$operator}{$search}";
    }
}
