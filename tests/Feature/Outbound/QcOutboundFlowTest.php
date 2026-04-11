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
