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

        DB::table('menus')->updateOrInsert(
            ['slug' => 'attendance'],
            [
                'name' => 'Attendance',
                'route' => 'admin.attendance.index',
                'icon' => 'fas fa-fingerprint',
                'parent_id' => null,
                'sort_order' => 16,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $menuId = DB::table('menus')->where('slug', 'attendance')->value('id');
        $adminRole = DB::table('roles')->where('slug', 'admin')->first();
        if ($menuId && $adminRole && Schema::hasTable('permission_menu')) {
            DB::table('permission_menu')->updateOrInsert(
                ['role_id' => $adminRole->id, 'menu_id' => $menuId],
                [
                    'can_view' => true,
                    'can_create' => true,
                    'can_update' => true,
                    'can_delete' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('menus')) {
            return;
        }

        $menuId = DB::table('menus')->where('slug', 'attendance')->value('id');
        if ($menuId && Schema::hasTable('permission_menu')) {
            DB::table('permission_menu')->where('menu_id', $menuId)->delete();
        }
        DB::table('menus')->where('slug', 'attendance')->delete();
    }
};
