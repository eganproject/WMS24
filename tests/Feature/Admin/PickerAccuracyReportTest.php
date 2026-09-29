<?php

namespace Tests\Feature\Admin;

use App\Models\CustomerReturn;
use App\Models\CustomerReturnItem;
use App\Models\Employee;
use App\Models\Item;
use App\Models\Menu;
use App\Models\QcResiScan;
use App\Models\QcResiScanEvent;
use App\Models\QcResiScanItem;
use App\Models\Resi;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PickerAccuracyReportMenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PickerAccuracyReportTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->operator = User::factory()->create(['name' => 'Operator QC']);
    }

    public function test_report_calculates_accuracy_per_picker_from_qc_events_and_returns(): void
    {
        $pickerA = Employee::create(['employee_code' => 'PCK-A', 'name' => 'Picker Andi', 'employment_status' => 'active']);
        $pickerB = Employee::create(['employee_code' => 'PCK-B', 'name' => 'Picker Budi', 'employment_status' => 'active']);
        Item::create(['sku' => 'SKU-X', 'name' => 'Kabel Hitam', 'category_id' => 0]);
        $itemY = Item::create(['sku' => 'SKU-Y', 'name' => 'Kabel Putih', 'category_id' => 0]);

        $this->withoutMiddleware()->get(route('admin.reports.picker-accuracy.index'))
            ->assertOk()
            ->assertSee('Akurasi Picking per Picker')
            ->assertSee('Export Excel');

        $this->withoutMiddleware()->getJson(route('admin.reports.picker-accuracy.data', [
            'date_from' => '2026-09-16',
            'date_to' => '2026-09-15',
        ]))->assertUnprocessable()->assertJsonValidationErrors('date_to');

        // Picker A: 1 bersih, 1 salah SKU, 1 hold karena kurang ambil, 1 reset karena salah scan operator (bukan kesalahan picker).
        $this->qc($pickerA, 'A-CLEAN');
        $wrong = $this->qc($pickerA, 'A-WRONG');
        $this->event($wrong, QcResiScanEvent::TYPE_WRONG_SKU, ['sku' => 'SKU-Y', 'expected_sku' => 'SKU-X', 'qty' => 1]);
        $short = $this->qc($pickerA, 'A-SHORT', 'hold');
        $this->event($short, QcResiScanEvent::TYPE_HOLD, ['reason_code' => 'short_pick', 'reason' => 'Barang kurang diambil picker']);
        $operatorReset = $this->qc($pickerA, 'A-RESET');
        $this->event($operatorReset, QcResiScanEvent::TYPE_RESET, ['reason_code' => 'qc_scan_error', 'reason' => 'Salah scan oleh operator QC']);

        // Picker B: 1 bersih, 1 lolos QC tapi diretur salah barang, 1 barcode tidak dikenal (masalah master data).
        $this->qc($pickerB, 'B-CLEAN');
        $escaped = $this->qc($pickerB, 'B-ESCAPED');
        $return = CustomerReturn::create([
            'code' => 'RET-001',
            'resi_id' => $escaped->resi_id,
            'resi_no' => 'B-ESCAPED',
            'received_at' => '2026-09-18 10:00:00',
            'status' => 'inspected',
        ]);
        CustomerReturnItem::create([
            'customer_return_id' => $return->id,
            'item_id' => $itemY->id,
            'expected_qty' => 1,
            'received_qty' => 1,
            'root_cause' => CustomerReturnItem::ROOT_CAUSE_WRONG_ITEM,
        ]);
        $unknown = $this->qc($pickerB, 'B-UNKNOWN');
        $this->event($unknown, QcResiScanEvent::TYPE_UNKNOWN_BARCODE, ['scan_code' => 'BRC-ASING']);

        // Tidak dihitung: QC tanpa picker (hanya dilaporkan terpisah) dan QC di luar periode.
        QcResiScan::create([
            'resi_id' => $this->resi('NO-PICKER')->id,
            'scan_type' => 'no_resi',
            'scan_code' => 'NO-PICKER',
            'status' => 'passed',
            'started_at' => '2026-09-15 09:00:00',
            'scanned_by' => $this->operator->id,
        ]);
        $outside = $this->qc($pickerA, 'A-OUTSIDE', 'passed', '2026-09-20 09:00:00');
        $this->event($outside, QcResiScanEvent::TYPE_WRONG_SKU, ['sku' => 'SKU-Y', 'expected_sku' => 'SKU-X']);

        $response = $this->withoutMiddleware()->getJson(route('admin.reports.picker-accuracy.data', [
            'date_from' => '2026-09-15',
            'date_to' => '2026-09-15',
        ]));

        $response->assertOk()
            ->assertJsonPath('summary.total_resi', 7)
            ->assertJsonPath('summary.total_pickers', 2)
            ->assertJsonPath('summary.accurate_resi', 4)
            ->assertJsonPath('summary.accuracy_rate', 57.14)
            ->assertJsonPath('summary.first_pass_rate', 57.14)
            ->assertJsonPath('summary.caught_error_resi', 2)
            ->assertJsonPath('summary.escaped_error_resi', 1)
            ->assertJsonPath('summary.wrong_sku_events', 1)
            ->assertJsonPath('summary.picker_fault_events', 1)
            ->assertJsonPath('summary.unknown_barcode_events', 1)
            ->assertJsonPath('summary.unattributed_resi', 1)
            // Picker dengan akurasi terendah di urutan pertama.
            ->assertJsonPath('pickers.0.picker', 'Picker Andi')
            ->assertJsonPath('pickers.0.total_resi', 4)
            ->assertJsonPath('pickers.0.accuracy_rate', 50)
            ->assertJsonPath('pickers.0.first_pass_rate', 25)
            ->assertJsonPath('pickers.0.caught_error_resi', 2)
            ->assertJsonPath('pickers.1.picker', 'Picker Budi')
            ->assertJsonPath('pickers.1.accuracy_rate', 66.67)
            ->assertJsonPath('pickers.1.escaped_error_resi', 1)
            ->assertJsonPath('pickers.1.first_pass_rate', 100)
            ->assertJsonCount(1, 'sku_pairs')
            ->assertJsonPath('sku_pairs.0.expected_sku', 'SKU-X')
            ->assertJsonPath('sku_pairs.0.expected_sku_name', 'Kabel Hitam')
            ->assertJsonPath('sku_pairs.0.picked_sku', 'SKU-Y')
            ->assertJsonPath('sku_pairs.0.occurrences', 1)
            ->assertJsonCount(2, 'reasons')
            ->assertJsonCount(4, 'events');

        $reasons = collect($response->json('reasons'))->keyBy('reason_code');
        $this->assertTrue($reasons['short_pick']['is_picker_fault']);
        $this->assertFalse($reasons['qc_scan_error']['is_picker_fault']);

        $this->withoutMiddleware()->getJson(route('admin.reports.picker-accuracy.data', [
            'date_from' => '2026-09-15',
            'date_to' => '2026-09-15',
            'picker_id' => $pickerB->id,
        ]))->assertOk()
            ->assertJsonPath('summary.total_resi', 3)
            ->assertJsonCount(1, 'pickers')
            ->assertJsonCount(1, 'events');

        $this->withoutMiddleware()->getJson(route('admin.reports.picker-accuracy.data', [
            'date_from' => '2026-09-15',
            'date_to' => '2026-09-15',
            'q' => 'A-WRONG',
        ]))->assertOk()
            ->assertJsonPath('summary.total_resi', 1)
            ->assertJsonPath('summary.accuracy_rate', 0);

        $this->withoutMiddleware()->get(route('admin.reports.picker-accuracy.export', [
            'date_from' => '2026-09-15',
            'date_to' => '2026-09-15',
        ]))->assertOk()->assertDownload();
    }

    public function test_menu_seeder_inserts_once_and_never_overwrites_changes_from_the_app(): void
    {
        $reports = Menu::create(['name' => 'Laporan Custom', 'slug' => 'reports', 'sort_order' => 99, 'is_active' => true]);
        $role = Role::create(['name' => 'Administrator', 'slug' => 'admin']);

        $this->seed(PickerAccuracyReportMenuSeeder::class);

        $menu = Menu::query()->where('slug', 'report-picker-accuracy')->firstOrFail();
        $this->assertSame($reports->id, $menu->parent_id);
        $this->assertSame('admin.reports.picker-accuracy.index', $menu->route);
        $this->assertDatabaseHas('permission_menu', ['role_id' => $role->id, 'menu_id' => $menu->id, 'can_view' => true]);

        // Admin mengubah menu dan mencabut hak akses dari halaman manajemen menu.
        $menu->update(['name' => 'Nama dari Aplikasi', 'sort_order' => 7, 'is_active' => false]);
        DB::table('permission_menu')->where(['role_id' => $role->id, 'menu_id' => $menu->id])->delete();

        $this->seed(PickerAccuracyReportMenuSeeder::class);

        $this->assertDatabaseHas('menus', ['id' => $reports->id, 'name' => 'Laporan Custom', 'sort_order' => 99]);
        $this->assertDatabaseHas('menus', ['id' => $menu->id, 'name' => 'Nama dari Aplikasi', 'sort_order' => 7, 'is_active' => false]);
        $this->assertDatabaseMissing('permission_menu', ['role_id' => $role->id, 'menu_id' => $menu->id]);
        $this->assertSame(1, Menu::query()->where('slug', 'report-picker-accuracy')->count());
    }

    public function test_menu_seeder_skips_when_menu_was_already_created_from_the_app_with_other_slug(): void
    {
        Menu::create(['name' => 'Laporan', 'slug' => 'reports', 'sort_order' => 1, 'is_active' => true]);
        Role::create(['name' => 'Administrator', 'slug' => 'admin']);
        $manual = Menu::create([
            'name' => 'Akurasi Picker (manual)',
            'slug' => 'akurasi-picker-manual',
            'route' => 'admin.reports.picker-accuracy.index',
            'sort_order' => 5,
            'is_active' => true,
        ]);

        $this->seed(PickerAccuracyReportMenuSeeder::class);

        $this->assertSame(1, Menu::query()->where('route', 'admin.reports.picker-accuracy.index')->count());
        $this->assertDatabaseHas('menus', ['id' => $manual->id, 'name' => 'Akurasi Picker (manual)', 'parent_id' => null, 'sort_order' => 5]);
        $this->assertDatabaseMissing('menus', ['slug' => 'report-picker-accuracy']);
        $this->assertDatabaseMissing('permission_menu', ['menu_id' => $manual->id]);
    }

    public function test_menu_seeder_does_nothing_without_reports_parent_menu(): void
    {
        $this->seed(PickerAccuracyReportMenuSeeder::class);

        $this->assertDatabaseMissing('menus', ['slug' => 'report-picker-accuracy']);
    }

    private function qc(Employee $picker, string $code, string $status = 'passed', string $startedAt = '2026-09-15 09:00:00'): QcResiScan
    {
        $qc = QcResiScan::create([
            'resi_id' => $this->resi($code)->id,
            'picker_employee_id' => $picker->id,
            'scan_type' => 'no_resi',
            'scan_code' => $code,
            'status' => $status,
            'started_at' => $startedAt,
            'completed_at' => $status === 'passed' ? $startedAt : null,
            'scanned_by' => $this->operator->id,
            'completed_by' => $status === 'passed' ? $this->operator->id : null,
        ]);
        QcResiScanItem::create(['qc_resi_scan_id' => $qc->id, 'sku' => 'SKU-X', 'expected_qty' => 1, 'scanned_qty' => 1]);

        return $qc;
    }

    private function event(QcResiScan $qc, string $type, array $attributes = []): void
    {
        QcResiScanEvent::create(array_merge([
            'qc_resi_scan_id' => $qc->id,
            'resi_id' => $qc->resi_id,
            'picker_employee_id' => $qc->picker_employee_id,
            'event_type' => $type,
            'created_by' => $this->operator->id,
            'occurred_at' => $qc->started_at,
        ], $attributes));
    }

    private function resi(string $code): Resi
    {
        return Resi::create([
            'id_pesanan' => 'ORDER-'.$code,
            'tanggal_pesanan' => '2026-09-15',
            'tanggal_upload' => '2026-09-15',
            'no_resi' => $code,
            'uploader_id' => $this->operator->id,
        ]);
    }
}
