<?php

namespace Tests\Feature\Api;

use App\Models\Item;
use App\Models\ItemStock;
use App\Models\StockApiAllowedIp;
use App\Models\StockMutation;
use App\Models\Warehouse;
use App\Support\StockApiSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockApiWarehouseScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_and_historical_qty_only_include_main_and_display_warehouses(): void
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
        $other = Warehouse::create(['code' => 'GUDANG_LAIN', 'name' => 'Gudang Lain', 'type' => 'other']);
        $item = Item::create(['sku' => 'SKU-API-1', 'name' => 'Barang API']);

        foreach ([[$main, 10], [$display, 4], [$damaged, 3], [$other, 8]] as [$warehouse, $qty]) {
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

        StockApiSyncService::syncItem($item->id);
        StockApiAllowedIp::create(['ip_address' => '127.0.0.1', 'is_active' => true]);

        $headers = ['Authorization' => 'Bearer test-token'];
        $this->getJson('/api/v1/stocks', $headers)
            ->assertOk()
            ->assertJsonPath('data.0.qty', 14);

        $this->getJson('/api/v1/stocks?as_of='.now()->format('Y-m-d'), $headers)
            ->assertOk()
            ->assertJsonPath('data.0.qty', 14);
    }
}
