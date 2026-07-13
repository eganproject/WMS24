<?php

namespace Tests\Feature\Admin;

use App\Models\Item;
use App\Models\ItemStock;
use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemStockBulkUpdateTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $mainWarehouse;
    private Warehouse $displayWarehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mainWarehouse = Warehouse::firstOrCreate(['code' => 'GUDANG_BESAR'], [
            'name' => 'Gudang Besar',
            'type' => 'main',
        ]);
        $this->displayWarehouse = Warehouse::firstOrCreate(['code' => 'GUDANG_DISPLAY'], [
            'name' => 'Gudang Display',
            'type' => 'display',
        ]);
    }

    public function test_bulk_item_deactivation_preserves_stock_and_monitoring_settings(): void
    {
        $first = $this->physicalItem('SKU-STATE-001');
        $second = $this->physicalItem('SKU-STATE-002', Item::STATUS_INACTIVE);
        $stock = ItemStock::create([
            'item_id' => $first->id,
            'warehouse_id' => $this->mainWarehouse->id,
            'stock' => 27,
            'safety_stock' => 10,
            'is_stock_monitored' => true,
        ]);

        $this->withoutMiddleware()->postJson(route('admin.inventory.item-stocks.update'), [
            'item_ids' => [$first->id, $second->id],
            'action' => 'deactivate_items',
        ])->assertOk()
            ->assertJsonPath('selected_count', 2)
            ->assertJsonPath('changed_count', 1)
            ->assertJsonPath('unchanged_count', 1);

        $this->assertDatabaseHas('items', ['id' => $first->id, 'status' => Item::STATUS_INACTIVE]);
        $this->assertDatabaseHas('items', ['id' => $second->id, 'status' => Item::STATUS_INACTIVE]);
        $this->assertDatabaseHas('item_stocks', [
            'id' => $stock->id,
            'stock' => 27,
            'safety_stock' => 10,
            'is_stock_monitored' => true,
        ]);
        $audit = ActivityLog::where('action', 'Bulk update item stock: deactivate_items')->first();
        $this->assertNotNull($audit);
        $this->assertSame([$first->id, $second->id], $audit->payload['audit']['item_ids']);
        $this->assertCount(1, $audit->payload['audit']['changes']);
    }

    public function test_bulk_monitoring_can_target_main_warehouse_without_changing_display(): void
    {
        $first = $this->physicalItem('SKU-MONITOR-001');
        $second = $this->physicalItem('SKU-MONITOR-002');
        foreach ([$first, $second] as $item) {
            ItemStock::create([
                'item_id' => $item->id,
                'warehouse_id' => $this->mainWarehouse->id,
                'stock' => 5,
                'is_stock_monitored' => true,
            ]);
            ItemStock::create([
                'item_id' => $item->id,
                'warehouse_id' => $this->displayWarehouse->id,
                'stock' => 3,
                'is_stock_monitored' => true,
            ]);
        }

        $this->withoutMiddleware()->postJson(route('admin.inventory.item-stocks.update'), [
            'item_ids' => [$first->id, $second->id],
            'action' => 'disable_monitoring',
            'monitoring_scope' => 'main',
        ])->assertOk()->assertJsonPath('changed_count', 2);

        foreach ([$first, $second] as $item) {
            $this->assertDatabaseHas('item_stocks', [
                'item_id' => $item->id,
                'warehouse_id' => $this->mainWarehouse->id,
                'stock' => 5,
                'is_stock_monitored' => false,
            ]);
            $this->assertDatabaseHas('item_stocks', [
                'item_id' => $item->id,
                'warehouse_id' => $this->displayWarehouse->id,
                'stock' => 3,
                'is_stock_monitored' => true,
            ]);
        }
    }

    public function test_bulk_monitoring_rejects_bundle_without_partially_changing_physical_item(): void
    {
        $physical = $this->physicalItem('SKU-ATOMIC-001');
        $bundle = Item::create([
            'sku' => 'SKU-ATOMIC-BUNDLE',
            'name' => 'Bundle Atomic',
            'item_type' => Item::TYPE_BUNDLE,
            'status' => Item::STATUS_ACTIVE,
            'category_id' => 0,
        ]);
        ItemStock::create([
            'item_id' => $physical->id,
            'warehouse_id' => $this->mainWarehouse->id,
            'stock' => 8,
            'is_stock_monitored' => true,
        ]);

        $this->withoutMiddleware()->postJson(route('admin.inventory.item-stocks.update'), [
            'item_ids' => [$physical->id, $bundle->id],
            'action' => 'disable_monitoring',
            'monitoring_scope' => 'both',
        ])->assertUnprocessable()->assertJsonValidationErrors('item_ids');

        $this->assertDatabaseHas('item_stocks', [
            'item_id' => $physical->id,
            'warehouse_id' => $this->mainWarehouse->id,
            'is_stock_monitored' => true,
        ]);
        $this->assertDatabaseMissing('item_stocks', [
            'item_id' => $physical->id,
            'warehouse_id' => $this->displayWarehouse->id,
        ]);
    }

    public function test_item_stock_page_exposes_clear_bulk_controls_to_authorized_user(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $role = Role::create(['name' => 'Admin Bulk Stock', 'slug' => 'admin-bulk-stock']);
        $user->roles()->attach($role);

        $this->actingAs($user)->withoutMiddleware()->get(route('admin.inventory.item-stocks.index'))
            ->assertOk()
            ->assertSee('btn_bulk_activate_items', false)
            ->assertSee('btn_bulk_deactivate_items', false)
            ->assertSee('btn_bulk_enable_monitoring', false)
            ->assertSee('btn_bulk_disable_monitoring', false);
    }

    private function physicalItem(string $sku, string $status = Item::STATUS_ACTIVE): Item
    {
        return Item::create([
            'sku' => $sku,
            'name' => 'Item '.$sku,
            'item_type' => Item::TYPE_SINGLE,
            'status' => $status,
            'category_id' => 0,
            'safety_stock' => 0,
        ]);
    }
}
