<?php

namespace Tests\Feature\Inventory;

use App\Models\Item;
use App\Models\ItemStock;
use App\Models\DamagedGood;
use App\Models\DamagedGoodItem;
use App\Models\StockMutation;
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
            'koli_qty' => 1,
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
                        'koli' => 5,
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

    public function test_transfer_to_main_warehouse_is_rejected(): void
    {
        $user = User::factory()->create();
        $displayWarehouse = Warehouse::firstOrCreate(
            ['code' => 'GUDANG_DISPLAY'],
            ['name' => 'Gudang Display', 'type' => 'display']
        );
        $mainWarehouse = Warehouse::firstOrCreate(
            ['code' => 'GUDANG_BESAR'],
            ['name' => 'Gudang Besar', 'type' => 'main']
        );
        $item = Item::create([
            'sku' => 'TRF-MAIN-REJECT',
            'name' => 'Transfer Main Reject',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
        ]);

        ItemStock::create([
            'item_id' => $item->id,
            'warehouse_id' => $displayWarehouse->id,
            'stock' => 8,
        ]);

        $this->withoutMiddleware();

        $this->actingAs($user)
            ->postJson(route('admin.inventory.stock-transfers.store'), [
                'from_warehouse_id' => $displayWarehouse->id,
                'to_warehouse_id' => $mainWarehouse->id,
                'transacted_at' => now()->format('Y-m-d H:i'),
                'items' => [
                    [
                        'item_id' => $item->id,
                        'qty' => 2,
                    ],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['to_warehouse_id'])
            ->assertJsonPath('errors.to_warehouse_id.0', 'Transfer stok ke Gudang Besar tidak diperbolehkan.');
    }

    public function test_transfer_from_main_to_display_requires_matching_koli(): void
    {
        $user = User::factory()->create();
        $fromWarehouse = Warehouse::firstOrCreate(
            ['code' => 'GUDANG_BESAR'],
            ['name' => 'Gudang Besar', 'type' => 'main']
        );
        $toWarehouse = Warehouse::firstOrCreate(
            ['code' => 'GUDANG_DISPLAY'],
            ['name' => 'Gudang Display', 'type' => 'display']
        );
        $item = Item::create([
            'sku' => 'TRF-KOLI-001',
            'name' => 'Transfer Koli Item',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
            'koli_qty' => 12,
        ]);

        ItemStock::create([
            'item_id' => $item->id,
            'warehouse_id' => $fromWarehouse->id,
            'stock' => 30,
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
                        'qty' => 12,
                    ],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.koli']);

        $this->actingAs($user)
            ->postJson(route('admin.inventory.stock-transfers.store'), [
                'from_warehouse_id' => $fromWarehouse->id,
                'to_warehouse_id' => $toWarehouse->id,
                'transacted_at' => now()->format('Y-m-d H:i'),
                'items' => [
                    [
                        'item_id' => $item->id,
                        'koli' => 2,
                        'qty' => 24,
                    ],
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('stock_transfer_items', [
            'item_id' => $item->id,
            'qty' => 24,
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
            'koli_qty' => 1,
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
                        'koli' => 4,
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

    public function test_transfer_qc_reject_goes_to_damaged_warehouse(): void
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
        $damagedWarehouse = Warehouse::firstOrCreate(
            ['code' => 'GUDANG_RUSAK'],
            ['name' => 'Gudang Rusak', 'type' => 'damaged']
        );
        $item = Item::create([
            'sku' => 'TRF-REJECT-001',
            'name' => 'Transfer Reject Item',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
            'koli_qty' => 1,
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
                        'koli' => 5,
                        'qty' => 5,
                    ],
                ],
            ])
            ->assertOk();

        $transfer = StockTransfer::query()->firstOrFail();

        $this->actingAs($user)
            ->postJson(route('admin.inventory.stock-transfers.qc', $transfer->id), [
                'items' => [
                    [
                        'item_id' => $item->id,
                        'qty_ok' => 3,
                        'qty_reject' => 2,
                        'qc_note' => 'Kemasan rusak',
                    ],
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('item_stocks', [
            'item_id' => $item->id,
            'warehouse_id' => $fromWarehouse->id,
            'stock' => 15,
        ]);
        $this->assertDatabaseHas('item_stocks', [
            'item_id' => $item->id,
            'warehouse_id' => $toWarehouse->id,
            'stock' => 3,
        ]);
        $this->assertDatabaseHas('item_stocks', [
            'item_id' => $item->id,
            'warehouse_id' => $damagedWarehouse->id,
            'stock' => 2,
        ]);
        $this->assertTrue(StockMutation::query()
            ->where('item_id', $item->id)
            ->where('warehouse_id', $damagedWarehouse->id)
            ->where('source_type', 'transfer')
            ->where('source_subtype', 'qc_reject')
            ->where('direction', 'in')
            ->where('qty', 2)
            ->exists());

        $damagedGood = DamagedGood::query()
            ->where('source_type', DamagedGood::SOURCE_TRANSFER_REJECT)
            ->where('source_ref', $transfer->code)
            ->where('status', 'approved')
            ->firstOrFail();

        $this->assertDatabaseHas('damaged_good_items', [
            'damaged_good_id' => $damagedGood->id,
            'item_id' => $item->id,
            'qty' => 2,
            'reason_code' => DamagedGoodItem::REASON_OTHER,
        ]);
    }
}
