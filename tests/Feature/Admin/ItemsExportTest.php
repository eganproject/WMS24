<?php

namespace Tests\Feature\Admin;

use App\Exports\ItemsExport;
use App\Models\Area;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\Location;
use App\Support\WarehouseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class ItemsExportTest extends TestCase
{
    use RefreshDatabase;

    private function location(string $areaCode, int $warehouseId, string $rack): Location
    {
        $area = Area::firstOrCreate(
            ['code' => $areaCode],
            ['warehouse_id' => $warehouseId, 'name' => $areaCode, 'is_active' => true]
        );

        return Location::create([
            'area_id' => $area->id,
            'rack_code' => $rack,
            'column_no' => 1,
            'row_no' => 1,
            'code' => "{$areaCode}-{$rack}-01-01",
        ]);
    }

    public function test_export_puts_main_and_display_addresses_in_columns_b_and_c(): void
    {
        $gbId = WarehouseService::defaultWarehouseId();
        $gkId = WarehouseService::displayWarehouseId();
        $item = Item::create([
            'sku' => 'SOK57',
            'name' => 'Paket Soket',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
            'location_id' => $this->location('KAB', $gkId, 'A')->id,
            'secondary_location_id' => $this->location('KAB', $gkId, 'B')->id,
            'main_location_id' => $this->location('GB1', $gbId, 'C')->id,
        ]);
        ItemStock::create(['item_id' => $item->id, 'warehouse_id' => $gbId, 'stock' => 150]);
        Item::create(['sku' => 'OFF1', 'name' => 'Nonaktif', 'item_type' => Item::TYPE_SINGLE, 'category_id' => 0, 'status' => Item::STATUS_INACTIVE]);

        $export = new ItemsExport(Item::query()->where('status', Item::STATUS_ACTIVE)->orderBy('name'));
        $headings = $export->headings();
        $this->assertSame(['ID', 'ADDRESS GB', 'ADDRESS GK', 'SKU', 'Nama', 'Tipe', 'Status'], array_slice($headings, 0, 7));

        $rows = $export->collection();
        $this->assertCount(1, $rows);
        $row = $export->map($rows->first());
        $this->assertSame([$item->id, 'GB1-C-01-01', 'KAB-A-01-01 / KAB-B-01-01', 'SOK57', 'Paket Soket', 'single', 'Aktif', 150], array_slice($row, 0, 8));
        $this->assertCount(count($headings), $row);
    }

    public function test_export_route_downloads_xlsx_with_filters(): void
    {
        Excel::fake();

        $this->actingAs(\App\Models\User::factory()->create())
            ->withoutMiddleware([\App\Http\Middleware\AuthorizeMenuPermission::class])
            ->get(route('admin.masterdata.items.export', ['q' => 'SOK', 'status' => 'all']))
            ->assertOk();

        Excel::matchByRegex();
        Excel::assertDownloaded('/^items-\d+\.xlsx$/', fn (ItemsExport $export) => true);
    }
}
