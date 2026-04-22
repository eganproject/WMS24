<?php

namespace Tests\Feature\Admin;

use App\Models\Item;
use App\Models\ItemStock;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class SafetyStockReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_low_stock_report_uses_allowed_warehouse_filter_and_exposes_safety_source(): void
    {
        $mainWarehouse = $this->firstOrCreateWarehouse('GUDANG_BESAR', 'Gudang Besar', 'main');
        $displayWarehouse = $this->firstOrCreateWarehouse('GUDANG_DISPLAY', 'Gudang Display', 'display');
        $damagedWarehouse = $this->firstOrCreateWarehouse('GUDANG_RUSAK', 'Gudang Rusak', 'damaged');

        $defaultSafetyItem = Item::create([
            'sku' => 'SKU-LOW-MAIN',
            'name' => 'Low Main',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
            'safety_stock' => 10,
        ]);
        ItemStock::create([
            'item_id' => $defaultSafetyItem->id,
            'warehouse_id' => $mainWarehouse->id,
            'stock' => 8,
            'safety_stock' => null,
        ]);

        $displaySafetyItem = Item::create([
            'sku' => 'SKU-LOW-DISPLAY',
            'name' => 'Low Display',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
            'safety_stock' => 2,
        ]);
        ItemStock::create([
            'item_id' => $displaySafetyItem->id,
            'warehouse_id' => $displayWarehouse->id,
            'stock' => 5,
            'safety_stock' => 6,
        ]);
        ItemStock::create([
            'item_id' => $displaySafetyItem->id,
            'warehouse_id' => $mainWarehouse->id,
            'stock' => 20,
            'safety_stock' => null,
        ]);

        $mainResponse = $this->withoutMiddleware()->getJson(route('admin.reports.low-stock.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 25,
            'warehouse_id' => $mainWarehouse->id,
        ]));

        $mainResponse->assertOk();

        $mainRows = Collection::make($mainResponse->json('data'))->keyBy('sku');
        $this->assertSame(8, $mainRows->get('SKU-LOW-MAIN')['stock']);
        $this->assertSame(10, $mainRows->get('SKU-LOW-MAIN')['safety_stock']);
        $this->assertSame('Default item', $mainRows->get('SKU-LOW-MAIN')['safety_source']);

        $displayResponse = $this->withoutMiddleware()->getJson(route('admin.reports.low-stock.data', [
            'draw' => 2,
            'start' => 0,
            'length' => 25,
            'warehouse_id' => $displayWarehouse->id,
        ]));

        $displayResponse->assertOk();

        $displayRows = Collection::make($displayResponse->json('data'))->keyBy('sku');
        $this->assertSame(5, $displayRows->get('SKU-LOW-DISPLAY')['stock']);
        $this->assertSame(6, $displayRows->get('SKU-LOW-DISPLAY')['safety_stock']);
        $this->assertSame('Per gudang', $displayRows->get('SKU-LOW-DISPLAY')['safety_source']);

        $damagedResponse = $this->withoutMiddleware()->getJson(route('admin.reports.low-stock.data', [
            'draw' => 3,
            'start' => 0,
            'length' => 25,
            'warehouse_id' => $damagedWarehouse->id,
        ]));

        $damagedResponse->assertOk();

        $damagedRows = Collection::make($damagedResponse->json('data'))->keyBy('sku');
        $this->assertTrue($damagedRows->has('SKU-LOW-MAIN'));
        $this->assertFalse($damagedRows->has('SKU-LOW-DISPLAY'));
        $this->assertSame(8, $damagedRows->get('SKU-LOW-MAIN')['stock']);
    }

    public function test_replenishment_report_reserves_main_safety_stock_and_skips_bundles(): void
    {
        $mainWarehouse = $this->firstOrCreateWarehouse('GUDANG_BESAR', 'Gudang Besar', 'main');
        $displayWarehouse = $this->firstOrCreateWarehouse('GUDANG_DISPLAY', 'Gudang Display', 'display');

        $singleItem = Item::create([
            'sku' => 'SKU-REP-001',
            'name' => 'Replenishment Item',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
            'safety_stock' => 4,
        ]);
        ItemStock::create([
            'item_id' => $singleItem->id,
            'warehouse_id' => $mainWarehouse->id,
            'stock' => 10,
            'safety_stock' => 7,
        ]);
        ItemStock::create([
            'item_id' => $singleItem->id,
            'warehouse_id' => $displayWarehouse->id,
            'stock' => 3,
            'safety_stock' => 9,
        ]);

        Item::create([
            'sku' => 'SKU-REP-BUNDLE',
            'name' => 'Bundle Replenishment',
            'item_type' => Item::TYPE_BUNDLE,
            'category_id' => 0,
            'safety_stock' => 20,
        ]);

        $response = $this->withoutMiddleware()->getJson(route('admin.reports.replenishment.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 25,
        ]));

        $response->assertOk();

        $rows = Collection::make($response->json('data'))->keyBy('sku');
        $this->assertTrue($rows->has('SKU-REP-001'));
        $this->assertFalse($rows->has('SKU-REP-BUNDLE'));

        $row = $rows->get('SKU-REP-001');
        $this->assertSame(9, $row['safety_stock']);
        $this->assertSame('Per gudang', $row['display_safety_source']);
        $this->assertSame(10, $row['main_stock']);
        $this->assertSame(7, $row['main_safety_stock']);
        $this->assertSame(3, $row['available_main_qty']);
        $this->assertSame(6, $row['need_qty']);
        $this->assertSame(3, $row['suggest_qty']);
        $this->assertSame(1, $response->json('summary.total_items'));
        $this->assertSame(6, $response->json('summary.total_need'));
        $this->assertSame(3, $response->json('summary.total_suggest'));
    }

    public function test_replenishment_report_rounds_transfer_suggestion_to_full_koli(): void
    {
        $mainWarehouse = $this->firstOrCreateWarehouse('GUDANG_BESAR', 'Gudang Besar', 'main');
        $displayWarehouse = $this->firstOrCreateWarehouse('GUDANG_DISPLAY', 'Gudang Display', 'display');

        $roundedItem = Item::create([
            'sku' => 'SKU-REP-KOLI',
            'name' => 'Replenishment Koli',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
            'safety_stock' => 4,
            'koli_qty' => 6,
        ]);
        ItemStock::create([
            'item_id' => $roundedItem->id,
            'warehouse_id' => $mainWarehouse->id,
            'stock' => 20,
            'safety_stock' => 5,
        ]);
        ItemStock::create([
            'item_id' => $roundedItem->id,
            'warehouse_id' => $displayWarehouse->id,
            'stock' => 2,
            'safety_stock' => 9,
        ]);

        $insufficientKoliItem = Item::create([
            'sku' => 'SKU-REP-KOLI-LOW',
            'name' => 'Replenishment Koli Low',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
            'safety_stock' => 4,
            'koli_qty' => 8,
        ]);
        ItemStock::create([
            'item_id' => $insufficientKoliItem->id,
            'warehouse_id' => $mainWarehouse->id,
            'stock' => 14,
            'safety_stock' => 7,
        ]);
        ItemStock::create([
            'item_id' => $insufficientKoliItem->id,
            'warehouse_id' => $displayWarehouse->id,
            'stock' => 1,
            'safety_stock' => 5,
        ]);

        $response = $this->withoutMiddleware()->getJson(route('admin.reports.replenishment.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 25,
        ]));

        $response->assertOk();

        $rows = Collection::make($response->json('data'))->keyBy('sku');

        $roundedRow = $rows->get('SKU-REP-KOLI');
        $this->assertNotNull($roundedRow);
        $this->assertSame(6, $roundedRow['koli_qty']);
        $this->assertSame(7, $roundedRow['need_qty']);
        $this->assertSame(12, $roundedRow['need_rounded_qty']);
        $this->assertSame(15, $roundedRow['available_main_qty']);
        $this->assertSame(12, $roundedRow['available_main_rounded_qty']);
        $this->assertSame(12, $roundedRow['suggest_qty']);
        $this->assertSame(2, $roundedRow['suggest_koli']);

        $insufficientRow = $rows->get('SKU-REP-KOLI-LOW');
        $this->assertNotNull($insufficientRow);
        $this->assertSame(8, $insufficientRow['koli_qty']);
        $this->assertSame(4, $insufficientRow['need_qty']);
        $this->assertSame(8, $insufficientRow['need_rounded_qty']);
        $this->assertSame(7, $insufficientRow['available_main_qty']);
        $this->assertSame(0, $insufficientRow['available_main_rounded_qty']);
        $this->assertSame(0, $insufficientRow['suggest_qty']);
        $this->assertSame(0, $insufficientRow['suggest_koli']);

        $this->assertSame(2, $response->json('summary.total_items'));
        $this->assertSame(11, $response->json('summary.total_need'));
        $this->assertSame(12, $response->json('summary.total_suggest'));
    }

    private function firstOrCreateWarehouse(string $code, string $name, string $type): Warehouse
    {
        return Warehouse::firstOrCreate([
            'code' => $code,
        ], [
            'name' => $name,
            'type' => $type,
        ]);
    }
}
