<?php

namespace App\Exports;

use App\Models\Attendance;
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

class AttendancesExport implements FromCollection, WithHeadings, WithMapping, WithTitle, WithCustomStartCell, ShouldAutoSize, WithStyles, WithEvents
{
    public function __construct(
        private Collection $rows,
        private string $dateFrom,
        private string $dateTo
    ) {
    }

    public function title(): string
    {
        return 'Rekap Absensi';
    }

    public function startCell(): string
    {
        return 'A5';
    }

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'Tanggal',
            'Kode Karyawan',
            'Nama Karyawan',
            'Status Karyawan',
            'Shift',
            'Check In',
            'Check Out',
            'Status Absensi',
            'Telat (Menit)',
            'Pulang Cepat (Menit)',
            'Kerja (Jam)',
            'Lembur Hitung (Jam)',
            'Lembur Approved (Jam)',
            'Status Lembur',
            'Approved By',
            'Approved At',
            'Source',
            'Catatan Absensi',
            'Catatan Lembur',
        ];
    }

    public function map($row): array
    {
        $isArray = is_array($row);

        return [
            $isArray ? ($row['attendance_date'] ?? '-') : ($row->attendance_date?->format('Y-m-d') ?? '-'),
            $isArray ? $this->employeeCode($row['employee'] ?? '-') : ($row->employee?->employee_code ?? '-'),
            $isArray ? $this->employeeName($row['employee'] ?? '-') : ($row->employee?->name ?? '-'),
            $this->employmentStatusLabel($isArray ? ($row['employment_status'] ?? null) : $row->employee?->employment_status),
            $isArray ? ($row['shift'] ?? '-') : ($row->shift?->name ?? '-'),
            $isArray ? ($row['check_in_at'] ?? '-') : ($row->check_in_at?->format('Y-m-d H:i:s') ?? '-'),
            $isArray ? ($row['check_out_at'] ?? '-') : ($row->check_out_at?->format('Y-m-d H:i:s') ?? '-'),
            $isArray ? ($row['status_label'] ?? $this->attendanceStatusLabel($row['status'] ?? null)) : $this->attendanceStatusLabel($row->status),
            (int) ($isArray ? ($row['late_minutes'] ?? 0) : ($row->late_minutes ?? 0)),
            (int) ($isArray ? ($row['early_leave_minutes'] ?? 0) : ($row->early_leave_minutes ?? 0)),
            round(((int) ($isArray ? ($row['work_minutes'] ?? 0) : ($row->work_minutes ?? 0))) / 60, 2),
            round(((int) ($isArray ? ($row['calculated_overtime_minutes'] ?? 0) : ($row->calculated_overtime_minutes ?? 0))) / 60, 2),
            round(((int) ($isArray ? ($row['approved_overtime_minutes'] ?? 0) : ($row->approved_overtime_minutes ?? 0))) / 60, 2),
            $this->overtimeStatusLabel($isArray ? ($row['overtime_status'] ?? null) : $row->overtime_status),
            $isArray ? ($row['approved_by'] ?? '-') : ($row->approver?->name ?? '-'),
            $isArray ? ($row['approved_at'] ?? '-') : ($row->approved_at?->format('Y-m-d H:i:s') ?? '-'),
            $isArray ? ($row['source'] ?? '-') : ($row->source ?? '-'),
            $isArray ? ($row['note'] ?? '-') : ($row->note ?? '-'),
            $isArray ? ($row['overtime_note'] ?? '-') : ($row->overtime_note ?? '-'),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->mergeCells('A1:S1');
        $sheet->mergeCells('A2:S2');
        $sheet->mergeCells('A3:S3');
        $sheet->setCellValue('A1', 'Rekap Absensi Harian');
        $sheet->setCellValue('A2', 'Periode '.$this->dateFrom.' sampai '.$this->dateTo);
        $sheet->setCellValue('A3', 'Total data: '.number_format($this->rows->count(), 0, ',', '.'));

        return [
            1 => ['font' => ['bold' => true, 'size' => 16]],
            2 => ['font' => ['color' => ['rgb' => '7E8299']]],
            3 => ['font' => ['bold' => true]],
            5 => [
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
                $lastRow = max(5, 5 + $this->rows->count());
                $range = 'A5:S'.$lastRow;

                $sheet->freezePane('A6');
                $sheet->setAutoFilter($range);
                $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E4E6EF');
                $sheet->getStyle('I6:M'.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle('A1:S'.$lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle('K6:M'.$lastRow)->getNumberFormat()->setFormatCode('#,##0.00');
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

    private function attendanceStatusLabel(?string $status): string
    {
        return match ($status) {
            Attendance::STATUS_PRESENT => 'Hadir',
            Attendance::STATUS_LATE => 'Terlambat',
            Attendance::STATUS_ABSENT => 'Alfa',
            Attendance::STATUS_INCOMPLETE => 'Belum Check-out',
            Attendance::STATUS_LEAVE => 'Cuti/Izin',
            Attendance::STATUS_HOLIDAY => 'Libur Perusahaan',
            Attendance::STATUS_DAY_OFF => 'Libur',
            'not_checked_in' => 'Belum Check-in',
            default => $status ?: '-',
        };
    }

    private function employeeCode(string $employee): string
    {
        return trim(explode(' - ', $employee, 2)[0] ?? '-') ?: '-';
    }

    private function employeeName(string $employee): string
    {
        $parts = explode(' - ', $employee, 2);
        return trim($parts[1] ?? $employee) ?: '-';
    }

    private function overtimeStatusLabel(?string $status): string
    {
        return match ($status) {
            Attendance::OVERTIME_NONE => 'Tidak Ada',
            Attendance::OVERTIME_PENDING => 'Pending',
            Attendance::OVERTIME_APPROVED => 'Approved',
            Attendance::OVERTIME_REJECTED => 'Rejected',
            default => $status ?: '-',
        };
    }
}
