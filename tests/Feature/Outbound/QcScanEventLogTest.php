<?php

namespace Tests\Feature\Outbound;

use App\Models\Employee;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\QcResiScan;
use App\Models\QcResiScanEvent;
use App\Models\Resi;
use App\Models\ResiDetail;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\QcReasonCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QcScanEventLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_qc_requires_picker_and_shows_picker_options(): void
    {
        $qcUser = $this->createQcUser();
        $picker = Employee::create([
            'employee_code' => 'PCK-MOB-001',
            'name' => 'Picker Mobile',
            'employment_status' => 'active',
        ]);
        Employee::create([
            'employee_code' => 'PCK-MOB-OFF',
            'name' => 'Picker Nonaktif Mobile',
            'employment_status' => 'inactive',
        ]);
        Item::create(['sku' => 'SKU-MOB-A', 'name' => 'Item Mobile', 'category_id' => 0]);
        $resi = $this->createResiWithDetails('RESI-MOB-PICKER', ['SKU-MOB-A' => 1]);

        $this->actingAs($qcUser)
            ->get(route('mobile.qc.index'))
            ->assertOk()
            ->assertSee('Picker Mobile')
            ->assertDontSee('Picker Nonaktif Mobile');

        $this->actingAs($qcUser)
            ->postJson(route('mobile.qc.scan'), [
                'type' => 'no_resi',
                'code' => $resi->no_resi,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('picker_employee_id');

        $this->actingAs($qcUser)
            ->postJson(route('mobile.qc.scan'), [
                'type' => 'no_resi',
                'code' => $resi->no_resi,
                'picker_employee_id' => $picker->id,
            ])
            ->assertOk()
            ->assertJsonPath('qc.audit.picker_name', 'Picker Mobile');

        $this->assertSame($picker->id, QcResiScan::where('resi_id', $resi->id)->value('picker_employee_id'));
    }

    public function test_qc_scan_problems_are_logged_as_events_with_picker_snapshot(): void
    {
        $qcUser = $this->createQcUser();
        $picker = Employee::create([
            'employee_code' => 'PCK-EVT-001',
            'name' => 'Picker Event',
            'employment_status' => 'active',
        ]);
        $display = Warehouse::firstOrCreate(['code' => 'GUDANG_DISPLAY'], ['name' => 'Gudang Display']);
        Warehouse::firstOrCreate(['code' => 'GUDANG_BESAR'], ['name' => 'Gudang Besar']);
        foreach (['SKU-EVT-A', 'SKU-EVT-B', 'SKU-EVT-C'] as $sku) {
            $item = Item::create(['sku' => $sku, 'name' => 'Item '.$sku, 'category_id' => 0]);
            ItemStock::create(['item_id' => $item->id, 'warehouse_id' => $display->id, 'stock' => 10]);
        }
        $resi = $this->createResiWithDetails('RESI-EVT-001', ['SKU-EVT-A' => 2, 'SKU-EVT-B' => 1]);

        $this->actingAs($qcUser)
            ->postJson(route('mobile.qc.scan'), [
                'type' => 'no_resi',
                'code' => $resi->no_resi,
                'picker_employee_id' => $picker->id,
            ])
            ->assertOk();
        $qc = QcResiScan::where('resi_id', $resi->id)->firstOrFail();

        $scanSku = fn (string $code, int $qty = 1) => $this->actingAs($qcUser)
            ->postJson(route('mobile.qc.scan-sku'), ['qc_id' => $qc->id, 'code' => $code, 'qty' => $qty]);

        // Salah ambil saat masih ada dua SKU yang belum terpenuhi: SKU seharusnya belum bisa dipastikan.
        $scanSku('SKU-EVT-C')->assertUnprocessable()->assertJsonPath('message', 'SKU tidak sesuai resi.');
        $scanSku('BARCODE-TIDAK-ADA')->assertUnprocessable()->assertJsonPath('message', 'SKU tidak sesuai resi.');
        $scanSku('SKU-EVT-A', 2)->assertOk();
        // Tersisa satu SKU (B), jadi pasangan salah ambil C -> B tercatat.
        $scanSku('SKU-EVT-C')->assertUnprocessable();
        $scanSku('SKU-EVT-A')->assertUnprocessable()->assertJsonPath('message', 'Qty scan melebihi kebutuhan.');

        $this->actingAs($qcUser)
            ->postJson(route('mobile.qc.hold'), [
                'qc_id' => $qc->id,
                'reason_code' => 'stock_not_found',
                'reason' => 'Rak B kosong',
            ])
            ->assertOk()
            ->assertJsonPath('qc.audit.hold_reason', 'Stok tidak ditemukan di rak - Rak B kosong');
        $this->actingAs($qcUser)
            ->postJson(route('mobile.qc.reset'), ['qc_id' => $qc->id, 'reason_code' => 'wrong_item_picked'])
            ->assertOk()
            ->assertJsonPath('qc.audit.reset_reason', 'Isi resi tertukar / picker salah ambil')
            ->assertJsonPath('qc.summary.total_scanned', 0);

        $events = QcResiScanEvent::where('qc_resi_scan_id', $qc->id)->orderBy('id')->get();
        $this->assertSame(
            ['wrong_sku', 'unknown_barcode', 'wrong_sku', 'over_qty', 'hold', 'reset'],
            $events->pluck('event_type')->all()
        );
        $this->assertTrue($events->every(fn ($event) => $event->picker_employee_id === $picker->id
            && $event->resi_id === $resi->id
            && $event->created_by === $qcUser->id));

        [$firstWrong, $unknown, $secondWrong, $overQty, $hold, $reset] = $events->all();

        $this->assertSame('SKU-EVT-C', $firstWrong->sku);
        $this->assertNull($firstWrong->expected_sku);
        $this->assertCount(2, $firstWrong->meta['outstanding']);

        $this->assertNull($unknown->sku);
        $this->assertSame('BARCODE-TIDAK-ADA', $unknown->scan_code);

        $this->assertSame('SKU-EVT-C', $secondWrong->sku);
        $this->assertSame('SKU-EVT-B', $secondWrong->expected_sku);

        $this->assertSame('SKU-EVT-A', $overQty->sku);
        $this->assertSame(1, $overQty->qty);
        $this->assertSame(2, $overQty->expected_qty);
        $this->assertSame(2, $overQty->scanned_qty);

        $this->assertSame('stock_not_found', $hold->reason_code);
        $this->assertSame('Stok tidak ditemukan di rak - Rak B kosong', $hold->reason);
        $this->assertSame(3, $hold->expected_qty);
        $this->assertSame(2, $hold->scanned_qty);

        // Snapshot reset diambil sebelum qty scan dikosongkan.
        $this->assertSame('wrong_item_picked', $reset->reason_code);
        $this->assertTrue(QcReasonCategory::isPickerFault($reset->reason_code));
        $this->assertSame(2, $reset->scanned_qty);

        // Log barcode miss lama tetap berjalan untuk alur mapping barcode.
        $this->assertDatabaseHas('item_barcode_scan_misses', [
            'context' => 'qc_scan_sku',
            'source_id' => $qc->id,
            'scan_code' => 'BARCODE-TIDAK-ADA',
        ]);
    }

    public function test_hold_and_reset_require_category_valid_for_the_action(): void
    {
        $qcUser = $this->createQcUser();
        $picker = Employee::create([
            'employee_code' => 'PCK-RSN-001',
            'name' => 'Picker Alasan',
            'employment_status' => 'active',
        ]);
        Item::create(['sku' => 'SKU-RSN-A', 'name' => 'Item Alasan', 'category_id' => 0]);
        $resi = $this->createResiWithDetails('RESI-RSN-001', ['SKU-RSN-A' => 1]);

        $this->actingAs($qcUser)
            ->postJson(route('mobile.qc.scan'), [
                'type' => 'no_resi',
                'code' => $resi->no_resi,
                'picker_employee_id' => $picker->id,
            ])
            ->assertOk();
        $qc = QcResiScan::where('resi_id', $resi->id)->firstOrFail();

        // Tanpa kategori.
        $this->actingAs($qcUser)
            ->postJson(route('mobile.qc.hold'), ['qc_id' => $qc->id, 'reason' => 'Teks bebas saja'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason_code');

        // Kategori milik substitusi tidak berlaku untuk hold.
        $this->actingAs($qcUser)
            ->postJson(route('mobile.qc.hold'), ['qc_id' => $qc->id, 'reason_code' => 'buyer_request'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason_code');

        // "Lainnya" wajib disertai catatan.
        $this->actingAs($qcUser)
            ->postJson(route('mobile.qc.reset'), ['qc_id' => $qc->id, 'reason_code' => 'other'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');

        $this->assertSame(0, QcResiScanEvent::where('qc_resi_scan_id', $qc->id)->count());

        $this->actingAs($qcUser)
            ->postJson(route('mobile.qc.hold'), ['qc_id' => $qc->id, 'reason_code' => 'short_pick'])
            ->assertOk()
            ->assertJsonPath('qc.audit.hold_reason', 'Barang kurang diambil picker');

        $this->assertDatabaseHas('qc_resi_scan_events', [
            'qc_resi_scan_id' => $qc->id,
            'event_type' => 'hold',
            'reason_code' => 'short_pick',
            'picker_employee_id' => $picker->id,
        ]);
    }

    private function createQcUser(): User
    {
        $role = Role::firstOrCreate(['slug' => 'qc'], ['name' => 'QC', 'description' => 'qc']);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    private function createResiWithDetails(string $resiNo, array $details): Resi
    {
        $resi = Resi::create([
            'id_pesanan' => 'ORD-'.$resiNo,
            'tanggal_pesanan' => now()->toDateString(),
            'tanggal_upload' => now()->toDateString(),
            'no_resi' => $resiNo,
            'uploader_id' => User::factory()->create()->id,
            'status' => 'active',
        ]);

        foreach ($details as $sku => $qty) {
            ResiDetail::create(['resi_id' => $resi->id, 'sku' => $sku, 'qty' => $qty]);
        }

        return $resi;
    }
}
