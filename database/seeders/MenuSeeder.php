<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $menuRows = [
            ['name' => 'Dashboard', 'slug' => 'dashboard', 'route' => 'admin.dashboard', 'icon' => 'fas fa-tachometer-alt', 'parent_slug' => null, 'sort_order' => 0],
            ['name' => 'Master Data', 'slug' => 'master-data', 'route' => null, 'icon' => 'fas fa-database', 'parent_slug' => null, 'sort_order' => 10],
            ['name' => 'Inventory', 'slug' => 'inventory', 'route' => null, 'icon' => 'fas fa-warehouse', 'parent_slug' => null, 'sort_order' => 12],
            ['name' => 'Inbound', 'slug' => 'inbound', 'route' => null, 'icon' => 'fas fa-arrow-down', 'parent_slug' => null, 'sort_order' => 13],
            ['name' => 'Outbound', 'slug' => 'outbound', 'route' => null, 'icon' => 'fas fa-arrow-up', 'parent_slug' => null, 'sort_order' => 14],
            ['name' => 'Laporan', 'slug' => 'reports', 'route' => null, 'icon' => 'fas fa-chart-line', 'parent_slug' => null, 'sort_order' => 15],
            ['name' => 'Users', 'slug' => 'users', 'route' => 'admin.masterdata.users.index', 'icon' => 'fas fa-users', 'parent_slug' => 'master-data', 'sort_order' => 20],
            ['name' => 'Roles', 'slug' => 'roles', 'route' => 'admin.masterdata.roles.index', 'icon' => 'fas fa-user-shield', 'parent_slug' => 'master-data', 'sort_order' => 21],
            ['name' => 'Kurir', 'slug' => 'kurir', 'route' => 'admin.masterdata.kurir.index', 'icon' => 'fas fa-truck', 'parent_slug' => 'master-data', 'sort_order' => 21.45],
            ['name' => 'Categories', 'slug' => 'categories', 'route' => 'admin.masterdata.categories.index', 'icon' => 'fas fa-sitemap', 'parent_slug' => 'master-data', 'sort_order' => 21.5],
            ['name' => 'Items', 'slug' => 'items', 'route' => 'admin.masterdata.items.index', 'icon' => 'fas fa-box', 'parent_slug' => 'master-data', 'sort_order' => 21.6],
            ['name' => 'Areas', 'slug' => 'areas', 'route' => 'admin.masterdata.areas.index', 'icon' => 'fas fa-road', 'parent_slug' => 'master-data', 'sort_order' => 21.61],
            ['name' => 'Locations', 'slug' => 'locations', 'route' => 'admin.masterdata.locations.index', 'icon' => 'fas fa-map-marker-alt', 'parent_slug' => 'master-data', 'sort_order' => 21.62],
            ['name' => 'Item Stocks', 'slug' => 'item-stocks', 'route' => 'admin.inventory.item-stocks.index', 'icon' => 'fas fa-boxes', 'parent_slug' => 'inventory', 'sort_order' => 10],
            ['name' => 'Stock Mutations', 'slug' => 'stock-mutations', 'route' => 'admin.inventory.stock-mutations.index', 'icon' => 'fas fa-exchange-alt', 'parent_slug' => 'inventory', 'sort_order' => 11],
            ['name' => 'Transfer Gudang', 'slug' => 'stock-transfers', 'route' => 'admin.inventory.stock-transfers.index', 'icon' => 'fas fa-exchange-alt', 'parent_slug' => 'inventory', 'sort_order' => 11.5],
            ['name' => 'Stock Opname', 'slug' => 'stock-opname', 'route' => 'admin.inventory.stock-opname.index', 'icon' => 'fas fa-clipboard-check', 'parent_slug' => 'inventory', 'sort_order' => 12],
            ['name' => 'Penyesuaian Stok', 'slug' => 'stock-adjustments', 'route' => 'admin.inventory.stock-adjustments.index', 'icon' => 'fas fa-sliders-h', 'parent_slug' => 'inventory', 'sort_order' => 12.5],
            ['name' => 'Barang Rusak', 'slug' => 'damaged-goods', 'route' => 'admin.inventory.damaged-goods.index', 'icon' => 'fas fa-exclamation-triangle', 'parent_slug' => 'inventory', 'sort_order' => 13],
            ['name' => 'Alokasi Barang Rusak', 'slug' => 'damaged-allocations', 'route' => 'admin.inventory.damaged-allocations.index', 'icon' => 'fas fa-recycle', 'parent_slug' => 'inventory', 'sort_order' => 13.25],
            ['name' => 'Resep Rework', 'slug' => 'rework-recipes', 'route' => 'admin.inventory.rework-recipes.index', 'icon' => 'fas fa-flask', 'parent_slug' => 'inventory', 'sort_order' => 13.3],
            ['name' => 'Import Resi', 'slug' => 'resi-import', 'route' => 'admin.inventory.resi-import.index', 'icon' => 'fas fa-file-import', 'parent_slug' => 'inventory', 'sort_order' => 13.5],
            ['name' => 'Retur Customer', 'slug' => 'customer-returns', 'route' => 'admin.inventory.customer-returns.index', 'icon' => 'fas fa-box-open', 'parent_slug' => 'inventory', 'sort_order' => 14],
            ['name' => 'Picking List', 'slug' => 'picking-list', 'route' => 'admin.inventory.picking-list.index', 'icon' => 'fas fa-tasks', 'parent_slug' => 'inventory', 'sort_order' => 14.5],
            ['name' => 'Stores', 'slug' => 'stores', 'route' => 'admin.masterdata.stores.index', 'icon' => 'fas fa-store', 'parent_slug' => 'master-data', 'sort_order' => 21.7],
            ['name' => 'Menus', 'slug' => 'menus', 'route' => 'admin.masterdata.menus.index', 'icon' => 'fas fa-bars', 'parent_slug' => 'master-data', 'sort_order' => 22],
            ['name' => 'Permissions', 'slug' => 'permissions', 'route' => 'admin.masterdata.permissions.index', 'icon' => 'fas fa-lock', 'parent_slug' => 'master-data', 'sort_order' => 23],
            ['name' => 'Penerimaan Barang', 'slug' => 'inbound-receiving', 'route' => 'admin.inbound.receipts.index', 'icon' => 'fas fa-dolly', 'parent_slug' => 'inbound', 'sort_order' => 10],
            ['name' => 'Retur', 'slug' => 'inbound-return', 'route' => 'admin.inbound.returns.index', 'icon' => 'fas fa-undo', 'parent_slug' => 'inbound', 'sort_order' => 11, 'is_active' => false],
            ['name' => 'Manual', 'slug' => 'inbound-manual', 'route' => 'admin.inbound.manuals.index', 'icon' => 'fas fa-edit', 'parent_slug' => 'inbound', 'sort_order' => 12],
            // ['name' => 'Picker', 'slug' => 'outbound-picker', 'route' => 'admin.outbound.pickers.index', 'icon' => 'fas fa-people-carry', 'parent_slug' => 'outbound', 'sort_order' => 10],
            ['name' => 'Manual', 'slug' => 'outbound-manual', 'route' => 'admin.outbound.manuals.index', 'icon' => 'fas fa-edit', 'parent_slug' => 'outbound', 'sort_order' => 11],
            ['name' => 'Retur', 'slug' => 'outbound-return', 'route' => 'admin.outbound.returns.index', 'icon' => 'fas fa-undo', 'parent_slug' => 'outbound', 'sort_order' => 12],
            ['name' => 'QC Scan Desktop', 'slug' => 'outbound-qc-scan', 'route' => 'admin.outbound.qc-scan.index', 'icon' => 'fas fa-barcode', 'parent_slug' => 'outbound', 'sort_order' => 13],
            ['name' => 'History QC', 'slug' => 'outbound-qc-history', 'route' => 'admin.outbound.qc-history.index', 'icon' => 'fas fa-search', 'parent_slug' => 'outbound', 'sort_order' => 13.25],
            ['name' => 'Riwayat Scan Out', 'slug' => 'outbound-scan-out-history', 'route' => 'admin.outbound.scan-out-history.index', 'icon' => 'fas fa-truck-loading', 'parent_slug' => 'outbound', 'sort_order' => 13.5],
            ['name' => 'SKU Exception QC', 'slug' => 'outbound-qc-scan-exceptions', 'route' => 'admin.outbound.qc-scan-exceptions.index', 'icon' => 'fas fa-ban', 'parent_slug' => 'outbound', 'sort_order' => 13.8],
            ['name' => 'Laporan Scan Out', 'slug' => 'outbound-scan-out-report', 'route' => 'admin.reports.scan-out-reports.index', 'icon' => 'fas fa-truck-loading', 'parent_slug' => 'reports', 'sort_order' => 1.2],
            ['name' => 'Laporan Stok Pengaman', 'slug' => 'report-low-stock', 'route' => 'admin.reports.low-stock.index', 'icon' => 'fas fa-exclamation-triangle', 'parent_slug' => 'reports', 'sort_order' => 1.25],
            ['name' => 'Laporan Retur', 'slug' => 'report-returns', 'route' => 'admin.reports.returns.index', 'icon' => 'fas fa-undo', 'parent_slug' => 'reports', 'sort_order' => 1.26],
            ['name' => 'Replenishment Display', 'slug' => 'report-replenishment', 'route' => 'admin.reports.replenishment.index', 'icon' => 'fas fa-sync-alt', 'parent_slug' => 'reports', 'sort_order' => 1.27],
            ['name' => 'Aktivitas User', 'slug' => 'activity-logs', 'route' => 'admin.reports.activity-logs.index', 'icon' => 'fas fa-clipboard-check', 'parent_slug' => 'reports', 'sort_order' => 2],
            ['name' => 'Laporan Stock Opname', 'slug' => 'report-stock-opname', 'route' => 'admin.reports.stock-opname.index', 'icon' => 'fas fa-clipboard-list', 'parent_slug' => 'reports', 'sort_order' => 3],
        ];

        foreach ($menuRows as $menu) {
            if ($menu['parent_slug'] === null) {
                DB::table('menus')->updateOrInsert(
                    ['slug' => $menu['slug']],
                    [
                        'name' => $menu['name'],
                        'route' => $menu['route'],
                        'icon' => $menu['icon'],
                        'parent_id' => null,
                        'sort_order' => $menu['sort_order'],
                        'is_active' => $menu['is_active'] ?? true,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }

        foreach ($menuRows as $menu) {
            if ($menu['parent_slug'] !== null) {
                $parent = DB::table('menus')->where('slug', $menu['parent_slug'])->first();
                DB::table('menus')->updateOrInsert(
                    ['slug' => $menu['slug']],
                    [
                        'name' => $menu['name'],
                        'route' => $menu['route'],
                        'icon' => $menu['icon'],
                        'parent_id' => $parent?->id,
                        'sort_order' => $menu['sort_order'],
                        'is_active' => $menu['is_active'] ?? true,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }

        $adminRole = DB::table('roles')->where('slug', 'admin')->first();
        if ($adminRole) {
            $menus = DB::table('menus')->get();
            foreach ($menus as $m) {
                DB::table('permission_menu')->updateOrInsert(
                    ['role_id' => $adminRole->id, 'menu_id' => $m->id],
                    [
                        'can_view' => true,
                        'can_create' => true,
                        'can_update' => true,
                        'can_delete' => true,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }
    }
}
