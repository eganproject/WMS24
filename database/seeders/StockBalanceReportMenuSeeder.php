<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StockBalanceReportMenuSeeder extends Seeder
{
    /**
     * Seeder aditif untuk production. Hanya membuat menu dan permission yang
     * belum ada; konfigurasi menu yang sudah diatur melalui web tidak diubah.
     * Jalankan secara eksplisit, terpisah dari MenuSeeder utama.
     */
    public function run(): void
    {
        if (!Schema::hasTable('menus')) {
            return;
        }

        $reportsMenuId = DB::table('menus')->where('slug', 'reports')->value('id');
        if (!$reportsMenuId) {
            $this->command?->warn('Menu induk Laporan tidak ditemukan.');

            return;
        }

        $now = now();
        $menuId = DB::table('menus')->where('slug', 'report-stock-balance')->value('id');

        if (!$menuId) {
            $menuId = DB::table('menus')->insertGetId([
                'name' => 'Laporan Saldo Stok',
                'slug' => 'report-stock-balance',
                'route' => 'admin.reports.stock-balance.index',
                'icon' => 'fas fa-balance-scale',
                'parent_id' => $reportsMenuId,
                'sort_order' => 1.24,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (!Schema::hasTable('roles') || !Schema::hasTable('permission_menu')) {
            return;
        }

        $adminRoleIds = DB::table('roles')->where('slug', 'admin')->pluck('id');
        foreach ($adminRoleIds as $roleId) {
            $permissionExists = DB::table('permission_menu')
                ->where('role_id', $roleId)
                ->where('menu_id', $menuId)
                ->exists();

            if (!$permissionExists) {
                DB::table('permission_menu')->insert([
                    'role_id' => $roleId,
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
}
