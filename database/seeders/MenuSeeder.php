<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Menu;
use App\Models\Permission;
use App\Models\Jabatan;

class MenuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Dashboard
        $dashboard = Menu::updateOrCreate(
            ['name' => 'Dashboard'],
            [
                'url' => '/admin/dashboard',
                'icon' => 'fas fa-fire',
                'order' => 1,
            ]
        );

        // Masterdata
        $masterdata = Menu::updateOrCreate(
            ['name' => 'Masterdata'],
            [
                'url' => null,
                'icon' => 'fas fa-database',
                'order' => 2,
            ]
        );

        // Children of Masterdata
        $userMenu = Menu::updateOrCreate(
            ['name' => 'Users'],
            [
                'url' => '/admin/masterdata/users',
                'icon' => 'fas fa-users',
                'parent_id' => $masterdata->id,
                'order' => 1,
            ]
        );

        $jabatanMenu = Menu::updateOrCreate(
            ['name' => 'Jabatan'],
            [
                'url' => '/admin/masterdata/jabatans',
                'icon' => 'fas fa-briefcase',
                'parent_id' => $masterdata->id,
                'order' => 2,
            ]
        );

        $permissionMenu = Menu::updateOrCreate(
            ['name' => 'Permissions'],
            [
                'url' => '/admin/masterdata/permissions',
                'icon' => 'fas fa-key',
                'parent_id' => $masterdata->id,
                'order' => 3,
            ]
        );

        $menusMenu = Menu::updateOrCreate(
            ['name' => 'Menus'],
            [
                'url' => '/admin/masterdata/menus',
                'icon' => 'fas fa-bars',
                'parent_id' => $masterdata->id,
                'order' => 4,
            ]
        );

        $warehouseMenu = Menu::updateOrCreate(
            ['name' => 'Warehouses'],
            [
                'url' => '/admin/masterdata/warehouses',
                'icon' => 'fas fa-warehouse',
                'parent_id' => $masterdata->id,
                'order' => 5,
            ]
        );

        $uomMenu = Menu::updateOrCreate(
            ['name' => 'UOMs'],
            [
                'url' => '/admin/masterdata/uoms',
                'icon' => 'fas fa-balance-scale',
                'parent_id' => $masterdata->id,
                'order' => 6,
            ]
        );

        $itemMenu = Menu::updateOrCreate(
            ['name' => 'Items'],
            [
                'url' => '/admin/masterdata/items',
                'icon' => 'fas fa-box',
                'parent_id' => $masterdata->id,
                'order' => 8,
            ]
        );

        $assemblyRecipeMenu = Menu::updateOrCreate(
            ['name' => 'Assembly Recipes'],
            [
                'url' => '/admin/masterdata/assemblyrecipes',
                'icon' => 'fas fa-project-diagram',
                'parent_id' => $masterdata->id,
                'order' => 9,
            ]
        );

        $itemCategoryMenu = Menu::updateOrCreate(
            ['name' => 'Kategori Item'],
            [
                'url' => '/admin/masterdata/itemcategories',
                'icon' => 'fas fa-tags',
                'parent_id' => $masterdata->id,
                'order' => 7,
            ]
        );

        // Manajemen Stok
        $manajemenStok = Menu::updateOrCreate(
            ['name' => 'Manajemen Stok'],
            [
                'url' => null,
                'icon' => 'fas fa-cubes',
                'order' => 3,
            ]
        );

        // Children of Manajemen Stok
        Menu::updateOrCreate(
            ['name' => 'Kartu Stok'],
            [
                'url' => '/admin/manajemen-stok/kartu-stok',
                'icon' => 'fas fa-id-card',
                'parent_id' => $manajemenStok->id,
                'order' => 1,
            ]
        );

        Menu::updateOrCreate(
            ['name' => 'Warehouse Stok'],
            [
                'url' => '/admin/manajemen-stok/warehouse-stok',
                'icon' => 'fas fa-warehouse',
                'parent_id' => $manajemenStok->id,
                'order' => 2,
            ]
        );

        Menu::updateOrCreate(
            ['name' => 'Master Stok'],
            [
                'url' => '/admin/manajemen-stok/master-stok',
                'icon' => 'fas fa-archive',
                'parent_id' => $manajemenStok->id,
                'order' => 3,
            ]
        );

        Menu::updateOrCreate(
            ['name' => 'Stok Opname'],
            [
                'url' => '/admin/manajemen-stok/stok-opname',
                'icon' => 'fas fa-clipboard-check',
                'parent_id' => $manajemenStok->id,
                'order' => 4,
            ]
        );

        Menu::updateOrCreate(
            ['name' => 'Adjustment'],
            [
                'url' => '/admin/manajemen-stok/adjustment',
                'icon' => 'fas fa-sliders-h',
                'parent_id' => $manajemenStok->id,
                'order' => 5,
            ]
        );

        // Stok Masuk
        $stokMasuk = Menu::updateOrCreate(
            ['name' => 'Stok Masuk'],
            [
                'url' => null,
                'icon' => 'fas fa-dolly',
                'order' => 4,
            ]
        );

        // Children of Stok Masuk
        Menu::updateOrCreate(
            ['name' => 'Pengadaan'],
            [
                'url' => '/admin/stok-masuk/pengadaan',
                'icon' => 'fas fa-list-alt',
                'parent_id' => $stokMasuk->id,
                'order' => 1,
            ]
        );

        Menu::updateOrCreate(
            ['name' => 'Penerimaan Barang'],
            [
                'url' => '/admin/stok-masuk/penerimaan-barang',
                'icon' => 'fas fa-exchange-alt',
                'parent_id' => $stokMasuk->id,
                'order' => 2,
            ]
        );

        $requestTransferMenu = Menu::whereIn('name', ['Request Transfer', 'Permintaan Terkirim'])->first();

        if ($requestTransferMenu) {
            $requestTransferMenu->update([
                'name' => 'Request Transfer',
                'url' => '/admin/stok-masuk/request-transfer',
                'icon' => 'fas fa-plus-circle',
                'parent_id' => $stokMasuk->id,
                'order' => 3,
            ]);
        } else {
            Menu::create([
                'name' => 'Request Transfer',
                'url' => '/admin/stok-masuk/request-transfer',
                'icon' => 'fas fa-plus-circle',
                'parent_id' => $stokMasuk->id,
                'order' => 3,
            ]);
        }

        // Penerimaan Retur
        Menu::updateOrCreate(
            ['name' => 'Penerimaan Retur'],
            [
                'url' => '/admin/stok-masuk/penerimaan-retur',
                'icon' => 'fas fa-undo',
                'parent_id' => $stokMasuk->id,
                'order' => 4,
            ]
        );

      

        // Stok Keluar
        $stokKeluar = Menu::updateOrCreate(
            ['name' => 'Stok Keluar'],
            [
                'url' => null,
                'icon' => 'fas fa-minus-circle',
                'order' => 6,
            ]
        );

        // Children of Stok Keluar
        Menu::updateOrCreate(
            ['name' => 'Pengeluaran Barang'],
            [
                'url' => '/admin/stok-keluar/pengeluaran-barang',
                'icon' => 'fas fa-box-open',
                'parent_id' => $stokKeluar->id,
                'order' => 1,
            ]
        );

        Menu::updateOrCreate(
            ['name' => 'Permintaan Barang'],
            [
                'url' => '/admin/stok-keluar/permintaan-barang',
                'icon' => 'fas fa-list-ul',
                'parent_id' => $stokKeluar->id,
                'order' => 2,
            ]
        );

        // Retur Issue (Retur Keluar)
        Menu::updateOrCreate(
            ['name' => 'Retur Out'],
            [
                'url' => '/admin/stok-keluar/retur-out',
                'icon' => 'fas fa-undo-alt',
                'parent_id' => $stokKeluar->id,
                'order' => 3,
            ]
        );

        // Laporan
        $laporan = Menu::updateOrCreate(
            ['name' => 'Laporan'],
            [
                'url' => null,
                'icon' => 'fas fa-file-alt',
                'order' => 7,
            ]
        );

        // Riwayat Pengiriman (Top-level, before Laporan)
        Menu::updateOrCreate(
            ['name' => 'Riwayat Pengiriman'],
            [
                'url' => '/admin/riwayat-pengiriman',
                'icon' => 'fas fa-truck',
                'order' => 6, // before Laporan (7)
            ]
        );

        // Children of Laporan
        Menu::updateOrCreate(
            ['name' => 'Laporan Stok'],
            [
                'url' => '/admin/laporan/laporan-stok',
                'icon' => 'fas fa-boxes',
                'parent_id' => $laporan->id,
                'order' => 1,
            ]
        );

        Menu::updateOrCreate(
            ['name' => 'Laporan Pergerakan Barang'],
            [
                'url' => '/admin/laporan/laporan-pergerakan-barang',
                'icon' => 'fas fa-history',
                'parent_id' => $laporan->id,
                'order' => 2,
            ]
        );

        Menu::updateOrCreate(
            ['name' => 'Laporan Transfer Gudang'],
            [
                'url' => '/admin/laporan/laporan-transfer-gudang',
                'icon' => 'fas fa-random',
                'parent_id' => $laporan->id,
                'order' => 3,
            ]
        );

        // Assign permissions to a role
        $developerJabatan = Jabatan::where('name', 'Developer')->first();

        if ($developerJabatan) {
            $menus = Menu::all();
            foreach ($menus as $menu) {
                Permission::updateOrCreate([
                    'jabatan_id' => $developerJabatan->id,
                    'menu_id' => $menu->id,
                ], [
                    'can_read' => true,
                    'can_create' => true,
                    'can_edit' => true,
                    'can_delete' => true,
                    'can_approve' => true,
                ]);
            }
        }
    }
}
