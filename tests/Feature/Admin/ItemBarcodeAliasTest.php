<?php

namespace Tests\Feature\Admin;

use App\Models\Item;
use App\Models\ItemBarcode;
use App\Models\ItemBarcodeScanMiss;
use App\Imports\ItemBarcodesImport;
use App\Imports\ItemsImport;
use Illuminate\Support\Collection;
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

    public function test_item_sku_update_cannot_collide_with_other_item_barcode_alias(): void
    {
        $first = Item::create([
            'sku' => 'ALIAS-SKU-005',
            'name' => 'Item Alias D',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
        ]);
        $second = Item::create([
            'sku' => 'ALIAS-SKU-006',
            'name' => 'Item Alias E',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
        ]);

        ItemBarcode::create([
            'item_id' => $first->id,
            'barcode_value' => 'EXT-SKU-COLLIDE-001',
            'normalized_barcode' => 'ext-sku-collide-001',
            'normalized_hash' => hash('sha256', 'ext-sku-collide-001'),
            'is_active' => true,
        ]);

        $response = $this->withoutMiddleware()->putJson(route('admin.masterdata.items.update', $second), [
            'sku' => 'ext-sku-collide-001',
            'name' => $second->name,
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('sku');
    }

    public function test_item_barcode_import_creates_and_updates_aliases(): void
    {
        $item = Item::create([
            'sku' => 'ALIAS-IMPORT-001',
            'name' => 'Item Import Alias',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
        ]);

        $import = new ItemBarcodesImport();
        $import->collection(new Collection([
            new Collection([
                'sku' => 'alias-import-001',
                'barcode' => 'IMPORT-BC-001',
                'source_name' => 'Supplier Import',
                'note' => 'Awal',
            ]),
        ]));

        $this->assertSame(1, $import->created);
        $this->assertDatabaseHas('item_barcodes', [
            'item_id' => $item->id,
            'barcode_value' => 'IMPORT-BC-001',
            'source_name' => 'Supplier Import',
            'note' => 'Awal',
        ]);

        $secondImport = new ItemBarcodesImport();
        $secondImport->collection(new Collection([
            new Collection([
                'sku' => 'ALIAS-IMPORT-001',
                'barcode' => 'import-bc-001',
                'source_name' => 'Supplier Import Update',
                'note' => 'Update',
            ]),
        ]));

        $this->assertSame(1, $secondImport->updated);
        $this->assertDatabaseCount('item_barcodes', 1);
        $this->assertDatabaseHas('item_barcodes', [
            'item_id' => $item->id,
            'barcode_value' => 'import-bc-001',
            'source_name' => 'Supplier Import Update',
            'note' => 'Update',
        ]);
    }

    public function test_items_import_with_empty_barcode_column_keeps_existing_aliases(): void
    {
        $item = Item::create([
            'sku' => 'ALIAS-KEEP-001',
            'name' => 'Item Keep Alias',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
        ]);

        ItemBarcode::create([
            'item_id' => $item->id,
            'barcode_value' => 'KEEP-BC-001',
            'normalized_barcode' => 'keep-bc-001',
            'normalized_hash' => hash('sha256', 'keep-bc-001'),
            'is_active' => true,
        ]);

        $import = new ItemsImport();
        $import->collection(new Collection([
            new Collection([
                'sku' => 'ALIAS-KEEP-001',
                'name' => 'Item Keep Alias Updated',
                'external_barcodes' => '',
            ]),
        ]));

        $this->assertDatabaseHas('items', [
            'id' => $item->id,
            'name' => 'Item Keep Alias Updated',
        ]);
        $this->assertDatabaseHas('item_barcodes', [
            'item_id' => $item->id,
            'barcode_value' => 'KEEP-BC-001',
        ]);
    }

    public function test_unmatched_barcode_scan_can_be_resolved_into_item_alias(): void
    {
        $item = Item::create([
            'sku' => 'ALIAS-RESOLVE-001',
            'name' => 'Item Resolve Alias',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
        ]);

        $miss = ItemBarcodeScanMiss::create([
            'context' => 'stock_opname',
            'scan_code' => 'MISS-BC-001',
            'normalized_code' => 'miss-bc-001',
            'normalized_hash' => hash('sha256', 'miss-bc-001'),
            'scan_count' => 2,
            'last_scanned_at' => now(),
        ]);

        $response = $this->withoutMiddleware()->postJson(route('admin.masterdata.items.barcode-misses.resolve', $miss), [
            'item_id' => $item->id,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('item_barcodes', [
            'item_id' => $item->id,
            'barcode_value' => 'MISS-BC-001',
        ]);
        $this->assertDatabaseHas('item_barcode_scan_misses', [
            'id' => $miss->id,
            'resolved_item_id' => $item->id,
        ]);
        $this->assertNotNull($miss->fresh()->resolved_at);
    }
}
