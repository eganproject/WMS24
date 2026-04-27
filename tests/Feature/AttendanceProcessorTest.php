<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceDevice;
use App\Models\Employee;
use App\Models\EmployeeFingerprint;
use App\Models\EmployeeSchedule;
use App\Models\WorkShift;
use App\Support\AttendanceProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
