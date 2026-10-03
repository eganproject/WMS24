<?php

namespace Tests\Feature;

use App\Exports\StockBalanceReportExport;
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

    public function test_report_consolidates_main_and_display_warehouses_per_sku(): void
    {
        $user = $this->adminUser();
        $mainWarehouse = Warehouse::query()->where('code', config('inventory.default_warehouse_code'))->firstOrFail();
        $displayWarehouse = Warehouse::query()->where('code', config('inventory.display_warehouse_code'))->firstOrFail();
        $otherWarehouse = Warehouse::create(['code' => 'TEST_REPORT', 'name' => 'Gudang Uji Laporan']);
        $item = Item::create([
            'sku' => 'SKU-SALDO-01',
            'name' => 'Barang Uji Saldo',
            'item_type' => Item::TYPE_SINGLE,
            'status' => Item::STATUS_ACTIVE,
        ]);
        $otherOnlyItem = Item::create(['sku' => 'SKU-LAIN', 'name' => 'Barang Gudang Lain', 'item_type' => Item::TYPE_SINGLE]);

        // Stok terkini gabungan Besar + Display = 140. Gudang lain tidak ikut dihitung.
        ItemStock::create(['item_id' => $item->id, 'warehouse_id' => $mainWarehouse->id, 'stock' => 100]);
        ItemStock::create(['item_id' => $item->id, 'warehouse_id' => $displayWarehouse->id, 'stock' => 40]);
        ItemStock::create(['item_id' => $item->id, 'warehouse_id' => $otherWarehouse->id, 'stock' => 500]);
        ItemStock::create(['item_id' => $otherOnlyItem->id, 'warehouse_id' => $otherWarehouse->id, 'stock' => 20]);

        // Masuk: hanya inbound ke Gudang Besar.
        $this->mutation($item, $mainWarehouse, 'in', 50, '2026-08-11 09:00:00', 1, false, 'inbound', 'manual');
        // Keluar: QC scan resi + outbound manual.
        $this->mutation($item, $displayWarehouse, 'out', 12, '2026-08-12 09:00:00', 2, false, 'qc_shipment', 'resi');
        $this->mutation($item, $mainWarehouse, 'out', 8, '2026-08-13 09:00:00', 3, false, 'outbound', 'manual');
        // Mutasi lain: inbound ke Display, transfer internal, retur outbound, retur pelanggan, opname.
        $this->mutation($item, $displayWarehouse, 'in', 7, '2026-08-14 09:00:00', 4, false, 'inbound', 'manual');
        $this->mutation($item, $mainWarehouse, 'out', 15, '2026-08-15 09:00:00', 5, false, 'transfer', 'send');
        $this->mutation($item, $displayWarehouse, 'in', 15, '2026-08-15 10:00:00', 6, false, 'transfer', 'receive');
        $this->mutation($item, $mainWarehouse, 'out', 3, '2026-08-16 09:00:00', 7, false, 'outbound', 'return');
        $this->mutation($item, $displayWarehouse, 'in', 4, '2026-08-17 09:00:00', 8, false, 'customer_return', 'good');
        $this->mutation($item, $mainWarehouse, 'out', 2, '2026-08-18 09:00:00', 9, false, 'opname', 'approve');
        // Diabaikan: mutasi batal dan gudang di luar cakupan.
        $this->mutation($item, $mainWarehouse, 'in', 999, '2026-08-15 09:00:00', 10, true, 'inbound', 'manual');
        $this->mutation($item, $otherWarehouse, 'in', 300, '2026-08-15 09:00:00', 11, false, 'inbound', 'manual');
        // Setelah periode.
        $this->mutation($item, $displayWarehouse, 'out', 10, '2026-08-25 09:00:00', 12, false, 'qc_shipment', 'resi');

        $response = $this->actingAs($user)->getJson(route('admin.reports.stock-balance.data', [
            'date_from' => '2026-08-10',
            'date_to' => '2026-08-20',
            'draw' => 1,
            'start' => 0,
            'length' => 25,
        ]));

        // Awal 114 + masuk 50 - keluar 20 + lain 6 = akhir 150.
        $response->assertOk()
            ->assertJsonPath('recordsTotal', 1)
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('summary.total_items', 1)
            ->assertJsonPath('summary.opening_stock', 114)
            ->assertJsonPath('summary.stock_in', 50)
            ->assertJsonPath('summary.stock_out', 20)
            ->assertJsonPath('summary.other_net', 6)
            ->assertJsonPath('summary.ending_stock', 150)
            ->assertJsonPath('data.0.sku', 'SKU-SALDO-01')
            ->assertJsonPath('data.0.opening_stock', 114)
            ->assertJsonPath('data.0.stock_in', 50)
            ->assertJsonPath('data.0.stock_out', 20)
            ->assertJsonPath('data.0.other_net', 6)
            ->assertJsonPath('data.0.ending_stock', 150)
            ->assertJsonMissingPath('data.0.warehouse_id');

        $this->actingAs($user)->getJson(route('admin.reports.stock-balance.data', [
            'date_from' => '2026-08-10',
            'date_to' => '2026-08-20',
            'q' => 'TIDAK-ADA',
        ]))->assertOk()
            ->assertJsonPath('recordsTotal', 1)
            ->assertJsonPath('recordsFiltered', 0);

        $this->actingAs($user)->getJson(route('admin.reports.stock-balance.data', [
            'date_from' => '2026-08-31',
            'date_to' => '2026-08-01',
        ]))->assertUnprocessable()->assertJsonValidationErrors('date_to');
    }

    public function test_balance_page_and_export_use_consolidated_columns(): void
    {
        $user = $this->adminUser();
        $mainWarehouse = Warehouse::query()->where('code', config('inventory.default_warehouse_code'))->firstOrFail();
        $displayWarehouse = Warehouse::query()->where('code', config('inventory.display_warehouse_code'))->firstOrFail();
        $item = Item::create(['sku' => 'EXPORT-SALDO', 'name' => 'Barang Export Saldo', 'item_type' => Item::TYPE_SINGLE]);
        $idleItem = Item::create(['sku' => 'IDLE-SALDO', 'name' => 'Barang Diam', 'item_type' => Item::TYPE_SINGLE]);
        $soldOutItem = Item::create(['sku' => 'HABIS-SALDO', 'name' => 'Barang Habis', 'item_type' => Item::TYPE_SINGLE]);
        $minusItem = Item::create(['sku' => 'MINUS-SALDO', 'name' => 'Barang Minus', 'item_type' => Item::TYPE_SINGLE]);

        ItemStock::create(['item_id' => $item->id, 'warehouse_id' => $mainWarehouse->id, 'stock' => 30]);
        ItemStock::create(['item_id' => $item->id, 'warehouse_id' => $displayWarehouse->id, 'stock' => 5]);
        ItemStock::create(['item_id' => $idleItem->id, 'warehouse_id' => $displayWarehouse->id, 'stock' => 3]);
        ItemStock::create(['item_id' => $soldOutItem->id, 'warehouse_id' => $displayWarehouse->id, 'stock' => 0]);
        ItemStock::create(['item_id' => $minusItem->id, 'warehouse_id' => $mainWarehouse->id, 'stock' => -1]);
        $this->mutation($item, $mainWarehouse, 'in', 20, '2026-08-05 09:00:00', 1, false, 'inbound', 'manual');
        $this->mutation($item, $displayWarehouse, 'out', 4, '2026-08-06 09:00:00', 2, false, 'qc_shipment', 'resi');
        $this->mutation($item, $mainWarehouse, 'out', 1, '2026-08-07 09:00:00', 3, false, 'adjustment', 'approve');
        $this->mutation($soldOutItem, $displayWarehouse, 'out', 2, '2026-08-08 09:00:00', 4, false, 'qc_shipment', 'resi');

        $this->actingAs($user)
            ->get(route('admin.reports.stock-balance.index'))
            ->assertOk()
            ->assertSee('Laporan Saldo Stok')
            ->assertSee('Stok Awal')
            ->assertSee('Mutasi Lain')
            ->assertSee('Gudang Besar + Gudang Display')
            ->assertSee('Analisis Pergerakan')
            ->assertSee('Fast Moving')
            ->assertSee('Non Moving')
            ->assertSee('id="btn_export_stock_movement"', false)
            ->assertSee('Export Analisis')
            ->assertDontSee('id="filter_warehouse"', false);

        $filters = ['date_from' => '2026-08-01', 'date_to' => '2026-08-31'];

        $this->actingAs($user)
            ->get(route('admin.reports.stock-balance.export', $filters))
            ->assertOk()
            ->assertDownload();

        $binary = Excel::raw(new StockBalanceReportExport($filters + ['q' => '']), ExcelWriter::XLSX);
        $path = tempnam(sys_get_temp_dir(), 'stock-balance-export').'.xlsx';
        file_put_contents($path, $binary);

        try {
            $workbook = IOFactory::load($path);
            $this->assertSame(['Ringkasan', 'Detail Saldo per SKU', 'Perlu Perhatian'], $workbook->getSheetNames());

            $detail = $workbook->getSheetByName('Detail Saldo per SKU');
            $this->assertSame('Laporan Saldo Stok - Detail per SKU', $detail->getCell('A1')->getValue());
            $this->assertSame([
                'No', 'SKU', 'Nama Item', 'Status Item', 'Stok Awal', 'Masuk (Inbound Gudang Besar)',
                'Keluar (Outbound Manual + QC Resi)', 'Mutasi Lain (Net)', 'Saldo Akhir', 'Saldo Akhir Gudang Besar',
                'Saldo Akhir Gudang Display', 'Perubahan (Akhir - Awal)', 'Kondisi Stok', 'Keterangan',
            ], $detail->rangeToArray('A6:N6')[0]);
            // Urut nama: Diam, Export Saldo, Habis, Minus. Nilai kosong ditulis 0, bukan sel kosong.
            $this->assertSame(
                [1, 'IDLE-SALDO', 'Barang Diam', 'Aktif', 3, 0, 0, 0, 3, 0, 3, 0, 'Tersedia', 'Tidak ada pergerakan'],
                $detail->rangeToArray('A7:N7', null, false, false)[0]
            );
            // Awal 20 + masuk 20 - keluar 4 + lain (-1) = akhir 35 (Besar 30, Display 5).
            $this->assertSame(
                [2, 'EXPORT-SALDO', 'Barang Export Saldo', 'Aktif', 20, 20, 4, -1, 35, 30, 5, 15, 'Tersedia', 'Ada mutasi lain'],
                $detail->rangeToArray('A8:N8', null, false, false)[0]
            );
            $this->assertSame(
                [3, 'HABIS-SALDO', 'Barang Habis', 'Aktif', 2, 0, 2, 0, 0, 0, 0, -2, 'Habis', 'Habis setelah terjual'],
                $detail->rangeToArray('A9:N9', null, false, false)[0]
            );
            $this->assertSame('MINUS-SALDO', $detail->getCell('B10')->getValue());
            $this->assertSame('Minus', $detail->getCell('M10')->getValue());
            $this->assertSame('TOTAL', $detail->getCell('A11')->getValue());
            $this->assertSame('=SUBTOTAL(109,I7:I10)', $detail->getCell('I11')->getValue());
            $this->assertSame(37, $detail->getCell('I11')->getCalculatedValue());

            $attention = $workbook->getSheetByName('Perlu Perhatian');
            $this->assertSame(['Saldo minus', 'MINUS-SALDO'], $attention->rangeToArray('B7:C7')[0]);
            $this->assertSame(['Habis setelah terjual', 'HABIS-SALDO'], $attention->rangeToArray('B8:C8')[0]);
            $this->assertSame(['Stok tidak bergerak', 'IDLE-SALDO'], $attention->rangeToArray('B9:C9')[0]);
            $this->assertNull($attention->getCell('A10')->getValue());

            $summary = $workbook->getSheetByName('Ringkasan');
            $this->assertSame('Laporan Saldo Stok - Ringkasan', $summary->getCell('A1')->getValue());
            // Stok awal 24, masuk 20, keluar 6, mutasi lain -1, saldo akhir 37, 4 SKU.
            $this->assertSame([24, 20, 6, -1, 37, 4], $summary->rangeToArray('A6:F6', null, false, false)[0]);
            $values = collect($summary->toArray(null, true, false, false));
            $reconciliation = $values->first(fn ($row) => $row[0] === '(=) Saldo Akhir');
            $this->assertSame([37, 'Seimbang'], [$reconciliation[1], $reconciliation[2]]);
            $this->assertSame([0, 1, -1], array_slice($values->first(fn ($row) => $row[0] === 'Penyesuaian stok'), 1, 3));
            $topOutHeader = $values->search(fn ($row) => $row[0] === '10 SKU KELUAR TERBANYAK');
            $this->assertSame(['EXPORT-SALDO', 'Barang Export Saldo', null, 4, 35, 'Tersedia'], $values[$topOutHeader + 2]);
            $this->assertSame(['HABIS-SALDO', 'Barang Habis', null, 2, 0, 'Habis'], $values[$topOutHeader + 3]);
        } finally {
            @unlink($path);
        }
    }

    public function test_movement_analysis_classifies_actual_demand_and_ignores_internal_mutations(): void
    {
        $user = $this->adminUser();
        $mainWarehouse = Warehouse::query()->where('code', config('inventory.default_warehouse_code'))->firstOrFail();
        $displayWarehouse = Warehouse::query()->where('code', config('inventory.display_warehouse_code'))->firstOrFail();
        $otherWarehouse = Warehouse::create(['code' => 'IGNORED_MOVEMENT', 'name' => 'Gudang Tidak Dianalisis']);
        $items = collect([
            'FAST-A' => ['stock' => 60, 'qty' => 40],
            'FAST-B' => ['stock' => 40, 'qty' => 30],
            'MEDIUM-A' => ['stock' => 30, 'qty' => 15],
            'SLOW-A' => ['stock' => 20, 'qty' => 10],
            'SLOW-B' => ['stock' => 10, 'qty' => 5],
            'NON' => ['stock' => 8, 'qty' => 0],
        ])->map(function (array $data, string $sku) use ($mainWarehouse) {
            $item = Item::create([
                'sku' => 'MOVE-'.$sku,
                'name' => 'Barang '.$sku,
                'item_type' => Item::TYPE_SINGLE,
                'status' => Item::STATUS_ACTIVE,
            ]);
            ItemStock::create(['item_id' => $item->id, 'warehouse_id' => $mainWarehouse->id, 'stock' => $data['stock']]);

            return ['item' => $item, ...$data];
        });

        ItemStock::create(['item_id' => $items['FAST-A']['item']->id, 'warehouse_id' => $displayWarehouse->id, 'stock' => 20]);
        ItemStock::create(['item_id' => $items['FAST-A']['item']->id, 'warehouse_id' => $otherWarehouse->id, 'stock' => 999]);

        $sourceId = 100;
        $this->mutation($items['FAST-A']['item'], $mainWarehouse, 'out', 30, '2026-08-15 09:00:00', $sourceId++, false, 'outbound', 'manual');
        $this->mutation($items['FAST-A']['item'], $displayWarehouse, 'out', 10, '2026-08-15 10:00:00', $sourceId++, false, 'outbound', 'manual');
        foreach (['FAST-B', 'MEDIUM-A', 'SLOW-A', 'SLOW-B'] as $key) {
            $this->mutation($items[$key]['item'], $mainWarehouse, 'out', $items[$key]['qty'], '2026-08-15 09:00:00', $sourceId++, false, 'outbound', 'manual');
        }
        $this->mutation($items['FAST-A']['item'], $otherWarehouse, 'out', 999, '2026-08-15 11:00:00', $sourceId++, false, 'outbound', 'manual');

        // Transfer mengurangi saldo, tetapi tidak boleh dianggap sebagai demand.
        $this->mutation($items['NON']['item'], $mainWarehouse, 'out', 5, '2026-08-16 09:00:00', $sourceId, false, 'transfer');

        $response = $this->actingAs($user)->getJson(route('admin.reports.stock-balance.data', [
            'analysis' => 'movement',
            'date_from' => '2026-08-01',
            'date_to' => '2026-08-28',
            'warehouse_ids' => [$otherWarehouse->id],
            'draw' => 1,
            'start' => 0,
            'length' => 25,
        ]));

        $response->assertOk()
            ->assertJsonPath('recordsFiltered', 6)
            ->assertJsonPath('period.days', 28)
            ->assertJsonPath('summary.fast_items', 2)
            ->assertJsonPath('summary.medium_items', 1)
            ->assertJsonPath('summary.slow_items', 2)
            ->assertJsonPath('summary.non_moving_items', 1)
            ->assertJsonPath('summary.demand_out', 100);

        $categories = collect($response->json('data'))->pluck('movement_category', 'sku');
        $this->assertSame('fast', $categories['MOVE-FAST-A']);
        $this->assertSame('fast', $categories['MOVE-FAST-B']);
        $this->assertSame('medium', $categories['MOVE-MEDIUM-A']);
        $this->assertSame('slow', $categories['MOVE-SLOW-A']);
        $this->assertSame('slow', $categories['MOVE-SLOW-B']);
        $this->assertSame('non_moving', $categories['MOVE-NON']);

        $fastRow = collect($response->json('data'))->firstWhere('sku', 'MOVE-FAST-A');
        $this->assertSame(40, $fastRow['demand_out']);
        $this->assertSame(2, $fastRow['demand_documents']);
        $this->assertEquals(40.0, $fastRow['contribution_percentage']);
        $this->assertSame(80, $fastRow['ending_stock']);
        $this->assertEquals(56.0, $fastRow['stock_coverage_days']);

        $this->actingAs($user)->getJson(route('admin.reports.stock-balance.data', [
            'analysis' => 'movement',
            'date_from' => '2026-08-01',
            'date_to' => '2026-08-28',
            'warehouse_ids' => [$otherWarehouse->id],
            'movement_category' => 'non_moving',
        ]))->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.sku', 'MOVE-NON');

        $daysCoverResponse = $this->actingAs($user)->getJson(route('admin.reports.stock-balance.data', [
            'analysis' => 'movement',
            'date_from' => '2026-08-01',
            'date_to' => '2026-08-28',
            'days_cover' => '31_to_60',
        ]));

        $daysCoverResponse->assertOk()
            ->assertJsonPath('recordsFiltered', 5)
            ->assertJsonPath('summary.total_items', 5);
        $this->assertTrue(collect($daysCoverResponse->json('data'))->every(fn (array $row) => $row['stock_coverage_days'] > 30 && $row['stock_coverage_days'] <= 60));

        $this->actingAs($user)->getJson(route('admin.reports.stock-balance.data', [
            'analysis' => 'movement',
            'date_from' => '2026-08-01',
            'date_to' => '2026-08-28',
            'days_cover' => 'unavailable',
        ]))->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.sku', 'MOVE-NON');
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
