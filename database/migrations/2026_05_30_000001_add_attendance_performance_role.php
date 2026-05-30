<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('roles')) {
            return;
        }

        DB::table('roles')->updateOrInsert(
            ['slug' => 'attendance-performance'],
            [
                'name' => 'Performa Absensi',
                'description' => 'Akses terbatas untuk melihat performa absensi pribadi.',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        if (!Schema::hasTable('roles')) {
            return;
        }

        $roleId = DB::table('roles')->where('slug', 'attendance-performance')->value('id');

        if ($roleId && Schema::hasTable('role_user')) {
            DB::table('role_user')->where('role_id', $roleId)->delete();
        }

        if ($roleId && Schema::hasTable('permission_menu')) {
            DB::table('permission_menu')->where('role_id', $roleId)->delete();
        }

        DB::table('roles')->where('slug', 'attendance-performance')->delete();
    }
};
