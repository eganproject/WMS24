<?php

namespace Tests\Feature;

use App\Exports\StockMovementAnalysisExport;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\Menu;
use App\Models\Role;
use App\Models\StockMutation;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\StockBalanceReportMenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class StockBalanceReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_reconstructs_opening_and_ending_stock_for_a_date_range(): void
    {
        $user = $this->adminUser();
        $warehouse = Warehouse::query()->where('code', config('inventory.default_warehouse_code'))->firstOrFail();
        $item = Item::create([
            'sku' => 'SKU-SALDO-01',
            'name' => 'Barang Uji Saldo',
            'item_type' => Item::TYPE_SINGLE,
            'status' => Item::STATUS_ACTIVE,
        ]);

        // Saldo dasar 100 + 20 sebelum periode + 50 masuk - 30 keluar - 10 setelah periode.
        ItemStock::create([
            'item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'stock' => 130,
        ]);

        $this->mutation($item, $warehouse, 'in', 20, '2026-08-05 09:00:00', 1);
        $this->mutation($item, $warehouse, 'in', 50, '2026-08-12 09:00:00', 2);
        $this->mutation($item, $warehouse, 'out', 30, '2026-08-18 09:00:00', 3);
        $this->mutation($item, $warehouse, 'out', 10, '2026-08-25 09:00:00', 4);
        $this->mutation($item, $warehouse, 'in', 999, '2026-08-15 09:00:00', 5, true);

        $response = $this->actingAs($user)->getJson(route('admin.reports.stock-balance.data', [
            'date_from' => '2026-08-10',
            'date_to' => '2026-08-20',
            'warehouse_ids' => [$warehouse->id],
            'draw' => 1,
            'start' => 0,
            'length' => 25,
        ]));

        $response->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('summary.opening_stock', 120)
            ->assertJsonPath('summary.stock_in', 50)
            ->assertJsonPath('summary.stock_out', 30)
            ->assertJsonPath('summary.ending_stock', 140)
            ->assertJsonPath('data.0.sku', 'SKU-SALDO-01')
            ->assertJsonPath('data.0.opening_stock', 120)
            ->assertJsonPath('data.0.stock_in', 50)
            ->assertJsonPath('data.0.stock_out', 30)
            ->assertJsonPath('data.0.ending_stock', 140);
    }

    public function test_report_filters_warehouses_searches_items_and_rejects_invalid_periods(): void
    {
        $user = $this->adminUser();
        $mainWarehouse = Warehouse::query()->where('code', config('inventory.default_warehouse_code'))->firstOrFail();
        $otherWarehouse = Warehouse::create(['code' => 'TEST_REPORT', 'name' => 'Gudang Uji Laporan']);
        $firstItem = Item::create(['sku' => 'FILTER-ONE', 'name' => 'Barang Pertama', 'item_type' => Item::TYPE_SINGLE]);
        $secondItem = Item::create(['sku' => 'FILTER-TWO', 'name' => 'Barang Kedua', 'item_type' => Item::TYPE_SINGLE]);

        ItemStock::create(['item_id' => $firstItem->id, 'warehouse_id' => $mainWarehouse->id, 'stock' => 10]);
        ItemStock::create(['item_id' => $secondItem->id, 'warehouse_id' => $otherWarehouse->id, 'stock' => 20]);

        $allWarehousesResponse = $this->actingAs($user)->getJson(route('admin.reports.stock-balance.data', [
            'date_from' => '2026-08-01',
            'date_to' => '2026-08-31',
        ]));
        $allWarehousesResponse->assertOk()
            ->assertJsonPath('recordsFiltered', 2)
            ->assertJsonPath('summary.total_warehouses', 2)
            ->assertJsonPath('summary.ending_stock', 30);

        $multipleWarehousesResponse = $this->actingAs($user)->getJson(route('admin.reports.stock-balance.data', [
            'date_from' => '2026-08-01',
            'date_to' => '2026-08-31',
            'warehouse_ids' => [$mainWarehouse->id, $otherWarehouse->id],
            'q' => 'FILTER',
        ]));
        $multipleWarehousesResponse->assertOk()
            ->assertJsonPath('recordsFiltered', 2)
            ->assertJsonPath('summary.total_warehouses', 2)
            ->assertJsonPath('summary.ending_stock', 30);

        $response = $this->actingAs($user)->getJson(route('admin.reports.stock-balance.data', [
            'date_from' => '2026-08-01',
            'date_to' => '2026-08-31',
            'warehouse_ids' => [$otherWarehouse->id],
            'q' => 'FILTER-TWO',
        ]));

        $response->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('summary.total_items', 1)
            ->assertJsonPath('summary.ending_stock', 20)
            ->assertJsonPath('data.0.warehouse_id', $otherWarehouse->id)
            ->assertJsonPath('data.0.sku', 'FILTER-TWO');

        $this->actingAs($user)->getJson(route('admin.reports.stock-balance.data', [
            'date_from' => '2026-08-31',
            'date_to' => '2026-08-01',
        ]))->assertUnprocessable()->assertJsonValidationErrors('date_to');

        $this->actingAs($user)
            ->get(route('admin.reports.stock-balance.index'))
            ->assertOk()
            ->assertSee('Laporan Saldo Stok')
            ->assertSee('Stok Awal')
            ->assertSee('Analisis Pergerakan')
            ->assertSee('Fast Moving')
            ->assertSee('Dead Stock')
            ->assertSee('id="btn_export_stock_movement"', false)
            ->assertSee('Export Analisis')
            ->assertSee('Seluruh Gudang')
            ->assertSee('Gudang (bisa pilih beberapa)');

        $this->actingAs($user)
            ->get(route('admin.reports.stock-balance.export', [
                'date_from' => '2026-08-01',
                'date_to' => '2026-08-31',
                'warehouse_ids' => [$mainWarehouse->id, $otherWarehouse->id],
            ]))
            ->assertOk()
            ->assertDownload();
    }

    public function test_movement_analysis_classifies_actual_demand_and_ignores_internal_mutations(): void
    {
        $user = $this->adminUser();
        $warehouse = Warehouse::query()->where('code', config('inventory.default_warehouse_code'))->firstOrFail();
        $items = collect([
            'FAST' => ['stock' => 72, 'qty' => 28],
            'MEDIUM' => ['stock' => 46, 'qty' => 4],
            'SLOW' => ['stock' => 19, 'qty' => 1],
            'DEAD' => ['stock' => 10, 'qty' => 0],
            'EMPTY' => ['stock' => 0, 'qty' => 0],
        ])->map(function (array $data, string $sku) use ($warehouse) {
            $item = Item::create([
                'sku' => 'MOVE-'.$sku,
                'name' => 'Barang '.$sku,
                'item_type' => Item::TYPE_SINGLE,
                'status' => Item::STATUS_ACTIVE,
            ]);
            ItemStock::create(['item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'stock' => $data['stock']]);

            return ['item' => $item, ...$data];
        });

        $sourceId = 100;
        foreach (['FAST', 'MEDIUM', 'SLOW'] as $key) {
            $this->mutation(
                $items[$key]['item'],
                $warehouse,
                'out',
                $items[$key]['qty'],
                '2026-08-15 09:00:00',
                $sourceId++,
                false,
                'outbound',
                'manual'
            );
        }

        // Transfer mengurangi saldo, tetapi tidak boleh dianggap sebagai demand.
        $this->mutation($items['DEAD']['item'], $warehouse, 'out', 5, '2026-08-16 09:00:00', $sourceId, false, 'transfer');

        $response = $this->actingAs($user)->getJson(route('admin.reports.stock-balance.data', [
            'analysis' => 'movement',
            'date_from' => '2026-08-01',
            'date_to' => '2026-08-28',
            'warehouse_ids' => [$warehouse->id],
            'draw' => 1,
            'start' => 0,
            'length' => 25,
        ]));

        $response->assertOk()
            ->assertJsonPath('recordsFiltered', 5)
            ->assertJsonPath('period.days', 28)
            ->assertJsonPath('summary.fast_items', 1)
            ->assertJsonPath('summary.medium_items', 1)
            ->assertJsonPath('summary.slow_items', 1)
            ->assertJsonPath('summary.dead_stock_items', 1)
            ->assertJsonPath('summary.no_stock_items', 1)
            ->assertJsonPath('summary.demand_out', 33);

        $categories = collect($response->json('data'))->pluck('movement_category', 'sku');
        $this->assertSame('fast', $categories['MOVE-FAST']);
        $this->assertSame('medium', $categories['MOVE-MEDIUM']);
        $this->assertSame('slow', $categories['MOVE-SLOW']);
        $this->assertSame('dead_stock', $categories['MOVE-DEAD']);
        $this->assertSame('no_stock', $categories['MOVE-EMPTY']);

        $this->actingAs($user)->getJson(route('admin.reports.stock-balance.data', [
            'analysis' => 'movement',
            'date_from' => '2026-08-01',
            'date_to' => '2026-08-28',
            'warehouse_ids' => [$warehouse->id],
            'movement_category' => 'dead_stock',
        ]))->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.sku', 'MOVE-DEAD');
    }

    public function test_movement_export_downloads_a_clean_analysis_workbook(): void
    {
        $user = $this->adminUser();
        $warehouse = Warehouse::query()->where('code', config('inventory.default_warehouse_code'))->firstOrFail();
        $item = Item::create([
            'sku' => 'MOVE-EXPORT',
            'name' => 'Barang Export Analisis',
            'item_type' => Item::TYPE_SINGLE,
            'status' => Item::STATUS_ACTIVE,
        ]);
        ItemStock::create(['item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'stock' => 7]);
        $this->mutation($item, $warehouse, 'out', 28, '2026-08-15 09:00:00', 501, false, 'outbound', 'manual');

        $filters = [
            'analysis' => 'movement',
            'date_from' => '2026-08-01',
            'date_to' => '2026-08-28',
            'warehouse_ids' => [$warehouse->id],
        ];

        $this->freezeTime();
        $this->actingAs($user)
            ->get(route('admin.reports.stock-balance.export', $filters))
            ->assertOk()
            ->assertDownload('analisis-pergerakan-stok-2026-08-01-sd-2026-08-28-'.now()->format('His').'.xlsx');

        unset($filters['analysis']);
        $binary = Excel::raw(new StockMovementAnalysisExport($filters), ExcelWriter::XLSX);
        $path = tempnam(sys_get_temp_dir(), 'stock-movement-export').'.xlsx';
        file_put_contents($path, $binary);

        try {
            $workbook = IOFactory::load($path);
            $this->assertSame(['Ringkasan Analisis', 'Detail Semua SKU', 'Fokus Tindak Lanjut'], $workbook->getSheetNames());
            $this->assertSame('Laporan Analisis Pergerakan Stok', $workbook->getSheetByName('Ringkasan Analisis')->getCell('A1')->getValue());
            $this->assertSame('MOVE-EXPORT', $workbook->getSheetByName('Detail Semua SKU')->getCell('B6')->getValue());
            $this->assertSame('Fast Moving', $workbook->getSheetByName('Detail Semua SKU')->getCell('E6')->getValue());
            $this->assertSame(28, $workbook->getSheetByName('Detail Semua SKU')->getCell('G6')->getValue());
            $this->assertSame('MOVE-EXPORT', $workbook->getSheetByName('Fokus Tindak Lanjut')->getCell('B6')->getValue());
        } finally {
            @unlink($path);
        }
    }

    public function test_menu_seeder_does_not_overwrite_existing_production_menus_or_permissions(): void
    {
        $reportsMenu = Menu::query()->create([
            'name' => 'Laporan Production',
            'slug' => 'reports',
            'route' => null,
            'icon' => 'custom-reports-icon',
            'sort_order' => 77,
            'is_active' => true,
        ]);
        $unrelatedMenu = Menu::query()->create([
            'name' => 'Menu Custom Production',
            'slug' => 'production-custom-menu',
            'route' => 'production.custom.index',
            'icon' => 'custom-icon',
            'parent_id' => $reportsMenu->id,
            'sort_order' => 88,
            'is_active' => false,
        ]);
        $stockBalanceMenu = Menu::query()->create([
            'name' => 'Nama Custom Saldo',
            'slug' => 'report-stock-balance',
            'route' => 'custom.stock-balance.route',
            'icon' => 'custom-balance-icon',
            'parent_id' => null,
            'sort_order' => 99,
            'is_active' => false,
        ]);
        $adminRole = Role::query()->firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Administrator', 'description' => 'Full access']
        );
        DB::table('permission_menu')->insert([
            'role_id' => $adminRole->id,
            'menu_id' => $stockBalanceMenu->id,
            'can_view' => false,
            'can_create' => true,
            'can_update' => true,
            'can_delete' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $menuCountBefore = Menu::query()->count();
        $this->seed(StockBalanceReportMenuSeeder::class);

        $this->assertSame($menuCountBefore, Menu::query()->count());
        $this->assertSame('Menu Custom Production', $unrelatedMenu->fresh()->name);
        $this->assertSame('production.custom.index', $unrelatedMenu->fresh()->route);
        $this->assertSame('Nama Custom Saldo', $stockBalanceMenu->fresh()->name);
        $this->assertSame('custom.stock-balance.route', $stockBalanceMenu->fresh()->route);
        $this->assertSame('custom-balance-icon', $stockBalanceMenu->fresh()->icon);
        $this->assertNull($stockBalanceMenu->fresh()->parent_id);
        $this->assertFalse((bool) $stockBalanceMenu->fresh()->is_active);

        $permission = DB::table('permission_menu')
            ->where('role_id', $adminRole->id)
            ->where('menu_id', $stockBalanceMenu->id)
            ->first();
        $this->assertFalse((bool) $permission->can_view);
        $this->assertTrue((bool) $permission->can_create);
        $this->assertTrue((bool) $permission->can_update);
        $this->assertTrue((bool) $permission->can_delete);
    }

    private function mutation(
        Item $item,
        Warehouse $warehouse,
        string $direction,
        int $qty,
        string $occurredAt,
        int $sourceId,
        bool $isVoid = false,
        string $sourceType = 'report_test',
        ?string $sourceSubtype = null
    ): void {
        StockMutation::create([
            'item_id' => $item->id,
            'reference_item_id' => $item->id,
            'reference_sku' => $item->sku,
            'warehouse_id' => $warehouse->id,
            'direction' => $direction,
            'qty' => $qty,
            'source_type' => $sourceType,
            'source_subtype' => $sourceSubtype ?? 'row_'.$sourceId,
            'source_id' => $sourceId,
            'source_code' => 'TEST-'.$sourceId,
            'occurred_at' => $occurredAt,
            'is_void' => $isVoid,
        ]);
    }

    private function adminUser(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $role = Role::query()->firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Administrator', 'description' => 'Full access']
        );
        $user->roles()->syncWithoutDetaching([$role->id]);

        $menu = Menu::query()->firstOrCreate(
            ['slug' => 'report-stock-balance'],
            [
                'name' => 'Laporan Saldo Stok',
                'route' => 'admin.reports.stock-balance.index',
                'icon' => 'fas fa-balance-scale',
                'sort_order' => 1,
                'is_active' => true,
            ]
        );
        DB::table('permission_menu')->updateOrInsert(
            ['role_id' => $role->id, 'menu_id' => $menu->id],
            [
                'can_view' => true,
                'can_create' => false,
                'can_update' => false,
                'can_delete' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        return $user;
    }
}
