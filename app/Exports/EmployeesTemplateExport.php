<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class EmployeesTemplateExport implements FromCollection, WithHeadings
{
    public function collection(): Collection
    {
        return new Collection([
            [
                '',
                'Budi Santoso',
                '081234567890',
                'active',
                'Picker',
                '',
                'GDG',
                '',
                'yes',
                'budi@example.com',
                '',
                'Password!2',
                'picker',
                now()->toDateString(),
            ],
        ]);
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
}
