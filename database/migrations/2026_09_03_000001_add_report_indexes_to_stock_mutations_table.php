<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_mutations', function (Blueprint $table) {
            $table->index(['is_void', 'occurred_at', 'id'], 'sm_void_occurred_id_idx');
            $table->index(['is_void', 'warehouse_id', 'occurred_at', 'id'], 'sm_void_warehouse_occurred_idx');
        });
    }

    public function down(): void
    {
        Schema::table('stock_mutations', function (Blueprint $table) {
            $table->dropIndex('sm_void_occurred_id_idx');
            $table->dropIndex('sm_void_warehouse_occurred_idx');
        });
    }
};
