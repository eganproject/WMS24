<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('stock_mutations') || Schema::hasIndex('stock_mutations', 'sm_balance_report_idx')) {
            return;
        }

        Schema::table('stock_mutations', function (Blueprint $table) {
            $table->index(
                ['is_void', 'occurred_at', 'warehouse_id', 'item_id'],
                'sm_balance_report_idx'
            );
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('stock_mutations') || !Schema::hasIndex('stock_mutations', 'sm_balance_report_idx')) {
            return;
        }

        Schema::table('stock_mutations', function (Blueprint $table) {
            $table->dropIndex('sm_balance_report_idx');
        });
    }
};
