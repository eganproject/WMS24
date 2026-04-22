<?php

namespace Tests\Feature\Admin;

use App\Models\Item;
use App\Models\ItemStock;
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
        $this->assertTrue($lowRow['is_main_below_safety']);
        $this->assertTrue($lowRow['is_display_below_safety']);

        $safeRow = $rows->get('SKU-SAFE-001');
        $this->assertNotNull($safeRow);
        $this->assertFalse($safeRow['is_main_below_safety']);
        $this->assertFalse($safeRow['is_display_below_safety']);

        $bundleRow = $rows->get('SKU-BUNDLE-001');
        $this->assertNotNull($bundleRow);
        $this->assertFalse($bundleRow['is_main_below_safety']);
        $this->assertFalse($bundleRow['is_display_below_safety']);
    }
}
