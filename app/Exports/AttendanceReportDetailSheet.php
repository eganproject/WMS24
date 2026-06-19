<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AttendanceReportDetailSheet implements FromCollection, WithHeadings, WithTitle, WithCustomStartCell, ShouldAutoSize, WithStyles, WithEvents
{
    public function __construct(
        private Collection $rows,
        private array $period
    ) {
    }

    public function title(): string
    {
        return 'Detail Harian';
    }

    public function startCell(): string
    {
        return 'A4';
    }

    public function collection(): Collection
    {
        return $this->rows->flatMap(function (array $row) {
            return collect($row['detail_rows'] ?? [])->map(fn (array $detail) => [
                $detail['date'] ?? '-',
                $row['employee_code'] ?? '-',
                $row['employee_name'] ?? '-',
                $row['area'] ?? '-',
                $row['position'] ?? '-',
                $detail['schedule_label'] ?? $this->scheduleTypeLabel($detail['schedule_type'] ?? null),
                $detail['shift'] ?? '-',
                $detail['check_in_at'] ?? '-',
                $detail['check_out_at'] ?? '-',
                $detail['status_label'] ?? $this->attendanceStatusLabel($detail['status'] ?? null),
                (int) ($detail['late_minutes'] ?? 0),
                (int) ($detail['early_leave_minutes'] ?? 0),
                round(((int) ($detail['work_minutes'] ?? 0)) / 60, 2),
                round(((int) ($detail['calculated_overtime_minutes'] ?? 0)) / 60, 2),
                round(((int) ($detail['approved_overtime_minutes'] ?? 0)) / 60, 2),
                $this->overtimeStatusLabel($detail['overtime_status'] ?? null),
                $detail['note'] ?? '-',
            ]);
        })->values();
    }

    public function headings(): array
    {
        return [
            'Tanggal',
            'Kode Karyawan',
            'Nama Karyawan',
            'Area',
            'Jabatan',
            'Jadwal',
            'Shift',
            'Masuk',
            'Pulang',
            'Status',
            'Telat (Menit)',
            'Pulang Cepat (Menit)',
            'Jam Kerja',
            'Lembur Hitung',
            'Lembur Approved',
            'Status Lembur',
            'Catatan',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->mergeCells('A1:Q1');
        $sheet->mergeCells('A2:Q2');
        $sheet->setCellValue('A1', 'Detail Harian Absensi');
        $sheet->setCellValue('A2', 'Periode '.$this->period['from'].' sampai '.$this->period['to']);

        return [
            1 => ['font' => ['bold' => true, 'size' => 16]],
            2 => ['font' => ['color' => ['rgb' => '7E8299']]],
            4 => [
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
                $rowCount = $this->collection()->count();
                $lastRow = max(4, 4 + $rowCount);
                $range = 'A4:Q'.$lastRow;

                $sheet->freezePane('A5');
                $sheet->setAutoFilter($range);
                $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E4E6EF');
                $sheet->getStyle('K5:O'.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle('A1:Q'.$lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle('M5:O'.$lastRow)->getNumberFormat()->setFormatCode('#,##0.00');
            },
        ];
    }

    private function scheduleTypeLabel(?string $value): string
    {
        return match ($value) {
            'work' => 'Masuk',
            'leave' => 'Cuti/Izin',
            'holiday' => 'Libur Perusahaan',
            'day_off' => 'Libur Mingguan',
            'not_checked_in' => 'Belum Check-in',
            default => $value ?: '-',
        };
    }

    private function attendanceStatusLabel(?string $value): string
    {
        return match ($value) {
            'present' => 'Hadir',
            'late' => 'Terlambat',
            'absent' => 'Alpha',
            'incomplete' => 'Tidak Lengkap',
            'leave' => 'Cuti/Izin',
            'holiday' => 'Libur Perusahaan',
            'day_off' => 'Libur Mingguan',
            'not_checked_in' => 'Belum Check-in',
            default => $value ?: '-',
        };
    }

    private function overtimeStatusLabel(?string $value): string
    {
        return match ($value) {
            'none' => 'Tidak Ada',
            'pending' => 'Pending',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            default => $value ?: '-',
        };
    }
}
