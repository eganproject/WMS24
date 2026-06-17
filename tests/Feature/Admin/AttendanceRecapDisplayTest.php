<?php

namespace Tests\Feature\Admin;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeLeave;
use App\Models\EmployeeSchedule;
use App\Models\Holiday;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkShift;
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
            'check_in_at' => '2026-06-05 08:10:00',
            'check_out_at' => '2026-06-05 17:00:00',
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
        $checkedInEmployee = Employee::create([
            'employee_code' => 'EMP-CHECKIN',
            'name' => 'Karyawan Sudah Masuk',
            'employment_status' => 'active',
        ]);
        Attendance::create([
            'employee_id' => $checkedInEmployee->id,
            'attendance_date' => '2026-06-05',
            'check_in_at' => '2026-06-05 08:00:00',
            'status' => Attendance::STATUS_INCOMPLETE,
        ]);

        $this->getJson(route('admin.attendance.attendances.data', ['draw' => 1]))
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('summary.total', 3)
            ->assertJsonPath('summary.late', 1)
            ->assertJsonPath('summary.present', 2)
            ->assertJsonPath('summary.incomplete', 1)
            ->assertJsonPath('summary.day_off', 1)
            ->assertJsonPath('summary.overtime_pending', 1);
    }

    public function test_recap_search_filters_rows_and_summary_by_employee_identity(): void
    {
        Carbon::setTestNow('2026-06-05 10:00:00');
        $this->actingAsAdmin();

        $matched = Employee::create([
            'employee_code' => 'EMP-MATCH',
            'name' => 'Budi Rekap',
            'phone' => '08123456789',
            'employment_status' => Employee::STATUS_ACTIVE,
        ]);
        $other = Employee::create([
            'employee_code' => 'EMP-OTHER',
            'name' => 'Karyawan Lain',
            'employment_status' => Employee::STATUS_ACTIVE,
        ]);

        foreach ([$matched, $other] as $employee) {
            Attendance::create([
                'employee_id' => $employee->id,
                'attendance_date' => '2026-06-05',
                'status' => Attendance::STATUS_PRESENT,
                'check_in_at' => '2026-06-05 08:00:00',
            ]);
        }

        $this->getJson(route('admin.attendance.attendances.data', [
            'draw' => 1,
            'q' => '081234',
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.employee_id', $matched->id)
            ->assertJsonPath('summary.total', 1)
            ->assertJsonPath('summary.present', 1);
    }

    public function test_recap_filters_by_date_employee_status_overtime_and_employment_status(): void
    {
        Carbon::setTestNow('2026-06-05 10:00:00');
        $this->actingAsAdmin();

        $active = Employee::create([
            'employee_code' => 'EMP-FILTER-ACTIVE',
            'name' => 'Karyawan Filter Aktif',
            'employment_status' => Employee::STATUS_ACTIVE,
        ]);
        $other = Employee::create([
            'employee_code' => 'EMP-FILTER-OTHER',
            'name' => 'Karyawan Filter Lain',
            'employment_status' => Employee::STATUS_ACTIVE,
        ]);
        $inactive = Employee::create([
            'employee_code' => 'EMP-FILTER-INACTIVE',
            'name' => 'Karyawan Filter Nonaktif',
            'employment_status' => Employee::STATUS_INACTIVE,
        ]);

        Attendance::create([
            'employee_id' => $active->id,
            'attendance_date' => '2026-06-04',
            'status' => Attendance::STATUS_PRESENT,
            'check_in_at' => '2026-06-04 08:00:00',
            'overtime_status' => Attendance::OVERTIME_APPROVED,
        ]);
        Attendance::create([
            'employee_id' => $active->id,
            'attendance_date' => '2026-06-05',
            'status' => Attendance::STATUS_LATE,
            'check_in_at' => '2026-06-05 08:20:00',
            'overtime_status' => Attendance::OVERTIME_PENDING,
        ]);
        Attendance::create([
            'employee_id' => $other->id,
            'attendance_date' => '2026-06-05',
            'status' => Attendance::STATUS_ABSENT,
            'overtime_status' => Attendance::OVERTIME_NONE,
        ]);
        Attendance::create([
            'employee_id' => $inactive->id,
            'attendance_date' => '2026-06-05',
            'status' => Attendance::STATUS_LATE,
            'check_in_at' => '2026-06-05 08:10:00',
            'overtime_status' => Attendance::OVERTIME_PENDING,
        ]);

        $this->getJson(route('admin.attendance.attendances.data', [
            'draw' => 1,
            'date_from' => '2026-06-05',
            'date_to' => '2026-06-05',
            'employee_id' => $active->id,
            'status' => Attendance::STATUS_LATE,
            'overtime_status' => Attendance::OVERTIME_PENDING,
            'employment_status' => Employee::STATUS_ACTIVE,
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.employee_id', $active->id)
            ->assertJsonPath('data.0.status', Attendance::STATUS_LATE)
            ->assertJsonPath('data.0.overtime_status', Attendance::OVERTIME_PENDING)
            ->assertJsonPath('summary.total', 1)
            ->assertJsonPath('summary.late', 1)
            ->assertJsonPath('summary.overtime_pending', 1);

        $this->getJson(route('admin.attendance.attendances.data', [
            'draw' => 1,
            'date_from' => '2026-06-05',
            'date_to' => '2026-06-05',
            'employment_status' => Employee::STATUS_INACTIVE,
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.employee_id', $inactive->id)
            ->assertJsonPath('summary.total', 1);
    }

    public function test_recap_off_summary_counts_day_off_holiday_and_leave(): void
    {
        Carbon::setTestNow('2026-06-05 10:00:00');
        $this->actingAsAdmin();

        foreach ([
            ['EMP-OFF-DAY', Attendance::STATUS_DAY_OFF],
            ['EMP-OFF-HOLIDAY', Attendance::STATUS_HOLIDAY],
            ['EMP-OFF-LEAVE', Attendance::STATUS_LEAVE],
        ] as [$code, $status]) {
            $employee = Employee::create([
                'employee_code' => $code,
                'name' => $code,
                'employment_status' => Employee::STATUS_ACTIVE,
            ]);
            Attendance::create([
                'employee_id' => $employee->id,
                'attendance_date' => '2026-06-05',
                'status' => $status,
            ]);
        }

        $this->getJson(route('admin.attendance.attendances.data', [
            'draw' => 1,
            'date_from' => '2026-06-05',
            'date_to' => '2026-06-05',
        ]))
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('summary.total', 3)
            ->assertJsonPath('summary.day_off', 3);
    }

    public function test_daily_monitor_includes_active_employee_without_schedule_or_recap(): void
    {
        Carbon::setTestNow('2026-06-05 10:00:00');
        $this->actingAsAdmin();

        $unscheduled = Employee::create([
            'employee_code' => 'EMP-NO-SCHEDULE',
            'name' => 'Karyawan Tanpa Jadwal',
            'employment_status' => Employee::STATUS_ACTIVE,
        ]);
        $futureEmployee = Employee::create([
            'employee_code' => 'EMP-FUTURE',
            'name' => 'Karyawan Belum Masuk',
            'join_date' => '2026-06-06',
            'employment_status' => Employee::STATUS_ACTIVE,
        ]);

        $response = $this->getJson(route('admin.attendance.absences.data', [
            'date' => '2026-06-05',
            'status' => 'unscheduled',
        ]));

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.employee_id', $unscheduled->id)
            ->assertJsonPath('data.0.status_key', 'unscheduled')
            ->assertJsonPath('summary.total', 1);

        $this->assertNotSame($futureEmployee->id, $response->json('data.0.employee_id'));
    }

    public function test_daily_monitor_derives_absent_after_shift_late_cutoff(): void
    {
        Carbon::setTestNow('2026-06-05 10:00:00');
        $this->actingAsAdmin();

        $employee = Employee::create([
            'employee_code' => 'EMP-ABSENT',
            'name' => 'Karyawan Alfa',
            'employment_status' => Employee::STATUS_ACTIVE,
        ]);
        $shift = WorkShift::create([
            'name' => 'Shift Pagi',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'late_tolerance_minutes' => 15,
        ]);
        EmployeeSchedule::create([
            'employee_id' => $employee->id,
            'work_shift_id' => $shift->id,
            'schedule_date' => '2026-06-05',
            'schedule_type' => EmployeeSchedule::TYPE_WORK,
        ]);

        $this->getJson(route('admin.attendance.absences.data', [
            'date' => '2026-06-05',
            'employee_id' => $employee->id,
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status_key', Attendance::STATUS_ABSENT)
            ->assertJsonPath('summary.absent_count', 1);
    }

    public function test_daily_monitor_uses_effective_holiday_without_existing_recap(): void
    {
        Carbon::setTestNow('2026-06-05 10:00:00');
        $this->actingAsAdmin();

        $employee = Employee::create([
            'employee_code' => 'EMP-MON-HOLIDAY',
            'name' => 'Karyawan Monitor Libur',
            'employment_status' => Employee::STATUS_ACTIVE,
        ]);
        $shift = WorkShift::create([
            'name' => 'Shift Monitor Holiday',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);
        EmployeeSchedule::create([
            'employee_id' => $employee->id,
            'work_shift_id' => $shift->id,
            'schedule_date' => '2026-06-17',
            'schedule_type' => EmployeeSchedule::TYPE_WORK,
        ]);
        Holiday::create([
            'holiday_date' => '2026-06-17',
            'name' => 'Libur Nasional',
            'type' => 'national',
            'is_paid' => true,
        ]);

        $this->getJson(route('admin.attendance.absences.data', [
            'date' => '2026-06-17',
            'employee_id' => $employee->id,
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status_key', Attendance::STATUS_HOLIDAY)
            ->assertJsonPath('data.0.schedule_type', EmployeeSchedule::TYPE_HOLIDAY)
            ->assertJsonPath('data.0.schedule_type_label', 'Libur Nasional')
            ->assertJsonPath('data.0.shift', '-')
            ->assertJsonPath('data.0.note', 'Libur Nasional')
            ->assertJsonPath('summary.off_count', 1);
    }

    public function test_daily_monitor_uses_effective_approved_leave_without_existing_recap(): void
    {
        Carbon::setTestNow('2026-06-05 10:00:00');
        $this->actingAsAdmin();

        $employee = Employee::create([
            'employee_code' => 'EMP-MON-LEAVE',
            'name' => 'Karyawan Monitor Cuti',
            'employment_status' => Employee::STATUS_ACTIVE,
        ]);
        $shift = WorkShift::create([
            'name' => 'Shift Monitor Leave',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);
        EmployeeSchedule::create([
            'employee_id' => $employee->id,
            'work_shift_id' => $shift->id,
            'schedule_date' => '2026-06-06',
            'schedule_type' => EmployeeSchedule::TYPE_WORK,
        ]);
        EmployeeLeave::create([
            'employee_id' => $employee->id,
            'leave_type' => 'izin',
            'start_date' => '2026-06-06',
            'end_date' => '2026-06-06',
            'status' => EmployeeLeave::STATUS_APPROVED,
        ]);

        $this->getJson(route('admin.attendance.absences.data', [
            'date' => '2026-06-06',
            'employee_id' => $employee->id,
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status_key', Attendance::STATUS_LEAVE)
            ->assertJsonPath('data.0.schedule_type', EmployeeSchedule::TYPE_LEAVE)
            ->assertJsonPath('data.0.shift', '-')
            ->assertJsonPath('data.0.note', 'Cuti/Izin: izin')
            ->assertJsonPath('summary.off_count', 1);
    }

    public function test_storing_holiday_rebuilds_active_employee_recap_as_holiday(): void
    {
        Carbon::setTestNow('2026-06-05 10:00:00');
        $this->actingAsAdmin();

        $employee = Employee::create([
            'employee_code' => 'EMP-HOLIDAY-RECAP',
            'name' => 'Karyawan Rekap Libur',
            'employment_status' => Employee::STATUS_ACTIVE,
        ]);
        $futureEmployee = Employee::create([
            'employee_code' => 'EMP-HOLIDAY-FUTURE',
            'name' => 'Karyawan Belum Join',
            'join_date' => '2026-06-18',
            'employment_status' => Employee::STATUS_ACTIVE,
        ]);
        $shift = WorkShift::create([
            'name' => 'Shift Holiday Recap',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);
        EmployeeSchedule::create([
            'employee_id' => $employee->id,
            'work_shift_id' => $shift->id,
            'schedule_date' => '2026-06-17',
            'schedule_type' => EmployeeSchedule::TYPE_WORK,
        ]);

        $this->postJson(route('admin.attendance.holidays.store'), [
            'holiday_date' => '2026-06-17',
            'name' => 'Libur Nasional',
            'type' => 'national',
            'is_paid' => true,
        ])
            ->assertOk()
            ->assertJsonPath('rebuilt_count', 1);

        $this->assertTrue(Holiday::query()
            ->whereDate('holiday_date', '2026-06-17')
            ->where('name', 'Libur Nasional')
            ->where('type', 'national')
            ->exists());
        $attendance = Attendance::query()
            ->where('employee_id', $employee->id)
            ->whereDate('attendance_date', '2026-06-17')
            ->firstOrFail();
        $this->assertSame(Attendance::STATUS_HOLIDAY, $attendance->status);
        $this->assertNull($attendance->work_shift_id);
        $this->assertSame('Libur Nasional', $attendance->note);
        $this->assertDatabaseMissing('attendances', [
            'employee_id' => $futureEmployee->id,
            'attendance_date' => '2026-06-17',
        ]);
    }

    public function test_inactive_employee_is_hidden_from_monitor_and_default_recap(): void
    {
        Carbon::setTestNow('2026-06-05 10:00:00');
        $this->actingAsAdmin();

        $inactive = Employee::create([
            'employee_code' => 'EMP-INACTIVE',
            'name' => 'Karyawan Nonaktif',
            'employment_status' => Employee::STATUS_INACTIVE,
        ]);
        $shift = WorkShift::create([
            'name' => 'Shift Nonaktif',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);
        EmployeeSchedule::create([
            'employee_id' => $inactive->id,
            'work_shift_id' => $shift->id,
            'schedule_date' => '2026-06-05',
            'schedule_type' => EmployeeSchedule::TYPE_WORK,
        ]);
        Attendance::create([
            'employee_id' => $inactive->id,
            'attendance_date' => '2026-06-05',
            'work_shift_id' => $shift->id,
            'status' => Attendance::STATUS_PRESENT,
            'check_in_at' => '2026-06-05 08:00:00',
        ]);

        $this->getJson(route('admin.attendance.absences.data', ['date' => '2026-06-05']))
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('summary.total', 0);

        $this->getJson(route('admin.attendance.attendances.data', ['draw' => 1]))
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('summary.total', 0);

        $this->getJson(route('admin.attendance.attendances.data', [
            'draw' => 1,
            'employment_status' => Employee::STATUS_INACTIVE,
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.employee_id', $inactive->id)
            ->assertJsonPath('data.0.employment_status', Employee::STATUS_INACTIVE);
    }

    public function test_inactive_employee_is_hidden_from_operational_schedule_and_live_summary(): void
    {
        Carbon::setTestNow('2026-06-05 10:00:00');
        $this->actingAsAdmin();

        $inactive = Employee::create([
            'employee_code' => 'EMP-INACTIVE-LIVE',
            'name' => 'Karyawan Nonaktif Live',
            'employment_status' => Employee::STATUS_INACTIVE,
        ]);
        $shift = WorkShift::create([
            'name' => 'Shift Inactive Live',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);
        EmployeeSchedule::create([
            'employee_id' => $inactive->id,
            'work_shift_id' => $shift->id,
            'schedule_date' => '2026-06-05',
            'schedule_type' => EmployeeSchedule::TYPE_WORK,
        ]);
        Attendance::create([
            'employee_id' => $inactive->id,
            'attendance_date' => '2026-06-05',
            'work_shift_id' => $shift->id,
            'status' => Attendance::STATUS_INCOMPLETE,
            'check_in_at' => '2026-06-05 08:00:00',
        ]);

        $this->getJson(route('admin.attendance.schedules.data', ['draw' => 1]))
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson(route('admin.attendance.live-display.feed', ['date' => '2026-06-05']))
            ->assertOk()
            ->assertJsonPath('summary.checked_in', 0)
            ->assertJsonPath('summary.incomplete', 0);
    }

    public function test_recap_and_daily_monitor_reject_unknown_status_filters(): void
    {
        $this->actingAsAdmin();

        $this->getJson(route('admin.attendance.attendances.data', ['status' => 'unknown']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->getJson(route('admin.attendance.absences.data', ['status' => 'unknown']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    private function actingAsAdmin(): void
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
        $user->roles()->attach($role);
        $this->actingAs($user);
    }
}
