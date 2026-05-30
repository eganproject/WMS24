<?php

namespace App\Exports;

use App\Models\Employee;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class EmployeesExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(private string $search = '')
    {
    }

    public function collection(): Collection
    {
        $query = Employee::query()
            ->with(['area:id,code,name', 'positionRelation:id,name', 'user:id,email', 'user.roles:id,slug'])
            ->orderBy('name');

        $search = trim($this->search);
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('employee_code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'employee_code',
            'name',
            'phone',
            'employment_status',
            'position',
            'position_id',
            'area',
            'area_id',
            'create_user',
            'user_email',
            'user_id',
            'user_password',
            'user_roles',
            'join_date',
        ];
    }

    public function map($employee): array
    {
        return [
            $employee->employee_code,
            $employee->name,
            $employee->phone,
            $employee->employment_status,
            $employee->positionRelation?->name ?? $employee->position,
            $employee->position_id,
            $employee->area?->code,
            $employee->area_id,
            '',
            $employee->user?->email,
            $employee->user_id,
            '',
            $employee->user?->roles?->pluck('slug')->implode(','),
            $employee->join_date?->format('Y-m-d'),
        ];
    }
}
