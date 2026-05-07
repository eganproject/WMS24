<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->string('traceability_mode', 20)->nullable()->after('status');
            $table->text('legacy_reason')->nullable()->after('traceability_mode');
        });
    }

    public function down(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->dropColumn(['traceability_mode', 'legacy_reason']);
        });
    }
};
