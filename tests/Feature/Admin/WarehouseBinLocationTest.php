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

class WarehouseBinLocationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Lewati auth/permission tapi tetap jalankan route model binding.
     */
    private function bindingOnly(): self
    {
        return $this->actingAs(\App\Models\User::factory()->create())
            ->withoutMiddleware([
                \App\Http\Middleware\AuthorizeMenuPermission::class,
                \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            ]);
    }

    private function displayArea(string $code = 'KAB'): Area
    {
        return Area::create([
            'warehouse_id' => WarehouseService::displayWarehouseId(),
            'code' => $code,
            'name' => $code,
            'is_active' => true,
        ]);
    }

    private function mainArea(string $code = 'GB1'): Area
    {
        return Area::create([
            'warehouse_id' => WarehouseService::defaultWarehouseId(),
            'code' => $code,
            'name' => $code,
            'is_active' => true,
        ]);
    }

    public function test_area_can_be_created_for_main_warehouse(): void
    {
        $response = $this->withoutMiddleware()->postJson(route('admin.masterdata.areas.store'), [
            'code' => 'gb1',
            'name' => 'Gudang Besar Zona 1',
            'warehouse_id' => WarehouseService::defaultWarehouseId(),
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('areas', [
            'code' => 'GB1',
            'warehouse_id' => WarehouseService::defaultWarehouseId(),
        ]);
    }

    public function test_area_without_warehouse_defaults_to_display(): void
    {
        $this->withoutMiddleware()->postJson(route('admin.masterdata.areas.store'), [
            'code' => 'KAB',
            'name' => 'Display',
        ])->assertOk();

        $this->assertDatabaseHas('areas', [
            'code' => 'KAB',
            'warehouse_id' => WarehouseService::displayWarehouseId(),
        ]);
    }

    public function test_item_stores_display_and_main_warehouse_locations_separately(): void
    {
        $display = $this->displayArea();
        $main = $this->mainArea();

        $response = $this->withoutMiddleware()->postJson(route('admin.masterdata.items.store'), [
            'sku' => 'SKU-BIN-001',
            'name' => 'Item Bin',
            'item_type' => Item::TYPE_SINGLE,
            'area_id' => $display->id,
            'rack_code' => 'a',
            'column_no' => 3,
            'row_no' => 5,
            'main_area_id' => $main->id,
            'main_rack_code' => 'b',
            'main_column_no' => 1,
            'main_row_no' => 2,
        ]);

        $response->assertOk();
        $item = Item::where('sku', 'SKU-BIN-001')->firstOrFail();
        $this->assertSame('KAB-A-03-05', $item->resolvedAddress());
        $this->assertSame('GB1-B-01-02', $item->mainAddress());
        $this->assertSame($display->id, $item->area_id);

        $data = $this->withoutMiddleware()->getJson(route('admin.masterdata.items.data', ['q' => 'GB1-B-01-02']));
        $data->assertOk()
            ->assertJsonPath('data.0.sku', 'SKU-BIN-001')
            ->assertJsonPath('data.0.main_address', 'GB1-B-01-02');
    }

    public function test_main_location_rejects_display_area_and_partial_slot(): void
    {
        $display = $this->displayArea();
        $main = $this->mainArea();

        $this->withoutMiddleware()->postJson(route('admin.masterdata.items.store'), [
            'sku' => 'SKU-BIN-002',
            'name' => 'Item Bin',
            'item_type' => Item::TYPE_SINGLE,
            'main_area_id' => $display->id,
            'main_rack_code' => 'A',
            'main_column_no' => 1,
            'main_row_no' => 1,
        ])->assertStatus(422)->assertJsonValidationErrors('main_area_id');

        $this->withoutMiddleware()->postJson(route('admin.masterdata.items.store'), [
            'sku' => 'SKU-BIN-003',
            'name' => 'Item Bin',
            'item_type' => Item::TYPE_SINGLE,
            'main_area_id' => $main->id,
        ])->assertStatus(422)->assertJsonValidationErrors('main_rack_code');
    }

    public function test_display_location_rejects_main_warehouse_area(): void
    {
        $main = $this->mainArea();

        $this->withoutMiddleware()->postJson(route('admin.masterdata.items.store'), [
            'sku' => 'SKU-BIN-004',
            'name' => 'Item Bin',
            'item_type' => Item::TYPE_SINGLE,
            'area_id' => $main->id,
        ])->assertStatus(422)->assertJsonValidationErrors('area_id');
    }

    public function test_clearing_main_location_fields_removes_main_location(): void
    {
        $main = $this->mainArea();
        $location = Location::create([
            'area_id' => $main->id,
            'rack_code' => 'A',
            'column_no' => 1,
            'row_no' => 1,
            'code' => 'GB1-A-01-01',
        ]);
        $item = Item::create([
            'sku' => 'SKU-BIN-005',
            'name' => 'Item Bin',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
            'main_location_id' => $location->id,
        ]);

        $this->bindingOnly()->putJson(route('admin.masterdata.items.update', $item), [
            'sku' => 'SKU-BIN-005',
            'name' => 'Item Bin',
            'item_type' => Item::TYPE_SINGLE,
            'main_area_id' => '',
            'main_rack_code' => '',
            'main_column_no' => '',
            'main_row_no' => '',
        ])->assertOk();

        $this->assertNull($item->fresh()->main_location_id);
    }

    public function test_area_warehouse_cannot_change_while_used_by_items(): void
    {
        $display = $this->displayArea();
        Item::create([
            'sku' => 'SKU-BIN-006',
            'name' => 'Item Bin',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
            'area_id' => $display->id,
            'address' => 'KAB',
        ]);

        $this->bindingOnly()->putJson(route('admin.masterdata.areas.update', $display), [
            'code' => 'KAB',
            'name' => 'KAB',
            'warehouse_id' => WarehouseService::defaultWarehouseId(),
        ])->assertStatus(422)->assertJsonValidationErrors('warehouse_id');
    }

    public function test_items_import_sets_main_location_and_creates_main_area(): void
    {
        $import = new ItemsImport();
        $import->collection(collect([
            new Collection([
                'sku' => 'SKU-BIN-IMP',
                'name' => 'Item Import',
                'address' => 'KAB-A-1-1',
                'main_address' => 'GB2-C-4-6',
            ]),
        ]));

        $item = Item::where('sku', 'SKU-BIN-IMP')->firstOrFail();
        $this->assertSame('KAB-A-01-01', $item->resolvedAddress());
        $this->assertSame('GB2-C-04-06', $item->mainAddress());
        $this->assertSame(
            WarehouseService::defaultWarehouseId(),
            (int) Area::where('code', 'GB2')->value('warehouse_id')
        );
    }

    public function test_item_updates_import_rejects_main_address_in_display_area(): void
    {
        $this->displayArea();
        Item::create([
            'sku' => 'SKU-BIN-UPD',
            'name' => 'Item Update',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
        ]);

        $this->expectException(ValidationException::class);
        (new ItemUpdatesImport(['main_address']))->collection(collect([
            new Collection(['sku' => 'SKU-BIN-UPD', 'main_address' => 'KAB-A-01-01']),
        ]));
    }

    public function test_item_updates_import_sets_main_address(): void
    {
        $this->mainArea();
        $item = Item::create([
            'sku' => 'SKU-BIN-UPD2',
            'name' => 'Item Update',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
        ]);

        (new ItemUpdatesImport(['main_address']))->collection(collect([
            new Collection(['sku' => 'SKU-BIN-UPD2', 'main_address' => 'GB1-A-2-3']),
        ]));

        $this->assertSame('GB1-A-02-03', $item->fresh()->mainAddress());
    }
}
