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

        DB::table('menus')->updateOrInsert(
            ['slug' => 'outbound-delivery-notes'],
            [
                'name' => 'History Surat Jalan',
                'route' => 'admin.outbound.delivery-notes.index',
                'icon' => 'fas fa-file-invoice',
                'parent_id' => $parentId,
                'sort_order' => 12.25,
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        if (!Schema::hasTable('roles') || !Schema::hasTable('permission_menu')) {
            return;
        }

        $menuId = DB::table('menus')->where('slug', 'outbound-delivery-notes')->value('id');
        if (!$menuId) {
            return;
        }

        $adminRoleId = DB::table('roles')->where('slug', 'admin')->value('id');
        if ($adminRoleId) {
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

        $menuId = DB::table('menus')->where('slug', 'outbound-delivery-notes')->value('id');
        if ($menuId && Schema::hasTable('permission_menu')) {
            DB::table('permission_menu')->where('menu_id', $menuId)->delete();
        }

        DB::table('menus')->where('slug', 'outbound-delivery-notes')->delete();
    }
};
