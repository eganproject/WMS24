<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('stock_mutations', function (Blueprint $table) {
            $table->integer('stock_before')->nullable()->after('qty');
            $table->integer('stock_after')->nullable()->after('stock_before');
        });

        $balances = DB::table('item_stocks')
            ->select('item_id', 'warehouse_id', 'stock')
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->item_id.'|'.(int) $row->warehouse_id => (int) $row->stock]);

        DB::table('stock_mutations')
            ->select('id', 'item_id', 'warehouse_id', 'direction', 'qty')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->chunk(500, function ($mutations) use (&$balances) {
                foreach ($mutations as $mutation) {
                    $key = (int) $mutation->item_id.'|'.(int) $mutation->warehouse_id;
                    $stockAfter = (int) ($balances[$key] ?? 0);
                    $qty = (int) $mutation->qty;
                    $stockBefore = $mutation->direction === 'in'
                        ? $stockAfter - $qty
                        : $stockAfter + $qty;

                    DB::table('stock_mutations')
                        ->where('id', $mutation->id)
                        ->update([
                            'stock_before' => $stockBefore,
                            'stock_after' => $stockAfter,
                        ]);

                    $balances[$key] = $stockBefore;
                }
            });
    }

    public function down(): void
    {
        Schema::table('stock_mutations', function (Blueprint $table) {
            $table->dropColumn(['stock_before', 'stock_after']);
        });
    }
};
