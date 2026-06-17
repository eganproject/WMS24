<?php

namespace Tests\Feature\Admin;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\EmployeeScheduleAssignment;
use App\Models\Holiday;
use App\Models\Role;
use App\Models\User;
use App\Models\WeeklyScheduleTemplate;
use App\Models\WeeklyScheduleTemplateDay;
use App\Models\WorkShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AttendanceScheduleChangeRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_template_update_only_syncs_generated_schedules_from_today_forward(): void
    {
        Carbon::setTestNow('2026-06-05 10:00:00');
        $this->loginAsAdmin();

        $employee = $this->employee();
        $oldShift = $this->shift('Pagi', '08:00', '17:00');
        $newShift = $this->shift('Siang', '12:00', '20:00');
        $template = WeeklyScheduleTemplate::create(['name' => 'Operasional', 'is_active' => true]);

        foreach ([4, 5, 6] as $day) {
            WeeklyScheduleTemplateDay::create([
                'weekly_schedule_template_id' => $template->id,
                'day_of_week' => $day,
                'schedule_type' => 'work',
                'work_shift_id' => $oldShift->id,
            ]);
        }

        $assignment = EmployeeScheduleAssignment::create([
            'employee_id' => $employee->id,
            'weekly_schedule_template_id' => $template->id,
            'effective_from' => '2026-06-04',
            'effective_until' => '2026-06-06',
        ]);

        EmployeeSchedule::create([
            'employee_id' => $employee->id,
            'employee_schedule_assignment_id' => $assignment->id,
            'work_shift_id' => $oldShift->id,
            'schedule_date' => '2026-06-04',
            'schedule_type' => 'work',
            'note' => 'Dibuat dari template jadwal',
        ]);
        EmployeeSchedule::create([
            'employee_id' => $employee->id,
            'employee_schedule_assignment_id' => $assignment->id,
            'work_shift_id' => $oldShift->id,
            'schedule_date' => '2026-06-05',
            'schedule_type' => 'work',
            'note' => 'Dibuat dari template jadwal',
        ]);
        EmployeeSchedule::create([
            'employee_id' => $employee->id,
            'work_shift_id' => $oldShift->id,
            'schedule_date' => '2026-06-06',
            'schedule_type' => 'work',
            'note' => 'Jadwal manual',
        ]);

        $this->putJson(route('admin.attendance.templates.update', $template), [
            'name' => 'Operasional Baru',
            'is_active' => 1,
            'days' => [
                ['day_of_week' => 4, 'schedule_type' => 'work', 'work_shift_id' => $newShift->id],
                ['day_of_week' => 5, 'schedule_type' => 'work', 'work_shift_id' => $newShift->id],
                ['day_of_week' => 6, 'schedule_type' => 'work', 'work_shift_id' => $newShift->id],
            ],
        ])->assertOk();

        $pastSchedule = EmployeeSchedule::query()
            ->where('employee_id', $employee->id)
            ->whereDate('schedule_date', '2026-06-04')
            ->firstOrFail();
        $todaySchedule = EmployeeSchedule::query()
            ->where('employee_id', $employee->id)
            ->whereDate('schedule_date', '2026-06-05')
            ->firstOrFail();
        $manualSchedule = EmployeeSchedule::query()
            ->where('employee_id', $employee->id)
            ->whereDate('schedule_date', '2026-06-06')
            ->firstOrFail();

        $this->assertSame($oldShift->id, $pastSchedule->work_shift_id);
        $this->assertSame($newShift->id, $todaySchedule->work_shift_id);
        $this->assertSame($assignment->id, $todaySchedule->employee_schedule_assignment_id);
        $this->assertSame($oldShift->id, $manualSchedule->work_shift_id);
        $this->assertNull($manualSchedule->employee_schedule_assignment_id);
        $this->assertSame('Jadwal manual', $manualSchedule->note);
    }

    public function test_past_schedule_cannot_be_created_updated_or_deleted(): void
    {
        Carbon::setTestNow('2026-06-05 10:00:00');
        $this->loginAsAdmin();

        $employee = $this->employee();
        $shift = $this->shift('Pagi', '08:00', '17:00');
        $schedule = EmployeeSchedule::create([
            'employee_id' => $employee->id,
            'work_shift_id' => $shift->id,
            'schedule_date' => '2026-06-04',
            'schedule_type' => 'work',
        ]);

        $payload = [
            'employee_id' => $employee->id,
            'work_shift_id' => $shift->id,
            'schedule_date' => '2026-06-04',
            'schedule_type' => 'work',
        ];

        $this->postJson(route('admin.attendance.schedules.store'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('schedule_date');

        $this->putJson(route('admin.attendance.schedules.update', $schedule), [
            ...$payload,
            'schedule_date' => '2026-06-05',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('schedule_date');

        $this->deleteJson(route('admin.attendance.schedules.destroy', $schedule))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('schedule_date');
    }

    public function test_manual_edit_detaches_generated_schedule_from_assignment(): void
    {
        Carbon::setTestNow('2026-06-05 10:00:00');
        $this->loginAsAdmin();

        $employee = $this->employee();
        $shift = $this->shift('Pagi', '08:00', '17:00');
        $template = WeeklyScheduleTemplate::create(['name' => 'Operasional', 'is_active' => true]);
        $assignment = EmployeeScheduleAssignment::create([
            'employee_id' => $employee->id,
            'weekly_schedule_template_id' => $template->id,
            'effective_from' => '2026-06-05',
            'effective_until' => '2026-06-30',
        ]);
        $schedule = EmployeeSchedule::create([
            'employee_id' => $employee->id,
            'employee_schedule_assignment_id' => $assignment->id,
            'work_shift_id' => $shift->id,
            'schedule_date' => '2026-06-06',
            'schedule_type' => 'work',
            'note' => 'Dibuat dari template jadwal',
        ]);

        $this->putJson(route('admin.attendance.schedules.update', $schedule), [
            'employee_id' => $employee->id,
            'schedule_date' => '2026-06-06',
            'schedule_type' => 'day_off',
            'note' => 'Libur manual',
        ])->assertOk();

        $this->assertDatabaseHas('employee_schedules', [
            'id' => $schedule->id,
            'employee_schedule_assignment_id' => null,
            'work_shift_id' => null,
            'schedule_type' => 'day_off',
            'note' => 'Libur manual',
        ]);
    }

    public function test_schedule_list_supports_card_view_fields_and_type_filter(): void
    {
        Carbon::setTestNow('2026-06-05 10:00:00');
        $this->loginAsAdmin();

        $employee = $this->employee();
        $shift = $this->shift('Malam', '22:00', '06:00');
        $shift->update(['crosses_midnight' => true]);

        EmployeeSchedule::create([
            'employee_id' => $employee->id,
            'work_shift_id' => $shift->id,
            'schedule_date' => '2026-06-06',
            'schedule_type' => 'work',
        ]);
        EmployeeSchedule::create([
            'employee_id' => $employee->id,
            'schedule_date' => '2026-06-07',
            'schedule_type' => 'day_off',
        ]);

        $this->getJson(route('admin.attendance.schedules.data', [
            'draw' => 1,
            'length' => 9,
            'schedule_type' => 'work',
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.shift', 'Malam')
            ->assertJsonPath('data.0.shift_start_time', '22:00')
            ->assertJsonPath('data.0.shift_end_time', '06:00')
            ->assertJsonPath('data.0.crosses_midnight', true);
    }

    public function test_schedule_calendar_does_not_mix_attendance_status_with_employee_schedule(): void
    {
        Carbon::setTestNow('2026-06-05 10:00:00');
        $this->loginAsAdmin();

        $employee = $this->employee();
        $shift = $this->shift('Pagi', '08:00', '17:00');

        EmployeeSchedule::create([
            'employee_id' => $employee->id,
            'work_shift_id' => $shift->id,
            'schedule_date' => '2026-06-06',
            'schedule_type' => 'work',
        ]);
        Attendance::create([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-06-06',
            'status' => Attendance::STATUS_DAY_OFF,
            'source' => 'generated',
        ]);

        $response = $this->getJson(route('admin.attendance.schedules.calendar-events', [
            'start' => '2026-06-01',
            'end' => '2026-07-01',
            'employee_id' => $employee->id,
        ]))->assertOk();

        $this->assertSame(['1 Jadwal Masuk'], collect($response->json())->pluck('title')->all());
    }

    public function test_employee_schedule_page_data_exposes_effective_holiday_overlay(): void
    {
        Carbon::setTestNow('2026-06-05 10:00:00');
        $this->loginAsAdmin();

        $employee = $this->employee();
        $shift = $this->shift('Pagi', '08:00', '17:00');

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

        $this->getJson(route('admin.attendance.schedules.data', [
            'draw' => 1,
            'length' => 9,
            'employee_id' => $employee->id,
            'date_from' => '2026-06-01',
            'date_to' => '2026-06-30',
        ]))
            ->assertOk()
            ->assertJsonPath('data.0.schedule_type', EmployeeSchedule::TYPE_WORK)
            ->assertJsonPath('data.0.effective_schedule_type', EmployeeSchedule::TYPE_HOLIDAY)
            ->assertJsonPath('data.0.effective_schedule_type_label', 'Libur Nasional')
            ->assertJsonPath('data.0.is_effective_override', true)
            ->assertJsonPath('data.0.effective_shift', '-')
            ->assertJsonPath('data.0.effective_note', 'Libur Nasional');
    }

    public function test_schedule_calendar_includes_global_holiday_overlay(): void
    {
        Carbon::setTestNow('2026-06-05 10:00:00');
        $this->loginAsAdmin();

        $employee = $this->employee();
        $shift = $this->shift('Pagi', '08:00', '17:00');

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

        $titles = collect($this->getJson(route('admin.attendance.schedules.calendar-events', [
            'start' => '2026-06-01',
            'end' => '2026-07-01',
            'employee_id' => $employee->id,
        ]))->assertOk()->json())->pluck('title')->all();

        $this->assertContains('1 Jadwal Masuk', $titles);
        $this->assertContains('1 Libur Nasional', $titles);
    }

    private function employee(): Employee
    {
        return Employee::create([
            'employee_code' => 'EMP-RULE',
            'name' => 'Karyawan Jadwal',
            'employment_status' => 'active',
        ]);
    }

    private function loginAsAdmin(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        $user->roles()->attach($role);
        $this->actingAs($user);
    }

    private function shift(string $name, string $start, string $end): WorkShift
    {
        return WorkShift::create([
            'name' => $name,
            'start_time' => $start,
            'end_time' => $end,
            'is_active' => true,
        ]);
    }
}
