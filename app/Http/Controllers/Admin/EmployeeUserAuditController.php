<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class EmployeeUserAuditController extends Controller
{
    public function index()
    {
        return view('admin.masterdata.employee-user-audit.index', [
            'summary' => $this->summary(),
        ]);
    }

    public function data(Request $request)
    {
        $type = (string) $request->input('type', '');
        $search = trim((string) $request->input('q', ''));

        $rows = $this->rows()
            ->when($type !== '', fn (Collection $items) => $items->where('type', $type)->values())
            ->when($search !== '', function (Collection $items) use ($search) {
                $needle = mb_strtolower($search);

                return $items->filter(function (array $row) use ($needle) {
                    $haystack = mb_strtolower(implode(' ', [
                        $row['issue'],
                        $row['user_name'],
                        $row['user_email'],
                        $row['employee_code'],
                        $row['employee_name'],
                        $row['position'],
                        $row['area'],
                        $row['recommendation'],
                    ]));

                    return str_contains($haystack, $needle);
                })->values();
            })
            ->values();

        return response()->json(['data' => $rows]);
    }

    private function summary(): array
    {
        return [
            'users_without_employee' => User::query()->doesntHave('employee')->count(),
            'active_employees_without_user' => Employee::query()
                ->where('employment_status', Employee::STATUS_ACTIVE)
                ->whereNull('user_id')
                ->count(),
            'employees_without_area' => Employee::query()
                ->where('employment_status', Employee::STATUS_ACTIVE)
                ->whereNull('area_id')
                ->count(),
            'employees_without_position' => Employee::query()
                ->where('employment_status', Employee::STATUS_ACTIVE)
                ->whereNull('position_id')
                ->where(function ($query) {
                    $query->whereNull('position')->orWhere('position', '');
                })
                ->count(),
            'inactive_employees_with_user' => Employee::query()
                ->where('employment_status', '!=', Employee::STATUS_ACTIVE)
                ->whereNotNull('user_id')
                ->count(),
            'area_mismatches' => Employee::query()
                ->whereNotNull('employees.user_id')
                ->whereNotNull('employees.area_id')
                ->whereHas('user', fn ($query) => $query->whereNotNull('area_id'))
                ->whereColumn('employees.area_id', '!=', 'users.area_id')
                ->join('users', 'users.id', '=', 'employees.user_id')
                ->count(),
        ];
    }

    private function rows(): Collection
    {
        return collect()
            ->concat($this->usersWithoutEmployeeRows())
            ->concat($this->employeesWithoutUserRows())
            ->concat($this->employeesWithoutAreaRows())
            ->concat($this->employeesWithoutPositionRows())
            ->concat($this->inactiveEmployeesWithUserRows())
            ->concat($this->areaMismatchRows())
            ->values();
    }

    private function usersWithoutEmployeeRows(): Collection
    {
        return User::query()
            ->with(['roles:id,name', 'area:id,code,name'])
            ->doesntHave('employee')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'type' => 'user_without_employee',
                'severity' => 'high',
                'issue' => 'User belum terhubung ke karyawan',
                'user_name' => $user->name,
                'user_email' => $user->email,
                'roles' => $user->roles->pluck('name')->implode(', ') ?: '-',
                'employee_code' => '-',
                'employee_name' => '-',
                'position' => '-',
                'area' => $this->areaLabel($user->area),
                'recommendation' => 'Hubungkan user ini ke data karyawan agar fitur self-service, cuti/izin, dan performa absensi dapat digunakan.',
                'action_url' => route('admin.masterdata.users.edit', $user),
                'action_label' => 'Edit User',
            ]);
    }

    private function employeesWithoutUserRows(): Collection
    {
        return Employee::query()
            ->with(['area:id,code,name', 'positionRelation:id,name'])
            ->whereNull('user_id')
            ->where('employment_status', Employee::STATUS_ACTIVE)
            ->orderBy('name')
            ->get()
            ->map(fn (Employee $employee) => $this->employeeRow(
                $employee,
                'employee_without_user',
                'high',
                'Karyawan aktif belum punya user login',
                'Buat user atau pilih user existing pada master karyawan agar karyawan bisa login ke fitur personal.',
            ));
    }

    private function employeesWithoutAreaRows(): Collection
    {
        return Employee::query()
            ->with(['user:id,name,email,area_id', 'user.roles:id,name', 'user.area:id,code,name', 'area:id,code,name', 'positionRelation:id,name'])
            ->whereNull('area_id')
            ->where('employment_status', Employee::STATUS_ACTIVE)
            ->orderBy('name')
            ->get()
            ->map(fn (Employee $employee) => $this->employeeRow(
                $employee,
                'employee_without_area',
                'medium',
                'Karyawan aktif belum punya area',
                'Lengkapi area karyawan agar filtering laporan, absensi, dan operasional per area lebih akurat.',
            ));
    }

    private function employeesWithoutPositionRows(): Collection
    {
        return Employee::query()
            ->with(['user:id,name,email,area_id', 'user.roles:id,name', 'user.area:id,code,name', 'area:id,code,name', 'positionRelation:id,name'])
            ->where('employment_status', Employee::STATUS_ACTIVE)
            ->whereNull('position_id')
            ->where(function ($query) {
                $query->whereNull('position')->orWhere('position', '');
            })
            ->orderBy('name')
            ->get()
            ->map(fn (Employee $employee) => $this->employeeRow(
                $employee,
                'employee_without_position',
                'medium',
                'Karyawan aktif belum punya jabatan',
                'Lengkapi jabatan karyawan agar KPI, assignment, dan laporan per jabatan tidak kosong.',
            ));
    }

    private function inactiveEmployeesWithUserRows(): Collection
    {
        return Employee::query()
            ->with(['user:id,name,email,area_id', 'user.roles:id,name', 'user.area:id,code,name', 'area:id,code,name', 'positionRelation:id,name'])
            ->where('employment_status', '!=', Employee::STATUS_ACTIVE)
            ->whereNotNull('user_id')
            ->orderBy('name')
            ->get()
            ->map(fn (Employee $employee) => $this->employeeRow(
                $employee,
                'inactive_employee_with_user',
                'medium',
                'Karyawan nonaktif masih terhubung ke user',
                'Review akses user. Jika karyawan sudah tidak aktif, pertimbangkan lepas relasi user atau cabut role akses.',
            ));
    }

    private function areaMismatchRows(): Collection
    {
        return Employee::query()
            ->with(['user:id,name,email,area_id', 'user.roles:id,name', 'user.area:id,code,name', 'area:id,code,name', 'positionRelation:id,name'])
            ->whereNotNull('employees.user_id')
            ->whereNotNull('employees.area_id')
            ->whereHas('user', fn ($query) => $query->whereNotNull('area_id'))
            ->whereColumn('employees.area_id', '!=', 'users.area_id')
            ->join('users', 'users.id', '=', 'employees.user_id')
            ->select('employees.*')
            ->orderBy('employees.name')
            ->get()
            ->map(fn (Employee $employee) => $this->employeeRow(
                $employee,
                'area_mismatch',
                'low',
                'Area user berbeda dengan area karyawan',
                'Samakan area user dan area karyawan jika akses operasional harus mengikuti area kerja karyawan.',
            ));
    }

    private function employeeRow(
        Employee $employee,
        string $type,
        string $severity,
        string $issue,
        string $recommendation
    ): array {
        $user = $employee->user;

        return [
            'type' => $type,
            'severity' => $severity,
            'issue' => $issue,
            'user_name' => $user?->name ?? '-',
            'user_email' => $user?->email ?? '-',
            'roles' => $user?->roles?->pluck('name')->implode(', ') ?: '-',
            'employee_code' => $employee->employee_code,
            'employee_name' => $employee->name,
            'position' => $employee->positionRelation?->name ?? $employee->position ?? '-',
            'area' => $this->employeeAreaLabel($employee),
            'recommendation' => $recommendation,
            'action_url' => route('admin.attendance.employees.index'),
            'action_label' => 'Buka Master Karyawan',
        ];
    }

    private function employeeAreaLabel(Employee $employee): string
    {
        $employeeArea = $this->areaLabel($employee->area);
        $userArea = $this->areaLabel($employee->user?->area);

        if ($employeeArea !== '-' && $userArea !== '-' && $employeeArea !== $userArea) {
            return "Karyawan: {$employeeArea} | User: {$userArea}";
        }

        return $employeeArea;
    }

    private function areaLabel($area): string
    {
        if (!$area) {
            return '-';
        }

        return trim(($area->code ? "{$area->code} - " : '').$area->name);
    }
}
