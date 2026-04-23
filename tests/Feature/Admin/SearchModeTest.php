<?php

namespace Tests\Feature\Admin;

use App\Models\CustomerReturn;
use App\Models\CustomerReturnItem;
use App\Models\Item;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class SearchModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_data_supports_contains_and_exact_search_modes(): void
    {
        Supplier::create(['name' => 'BAN']);
        Supplier::create(['name' => 'BAN BESAR']);

        $containsResponse = $this->withoutMiddleware()->getJson(route('admin.masterdata.suppliers.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 25,
            'q' => 'BAN',
        ]));

        $containsResponse->assertOk();
        $containsNames = Collection::make($containsResponse->json('data'))->pluck('name')->all();
        $this->assertSame(['BAN', 'BAN BESAR'], $containsNames);

        $exactResponse = $this->withoutMiddleware()->getJson(route('admin.masterdata.suppliers.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 25,
            'q' => 'ban',
            'search_mode' => 'exact',
        ]));

        $exactResponse->assertOk();
        $exactNames = Collection::make($exactResponse->json('data'))->pluck('name')->all();
        $this->assertSame(['BAN'], $exactNames);
    }

    public function test_customer_return_data_supports_exact_search_for_related_item_sku(): void
    {
        $user = User::factory()->create();

        $exactItem = Item::create([
            'sku' => 'RET-001',
            'name' => 'Item Retur Tepat',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
        ]);
        $partialItem = Item::create([
            'sku' => 'RET-001-EXTRA',
            'name' => 'Item Retur Partial',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
        ]);

        $exactReturn = CustomerReturn::create([
            'code' => 'CRT-EXACT-001',
            'resi_no' => 'RESI-EXACT-001',
            'received_at' => now()->subHour(),
            'status' => CustomerReturn::STATUS_INSPECTED,
            'created_by' => $user->id,
            'inspected_by' => $user->id,
        ]);
        CustomerReturnItem::create([
            'customer_return_id' => $exactReturn->id,
            'item_id' => $exactItem->id,
            'expected_qty' => 1,
            'received_qty' => 1,
            'good_qty' => 1,
            'damaged_qty' => 0,
        ]);

        $partialReturn = CustomerReturn::create([
            'code' => 'CRT-EXACT-002',
            'resi_no' => 'RESI-EXACT-002',
            'received_at' => now(),
            'status' => CustomerReturn::STATUS_INSPECTED,
            'created_by' => $user->id,
            'inspected_by' => $user->id,
        ]);
        CustomerReturnItem::create([
            'customer_return_id' => $partialReturn->id,
            'item_id' => $partialItem->id,
            'expected_qty' => 1,
            'received_qty' => 1,
            'good_qty' => 1,
            'damaged_qty' => 0,
        ]);

        $containsResponse = $this->withoutMiddleware()->getJson(route('admin.inventory.customer-returns.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 25,
            'q' => 'RET-001',
        ]));

        $containsResponse->assertOk();
        $containsCodes = Collection::make($containsResponse->json('data'))->pluck('code')->sort()->values()->all();
        $this->assertSame(['CRT-EXACT-001', 'CRT-EXACT-002'], $containsCodes);

        $exactResponse = $this->withoutMiddleware()->getJson(route('admin.inventory.customer-returns.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 25,
            'q' => 'ret-001',
            'search_mode' => 'exact',
        ]));

        $exactResponse->assertOk();
        $exactCodes = Collection::make($exactResponse->json('data'))->pluck('code')->all();
        $this->assertSame(['CRT-EXACT-001'], $exactCodes);
    }
}
