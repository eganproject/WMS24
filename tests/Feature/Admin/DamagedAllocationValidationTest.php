<?php

namespace Tests\Feature\Admin;

use App\Models\DamagedAllocation;
use App\Models\DamagedAllocationItem;
use App\Models\DamagedGood;
use App\Models\DamagedGoodItem;
use App\Models\Item;
use App\Models\ItemStock;
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
            'source_type' => DamagedGood::SOURCE_MANUAL,
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
            'reason_code' => DamagedGoodItem::REASON_OTHER,
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

    public function test_approve_disposal_requires_physical_damaged_warehouse_stock(): void
    {
        [, , $damagedWarehouse] = $this->createWarehouseFixtures();
        $user = User::factory()->create();
        $item = Item::create([
            'sku' => 'SKU-DMG-ALLOC-APPROVE-MISSING',
            'name' => 'Item Rusak Missing Stock',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
        ]);
        $damagedItem = $this->createApprovedDamagedItem($user, $item, 5);
        $allocation = $this->createPendingDisposalAllocation($user, $damagedItem, 3);

        $response = $this->actingAs($user)
            ->withoutMiddleware()
            ->postJson(route('admin.inventory.damaged-allocations.approve', $allocation->id));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['source_items']);
        $this->assertStringContainsString('Stok fisik Gudang Rusak', $response->json('errors.source_items.0'));
        $this->assertDatabaseMissing('item_stocks', [
            'item_id' => $item->id,
            'warehouse_id' => $damagedWarehouse->id,
        ]);
    }

    public function test_approve_disposal_moves_stock_out_of_damaged_warehouse(): void
    {
        [, , $damagedWarehouse] = $this->createWarehouseFixtures();
        $user = User::factory()->create();
        $item = Item::create([
            'sku' => 'SKU-DMG-ALLOC-APPROVE-OK',
            'name' => 'Item Rusak Approve OK',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
        ]);
        $damagedItem = $this->createApprovedDamagedItem($user, $item, 5);
        ItemStock::create([
            'item_id' => $item->id,
            'warehouse_id' => $damagedWarehouse->id,
            'stock' => 5,
        ]);
        $allocation = $this->createPendingDisposalAllocation($user, $damagedItem, 3);

        $response = $this->actingAs($user)
            ->withoutMiddleware()
            ->postJson(route('admin.inventory.damaged-allocations.approve', $allocation->id));

        $response->assertOk()
            ->assertJsonPath('message', 'Alokasi barang rusak berhasil disetujui');
        $this->assertDatabaseHas('damaged_allocations', [
            'id' => $allocation->id,
            'status' => 'approved',
        ]);
        $this->assertDatabaseHas('item_stocks', [
            'item_id' => $item->id,
            'warehouse_id' => $damagedWarehouse->id,
            'stock' => 2,
        ]);
        $this->assertDatabaseHas('stock_mutations', [
            'item_id' => $item->id,
            'warehouse_id' => $damagedWarehouse->id,
            'direction' => 'out',
            'qty' => 3,
            'source_type' => 'damaged_allocation',
            'source_id' => $allocation->id,
        ]);
    }

    private function createApprovedDamagedItem(User $user, Item $item, int $qty): DamagedGoodItem
    {
        $damagedGood = DamagedGood::create([
            'code' => 'DMG-ALLOC-'.strtoupper(substr(md5($item->id.$qty), 0, 8)),
            'source_type' => DamagedGood::SOURCE_MANUAL,
            'source_warehouse_id' => null,
            'source_ref' => 'TEST',
            'transacted_at' => now(),
            'status' => 'approved',
            'approved_at' => now(),
            'created_by' => $user->id,
            'approved_by' => $user->id,
        ]);

        return DamagedGoodItem::create([
            'damaged_good_id' => $damagedGood->id,
            'item_id' => $item->id,
            'qty' => $qty,
            'reason_code' => DamagedGoodItem::REASON_OTHER,
            'note' => 'stok rusak',
        ]);
    }

    private function createPendingDisposalAllocation(User $user, DamagedGoodItem $damagedItem, int $qty): DamagedAllocation
    {
        $allocation = DamagedAllocation::create([
            'code' => 'DGA-ALLOC-'.strtoupper(substr(md5($damagedItem->id.$qty), 0, 8)),
            'type' => 'disposal',
            'transacted_at' => now(),
            'status' => 'pending',
            'created_by' => $user->id,
        ]);

        DamagedAllocationItem::create([
            'damaged_allocation_id' => $allocation->id,
            'line_type' => 'source',
            'damaged_good_item_id' => $damagedItem->id,
            'item_id' => $damagedItem->item_id,
            'qty' => $qty,
        ]);

        return $allocation;
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
