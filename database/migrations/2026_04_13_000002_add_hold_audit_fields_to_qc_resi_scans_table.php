<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('qc_resi_scans', function (Blueprint $table) {
            $table->foreignId('hold_by')
                ->nullable()
                ->after('reset_reason')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('hold_at')
                ->nullable()
                ->after('hold_by');
            $table->text('hold_reason')
                ->nullable()
                ->after('hold_at');
        });
    }

    public function down(): void
    {
        Schema::table('qc_resi_scans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hold_by');
            $table->dropColumn([
                'hold_at',
                'hold_reason',
            ]);
        });
    }
};
