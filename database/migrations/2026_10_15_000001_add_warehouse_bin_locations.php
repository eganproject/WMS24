<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('areas', 'warehouse_id')) {
            Schema::table('areas', function (Blueprint $table) {
                $table->foreignId('warehouse_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('warehouses')
                    ->nullOnDelete();
            });
        }

        // Semua area yang sudah ada selama ini dipakai untuk gudang display.
        $displayCode = config('inventory.display_warehouse_code', 'GUDANG_DISPLAY');
        $displayId = DB::table('warehouses')->where('code', $displayCode)->value('id');
        if ($displayId) {
            DB::table('areas')->whereNull('warehouse_id')->update(['warehouse_id' => $displayId]);
        }

        if (!Schema::hasColumn('items', 'main_location_id')) {
            Schema::table('items', function (Blueprint $table) {
                $table->foreignId('main_location_id')
                    ->nullable()
                    ->after('location_id')
                    ->constrained('locations')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('items', 'main_location_id')) {
            Schema::table('items', function (Blueprint $table) {
                $table->dropConstrainedForeignId('main_location_id');
            });
        }

        if (Schema::hasColumn('areas', 'warehouse_id')) {
            Schema::table('areas', function (Blueprint $table) {
                $table->dropConstrainedForeignId('warehouse_id');
            });
        }
    }
};
