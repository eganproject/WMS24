<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DisplayReceiptsMenuSeeder extends Seeder
{
    /** Tambahkan hanya menu laporan penerimaan Display; tidak menyentuh menu lain. */
    public function run(): void
    {
        if (!Schema::hasTable('menus')) {
            return;
        }

        $parentId = DB::table('menus')->where('slug', 'reports')->value('id');
        if (!$parentId) {
            $this->command?->warn('Menu induk Laporan tidak ditemukan.');
            return;
        }

        DB::table('menus')->updateOrInsert(
            ['slug' => 'report-display-receipts'],
            [
                'name' => 'Penerimaan Gudang Display',
                'route' => 'admin.reports.display-receipts.index',
                'icon' => 'fas fa-dolly-flatbed',
                'parent_id' => $parentId,
                'sort_order' => 1.275,
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        if (!Schema::hasTable('roles') || !Schema::hasTable('permission_menu')) {
            return;
        }

        $adminRoleId = DB::table('roles')->where('slug', 'admin')->value('id');
        $menuId = DB::table('menus')->where('slug', 'report-display-receipts')->value('id');
        if (!$adminRoleId || !$menuId) {
            return;
        }

        DB::table('permission_menu')->updateOrInsert(
            ['role_id' => $adminRoleId, 'menu_id' => $menuId],
            ['can_view' => true, 'can_create' => false, 'can_update' => false, 'can_delete' => false, 'updated_at' => now(), 'created_at' => now()]
        );
    }
}
