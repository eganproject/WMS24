<?php

namespace App\Exports;

use App\Models\WeeklyScheduleTemplate;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class WeeklyScheduleTemplatesExport implements FromCollection, WithHeadings, ShouldAutoSize
{
    public function collection(): Collection
    {
        return WeeklyScheduleTemplate::query()
            ->with(['days.shift'])
            ->orderBy('name')
            ->get()
            ->flatMap(function (WeeklyScheduleTemplate $template) {
                return $template->days
                    ->sortBy('day_of_week')
                    ->map(fn ($day) => [
                        $template->name,
                        $template->is_active ? 'active' : 'inactive',
                        $day->day_of_week,
                        $this->dayName((int) $day->day_of_week),
                        $day->schedule_type,
                        $day->shift?->name,
                        $day->work_shift_id,
                    ]);
            })
            ->values();
    }

    public function headings(): array
    {
        return self::headers();
    }

    public static function headers(): array
    {
        return [
            'template_name',
            'is_active',
            'day_of_week',
            'day_name',
            'schedule_type',
            'shift',
            'work_shift_id',
        ];
    }

    private function dayName(int $day): string
    {
        return [
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            7 => 'Minggu',
        ][$day] ?? '';
    }
}
