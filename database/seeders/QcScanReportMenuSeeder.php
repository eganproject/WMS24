<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class QcScanReportMenuSeeder extends Seeder
{
    /**
     * Seeder production-safe dan aditif.
     *
     * Seeder ini sengaja tidak dipanggil DatabaseSeeder. Ia hanya menambahkan
     * menu/permission yang belum ada dan tidak menimpa konfigurasi menu yang
     * sudah disesuaikan melalui aplikasi di production.
     */
    public function run(): void
    {
        if (!Schema::hasTable('menus')) {
            return;
        }

        $reportsMenuId = DB::table('menus')->where('slug', 'reports')->value('id');
        if (!$reportsMenuId) {
            $this->command?->warn('Menu induk Laporan tidak ditemukan; tidak ada data yang diubah.');

            return;
        }

        $now = now();
        $menuId = DB::table('menus')->where('slug', 'report-qc-scan')->value('id');

        if (!$menuId) {
            $menuId = DB::table('menus')->insertGetId([
                'name' => 'Laporan QC Scan',
                'slug' => 'report-qc-scan',
                'route' => 'admin.reports.qc-scan.index',
                'icon' => 'fas fa-chart-bar',
                'parent_id' => $reportsMenuId,
                'sort_order' => 1.21,
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
            $exists = DB::table('permission_menu')
                ->where('role_id', $roleId)
                ->where('menu_id', $menuId)
                ->exists();

            if (!$exists) {
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
