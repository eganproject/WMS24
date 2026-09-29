<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('qc_resi_scan_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qc_resi_scan_id')->constrained('qc_resi_scans')->cascadeOnDelete();
            $table->foreignId('resi_id')->nullable()->constrained('resis')->nullOnDelete();
            $table->foreignId('picker_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('event_type', 30);
            $table->string('scan_code', 191)->nullable();
            $table->string('sku', 100)->nullable();
            $table->string('expected_sku', 100)->nullable();
            $table->integer('qty')->nullable();
            $table->integer('expected_qty')->nullable();
            $table->integer('scanned_qty')->nullable();
            $table->string('reason', 500)->nullable();
            $table->json('meta')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();

            $table->index(['picker_employee_id', 'occurred_at'], 'qcrse_picker_at_idx');
            $table->index(['event_type', 'occurred_at'], 'qcrse_type_at_idx');
            $table->index(['sku', 'expected_sku'], 'qcrse_sku_pair_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qc_resi_scan_events');
    }
};
