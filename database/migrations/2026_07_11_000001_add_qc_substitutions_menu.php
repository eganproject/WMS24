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

        $parentId = DB::table('menus')->where('slug', 'outbound')->value('id');
        if (!$parentId) {
            return;
        }

        DB::table('menus')->updateOrInsert(
            ['slug' => 'outbound-qc-substitutions'],
            [
                'name' => 'Riwayat Substitusi SKU',
                'route' => 'admin.outbound.qc-substitutions.index',
                'icon' => 'fas fa-random',
                'parent_id' => $parentId,
                'sort_order' => 13.275,
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        if (!Schema::hasTable('roles') || !Schema::hasTable('permission_menu')) {
            return;
        }

        $menuId = DB::table('menus')->where('slug', 'outbound-qc-substitutions')->value('id');
        $adminRoleId = DB::table('roles')->where('slug', 'admin')->value('id');
        if ($menuId && $adminRoleId) {
            DB::table('permission_menu')->updateOrInsert(
                ['role_id' => $adminRoleId, 'menu_id' => $menuId],
                [
                    'can_view' => true,
                    'can_create' => false,
                    'can_update' => false,
                    'can_delete' => false,
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

        $menuId = DB::table('menus')->where('slug', 'outbound-qc-substitutions')->value('id');
        if ($menuId && Schema::hasTable('permission_menu')) {
            DB::table('permission_menu')->where('menu_id', $menuId)->delete();
        }

        DB::table('menus')->where('slug', 'outbound-qc-substitutions')->delete();
    }
};
