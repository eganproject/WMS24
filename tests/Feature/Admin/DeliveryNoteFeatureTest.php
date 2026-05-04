<?php

namespace Tests\Feature\Admin;

use App\Models\Item;
use App\Models\OutboundItem;
use App\Models\OutboundTransaction;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryNoteFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_delivery_note_history_and_document_can_be_rendered(): void
    {
        $user = User::factory()->create();
        $warehouse = Warehouse::firstOrCreate(
            ['code' => 'GUDANG_BESAR'],
            ['name' => 'Gudang Besar', 'type' => 'main']
        );
        $item = Item::create([
            'sku' => 'SKU-SJ-001',
            'name' => 'Barang Surat Jalan',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
            'koli_qty' => 12,
        ]);

        $transaction = OutboundTransaction::create([
            'code' => 'OUT-MNL-SJ-001',
            'type' => 'manual',
            'ref_no' => 'REF-SJ-001',
            'surat_jalan_no' => 'SJ-OUT-MNL-TEST',
            'surat_jalan_at' => now()->toDateString(),
            'warehouse_id' => $warehouse->id,
            'transacted_at' => now(),
            'created_by' => $user->id,
            'status' => 'approved',
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        OutboundItem::create([
            'outbound_transaction_id' => $transaction->id,
            'item_id' => $item->id,
            'qty' => 24,
            'note' => 'Dua koli',
        ]);

        $this->withoutMiddleware();

        $this->actingAs($user)
            ->get(route('admin.outbound.delivery-notes.index'))
            ->assertOk()
            ->assertSee('History Surat Jalan');

        $this->actingAs($user)
            ->getJson(route('admin.outbound.delivery-notes.data', ['q' => 'SJ-OUT-MNL-TEST', 'search_mode' => 'exact']))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.surat_jalan_no', 'SJ-OUT-MNL-TEST')
            ->assertJsonPath('data.0.qty', 24);

        $this->actingAs($user)
            ->get(route('admin.outbound.delivery-notes.show', $transaction->id))
            ->assertOk()
            ->assertSee('SURAT JALAN')
            ->assertSee('SJ-OUT-MNL-TEST')
            ->assertSee('SKU-SJ-001')
            ->assertSee('Barang Surat Jalan');

        $this->actingAs($user)
            ->get(route('admin.outbound.delivery-notes.print', $transaction->id))
            ->assertOk()
            ->assertSee('window.print');
    }
}
