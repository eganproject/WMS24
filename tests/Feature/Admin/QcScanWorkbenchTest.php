<?php

namespace Tests\Feature\Admin;

use App\Http\Middleware\AuthorizeMenuPermission;
use App\Models\Employee;
use App\Models\EmployeePosition;
use App\Models\Item;
use App\Models\QcResiScan;
use App\Models\Resi;
use App\Models\ResiDetail;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QcScanWorkbenchTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_qc_scan_workbench_page(): void
    {
        $this->withoutMiddleware(AuthorizeMenuPermission::class);

        $role = Role::firstOrCreate(
            ['slug' => 'admin'],
            [
                'name' => 'Administrator',
                'description' => 'Full access to system',
            ]
        );

        $user = User::factory()->create();
        $user->roles()->attach($role);
        $pickerPosition = EmployeePosition::create(['name' => 'Picker', 'is_active' => true]);
        Employee::create([
            'employee_code' => 'PCK-001',
            'name' => 'Picker Pertama',
            'employment_status' => 'active',
            'position_id' => $pickerPosition->id,
        ]);

        $this->actingAs($user)
            ->get(route('admin.outbound.qc-scan.index'))
            ->assertOk()
            ->assertSee('Workbench QC untuk Scanner Desktop')
            ->assertSee('Picker Aktif')
            ->assertSee('Cukup dipilih sekali per sesi kerja')
            ->assertSee('Picker Pertama')
            ->assertSee('Scan Resi')
            ->assertSee('Scan SKU');
    }

    public function test_desktop_qc_requires_valid_picker_and_keeps_existing_attribution(): void
    {
        $this->withoutMiddleware(AuthorizeMenuPermission::class);

        $role = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        $pickerPosition = EmployeePosition::create(['name' => 'Picker', 'is_active' => true]);
        $qcPosition = EmployeePosition::create(['name' => 'Quality Control', 'is_active' => true]);
        $picker = Employee::create([
            'employee_code' => 'PCK-VALID',
            'name' => 'Picker Valid',
            'employment_status' => 'active',
            'position_id' => $pickerPosition->id,
        ]);
        $otherPicker = Employee::create([
            'employee_code' => 'PCK-OTHER',
            'name' => 'Picker Lain',
            'employment_status' => 'active',
            'position_id' => $pickerPosition->id,
        ]);
        $notPicker = Employee::create([
            'employee_code' => 'QC-001',
            'name' => 'Bukan Picker',
            'employment_status' => 'active',
            'position_id' => $qcPosition->id,
        ]);

        $resi = Resi::create([
            'id_pesanan' => 'ORD-PICKER-001',
            'tanggal_pesanan' => now()->toDateString(),
            'tanggal_upload' => now()->toDateString(),
            'no_resi' => 'RESI-PICKER-001',
            'uploader_id' => $user->id,
            'status' => 'active',
        ]);
        Item::create(['sku' => 'SKU-PICKER-001', 'name' => 'Item Picker Test', 'category_id' => 0]);
        ResiDetail::create(['resi_id' => $resi->id, 'sku' => 'SKU-PICKER-001', 'qty' => 1]);

        $this->actingAs($user)
            ->postJson(route('admin.outbound.qc-scan.scan'), [
                'type' => 'no_resi',
                'code' => $resi->no_resi,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('picker_employee_id');

        $this->actingAs($user)
            ->postJson(route('admin.outbound.qc-scan.scan'), [
                'type' => 'no_resi',
                'code' => $resi->no_resi,
                'picker_employee_id' => $notPicker->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('picker_employee_id');

        $this->actingAs($user)
            ->postJson(route('admin.outbound.qc-scan.scan'), [
                'type' => 'no_resi',
                'code' => $resi->no_resi,
                'picker_employee_id' => $picker->id,
            ])
            ->assertOk()
            ->assertJsonPath('qc.audit.picker_name', 'Picker Valid')
            ->assertJsonPath('qc.audit.picker_code', 'PCK-VALID');

        $this->assertDatabaseHas('qc_resi_scans', [
            'resi_id' => $resi->id,
            'picker_employee_id' => $picker->id,
        ]);

        $this->actingAs($user)
            ->postJson(route('admin.outbound.qc-scan.scan'), [
                'type' => 'no_resi',
                'code' => $resi->no_resi,
                'picker_employee_id' => $otherPicker->id,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.picker_employee_id.0', 'Picker tidak sama dengan atribusi QC yang sudah tersimpan.');

        $this->assertSame($picker->id, QcResiScan::where('resi_id', $resi->id)->value('picker_employee_id'));

        $this->actingAs($user)
            ->getJson(route('admin.outbound.qc-history.data', [
                'date_from' => now()->toDateString(),
                'date_to' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertJsonPath('data.0.picker', 'Picker Valid')
            ->assertJsonPath('data.0.picker_code', 'PCK-VALID');
    }
}
