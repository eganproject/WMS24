<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('outbound_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('outbound_transactions', 'warehouse_id')) {
                $table->foreignId('warehouse_id')->nullable()->after('note')->constrained('warehouses')->nullOnDelete();
            }
        });

        $displayCode = config('inventory.display_warehouse_code', 'GUDANG_DISPLAY');
        $displayId = DB::table('warehouses')->where('code', $displayCode)->value('id');
        if ($displayId) {
            DB::table('outbound_transactions')->whereNull('warehouse_id')->update([
                'warehouse_id' => $displayId,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('outbound_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('outbound_transactions', 'warehouse_id')) {
                $table->dropForeign(['warehouse_id']);
                $table->dropColumn(['warehouse_id']);
            }
        });
    }
};
