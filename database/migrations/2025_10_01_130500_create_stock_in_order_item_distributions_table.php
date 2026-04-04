<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stock_in_order_item_distributions', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->foreignId('stock_in_order_item_id')->constrained('stock_in_order_items')->cascadeOnDelete();
            $table->foreignId('to_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->decimal('quantity', 20, 2)->default(0);
            $table->decimal('koli', 20, 2)->default(0);
            $table->enum('status', ['draft', 'completed'])->default('draft');
            $table->mediumText('note')->nullable();
            $table->timestamps();

            // Use a short, explicit index name to avoid MySQL 64-char limit
            $table->index(['stock_in_order_item_id', 'to_warehouse_id'], 'sioi_dist_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_in_order_item_distributions');
    }
};
