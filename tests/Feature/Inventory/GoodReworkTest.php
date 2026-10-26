<?php

namespace Tests\Feature\Inventory;

use App\Http\Middleware\AuthorizeMenuPermission;
use App\Models\GoodRework;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoodReworkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(AuthorizeMenuPermission::class);
    }

    public function test_index_page_renders(): void
    {
        $this->actingAs($this->user())
            ->get(route('admin.inventory.good-reworks.index'))
            ->assertOk()
            ->assertSee('Rework Barang Bagus');
    }

    public function test_approved_rework_moves_good_stock_without_touching_damaged_data(): void
    {
        $user = $this->user();
        $display = Warehouse::where('code', 'GUDANG_DISPLAY')->firstOrFail();
        $main = Warehouse::where('code', 'GUDANG_BESAR')->firstOrFail();
        $input = $this->createItem('GRW-IN');
        $output = $this->createItem('GRW-OUT');
        $this->seedStock($input, $display, 10);

        $this->actingAs($user)
            ->postJson(route('admin.inventory.good-reworks.store'), [
                'source_warehouse_id' => $display->id,
                'target_warehouse_id' => $main->id,
                'transacted_at' => now()->format('Y-m-d H:i'),
                'input_items' => [['item_id' => $input->id, 'qty' => 4]],
                'output_items' => [['item_id' => $output->id, 'qty' => 2]],
            ])
            ->assertOk()
            ->assertJsonPath('status', 'pending');

        $rework = GoodRework::firstOrFail();
        $this->assertSame(10, $this->stock($input, $display));

        $this->actingAs($user)
            ->postJson(route('admin.inventory.good-reworks.approve', $rework->id))
            ->assertOk();

        $this->assertSame('approved', $rework->fresh()->status);
        $this->assertSame(6, $this->stock($input, $display));
        $this->assertSame(2, $this->stock($output, $main));
        $this->assertDatabaseHas('stock_mutations', [
            'source_type' => 'good_rework',
            'source_subtype' => 'rework_input',
            'source_id' => $rework->id,
            'item_id' => $input->id,
            'direction' => 'out',
            'qty' => 4,
        ]);
        $this->assertDatabaseCount('damaged_goods', 0);
        $this->assertDatabaseCount('damaged_allocations', 0);

        $this->actingAs($user)
            ->putJson(route('admin.inventory.good-reworks.update', $rework->id), [
                'source_warehouse_id' => $display->id,
                'target_warehouse_id' => $main->id,
                'transacted_at' => now()->format('Y-m-d H:i'),
                'input_items' => [['item_id' => $input->id, 'qty' => 1]],
                'output_items' => [['item_id' => $output->id, 'qty' => 1]],
            ])
            ->assertStatus(422);

        $this->actingAs($user)
            ->deleteJson(route('admin.inventory.good-reworks.destroy', $rework->id))
            ->assertStatus(422);
    }

    public function test_approve_is_blocked_when_source_stock_is_short(): void
    {
        $user = $this->user();
        $display = Warehouse::where('code', 'GUDANG_DISPLAY')->firstOrFail();
        $input = $this->createItem('GRW-SHORT-IN');
        $output = $this->createItem('GRW-SHORT-OUT');
        $this->seedStock($input, $display, 1);

        $this->actingAs($user)
            ->postJson(route('admin.inventory.good-reworks.store'), [
                'source_warehouse_id' => $display->id,
                'target_warehouse_id' => $display->id,
                'transacted_at' => now()->format('Y-m-d H:i'),
                'input_items' => [['item_id' => $input->id, 'qty' => 3]],
                'output_items' => [['item_id' => $output->id, 'qty' => 1]],
            ])
            ->assertOk();

        $rework = GoodRework::firstOrFail();
        $this->actingAs($user)
            ->postJson(route('admin.inventory.good-reworks.approve', $rework->id))
            ->assertStatus(422)
            ->assertJsonValidationErrors('items');

        $this->assertSame('pending', $rework->fresh()->status);
        $this->assertSame(1, $this->stock($input, $display));
        $this->assertSame(0, $this->stock($output, $display));
    }

    public function test_damaged_warehouse_cannot_be_used(): void
    {
        $damaged = Warehouse::where('code', 'GUDANG_RUSAK')->firstOrFail();
        $display = Warehouse::where('code', 'GUDANG_DISPLAY')->firstOrFail();
        $input = $this->createItem('GRW-DMG-IN');
        $output = $this->createItem('GRW-DMG-OUT');

        $this->actingAs($this->user())
            ->postJson(route('admin.inventory.good-reworks.store'), [
                'source_warehouse_id' => $damaged->id,
                'target_warehouse_id' => $display->id,
                'transacted_at' => now()->format('Y-m-d H:i'),
                'input_items' => [['item_id' => $input->id, 'qty' => 1]],
                'output_items' => [['item_id' => $output->id, 'qty' => 1]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('source_warehouse_id');

        $this->assertDatabaseCount('good_reworks', 0);
    }

    private function user(): User
    {
        return User::factory()->create(['is_active' => true]);
    }

    private function seedStock(Item $item, Warehouse $warehouse, int $qty): void
    {
        StockService::mutate([
            'item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'direction' => 'in',
            'qty' => $qty,
            'source_type' => 'adjustment',
            'source_id' => 999,
            'source_code' => 'TEST',
        ]);
    }

    private function stock(Item $item, Warehouse $warehouse): int
    {
        return (int) ItemStock::where('item_id', $item->id)->where('warehouse_id', $warehouse->id)->value('stock');
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
