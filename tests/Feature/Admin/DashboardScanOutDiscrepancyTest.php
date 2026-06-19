<?php

namespace Tests\Feature\Admin;

use App\Http\Middleware\AuthorizeMenuPermission;
use App\Models\Kurir;
use App\Models\Resi;
use App\Models\ResiDetail;
use App\Models\Role;
use App\Models\ShipmentScanOut;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardScanOutDiscrepancyTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_lists_scan_out_discrepancy_rows(): void
    {
        $this->withoutMiddleware(AuthorizeMenuPermission::class);
        $this->travelTo(Carbon::parse('2026-06-19 10:00:00'));

        $user = $this->createUserWithRole('admin');
        $kurir = Kurir::create(['name' => 'JNE']);

        $matching = $this->createResi($user->id, $kurir->id, 'ORD-MATCH', 'RESI-MATCH', '2026-06-19');
        $this->createDetail($matching, 'SKU-MATCH', 1);
        $this->createScanOut($matching, $user, $kurir, '2026-06-19 09:00:00');

        $overOldUpload = $this->createResi($user->id, $kurir->id, 'ORD-OLD', 'RESI-OLD', '2026-06-18');
        $this->createDetail($overOldUpload, 'SKU-OLD', 2);
        $this->createScanOut($overOldUpload, $user, $kurir, '2026-06-19 09:10:00');

        $overCanceled = $this->createResi($user->id, $kurir->id, 'ORD-CANCELED', 'RESI-CANCELED', '2026-06-19', 'canceled');
        $this->createDetail($overCanceled, 'SKU-CANCELED', 1);
        $this->createScanOut($overCanceled, $user, $kurir, '2026-06-19 09:20:00');

        $underNoScan = $this->createResi($user->id, $kurir->id, 'ORD-NO-SCAN', 'RESI-NO-SCAN', '2026-06-19');
        $this->createDetail($underNoScan, 'SKU-NO-SCAN', 3);

        $underOtherDate = $this->createResi($user->id, $kurir->id, 'ORD-OTHER-DATE', 'RESI-OTHER-DATE', '2026-06-19');
        $this->createDetail($underOtherDate, 'SKU-OTHER-DATE', 4);
        $this->createScanOut($underOtherDate, $user, $kurir, '2026-06-20 08:00:00');

        $this->actingAs($user)
            ->get(route('admin.dashboard', ['date' => '2026-06-19']))
            ->assertOk()
            ->assertSee('Lebih: 2, Kurang: 2.');

        $this->actingAs($user)
            ->getJson(route('admin.dashboard.scan-out-discrepancy', ['date' => '2026-06-19']))
            ->assertOk()
            ->assertJsonPath('meta.date', '2026-06-19')
            ->assertJsonPath('meta.over_total', 2)
            ->assertJsonPath('meta.under_total', 2)
            ->assertJsonPath('meta.difference', 0)
            ->assertJsonFragment([
                'type' => 'over',
                'no_resi' => 'RESI-OLD',
                'reason' => 'Tanggal upload bukan 2026-06-19',
                'sku' => 'SKU-OLD (2)',
            ])
            ->assertJsonFragment([
                'type' => 'over',
                'no_resi' => 'RESI-CANCELED',
                'reason' => 'Resi canceled tetapi ada scan out tanggal ini',
            ])
            ->assertJsonFragment([
                'type' => 'under',
                'no_resi' => 'RESI-NO-SCAN',
                'reason' => 'Belum ada scan out tanggal 2026-06-19',
                'sku' => 'SKU-NO-SCAN (3)',
            ])
            ->assertJsonFragment([
                'type' => 'under',
                'no_resi' => 'RESI-OTHER-DATE',
                'reason' => 'Scan out tercatat di tanggal lain',
                'scanned_at' => '2026-06-20 08:00',
            ])
            ->assertJsonMissing(['no_resi' => 'RESI-MATCH']);
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

    private function createDetail(Resi $resi, string $sku, int $qty): void
    {
        ResiDetail::create([
            'resi_id' => $resi->id,
            'sku' => $sku,
            'qty' => $qty,
        ]);
    }

    private function createScanOut(Resi $resi, User $user, Kurir $kurir, string $scannedAt): void
    {
        $scannedAt = Carbon::parse($scannedAt);

        ShipmentScanOut::create([
            'resi_id' => $resi->id,
            'kurir_id' => $kurir->id,
            'scan_type' => 'no_resi',
            'scan_code' => $resi->no_resi,
            'scan_date' => $scannedAt->toDateString(),
            'scanned_at' => $scannedAt,
            'scanned_by' => $user->id,
        ]);
    }
}
