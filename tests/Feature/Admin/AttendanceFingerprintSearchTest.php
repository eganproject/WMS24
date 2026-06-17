<?php

namespace Tests\Feature\Admin;

use App\Models\AttendanceDevice;
use App\Models\Employee;
use App\Models\EmployeeFingerprint;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceFingerprintSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_fingerprint_search_matches_employee_device_machine_user_uid_and_global_device(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        $user->roles()->attach($role);
        $this->actingAs($user);

        $employee = Employee::create([
            'employee_code' => 'EMP-FP-001',
            'name' => 'Budi Finger',
            'employment_status' => Employee::STATUS_ACTIVE,
        ]);
        $otherEmployee = Employee::create([
            'employee_code' => 'EMP-FP-002',
            'name' => 'Siti Finger',
            'employment_status' => Employee::STATUS_ACTIVE,
        ]);
        $device = AttendanceDevice::create([
            'name' => 'Mesin Gudang Barat',
            'is_active' => true,
        ]);

        $fingerprint = EmployeeFingerprint::create([
            'employee_id' => $employee->id,
            'attendance_device_id' => $device->id,
            'device_user_id' => 'USR-1001',
            'fingerprint_uid' => 'UID-ABC',
            'is_active' => true,
        ]);
        $globalFingerprint = EmployeeFingerprint::create([
            'employee_id' => $otherEmployee->id,
            'attendance_device_id' => null,
            'device_user_id' => 'USR-GLOBAL',
            'fingerprint_uid' => 'UID-GLOBAL',
            'is_active' => true,
        ]);

        foreach (['Budi Finger', 'EMP-FP-001', 'Mesin Gudang', 'USR-1001', 'UID-ABC'] as $search) {
            $this->getJson(route('admin.attendance.fingerprints.data', [
                'draw' => 1,
                'q' => $search,
            ]))
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id', $fingerprint->id);
        }

        $this->getJson(route('admin.attendance.fingerprints.data', [
            'draw' => 1,
            'q' => 'Semua device',
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $globalFingerprint->id)
            ->assertJsonPath('data.0.device', 'Semua device');
    }
}
