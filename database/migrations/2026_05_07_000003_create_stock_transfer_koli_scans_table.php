<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stock_transfer_koli_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained('stock_transfers')->cascadeOnDelete();
            $table->foreignId('stock_transfer_item_id')->constrained('stock_transfer_items')->cascadeOnDelete();
            $table->foreignId('inbound_koli_unit_id')->unique()->constrained('inbound_koli_units')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->unsignedInteger('qty');
            $table->unsignedInteger('qty_ok')->default(0);
            $table->unsignedInteger('qty_reject')->default(0);
            $table->unsignedInteger('qty_short')->default(0);
            $table->text('qc_note')->nullable();
            $table->foreignId('scanned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('scanned_at')->nullable();
            $table->timestamps();

            $table->unique(['stock_transfer_id', 'inbound_koli_unit_id'], 'uq_transfer_koli_scan');
            $table->index(['stock_transfer_item_id']);
            $table->index(['item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_koli_scans');
    }
};
