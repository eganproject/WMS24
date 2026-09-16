<?php

namespace Tests\Feature\Admin;

use App\Models\Menu;
use App\Models\QcResiScan;
use App\Models\QcResiScanDuplicateAttempt;
use App\Models\QcResiScanItem;
use App\Models\QcResiScanSubstitution;
use App\Models\Resi;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\QcScanReportMenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QcScanReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_calculates_hourly_operator_and_audit_metrics(): void
    {
        $operatorOne = User::factory()->create(['name' => 'QC Satu']);
        $operatorTwo = User::factory()->create(['name' => 'QC Dua']);

        $this->withoutMiddleware()->get(route('admin.reports.qc-scan.index'))
            ->assertOk()
            ->assertSee('Produktivitas QC per Jam')
            ->assertSee('Export Excel');

        $this->withoutMiddleware()->getJson(route('admin.reports.qc-scan.data', [
            'date_from' => '2026-09-16',
            'date_to' => '2026-09-15',
        ]))->assertUnprocessable()->assertJsonValidationErrors('date_to');

        $first = $this->completedQc($operatorOne, 'QC-R-001', '2026-09-15 09:00:00', '2026-09-15 09:10:00', 2, 1);
        $second = $this->completedQc($operatorOne, 'QC-R-002', '2026-09-15 09:20:00', '2026-09-15 09:40:00', 3);
        $this->completedQc($operatorTwo, 'QC-R-003', '2026-09-15 10:00:00', '2026-09-15 10:15:00', 4);

        QcResiScanSubstitution::create([
            'qc_resi_scan_id' => $second->id,
            'original_sku' => 'SKU-ORIGINAL',
            'replacement_sku' => 'SKU-GANTI',
            'qty' => 1,
            'reason' => 'Uji laporan',
            'created_by' => $operatorOne->id,
        ]);
        QcResiScanDuplicateAttempt::create([
            'qc_resi_scan_id' => $first->id,
            'resi_id' => $first->resi_id,
            'scan_type' => 'resi',
            'scan_code' => 'QC-R-001',
            'existing_status' => 'passed',
            'qc_completed_at' => '2026-09-15 09:10:00',
            'qc_completed_by' => $operatorOne->id,
            'scanned_by' => $operatorOne->id,
            'scanned_at' => '2026-09-15 11:00:00',
        ]);

        $holdResi = $this->resi($operatorOne, 'QC-HOLD-001');
        QcResiScan::create([
            'resi_id' => $holdResi->id,
            'scan_type' => 'resi',
            'scan_code' => 'QC-HOLD-001',
            'status' => 'hold',
            'started_at' => '2026-09-15 11:00:00',
            'scanned_by' => $operatorOne->id,
            'hold_by' => $operatorOne->id,
            'hold_at' => '2026-09-15 11:10:00',
            'hold_reason' => 'Uji hold',
        ]);

        $response = $this->withoutMiddleware()->getJson(route('admin.reports.qc-scan.data', [
            'date_from' => '2026-09-15',
            'date_to' => '2026-09-15',
        ]));

        $response->assertOk()
            ->assertJsonPath('summary.total_resi', 3)
            ->assertJsonPath('summary.total_operators', 2)
            ->assertJsonPath('summary.active_operator_hours', 2)
            ->assertJsonPath('summary.avg_resi_per_hour', 1.5)
            ->assertJsonPath('summary.avg_duration_minutes', 15)
            ->assertJsonPath('summary.total_qty', 9)
            ->assertJsonPath('summary.reset_count', 1)
            ->assertJsonPath('summary.substitution_count', 1)
            ->assertJsonPath('summary.duplicate_attempts', 1)
            ->assertJsonPath('summary.hold_events', 1)
            ->assertJsonPath('summary.peak_hour', '09:00 - 10:00')
            ->assertJsonPath('summary.peak_hour_resi', 2)
            ->assertJsonCount(2, 'hourly')
            ->assertJsonCount(2, 'operators')
            ->assertJsonCount(3, 'details');

        $operatorResponse = $this->withoutMiddleware()->getJson(route('admin.reports.qc-scan.data', [
            'date_from' => '2026-09-15',
            'date_to' => '2026-09-15',
            'operator_id' => $operatorOne->id,
        ]));

        $operatorResponse->assertOk()
            ->assertJsonPath('summary.total_resi', 2)
            ->assertJsonPath('summary.avg_resi_per_hour', 2)
            ->assertJsonPath('summary.duplicate_attempts', 1)
            ->assertJsonPath('summary.hold_events', 1)
            ->assertJsonCount(1, 'operators');

        $this->withoutMiddleware()->getJson(route('admin.reports.qc-scan.data', [
            'date_from' => '2026-09-15',
            'date_to' => '2026-09-15',
            'q' => 'QC-R-001',
        ]))->assertOk()
            ->assertJsonPath('summary.total_resi', 1)
            ->assertJsonPath('summary.duplicate_attempts', 1)
            ->assertJsonPath('summary.hold_events', 0);

        $this->withoutMiddleware()->get(route('admin.reports.qc-scan.export', [
            'date_from' => '2026-09-15',
            'date_to' => '2026-09-15',
        ]))->assertOk()->assertDownload();
    }

    public function test_menu_seeder_only_inserts_missing_records_and_preserves_production_changes(): void
    {
        $reports = Menu::create([
            'name' => 'Laporan Custom',
            'slug' => 'reports',
            'route' => null,
            'sort_order' => 99,
            'is_active' => true,
        ]);
        $role = Role::create(['name' => 'Administrator', 'slug' => 'admin']);

        $this->seed(QcScanReportMenuSeeder::class);

        $menu = Menu::query()->where('slug', 'report-qc-scan')->firstOrFail();
        $this->assertSame($reports->id, $menu->parent_id);
        $this->assertDatabaseHas('permission_menu', [
            'role_id' => $role->id,
            'menu_id' => $menu->id,
            'can_view' => true,
        ]);

        $menu->update(['name' => 'Nama Production', 'route' => 'custom.production.route', 'is_active' => false]);
        DB::table('permission_menu')->where(['role_id' => $role->id, 'menu_id' => $menu->id])->update(['can_view' => false]);

        $this->seed(QcScanReportMenuSeeder::class);

        $this->assertDatabaseHas('menus', ['id' => $reports->id, 'name' => 'Laporan Custom', 'sort_order' => 99]);
        $this->assertDatabaseHas('menus', [
            'id' => $menu->id,
            'name' => 'Nama Production',
            'route' => 'custom.production.route',
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('permission_menu', [
            'role_id' => $role->id,
            'menu_id' => $menu->id,
            'can_view' => false,
        ]);
        $this->assertSame(1, Menu::query()->where('slug', 'report-qc-scan')->count());
    }

    private function completedQc(User $operator, string $code, string $startedAt, string $completedAt, int $qty, int $resetCount = 0): QcResiScan
    {
        $resi = $this->resi($operator, $code);
        $qc = QcResiScan::create([
            'resi_id' => $resi->id,
            'scan_type' => 'resi',
            'scan_code' => $code,
            'status' => 'passed',
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
            'scanned_by' => $operator->id,
            'completed_by' => $operator->id,
            'last_scanned_by' => $operator->id,
            'last_scanned_at' => $completedAt,
            'reset_count' => $resetCount,
        ]);
        QcResiScanItem::create([
            'qc_resi_scan_id' => $qc->id,
            'sku' => 'SKU-'.$code,
            'expected_qty' => $qty,
            'scanned_qty' => $qty,
        ]);

        return $qc;
    }

    private function resi(User $uploader, string $code): Resi
    {
        return Resi::create([
            'id_pesanan' => 'ORDER-'.$code,
            'tanggal_pesanan' => '2026-09-15',
            'tanggal_upload' => '2026-09-15',
            'no_resi' => $code,
            'uploader_id' => $uploader->id,
        ]);
    }
}
