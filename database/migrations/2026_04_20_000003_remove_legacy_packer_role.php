<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('roles')) {
            return;
        }

        $packerRoleId = DB::table('roles')->where('slug', 'packer')->value('id');
        if (!$packerRoleId) {
            return;
        }

        if (Schema::hasTable('role_user')) {
            DB::table('role_user')->where('role_id', $packerRoleId)->delete();
        }

        if (Schema::hasTable('permission_menu')) {
            DB::table('permission_menu')->where('role_id', $packerRoleId)->delete();
        }

        DB::table('roles')->where('id', $packerRoleId)->delete();
    }

    public function down(): void
    {
        if (!Schema::hasTable('roles')) {
            return;
        }

        $exists = DB::table('roles')->where('slug', 'packer')->exists();
        if ($exists) {
            return;
        }

        DB::table('roles')->insert([
            'name' => 'Packer',
            'slug' => 'packer',
            'description' => 'Packer mobile role',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
