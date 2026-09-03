<?php

namespace Tests\Feature\Admin;

use App\Models\InboundItem;
use App\Models\InboundTransaction;
use App\Models\Item;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\InboundScanStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InboundReturnListUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_return_page_uses_the_readable_item_card_and_wide_detail_modal(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->withoutMiddleware()
            ->get(route('admin.inbound.returns.index'));

        $response->assertOk();
        $response->assertSee('return-in-list-card', false);
        $response->assertSee('return-in-table', false);
        $response->assertSee('Ringkasan Item Retur');
        $response->assertSee('Dokumen Retur');
        $response->assertSee('Gudang &amp; Referensi', false);
        $response->assertSee('Rincian Item Retur');
        $response->assertSee('modal-xl', false);
        $response->assertSee('const enhancedItemList = true;', false);
        $response->assertSee('return-in-item-card__body', false);
        $response->assertSee('return-in-action-cell', false);
        $response->assertSee("{ data: 'id', visible: !enhancedItemList }", false);
        $response->assertSee("{ data: 'note', visible: !enhancedItemList", false);
        $response->assertDontSee('min-width: 1480px', false);
        $response->assertSee('Buka seluruh rincian item retur');
    }

    public function test_enhanced_list_is_scoped_to_inbound_returns(): void
    {
        $this->actingAs(User::factory()->create())
            ->withoutMiddleware()
            ->get(route('admin.inbound.manuals.index'))
            ->assertOk()
            ->assertSee('const enhancedItemList = false;', false)
            ->assertDontSee('<div class="card return-in-list-card">', false)
            ->assertDontSee('Ringkasan Item Retur');
    }

    public function test_return_data_provides_clear_item_identity_units_quantities_and_notes(): void
    {
        $warehouse = Warehouse::firstOrCreate(['code' => 'GUDANG_BESAR'], [
            'name' => 'Gudang Besar',
            'type' => 'main',
        ]);
        $firstItem = $this->item('SKU-RET-001', 'Produk Retur Pertama', 12);
        $secondItem = $this->item('SKU-RET-002', 'Produk Retur Kedua', 6);
        $transaction = InboundTransaction::create([
            'code' => 'RET-IN-UI-001',
            'type' => 'return',
            'warehouse_id' => $warehouse->id,
            'transacted_at' => now(),
            'status' => InboundScanStatus::PENDING_SCAN,
            'note' => 'Periksa kondisi kemasan',
        ]);
        InboundItem::create([
            'inbound_transaction_id' => $transaction->id,
            'item_id' => $firstItem->id,
            'input_unit' => 'koli',
            'koli' => 2,
            'qty' => 24,
            'note' => 'Dus penyok',
        ]);
        InboundItem::create([
            'inbound_transaction_id' => $transaction->id,
            'item_id' => $secondItem->id,
            'input_unit' => 'pcs',
            'koli' => 0,
            'qty' => 5,
        ]);

        $this->actingAs(User::factory()->create())
            ->withoutMiddleware()
            ->getJson(route('admin.inbound.returns.data', ['start' => 0, 'length' => 10]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(2, 'data.0.item_details')
            ->assertJsonPath('data.0.sku_count', 2)
            ->assertJsonPath('data.0.qty', 29)
            ->assertJsonPath('data.0.item_details.0.sku', 'SKU-RET-001')
            ->assertJsonPath('data.0.item_details.0.name', 'Produk Retur Pertama')
            ->assertJsonPath('data.0.item_details.0.input_unit', 'koli')
            ->assertJsonPath('data.0.item_details.0.koli', 2)
            ->assertJsonPath('data.0.item_details.0.qty', 24)
            ->assertJsonPath('data.0.item_details.0.note', 'Dus penyok')
            ->assertJsonPath('data.0.item_details.1.input_unit', 'pcs')
            ->assertJsonPath('data.0.item_details.1.qty', 5);
    }

    private function item(string $sku, string $name, int $koliQty): Item
    {
        return Item::create([
            'sku' => $sku,
            'name' => $name,
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
            'koli_qty' => $koliQty,
        ]);
    }
}
