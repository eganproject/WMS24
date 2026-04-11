<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('qc_resi_scans', function (Blueprint $table) {
            $table->foreignId('completed_by')
                ->nullable()
                ->after('completed_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('last_scanned_by')
                ->nullable()
                ->after('completed_by')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('last_scanned_at')
                ->nullable()
                ->after('last_scanned_by');
            $table->unsignedInteger('reset_count')
                ->default(0)
                ->after('last_scanned_at');
            $table->foreignId('reset_by')
                ->nullable()
                ->after('reset_count')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('reset_at')
                ->nullable()
                ->after('reset_by');
            $table->text('reset_reason')
                ->nullable()
                ->after('reset_at');
        });
    }

    public function down(): void
    {
        Schema::table('qc_resi_scans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('completed_by');
            $table->dropConstrainedForeignId('last_scanned_by');
            $table->dropConstrainedForeignId('reset_by');
            $table->dropColumn([
                'last_scanned_at',
                'reset_count',
                'reset_at',
                'reset_reason',
            ]);
        });
    }
};
