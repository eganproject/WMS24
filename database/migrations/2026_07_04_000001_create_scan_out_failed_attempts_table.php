<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('scan_out_failed_attempts')) {
            Schema::create('scan_out_failed_attempts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('resi_id')->nullable()->constrained('resis')->nullOnDelete();
                $table->foreignId('qc_resi_scan_id')->nullable()->constrained('qc_resi_scans')->nullOnDelete();
                $table->foreignId('shipment_scan_out_id')->nullable()->constrained('shipment_scan_outs')->nullOnDelete();
                $table->string('scan_type', 30)->nullable();
                $table->string('scan_code', 150)->nullable();
                $table->string('reason_code', 50);
                $table->string('message')->nullable();
                $table->string('resi_status', 30)->nullable();
                $table->string('qc_status', 30)->nullable();
                $table->timestamp('qc_completed_at')->nullable();
                $table->timestamp('existing_scanned_at')->nullable();
                $table->foreignId('attempted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('attempted_at')->useCurrent();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamps();

                $table->index('attempted_at', 'sofa_attempted_at_idx');
                $table->index(['attempted_by', 'attempted_at'], 'sofa_attempted_by_at_idx');
                $table->index(['reason_code', 'attempted_at'], 'sofa_reason_at_idx');
                $table->index(['resi_id', 'attempted_at'], 'sofa_resi_at_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('scan_out_failed_attempts');
    }
};
