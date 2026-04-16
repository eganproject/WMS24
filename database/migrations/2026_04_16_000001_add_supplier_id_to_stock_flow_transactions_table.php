<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('inbound_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('inbound_transactions', 'supplier_id')) {
                $table->foreignId('supplier_id')
                    ->nullable()
                    ->after('ref_no')
                    ->constrained('suppliers')
                    ->nullOnDelete();
            }
        });

        Schema::table('outbound_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('outbound_transactions', 'supplier_id')) {
                $table->foreignId('supplier_id')
                    ->nullable()
                    ->after('ref_no')
                    ->constrained('suppliers')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('outbound_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('outbound_transactions', 'supplier_id')) {
                $table->dropConstrainedForeignId('supplier_id');
            }
        });

        Schema::table('inbound_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('inbound_transactions', 'supplier_id')) {
                $table->dropConstrainedForeignId('supplier_id');
            }
        });
    }
};
