<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StockApiMenuSeeder extends Seeder
{
    /**
     * Adds only the stock API access menu and the admin permission for it.
     * It deliberately does not reset any existing menus or permissions.
     */
    public function run(): void
    {
        $parent = DB::table('menus')->where('slug', 'master-data')->first(['id']);
        if (! $parent) {
            $this->command?->warn('Menu Master Data tidak ditemukan. Jalankan MenuSeeder umum terlebih dahulu.');

            return;
        }

        DB::table('menus')->updateOrInsert(
            ['slug' => 'stock-api-access'],
            [
                'name' => 'Akses API Stok',
                'route' => 'admin.masterdata.stock-api-access.index',
                'icon' => 'fas fa-shield-alt',
                'parent_id' => $parent->id,
                'sort_order' => 21.63,
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $admin = DB::table('roles')->where('slug', 'admin')->first(['id']);
        $menu = DB::table('menus')->where('slug', 'stock-api-access')->first(['id']);
        if (! $admin || ! $menu) {
            return;
        }

        DB::table('permission_menu')->updateOrInsert(
            ['role_id' => $admin->id, 'menu_id' => $menu->id],
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
