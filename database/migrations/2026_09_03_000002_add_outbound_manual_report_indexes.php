<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('outbound_transactions', function (Blueprint $table) {
            $table->index(['type', 'transacted_at'], 'idx_outbound_report_type_date');
            $table->index(
                ['type', 'warehouse_id', 'status', 'transacted_at'],
                'idx_outbound_report_type_wh_status_date'
            );
        });
    }

    public function down(): void
    {
        Schema::table('outbound_transactions', function (Blueprint $table) {
            $table->dropIndex('idx_outbound_report_type_date');
            $table->dropIndex('idx_outbound_report_type_wh_status_date');
        });
    }
};
