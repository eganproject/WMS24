<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InboundLeadTimeReportMenuSeeder extends Seeder
{
    /** Seeder aditif: tidak dipanggil DatabaseSeeder dan tidak menimpa konfigurasi production. */
    public function run(): void
    {
        if (! Schema::hasTable('menus')) {
            return;
        }

        $reportsMenuId = DB::table('menus')->where('slug', 'reports')->value('id');
        if (! $reportsMenuId) {
            $this->command?->warn('Menu induk Laporan tidak ditemukan; tidak ada data yang diubah.');

            return;
        }

        $now = now();
        $menuId = DB::table('menus')->where('slug', 'report-inbound-lead-time')->value('id');

        if (! $menuId) {
            $menuId = DB::table('menus')->insertGetId([
                'name' => 'Lead Time Operasional',
                'slug' => 'report-inbound-lead-time',
                'route' => 'admin.reports.inbound-lead-time.index',
                'icon' => 'fas fa-stopwatch',
                'parent_id' => $reportsMenuId,
                'sort_order' => 1.215,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (! Schema::hasTable('roles') || ! Schema::hasTable('permission_menu')) {
            return;
        }

        foreach (DB::table('roles')->where('slug', 'admin')->pluck('id') as $roleId) {
            $exists = DB::table('permission_menu')
                ->where('role_id', $roleId)
                ->where('menu_id', $menuId)
                ->exists();

            if (! $exists) {
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
