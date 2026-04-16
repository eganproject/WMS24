<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('damaged_goods', function (Blueprint $table) {
            if (!Schema::hasColumn('damaged_goods', 'source_warehouse_id')) {
                $table->foreignId('source_warehouse_id')
                    ->nullable()
                    ->after('source_type')
                    ->constrained('warehouses')
                    ->nullOnDelete();
            }
        });

        if (!Schema::hasTable('warehouses')) {
            return;
        }

        $defaultId = DB::table('warehouses')
            ->where('code', config('inventory.default_warehouse_code', 'GUDANG_BESAR'))
            ->value('id');
        $displayId = DB::table('warehouses')
            ->where('code', config('inventory.display_warehouse_code', 'GUDANG_DISPLAY'))
            ->value('id');

        if ($displayId) {
            DB::table('damaged_goods')
                ->whereNull('source_warehouse_id')
                ->where('source_type', 'display')
                ->update(['source_warehouse_id' => $displayId]);
        }

        if ($defaultId) {
            DB::table('damaged_goods')
                ->whereNull('source_warehouse_id')
                ->where('source_type', 'inbound_return')
                ->update(['source_warehouse_id' => $defaultId]);

            DB::table('damaged_goods')
                ->whereNull('source_warehouse_id')
                ->update(['source_warehouse_id' => $defaultId]);
        }

        DB::table('damaged_goods')
            ->where('source_type', 'display')
            ->update(['source_type' => 'warehouse']);
    }

    public function down(): void
    {
        DB::table('damaged_goods')
            ->where('source_type', 'warehouse')
            ->update(['source_type' => 'display']);

        Schema::table('damaged_goods', function (Blueprint $table) {
            if (Schema::hasColumn('damaged_goods', 'source_warehouse_id')) {
                $table->dropConstrainedForeignId('source_warehouse_id');
            }
        });
    }
};
