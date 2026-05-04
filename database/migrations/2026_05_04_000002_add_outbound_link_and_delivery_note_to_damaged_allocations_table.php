<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('damaged_allocations', function (Blueprint $table) {
            if (!Schema::hasColumn('damaged_allocations', 'outbound_transaction_id')) {
                $table->foreignId('outbound_transaction_id')
                    ->nullable()
                    ->after('target_warehouse_id')
                    ->constrained('outbound_transactions')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('damaged_allocations', 'surat_jalan_no')) {
                $table->string('surat_jalan_no', 100)->nullable()->after('source_ref');
            }

            if (!Schema::hasColumn('damaged_allocations', 'surat_jalan_at')) {
                $table->timestamp('surat_jalan_at')->nullable()->after('surat_jalan_no');
            }
        });
    }

    public function down(): void
    {
        Schema::table('damaged_allocations', function (Blueprint $table) {
            if (Schema::hasColumn('damaged_allocations', 'surat_jalan_at')) {
                $table->dropColumn('surat_jalan_at');
            }

            if (Schema::hasColumn('damaged_allocations', 'surat_jalan_no')) {
                $table->dropColumn('surat_jalan_no');
            }

            if (Schema::hasColumn('damaged_allocations', 'outbound_transaction_id')) {
                $table->dropConstrainedForeignId('outbound_transaction_id');
            }
        });
    }
};
