<?php

namespace Tests\Feature\Admin;

use App\Imports\ItemUpdatesImport;
use App\Imports\ItemsImport;
use App\Models\Area;
use App\Models\Item;
use App\Models\Location;
use App\Support\WarehouseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ItemSecondaryAddressTest extends TestCase
{
    use RefreshDatabase;

    private function bindingOnly(): self
    {
        return $this->actingAs(\App\Models\User::factory()->create())
            ->withoutMiddleware([
                \App\Http\Middleware\AuthorizeMenuPermission::class,
                \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            ]);
    }

    private function area(string $code, int $warehouseId): Area
    {
        return Area::create([
            'warehouse_id' => $warehouseId,
            'code' => $code,
            'name' => $code,
            'is_active' => true,
        ]);
    }

    public function test_item_stores_second_address_for_display_and_main_warehouse(): void
    {
        $display = $this->area('KAB', WarehouseService::displayWarehouseId());
        $main = $this->area('GB1', WarehouseService::defaultWarehouseId());

        $this->withoutMiddleware()->postJson(route('admin.masterdata.items.store'), [
            'sku' => 'SKU-2ADDR',
            'name' => 'Item Dua Alamat',
            'item_type' => Item::TYPE_SINGLE,
            'area_id' => $display->id,
            'rack_code' => 'A',
            'column_no' => 1,
            'row_no' => 1,
            'secondary_area_id' => $display->id,
            'secondary_rack_code' => 'b',
            'secondary_column_no' => 2,
            'secondary_row_no' => 3,
            'main_area_id' => $main->id,
            'main_rack_code' => 'C',
            'main_column_no' => 1,
            'main_row_no' => 1,
            'main_secondary_area_id' => $main->id,
            'main_secondary_rack_code' => 'D',
            'main_secondary_column_no' => 4,
            'main_secondary_row_no' => 5,
        ])->assertOk();

        $item = Item::where('sku', 'SKU-2ADDR')->firstOrFail();
        $this->assertSame('KAB-A-01-01', $item->resolvedAddress());
        $this->assertSame('KAB-B-02-03', $item->secondaryAddress());
        $this->assertSame('GB1-C-01-01', $item->mainAddress());
        $this->assertSame('GB1-D-04-05', $item->mainSecondaryAddress());
        $this->assertSame('KAB-A-01-01 / KAB-B-02-03', $item->displayAddressLabel());
        $this->assertSame('GB1-C-01-01 / GB1-D-04-05', $item->mainAddressLabel());

        $this->withoutMiddleware()->getJson(route('admin.masterdata.items.data', ['q' => 'GB1-D-04-05']))
            ->assertOk()
            ->assertJsonPath('data.0.sku', 'SKU-2ADDR')
            ->assertJsonPath('data.0.secondary_address', 'KAB-B-02-03')
            ->assertJsonPath('data.0.main_secondary_address', 'GB1-D-04-05');
    }

    public function test_second_address_must_belong_to_matching_warehouse_and_be_complete(): void
    {
        $display = $this->area('KAB', WarehouseService::displayWarehouseId());
        $main = $this->area('GB1', WarehouseService::defaultWarehouseId());

        $this->withoutMiddleware()->postJson(route('admin.masterdata.items.store'), [
            'sku' => 'SKU-2ADDR-X',
            'name' => 'X',
            'item_type' => Item::TYPE_SINGLE,
            'secondary_area_id' => $main->id,
            'secondary_rack_code' => 'A',
            'secondary_column_no' => 1,
            'secondary_row_no' => 1,
        ])->assertStatus(422)->assertJsonValidationErrors('secondary_area_id');

        $this->withoutMiddleware()->postJson(route('admin.masterdata.items.store'), [
            'sku' => 'SKU-2ADDR-Y',
            'name' => 'Y',
            'item_type' => Item::TYPE_SINGLE,
            'main_secondary_area_id' => $display->id,
            'main_secondary_rack_code' => 'A',
            'main_secondary_column_no' => 1,
            'main_secondary_row_no' => 1,
        ])->assertStatus(422)->assertJsonValidationErrors('main_secondary_area_id');

        $this->withoutMiddleware()->postJson(route('admin.masterdata.items.store'), [
            'sku' => 'SKU-2ADDR-Z',
            'name' => 'Z',
            'item_type' => Item::TYPE_SINGLE,
            'main_secondary_area_id' => $main->id,
        ])->assertStatus(422)->assertJsonValidationErrors('main_secondary_rack_code');
    }

    public function test_existing_item_keeps_first_address_and_second_can_be_cleared(): void
    {
        $display = $this->area('KAB', WarehouseService::displayWarehouseId());
        $first = Location::create(['area_id' => $display->id, 'rack_code' => 'A', 'column_no' => 1, 'row_no' => 1, 'code' => 'KAB-A-01-01']);
        $second = Location::create(['area_id' => $display->id, 'rack_code' => 'B', 'column_no' => 1, 'row_no' => 1, 'code' => 'KAB-B-01-01']);
        $item = Item::create([
            'sku' => 'SKU-OLD',
            'name' => 'Item Lama',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
            'area_id' => $display->id,
            'location_id' => $first->id,
            'address' => 'KAB-A-01-01',
            'secondary_location_id' => $second->id,
        ]);

        $this->bindingOnly()->putJson(route('admin.masterdata.items.update', $item), [
            'sku' => 'SKU-OLD',
            'name' => 'Item Lama',
            'item_type' => Item::TYPE_SINGLE,
            'area_id' => $display->id,
            'rack_code' => 'A',
            'column_no' => 1,
            'row_no' => 1,
            'secondary_area_id' => '',
            'secondary_rack_code' => '',
            'secondary_column_no' => '',
            'secondary_row_no' => '',
        ])->assertOk();

        $item->refresh();
        $this->assertSame($first->id, (int) $item->location_id);
        $this->assertNull($item->secondary_location_id);
        $this->assertSame('KAB-A-01-01', $item->displayAddressLabel());
    }

    public function test_deleting_location_clears_second_address(): void
    {
        $main = $this->area('GB1', WarehouseService::defaultWarehouseId());
        $location = Location::create(['area_id' => $main->id, 'rack_code' => 'A', 'column_no' => 1, 'row_no' => 1, 'code' => 'GB1-A-01-01']);
        $item = Item::create([
            'sku' => 'SKU-DEL',
            'name' => 'Item',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
            'main_secondary_location_id' => $location->id,
        ]);

        $this->bindingOnly()->deleteJson(route('admin.masterdata.locations.destroy', $location))->assertOk();

        $this->assertNull($item->fresh()->main_secondary_location_id);
    }

    public function test_imports_set_second_addresses(): void
    {
        (new ItemsImport())->collection(collect([
            new Collection([
                'sku' => 'SKU-IMP2',
                'name' => 'Item Import',
                'address' => 'KAB-A-1-1',
                'address_2' => 'KAB-B-2-2',
                'main_address' => 'GB2-C-4-6',
                'main_address_2' => 'GB2-D-1-1',
            ]),
        ]));

        $item = Item::where('sku', 'SKU-IMP2')->firstOrFail();
        $this->assertSame('KAB-B-02-02', $item->secondaryAddress());
        $this->assertSame('GB2-D-01-01', $item->mainSecondaryAddress());

        (new ItemUpdatesImport(['address_2', 'main_address_2']))->collection(collect([
            new Collection(['sku' => 'SKU-IMP2', 'address_2' => 'KAB-C-3-3', 'main_address_2' => '']),
        ]));

        $item->refresh();
        $this->assertSame('KAB-C-03-03', $item->secondaryAddress());
        $this->assertNull($item->main_secondary_location_id);
        $this->assertSame('KAB-A-01-01', $item->resolvedAddress());
    }

    public function test_item_updates_import_rejects_second_main_address_in_display_area(): void
    {
        $this->area('KAB', WarehouseService::displayWarehouseId());
        Item::create([
            'sku' => 'SKU-UPD3',
            'name' => 'Item',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
        ]);

        $this->expectException(ValidationException::class);
        (new ItemUpdatesImport(['main_address_2']))->collection(collect([
            new Collection(['sku' => 'SKU-UPD3', 'main_address_2' => 'KAB-A-01-01']),
        ]));
    }
}
