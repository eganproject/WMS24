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
            ->assertSee('Analisis Lead Time per Role dan Jabatan')
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

    public function test_operational_report_compares_picker_packer_inbound_and_customer_return(): void
    {
        Carbon::setTestNow('2026-09-23 12:00:00');
        $inputter = User::factory()->create(['name' => 'Admin Resi']);
        $qcUser = User::factory()->create(['name' => 'Picker Satu']);
        $scanOutUser = User::factory()->create(['name' => 'Admin Scan Out']);
        $inboundUser = User::factory()->create(['name' => 'Inbound Satu']);
        $returnCreator = User::factory()->create(['name' => 'Admin Retur']);
        $returnFinalizer = User::factory()->create(['name' => 'Retur Satu']);

        $positionIds = [];
        foreach (['Picker', 'Packer', 'Inbound', 'Retur Customer'] as $position) {
            $positionIds[$position] = DB::table('employee_positions')->insertGetId([
                'name' => $position, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $employee = function (?User $user, string $code, string $name, string $position) use ($positionIds): int {
            return DB::table('employees')->insertGetId([
                'user_id' => $user?->id, 'position_id' => $positionIds[$position],
                'employee_code' => $code, 'name' => $name, 'position' => $position,
                'employment_status' => 'active', 'created_at' => now(), 'updated_at' => now(),
            ]);
        };
        $employee($qcUser, 'EMP-PICKER-1', 'Picker Satu', 'Picker');
        $packerEmployeeId = $employee(null, 'EMP-PACKER-1', 'Packer Satu', 'Packer');
        $employee($inboundUser, 'EMP-INBOUND-1', 'Inbound Satu', 'Inbound');
        $employee($returnFinalizer, 'EMP-RETURN-1', 'Retur Satu', 'Retur Customer');

        $resiId = DB::table('resis')->insertGetId([
            'id_pesanan' => 'ORDER-OPS-001', 'tanggal_pesanan' => '2026-09-23',
            'tanggal_upload' => '2026-09-23', 'no_resi' => 'RESI-OPS-001',
            'uploader_id' => $inputter->id, 'status' => 'active',
            'created_at' => '2026-09-23 08:00:00', 'updated_at' => '2026-09-23 08:00:00',
        ]);
        DB::table('resi_details')->insert([
            'resi_id' => $resiId, 'sku' => 'SKU-OPS-001', 'qty' => 5,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('qc_resi_scans')->insert([
            'resi_id' => $resiId, 'scan_type' => 'no_resi', 'scan_code' => 'RESI-OPS-001',
            'status' => 'passed', 'started_at' => '2026-09-23 09:00:00',
            'completed_at' => '2026-09-23 10:00:00', 'scanned_by' => $qcUser->id,
            'completed_by' => $qcUser->id, 'last_scanned_by' => $qcUser->id,
            'last_scanned_at' => '2026-09-23 10:00:00', 'reset_count' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('shipment_scan_outs')->insert([
            'resi_id' => $resiId, 'scan_type' => 'no_resi', 'scan_code' => 'RESI-OPS-001',
            'scan_date' => '2026-09-23', 'scanned_at' => '2026-09-23 10:45:00',
            'scanned_by' => $scanOutUser->id, 'packed_employee_id' => $packerEmployeeId,
            'packed_at' => '2026-09-23 10:45:00', 'packing_confirmed_by' => $scanOutUser->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $warehouse = Warehouse::firstOrCreate(['code' => 'OPS'], ['name' => 'Gudang Operasional', 'type' => 'main']);
        $supplier = Supplier::create(['name' => 'Supplier Operasional']);
        $item = Item::create(['sku' => 'INB-OPS-001', 'name' => 'Item Inbound Ops', 'category_id' => 0, 'koli_qty' => 10]);
        $inbound = $this->transaction($inboundUser, $warehouse, $supplier, $item, 'RCV-OPS-001', 'completed', '2026-09-23 08:00:00', 10, 1);
        $this->makeScanSession($inbound, $inboundUser, $item, '2026-09-23 09:00:00', '2026-09-23 10:30:00', 10, 10, 1, 1);

        DB::table('customer_returns')->insert([
            'code' => 'CRT-OPS-001', 'resi_no' => 'RESI-RETUR-001', 'order_ref' => 'ORDER-RETUR-001',
            'received_at' => '2026-09-23 08:00:00', 'inspected_at' => '2026-09-23 09:00:00',
            'finalized_at' => '2026-09-23 11:00:00', 'status' => 'completed',
            'created_by' => $returnCreator->id, 'inspected_by' => $returnFinalizer->id,
            'finalized_by' => $returnFinalizer->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->withoutMiddleware()->getJson(route('admin.reports.inbound-lead-time.data', [
            'date_from' => '2026-09-23', 'date_to' => '2026-09-23',
        ]));
        $response->assertOk()
            ->assertJsonPath('summary.total_documents', 4)
            ->assertJsonPath('summary.completed_documents', 4)
            ->assertJsonPath('summary.avg_lead_minutes', 123.75)
            ->assertJsonPath('summary.median_lead_minutes', 120)
            ->assertJsonPath('summary.p90_lead_minutes', 180)
            ->assertJsonPath('summary.missing_position_documents', 0)
            ->assertJsonCount(4, 'roles')
            ->assertJsonCount(4, 'details');

        $roleMetrics = collect($response->json('roles'))->keyBy('key');
        $this->assertSame(120.0, (float) $roleMetrics['picker']['avg_lead_minutes']);
        $this->assertSame(45.0, (float) $roleMetrics['packer']['avg_lead_minutes']);
        $this->assertSame(150.0, (float) $roleMetrics['inbound']['avg_lead_minutes']);
        $this->assertSame(180.0, (float) $roleMetrics['customer_return']['avg_lead_minutes']);
        $this->assertSame(['Inbound', 'Packer', 'Picker', 'Retur Customer'], collect($response->json('positions'))->pluck('position')->sort()->values()->all());

        $this->withoutMiddleware()->getJson(route('admin.reports.inbound-lead-time.data', [
            'date_from' => '2026-09-23', 'date_to' => '2026-09-23',
            'role' => 'packer', 'status' => 'completed', 'q' => 'Packer Satu',
        ]))->assertOk()
            ->assertJsonPath('summary.total_documents', 1)
            ->assertJsonPath('details.0.role', 'packer')
            ->assertJsonPath('details.0.pic', 'Packer Satu')
            ->assertJsonPath('details.0.lead_minutes', 45);

        $this->withoutMiddleware()->getJson(route('admin.reports.inbound-lead-time.data', [
            'date_from' => '2026-09-23', 'date_to' => '2026-09-23', 'type' => 'receipt',
        ]))->assertOk()
            ->assertJsonPath('period.role', 'inbound')
            ->assertJsonPath('summary.total_documents', 1)
            ->assertJsonPath('details.0.role', 'inbound');
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
