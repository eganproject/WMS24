<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('qc_resi_scan_substitutions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qc_resi_scan_id')->constrained('qc_resi_scans')->cascadeOnDelete();
            $table->string('original_sku', 100);
            $table->string('replacement_sku', 100);
            $table->integer('qty');
            $table->string('reason', 500);
            $table->text('buyer_note_snapshot')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['original_sku', 'replacement_sku']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qc_resi_scan_substitutions');
    }
};
