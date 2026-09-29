<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('qc_resi_scan_events', function (Blueprint $table) {
            $table->string('reason_code', 30)->nullable()->after('scanned_qty');
            $table->index(['reason_code', 'occurred_at'], 'qcrse_reason_at_idx');
        });

        Schema::table('qc_resi_scan_substitutions', function (Blueprint $table) {
            $table->string('reason_code', 30)->nullable()->after('qty')->index();
        });
    }

    public function down(): void
    {
        Schema::table('qc_resi_scan_substitutions', function (Blueprint $table) {
            $table->dropIndex(['reason_code']);
            $table->dropColumn('reason_code');
        });

        Schema::table('qc_resi_scan_events', function (Blueprint $table) {
            $table->dropIndex('qcrse_reason_at_idx');
            $table->dropColumn('reason_code');
        });
    }
};
