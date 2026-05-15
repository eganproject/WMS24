<?php

namespace Tests\Feature\Admin;

use App\Models\Item;
use App\Models\ItemStock;
use App\Models\StockAdjustment;
use App\Models\StockMutation;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ItemStockDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_item_stock_data_marks_main_and_display_stock_that_are_below_active_safety(): void
    {
        $mainWarehouse = Warehouse::firstOrCreate([
            'code' => 'GUDANG_BESAR',
        ], [
            'name' => 'Gudang Besar',
            'type' => 'main',
        ]);
        $displayWarehouse = Warehouse::firstOrCreate([
            'code' => 'GUDANG_DISPLAY',
        ], [
            'name' => 'Gudang Display',
            'type' => 'display',
        ]);

        $lowItem = Item::create([
            'sku' => 'SKU-LOW-001',
            'name' => 'Item Low Stock',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
            'safety_stock' => 10,
            'koli_qty' => 6,
        ]);
        ItemStock::create([
            'item_id' => $lowItem->id,
            'warehouse_id' => $mainWarehouse->id,
            'stock' => 8,
            'safety_stock' => null,
        ]);
        ItemStock::create([
            'item_id' => $lowItem->id,
            'warehouse_id' => $displayWarehouse->id,
            'stock' => 5,
            'safety_stock' => 6,
        ]);

        $safeItem = Item::create([
            'sku' => 'SKU-SAFE-001',
            'name' => 'Item Aman',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
            'safety_stock' => 4,
        ]);
        ItemStock::create([
            'item_id' => $safeItem->id,
            'warehouse_id' => $mainWarehouse->id,
            'stock' => 4,
            'safety_stock' => null,
        ]);
        ItemStock::create([
            'item_id' => $safeItem->id,
            'warehouse_id' => $displayWarehouse->id,
            'stock' => 7,
            'safety_stock' => 6,
        ]);

        Item::create([
            'sku' => 'SKU-BUNDLE-001',
            'name' => 'Bundle Virtual',
            'item_type' => Item::TYPE_BUNDLE,
            'category_id' => 0,
            'safety_stock' => 9,
        ]);

        $response = $this->withoutMiddleware()->getJson(route('admin.inventory.item-stocks.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 25,
        ]));

        $response->assertOk();

        $rows = Collection::make($response->json('data'))->keyBy('sku');

        $lowRow = $rows->get('SKU-LOW-001');
        $this->assertNotNull($lowRow);
        $this->assertSame(10, $lowRow['safety_main']);
        $this->assertSame(6, $lowRow['safety_display']);
        $this->assertSame(6, $lowRow['koli_qty']);
        $this->assertSame(1, $lowRow['stock_main_koli']);
        $this->assertSame(2, $lowRow['stock_main_koli_remainder']);
        $this->assertTrue($lowRow['is_main_below_safety']);
        $this->assertTrue($lowRow['is_display_below_safety']);

        $safeRow = $rows->get('SKU-SAFE-001');
        $this->assertNotNull($safeRow);
        $this->assertFalse($safeRow['is_main_below_safety']);
        $this->assertFalse($safeRow['is_display_below_safety']);

        $bundleRow = $rows->get('SKU-BUNDLE-001');
        $this->assertNotNull($bundleRow);
        $this->assertSame(0, $bundleRow['koli_qty']);
        $this->assertNull($bundleRow['stock_main_koli']);
        $this->assertNull($bundleRow['stock_main_koli_remainder']);
        $this->assertFalse($bundleRow['is_main_below_safety']);
        $this->assertFalse($bundleRow['is_display_below_safety']);
    }

    public function test_item_stock_edit_adjustment_can_be_auto_approved(): void
    {
        $displayWarehouse = Warehouse::firstOrCreate([
            'code' => 'GUDANG_DISPLAY',
        ], [
            'name' => 'Gudang Display',
            'type' => 'display',
        ]);

        $item = Item::create([
            'sku' => 'SKU-AUTO-ADJ-001',
            'name' => 'Item Auto Adjustment',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
            'safety_stock' => 0,
        ]);

        ItemStock::create([
            'item_id' => $item->id,
            'warehouse_id' => $displayWarehouse->id,
            'stock' => 10,
        ]);

        $response = $this->withoutMiddleware()->postJson(route('admin.inventory.stock-adjustments.store'), [
            'auto_approve' => true,
            'warehouse_id' => $displayWarehouse->id,
            'transacted_at' => now()->format('Y-m-d H:i'),
            'note' => 'Edit stok dari halaman Item Stocks.',
            'items' => [
                [
                    'item_id' => $item->id,
                    'direction' => 'in',
                    'qty' => 5,
                    'note' => 'Set stok akhir 15 pcs',
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'approved');

        $this->assertSame(15, (int) ItemStock::where('item_id', $item->id)
            ->where('warehouse_id', $displayWarehouse->id)
            ->value('stock'));

        $adjustment = StockAdjustment::first();
        $this->assertNotNull($adjustment);
        $this->assertSame('approved', $adjustment->status);
        $this->assertNotNull($adjustment->approved_at);

        $mutation = StockMutation::where('source_type', 'adjustment')
            ->where('source_id', $adjustment->id)
            ->first();

        $this->assertNotNull($mutation);
        $this->assertSame('auto_approve', $mutation->source_subtype);
        $this->assertSame('in', $mutation->direction);
        $this->assertSame(5, (int) $mutation->qty);
    }
}
