<?php

namespace Tests\Feature\Inventory;

use App\Models\Item;
use App\Models\ItemStock;
use App\Models\DamagedGood;
use App\Models\DamagedGoodItem;
use App\Models\InboundItem;
use App\Models\InboundTransaction;
use App\Models\StockMutation;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\InboundKoliUnitService;
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

    public function test_inbound_koli_qr_cannot_be_scanned_twice_for_transfer(): void
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
            'sku' => 'TRF-SCAN-DUP-001',
            'name' => 'Transfer Scan Duplicate Item',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
            'koli_qty' => 12,
        ]);

        ItemStock::create([
            'item_id' => $item->id,
            'warehouse_id' => $fromWarehouse->id,
            'stock' => 24,
        ]);

        $unit = $this->createInboundKoliUnits($item, $fromWarehouse, 1, 12)->first();

        $this->withoutMiddleware();

        $this->actingAs($user)
            ->postJson(route('admin.inventory.stock-transfers.store'), [
                'from_warehouse_id' => $fromWarehouse->id,
                'to_warehouse_id' => $toWarehouse->id,
                'transacted_at' => now()->format('Y-m-d H:i'),
                'items' => [
                    [
                        'item_id' => $item->id,
                        'koli' => 1,
                        'qty' => 12,
                    ],
                ],
            ])
            ->assertOk();

        $transfer = StockTransfer::query()->firstOrFail();

        $this->actingAs($user)
            ->postJson(route('admin.inventory.stock-transfers.scan-koli', $transfer->id), [
                'code' => $unit->code,
            ])
            ->assertOk();

        $this->actingAs($user)
            ->postJson(route('admin.inventory.stock-transfers.scan-koli', $transfer->id), [
                'code' => $unit->code,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'QR dus inbound sudah dipakai pada transfer lain.');
    }

    public function test_transfer_qc_can_use_legacy_no_qr_mode_with_reason(): void
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
            'sku' => 'TRF-LEGACY-001',
            'name' => 'Transfer Legacy Item',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
            'koli_qty' => 12,
        ]);

        ItemStock::create([
            'item_id' => $item->id,
            'warehouse_id' => $fromWarehouse->id,
            'stock' => 24,
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
                        'koli' => 1,
                        'qty' => 12,
                    ],
                ],
            ])
            ->assertOk();

        $transfer = StockTransfer::query()->firstOrFail();
        $unit = $this->createInboundKoliUnits($item, $fromWarehouse, 1, 12)->first();

        $this->actingAs($user)
            ->postJson(route('admin.inventory.stock-transfers.scan-koli', $transfer->id), [
                'code' => $unit->code,
            ])
            ->assertOk();

        $this->actingAs($user)
            ->postJson(route('admin.inventory.stock-transfers.qc', $transfer->id), [
                'traceability_mode' => 'legacy',
                'legacy_reason' => 'Stok lama sebelum QR inbound diterapkan',
                'items' => [
                    [
                        'item_id' => $item->id,
                        'qty_ok' => 11,
                        'qty_reject' => 0,
                        'qty_short' => 1,
                        'qc_note' => 'Dus lama tanpa QR',
                    ],
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('stock_transfers', [
            'id' => $transfer->id,
            'status' => 'completed',
            'traceability_mode' => 'legacy',
            'legacy_reason' => 'Stok lama sebelum QR inbound diterapkan',
        ]);
        $this->assertDatabaseHas('stock_transfer_items', [
            'stock_transfer_id' => $transfer->id,
            'item_id' => $item->id,
            'qty_ok' => 11,
            'qty_short' => 1,
        ]);
        $this->assertDatabaseHas('item_stocks', [
            'item_id' => $item->id,
            'warehouse_id' => $fromWarehouse->id,
            'stock' => 12,
        ]);
        $this->assertDatabaseHas('item_stocks', [
            'item_id' => $item->id,
            'warehouse_id' => $toWarehouse->id,
            'stock' => 11,
        ]);
        $this->assertSame(0, $transfer->koliScans()->count());
        $this->assertDatabaseHas('inbound_koli_units', [
            'id' => $unit->id,
            'status' => 'available',
            'reserved_transfer_id' => null,
        ]);
    }

    public function test_transfer_qc_shortage_does_not_go_to_damaged_warehouse(): void
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
        Warehouse::firstOrCreate(
            ['code' => 'GUDANG_RUSAK'],
            ['name' => 'Gudang Rusak', 'type' => 'damaged']
        );
        $item = Item::create([
            'sku' => 'TRF-SHORT-001',
            'name' => 'Transfer Short Item',
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
                        'koli' => 2,
                        'qty' => 24,
                    ],
                ],
            ])
            ->assertOk();

        $transfer = StockTransfer::query()->firstOrFail();
        $units = $this->createInboundKoliUnits($item, $fromWarehouse, 2, 12);

        foreach ($units as $unit) {
            $this->actingAs($user)
                ->postJson(route('admin.inventory.stock-transfers.scan-koli', $transfer->id), [
                    'code' => $unit->code,
                ])
                ->assertOk();
        }

        $this->actingAs($user)
            ->postJson(route('admin.inventory.stock-transfers.qc', $transfer->id), [
                'items' => [
                    [
                        'item_id' => $item->id,
                        'qty_ok' => 22,
                        'qty_reject' => 0,
                        'qty_short' => 2,
                        'qc_note' => 'Isi dus kurang 2 pcs',
                    ],
                ],
                'scans' => [
                    [
                        'id' => $transfer->koliScans()->orderBy('id')->firstOrFail()->id,
                        'qty_ok' => 12,
                        'qty_reject' => 0,
                        'qty_short' => 0,
                    ],
                    [
                        'id' => $transfer->koliScans()->orderBy('id')->skip(1)->firstOrFail()->id,
                        'qty_ok' => 10,
                        'qty_reject' => 0,
                        'qty_short' => 2,
                        'qc_note' => 'Isi dus kurang 2 pcs',
                    ],
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('stock_transfer_items', [
            'stock_transfer_id' => $transfer->id,
            'item_id' => $item->id,
            'qty' => 24,
            'qty_ok' => 22,
            'qty_reject' => 0,
            'qty_short' => 2,
        ]);
        $this->assertDatabaseHas('item_stocks', [
            'item_id' => $item->id,
            'warehouse_id' => $fromWarehouse->id,
            'stock' => 6,
        ]);
        $this->assertDatabaseHas('item_stocks', [
            'item_id' => $item->id,
            'warehouse_id' => $toWarehouse->id,
            'stock' => 22,
        ]);
        $this->assertDatabaseMissing('stock_mutations', [
            'item_id' => $item->id,
            'source_type' => 'transfer',
            'source_subtype' => 'qc_reject',
            'qty' => 2,
        ]);
        $this->assertSame(0, DamagedGood::query()->count());
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
        $units = $this->createInboundKoliUnits($item, $fromWarehouse, 5, 1);

        foreach ($units as $unit) {
            $this->actingAs($user)
                ->postJson(route('admin.inventory.stock-transfers.scan-koli', $transfer->id), [
                    'code' => $unit->code,
                ])
                ->assertOk();
        }

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
                'scans' => $transfer->koliScans()->orderBy('id')->get()->map(function ($scan, $idx) {
                    return [
                        'id' => $scan->id,
                        'qty_ok' => $idx < 3 ? 1 : 0,
                        'qty_reject' => $idx < 3 ? 0 : 1,
                        'qty_short' => 0,
                        'qc_note' => $idx < 3 ? null : 'Kemasan rusak',
                    ];
                })->values()->all(),
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

    private function createInboundKoliUnits(Item $item, Warehouse $warehouse, int $koli, int $qtyPerKoli)
    {
        $transaction = InboundTransaction::create([
            'code' => 'INB-TRF-'.$item->sku,
            'type' => 'receipt',
            'warehouse_id' => $warehouse->id,
            'transacted_at' => now(),
            'status' => 'completed',
        ]);

        InboundItem::create([
            'inbound_transaction_id' => $transaction->id,
            'item_id' => $item->id,
            'qty' => $koli * $qtyPerKoli,
            'koli' => $koli,
        ]);

        return app(InboundKoliUnitService::class)
            ->syncForTransaction($transaction->fresh(['items.item']));
    }
}
