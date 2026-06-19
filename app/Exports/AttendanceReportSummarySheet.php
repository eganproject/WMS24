<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AttendanceReportSummarySheet implements FromCollection, WithHeadings, WithMapping, WithTitle, WithCustomStartCell, ShouldAutoSize, WithStyles, WithEvents
{
    public function __construct(
        private Collection $rows,
        private array $summary,
        private array $period
    ) {
    }

    public function title(): string
    {
        return 'Ringkasan';
    }

    public function startCell(): string
    {
        return 'A7';
    }

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'Kode Karyawan',
            'Nama Karyawan',
            'Status Karyawan',
            'Area',
            'Jabatan',
            'Hari Kerja',
            'Hadir',
            'Telat',
            'Alpha',
            'Tidak Lengkap',
            'Cuti/Izin',
            'Libur',
            'Libur Perusahaan',
            'Libur Mingguan',
            'Rate Hadir (%)',
            'Rate Tepat Waktu (%)',
            'Jam Kerja',
            'Lembur Approved',
            'Lembur Pending',
        ];
    }

    public function map($row): array
    {
        return [
            $row['employee_code'] ?? '-',
            $row['employee_name'] ?? '-',
            $this->employmentStatusLabel($row['employment_status'] ?? null),
            $row['area'] ?? '-',
            $row['position'] ?? '-',
            (int) ($row['scheduled_work_days'] ?? 0),
            (int) ($row['present_days'] ?? 0),
            (int) ($row['late_days'] ?? 0),
            (int) ($row['absent_days'] ?? 0),
            (int) ($row['incomplete_days'] ?? 0),
            (int) ($row['leave_days'] ?? 0),
            (int) ($row['non_work_days'] ?? 0),
            (int) ($row['holiday_days'] ?? 0),
            (int) ($row['day_off_days'] ?? 0),
            (float) ($row['attendance_rate'] ?? 0),
            (float) ($row['punctual_rate'] ?? 0),
            round(((int) ($row['work_minutes'] ?? 0)) / 60, 2),
            round(((int) ($row['approved_overtime_minutes'] ?? 0)) / 60, 2),
            round(((int) ($row['pending_overtime_minutes'] ?? 0)) / 60, 2),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->mergeCells('A1:S1');
        $sheet->mergeCells('A2:S2');
        $sheet->mergeCells('A4:D4');
        $sheet->mergeCells('E4:H4');
        $sheet->mergeCells('I4:M4');
        $sheet->mergeCells('N4:S4');

        $sheet->setCellValue('A1', 'Laporan Absensi');
        $sheet->setCellValue('A2', 'Periode '.$this->period['from'].' sampai '.$this->period['to']);
        $sheet->setCellValue('A4', 'Karyawan: '.number_format((int) ($this->summary['employees'] ?? 0), 0, ',', '.'));
        $sheet->setCellValue('E4', 'Hari Kerja: '.number_format((int) ($this->summary['scheduled_work_days'] ?? 0), 0, ',', '.'));
        $sheet->setCellValue('I4', 'Rate Hadir: '.number_format((float) ($this->summary['attendance_rate'] ?? 0), 2, ',', '.').'%');
        $sheet->setCellValue('N4', 'Libur: '.number_format((int) ($this->summary['non_work_days'] ?? 0), 0, ',', '.').' hari | Lembur Approved: '.$this->hours((int) ($this->summary['approved_overtime_minutes'] ?? 0)));

        return [
            1 => ['font' => ['bold' => true, 'size' => 16]],
            2 => ['font' => ['color' => ['rgb' => '7E8299']]],
            4 => ['font' => ['bold' => true]],
            7 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1B84FF']],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = max(7, 7 + $this->rows->count());
                $range = 'A7:S'.$lastRow;

                $sheet->freezePane('A8');
                $sheet->setAutoFilter($range);
                $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E4E6EF');
                $sheet->getStyle('F8:S'.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle('A1:S'.$lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle('O8:S'.$lastRow)->getNumberFormat()->setFormatCode('#,##0.00');
            },
        ];
    }

    private function employmentStatusLabel(?string $status): string
    {
        return match ($status) {
            'active' => 'Aktif',
            'inactive' => 'Nonaktif',
            default => $status ?: '-',
        };
    }

    private function hours(int $minutes): string
    {
        return number_format($minutes / 60, 2, ',', '.').' jam';
    }
}
