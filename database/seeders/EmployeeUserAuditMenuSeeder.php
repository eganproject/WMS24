<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EmployeeUserAuditMenuSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('menus')) {
            return;
        }

        $parentId = DB::table('menus')->where('slug', 'master-data')->value('id');

        DB::table('menus')->updateOrInsert(
            ['slug' => 'employee-user-audit'],
            [
                'name' => 'Audit User Karyawan',
                'route' => 'admin.masterdata.employee-user-audit.index',
                'icon' => 'fas fa-user-check',
                'parent_id' => $parentId,
                'sort_order' => 24,
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        if (!Schema::hasTable('roles') || !Schema::hasTable('permission_menu')) {
            return;
        }

        $menuId = DB::table('menus')->where('slug', 'employee-user-audit')->value('id');
        if (!$menuId) {
            return;
        }

        foreach (['admin', 'hr'] as $roleSlug) {
            $roleId = DB::table('roles')->where('slug', $roleSlug)->value('id');
            if (!$roleId) {
                continue;
            }

            DB::table('permission_menu')->updateOrInsert(
                ['role_id' => $roleId, 'menu_id' => $menuId],
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
}
