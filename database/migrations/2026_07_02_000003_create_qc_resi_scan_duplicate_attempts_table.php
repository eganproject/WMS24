<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('qc_resi_scan_duplicate_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qc_resi_scan_id')->constrained('qc_resi_scans')->cascadeOnDelete();
            $table->foreignId('resi_id')->constrained('resis')->cascadeOnDelete();
            $table->string('scan_type', 20);
            $table->string('scan_code', 120);
            $table->string('existing_status', 20);
            $table->timestamp('qc_completed_at')->nullable();
            $table->foreignId('qc_completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('scanned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('scanned_at')->useCurrent();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index('scanned_at');
            $table->index(['scanned_by', 'scanned_at']);
            $table->index(['resi_id', 'scanned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qc_resi_scan_duplicate_attempts');
    }
};
