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
        Schema::create('stock_opname_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_opname_id')->constrained('stock_opnames')->onDelete('cascade');
            $table->foreignId('item_id')->constrained('items')->onDelete('cascade');
            $table->decimal('system_quantity', 20, 2);
            $table->decimal('system_koli', 20, 2)->nullable();
            $table->decimal('physical_quantity', 20, 2);
            $table->decimal('physical_koli', 20, 2)->nullable();
            $table->decimal('discrepancy_quantity', 20, 2);
            $table->decimal('discrepancy_koli', 20, 2);
            $table->mediumText('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_opname_items');
    }
};
