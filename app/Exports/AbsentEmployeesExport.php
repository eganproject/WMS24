<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AbsentEmployeesExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(private array $rows = [])
    {
    }

    public function collection(): Collection
    {
        return collect($this->rows);
    }

    public function headings(): array
    {
        return [
            'Tanggal',
            'Kode Karyawan',
            'Nama Karyawan',
            'Jabatan',
            'Area',
            'Shift',
            'Jadwal',
            'Check In',
            'Check Out',
            'Status',
            'Telat (Menit)',
            'Pulang Cepat (Menit)',
            'Catatan',
        ];
    }

    public function map($row): array
    {
        return [
            $row['attendance_date'] ?? '-',
            $row['employee_code'] ?? '-',
            $row['employee_name'] ?? '-',
            $row['position'] ?? '-',
            $row['area'] ?? '-',
            $row['shift'] ?? '-',
            $row['schedule_type_label'] ?? '-',
            $row['check_in_at'] ?? '-',
            $row['check_out_at'] ?? '-',
            $row['status_label'] ?? '-',
            $row['late_minutes'] ?? 0,
            $row['early_leave_minutes'] ?? 0,
            $row['note'] ?? '-',
        ];
    }
}
