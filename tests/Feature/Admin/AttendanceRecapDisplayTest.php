<?php

namespace Tests\Feature\Admin;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AttendanceRecapDisplayTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_recap_defaults_to_today_and_returns_filtered_summary(): void
    {
        Carbon::setTestNow('2026-06-05 10:00:00');
        $user = User::factory()->create();
        $role = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        $user->roles()->attach($role);
        $this->actingAs($user);

        $employee = Employee::create([
            'employee_code' => 'EMP-RECAP',
            'name' => 'Karyawan Rekap',
            'employment_status' => 'active',
        ]);

        Attendance::create([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-06-05',
            'status' => Attendance::STATUS_LATE,
            'late_minutes' => 10,
            'overtime_status' => Attendance::OVERTIME_PENDING,
            'calculated_overtime_minutes' => 30,
        ]);
        Attendance::create([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-06-04',
            'status' => Attendance::STATUS_PRESENT,
        ]);
        $offEmployee = Employee::create([
            'employee_code' => 'EMP-OFF',
            'name' => 'Karyawan Libur',
            'employment_status' => 'active',
        ]);
        Attendance::create([
            'employee_id' => $offEmployee->id,
            'attendance_date' => '2026-06-05',
            'status' => Attendance::STATUS_DAY_OFF,
        ]);

        $this->getJson(route('admin.attendance.attendances.data', ['draw' => 1]))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('summary.total', 2)
            ->assertJsonPath('summary.late', 1)
            ->assertJsonPath('summary.present', 0)
            ->assertJsonPath('summary.day_off', 1)
            ->assertJsonPath('summary.overtime_pending', 1);
    }
}
