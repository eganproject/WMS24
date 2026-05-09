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

        DB::table('menus')
            ->where('slug', 'attendance-absences')
            ->update([
                'name' => 'Monitor Harian',
                'icon' => 'fas fa-user-check',
                'route' => 'admin.attendance.absences.index',
                'sort_order' => 10.5,
                'is_active' => true,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('menus')) {
            return;
        }

        DB::table('menus')
            ->where('slug', 'attendance-absences')
            ->update([
                'name' => 'Orang Absen',
                'icon' => 'fas fa-user-times',
                'updated_at' => now(),
            ]);
    }
};
