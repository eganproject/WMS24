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
            ->get(route('admin.dashboard', ['date_from' => '2026-06-19', 'date_to' => '2026-06-19']))
            ->assertOk()
            ->assertSee('Lebih: 2, Kurang: 2.');

        $this->actingAs($user)
            ->getJson(route('admin.dashboard.scan-out-discrepancy', ['date_from' => '2026-06-19', 'date_to' => '2026-06-19']))
            ->assertOk()
            ->assertJsonPath('meta.date', '2026-06-19')
            ->assertJsonPath('meta.over_total', 2)
            ->assertJsonPath('meta.under_total', 2)
            ->assertJsonPath('meta.difference', 0)
            ->assertJsonFragment([
                'type' => 'over',
                'no_resi' => 'RESI-OLD',
                'reason' => 'Tanggal upload di luar 2026-06-19',
                'sku' => 'SKU-OLD (2)',
            ])
            ->assertJsonFragment([
                'type' => 'over',
                'no_resi' => 'RESI-CANCELED',
                'reason' => 'Resi canceled tetapi ada scan out pada periode ini',
            ])
            ->assertJsonFragment([
                'type' => 'under',
                'no_resi' => 'RESI-NO-SCAN',
                'reason' => 'Belum ada scan out pada 2026-06-19',
                'sku' => 'SKU-NO-SCAN (3)',
            ])
            ->assertJsonFragment([
                'type' => 'under',
                'no_resi' => 'RESI-OTHER-DATE',
                'reason' => 'Scan out tercatat di luar periode',
                'scanned_at' => '2026-06-20 08:00',
            ])
            ->assertJsonMissing(['no_resi' => 'RESI-MATCH']);
    }

    public function test_dashboard_shows_duplicate_resi_links_to_import_resi_filter(): void
    {
        $this->withoutMiddleware(AuthorizeMenuPermission::class);
        $this->travelTo(Carbon::parse('2026-06-19 10:00:00'));

        $user = $this->createUserWithRole('admin');
        $kurir = Kurir::create(['name' => 'JNE']);

        $this->createResi($user->id, $kurir->id, 'ORD-DUP-A', 'RESI-DUP-001', '2026-06-19');
        $this->createResi($user->id, $kurir->id, 'ORD-DUP-B', 'RESI-DUP-001', '2026-06-19');
        $this->createResi($user->id, $kurir->id, 'ORD-NORMAL', 'RESI-NORMAL-001', '2026-06-19');

        $this->actingAs($user)
            ->get(route('admin.dashboard', ['date_from' => '2026-06-19', 'date_to' => '2026-06-19']))
            ->assertOk()
            ->assertSee('Audit Double Resi')
            ->assertSee('RESI-DUP-001')
            ->assertSee('/admin/inventory/resi-import?date_from=2026-06-19', false)
            ->assertSee('date_to=2026-06-19', false)
            ->assertSee('q=RESI-DUP-001', false)
            ->assertSee('search_mode=exact', false)
            ->assertDontSee('RESI-NORMAL-001');
    }

    public function test_operational_dashboard_uses_date_range_and_defaults_both_dates_to_today(): void
    {
        $this->withoutMiddleware(AuthorizeMenuPermission::class);
        $this->travelTo(Carbon::parse('2026-06-19 10:00:00'));

        $user = $this->createUserWithRole('admin');
        $kurir = Kurir::create(['name' => 'JNT']);
        $first = $this->createResi($user->id, $kurir->id, 'ORD-RANGE-1', 'RESI-RANGE-1', '2026-06-18');
        $this->createScanOut($first, $user, $kurir, '2026-06-18 09:00:00');
        $this->createResi($user->id, $kurir->id, 'ORD-RANGE-2', 'RESI-RANGE-2', '2026-06-19');

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('id="filter_date_from"', false)
            ->assertSee('id="filter_date_to"', false)
            ->assertSee('value="2026-06-19"', false);

        $this->actingAs($user)
            ->get(route('admin.dashboard', ['date_from' => '2026-06-18', 'date_to' => '2026-06-19']))
            ->assertOk()
            ->assertSee('Menampilkan data periode')
            ->assertSee('2026-06-18 s.d. 2026-06-19')
            ->assertSee('Lebih: 0, Kurang: 1.');

        $this->actingAs($user)
            ->getJson(route('admin.dashboard.kurir-detail', [
                'kurir_id' => $kurir->id,
                'date_from' => '2026-06-18',
                'date_to' => '2026-06-19',
                'type' => 'total',
            ]))
            ->assertOk()
            ->assertJsonPath('meta.date_from', '2026-06-18')
            ->assertJsonPath('meta.date_to', '2026-06-19')
            ->assertJsonPath('meta.total_resi', 2)
            ->assertJsonPath('meta.scanned_total', 1)
            ->assertJsonPath('meta.remaining_total', 1)
            ->assertJsonCount(2, 'data');

        $this->actingAs($user)
            ->getJson(route('admin.dashboard.scan-out-discrepancy', [
                'date_from' => '2026-06-18',
                'date_to' => '2026-06-19',
            ]))
            ->assertOk()
            ->assertJsonPath('meta.period', '2026-06-18 s.d. 2026-06-19')
            ->assertJsonPath('meta.over_total', 0)
            ->assertJsonPath('meta.under_total', 1)
            ->assertJsonPath('data.under.0.no_resi', 'RESI-RANGE-2');
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
            'is_active' => true,
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
