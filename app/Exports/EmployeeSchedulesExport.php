<?php

namespace App\Exports;

use App\Models\EmployeeSchedule;
use App\Models\Employee;
use App\Models\Holiday;
use Illuminate\Database\Eloquent\Builder;
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

class EmployeeSchedulesExport implements FromCollection, WithHeadings, WithMapping, WithTitle, WithCustomStartCell, ShouldAutoSize, WithStyles, WithEvents
{
    private Collection $rows;
    private Collection $holidaysByDate;

    public function __construct(private array $filters = [])
    {
        $this->rows = $this->query()->get();
        $this->holidaysByDate = $this->rows->isEmpty()
            ? collect()
            : Holiday::query()
                ->whereDate('holiday_date', '>=', $this->rows->min('schedule_date'))
                ->whereDate('holiday_date', '<=', $this->rows->max('schedule_date'))
                ->get()
                ->keyBy(fn (Holiday $holiday) => $holiday->holiday_date?->toDateString());
    }

    public function title(): string
    {
        return 'Jadwal Kerja';
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
        return self::headers();
    }

    public static function headers(): array
    {
        return [
            'employee_code',
            'employee_name',
            'schedule_date',
            'schedule_type',
            'schedule_type_label',
            'shift',
            'work_shift_id',
            'shift_time',
            'note',
            'source',
        ];
    }

    public function map($schedule): array
    {
        $holiday = $this->holidaysByDate->get($schedule->schedule_date?->toDateString());
        $effectiveType = $holiday ? EmployeeSchedule::TYPE_HOLIDAY : $schedule->schedule_type;
        $effectiveShift = $effectiveType === EmployeeSchedule::TYPE_WORK ? $schedule->shift : null;
        $shiftStart = $this->timeValue($effectiveShift?->start_time);
        $shiftEnd = $this->timeValue($effectiveShift?->end_time);
        $shiftTime = $shiftStart && $shiftEnd
            ? $shiftStart.' - '.$shiftEnd.($effectiveShift?->crosses_midnight ? ' (+1 hari)' : '')
            : '';

        return [
            $schedule->employee?->employee_code ?? '',
            $schedule->employee?->name ?? '',
            $schedule->schedule_date?->format('Y-m-d') ?? '',
            $effectiveType,
            $this->scheduleTypeLabel($effectiveType, $holiday),
            $effectiveShift?->name ?? '',
            $effectiveType === EmployeeSchedule::TYPE_WORK ? $schedule->work_shift_id : null,
            $shiftTime,
            $holiday?->name ?? $schedule->note ?? '',
            $schedule->employee_schedule_assignment_id ? 'template' : 'manual',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->mergeCells('A1:J1');
        $sheet->mergeCells('A2:J2');
        $sheet->mergeCells('A3:J3');
        $sheet->setCellValue('A1', 'Export Jadwal Kerja Karyawan');
        $sheet->setCellValue('A2', 'Periode '.$this->periodLabel());
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
                $range = 'A5:J'.$lastRow;

                $sheet->freezePane('A6');
                $sheet->setAutoFilter($range);
                $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E4E6EF');
                $sheet->getStyle('A1:J'.$lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle('C6:C'.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle('G6:G'.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            },
        ];
    }

    private function query(): Builder
    {
        $query = EmployeeSchedule::query()
            ->with(['employee:id,employee_code,name,employment_status', 'shift:id,name,start_time,end_time,crosses_midnight'])
            ->whereHas('employee', fn ($employeeQuery) => $employeeQuery->where('employment_status', 'active'))
            ->orderBy('schedule_date')
            ->orderBy(Employee::select('employee_code')
                ->whereColumn('employees.id', 'employee_schedules.employee_id')
                ->limit(1));

        if (!empty($this->filters['employee_id'])) {
            $query->where('employee_id', (int) $this->filters['employee_id']);
        }

        if (!empty($this->filters['date_from'])) {
            $query->whereDate('schedule_date', '>=', $this->filters['date_from']);
        }

        if (!empty($this->filters['date_to'])) {
            $query->whereDate('schedule_date', '<=', $this->filters['date_to']);
        }

        if (!empty($this->filters['schedule_type'])) {
            $query->where('schedule_type', $this->filters['schedule_type']);
        }

        $search = trim((string) ($this->filters['q'] ?? ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('schedule_date', 'like', "%{$search}%")
                    ->orWhere('schedule_type', 'like', "%{$search}%")
                    ->orWhere('note', 'like', "%{$search}%")
                    ->orWhereHas('employee', fn ($eq) => $eq
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('employee_code', 'like', "%{$search}%"))
                    ->orWhereHas('shift', fn ($sq) => $sq->where('name', 'like', "%{$search}%"));
            });
        }

        return $query;
    }

    private function periodLabel(): string
    {
        $from = $this->filters['date_from'] ?? null;
        $to = $this->filters['date_to'] ?? null;

        return ($from ?: 'awal').' sampai '.($to ?: 'akhir');
    }

    private function scheduleTypeLabel(?string $type, ?Holiday $holiday = null): string
    {
        if ($type === EmployeeSchedule::TYPE_HOLIDAY && $holiday) {
            return $holiday->type === 'national' ? 'Libur Nasional' : 'Libur Perusahaan';
        }

        return match ($type) {
            EmployeeSchedule::TYPE_WORK => 'Masuk',
            EmployeeSchedule::TYPE_DAY_OFF => 'Libur',
            EmployeeSchedule::TYPE_HOLIDAY => 'Libur Perusahaan',
            EmployeeSchedule::TYPE_LEAVE => 'Cuti/Izin',
            default => $type ?: '',
        };
    }

    private function timeValue(?string $value): ?string
    {
        return $value ? substr($value, 0, 5) : null;
    }
}
