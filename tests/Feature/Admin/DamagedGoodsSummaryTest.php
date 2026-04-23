<?php

namespace Tests\Feature\Admin;

use App\Models\DamagedAllocation;
use App\Models\DamagedAllocationItem;
use App\Models\DamagedGood;
use App\Models\DamagedGoodItem;
use App\Models\Item;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DamagedGoodsSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_requires_reason_code_for_each_item(): void
    {
        [, $displayWarehouse] = $this->createWarehouseFixtures();
        $item = Item::create([
            'sku' => 'SKU-DMG-REQ',
            'name' => 'Item Reason Required',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
        ]);

        $response = $this->withoutMiddleware()->postJson(route('admin.inventory.damaged-goods.store'), [
            'source_type' => DamagedGood::SOURCE_WAREHOUSE,
            'source_warehouse_id' => $displayWarehouse->id,
            'transacted_at' => now()->format('Y-m-d H:i'),
            'items' => [
                [
                    'item_id' => $item->id,
                    'qty' => 2,
                ],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.reason_code']);
    }

    public function test_summary_by_sku_aggregates_remaining_stock_across_documents(): void
    {
        [, $displayWarehouse] = $this->createWarehouseFixtures();
        $user = User::factory()->create();
        $item = Item::create([
            'sku' => 'SKU-DMG-SUM',
            'name' => 'Item Summary Rusak',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
        ]);
        $otherItem = Item::create([
            'sku' => 'SKU-DMG-OTH',
            'name' => 'Item Lain',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
        ]);

        [$firstLine, $secondLine] = $this->createApprovedDamagedLines($user->id, $displayWarehouse->id, $item->id);
        $this->createApprovedDamagedLine(
            createdBy: $user->id,
            sourceWarehouseId: $displayWarehouse->id,
            itemId: $otherItem->id,
            qty: 5,
            reasonCode: DamagedGoodItem::REASON_OTHER,
            transactedAt: now()->subDays(3)
        );

        $allocation = DamagedAllocation::create([
            'code' => 'DMA-SUM-001',
            'type' => 'disposal',
            'transacted_at' => now(),
            'status' => 'approved',
            'created_by' => $user->id,
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        DamagedAllocationItem::create([
            'damaged_allocation_id' => $allocation->id,
            'line_type' => 'source',
            'damaged_good_item_id' => $firstLine->id,
            'item_id' => $item->id,
            'qty' => 4,
            'note' => 'Sudah dialokasikan sebagian',
        ]);

        $response = $this->withoutMiddleware()->getJson(route('admin.inventory.damaged-goods.summary-by-sku', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'q' => $item->sku,
        ]));

        $response->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.sku', $item->sku)
            ->assertJsonPath('data.0.doc_count', 2)
            ->assertJsonPath('data.0.intake_qty', 30)
            ->assertJsonPath('data.0.allocated_qty', 4)
            ->assertJsonPath('data.0.remaining_qty', 26)
            ->assertJsonPath('data.0.age_bucket', '31-60 hari');

        $this->assertStringContainsString('Kerusakan Fisik', $response->json('data.0.reason_summary'));
        $this->assertStringContainsString('Retur Customer', $response->json('data.0.reason_summary'));
    }

    public function test_aging_summary_groups_remaining_qty_into_buckets(): void
    {
        [, $displayWarehouse] = $this->createWarehouseFixtures();
        $user = User::factory()->create();
        $item = Item::create([
            'sku' => 'SKU-DMG-AGE-A',
            'name' => 'Item Aging A',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
        ]);
        $otherItem = Item::create([
            'sku' => 'SKU-DMG-AGE-B',
            'name' => 'Item Aging B',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
        ]);

        $oldLine = $this->createApprovedDamagedLine(
            createdBy: $user->id,
            sourceWarehouseId: $displayWarehouse->id,
            itemId: $item->id,
            qty: 10,
            reasonCode: DamagedGoodItem::REASON_PHYSICAL_DAMAGE,
            transactedAt: now()->subDays(45)
        );
        $midLine = $this->createApprovedDamagedLine(
            createdBy: $user->id,
            sourceWarehouseId: $displayWarehouse->id,
            itemId: $item->id,
            qty: 8,
            reasonCode: DamagedGoodItem::REASON_CUSTOMER_RETURN,
            transactedAt: now()->subDays(10)
        );
        $freshLine = $this->createApprovedDamagedLine(
            createdBy: $user->id,
            sourceWarehouseId: $displayWarehouse->id,
            itemId: $otherItem->id,
            qty: 5,
            reasonCode: DamagedGoodItem::REASON_OTHER,
            transactedAt: now()->subDays(3)
        );

        $allocation = DamagedAllocation::create([
            'code' => 'DMA-AGE-001',
            'type' => 'disposal',
            'transacted_at' => now(),
            'status' => 'approved',
            'created_by' => $user->id,
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        DamagedAllocationItem::create([
            'damaged_allocation_id' => $allocation->id,
            'line_type' => 'source',
            'damaged_good_item_id' => $oldLine->id,
            'item_id' => $item->id,
            'qty' => 4,
        ]);

        $response = $this->withoutMiddleware()->getJson(route('admin.inventory.damaged-goods.aging-summary', [
            'q' => 'SKU-DMG-AGE',
        ]));

        $response->assertOk()
            ->assertJsonPath('total_remaining_qty', 19)
            ->assertJsonPath('total_skus', 2)
            ->assertJsonPath('buckets.0.code', '0_7')
            ->assertJsonPath('buckets.0.qty', 5)
            ->assertJsonPath('buckets.1.code', '8_30')
            ->assertJsonPath('buckets.1.qty', 8)
            ->assertJsonPath('buckets.2.code', '31_60')
            ->assertJsonPath('buckets.2.qty', 6)
            ->assertJsonPath('buckets.3.code', '61_plus')
            ->assertJsonPath('buckets.3.qty', 0);

        $this->assertTrue($midLine->exists);
        $this->assertTrue($freshLine->exists);
    }

    private function createApprovedDamagedLines(int $createdBy, int $sourceWarehouseId, int $itemId): array
    {
        $firstLine = $this->createApprovedDamagedLine(
            createdBy: $createdBy,
            sourceWarehouseId: $sourceWarehouseId,
            itemId: $itemId,
            qty: 10,
            reasonCode: DamagedGoodItem::REASON_PHYSICAL_DAMAGE,
            transactedAt: now()->subDays(45)
        );

        $secondLine = $this->createApprovedDamagedLine(
            createdBy: $createdBy,
            sourceWarehouseId: $sourceWarehouseId,
            itemId: $itemId,
            qty: 20,
            reasonCode: DamagedGoodItem::REASON_CUSTOMER_RETURN,
            transactedAt: now()->subDays(10)
        );

        return [$firstLine, $secondLine];
    }

    private function createApprovedDamagedLine(
        int $createdBy,
        int $sourceWarehouseId,
        int $itemId,
        int $qty,
        string $reasonCode,
        $transactedAt
    ): DamagedGoodItem {
        $damagedGood = DamagedGood::create([
            'code' => 'DMG-'.strtoupper(substr(md5($itemId.$qty.$reasonCode.$transactedAt), 0, 10)),
            'source_type' => DamagedGood::SOURCE_WAREHOUSE,
            'source_warehouse_id' => $sourceWarehouseId,
            'source_ref' => 'TEST',
            'transacted_at' => $transactedAt,
            'status' => 'approved',
            'created_by' => $createdBy,
            'approved_by' => $createdBy,
            'approved_at' => $transactedAt,
        ]);

        return DamagedGoodItem::create([
            'damaged_good_id' => $damagedGood->id,
            'item_id' => $itemId,
            'qty' => $qty,
            'reason_code' => $reasonCode,
            'note' => 'Stok rusak test',
        ]);
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
