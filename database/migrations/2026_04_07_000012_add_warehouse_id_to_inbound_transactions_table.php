<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('inbound_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('inbound_transactions', 'warehouse_id')) {
                $table->foreignId('warehouse_id')->nullable()->after('note')->constrained('warehouses')->nullOnDelete();
            }
        });

        // Try to infer warehouse from existing stock mutations (opening import, etc.)
        $rows = DB::table('stock_mutations')
            ->select('source_id', DB::raw('COUNT(DISTINCT warehouse_id) as wh_count'), DB::raw('MIN(warehouse_id) as warehouse_id'))
            ->where('source_type', 'inbound')
            ->groupBy('source_id')
            ->get();

        foreach ($rows as $row) {
            $whId = (int) ($row->warehouse_id ?? 0);
            if ((int) ($row->wh_count ?? 0) === 1 && $whId > 0) {
                DB::table('inbound_transactions')
                    ->where('id', (int) $row->source_id)
                    ->update(['warehouse_id' => $whId]);
            }
        }

        // Fallback to default warehouse for remaining rows
        $defaultCode = config('inventory.default_warehouse_code', 'GUDANG_BESAR');
        $defaultId = DB::table('warehouses')->where('code', $defaultCode)->value('id');
        if ($defaultId) {
            DB::table('inbound_transactions')->whereNull('warehouse_id')->update([
                'warehouse_id' => $defaultId,
            ]);
        }

        try {
            DB::statement('ALTER TABLE inbound_transactions MODIFY warehouse_id BIGINT UNSIGNED NOT NULL');
        } catch (\Throwable) {
            // ignore for unsupported drivers
        }
    }

    public function down(): void
    {
        Schema::table('inbound_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('inbound_transactions', 'warehouse_id')) {
                $table->dropForeign(['warehouse_id']);
                $table->dropColumn(['warehouse_id']);
            }
        });
    }
};
