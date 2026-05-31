<?php

namespace Tests\Feature\Mobile;

use App\Models\Item;
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
}
