<?php

namespace Tests\Feature\Mobile;

use App\Models\Item;
use App\Models\ItemBarcode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockOpnameMobileSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_stock_opname_item_search_matches_exact_sku_only(): void
    {
        Item::create([
            'sku' => 'SO-SKU-001',
            'name' => 'Produk Tepat',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
            'safety_stock' => 0,
        ]);
        Item::create([
            'sku' => 'SO-SKU-001-EXTRA',
            'name' => 'Produk Mirip',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
            'safety_stock' => 0,
        ]);
        Item::create([
            'sku' => 'OTHER-SKU',
            'name' => 'SO-SKU-001',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
            'safety_stock' => 0,
        ]);

        $partialResponse = $this->withoutMiddleware()->getJson(route('opname.items.search', [
            'q' => 'SO-SKU',
        ]));

        $partialResponse->assertOk()
            ->assertJsonCount(0, 'items');

        $exactResponse = $this->withoutMiddleware()->getJson(route('opname.items.search', [
            'q' => 'so-sku-001',
        ]));

        $exactResponse->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.sku', 'SO-SKU-001');
    }

    public function test_mobile_stock_opname_item_search_matches_external_barcode_alias(): void
    {
        $item = Item::create([
            'sku' => 'SO-ALIAS-001',
            'name' => 'Produk Barcode Alias',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
            'safety_stock' => 0,
            'koli_qty' => 12,
        ]);

        ItemBarcode::create([
            'item_id' => $item->id,
            'barcode_value' => 'QR-SUPPLIER-001',
            'normalized_barcode' => 'qr-supplier-001',
            'normalized_hash' => hash('sha256', 'qr-supplier-001'),
            'is_active' => true,
        ]);

        $response = $this->withoutMiddleware()->getJson(route('opname.items.search', [
            'q' => 'qr-supplier-001',
        ]));

        $response->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.sku', 'SO-ALIAS-001')
            ->assertJsonPath('items.0.koli_qty', 12);
    }

    public function test_mobile_stock_opname_unknown_barcode_is_not_logged_during_live_search(): void
    {
        $response = $this->withoutMiddleware()->getJson(route('opname.items.search', [
            'q' => 'UNKNOWN-BC-001',
            'batch_code' => 'OPN-001',
        ]));

        $response->assertOk()
            ->assertJsonCount(0, 'items');

        $this->assertDatabaseMissing('item_barcode_scan_misses', [
            'context' => 'stock_opname',
            'scan_code' => 'UNKNOWN-BC-001',
        ]);
    }

    public function test_mobile_stock_opname_unknown_barcode_is_logged_on_final_scan(): void
    {
        $response = $this->withoutMiddleware()->getJson(route('opname.items.search', [
            'q' => 'UNKNOWN-BC-001',
            'batch_code' => 'OPN-001',
            'log_miss' => 1,
        ]));

        $response->assertOk()
            ->assertJsonCount(0, 'items');

        $this->assertDatabaseHas('item_barcode_scan_misses', [
            'context' => 'stock_opname',
            'scan_code' => 'UNKNOWN-BC-001',
            'normalized_hash' => hash('sha256', 'unknown-bc-001'),
            'source_code' => 'OPN-001',
            'scan_count' => 1,
        ]);
    }
}
