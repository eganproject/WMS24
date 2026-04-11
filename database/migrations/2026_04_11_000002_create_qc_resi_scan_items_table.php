<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('qc_resi_scan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qc_resi_scan_id')->constrained('qc_resi_scans')->cascadeOnDelete();
            $table->string('sku', 100);
            $table->integer('expected_qty');
            $table->integer('scanned_qty')->default(0);
            $table->timestamps();

            $table->unique(['qc_resi_scan_id', 'sku']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qc_resi_scan_items');
    }
};
