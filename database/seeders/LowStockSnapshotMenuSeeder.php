<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LowStockSnapshotMenuSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $parent = DB::table('menus')->where('slug', 'reports')->first();

        if (!$parent) {
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
        } else {
            $parentId = $parent->id;
        }

        $menu = DB::table('menus')->where('slug', 'report-low-stock-snapshots')->first();
        if (!$menu) {
            $menuId = DB::table('menus')->insertGetId([
                'name' => 'Snapshot Low Stock',
                'slug' => 'report-low-stock-snapshots',
                'route' => 'admin.reports.low-stock-snapshots.index',
                'icon' => 'fas fa-camera',
                'parent_id' => $parentId,
                'sort_order' => 1.255,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $menuId = $menu->id;
        }

        $adminRole = DB::table('roles')->where('slug', 'admin')->first();
        if ($adminRole && $menuId) {
            DB::table('permission_menu')->updateOrInsert(
                ['role_id' => $adminRole->id, 'menu_id' => $menuId],
                [
                    'can_view' => true,
                    'can_create' => true,
                    'can_update' => true,
                    'can_delete' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }
}
