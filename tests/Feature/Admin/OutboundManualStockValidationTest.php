<?php

namespace Tests\Feature\Admin;

use App\Models\Item;
use App\Models\ItemStock;
use App\Models\OutboundTransaction;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutboundManualStockValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_outbound_rejects_when_selected_warehouse_stock_is_short(): void
    {
        $warehouse = $this->createWarehouse('GUDANG_TEST_MANUAL');
        $user = User::factory()->create();
        $item = $this->createItem('SKU-MANUAL-STOCK');

        ItemStock::create([
            'item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'stock' => 4,
        ]);

        $response = $this->actingAs($user)
            ->withoutMiddleware()
            ->postJson(route('admin.outbound.manuals.store'), [
                'warehouse_id' => $warehouse->id,
                'transacted_at' => now()->format('Y-m-d H:i'),
                'items' => [
                    [
                        'item_id' => $item->id,
                        'qty' => 5,
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['qty'])
            ->assertJsonPath('errors.qty.0', 'Stok tidak mencukupi untuk SKU SKU-MANUAL-STOCK. Tersedia 4, dibutuhkan 5.');

        $this->assertDatabaseCount('outbound_transactions', 0);
    }

    public function test_manual_outbound_accepts_when_selected_warehouse_stock_is_enough(): void
    {
        $warehouse = $this->createWarehouse('GUDANG_TEST_MANUAL_OK');
        $user = User::factory()->create();
        $item = $this->createItem('SKU-MANUAL-OK');

        ItemStock::create([
            'item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'stock' => 5,
        ]);

        $this->actingAs($user)
            ->withoutMiddleware()
            ->postJson(route('admin.outbound.manuals.store'), [
                'warehouse_id' => $warehouse->id,
                'transacted_at' => now()->format('Y-m-d H:i'),
                'items' => [
                    [
                        'item_id' => $item->id,
                        'qty' => 5,
                    ],
                ],
            ])
            ->assertOk();

        $transaction = OutboundTransaction::firstOrFail();
        $this->assertSame('manual', $transaction->type);
        $this->assertSame($warehouse->id, (int) $transaction->warehouse_id);
        $this->assertSame('pending', $transaction->status);
    }

    private function createWarehouse(string $code): Warehouse
    {
        return Warehouse::create([
            'code' => $code,
            'name' => $code,
            'type' => 'display',
        ]);
    }

    private function createItem(string $sku): Item
    {
        return Item::create([
            'sku' => $sku,
            'name' => $sku,
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
        ]);
    }
}
