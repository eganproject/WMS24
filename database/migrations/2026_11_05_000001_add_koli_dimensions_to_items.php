<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dimensi koli (panjang, lebar, tinggi dalam cm) sebagai dasar volume per koli.
 * Hanya menambah kolom baru (nullable, tanpa default); data item lama tidak diubah.
 */
return new class extends Migration {
    private const COLUMNS = ['length_per_koli', 'width_per_koli', 'height_per_koli'];

    public function up(): void
    {
        $after = 'cbm_per_koli';
        foreach (self::COLUMNS as $column) {
            if (!Schema::hasColumn('items', $column)) {
                Schema::table('items', function (Blueprint $table) use ($column, $after) {
                    $table->decimal($column, 8, 2)->nullable()->after($after);
                });
            }
            $after = $column;
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::COLUMNS) as $column) {
            if (Schema::hasColumn('items', $column)) {
                Schema::table('items', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
