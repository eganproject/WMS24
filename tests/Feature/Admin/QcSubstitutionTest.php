<?php

namespace Tests\Feature\Admin;

use App\Http\Middleware\AuthorizeMenuPermission;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\Kurir;
use App\Models\PickingList;
use App\Models\PickingListException;
use App\Models\QcResiScan;
use App\Models\Resi;
use App\Models\ResiDetail;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QcSubstitutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_qc_substitution_keeps_original_order_and_moves_final_stock_by_qc_snapshot(): void
    {
        $this->withoutMiddleware(AuthorizeMenuPermission::class);

        $user = $this->createUserWithRole('admin');
        $display = Warehouse::firstOrCreate(['code' => 'GUDANG_DISPLAY'], ['name' => 'Gudang Display']);
        Warehouse::firstOrCreate(['code' => 'GUDANG_BESAR'], ['name' => 'Gudang Besar']);
        $kurir = Kurir::create(['name' => 'JNE']);

        $kab2 = Item::create(['sku' => 'KAB2', 'name' => 'Kabel 2', 'category_id' => 0]);
        $kab3 = Item::create(['sku' => 'KAB3', 'name' => 'Kabel 3', 'category_id' => 0]);
        ItemStock::create(['item_id' => $kab2->id, 'warehouse_id' => $display->id, 'stock' => 10]);
        ItemStock::create(['item_id' => $kab3->id, 'warehouse_id' => $display->id, 'stock' => 5]);
        PickingList::create([
            'list_date' => now()->toDateString(),
            'sku' => 'KAB2',
            'qty' => 2,
            'remaining_qty' => 2,
        ]);

        $resi = Resi::create([
            'id_pesanan' => 'ORD-SUB-001',
            'tanggal_pesanan' => now()->toDateString(),
            'tanggal_upload' => now()->toDateString(),
            'no_resi' => 'RESI-SUB-001',
            'kurir_id' => $kurir->id,
            'catatan_pembeli' => 'Tolong ganti 1 pcs KAB2 dengan KAB3',
            'uploader_id' => $user->id,
            'status' => 'active',
        ]);
        ResiDetail::create(['resi_id' => $resi->id, 'sku' => 'KAB2', 'qty' => 2]);

        $this->actingAs($user)
            ->postJson(route('admin.outbound.qc-scan.scan'), [
                'type' => 'no_resi',
                'code' => 'RESI-SUB-001',
            ])
            ->assertOk()
            ->assertJsonPath('resi.catatan_pembeli', 'Tolong ganti 1 pcs KAB2 dengan KAB3')
            ->assertJsonPath('qc.summary.total_expected', 2);

        $qc = QcResiScan::where('resi_id', $resi->id)->firstOrFail();

        $this->actingAs($user)
            ->postJson(route('admin.outbound.qc-scan.substitute'), [
                'qc_id' => $qc->id,
                'original_sku' => 'KAB2',
                'replacement_sku' => 'KAB3',
                'qty' => 1,
                'reason' => 'Sesuai catatan pembeli',
            ])
            ->assertOk()
            ->assertJsonPath('qc.summary.total_expected', 2)
            ->assertJsonPath('qc.substitutions.0.original_sku', 'KAB2')
            ->assertJsonPath('qc.substitutions.0.replacement_sku', 'KAB3');

        $this->assertDatabaseHas('resi_details', [
            'resi_id' => $resi->id,
            'sku' => 'KAB2',
            'qty' => 2,
        ]);
        $this->assertDatabaseHas('qc_resi_scan_items', [
            'qc_resi_scan_id' => $qc->id,
            'sku' => 'KAB2',
            'expected_qty' => 1,
        ]);
        $this->assertDatabaseHas('qc_resi_scan_items', [
            'qc_resi_scan_id' => $qc->id,
            'sku' => 'KAB3',
            'expected_qty' => 1,
        ]);

        $this->actingAs($user)
            ->postJson(route('admin.outbound.qc-scan.scan-sku'), [
                'qc_id' => $qc->id,
                'code' => 'KAB2',
                'qty' => 1,
            ])
            ->assertOk();

        $this->actingAs($user)
            ->postJson(route('admin.outbound.qc-scan.scan-sku'), [
                'qc_id' => $qc->id,
                'code' => 'KAB3',
                'qty' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('qc.summary.remaining', 0);

        $this->actingAs($user)
            ->postJson(route('admin.outbound.qc-scan.complete'), [
                'qc_id' => $qc->id,
            ])
            ->assertOk()
            ->assertJsonPath('qc.status', 'passed');

        $this->assertDatabaseHas('item_stocks', [
            'item_id' => $kab2->id,
            'warehouse_id' => $display->id,
            'stock' => 9,
        ]);
        $this->assertDatabaseHas('item_stocks', [
            'item_id' => $kab3->id,
            'warehouse_id' => $display->id,
            'stock' => 4,
        ]);
        $this->assertSame(0, (int) PickingList::where('sku', 'KAB2')->value('remaining_qty'));
        $this->assertSame(1, (int) PickingListException::where('sku', 'KAB3')->value('qty'));
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

        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role);

        return $user;
    }
}
