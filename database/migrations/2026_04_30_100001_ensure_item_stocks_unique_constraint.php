<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $dbName = DB::getDatabaseName();
        $exists = DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', $dbName)
            ->where('TABLE_NAME', 'item_stocks')
            ->where('INDEX_NAME', 'item_stocks_item_id_warehouse_id_unique')
            ->exists();

        if ($exists) {
            return;
        }

        // Merge duplicate (item_id, warehouse_id) rows by summing stock values
        $duplicates = DB::table('item_stocks')
            ->select('item_id', 'warehouse_id')
            ->groupBy('item_id', 'warehouse_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $dup) {
            $rows = DB::table('item_stocks')
                ->where('item_id', $dup->item_id)
                ->where('warehouse_id', $dup->warehouse_id)
                ->orderByDesc('id')
                ->get(['id', 'stock']);

            if ($rows->count() <= 1) {
                continue;
            }

            $totalStock = $rows->sum('stock');
            $keepId = $rows->first()->id;
            $deleteIds = $rows->slice(1)->pluck('id')->values()->all();

            DB::table('item_stocks')->where('id', $keepId)->update(['stock' => $totalStock]);
            DB::table('item_stocks')->whereIn('id', $deleteIds)->delete();
        }

        try {
            Schema::table('item_stocks', function (Blueprint $table) {
                $table->unique(['item_id', 'warehouse_id']);
            });
        } catch (\Throwable) {
            // already exists or unsupported
        }
    }

    public function down(): void
    {
        // no-op — do not drop the constraint on rollback
    }
};
