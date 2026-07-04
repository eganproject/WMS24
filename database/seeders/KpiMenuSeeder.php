<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class KpiMenuSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('menus')) {
            return;
        }

        $reportsMenuId = DB::table('menus')->where('slug', 'reports')->value('id');
        if (!$reportsMenuId) {
            return;
        }

        DB::table('menus')->updateOrInsert(
            ['slug' => 'report-kpi'],
            [
                'name' => 'KPI Master & Score',
                'route' => 'admin.reports.kpi.index',
                'icon' => 'fas fa-chart-line',
                'parent_id' => $reportsMenuId,
                'sort_order' => 1.35,
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        if (!Schema::hasTable('roles') || !Schema::hasTable('permission_menu')) {
            return;
        }

        $menuId = DB::table('menus')->where('slug', 'report-kpi')->value('id');
        if (!$menuId) {
            return;
        }

        $adminRoleIds = DB::table('roles')->whereIn('slug', ['admin'])->pluck('id');

        foreach ($adminRoleIds as $roleId) {
            DB::table('permission_menu')->updateOrInsert(
                ['role_id' => $roleId, 'menu_id' => $menuId],
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
