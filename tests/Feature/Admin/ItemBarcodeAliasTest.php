<?php

namespace Tests\Feature\Admin;

use App\Models\Item;
use App\Models\ItemBarcode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemBarcodeAliasTest extends TestCase
{
    use RefreshDatabase;

    public function test_item_can_store_unique_external_barcodes(): void
    {
        $response = $this->withoutMiddleware()->postJson(route('admin.masterdata.items.store'), [
            'sku' => 'ALIAS-SKU-001',
            'name' => 'Item Alias',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
            'external_barcodes' => [
                [
                    'barcode_value' => 'EXT-QR-001',
                    'source_name' => 'Supplier A',
                    'note' => 'QR dus',
                ],
            ],
        ]);

        $response->assertOk();

        $item = Item::where('sku', 'ALIAS-SKU-001')->firstOrFail();
        $this->assertDatabaseHas('item_barcodes', [
            'item_id' => $item->id,
            'barcode_value' => 'EXT-QR-001',
            'source_name' => 'Supplier A',
            'note' => 'QR dus',
            'is_active' => true,
        ]);
    }

    public function test_external_barcode_must_be_unique_across_items(): void
    {
        $first = Item::create([
            'sku' => 'ALIAS-SKU-002',
            'name' => 'Item Alias A',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
        ]);

        ItemBarcode::create([
            'item_id' => $first->id,
            'barcode_value' => 'EXT-DUP-001',
            'normalized_barcode' => 'ext-dup-001',
            'normalized_hash' => hash('sha256', 'ext-dup-001'),
            'is_active' => true,
        ]);

        $response = $this->withoutMiddleware()->postJson(route('admin.masterdata.items.store'), [
            'sku' => 'ALIAS-SKU-003',
            'name' => 'Item Alias B',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
            'external_barcodes' => [
                ['barcode_value' => 'ext-dup-001'],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('external_barcodes.0.barcode_value');
    }

    public function test_external_barcode_cannot_collide_with_other_item_sku(): void
    {
        Item::create([
            'sku' => 'REAL-SKU-001',
            'name' => 'Real SKU',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
        ]);

        $response = $this->withoutMiddleware()->postJson(route('admin.masterdata.items.store'), [
            'sku' => 'ALIAS-SKU-004',
            'name' => 'Item Alias C',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
            'external_barcodes' => [
                ['barcode_value' => 'real-sku-001'],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('external_barcodes.0.barcode_value');
    }
}
