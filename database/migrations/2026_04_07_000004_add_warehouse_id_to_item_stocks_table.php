<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('item_stocks', 'warehouse_id')) {
            Schema::table('item_stocks', function (Blueprint $table) {
                $table->unsignedBigInteger('warehouse_id')->nullable()->after('item_id');
            });
        }

        $defaultCode = config('inventory.default_warehouse_code', 'GUDANG_BESAR');
        $displayCode = config('inventory.display_warehouse_code', 'GUDANG_DISPLAY');
        $now = now();

        DB::table('warehouses')->updateOrInsert(
            ['code' => $defaultCode],
            ['name' => 'Gudang Besar', 'type' => 'main', 'updated_at' => $now, 'created_at' => $now]
        );
        DB::table('warehouses')->updateOrInsert(
            ['code' => $displayCode],
            ['name' => 'Gudang Display', 'type' => 'display', 'updated_at' => $now, 'created_at' => $now]
        );

        $defaultId = DB::table('warehouses')->where('code', $defaultCode)->value('id');
        if ($defaultId) {
            DB::table('item_stocks')->whereNull('warehouse_id')->update(['warehouse_id' => $defaultId]);
        }

        try {
            DB::statement('ALTER TABLE item_stocks MODIFY warehouse_id BIGINT UNSIGNED NOT NULL');
        } catch (\Throwable) {
            // ignore for unsupported drivers
        }

        try {
            Schema::table('item_stocks', function (Blueprint $table) {
                $table->dropForeign(['item_id']);
            });
        } catch (\Throwable) {
            // ignore if FK doesn't exist
        }

        try {
            Schema::table('item_stocks', function (Blueprint $table) {
                $table->dropUnique('item_stocks_item_id_unique');
            });
        } catch (\Throwable) {
            // ignore if index doesn't exist
        }

        try {
            Schema::table('item_stocks', function (Blueprint $table) {
                $table->unique(['item_id', 'warehouse_id']);
            });
        } catch (\Throwable) {
            // ignore if unique already exists
        }

        try {
            Schema::table('item_stocks', function (Blueprint $table) {
                $table->foreign('item_id')->references('id')->on('items')->cascadeOnDelete();
            });
        } catch (\Throwable) {
            // ignore if FK already exists
        }

        try {
            Schema::table('item_stocks', function (Blueprint $table) {
                $table->foreign('warehouse_id')->references('id')->on('warehouses')->cascadeOnDelete();
            });
        } catch (\Throwable) {
            // ignore if FK already exists
        }
    }

    public function down(): void
    {
        Schema::table('item_stocks', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropUnique(['item_id', 'warehouse_id']);
            $table->unique('item_id');
            $table->dropColumn('warehouse_id');
        });
    }
};
