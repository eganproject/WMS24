<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('menus')) {
            return;
        }

        $reportsMenuId = DB::table('menus')->where('slug', 'reports')->value('id');
        if (!$reportsMenuId) {
            return;
        }

        $now = now();
        $menuId = DB::table('menus')->where('slug', 'report-stock-balance')->value('id');

        // Jangan overwrite menu yang mungkin sudah dikustomisasi di production.
        if (!$menuId) {
            $menuId = DB::table('menus')->insertGetId([
                'name' => 'Laporan Saldo Stok',
                'slug' => 'report-stock-balance',
                'route' => 'admin.reports.stock-balance.index',
                'icon' => 'fas fa-balance-scale',
                'parent_id' => $reportsMenuId,
                'sort_order' => 1.24,
                'is_active' => true,
                'updated_at' => $now,
                'created_at' => $now,
            ]);
        }

        if (!Schema::hasTable('roles') || !Schema::hasTable('permission_menu')) {
            return;
        }

        $adminRoleIds = DB::table('roles')->where('slug', 'admin')->pluck('id');

        foreach ($adminRoleIds as $roleId) {
            $permissionExists = DB::table('permission_menu')
                ->where('role_id', $roleId)
                ->where('menu_id', $menuId)
                ->exists();

            if (!$permissionExists) {
                DB::table('permission_menu')->insert([
                    'role_id' => $roleId,
                    'menu_id' => $menuId,
                    'can_view' => true,
                    'can_create' => false,
                    'can_update' => false,
                    'can_delete' => false,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Sengaja no-op: migrasi tidak dapat membedakan menu yang dibuat di sini
        // dengan menu ber-slug sama yang sebelumnya sudah dikelola di production.
        // Jangan hapus menu/permission pengguna saat rollback.
    }
};
