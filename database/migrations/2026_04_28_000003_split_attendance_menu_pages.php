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
                'route' => null,
                'icon' => 'fas fa-fingerprint',
                'parent_id' => null,
                'sort_order' => 16,
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $attendanceMenuId = DB::table('menus')->where('slug', 'attendance')->value('id');
        if (!$attendanceMenuId) {
            return;
        }

        $children = [
            ['name' => 'Karyawan Absensi', 'slug' => 'attendance-employees', 'route' => 'admin.attendance.employees.index', 'icon' => 'fas fa-users', 'sort_order' => 1],
            ['name' => 'Device Absensi', 'slug' => 'attendance-devices', 'route' => 'admin.attendance.devices.index', 'icon' => 'fas fa-fingerprint', 'sort_order' => 2],
            ['name' => 'Mapping Fingerprint', 'slug' => 'attendance-fingerprints', 'route' => 'admin.attendance.fingerprints.index', 'icon' => 'fas fa-id-badge', 'sort_order' => 3],
            ['name' => 'Shift Kerja', 'slug' => 'attendance-shifts', 'route' => 'admin.attendance.shifts.index', 'icon' => 'fas fa-clock', 'sort_order' => 4],
            ['name' => 'Jadwal Kerja', 'slug' => 'attendance-schedules', 'route' => 'admin.attendance.schedules.index', 'icon' => 'fas fa-calendar-alt', 'sort_order' => 5],
            ['name' => 'Hari Libur', 'slug' => 'attendance-holidays', 'route' => 'admin.attendance.holidays.index', 'icon' => 'fas fa-calendar-day', 'sort_order' => 6],
            ['name' => 'Template Jadwal', 'slug' => 'attendance-templates', 'route' => 'admin.attendance.templates.index', 'icon' => 'fas fa-calendar-week', 'sort_order' => 7],
            ['name' => 'Cuti/Izin', 'slug' => 'attendance-leaves', 'route' => 'admin.attendance.leaves.index', 'icon' => 'fas fa-plane-departure', 'sort_order' => 8],
            ['name' => 'Raw Log Fingerprint', 'slug' => 'attendance-raw-logs', 'route' => 'admin.attendance.raw-logs.index', 'icon' => 'fas fa-list', 'sort_order' => 9],
            ['name' => 'Rekap Absensi', 'slug' => 'attendance-recap', 'route' => 'admin.attendance.attendances.index', 'icon' => 'fas fa-clipboard-check', 'sort_order' => 10],
            ['name' => 'Laporan Absensi', 'slug' => 'report-attendance', 'route' => 'admin.reports.attendance.index', 'icon' => 'fas fa-user-clock', 'sort_order' => 11],
        ];

        foreach ($children as $child) {
            DB::table('menus')->updateOrInsert(
                ['slug' => $child['slug']],
                [
                    'name' => $child['name'],
                    'route' => $child['route'],
                    'icon' => $child['icon'],
                    'parent_id' => $attendanceMenuId,
                    'sort_order' => $child['sort_order'],
                    'is_active' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        if (!Schema::hasTable('roles') || !Schema::hasTable('permission_menu')) {
            return;
        }

        $menuIds = DB::table('menus')
            ->where('id', $attendanceMenuId)
            ->orWhereIn('slug', collect($children)->pluck('slug'))
            ->pluck('id');
        $roleIds = DB::table('roles')->whereIn('slug', ['admin'])->pluck('id');

        foreach ($roleIds as $roleId) {
            foreach ($menuIds as $menuId) {
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
    }

    public function down(): void
    {
        if (!Schema::hasTable('menus')) {
            return;
        }

        $slugs = [
            'attendance-employees',
            'attendance-devices',
            'attendance-fingerprints',
            'attendance-shifts',
            'attendance-schedules',
            'attendance-holidays',
            'attendance-templates',
            'attendance-leaves',
            'attendance-raw-logs',
            'attendance-recap',
        ];

        $menuIds = DB::table('menus')->whereIn('slug', $slugs)->pluck('id');
        if ($menuIds->isNotEmpty() && Schema::hasTable('permission_menu')) {
            DB::table('permission_menu')->whereIn('menu_id', $menuIds)->delete();
        }

        DB::table('menus')->whereIn('slug', $slugs)->delete();
        DB::table('menus')->where('slug', 'attendance')->update([
            'route' => 'admin.attendance.index',
            'parent_id' => null,
            'updated_at' => now(),
        ]);

        $reportsMenuId = DB::table('menus')->where('slug', 'reports')->value('id');
        if ($reportsMenuId) {
            DB::table('menus')->where('slug', 'report-attendance')->update([
                'parent_id' => $reportsMenuId,
                'sort_order' => 1.265,
                'updated_at' => now(),
            ]);
        }
    }
};
