<?php

namespace Tests\Feature\Admin;

use App\Http\Middleware\AuthorizeMenuPermission;
use App\Models\Kurir;
use App\Models\QcResiScan;
use App\Models\Resi;
use App\Models\ResiDetail;
use App\Models\Role;
use App\Models\ShipmentScanOut;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardReadyPreviousScanOutTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_lists_ready_scan_out_resis_from_previous_uploads_only(): void
    {
        $this->withoutMiddleware(AuthorizeMenuPermission::class);
        $this->travelTo(Carbon::parse('2026-06-19 10:00:00'));

        $user = $this->createUserWithRole('admin');
        $kurir = Kurir::create(['name' => 'JNE']);

        $readyPrevious = $this->createResi($user->id, $kurir->id, 'ORD-OLD-READY', 'RESI-OLD-READY', '2026-06-18');
        ResiDetail::create(['resi_id' => $readyPrevious->id, 'sku' => 'SKU-OLD', 'qty' => 2]);
        $this->markQcPassed($readyPrevious, $user);

        $readyToday = $this->createResi($user->id, $kurir->id, 'ORD-TODAY', 'RESI-TODAY', '2026-06-19');
        $this->markQcPassed($readyToday, $user);

        $notQcPassed = $this->createResi($user->id, $kurir->id, 'ORD-DRAFT', 'RESI-DRAFT', '2026-06-18');
        QcResiScan::create([
            'resi_id' => $notQcPassed->id,
            'scan_type' => 'no_resi',
            'scan_code' => $notQcPassed->no_resi,
            'status' => 'draft',
            'started_at' => now(),
            'scanned_by' => $user->id,
        ]);

        $alreadyScanned = $this->createResi($user->id, $kurir->id, 'ORD-SCANNED', 'RESI-SCANNED', '2026-06-18');
        $this->markQcPassed($alreadyScanned, $user);
        ShipmentScanOut::create([
            'resi_id' => $alreadyScanned->id,
            'kurir_id' => $kurir->id,
            'scan_type' => 'no_resi',
            'scan_code' => $alreadyScanned->no_resi,
            'scan_date' => now()->toDateString(),
            'scanned_at' => now(),
            'scanned_by' => $user->id,
        ]);

        $canceled = $this->createResi($user->id, $kurir->id, 'ORD-CANCELED', 'RESI-CANCELED', '2026-06-18', 'canceled');
        $this->markQcPassed($canceled, $user);

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('1 resi sudah lolos QC, belum scan out, dan bukan upload hari ini.');

        $this->actingAs($user)
            ->getJson(route('admin.dashboard.ready-scan-out-previous-uploads'))
            ->assertOk()
            ->assertJsonPath('meta.current_date', '2026-06-19')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id_pesanan', 'ORD-OLD-READY')
            ->assertJsonPath('data.0.no_resi', 'RESI-OLD-READY')
            ->assertJsonPath('data.0.kurir', 'JNE')
            ->assertJsonPath('data.0.sku', 'SKU-OLD (2)')
            ->assertJsonPath('data.0.tanggal_upload', '2026-06-18')
            ->assertJsonMissing(['no_resi' => 'RESI-TODAY'])
            ->assertJsonMissing(['no_resi' => 'RESI-DRAFT'])
            ->assertJsonMissing(['no_resi' => 'RESI-SCANNED'])
            ->assertJsonMissing(['no_resi' => 'RESI-CANCELED']);
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

    private function createResi(
        int $uploaderId,
        int $kurirId,
        string $orderId,
        string $resiNo,
        string $uploadDate,
        string $status = 'active'
    ): Resi {
        return Resi::create([
            'id_pesanan' => $orderId,
            'tanggal_pesanan' => $uploadDate,
            'tanggal_upload' => $uploadDate,
            'no_resi' => $resiNo,
            'kurir_id' => $kurirId,
            'uploader_id' => $uploaderId,
            'status' => $status,
        ]);
    }

    private function markQcPassed(Resi $resi, User $user): void
    {
        QcResiScan::create([
            'resi_id' => $resi->id,
            'scan_type' => 'no_resi',
            'scan_code' => $resi->no_resi,
            'status' => 'passed',
            'started_at' => now(),
            'completed_at' => now(),
            'scanned_by' => $user->id,
            'completed_by' => $user->id,
        ]);
    }
}
