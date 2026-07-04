<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('low_stock_snapshot_items', function (Blueprint $table) {
            if (!Schema::hasColumn('low_stock_snapshot_items', 'resolution_status')) {
                $table->string('resolution_status', 20)->default('open')->after('status');
            }
            if (!Schema::hasColumn('low_stock_snapshot_items', 'resolved_at')) {
                $table->timestamp('resolved_at')->nullable()->after('resolution_status');
            }
            if (!Schema::hasColumn('low_stock_snapshot_items', 'resolved_snapshot_id')) {
                $table->foreignId('resolved_snapshot_id')->nullable()->after('resolved_at')->constrained('low_stock_snapshots')->nullOnDelete();
            }
            if (!Schema::hasColumn('low_stock_snapshot_items', 'resolved_stock')) {
                $table->integer('resolved_stock')->nullable()->after('resolved_snapshot_id');
            }
            if (!Schema::hasColumn('low_stock_snapshot_items', 'resolved_safety_stock')) {
                $table->integer('resolved_safety_stock')->nullable()->after('resolved_stock');
            }
        });

        Schema::table('low_stock_snapshot_items', function (Blueprint $table) {
            $table->index(['resolution_status', 'resolved_at'], 'lssi_resolution_status_at_idx');
            $table->index(['item_id', 'warehouse_id', 'resolution_status'], 'lssi_item_wh_resolution_idx');
        });
    }

    public function down(): void
    {
        Schema::table('low_stock_snapshot_items', function (Blueprint $table) {
            $table->dropIndex('lssi_resolution_status_at_idx');
            $table->dropIndex('lssi_item_wh_resolution_idx');
            $table->dropConstrainedForeignId('resolved_snapshot_id');
            $table->dropColumn([
                'resolution_status',
                'resolved_at',
                'resolved_stock',
                'resolved_safety_stock',
            ]);
        });
    }
};
