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

        DB::table('menus')->updateOrInsert(
            ['slug' => 'damaged-allocations'],
            [
                'name' => 'Alokasi Barang Rusak',
                'route' => 'admin.inventory.damaged-allocations.index',
                'icon' => 'fa-solid fa-recycle',
                'parent_id' => $inventoryMenuId,
                'sort_order' => 13,
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        if (!Schema::hasTable('roles') || !Schema::hasTable('permission_menu')) {
            return;
        }

        $adminRoleId = DB::table('roles')->where('slug', 'admin')->value('id');
        $menuId = DB::table('menus')->where('slug', 'damaged-allocations')->value('id');
        if (!$adminRoleId || !$menuId) {
            return;
        }

        DB::table('permission_menu')->updateOrInsert(
            ['role_id' => $adminRoleId, 'menu_id' => $menuId],
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

    public function down(): void
    {
        if (!Schema::hasTable('menus')) {
            return;
        }

        $menuId = DB::table('menus')->where('slug', 'damaged-allocations')->value('id');
        if ($menuId && Schema::hasTable('permission_menu')) {
            DB::table('permission_menu')->where('menu_id', $menuId)->delete();
        }

        DB::table('menus')->where('slug', 'damaged-allocations')->delete();
    }
};
