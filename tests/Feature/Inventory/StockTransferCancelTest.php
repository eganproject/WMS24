<?php

namespace Tests\Feature\Inventory;

use App\Models\Item;
use App\Models\ItemStock;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockTransferCancelTest extends TestCase
{
    use RefreshDatabase;

    public function test_transfer_can_be_canceled_before_qc_and_stock_is_restored(): void
    {
        $user = User::factory()->create();
        $fromWarehouse = Warehouse::firstOrCreate(
            ['code' => 'GUDANG_BESAR'],
            ['name' => 'Gudang Besar']
        );
        $toWarehouse = Warehouse::firstOrCreate(
            ['code' => 'GUDANG_DISPLAY'],
            ['name' => 'Gudang Display']
        );
        $item = Item::create([
            'sku' => 'TRF-CANCEL-001',
            'name' => 'Transfer Cancel Item',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
        ]);

        ItemStock::create([
            'item_id' => $item->id,
            'warehouse_id' => $fromWarehouse->id,
            'stock' => 15,
        ]);

        $this->withoutMiddleware();

        $this->actingAs($user)
            ->postJson(route('admin.inventory.stock-transfers.store'), [
                'from_warehouse_id' => $fromWarehouse->id,
                'to_warehouse_id' => $toWarehouse->id,
                'transacted_at' => now()->format('Y-m-d H:i'),
                'items' => [
                    [
                        'item_id' => $item->id,
                        'qty' => 5,
                    ],
                ],
            ])
            ->assertOk();

        $transfer = StockTransfer::query()->firstOrFail();

        $this->assertDatabaseHas('item_stocks', [
            'item_id' => $item->id,
            'warehouse_id' => $fromWarehouse->id,
            'stock' => 10,
        ]);

        $this->actingAs($user)
            ->postJson(route('admin.inventory.stock-transfers.cancel', $transfer->id), [
                'reason' => 'Salah gudang tujuan',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Transfer gudang berhasil dibatalkan');

        $transfer->refresh();

        $this->assertSame('canceled', $transfer->status);
        $this->assertStringContainsString('Salah gudang tujuan', (string) $transfer->note);
        $this->assertDatabaseHas('item_stocks', [
            'item_id' => $item->id,
            'warehouse_id' => $fromWarehouse->id,
            'stock' => 15,
        ]);
    }

    public function test_canceled_transfer_cannot_be_processed_by_qc(): void
    {
        $user = User::factory()->create();
        $fromWarehouse = Warehouse::firstOrCreate(
            ['code' => 'GUDANG_BESAR'],
            ['name' => 'Gudang Besar']
        );
        $toWarehouse = Warehouse::firstOrCreate(
            ['code' => 'GUDANG_DISPLAY'],
            ['name' => 'Gudang Display']
        );
        $item = Item::create([
            'sku' => 'TRF-CANCEL-002',
            'name' => 'Transfer Cancel Item 2',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
        ]);

        ItemStock::create([
            'item_id' => $item->id,
            'warehouse_id' => $fromWarehouse->id,
            'stock' => 20,
        ]);

        $this->withoutMiddleware();

        $this->actingAs($user)
            ->postJson(route('admin.inventory.stock-transfers.store'), [
                'from_warehouse_id' => $fromWarehouse->id,
                'to_warehouse_id' => $toWarehouse->id,
                'transacted_at' => now()->format('Y-m-d H:i'),
                'items' => [
                    [
                        'item_id' => $item->id,
                        'qty' => 4,
                    ],
                ],
            ])
            ->assertOk();

        $transfer = StockTransfer::query()->firstOrFail();

        $this->actingAs($user)
            ->postJson(route('admin.inventory.stock-transfers.cancel', $transfer->id))
            ->assertOk();

        $this->actingAs($user)
            ->postJson(route('admin.inventory.stock-transfers.qc', $transfer->id), [
                'items' => [
                    [
                        'item_id' => $item->id,
                        'qty_ok' => 4,
                        'qty_reject' => 0,
                    ],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Transfer sudah diproses QC');
    }
}
