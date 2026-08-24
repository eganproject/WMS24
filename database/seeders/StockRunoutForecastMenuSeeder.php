<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StockRunoutForecastMenuSeeder extends Seeder
{
    /**
     * Seeder ini sengaja berdiri sendiri dan tidak dipanggil DatabaseSeeder.
     * Jalankan secara eksplisit agar tidak mengubah menu yang dikelola melalui web.
     */
    public function run(): void
    {
        if (!Schema::hasTable('menus')) {
            return;
        }

        $reportsMenuId = DB::table('menus')->where('slug', 'reports')->value('id');
        if (!$reportsMenuId) {
            return;
        }

        $now = now();
        $menu = DB::table('menus')->where('slug', 'report-stock-runout-forecast')->first(['id']);

        // Hanya buat bila menu khusus ini belum ada; jangan overwrite hasil pengaturan dari web.
        if (!$menu) {
            $menuId = DB::table('menus')->insertGetId([
                'name' => 'Forecast Ketahanan Stok',
                'slug' => 'report-stock-runout-forecast',
                'route' => 'admin.reports.stock-runout-forecast.index',
                'icon' => 'fas fa-chart-line',
                'parent_id' => $reportsMenuId,
                'sort_order' => 1.255,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $menuId = (int) $menu->id;
        }

        if (!Schema::hasTable('roles') || !Schema::hasTable('permission_menu')) {
            return;
        }

        $adminRoleId = DB::table('roles')->where('slug', 'admin')->value('id');
        if (!$adminRoleId) {
            return;
        }

        if (!DB::table('permission_menu')->where(['role_id' => $adminRoleId, 'menu_id' => $menuId])->exists()) {
            DB::table('permission_menu')->insert([
                'role_id' => $adminRoleId,
                'menu_id' => $menuId,
                'can_view' => true,
                'can_create' => false,
                'can_update' => false,
                'can_delete' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
