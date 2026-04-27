<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceDevice;
use App\Models\Employee;
use App\Models\EmployeeFingerprint;
use App\Models\EmployeeLeave;
use App\Models\EmployeePosition;
use App\Models\Role;
use App\Models\EmployeeSchedule;
use App\Models\User;
use App\Models\WeeklyScheduleTemplate;
use App\Models\WeeklyScheduleTemplateDay;
use App\Models\WorkShift;
use App\Support\AttendanceProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AttendanceProcessorTest extends TestCase
{
    use RefreshDatabase;

    public function test_fingerprint_scans_create_daily_attendance_summary(): void
    {
        $employee = Employee::create([
            'employee_code' => 'EMP001',
            'name' => 'Budi',
            'employment_status' => 'active',
        ]);
        $device = AttendanceDevice::create([
            'name' => 'Fingerprint Gudang',
            'port' => 4370,
            'is_active' => true,
        ]);
        EmployeeFingerprint::create([
            'employee_id' => $employee->id,
            'attendance_device_id' => $device->id,
            'device_user_id' => '1001',
            'is_active' => true,
        ]);
        $shift = WorkShift::create([
            'name' => 'Pagi',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'late_tolerance_minutes' => 5,
            'checkout_tolerance_minutes' => 0,
            'is_active' => true,
        ]);
        EmployeeSchedule::create([
            'employee_id' => $employee->id,
            'work_shift_id' => $shift->id,
            'schedule_date' => '2026-04-27',
            'schedule_type' => 'work',
        ]);

        $processor = app(AttendanceProcessor::class);
        $processor->recordFingerprintScan($device, '1001', '2026-04-27 08:10:00');
        $processor->recordFingerprintScan($device, '1001', '2026-04-27 17:15:00');

        $attendance = Attendance::query()
            ->where('employee_id', $employee->id)
            ->whereDate('attendance_date', '2026-04-27')
            ->firstOrFail();

        $this->assertSame('late', $attendance->status);
        $this->assertSame(5, $attendance->late_minutes);
        $this->assertSame(0, $attendance->early_leave_minutes);
        $this->assertSame('fingerprint', $attendance->source);
        $this->assertEquals('2026-04-27 08:10:00', $attendance->check_in_at->format('Y-m-d H:i:s'));
        $this->assertEquals('2026-04-27 17:15:00', $attendance->check_out_at->format('Y-m-d H:i:s'));
    }

    public function test_fingerprint_webhook_records_scan_with_secret(): void
    {
        config(['services.attendance.webhook_secret' => 'attendance-secret']);

        $employee = Employee::create([
            'employee_code' => 'EMP002',
            'name' => 'Siti',
            'employment_status' => 'active',
        ]);
        $device = AttendanceDevice::create([
            'name' => 'Fingerprint Pintu Utama',
            'serial_number' => 'SN001',
            'port' => 4370,
            'is_active' => true,
        ]);
        EmployeeFingerprint::create([
            'employee_id' => $employee->id,
            'attendance_device_id' => $device->id,
            'device_user_id' => '2001',
            'is_active' => true,
        ]);

        $response = $this
            ->withHeader('X-Attendance-Webhook-Secret', 'attendance-secret')
            ->postJson(route('attendance.fingerprint.webhook'), [
                'serial_number' => 'SN001',
                'device_user_id' => '2001',
                'scan_at' => '2026-04-27 08:00:00',
                'verify_type' => 'fingerprint',
            ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('employee_id', $employee->id);
    }

    public function test_attendance_datatable_endpoints_return_json(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        $user->roles()->attach($role);
        $this->actingAs($user);

        $position = EmployeePosition::create(['name' => 'Picker', 'is_active' => true]);
        $employee = Employee::create([
            'employee_code' => 'EMP003',
            'name' => 'Rina',
            'position_id' => $position->id,
            'employment_status' => 'active',
        ]);
        $device = AttendanceDevice::create(['name' => 'Fingerprint Test', 'port' => 4370, 'is_active' => true]);
        EmployeeFingerprint::create([
            'employee_id' => $employee->id,
            'attendance_device_id' => $device->id,
            'device_user_id' => '3001',
            'is_active' => true,
        ]);
        $shift = WorkShift::create([
            'name' => 'Pagi',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'is_active' => true,
        ]);
        EmployeeSchedule::create([
            'employee_id' => $employee->id,
            'work_shift_id' => $shift->id,
            'schedule_date' => '2026-04-27',
            'schedule_type' => 'work',
            'created_by' => $user->id,
        ]);
        $secondEmployee = Employee::create([
            'employee_code' => 'EMP005',
            'name' => 'Doni',
            'employment_status' => 'active',
        ]);
        EmployeeSchedule::create([
            'employee_id' => $secondEmployee->id,
            'work_shift_id' => $shift->id,
            'schedule_date' => '2026-04-27',
            'schedule_type' => 'work',
            'created_by' => $user->id,
        ]);
        EmployeeLeave::create([
            'employee_id' => $employee->id,
            'leave_type' => 'permission',
            'start_date' => '2026-04-28',
            'end_date' => '2026-04-28',
            'status' => 'approved',
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        $routes = [
            'admin.attendance.employees.data',
            'admin.attendance.positions.data',
            'admin.attendance.devices.data',
            'admin.attendance.fingerprints.data',
            'admin.attendance.shifts.data',
            'admin.attendance.schedules.data',
            'admin.attendance.leaves.data',
            'admin.attendance.raw-logs.data',
            'admin.attendance.attendances.data',
        ];

        foreach ($routes as $route) {
            $this->getJson(route($route, ['draw' => 1]))
                ->assertOk()
                ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
        }

        $calendarResponse = $this->getJson(route('admin.attendance.schedules.calendar-events', [
            'start' => '2026-04-01',
            'end' => '2026-04-30',
        ]))
            ->assertOk()
            ->assertJsonFragment([
                'title' => '2 Jadwal Masuk',
                'start' => '2026-04-27',
            ]);

        $scheduleSummary = collect($calendarResponse->json())
            ->firstWhere('title', '2 Jadwal Masuk');

        $this->assertSame(2, $scheduleSummary['extendedProps']['count']);
        $this->assertCount(2, $scheduleSummary['extendedProps']['details']);
    }

    public function test_manual_raw_log_late_check_in_returns_notification_and_sends_telegram(): void
    {
        config([
            'services.telegram.bot_token' => 'TEST_TOKEN',
            'services.telegram.allowed_chat_ids' => ['12345'],
        ]);
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $user = User::factory()->create();
        $role = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        $user->roles()->attach($role);
        $this->actingAs($user);

        $employee = Employee::create([
            'employee_code' => 'EMP004',
            'name' => 'Andi',
            'employment_status' => 'active',
        ]);
        $device = AttendanceDevice::create(['name' => 'Fingerprint Lobby', 'port' => 4370, 'is_active' => true]);
        EmployeeFingerprint::create([
            'employee_id' => $employee->id,
            'attendance_device_id' => $device->id,
            'device_user_id' => '4001',
            'is_active' => true,
        ]);
        $shift = WorkShift::create([
            'name' => 'Pagi',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'late_tolerance_minutes' => 5,
            'is_active' => true,
        ]);
        EmployeeSchedule::create([
            'employee_id' => $employee->id,
            'work_shift_id' => $shift->id,
            'schedule_date' => '2026-04-27',
            'schedule_type' => 'work',
            'created_by' => $user->id,
        ]);

        $response = $this->postJson(route('admin.attendance.raw-logs.store'), [
            'attendance_device_id' => $device->id,
            'device_user_id' => '4001',
            'scan_at' => '2026-04-27 08:12:00',
            'verify_type' => 'fingerprint',
        ]);

        $response->assertOk()
            ->assertJsonPath('attendance.employee', 'EMP004 - Andi')
            ->assertJsonPath('attendance.late_minutes', 7)
            ->assertJsonPath('notification.late_check_in', true);

        Http::assertSent(function ($request) {
            $payload = $request->data();

            return $request->url() === 'https://api.telegram.org/botTEST_TOKEN/sendMessage'
                && $payload['chat_id'] === '12345'
                && str_contains($payload['text'], 'Notifikasi Absensi Terlambat')
                && str_contains($payload['text'], 'EMP004 - Andi')
                && str_contains($payload['text'], 'Terlambat: 7 menit');
        });
    }

    public function test_schedule_template_can_use_weekend_work_shifts(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        $user->roles()->attach($role);
        $this->actingAs($user);

        $shift = WorkShift::create([
            'name' => 'Pagi',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'is_active' => true,
        ]);

        $days = collect(range(1, 7))->map(fn (int $day) => [
            'day_of_week' => $day,
            'schedule_type' => 'work',
            'work_shift_id' => $shift->id,
        ])->all();

        $this->postJson(route('admin.attendance.templates.store'), [
            'name' => 'Template Weekend Masuk',
            'is_active' => 1,
            'days' => $days,
        ])->assertOk();

        $this->assertDatabaseHas('weekly_schedule_template_days', [
            'day_of_week' => 6,
            'schedule_type' => 'work',
            'work_shift_id' => $shift->id,
        ]);
        $this->assertDatabaseHas('weekly_schedule_template_days', [
            'day_of_week' => 7,
            'schedule_type' => 'work',
            'work_shift_id' => $shift->id,
        ]);
    }

    public function test_assigning_schedule_template_generates_visible_employee_schedules(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        $user->roles()->attach($role);
        $this->actingAs($user);

        $employee = Employee::create([
            'employee_code' => 'EMP006',
            'name' => 'Wawan',
            'employment_status' => 'active',
        ]);
        $shift = WorkShift::create([
            'name' => 'Pagi',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'is_active' => true,
        ]);
        $template = WeeklyScheduleTemplate::create([
            'name' => 'Template 3 Hari',
            'is_active' => true,
        ]);
        WeeklyScheduleTemplateDay::create([
            'weekly_schedule_template_id' => $template->id,
            'day_of_week' => 1,
            'schedule_type' => 'work',
            'work_shift_id' => $shift->id,
        ]);
        WeeklyScheduleTemplateDay::create([
            'weekly_schedule_template_id' => $template->id,
            'day_of_week' => 2,
            'schedule_type' => 'day_off',
        ]);

        $this->postJson(route('admin.attendance.templates.assign'), [
            'employee_id' => $employee->id,
            'weekly_schedule_template_id' => $template->id,
            'effective_from' => '2026-04-27',
            'effective_until' => '2026-04-28',
        ])
            ->assertOk()
            ->assertJsonPath('generated_until', '2026-04-28');

        $this->assertTrue(EmployeeSchedule::query()
            ->where('employee_id', $employee->id)
            ->whereDate('schedule_date', '2026-04-27')
            ->where('schedule_type', 'work')
            ->where('work_shift_id', $shift->id)
            ->exists());
        $this->assertTrue(EmployeeSchedule::query()
            ->where('employee_id', $employee->id)
            ->whereDate('schedule_date', '2026-04-28')
            ->where('schedule_type', 'day_off')
            ->whereNull('work_shift_id')
            ->exists());

        $this->getJson(route('admin.attendance.schedules.data', ['draw' => 1]))
            ->assertOk()
            ->assertJsonFragment([
                'employee' => 'EMP006 - Wawan',
                'schedule_date' => '2026-04-27',
            ]);
    }
}
