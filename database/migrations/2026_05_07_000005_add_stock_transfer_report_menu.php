<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('menus')) {
            return;
        }

        $reportsMenuId = DB::table('menus')->where('slug', 'reports')->value('id');
        if ($reportsMenuId) {
            DB::table('menus')->updateOrInsert(
                ['slug' => 'report-stock-transfers'],
                [
                    'name' => 'Laporan Transfer Gudang',
                    'route' => 'admin.reports.stock-transfers.index',
                    'icon' => 'fas fa-exchange-alt',
                    'parent_id' => $reportsMenuId,
                    'sort_order' => 1.28,
                    'is_active' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        if (!Schema::hasTable('roles') || !Schema::hasTable('permission_menu')) {
            return;
        }

        $menuId = DB::table('menus')->where('slug', 'report-stock-transfers')->value('id');
        if (!$menuId) {
            return;
        }

        $adminRoleIds = DB::table('roles')
            ->whereIn('slug', ['admin'])
            ->pluck('id');

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

    public function down(): void
    {
        if (!Schema::hasTable('menus')) {
            return;
        }

        $menuId = DB::table('menus')->where('slug', 'report-stock-transfers')->value('id');
        if ($menuId && Schema::hasTable('permission_menu')) {
            DB::table('permission_menu')->where('menu_id', $menuId)->delete();
        }

        DB::table('menus')->where('slug', 'report-stock-transfers')->delete();
    }
};
