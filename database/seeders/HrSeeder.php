<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class HrSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('roles')->updateOrInsert(
            ['slug' => 'hr'],
            [
                'name' => 'HR',
                'description' => 'Human resources role for attendance management',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $roleId = DB::table('roles')->where('slug', 'hr')->value('id');
        if (!$roleId) {
            return;
        }

        DB::table('users')->updateOrInsert(
            ['email' => 'hr@wms24.test'],
            [
                'name' => 'HR Attendance',
                'password' => Hash::make('123456'),
                'area_id' => null,
                'email_verified_at' => now(),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $userId = DB::table('users')->where('email', 'hr@wms24.test')->value('id');
        if ($userId) {
            DB::table('role_user')->updateOrInsert(
                ['role_id' => $roleId, 'user_id' => $userId],
                []
            );
        }

        $manageMenuSlugs = [
            'attendance-employees',
            'attendance-fingerprints',
            'attendance-shifts',
            'attendance-schedules',
            'attendance-holidays',
            'attendance-templates',
            'attendance-leaves',
            'attendance-recap',
            'attendance-overtime',
            'attendance-absences',
        ];

        $viewOnlyMenuSlugs = [
            'dashboard',
            'reports',
            'report-attendance',
            'attendance',
            'attendance-devices',
            'attendance-raw-logs',
            'attendance-live-display',
            'attendance-machine-logs',
        ];

        foreach ($viewOnlyMenuSlugs as $slug) {
            $this->syncPermission($roleId, $slug, true, false, false, false);
        }

        foreach ($manageMenuSlugs as $slug) {
            $this->syncPermission($roleId, $slug, true, true, true, true);
        }
    }

    private function syncPermission(
        int $roleId,
        string $menuSlug,
        bool $canView,
        bool $canCreate,
        bool $canUpdate,
        bool $canDelete
    ): void {
        $menuId = DB::table('menus')->where('slug', $menuSlug)->value('id');
        if (!$menuId) {
            return;
        }

        DB::table('permission_menu')->updateOrInsert(
            ['role_id' => $roleId, 'menu_id' => $menuId],
            [
                'can_view' => $canView,
                'can_create' => $canCreate,
                'can_update' => $canUpdate,
                'can_delete' => $canDelete,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }
}
