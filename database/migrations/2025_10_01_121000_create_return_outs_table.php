<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_outs', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('warehouse_id')->constrained('warehouses')->onDelete('restrict');
            // Untuk retur dari transfer request: gudang tujuan (gudang pengirim sebelumnya)
            $table->foreignId('destination_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            // Mengacu ke dokumen penerimaan barang yang menjadi sumber retur
            // Tanggal retur dikirim keluar dari gudang ini
            $table->date('return_date');
            $table->enum('status', ['draft', 'completed'])->default('draft');
            $table->mediumText('description')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['return_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_outs');
    }
};
