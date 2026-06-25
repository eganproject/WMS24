<?php

namespace Tests\Feature\Admin;

use App\Models\Item;
use App\Models\ItemStock;
use App\Models\StockMutation;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockOpnameApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_display_stock_opname_approval_uses_current_stock_for_adjustment(): void
    {
        $displayWarehouse = Warehouse::firstOrCreate([
            'code' => 'GUDANG_DISPLAY',
        ], [
            'name' => 'Gudang Display',
            'type' => 'display',
        ]);

        $item = Item::create([
            'sku' => 'SKU-SO-DISPLAY-001',
            'name' => 'Item SO Display',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
            'safety_stock' => 0,
        ]);

        $stock = ItemStock::create([
            'item_id' => $item->id,
            'warehouse_id' => $displayWarehouse->id,
            'stock' => 5,
        ]);

        $opname = StockOpname::create([
            'code' => 'OPN-DISPLAY-001',
            'warehouse_id' => $displayWarehouse->id,
            'transacted_at' => now(),
            'status' => 'open',
            'created_by' => User::factory()->create()->id,
        ]);

        $opnameItem = StockOpnameItem::create([
            'stock_opname_id' => $opname->id,
            'item_id' => $item->id,
            'system_qty' => 5,
            'counted_qty' => 0,
            'adjustment' => -5,
            'created_by' => $opname->created_by,
        ]);

        $stock->update(['stock' => 3]);

        $response = $this->actingAs(User::factory()->create())
            ->withoutMiddleware()
            ->postJson(route('admin.inventory.stock-opname.approve', $opname->id));

        $response->assertOk()
            ->assertJsonPath('message', 'Stock opname berhasil disetujui');

        $this->assertSame(0, (int) $stock->fresh()->stock);
        $this->assertSame('completed', $opname->fresh()->status);

        $opnameItem->refresh();
        $this->assertSame(3, (int) $opnameItem->system_qty);
        $this->assertSame(-3, (int) $opnameItem->adjustment);

        $mutation = StockMutation::where('source_type', 'opname')
            ->where('source_id', $opname->id)
            ->first();

        $this->assertNotNull($mutation);
        $this->assertSame('out', $mutation->direction);
        $this->assertSame(3, (int) $mutation->qty);
        $this->assertSame(3, (int) $mutation->stock_before);
        $this->assertSame(0, (int) $mutation->stock_after);
    }
}
