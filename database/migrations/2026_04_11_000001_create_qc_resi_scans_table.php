<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('qc_resi_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resi_id')->constrained('resis')->cascadeOnDelete();
            $table->string('scan_type', 20);
            $table->string('scan_code', 120);
            $table->string('status', 20)->default('draft');
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('scanned_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique('resi_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qc_resi_scans');
    }
};
