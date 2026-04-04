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
        Schema::create('stock_in_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_in_order_id')->constrained('stock_in_orders')->cascadeOnDelete();
            $table->integer('item_id');
            $table->decimal('quantity', 20, 2)->default(0);
            $table->decimal('koli', 20, 2)->default(0);
            $table->decimal('remaining_quantity', 20, 2)->default(0);
            $table->decimal('remaining_koli', 20, 2)->default(0);
            $table->enum('status', ['pending', 'on_progress', 'completed'])->default('pending');
            $table->mediumText('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_in_order_items');
    }
};
