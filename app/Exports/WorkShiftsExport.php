<?php

namespace App\Exports;

use App\Models\WorkShift;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class WorkShiftsExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function collection(): Collection
    {
        return WorkShift::query()
            ->orderBy('name')
            ->get();
    }

    public function headings(): array
    {
        return self::headers();
    }

    public static function headers(): array
    {
        return [
            'name',
            'start_time',
            'end_time',
            'break_start_time',
            'break_end_time',
            'late_tolerance_minutes',
            'checkout_tolerance_minutes',
            'overtime_start_after_minutes',
            'minimum_overtime_minutes',
            'crosses_midnight',
            'is_active',
        ];
    }

    public function map($shift): array
    {
        return [
            $shift->name,
            $this->timeValue($shift->start_time),
            $this->timeValue($shift->end_time),
            $this->timeValue($shift->break_start_time),
            $this->timeValue($shift->break_end_time),
            $shift->late_tolerance_minutes,
            $shift->checkout_tolerance_minutes,
            $shift->overtime_start_after_minutes,
            $shift->minimum_overtime_minutes,
            $shift->crosses_midnight ? 'yes' : 'no',
            $shift->is_active ? 'active' : 'inactive',
        ];
    }

    private function timeValue(?string $value): ?string
    {
        return $value ? substr($value, 0, 5) : null;
    }
}
