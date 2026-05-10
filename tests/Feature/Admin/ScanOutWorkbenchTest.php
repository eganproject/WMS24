<?php

namespace Tests\Feature\Admin;

use App\Http\Middleware\AuthorizeMenuPermission;
use App\Models\Kurir;
use App\Models\QcResiScan;
use App\Models\Resi;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScanOutWorkbenchTest extends TestCase
{
    use RefreshDatabase;

    public function test_desktop_scan_out_requires_completed_qc(): void
    {
        $this->withoutMiddleware(AuthorizeMenuPermission::class);

        $user = $this->createUserWithRole('admin');
        $kurir = Kurir::create(['name' => 'JNE']);
        $notStarted = $this->createResi($user->id, $kurir->id, 'ORD-SO-001', 'RESI-SO-001');
        $inProgress = $this->createResi($user->id, $kurir->id, 'ORD-SO-002', 'RESI-SO-002');
        $passed = $this->createResi($user->id, $kurir->id, 'ORD-SO-003', 'RESI-SO-003');

        QcResiScan::create([
            'resi_id' => $inProgress->id,
            'scan_type' => 'no_resi',
            'scan_code' => $inProgress->no_resi,
            'status' => 'draft',
            'started_at' => now(),
            'scanned_by' => $user->id,
        ]);

        QcResiScan::create([
            'resi_id' => $passed->id,
            'scan_type' => 'no_resi',
            'scan_code' => $passed->no_resi,
            'status' => 'passed',
            'started_at' => now(),
            'completed_at' => now(),
            'scanned_by' => $user->id,
            'completed_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->postJson(route('admin.outbound.scan-out.scan'), [
                'type' => 'no_resi',
                'code' => $notStarted->no_resi,
            ])
            ->assertStatus(422)
            ->assertJsonPath('reason_code', 'qc_not_started')
            ->assertJsonPath('resi.no_resi', $notStarted->no_resi);

        $this->actingAs($user)
            ->postJson(route('admin.outbound.scan-out.scan'), [
                'type' => 'no_resi',
                'code' => $inProgress->no_resi,
            ])
            ->assertStatus(422)
            ->assertJsonPath('reason_code', 'qc_not_passed')
            ->assertJsonPath('qc.status', 'draft');

        $this->actingAs($user)
            ->postJson(route('admin.outbound.scan-out.scan'), [
                'type' => 'no_resi',
                'code' => $passed->no_resi,
            ])
            ->assertOk()
            ->assertJsonPath('scan_out.no_resi', $passed->no_resi);

        $this->assertDatabaseHas('shipment_scan_outs', [
            'resi_id' => $passed->id,
            'scanned_by' => $user->id,
        ]);
    }

    private function createUserWithRole(string $slug): User
    {
        $role = Role::firstOrCreate(
            ['slug' => $slug],
            [
                'name' => strtoupper(str_replace('-', ' ', $slug)),
                'description' => $slug,
            ]
        );

        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $user->roles()->attach($role);

        return $user;
    }

    private function createResi(int $uploaderId, int $kurirId, string $orderId, string $resiNo): Resi
    {
        return Resi::create([
            'id_pesanan' => $orderId,
            'tanggal_pesanan' => now()->toDateString(),
            'tanggal_upload' => now()->toDateString(),
            'no_resi' => $resiNo,
            'kurir_id' => $kurirId,
            'uploader_id' => $uploaderId,
            'status' => 'active',
        ]);
    }
}
