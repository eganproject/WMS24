<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('stock_mutations', function (Blueprint $table) {
            $table->boolean('is_void')->default(false)->after('created_by');
            $table->timestamp('voided_at')->nullable()->after('is_void');
            $table->foreignId('voided_by')->nullable()->after('voided_at')
                ->constrained('users')->nullOnDelete();
            $table->index(['is_void', 'item_id', 'warehouse_id']);
        });
    }

    public function down(): void
    {
        Schema::table('stock_mutations', function (Blueprint $table) {
            $table->dropForeign(['voided_by']);
            $table->dropIndex(['is_void', 'item_id', 'warehouse_id']);
            $table->dropColumn(['is_void', 'voided_at', 'voided_by']);
        });
    }
};
