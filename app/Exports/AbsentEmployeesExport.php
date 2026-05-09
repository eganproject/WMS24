<?php

namespace App\Exports;

use App\Models\Attendance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AbsentEmployeesExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(private array $filters = [])
    {
    }

    public function collection(): Collection
    {
        return $this->query()->get();
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
            'Status',
            'Catatan',
        ];
    }

    public function map($row): array
    {
        return [
            $row->attendance_date?->format('Y-m-d'),
            $row->employee?->employee_code ?? '-',
            $row->employee?->name ?? '-',
            $row->employee?->positionRelation?->name ?? $row->employee?->position ?? '-',
            $row->employee?->area ? "{$row->employee->area->code} - {$row->employee->area->name}" : '-',
            $row->shift?->name ?? '-',
            'Absen',
            $row->note ?? '-',
        ];
    }

    private function query(): Builder
    {
        $dateFrom = $this->filters['date_from'] ?? now()->toDateString();
        $dateTo = $this->filters['date_to'] ?? $dateFrom;
        $search = trim((string) ($this->filters['q'] ?? ''));

        return Attendance::query()
            ->with(['employee.area:id,code,name', 'employee.positionRelation:id,name', 'shift:id,name'])
            ->where('status', Attendance::STATUS_ABSENT)
            ->whereDate('attendance_date', '>=', Carbon::parse($dateFrom)->toDateString())
            ->whereDate('attendance_date', '<=', Carbon::parse($dateTo)->toDateString())
            ->when($this->filters['employee_id'] ?? null, fn ($query, $employeeId) => $query->where('employee_id', $employeeId))
            ->when($search !== '', function ($query) use ($search) {
                $query->whereHas('employee', fn ($employeeQuery) => $employeeQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%"));
            })
            ->orderByDesc('attendance_date')
            ->orderBy('employee_id');
    }
}
