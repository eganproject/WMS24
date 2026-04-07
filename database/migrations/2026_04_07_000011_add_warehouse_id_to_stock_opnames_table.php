<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('stock_opnames', function (Blueprint $table) {
            if (!Schema::hasColumn('stock_opnames', 'warehouse_id')) {
                $table->foreignId('warehouse_id')->nullable()->after('note')->constrained('warehouses')->nullOnDelete();
            }
        });

        $defaultCode = config('inventory.default_warehouse_code', 'GUDANG_BESAR');
        $defaultId = DB::table('warehouses')->where('code', $defaultCode)->value('id');
        if ($defaultId) {
            DB::table('stock_opnames')->whereNull('warehouse_id')->update([
                'warehouse_id' => $defaultId,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('stock_opnames', function (Blueprint $table) {
            if (Schema::hasColumn('stock_opnames', 'warehouse_id')) {
                $table->dropForeign(['warehouse_id']);
                $table->dropColumn(['warehouse_id']);
            }
        });
    }
};
