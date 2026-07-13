<?php

namespace Tests\Feature\Admin;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceOvertimeBulkTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Employee $employee;
    private WorkShift $shift;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['is_active' => true]);
        $role = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        $this->user->roles()->attach($role);
        $this->actingAs($this->user);

        $this->employee = Employee::create([
            'employee_code' => 'EMP-BULK-OT',
            'name' => 'Karyawan Bulk Lembur',
            'employment_status' => 'active',
        ]);
        $this->shift = WorkShift::create([
            'name' => 'Shift Bulk Lembur',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'is_active' => true,
        ]);
    }

    public function test_bulk_approval_saves_each_confirmed_minute_atomically(): void
    {
        $first = $this->pendingAttendance('2026-07-10', 90);
        $second = $this->pendingAttendance('2026-07-11', 60);

        $this->postJson(route('admin.attendance.overtime.bulk-approve'), [
            'items' => [
                ['id' => $first->id, 'approved_overtime_minutes' => 75, 'overtime_note' => 'Approval pertama'],
                ['id' => $second->id, 'approved_overtime_minutes' => 45, 'overtime_note' => 'Approval kedua'],
            ],
        ])->assertOk()->assertJsonPath('message', '2 data lembur berhasil di-approve.');

        $this->assertDatabaseHas('attendances', [
            'id' => $first->id,
            'overtime_minutes' => 75,
            'approved_overtime_minutes' => 75,
            'overtime_status' => Attendance::OVERTIME_APPROVED,
            'overtime_note' => 'Approval pertama',
            'approved_by' => $this->user->id,
        ]);
        $this->assertDatabaseHas('attendances', [
            'id' => $second->id,
            'overtime_minutes' => 45,
            'approved_overtime_minutes' => 45,
            'overtime_status' => Attendance::OVERTIME_APPROVED,
            'overtime_note' => 'Approval kedua',
            'approved_by' => $this->user->id,
        ]);
    }

    public function test_overtime_monitor_exposes_bulk_controls_and_confirmation_details(): void
    {
        $attendance = $this->pendingAttendance('2026-07-10', 90);

        $this->get(route('admin.attendance.overtime.index'))
            ->assertOk()
            ->assertSee('btn_bulk_approve', false)
            ->assertSee('btn_bulk_reject', false)
            ->assertSee('Jam Masuk')
            ->assertSee('Jam Keluar');

        $this->getJson(route('admin.attendance.overtime.data', [
            'date_from' => '2026-07-10',
            'date_to' => '2026-07-10',
            'overtime_status' => Attendance::OVERTIME_PENDING,
        ]))->assertOk()
            ->assertJsonPath('data.0.id', $attendance->id)
            ->assertJsonPath('data.0.shift_start_time', '08:00')
            ->assertJsonPath('data.0.shift_end_time', '17:00');
    }

    public function test_bulk_approval_rolls_back_when_one_item_is_no_longer_pending(): void
    {
        $pending = $this->pendingAttendance('2026-07-10', 90);
        $changed = $this->pendingAttendance('2026-07-11', 60);
        $changed->update(['overtime_status' => Attendance::OVERTIME_REJECTED]);

        $this->postJson(route('admin.attendance.overtime.bulk-approve'), [
            'items' => [
                ['id' => $pending->id, 'approved_overtime_minutes' => 75],
                ['id' => $changed->id, 'approved_overtime_minutes' => 45],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors('items');

        $this->assertDatabaseHas('attendances', [
            'id' => $pending->id,
            'overtime_status' => Attendance::OVERTIME_PENDING,
            'approved_overtime_minutes' => null,
        ]);
    }

    public function test_bulk_rejection_requires_one_reason_and_keeps_minutes_consistent(): void
    {
        $first = $this->pendingAttendance('2026-07-10', 90);
        $second = $this->pendingAttendance('2026-07-11', 60);

        $this->postJson(route('admin.attendance.overtime.bulk-reject'), [
            'ids' => [$first->id, $second->id],
            'overtime_note' => 'Tidak ada surat perintah lembur',
        ])->assertOk()->assertJsonPath('message', '2 data lembur berhasil di-reject.');

        foreach ([$first, $second] as $attendance) {
            $this->assertDatabaseHas('attendances', [
                'id' => $attendance->id,
                'overtime_minutes' => 0,
                'approved_overtime_minutes' => 0,
                'overtime_status' => Attendance::OVERTIME_REJECTED,
                'overtime_note' => 'Tidak ada surat perintah lembur',
                'approved_by' => $this->user->id,
            ]);
        }
    }

    private function pendingAttendance(string $date, int $minutes): Attendance
    {
        return Attendance::create([
            'employee_id' => $this->employee->id,
            'attendance_date' => $date,
            'work_shift_id' => $this->shift->id,
            'check_in_at' => $date.' 08:00:00',
            'check_out_at' => $date.' 18:30:00',
            'work_minutes' => 630,
            'calculated_overtime_minutes' => $minutes,
            'overtime_status' => Attendance::OVERTIME_PENDING,
            'status' => Attendance::STATUS_PRESENT,
            'source' => 'fingerprint',
        ]);
    }
}
