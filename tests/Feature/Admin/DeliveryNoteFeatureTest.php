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
            'recipient_name' => 'Penerima Test',
            'recipient_phone' => '0800000001',
            'recipient_address' => 'Jl. Surat Jalan No. 1',
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
            ->assertSee('Penerima Test')
            ->assertSee('Jl. Surat Jalan No. 1')
            ->assertSee('SKU-SJ-001')
            ->assertSee('Barang Surat Jalan');

        $this->actingAs($user)
            ->get(route('admin.outbound.delivery-notes.print', $transaction->id))
            ->assertOk()
            ->assertSee('window.print')
            ->assertSee('data-copy-label="ARSIP"', false)
            ->assertSee('data-copy-label="COPY"', false)
            ->assertSee('data-copy-label="ASLI"', false);
    }

    public function test_delivery_note_cannot_be_viewed_or_printed_before_outbound_is_approved(): void
    {
        $user = User::factory()->create();
        $warehouse = Warehouse::firstOrCreate(
            ['code' => 'GUDANG_BESAR'],
            ['name' => 'Gudang Besar', 'type' => 'main']
        );

        $transaction = OutboundTransaction::create([
            'code' => 'OUT-MNL-SJ-PENDING',
            'type' => 'manual',
            'surat_jalan_no' => 'SJ-OUT-PENDING',
            'warehouse_id' => $warehouse->id,
            'transacted_at' => now(),
            'created_by' => $user->id,
            'status' => 'pending',
        ]);

        $this->withoutMiddleware();

        $this->actingAs($user)
            ->get(route('admin.outbound.delivery-notes.show', $transaction->id))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('admin.outbound.delivery-notes.print', $transaction->id))
            ->assertForbidden();
    }

    public function test_delivery_note_can_be_viewed_and_printed_when_waiting_for_qc(): void
    {
        $user = User::factory()->create();
        $warehouse = Warehouse::firstOrCreate(
            ['code' => 'GUDANG_BESAR'],
            ['name' => 'Gudang Besar', 'type' => 'main']
        );

        $transaction = OutboundTransaction::create([
            'code' => 'OUT-MNL-SJ-QC',
            'type' => 'manual',
            'surat_jalan_no' => 'SJ-OUT-PENDING-QC',
            'warehouse_id' => $warehouse->id,
            'transacted_at' => now(),
            'created_by' => $user->id,
            'status' => 'pending_qc',
        ]);

        $this->withoutMiddleware();

        $this->actingAs($user)
            ->get(route('admin.outbound.delivery-notes.show', $transaction->id))
            ->assertOk()
            ->assertSee('SJ-OUT-PENDING-QC');

        $this->actingAs($user)
            ->get(route('admin.outbound.delivery-notes.print', $transaction->id))
            ->assertOk()
            ->assertSee('window.print');
    }
}
