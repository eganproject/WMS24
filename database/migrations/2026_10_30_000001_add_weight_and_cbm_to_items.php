<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Berat per pcs, berat per koli (kg), dan CBM per koli (m³) pada item.
 * Hanya menambah kolom baru (nullable, tanpa default); data item lama tidak diubah.
 */
return new class extends Migration {
    private const COLUMNS = [
        'weight_per_pcs' => [10, 3],
        'weight_per_koli' => [10, 3],
        'cbm_per_koli' => [12, 6],
    ];

    public function up(): void
    {
        $after = 'koli_qty';
        foreach (self::COLUMNS as $column => [$precision, $scale]) {
            if (!Schema::hasColumn('items', $column)) {
                Schema::table('items', function (Blueprint $table) use ($column, $precision, $scale, $after) {
                    $table->decimal($column, $precision, $scale)->nullable()->after($after);
                });
            }
            $after = $column;
        }
    }

    public function down(): void
    {
        foreach (array_reverse(array_keys(self::COLUMNS)) as $column) {
            if (Schema::hasColumn('items', $column)) {
                Schema::table('items', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
