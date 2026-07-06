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

        $attendanceMenuId = DB::table('menus')->where('slug', 'attendance')->value('id');
        if (!$attendanceMenuId) {
            return;
        }

        DB::table('menus')->updateOrInsert(
            ['slug' => 'attendance-overtime'],
            [
                'name' => 'Monitor Lembur',
                'route' => 'admin.attendance.overtime.index',
                'icon' => 'fas fa-business-time',
                'parent_id' => $attendanceMenuId,
                'sort_order' => 10.3,
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        if (!Schema::hasTable('roles') || !Schema::hasTable('permission_menu')) {
            return;
        }

        $menuId = DB::table('menus')->where('slug', 'attendance-overtime')->value('id');
        $roleIds = DB::table('roles')->whereIn('slug', ['admin', 'hr'])->pluck('id');

        foreach ($roleIds as $roleId) {
            DB::table('permission_menu')->updateOrInsert(
                ['role_id' => $roleId, 'menu_id' => $menuId],
                [
                    'can_view' => true,
                    'can_create' => false,
                    'can_update' => true,
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

        $menuId = DB::table('menus')->where('slug', 'attendance-overtime')->value('id');
        if ($menuId && Schema::hasTable('permission_menu')) {
            DB::table('permission_menu')->where('menu_id', $menuId)->delete();
        }

        DB::table('menus')->where('slug', 'attendance-overtime')->delete();
    }
};
