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
            ->where('TABLE_NAME', 'stock_mutations')
            ->where('INDEX_NAME', 'sm_source_item_unique')
            ->exists();

        if ($exists) {
            return;
        }

        // Remove duplicate rows — keep the highest-id record per (source_type, source_id, item_id, reference_item_id)
        $duplicates = DB::table('stock_mutations')
            ->select('source_type', 'source_id', 'item_id', 'reference_item_id')
            ->groupBy('source_type', 'source_id', 'item_id', 'reference_item_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $dup) {
            $ids = DB::table('stock_mutations')
                ->where('source_type', $dup->source_type)
                ->where('source_id', $dup->source_id)
                ->where('item_id', $dup->item_id)
                ->where('reference_item_id', $dup->reference_item_id)
                ->orderByDesc('id')
                ->pluck('id');

            if ($ids->count() > 1) {
                DB::table('stock_mutations')
                    ->whereIn('id', $ids->slice(1)->values()->all())
                    ->delete();
            }
        }

        try {
            Schema::table('stock_mutations', function (Blueprint $table) {
                $table->unique(
                    ['source_type', 'source_id', 'item_id', 'reference_item_id'],
                    'sm_source_item_unique'
                );
            });
        } catch (\Throwable) {
            // already exists or unsupported
        }
    }

    public function down(): void
    {
        try {
            Schema::table('stock_mutations', function (Blueprint $table) {
                $table->dropUnique('sm_source_item_unique');
            });
        } catch (\Throwable) {
            // ignore
        }
    }
};
