<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MENU_SLUG = 'report-stock-balance';

    private const OLD_NAME = 'Laporan Saldo Stok';

    private const NEW_NAME = 'Laporan Stok';

    public function up(): void
    {
        if (!Schema::hasTable('menus')) {
            return;
        }

        // Ubah hanya menu yang masih menggunakan nama bawaan lama. Nama yang
        // sudah dikustomisasi di production tidak akan ditimpa.
        DB::table('menus')
            ->where('slug', self::MENU_SLUG)
            ->where('name', self::OLD_NAME)
            ->update([
                'name' => self::NEW_NAME,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('menus')) {
            return;
        }

        // Jangan timpa perubahan nama lain yang dibuat setelah migrasi berjalan.
        DB::table('menus')
            ->where('slug', self::MENU_SLUG)
            ->where('name', self::NEW_NAME)
            ->update([
                'name' => self::OLD_NAME,
                'updated_at' => now(),
            ]);
    }
};
