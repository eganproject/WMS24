<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inbound_koli_units', function (Blueprint $table) {
            $table->id();
            $table->string('code', 220)->unique();
            $table->foreignId('inbound_transaction_id')->constrained('inbound_transactions')->cascadeOnDelete();
            $table->foreignId('inbound_item_id')->constrained('inbound_items')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->string('sku', 100);
            $table->unsignedInteger('koli_no');
            $table->unsignedInteger('qty_per_koli');
            $table->unsignedInteger('qty');
            $table->string('status', 30)->default('available');
            $table->foreignId('reserved_transfer_id')->nullable()->constrained('stock_transfers')->nullOnDelete();
            $table->timestamps();

            $table->unique(['inbound_item_id', 'koli_no'], 'uq_inbound_koli_unit_no');
            $table->index(['item_id', 'status']);
            $table->index(['inbound_transaction_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbound_koli_units');
    }
};
