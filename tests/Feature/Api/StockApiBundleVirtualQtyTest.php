<?php

namespace Tests\Feature\Api;

use App\Models\Item;
use App\Models\ItemStock;
use App\Models\StockApiAllowedIp;
use App\Models\StockMutation;
use App\Models\Warehouse;
use App\Support\BundleService;
use App\Support\StockApiSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockApiBundleVirtualQtyTest extends TestCase
{
    use RefreshDatabase;

    public function test_bundle_qty_is_virtual_stock_from_components(): void
    {
        config([
            'stock_api.enabled' => true,
            'stock_api.token' => 'test-token',
            'inventory.default_warehouse_code' => 'GUDANG_BESAR',
            'inventory.display_warehouse_code' => 'GUDANG_DISPLAY',
        ]);

        $main = Warehouse::firstOrCreate(['code' => 'GUDANG_BESAR'], ['name' => 'Gudang Besar', 'type' => 'main']);
        $display = Warehouse::firstOrCreate(['code' => 'GUDANG_DISPLAY'], ['name' => 'Gudang Kecil', 'type' => 'display']);
        $damaged = Warehouse::firstOrCreate(['code' => 'GUDANG_RUSAK'], ['name' => 'Gudang Rusak', 'type' => 'damaged']);

        $a = Item::create(['sku' => 'KOMP-A', 'name' => 'Komponen A']);
        $b = Item::create(['sku' => 'KOMP-B', 'name' => 'Komponen B']);
        $bundle = Item::create(['sku' => 'ZBUNDLE-1', 'name' => 'Bundle', 'item_type' => Item::TYPE_BUNDLE]);
        BundleService::syncComponents($bundle, [
            ['component_item_id' => $a->id, 'required_qty' => 2],
            ['component_item_id' => $b->id, 'required_qty' => 1],
        ]);

        // Gudang besar: A=10 (5 set), B=3 (3 set) -> 3. Display: A=4 (2 set), B=9 -> 2. Rusak diabaikan.
        $stock = [[$a, $main, 10], [$b, $main, 3], [$a, $display, 4], [$b, $display, 9], [$a, $damaged, 50], [$b, $damaged, 50]];
        foreach ($stock as [$item, $warehouse, $qty]) {
            ItemStock::create(['item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'stock' => $qty]);
            StockMutation::create([
                'item_id' => $item->id,
                'warehouse_id' => $warehouse->id,
                'direction' => 'in',
                'qty' => $qty,
                'stock_before' => 0,
                'stock_after' => $qty,
                'source_type' => 'adjustment',
                'source_id' => $warehouse->id,
                'occurred_at' => now()->subDay(),
            ]);
        }

        foreach ([$a, $b, $bundle] as $item) {
            StockApiSyncService::syncItem($item->id);
        }
        StockApiAllowedIp::create(['ip_address' => '127.0.0.1', 'is_active' => true]);
        $headers = ['Authorization' => 'Bearer test-token'];

        $current = collect($this->getJson('/api/v1/stocks', $headers)->assertOk()->json('data'))->keyBy('sku');
        $this->assertEquals(5, $current['ZBUNDLE-1']['qty']);
        $this->assertSame(0, $current['ZBUNDLE-1']['min_qty']);
        $this->assertEquals(14, $current['KOMP-A']['qty']);

        $historical = collect($this->getJson('/api/v1/stocks?as_of='.now()->format('Y-m-d'), $headers)->assertOk()->json('data'))->keyBy('sku');
        $this->assertEquals(5, $historical['ZBUNDLE-1']['qty']);
        $this->assertSame(0, $historical['ZBUNDLE-1']['min_qty']);
        $this->assertEquals(12, $historical['KOMP-B']['qty']);

        $beforeStock = collect($this->getJson('/api/v1/stocks?as_of='.now()->subDays(3)->format('Y-m-d'), $headers)->assertOk()->json('data'))->keyBy('sku');
        $this->assertEquals(0, $beforeStock['ZBUNDLE-1']['qty']);
    }
}
