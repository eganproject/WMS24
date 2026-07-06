<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DailyStockForecastMenuSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('menus')) {
            return;
        }

        $now = now();
        $parentId = DB::table('menus')->where('slug', 'reports')->value('id');

        if (!$parentId) {
            $parentId = DB::table('menus')->insertGetId([
                'name' => 'Laporan',
                'slug' => 'reports',
                'route' => null,
                'icon' => 'fas fa-chart-line',
                'parent_id' => null,
                'sort_order' => 15,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('menus')->updateOrInsert(
            ['slug' => 'report-daily-stock-forecast'],
            [
                'name' => 'Forecast Stok Harian',
                'route' => 'admin.reports.daily-stock-forecast.index',
                'icon' => 'fas fa-chart-area',
                'parent_id' => $parentId,
                'sort_order' => 1.245,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        if (!Schema::hasTable('roles') || !Schema::hasTable('permission_menu')) {
            return;
        }

        $menuId = DB::table('menus')->where('slug', 'report-daily-stock-forecast')->value('id');
        if (!$menuId) {
            return;
        }

        foreach (['admin', 'hr'] as $roleSlug) {
            $roleId = DB::table('roles')->where('slug', $roleSlug)->value('id');
            if (!$roleId) {
                continue;
            }

            DB::table('permission_menu')->updateOrInsert(
                ['role_id' => $roleId, 'menu_id' => $menuId],
                [
                    'can_view' => true,
                    'can_create' => false,
                    'can_update' => false,
                    'can_delete' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }
}
