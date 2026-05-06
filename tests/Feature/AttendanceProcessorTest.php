<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceDevice;
use App\Models\AttendanceRawLog;
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

    public function test_adms_attlog_acknowledges_machine_push(): void
    {
        $employee = Employee::create([
            'employee_code' => 'EMP006',
            'name' => 'Joko',
            'employment_status' => 'active',
        ]);
        $device = AttendanceDevice::create([
            'name' => 'Solution X100C',
            'serial_number' => 'X100C001',
            'port' => 4370,
            'is_active' => true,
        ]);
        EmployeeFingerprint::create([
            'employee_id' => $employee->id,
            'attendance_device_id' => $device->id,
            'device_user_id' => '6001',
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
        ]);

        $response = $this->call(
            'POST',
            '/iclock/cdata?SN=X100C001&table=ATTLOG',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'text/plain'],
            "6001\t2026-04-27 08:00:00\t0\t1\t0\t0\r\n"
        );

        $response->assertOk();
        $this->assertSame("OK: 1\r\n", $response->getContent());
        $this->assertDatabaseHas('attendance_raw_logs', [
            'attendance_device_id' => $device->id,
            'device_user_id' => '6001',
            'scan_at' => '2026-04-27 08:00:00',
        ]);
    }

    public function test_replayed_raw_log_rebuilds_attendance_after_employee_mapping_is_added(): void
    {
        $employee = Employee::create([
            'employee_code' => 'EMP007',
            'name' => 'Tono',
            'employment_status' => 'active',
        ]);
        $device = AttendanceDevice::create([
            'name' => 'Fingerprint Belakang',
            'serial_number' => 'SN007',
            'port' => 4370,
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
        ]);

        AttendanceRawLog::create([
            'attendance_device_id' => $device->id,
            'device_user_id' => '7001',
            'scan_at' => '2026-04-27 08:00:00',
            'synced_at' => now(),
        ]);
        EmployeeFingerprint::create([
            'employee_id' => $employee->id,
            'attendance_device_id' => $device->id,
            'device_user_id' => '7001',
            'is_active' => true,
        ]);

        $result = app(AttendanceProcessor::class)
            ->recordFingerprintScanWithResult($device, '7001', '2026-04-27 08:00:00');

        $this->assertSame($employee->id, $result['raw_log']->employee_id);
        $this->assertNotNull($result['attendance']);
        $this->assertTrue(Attendance::query()
            ->where('employee_id', $employee->id)
            ->whereDate('attendance_date', '2026-04-27')
            ->where('source', 'fingerprint')
            ->exists());
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

    public function test_attendance_operational_sections_are_available_as_separate_pages(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        $user->roles()->attach($role);
        $this->actingAs($user);

        $this->get(route('admin.attendance.index'))
            ->assertRedirect(route('admin.attendance.employees.index'));

        foreach ([
            'admin.attendance.employees.index',
            'admin.attendance.devices.index',
            'admin.attendance.fingerprints.index',
            'admin.attendance.shifts.index',
            'admin.attendance.schedules.index',
            'admin.attendance.holidays.index',
            'admin.attendance.templates.index',
            'admin.attendance.leaves.index',
            'admin.attendance.raw-logs.index',
            'admin.attendance.attendances.index',
        ] as $route) {
            $this->get(route($route))
                ->assertOk()
                ->assertSee('Modul Absensi');
        }
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

    public function test_work_minutes_only_subtracts_break_overlap(): void
    {
        $employee = Employee::create([
            'employee_code' => 'EMP007',
            'name' => 'Nina',
            'employment_status' => 'active',
        ]);
        $device = AttendanceDevice::create(['name' => 'Fingerprint Gudang 2', 'port' => 4370, 'is_active' => true]);
        EmployeeFingerprint::create([
            'employee_id' => $employee->id,
            'attendance_device_id' => $device->id,
            'device_user_id' => '7001',
            'is_active' => true,
        ]);
        $shift = WorkShift::create([
            'name' => 'Pagi Dengan Istirahat',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'break_start_time' => '13:00',
            'break_end_time' => '14:00',
            'is_active' => true,
        ]);
        EmployeeSchedule::create([
            'employee_id' => $employee->id,
            'work_shift_id' => $shift->id,
            'schedule_date' => '2026-04-27',
            'schedule_type' => 'work',
        ]);

        $processor = app(AttendanceProcessor::class);
        $processor->recordFingerprintScan($device, '7001', '2026-04-27 08:00:00');
        $processor->recordFingerprintScan($device, '7001', '2026-04-27 12:00:00');

        $attendance = Attendance::where('employee_id', $employee->id)->firstOrFail();

        $this->assertSame(240, $attendance->work_minutes);
    }

    public function test_cross_midnight_shift_subtracts_next_day_break(): void
    {
        $employee = Employee::create([
            'employee_code' => 'EMP008',
            'name' => 'Rudi',
            'employment_status' => 'active',
        ]);
        $device = AttendanceDevice::create(['name' => 'Fingerprint Malam', 'port' => 4370, 'is_active' => true]);
        EmployeeFingerprint::create([
            'employee_id' => $employee->id,
            'attendance_device_id' => $device->id,
            'device_user_id' => '8001',
            'is_active' => true,
        ]);
        $shift = WorkShift::create([
            'name' => 'Malam',
            'start_time' => '22:00',
            'end_time' => '06:00',
            'break_start_time' => '02:00',
            'break_end_time' => '03:00',
            'crosses_midnight' => true,
            'is_active' => true,
        ]);
        EmployeeSchedule::create([
            'employee_id' => $employee->id,
            'work_shift_id' => $shift->id,
            'schedule_date' => '2026-04-27',
            'schedule_type' => 'work',
        ]);

        $processor = app(AttendanceProcessor::class);
        $processor->recordFingerprintScan($device, '8001', '2026-04-27 22:00:00');
        $processor->recordFingerprintScan($device, '8001', '2026-04-28 06:00:00');

        $attendance = Attendance::where('employee_id', $employee->id)->firstOrFail();

        $this->assertSame(420, $attendance->work_minutes);
    }

    public function test_work_schedule_requires_shift(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        $user->roles()->attach($role);
        $this->actingAs($user);

        $employee = Employee::create([
            'employee_code' => 'EMP009',
            'name' => 'Tono',
            'employment_status' => 'active',
        ]);

        $this->postJson(route('admin.attendance.schedules.store'), [
            'employee_id' => $employee->id,
            'schedule_date' => '2026-04-27',
            'schedule_type' => 'work',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['work_shift_id']);
    }

    public function test_work_template_day_requires_shift(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        $user->roles()->attach($role);
        $this->actingAs($user);

        $this->postJson(route('admin.attendance.templates.store'), [
            'name' => 'Template Invalid',
            'is_active' => 1,
            'days' => [
                [
                    'day_of_week' => 1,
                    'schedule_type' => 'work',
                ],
            ],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['days']);
    }

    public function test_raw_log_update_uses_global_fingerprint_mapping_and_rejects_duplicates(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        $user->roles()->attach($role);
        $this->actingAs($user);

        $employee = Employee::create([
            'employee_code' => 'EMP010',
            'name' => 'Dewi',
            'employment_status' => 'active',
        ]);
        $device = AttendanceDevice::create(['name' => 'Fingerprint Update', 'port' => 4370, 'is_active' => true]);
        EmployeeFingerprint::create([
            'employee_id' => $employee->id,
            'attendance_device_id' => null,
            'device_user_id' => '10001',
            'is_active' => true,
        ]);
        $rawLog = AttendanceRawLog::create([
            'attendance_device_id' => $device->id,
            'device_user_id' => 'OLD',
            'scan_at' => '2026-04-27 08:00:00',
        ]);
        AttendanceRawLog::create([
            'attendance_device_id' => $device->id,
            'device_user_id' => '10001',
            'scan_at' => '2026-04-27 09:00:00',
        ]);

        $this->putJson(route('admin.attendance.raw-logs.update', $rawLog), [
            'attendance_device_id' => $device->id,
            'device_user_id' => '10001',
            'scan_at' => '2026-04-27 08:00:00',
            'verify_type' => 'fingerprint',
        ])->assertOk();

        $this->assertDatabaseHas('attendance_raw_logs', [
            'id' => $rawLog->id,
            'employee_id' => $employee->id,
            'device_user_id' => '10001',
        ]);

        $this->putJson(route('admin.attendance.raw-logs.update', $rawLog), [
            'attendance_device_id' => $device->id,
            'device_user_id' => '10001',
            'scan_at' => '2026-04-27 09:00:00',
            'verify_type' => 'fingerprint',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['scan_at']);
    }

    public function test_overtime_calculation_respects_start_after_and_minimum_minutes(): void
    {
        $device = AttendanceDevice::create(['name' => 'Fingerprint Lembur', 'port' => 4370, 'is_active' => true]);
        $shift = WorkShift::create([
            'name' => 'Pagi Lembur',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'overtime_start_after_minutes' => 30,
            'minimum_overtime_minutes' => 45,
            'is_active' => true,
        ]);
        $processor = app(AttendanceProcessor::class);

        $employeeBelowMinimum = Employee::create([
            'employee_code' => 'EMP011',
            'name' => 'Bima',
            'employment_status' => 'active',
        ]);
        EmployeeFingerprint::create([
            'employee_id' => $employeeBelowMinimum->id,
            'attendance_device_id' => $device->id,
            'device_user_id' => '11001',
            'is_active' => true,
        ]);
        EmployeeSchedule::create([
            'employee_id' => $employeeBelowMinimum->id,
            'work_shift_id' => $shift->id,
            'schedule_date' => '2026-04-27',
            'schedule_type' => 'work',
        ]);
        $processor->recordFingerprintScan($device, '11001', '2026-04-27 08:00:00');
        $processor->recordFingerprintScan($device, '11001', '2026-04-27 17:50:00');

        $belowMinimumAttendance = Attendance::where('employee_id', $employeeBelowMinimum->id)->firstOrFail();
        $this->assertSame(0, $belowMinimumAttendance->calculated_overtime_minutes);
        $this->assertSame('none', $belowMinimumAttendance->overtime_status);

        $employeePending = Employee::create([
            'employee_code' => 'EMP012',
            'name' => 'Lina',
            'employment_status' => 'active',
        ]);
        EmployeeFingerprint::create([
            'employee_id' => $employeePending->id,
            'attendance_device_id' => $device->id,
            'device_user_id' => '12001',
            'is_active' => true,
        ]);
        EmployeeSchedule::create([
            'employee_id' => $employeePending->id,
            'work_shift_id' => $shift->id,
            'schedule_date' => '2026-04-27',
            'schedule_type' => 'work',
        ]);
        $processor->recordFingerprintScan($device, '12001', '2026-04-27 08:00:00');
        $processor->recordFingerprintScan($device, '12001', '2026-04-27 18:20:00');

        $pendingAttendance = Attendance::where('employee_id', $employeePending->id)->firstOrFail();
        $this->assertSame(50, $pendingAttendance->calculated_overtime_minutes);
        $this->assertNull($pendingAttendance->approved_overtime_minutes);
        $this->assertSame(0, $pendingAttendance->overtime_minutes);
        $this->assertSame('pending', $pendingAttendance->overtime_status);
    }

    public function test_attendance_update_approves_overtime_as_final_minutes(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        $user->roles()->attach($role);
        $this->actingAs($user);

        $employee = Employee::create([
            'employee_code' => 'EMP013',
            'name' => 'Sari',
            'employment_status' => 'active',
        ]);
        $shift = WorkShift::create([
            'name' => 'Pagi Approval',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'is_active' => true,
        ]);
        $attendance = Attendance::create([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-04-27',
            'work_shift_id' => $shift->id,
            'check_in_at' => '2026-04-27 08:00:00',
            'check_out_at' => '2026-04-27 18:00:00',
            'work_minutes' => 600,
            'calculated_overtime_minutes' => 60,
            'overtime_status' => 'pending',
            'status' => 'present',
            'source' => 'fingerprint',
        ]);

        $this->putJson(route('admin.attendance.attendances.update', $attendance), [
            'employee_id' => $employee->id,
            'attendance_date' => '2026-04-27',
            'work_shift_id' => $shift->id,
            'check_in_at' => '2026-04-27 08:00:00',
            'check_out_at' => '2026-04-27 18:00:00',
            'late_minutes' => 0,
            'early_leave_minutes' => 0,
            'work_minutes' => 600,
            'calculated_overtime_minutes' => 60,
            'approved_overtime_minutes' => 45,
            'overtime_status' => 'approved',
            'overtime_note' => 'Disetujui sesuai surat lembur',
            'status' => 'present',
            'source' => 'manual',
            'note' => 'Koreksi lembur',
        ])->assertOk();

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'calculated_overtime_minutes' => 60,
            'approved_overtime_minutes' => 45,
            'overtime_minutes' => 45,
            'overtime_status' => 'approved',
            'approved_by' => $user->id,
        ]);
    }

    public function test_attendance_report_returns_hr_summary_per_employee(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        $user->roles()->attach($role);
        $this->actingAs($user);

        $employee = Employee::create([
            'employee_code' => 'EMP014',
            'name' => 'Maya',
            'employment_status' => 'active',
        ]);
        $shift = WorkShift::create([
            'name' => 'Pagi Report',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'is_active' => true,
        ]);

        foreach (['2026-04-27', '2026-04-28', '2026-04-29'] as $date) {
            EmployeeSchedule::create([
                'employee_id' => $employee->id,
                'work_shift_id' => $shift->id,
                'schedule_date' => $date,
                'schedule_type' => 'work',
            ]);
        }

        Attendance::create([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-04-27',
            'work_shift_id' => $shift->id,
            'check_in_at' => '2026-04-27 08:00:00',
            'check_out_at' => '2026-04-27 18:00:00',
            'work_minutes' => 600,
            'calculated_overtime_minutes' => 60,
            'approved_overtime_minutes' => 45,
            'overtime_minutes' => 45,
            'overtime_status' => 'approved',
            'status' => 'present',
            'source' => 'fingerprint',
        ]);
        Attendance::create([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-04-28',
            'work_shift_id' => $shift->id,
            'check_in_at' => '2026-04-28 08:20:00',
            'check_out_at' => '2026-04-28 17:00:00',
            'late_minutes' => 20,
            'work_minutes' => 520,
            'status' => 'late',
            'source' => 'fingerprint',
        ]);
        Attendance::create([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-04-29',
            'work_shift_id' => $shift->id,
            'status' => 'absent',
            'source' => 'system',
        ]);

        $response = $this->getJson(route('admin.reports.attendance.data', [
            'draw' => 1,
            'date_from' => '2026-04-27',
            'date_to' => '2026-04-29',
            'employee_id' => $employee->id,
        ]));

        $response->assertOk()
            ->assertJsonPath('summary.employees', 1)
            ->assertJsonPath('summary.scheduled_work_days', 3)
            ->assertJsonPath('summary.present_days', 1)
            ->assertJsonPath('summary.late_days', 1)
            ->assertJsonPath('summary.absent_days', 1)
            ->assertJsonPath('summary.approved_overtime_minutes', 45)
            ->assertJsonPath('data.0.employee_label', 'EMP014 - Maya')
            ->assertJsonPath('data.0.scheduled_work_days', 3)
            ->assertJsonPath('data.0.attendance_rate', 66.67)
            ->assertJsonPath('data.0.approved_overtime_minutes', 45);

        $this->assertCount(3, $response->json('data.0.detail_rows'));
    }
}
