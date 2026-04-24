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

        $parentId = DB::table('menus')->where('slug', 'inbound')->value('id');

        DB::table('menus')->updateOrInsert(
            ['slug' => 'inbound-scan-workbench'],
            [
                'name' => 'Scan Inbound',
                'route' => 'admin.inbound.scan.index',
                'icon' => 'fa-solid fa-barcode',
                'parent_id' => $parentId,
                'sort_order' => 10.5,
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        if (!Schema::hasTable('permission_menu') || !Schema::hasTable('roles')) {
            return;
        }

        $adminRoleId = DB::table('roles')->where('slug', 'admin')->value('id');
        $menuId = DB::table('menus')->where('slug', 'inbound-scan-workbench')->value('id');

        if ($adminRoleId && $menuId) {
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
    }

    public function down(): void
    {
        if (!Schema::hasTable('menus')) {
            return;
        }

        $menuId = DB::table('menus')->where('slug', 'inbound-scan-workbench')->value('id');
        if ($menuId && Schema::hasTable('permission_menu')) {
            DB::table('permission_menu')->where('menu_id', $menuId)->delete();
        }

        DB::table('menus')->where('slug', 'inbound-scan-workbench')->delete();
    }
};
