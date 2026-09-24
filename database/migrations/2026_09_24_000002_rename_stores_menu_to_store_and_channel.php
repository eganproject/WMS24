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

        // Hanya ubah nama bawaan lama. Nama yang sudah dikustomisasi lewat web
        // sengaja tidak disentuh.
        DB::table('menus')
            ->where('slug', 'stores')
            ->where('name', 'Stores')
            ->update([
                'name' => 'Toko & Channel',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('menus')) {
            return;
        }

        DB::table('menus')
            ->where('slug', 'stores')
            ->where('name', 'Toko & Channel')
            ->update([
                'name' => 'Stores',
                'updated_at' => now(),
            ]);
    }
};
