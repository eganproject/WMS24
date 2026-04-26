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

        $inventoryMenuId = DB::table('menus')->where('slug', 'inventory')->value('id');
        if ($inventoryMenuId) {
            DB::table('menus')->updateOrInsert(
                ['slug' => 'customer-returns'],
                [
                    'name' => 'Retur Customer',
                    'route' => 'admin.inventory.customer-returns.index',
                    'icon' => 'fas fa-box-open',
                    'parent_id' => $inventoryMenuId,
                    'sort_order' => 14,
                    'is_active' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        DB::table('menus')
            ->where('slug', 'inbound-return')
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);

        if (!Schema::hasTable('roles') || !Schema::hasTable('permission_menu')) {
            return;
        }

        $menuId = DB::table('menus')->where('slug', 'customer-returns')->value('id');
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

        $menuId = DB::table('menus')->where('slug', 'customer-returns')->value('id');
        if ($menuId && Schema::hasTable('permission_menu')) {
            DB::table('permission_menu')->where('menu_id', $menuId)->delete();
        }

        DB::table('menus')->where('slug', 'customer-returns')->delete();

        DB::table('menus')
            ->where('slug', 'inbound-return')
            ->update([
                'is_active' => true,
                'updated_at' => now(),
            ]);
    }
};
