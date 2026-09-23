<?php

namespace Tests\Feature\Admin;

use App\Models\InboundItem;
use App\Models\InboundScanSession;
use App\Models\InboundScanSessionItem;
use App\Models\InboundTransaction;
use App\Models\Item;
use App\Models\Menu;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\InboundLeadTimeReportMenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InboundLeadTimeReportTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_report_tracks_document_wait_scan_and_total_lead_time(): void
    {
        Carbon::setTestNow('2026-09-23 12:00:00');
        $creator = User::factory()->create(['name' => 'Admin Inbound']);
        $scanner = User::factory()->create(['name' => 'Scanner Inbound']);
        $warehouse = Warehouse::firstOrCreate(
            ['code' => 'GUDANG_BESAR'],
            ['name' => 'Gudang Besar', 'type' => 'main']
        );
        $supplier = Supplier::create(['name' => 'Supplier Laporan']);
        $item = Item::create(['sku' => 'INB-REPORT-001', 'name' => 'Item Laporan', 'category_id' => 0, 'koli_qty' => 10]);

        $completed = $this->transaction($creator, $warehouse, $supplier, $item, 'RCV-REPORT-001', 'completed', '2026-09-23 08:00:00', 20, 2);
        $this->makeScanSession($completed, $scanner, $item, '2026-09-23 09:00:00', '2026-09-23 10:30:00', 20, 10, 2, 1, 1);

        $pending = $this->transaction($creator, $warehouse, $supplier, $item, 'RCV-REPORT-002', 'pending_scan', '2026-09-23 11:00:00', 10, 1);
        $scanning = $this->transaction($creator, $warehouse, $supplier, $item, 'RCV-REPORT-003', 'scanning', '2026-09-23 09:00:00', 10, 1);
        $this->makeScanSession($scanning, $scanner, $item, '2026-09-23 11:00:00', null, 10, 0, 1, 0);

        $this->withoutMiddleware()->get(route('admin.reports.inbound-lead-time.index'))
            ->assertOk()
            ->assertSee('Tracking Dokumen sampai Completed')
            ->assertSee('Export Excel');

        $response = $this->withoutMiddleware()->getJson(route('admin.reports.inbound-lead-time.data', [
            'date_from' => '2026-09-23',
            'date_to' => '2026-09-23',
        ]));

        $response->assertOk()
            ->assertJsonPath('summary.total_documents', 3)
            ->assertJsonPath('summary.completed_documents', 1)
            ->assertJsonPath('summary.pending_documents', 1)
            ->assertJsonPath('summary.scanning_documents', 1)
            ->assertJsonPath('summary.completion_rate', 33.33)
            ->assertJsonPath('summary.avg_waiting_minutes', 90)
            ->assertJsonPath('summary.avg_scan_minutes', 90)
            ->assertJsonPath('summary.avg_lead_minutes', 150)
            ->assertJsonPath('summary.oldest_open_minutes', 180)
            ->assertJsonPath('summary.variance_documents', 1)
            ->assertJsonPath('summary.reset_count', 1)
            ->assertJsonCount(3, 'details');

        $completedRow = collect($response->json('details'))->firstWhere('code', 'RCV-REPORT-001');
        $this->assertSame(60.0, (float) $completedRow['waiting_minutes']);
        $this->assertSame(90.0, (float) $completedRow['scan_minutes']);
        $this->assertSame(150.0, (float) $completedRow['lead_minutes']);
        $this->assertSame(-10, $completedRow['qty_variance']);

        $this->withoutMiddleware()->getJson(route('admin.reports.inbound-lead-time.data', [
            'date_from' => '2026-09-23',
            'date_to' => '2026-09-23',
            'status' => 'completed',
            'q' => 'RCV-REPORT-001',
        ]))->assertOk()
            ->assertJsonPath('summary.total_documents', 1)
            ->assertJsonPath('summary.completed_documents', 1);

        $this->withoutMiddleware()->getJson(route('admin.reports.inbound-lead-time.data', [
            'date_from' => '2026-09-24',
            'date_to' => '2026-09-23',
        ]))->assertUnprocessable()->assertJsonValidationErrors('date_to');

        $this->withoutMiddleware()->get(route('admin.reports.inbound-lead-time.export', [
            'date_from' => '2026-09-23',
            'date_to' => '2026-09-23',
        ]))->assertOk()->assertDownload();

        $this->assertNotNull($pending);
    }

    public function test_menu_seeder_is_additive_and_preserves_production_changes(): void
    {
        $reports = Menu::create([
            'name' => 'Laporan Production', 'slug' => 'reports', 'route' => null,
            'sort_order' => 88, 'is_active' => true,
        ]);
        $role = Role::create(['name' => 'Administrator', 'slug' => 'admin']);

        $this->seed(InboundLeadTimeReportMenuSeeder::class);
        $menu = Menu::query()->where('slug', 'report-inbound-lead-time')->firstOrFail();

        $this->assertSame($reports->id, $menu->parent_id);
        $this->assertDatabaseHas('permission_menu', [
            'role_id' => $role->id, 'menu_id' => $menu->id, 'can_view' => true,
        ]);

        $menu->update(['name' => 'Nama Custom Production', 'route' => 'custom.production.route', 'is_active' => false]);
        DB::table('permission_menu')->where(['role_id' => $role->id, 'menu_id' => $menu->id])->update(['can_view' => false]);

        $this->seed(InboundLeadTimeReportMenuSeeder::class);

        $this->assertDatabaseHas('menus', ['id' => $reports->id, 'name' => 'Laporan Production', 'sort_order' => 88]);
        $this->assertDatabaseHas('menus', [
            'id' => $menu->id, 'name' => 'Nama Custom Production', 'route' => 'custom.production.route', 'is_active' => false,
        ]);
        $this->assertDatabaseHas('permission_menu', [
            'role_id' => $role->id, 'menu_id' => $menu->id, 'can_view' => false,
        ]);
        $this->assertSame(1, Menu::query()->where('slug', 'report-inbound-lead-time')->count());
    }

    private function transaction(User $creator, Warehouse $warehouse, Supplier $supplier, Item $item, string $code, string $status, string $createdAt, int $qty, int $koli): InboundTransaction
    {
        $transaction = InboundTransaction::create([
            'code' => $code,
            'type' => 'receipt',
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'transacted_at' => $createdAt,
            'created_by' => $creator->id,
            'status' => $status,
        ]);
        $transaction->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->saveQuietly();

        InboundItem::create([
            'inbound_transaction_id' => $transaction->id,
            'item_id' => $item->id,
            'qty' => $qty,
            'koli' => $koli,
            'input_unit' => 'koli',
        ]);

        return $transaction;
    }

    private function makeScanSession(InboundTransaction $transaction, User $scanner, Item $item, string $startedAt, ?string $completedAt, int $expectedQty, int $scannedQty, int $expectedKoli, int $scannedKoli, int $resetCount = 0): InboundScanSession
    {
        $session = InboundScanSession::create([
            'inbound_transaction_id' => $transaction->id,
            'started_by' => $scanner->id,
            'started_at' => $startedAt,
            'last_scanned_by' => $scanner->id,
            'last_scanned_at' => $completedAt ?: $startedAt,
            'completed_by' => $completedAt ? $scanner->id : null,
            'completed_at' => $completedAt,
            'reset_count' => $resetCount,
        ]);

        InboundScanSessionItem::create([
            'inbound_scan_session_id' => $session->id,
            'item_id' => $item->id,
            'sku' => $item->sku,
            'item_name' => $item->name,
            'input_unit' => 'koli',
            'qty_per_koli' => 10,
            'expected_qty' => $expectedQty,
            'expected_koli' => $expectedKoli,
            'scanned_qty' => $scannedQty,
            'scanned_koli' => $scannedKoli,
        ]);

        if ($completedAt) {
            $transaction->forceFill(['approved_at' => $completedAt, 'approved_by' => $scanner->id])->saveQuietly();
        }

        return $session;
    }
}
