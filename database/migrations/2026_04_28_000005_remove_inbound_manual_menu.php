<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('menus')) {
            return;
        }

        $menuId = DB::table('menus')->where('slug', 'inbound-manual')->value('id');
        if ($menuId && Schema::hasTable('permission_menu')) {
            DB::table('permission_menu')->where('menu_id', $menuId)->delete();
        }

        DB::table('menus')->where('slug', 'inbound-manual')->delete();
    }

    public function down(): void
    {
        if (!Schema::hasTable('menus')) {
            return;
        }

        $inboundMenuId = DB::table('menus')->where('slug', 'inbound')->value('id');
        if (!$inboundMenuId) {
            return;
        }

        DB::table('menus')->updateOrInsert(
            ['slug' => 'inbound-manual'],
            [
                'name' => 'Manual',
                'route' => 'admin.inbound.manuals.index',
                'icon' => 'fas fa-edit',
                'parent_id' => $inboundMenuId,
                'sort_order' => 12,
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        if (!Schema::hasTable('roles') || !Schema::hasTable('permission_menu')) {
            return;
        }

        $menuId = DB::table('menus')->where('slug', 'inbound-manual')->value('id');
        $roleIds = DB::table('roles')->whereIn('slug', ['admin'])->pluck('id');

        foreach ($roleIds as $roleId) {
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
};
