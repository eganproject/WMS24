<?php

namespace Tests\Feature\Outbound;

use App\Models\Item;
use App\Models\ItemStock;
use App\Models\Kurir;
use App\Models\QcResiScan;
use App\Models\Resi;
use App\Models\ResiDetail;
use App\Models\Role;
use App\Models\StockMutation;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QcOutboundFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_outbound_flow_uses_qc_snapshot_until_scan_out(): void
    {
        [$displayWarehouse] = $this->createWarehouseFixtures();
        $qcUser = $this->createUserWithRole('qc');
        $scanOutUser = $this->createUserWithRole('admin-scan');
        $uploader = User::factory()->create();
        $kurir = Kurir::create(['name' => 'JNE']);
        $item = Item::create([
            'sku' => 'SKU-001',
            'name' => 'Item QC',
            'category_id' => 0,
        ]);

        ItemStock::create([
            'item_id' => $item->id,
            'warehouse_id' => $displayWarehouse->id,
            'stock' => 2,
        ]);

        $resi = $this->createResi($uploader->id, $kurir->id, 'ORD-001', 'RESI-001');
        ResiDetail::create([
            'resi_id' => $resi->id,
            'sku' => $item->sku,
            'qty' => 2,
        ]);

        $this->actingAs($qcUser)
            ->postJson(route('picker.qc.scan'), [
                'type' => 'no_resi',
                'code' => 'RESI-001',
            ])
            ->assertOk()
            ->assertJsonPath('qc.summary.total_expected', 2);

        $qc = QcResiScan::firstOrFail();

        $this->actingAs($qcUser)
            ->postJson(route('picker.qc.scan-sku'), [
                'qc_id' => $qc->id,
                'code' => 'SKU-001',
                'qty' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('qc.summary.total_scanned', 2);

        $this->actingAs($qcUser)
            ->postJson(route('picker.qc.complete'), [
                'qc_id' => $qc->id,
            ])
            ->assertOk()
            ->assertJsonPath('qc.status', 'passed');

        $qc->refresh();
        $this->assertSame('passed', $qc->status);
        $this->assertSame($qcUser->id, $qc->completed_by);
        $this->assertDatabaseHas('item_stocks', [
            'item_id' => $item->id,
            'warehouse_id' => $displayWarehouse->id,
            'stock' => 0,
        ]);
        $this->assertDatabaseHas('stock_mutations', [
            'item_id' => $item->id,
            'warehouse_id' => $displayWarehouse->id,
            'direction' => 'out',
            'qty' => 2,
            'source_type' => 'qc_shipment',
            'source_subtype' => 'resi',
            'source_id' => $qc->id,
        ]);

        ResiDetail::where('resi_id', $resi->id)->delete();
        ResiDetail::create([
            'resi_id' => $resi->id,
            'sku' => 'SKU-001',
            'qty' => 5,
        ]);

        $this->actingAs($scanOutUser)
            ->postJson(route('picker.scan-out.scan'), [
                'type' => 'no_resi',
                'code' => 'RESI-001',
            ])
            ->assertOk()
            ->assertJsonPath('resi.no_resi', 'RESI-001');

        $this->assertDatabaseHas('shipment_scan_outs', [
            'resi_id' => $resi->id,
            'scanned_by' => $scanOutUser->id,
            'kurir_id' => $kurir->id,
        ]);
    }

    public function test_scan_out_before_qc_fails_and_qc_after_pass_allows_scan_out(): void
    {
        [$displayWarehouse] = $this->createWarehouseFixtures();
        $qcUser = $this->createUserWithRole('qc');
        $scanOutUser = $this->createUserWithRole('admin-scan');
        $uploader = User::factory()->create();
        $kurir = Kurir::create(['name' => 'SiCepat']);
        $item = Item::create([
            'sku' => 'SKU-002',
            'name' => 'Item Guard',
            'category_id' => 0,
        ]);

        ItemStock::create([
            'item_id' => $item->id,
            'warehouse_id' => $displayWarehouse->id,
            'stock' => 1,
        ]);

        $resi = $this->createResi($uploader->id, $kurir->id, 'ORD-002', 'RESI-002');
        ResiDetail::create([
            'resi_id' => $resi->id,
            'sku' => $item->sku,
            'qty' => 1,
        ]);

        $this->actingAs($scanOutUser)
            ->postJson(route('picker.scan-out.scan'), [
                'type' => 'no_resi',
                'code' => 'RESI-002',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Resi belum lolos QC dan belum siap scan out.');

        $this->actingAs($qcUser)
            ->postJson(route('picker.qc.scan'), [
                'type' => 'no_resi',
                'code' => 'RESI-002',
            ])
            ->assertOk();

        $qc = QcResiScan::where('resi_id', $resi->id)->firstOrFail();

        $this->actingAs($qcUser)
            ->postJson(route('picker.qc.scan-sku'), [
                'qc_id' => $qc->id,
                'code' => 'SKU-002',
                'qty' => 1,
            ])
            ->assertOk();

        $this->actingAs($qcUser)
            ->postJson(route('picker.qc.complete'), [
                'qc_id' => $qc->id,
            ])
            ->assertOk()
            ->assertJsonPath('qc.status', 'passed');

        $this->actingAs($scanOutUser)
            ->postJson(route('picker.scan-out.scan'), [
                'type' => 'no_resi',
                'code' => 'RESI-002',
            ])
            ->assertOk();
    }

    public function test_qc_hold_reservation_blocks_other_qc_until_reset_releases_it(): void
    {
        [$displayWarehouse] = $this->createWarehouseFixtures();
        $qcUser = $this->createUserWithRole('qc');
        $uploader = User::factory()->create();
        $kurir = Kurir::create(['name' => 'Ninja']);
        $item = Item::create([
            'sku' => 'SKU-HOLD-001',
            'name' => 'Item Hold QC',
            'category_id' => 0,
        ]);

        ItemStock::create([
            'item_id' => $item->id,
            'warehouse_id' => $displayWarehouse->id,
            'stock' => 1,
        ]);

        $resiA = $this->createResi($uploader->id, $kurir->id, 'ORD-HOLD-001', 'RESI-HOLD-001');
        $resiB = $this->createResi($uploader->id, $kurir->id, 'ORD-HOLD-002', 'RESI-HOLD-002');

        ResiDetail::create([
            'resi_id' => $resiA->id,
            'sku' => $item->sku,
            'qty' => 1,
        ]);
        ResiDetail::create([
            'resi_id' => $resiB->id,
            'sku' => $item->sku,
            'qty' => 1,
        ]);

        $this->actingAs($qcUser)
            ->postJson(route('picker.qc.scan'), [
                'type' => 'no_resi',
                'code' => $resiA->no_resi,
            ])
            ->assertOk();

        $qcA = QcResiScan::where('resi_id', $resiA->id)->firstOrFail();

        $this->actingAs($qcUser)
            ->postJson(route('picker.qc.scan-sku'), [
                'qc_id' => $qcA->id,
                'code' => $item->sku,
                'qty' => 1,
            ])
            ->assertOk();

        $this->actingAs($qcUser)
            ->postJson(route('picker.qc.hold'), [
                'qc_id' => $qcA->id,
                'reason' => 'Menunggu pengecekan fisik',
            ])
            ->assertOk()
            ->assertJsonPath('qc.status', 'hold');

        $this->actingAs($qcUser)
            ->postJson(route('picker.qc.scan'), [
                'type' => 'no_resi',
                'code' => $resiB->no_resi,
            ])
            ->assertOk();

        $qcB = QcResiScan::where('resi_id', $resiB->id)->firstOrFail();

        $this->actingAs($qcUser)
            ->postJson(route('picker.qc.scan-sku'), [
                'qc_id' => $qcB->id,
                'code' => $item->sku,
                'qty' => 1,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Stok display tidak mencukupi untuk scan SKU ini.')
            ->assertJsonPath('details.0.available', 0);

        $this->actingAs($qcUser)
            ->postJson(route('picker.qc.reset'), [
                'qc_id' => $qcA->id,
                'reason' => 'Alokasi dibatalkan',
            ])
            ->assertOk()
            ->assertJsonPath('qc.status', 'draft')
            ->assertJsonPath('qc.summary.total_scanned', 0);

        $this->actingAs($qcUser)
            ->postJson(route('picker.qc.scan-sku'), [
                'qc_id' => $qcB->id,
                'code' => $item->sku,
                'qty' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('qc.summary.total_scanned', 1);

        $this->assertDatabaseHas('item_stocks', [
            'item_id' => $item->id,
            'warehouse_id' => $displayWarehouse->id,
            'stock' => 1,
        ]);
    }

    public function test_operational_mobile_routes_are_enforced_per_role(): void
    {
        $pickerUser = $this->createUserWithRole('picker');
        $qcUser = $this->createUserWithRole('qc');
        $scanOutUser = $this->createUserWithRole('admin-scan');
        $inboundScanUser = $this->createUserWithRole('inbound-scan');

        $this->actingAs($pickerUser)
            ->get(route('picker.picking-list.index'))
            ->assertOk();

        $this->actingAs($qcUser)
            ->get(route('picker.qc.index'))
            ->assertOk();

        $this->actingAs($scanOutUser)
            ->get(route('picker.scan-out.index'))
            ->assertOk();

        $this->actingAs($inboundScanUser)
            ->get(route('picker.inbound-scan.index'))
            ->assertOk();

        $this->actingAs($qcUser)
            ->get(route('picker.scan-out.index'))
            ->assertRedirect(route('picker.dashboard'));

        $this->actingAs($scanOutUser)
            ->postJson(route('picker.qc.scan'), [
                'type' => 'no_resi',
                'code' => 'FORBIDDEN',
            ])
            ->assertForbidden();

        $this->actingAs($pickerUser)
            ->postJson(route('picker.scan-out.scan'), [
                'type' => 'no_resi',
                'code' => 'FORBIDDEN',
            ])
            ->assertForbidden();
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

        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    private function createWarehouseFixtures(): array
    {
        $default = Warehouse::firstOrCreate(
            ['code' => 'GUDANG_BESAR'],
            ['name' => 'Gudang Besar']
        );

        $display = Warehouse::firstOrCreate(
            ['code' => 'GUDANG_DISPLAY'],
            ['name' => 'Gudang Display']
        );

        return [$display, $default];
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
