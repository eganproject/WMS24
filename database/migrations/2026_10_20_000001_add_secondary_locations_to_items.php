<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alamat ke-2 per item untuk gudang display dan gudang besar.
 * Hanya menambah kolom baru (nullable); alamat lama tetap menjadi Alamat 1.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('items', 'secondary_location_id')) {
            Schema::table('items', function (Blueprint $table) {
                $table->foreignId('secondary_location_id')
                    ->nullable()
                    ->after('location_id')
                    ->constrained('locations')
                    ->nullOnDelete();
            });
        }

        if (!Schema::hasColumn('items', 'main_secondary_location_id')) {
            Schema::table('items', function (Blueprint $table) {
                $table->foreignId('main_secondary_location_id')
                    ->nullable()
                    ->after('main_location_id')
                    ->constrained('locations')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('items', 'main_secondary_location_id')) {
            Schema::table('items', function (Blueprint $table) {
                $table->dropConstrainedForeignId('main_secondary_location_id');
            });
        }

        if (Schema::hasColumn('items', 'secondary_location_id')) {
            Schema::table('items', function (Blueprint $table) {
                $table->dropConstrainedForeignId('secondary_location_id');
            });
        }
    }
};
