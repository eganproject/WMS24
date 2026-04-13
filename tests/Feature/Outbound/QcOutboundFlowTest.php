<?php

namespace Tests\Feature\Outbound;

use App\Models\Item;
use App\Models\Kurir;
use App\Models\PickerTransitItem;
use App\Models\QcResiScan;
use App\Models\Resi;
use App\Models\ResiDetail;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QcOutboundFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_outbound_flow_uses_qc_snapshot_until_scan_out(): void
    {
        $qcUser = $this->createUserWithRole('qc');
        $packerUser = $this->createUserWithRole('packer');
        $scanOutUser = $this->createUserWithRole('admin-scan');
        $uploader = User::factory()->create();
        $kurir = Kurir::create(['name' => 'JNE']);
        $item = Item::create([
            'sku' => 'SKU-001',
            'name' => 'Item QC',
            'category_id' => 0,
        ]);

        $resi = Resi::create([
            'id_pesanan' => 'ORD-001',
            'tanggal_pesanan' => now()->toDateString(),
            'tanggal_upload' => now()->toDateString(),
            'no_resi' => 'RESI-001',
            'kurir_id' => $kurir->id,
            'uploader_id' => $uploader->id,
            'status' => 'active',
        ]);

        ResiDetail::create([
            'resi_id' => $resi->id,
            'sku' => $item->sku,
            'qty' => 2,
        ]);

        PickerTransitItem::create([
            'item_id' => $item->id,
            'picked_date' => now()->toDateString(),
            'qty' => 2,
            'remaining_qty' => 2,
            'picked_at' => now(),
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
        $this->assertSame(0, (int) PickerTransitItem::firstOrFail()->remaining_qty);

        ResiDetail::where('resi_id', $resi->id)->delete();
        ResiDetail::create([
            'resi_id' => $resi->id,
            'sku' => 'SKU-001',
            'qty' => 5,
        ]);

        $this->actingAs($packerUser)
            ->postJson(route('picker.packer.scan'), [
                'type' => 'no_resi',
                'code' => 'RESI-001',
            ])
            ->assertOk()
            ->assertJsonPath('items.0.sku', 'SKU-001')
            ->assertJsonPath('items.0.qty', 2);

        $this->assertDatabaseHas('packer_resi_scans', [
            'resi_id' => $resi->id,
            'scanned_by' => $packerUser->id,
        ]);
        $this->assertDatabaseHas('packer_transit_histories', [
            'resi_id' => $resi->id,
            'status' => 'menunggu scan out',
        ]);
        $this->assertSame(0, (int) PickerTransitItem::firstOrFail()->remaining_qty);

        $this->actingAs($scanOutUser)
            ->postJson(route('picker.scan-out.scan'), [
                'type' => 'no_resi',
                'code' => 'RESI-001',
            ])
            ->assertOk()
            ->assertJsonPath('resi.no_resi', 'RESI-001');

        $this->assertDatabaseHas('packer_scan_outs', [
            'resi_id' => $resi->id,
            'scanned_by' => $scanOutUser->id,
            'kurir_id' => $kurir->id,
        ]);
        $this->assertDatabaseHas('packer_transit_histories', [
            'resi_id' => $resi->id,
            'status' => 'selesai',
        ]);
    }

    public function test_packer_scan_before_qc_fails_without_creating_scan_and_can_succeed_after_qc(): void
    {
        $qcUser = $this->createUserWithRole('qc');
        $packerUser = $this->createUserWithRole('packer');
        $uploader = User::factory()->create();
        $kurir = Kurir::create(['name' => 'SiCepat']);
        $item = Item::create([
            'sku' => 'SKU-002',
            'name' => 'Item Packer Guard',
            'category_id' => 0,
        ]);

        $resi = Resi::create([
            'id_pesanan' => 'ORD-002',
            'tanggal_pesanan' => now()->toDateString(),
            'tanggal_upload' => now()->toDateString(),
            'no_resi' => 'RESI-002',
            'kurir_id' => $kurir->id,
            'uploader_id' => $uploader->id,
            'status' => 'active',
        ]);

        ResiDetail::create([
            'resi_id' => $resi->id,
            'sku' => $item->sku,
            'qty' => 1,
        ]);

        PickerTransitItem::create([
            'item_id' => $item->id,
            'picked_date' => now()->toDateString(),
            'qty' => 1,
            'remaining_qty' => 1,
            'picked_at' => now(),
        ]);

        $this->actingAs($packerUser)
            ->postJson(route('picker.packer.scan'), [
                'type' => 'no_resi',
                'code' => 'RESI-002',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Resi belum QC selesai.');

        $this->assertDatabaseMissing('packer_resi_scans', [
            'resi_id' => $resi->id,
        ]);

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

        $this->actingAs($packerUser)
            ->postJson(route('picker.packer.scan'), [
                'type' => 'no_resi',
                'code' => 'RESI-002',
            ])
            ->assertOk()
            ->assertJsonPath('resi.no_resi', 'RESI-002');

        $this->assertDatabaseHas('packer_resi_scans', [
            'resi_id' => $resi->id,
            'scanned_by' => $packerUser->id,
        ]);
    }

    public function test_scan_sku_blocks_when_picker_transit_available_qty_is_exhausted(): void
    {
        $qcUser = $this->createUserWithRole('qc');
        $uploader = User::factory()->create();
        $kurir = Kurir::create(['name' => 'JNT']);
        $item = Item::create([
            'sku' => 'SKU-TRANSIT-001',
            'name' => 'Item Transit QC',
            'category_id' => 0,
        ]);

        $resi = Resi::create([
            'id_pesanan' => 'ORD-TRANSIT-001',
            'tanggal_pesanan' => now()->toDateString(),
            'tanggal_upload' => now()->toDateString(),
            'no_resi' => 'RESI-TRANSIT-001',
            'kurir_id' => $kurir->id,
            'uploader_id' => $uploader->id,
            'status' => 'active',
        ]);

        ResiDetail::create([
            'resi_id' => $resi->id,
            'sku' => $item->sku,
            'qty' => 2,
        ]);

        PickerTransitItem::create([
            'item_id' => $item->id,
            'picked_date' => now()->toDateString(),
            'qty' => 1,
            'remaining_qty' => 1,
            'picked_at' => now(),
        ]);

        $this->actingAs($qcUser)
            ->postJson(route('picker.qc.scan'), [
                'type' => 'no_resi',
                'code' => $resi->no_resi,
            ])
            ->assertOk();

        $qc = QcResiScan::where('resi_id', $resi->id)->firstOrFail();

        $this->actingAs($qcUser)
            ->postJson(route('picker.qc.scan-sku'), [
                'qc_id' => $qc->id,
                'code' => $item->sku,
                'qty' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('qc.summary.total_scanned', 1);

        $this->actingAs($qcUser)
            ->postJson(route('picker.qc.scan-sku'), [
                'qc_id' => $qc->id,
                'code' => $item->sku,
                'qty' => 1,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Sisa transit picker tidak mencukupi untuk scan SKU ini.')
            ->assertJsonPath('details.0.sku', $item->sku)
            ->assertJsonPath('details.0.required', 1)
            ->assertJsonPath('details.0.available', 0);

        $this->assertSame(1, (int) PickerTransitItem::firstOrFail()->remaining_qty);
    }

    public function test_qc_hold_reservation_blocks_other_qc_until_reset_releases_it(): void
    {
        $qcUser = $this->createUserWithRole('qc');
        $uploader = User::factory()->create();
        $kurir = Kurir::create(['name' => 'Ninja']);
        $item = Item::create([
            'sku' => 'SKU-HOLD-001',
            'name' => 'Item Hold QC',
            'category_id' => 0,
        ]);

        $resiA = Resi::create([
            'id_pesanan' => 'ORD-HOLD-001',
            'tanggal_pesanan' => now()->toDateString(),
            'tanggal_upload' => now()->toDateString(),
            'no_resi' => 'RESI-HOLD-001',
            'kurir_id' => $kurir->id,
            'uploader_id' => $uploader->id,
            'status' => 'active',
        ]);
        $resiB = Resi::create([
            'id_pesanan' => 'ORD-HOLD-002',
            'tanggal_pesanan' => now()->toDateString(),
            'tanggal_upload' => now()->toDateString(),
            'no_resi' => 'RESI-HOLD-002',
            'kurir_id' => $kurir->id,
            'uploader_id' => $uploader->id,
            'status' => 'active',
        ]);

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

        PickerTransitItem::create([
            'item_id' => $item->id,
            'picked_date' => now()->toDateString(),
            'qty' => 1,
            'remaining_qty' => 1,
            'picked_at' => now(),
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
                'reason' => 'Transit diparkir sementara',
            ])
            ->assertOk()
            ->assertJsonPath('qc.status', 'hold')
            ->assertJsonPath('qc.audit.hold_reason', 'Transit diparkir sementara');

        $this->assertDatabaseHas('qc_resi_scans', [
            'id' => $qcA->id,
            'status' => 'hold',
            'hold_by' => $qcUser->id,
            'hold_reason' => 'Transit diparkir sementara',
        ]);

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
            ->assertJsonPath('message', 'Sisa transit picker tidak mencukupi untuk scan SKU ini.')
            ->assertJsonPath('details.0.available', 0);

        $this->actingAs($qcUser)
            ->postJson(route('picker.qc.reset'), [
                'qc_id' => $qcA->id,
                'reason' => 'Transit dilepas untuk resi lain',
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

        $this->assertSame(1, (int) PickerTransitItem::firstOrFail()->remaining_qty);
    }

    public function test_qc_can_be_held_resumed_and_only_then_forwarded_to_packer(): void
    {
        $qcUser = $this->createUserWithRole('qc');
        $packerUser = $this->createUserWithRole('packer');
        $uploader = User::factory()->create();
        $kurir = Kurir::create(['name' => 'AnterAja']);
        $item = Item::create([
            'sku' => 'SKU-HOLD-RESUME-001',
            'name' => 'Item Hold Resume',
            'category_id' => 0,
        ]);

        $resi = Resi::create([
            'id_pesanan' => 'ORD-HOLD-RESUME-001',
            'tanggal_pesanan' => now()->toDateString(),
            'tanggal_upload' => now()->toDateString(),
            'no_resi' => 'RESI-HOLD-RESUME-001',
            'kurir_id' => $kurir->id,
            'uploader_id' => $uploader->id,
            'status' => 'active',
        ]);

        ResiDetail::create([
            'resi_id' => $resi->id,
            'sku' => $item->sku,
            'qty' => 2,
        ]);

        $transit = PickerTransitItem::create([
            'item_id' => $item->id,
            'picked_date' => now()->toDateString(),
            'qty' => 1,
            'remaining_qty' => 1,
            'picked_at' => now(),
        ]);

        $this->actingAs($qcUser)
            ->postJson(route('picker.qc.scan'), [
                'type' => 'no_resi',
                'code' => $resi->no_resi,
            ])
            ->assertOk();

        $qc = QcResiScan::where('resi_id', $resi->id)->firstOrFail();

        $this->actingAs($qcUser)
            ->postJson(route('picker.qc.scan-sku'), [
                'qc_id' => $qc->id,
                'code' => $item->sku,
                'qty' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('qc.summary.total_scanned', 1);

        $this->actingAs($qcUser)
            ->postJson(route('picker.qc.hold'), [
                'qc_id' => $qc->id,
                'reason' => 'Menunggu tambahan transit',
            ])
            ->assertOk()
            ->assertJsonPath('qc.status', 'hold');

        $this->actingAs($packerUser)
            ->postJson(route('picker.packer.scan'), [
                'type' => 'no_resi',
                'code' => $resi->no_resi,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Resi belum QC selesai.');

        $transit->update([
            'qty' => 2,
            'remaining_qty' => 2,
        ]);

        $this->actingAs($qcUser)
            ->postJson(route('picker.qc.scan'), [
                'type' => 'no_resi',
                'code' => $resi->no_resi,
            ])
            ->assertOk()
            ->assertJsonPath('qc.status', 'hold');

        $this->actingAs($qcUser)
            ->postJson(route('picker.qc.scan-sku'), [
                'qc_id' => $qc->id,
                'code' => $item->sku,
                'qty' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('qc.status', 'draft')
            ->assertJsonPath('qc.summary.total_scanned', 2);

        $this->actingAs($qcUser)
            ->postJson(route('picker.qc.complete'), [
                'qc_id' => $qc->id,
            ])
            ->assertOk()
            ->assertJsonPath('qc.status', 'passed');

        $this->assertSame(0, (int) PickerTransitItem::firstOrFail()->remaining_qty);

        $this->actingAs($packerUser)
            ->postJson(route('picker.packer.scan'), [
                'type' => 'no_resi',
                'code' => $resi->no_resi,
            ])
            ->assertOk()
            ->assertJsonPath('resi.no_resi', $resi->no_resi);
    }

    public function test_operational_mobile_routes_are_enforced_per_role(): void
    {
        $qcUser = $this->createUserWithRole('qc');
        $packerUser = $this->createUserWithRole('packer');
        $scanOutUser = $this->createUserWithRole('admin-scan');

        $this->actingAs($qcUser)
            ->get(route('picker.qc.index'))
            ->assertOk();

        $this->actingAs($packerUser)
            ->get(route('picker.packer.index'))
            ->assertOk();

        $this->actingAs($scanOutUser)
            ->get(route('picker.scan-out.index'))
            ->assertOk();

        $this->actingAs($qcUser)
            ->postJson(route('picker.packer.scan'), [
                'type' => 'no_resi',
                'code' => 'FORBIDDEN',
            ])
            ->assertForbidden();

        $this->actingAs($packerUser)
            ->postJson(route('picker.qc.scan'), [
                'type' => 'no_resi',
                'code' => 'FORBIDDEN',
            ])
            ->assertForbidden();

        $this->actingAs($scanOutUser)
            ->postJson(route('picker.packer.scan'), [
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
}
