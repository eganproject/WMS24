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

        $reportsMenuId = DB::table('menus')->where('slug', 'reports')->value('id');
        if (!$reportsMenuId) {
            return;
        }

        DB::table('menus')->updateOrInsert(
            ['slug' => 'report-attendance'],
            [
                'name' => 'Laporan Absensi',
                'route' => 'admin.reports.attendance.index',
                'icon' => 'fas fa-user-clock',
                'parent_id' => $reportsMenuId,
                'sort_order' => 1.265,
                'is_active' => true,
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

        $attendanceMenuId = DB::table('menus')->where('slug', 'attendance')->value('id');
        if (!$attendanceMenuId) {
            return;
        }

        DB::table('menus')->where('slug', 'report-attendance')->update([
            'parent_id' => $attendanceMenuId,
            'sort_order' => 11,
            'updated_at' => now(),
        ]);
    }
};
