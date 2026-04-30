<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('menus') || !Schema::hasTable('permission_menu') || !Schema::hasTable('roles')) {
            return;
        }

        $qcRoleId = DB::table('roles')->where('slug', 'qc')->value('id');
        $menuId = DB::table('menus')->where('slug', 'outbound-qc-scan')->value('id');

        if (!$qcRoleId || !$menuId) {
            return;
        }

        DB::table('permission_menu')->updateOrInsert(
            ['role_id' => $qcRoleId, 'menu_id' => $menuId],
            [
                'can_view'   => true,
                'can_create' => true,
                'can_update' => true,
                'can_delete' => false,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        if (!Schema::hasTable('permission_menu') || !Schema::hasTable('roles') || !Schema::hasTable('menus')) {
            return;
        }

        $qcRoleId = DB::table('roles')->where('slug', 'qc')->value('id');
        $menuId = DB::table('menus')->where('slug', 'outbound-qc-scan')->value('id');

        if ($qcRoleId && $menuId) {
            DB::table('permission_menu')
                ->where('role_id', $qcRoleId)
                ->where('menu_id', $menuId)
                ->delete();
        }
    }
};
