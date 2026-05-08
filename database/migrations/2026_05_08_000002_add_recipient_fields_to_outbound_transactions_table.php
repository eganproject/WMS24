<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('outbound_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('outbound_transactions', 'recipient_name')) {
                $table->string('recipient_name', 150)->nullable()->after('supplier_id');
            }

            if (!Schema::hasColumn('outbound_transactions', 'recipient_phone')) {
                $table->string('recipient_phone', 50)->nullable()->after('recipient_name');
            }

            if (!Schema::hasColumn('outbound_transactions', 'recipient_address')) {
                $table->text('recipient_address')->nullable()->after('recipient_phone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('outbound_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('outbound_transactions', 'recipient_address')) {
                $table->dropColumn('recipient_address');
            }

            if (Schema::hasColumn('outbound_transactions', 'recipient_phone')) {
                $table->dropColumn('recipient_phone');
            }

            if (Schema::hasColumn('outbound_transactions', 'recipient_name')) {
                $table->dropColumn('recipient_name');
            }
        });
    }
};
