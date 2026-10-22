<?php

namespace Tests\Feature\Inventory;

use App\Models\DamagedAllocation;
use App\Models\DamagedGood;
use App\Models\Item;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\DamagedStockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DamagedStockAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_adjustment_in_and_out_on_damaged_warehouse_updates_damaged_balance(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $damagedWarehouse = Warehouse::where('code', 'GUDANG_RUSAK')->firstOrFail();
        $item = $this->createItem('ADJ-DMG-001');

        $this->actingAs($user)->withoutMiddleware()
            ->postJson(route('admin.inventory.stock-adjustments.store'), [
                'auto_approve' => true,
                'warehouse_id' => $damagedWarehouse->id,
                'transacted_at' => now()->format('Y-m-d H:i'),
                'items' => [[
                    'item_id' => $item->id,
                    'direction' => 'in',
                    'qty' => 5,
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('status', 'approved');

        $damagedGood = DamagedGood::firstOrFail();
        $this->assertSame(DamagedGood::SOURCE_STOCK_ADJUSTMENT, $damagedGood->source_type);
        $this->assertSame('approved', $damagedGood->status);
        $this->assertSame(5, $this->damagedBalance($item));

        $this->actingAs($user)->withoutMiddleware()
            ->postJson(route('admin.inventory.stock-adjustments.store'), [
                'warehouse_id' => $damagedWarehouse->id,
                'transacted_at' => now()->format('Y-m-d H:i'),
                'items' => [[
                    'item_id' => $item->id,
                    'direction' => 'out',
                    'qty' => 2,
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('status', 'pending');

        $this->assertSame(5, $this->damagedBalance($item));

        $pending = StockAdjustment::where('status', 'pending')->firstOrFail();
        $this->actingAs($user)->withoutMiddleware()
            ->postJson(route('admin.inventory.stock-adjustments.approve', $pending->id))
            ->assertOk();

        $allocation = DamagedAllocation::firstOrFail();
        $this->assertSame(DamagedStockService::ALLOCATION_TYPE_STOCK_ADJUSTMENT, $allocation->type);
        $this->assertSame('approved', $allocation->status);
        $this->assertSame($pending->code, $allocation->source_ref);
        $this->assertSame(3, $this->damagedBalance($item));
        $this->assertDatabaseHas('item_stocks', [
            'item_id' => $item->id,
            'warehouse_id' => $damagedWarehouse->id,
            'stock' => 3,
        ]);
    }

    public function test_adjustment_on_other_warehouse_does_not_touch_damaged_balance(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $displayWarehouse = Warehouse::where('code', 'GUDANG_DISPLAY')->firstOrFail();
        $item = $this->createItem('ADJ-DSP-001');

        $this->actingAs($user)->withoutMiddleware()
            ->postJson(route('admin.inventory.stock-adjustments.store'), [
                'auto_approve' => true,
                'warehouse_id' => $displayWarehouse->id,
                'transacted_at' => now()->format('Y-m-d H:i'),
                'items' => [[
                    'item_id' => $item->id,
                    'direction' => 'in',
                    'qty' => 4,
                ]],
            ])
            ->assertOk();

        $this->assertDatabaseCount('damaged_goods', 0);
        $this->assertDatabaseCount('damaged_allocations', 0);
    }

    private function damagedBalance(Item $item): int
    {
        $row = DamagedStockService::availableSkuBalances()->firstWhere('item_id', $item->id);

        return (int) ($row['remaining_qty'] ?? 0);
    }

    private function createItem(string $sku): Item
    {
        return Item::create([
            'sku' => $sku,
            'name' => 'Item '.$sku,
            'category_id' => 0,
            'koli_qty' => 12,
        ]);
    }
}
