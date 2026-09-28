<?php

namespace App\Exports;

use App\Exports\Concerns\SanitizesSpreadsheetText;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class InboundLeadTimeReportSummarySheet implements FromArray, WithEvents, WithTitle
{
    use SanitizesSpreadsheetText;

    public function __construct(private array $report) {}

    public function title(): string
    {
        return 'Ringkasan Operasional';
    }

    public function array(): array
    {
        $summary = $this->report['summary'];
        $rows = [
            ['LAPORAN LEAD TIME OPERASIONAL'],
            [$this->periodLabel($this->report['period'])],
            [],
            ['INDIKATOR KESELURUHAN', 'NILAI'],
            ['Total proses', $summary['total_documents']],
            ['Proses selesai', $summary['completed_documents']],
            ['Proses belum selesai', $summary['open_documents']],
            ['Completion rate (%)', $summary['completion_rate']],
            ['Rata-rata lead time (menit)', $summary['avg_lead_minutes']],
            ['Median lead time (menit)', $summary['median_lead_minutes']],
            ['P90 lead time (menit)', $summary['p90_lead_minutes']],
            ['Lead time terlama (menit)', $summary['max_lead_minutes']],
            ['Backlog terlama (menit)', $summary['oldest_open_minutes']],
            ['Proses tanpa PIC', $summary['missing_pic_documents']],
            ['Proses tanpa jabatan terhubung', $summary['missing_position_documents']],
            [],
            ['RINGKASAN PER ROLE'],
            ['Role', 'Definisi', 'Proses', 'Selesai', 'Terbuka', 'Completion (%)', 'Rata-rata (menit)', 'Median (menit)', 'P90 (menit)', 'Maksimum (menit)', 'Backlog Terlama (menit)'],
        ];
        foreach ($this->report['roles'] as $role) {
            $rows[] = [$role['label'], $role['definition'], $role['total'], $role['completed'], $role['open'], $role['completion_rate'], $role['avg_lead_minutes'], $role['median_lead_minutes'], $role['p90_lead_minutes'], $role['max_lead_minutes'], $role['oldest_open_minutes']];
        }
        $rows[] = [];
        $rows[] = ['RINGKASAN PER JABATAN'];
        $rows[] = ['Role', 'Jabatan', 'Proses', 'Selesai', 'Terbuka', 'Completion (%)', 'Rata-rata (menit)', 'Median (menit)', 'P90 (menit)', 'Maksimum (menit)', 'Backlog Terlama (menit)'];
        foreach ($this->report['positions'] as $position) {
            $rows[] = [$position['role_label'], $position['position'], $position['total'], $position['completed'], $position['open'], $position['completion_rate'], $position['avg_lead_minutes'], $position['median_lead_minutes'], $position['p90_lead_minutes'], $position['max_lead_minutes'], $position['oldest_open_minutes']];
        }

        return $rows;
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $sheet = $event->sheet->getDelegate();
            $roleEnd = 18 + count($this->report['roles']);
            $positionTitle = $roleEnd + 2;
            $positionHeader = $positionTitle + 1;
            $lastRow = max($positionHeader, $positionHeader + count($this->report['positions']));
            foreach ([1, 17, $positionTitle] as $row) {
                $sheet->mergeCells("A{$row}:K{$row}");
                $sheet->getStyle("A{$row}:K{$row}")->getFont()->setBold(true)->setSize($row === 1 ? 16 : 13);
            }
            $sheet->mergeCells('A2:K2');
            $sheet->getStyle('A2:K2')->getFont()->getColor()->setRGB('7E8299');
            foreach ([4, 18, $positionHeader] as $row) {
                $sheet->getStyle("A{$row}:K{$row}")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                $sheet->getStyle("A{$row}:K{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1B84FF');
            }
            $sheet->getStyle('A4:B15')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E4E6EF');
            $sheet->getStyle("A18:K{$roleEnd}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E4E6EF');
            $sheet->getStyle("A{$positionHeader}:K{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E4E6EF');
            $sheet->getStyle("B5:K{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->freezePane('A18');
            foreach (range('A', 'K') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }
        }];
    }

    private function periodLabel(array $period): string
    {
        $from = $period['date_from'] ?: 'Semua tanggal';
        $to = $period['date_to'] ?: 'Semua tanggal';
        $role = $period['role'] ?: 'Semua role';
        $status = $period['status'] ?: 'Semua status';
        $search = $period['search'] ? ' | Pencarian: '.$this->spreadsheetText($period['search']) : '';

        return "Waktu mulai proses: {$from} s.d. {$to} | Role: {$role} | Status: {$status}{$search}";
    }
}
