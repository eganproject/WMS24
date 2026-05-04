<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasIndex('stock_mutations', 'sm_source_item_unique')) {
            return;
        }

        $uniqueColumns = [
            'source_type',
            'source_id',
            'item_id',
            'reference_item_id',
            'warehouse_id',
            'direction',
            'source_subtype',
        ];

        // Merge exact duplicate mutation rows into one row before adding the guard index.
        $duplicates = DB::table('stock_mutations')
            ->select($uniqueColumns)
            ->groupBy($uniqueColumns)
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $dup) {
            $query = DB::table('stock_mutations');
            foreach ($uniqueColumns as $column) {
                $value = $dup->{$column};
                $value === null
                    ? $query->whereNull($column)
                    : $query->where($column, $value);
            }

            $rows = $query
                ->orderByDesc('id')
                ->get(['id', 'qty']);

            if ($rows->count() > 1) {
                $keepId = $rows->first()->id;
                $deleteIds = $rows->slice(1)->pluck('id')->values()->all();

                DB::table('stock_mutations')->where('id', $keepId)->update([
                    'qty' => (int) $rows->sum('qty'),
                ]);
                DB::table('stock_mutations')->whereIn('id', $deleteIds)->delete();
            }
        }

        try {
            Schema::table('stock_mutations', function (Blueprint $table) {
                $table->unique(
                    [
                        'source_type',
                        'source_id',
                        'item_id',
                        'reference_item_id',
                        'warehouse_id',
                        'direction',
                        'source_subtype',
                    ],
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
