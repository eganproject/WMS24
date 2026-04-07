<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('stock_mutations', function (Blueprint $table) {
            $table->unsignedBigInteger('warehouse_id')->nullable()->after('item_id');
        });

        $defaultCode = config('inventory.default_warehouse_code', 'GUDANG_BESAR');
        $defaultId = DB::table('warehouses')->where('code', $defaultCode)->value('id');
        if ($defaultId) {
            DB::table('stock_mutations')->whereNull('warehouse_id')->update(['warehouse_id' => $defaultId]);
        }

        try {
            DB::statement('ALTER TABLE stock_mutations MODIFY warehouse_id BIGINT UNSIGNED NOT NULL');
        } catch (\Throwable) {
            // ignore for unsupported drivers
        }

        Schema::table('stock_mutations', function (Blueprint $table) {
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->cascadeOnDelete();
            $table->index(['warehouse_id']);
        });
    }

    public function down(): void
    {
        Schema::table('stock_mutations', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropIndex(['warehouse_id']);
            $table->dropColumn('warehouse_id');
        });
    }
};
