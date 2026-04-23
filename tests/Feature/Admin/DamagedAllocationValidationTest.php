<?php

namespace Tests\Feature\Admin;

use App\Models\DamagedGood;
use App\Models\DamagedGoodItem;
use App\Models\Item;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DamagedAllocationValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_returns_nested_validation_error_for_missing_source_item_selection(): void
    {
        $this->createWarehouseFixtures();
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withoutMiddleware()
            ->postJson(route('admin.inventory.damaged-allocations.store'), [
                'type' => 'disposal',
                'transacted_at' => now()->format('Y-m-d H:i'),
                'source_items' => [
                    [
                        'qty' => 1,
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['source_items.0.damaged_good_item_id']);
    }

    public function test_store_returns_top_level_validation_error_for_missing_supplier(): void
    {
        $this->createWarehouseFixtures();
        $user = User::factory()->create();
        $item = Item::create([
            'sku' => 'SKU-DMG-ALLOC-001',
            'name' => 'Item Rusak Alokasi',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
        ]);
        Supplier::create(['name' => 'Supplier Test']);

        $damagedGood = DamagedGood::create([
            'code' => 'DMG-ALLOC-001',
            'source_type' => 'manual',
            'source_warehouse_id' => null,
            'source_ref' => 'TEST',
            'transacted_at' => now(),
            'status' => 'approved',
            'approved_at' => now(),
            'created_by' => $user->id,
            'approved_by' => $user->id,
        ]);

        $damagedItem = DamagedGoodItem::create([
            'damaged_good_id' => $damagedGood->id,
            'item_id' => $item->id,
            'qty' => 5,
            'note' => 'stok rusak',
        ]);

        $response = $this->actingAs($user)
            ->withoutMiddleware()
            ->postJson(route('admin.inventory.damaged-allocations.store'), [
                'type' => 'return_supplier',
                'transacted_at' => now()->format('Y-m-d H:i'),
                'source_items' => [
                    [
                        'damaged_good_item_id' => $damagedItem->id,
                        'qty' => 1,
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['supplier_id']);
    }

    private function createWarehouseFixtures(): array
    {
        $mainWarehouse = Warehouse::firstOrCreate(
            ['code' => 'GUDANG_BESAR'],
            ['name' => 'Gudang Besar', 'type' => 'main']
        );
        $displayWarehouse = Warehouse::firstOrCreate(
            ['code' => 'GUDANG_DISPLAY'],
            ['name' => 'Gudang Display', 'type' => 'display']
        );
        $damagedWarehouse = Warehouse::firstOrCreate(
            ['code' => 'GUDANG_RUSAK'],
            ['name' => 'Gudang Rusak', 'type' => 'damaged']
        );

        return [$mainWarehouse, $displayWarehouse, $damagedWarehouse];
    }
}
