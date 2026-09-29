<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PickerAccuracyReportMenuSeeder extends Seeder
{
    private const SLUG = 'report-picker-accuracy';
    private const ROUTE = 'admin.reports.picker-accuracy.index';

    /**
     * Seeder production-safe dan aditif.
     *
     * Tidak dipanggil DatabaseSeeder. Jalankan manual:
     *   php artisan db:seed --class=PickerAccuracyReportMenuSeeder
     *
     * - Bila menu sudah ada (dicari dari slug ATAU route, termasuk yang dibuat lewat halaman
     *   manajemen menu), seeder tidak mengubah apa pun: nama, urutan, induk, status, dan hak akses
     *   tetap mengikuti konfigurasi dari aplikasi.
     * - Hak akses admin hanya ditambahkan untuk menu yang baru dibuat oleh seeder ini.
     */
    public function run(): void
    {
        if (!Schema::hasTable('menus')) {
            return;
        }

        $existingId = DB::table('menus')
            ->where('slug', self::SLUG)
            ->orWhere('route', self::ROUTE)
            ->value('id');

        if ($existingId) {
            $this->command?->info('Menu Akurasi Picker sudah ada; konfigurasi yang ada tidak diubah.');

            return;
        }

        $reportsMenuId = DB::table('menus')->where('slug', 'reports')->value('id');
        if (!$reportsMenuId) {
            $this->command?->warn('Menu induk Laporan tidak ditemukan; tidak ada data yang diubah.');

            return;
        }

        $now = now();
        $menuId = DB::table('menus')->insertGetId([
            'name' => 'Akurasi Picker',
            'slug' => self::SLUG,
            'route' => self::ROUTE,
            'icon' => 'fas fa-crosshairs',
            'parent_id' => $reportsMenuId,
            'sort_order' => 1.212,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if (!Schema::hasTable('roles') || !Schema::hasTable('permission_menu')) {
            return;
        }

        foreach (DB::table('roles')->where('slug', 'admin')->pluck('id') as $roleId) {
            DB::table('permission_menu')->insert([
                'role_id' => $roleId,
                'menu_id' => $menuId,
                'can_view' => true,
                'can_create' => false,
                'can_update' => false,
                'can_delete' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
