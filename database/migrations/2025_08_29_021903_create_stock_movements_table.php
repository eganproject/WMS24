<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->integer('item_id');
            $table->integer('warehouse_id');
            $table->date('date')->default(now());
            $table->decimal('quantity', 20, 2)->default(0);
            $table->decimal('koli', 20, 2)->default(0);
            $table->decimal('stock_before', 20, 2)->default(0);
            $table->decimal('stock_after', 20, 2)->default(0);
            $table->enum('type', ['stock_in', 'stock_out']);
            $table->mediumText('description')->nullable();
            $table->bigInteger('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->bigInteger('reference_id')->comment('ID dari dokumen sumber (misal: id dari goods_receipt_details)')->nullable();
            $table->enum('reference_type', ['goods_receipt_details', 'transfer_request_items', 'stock_out_items', 'adjustment_items', 'stock_opname_items', 'return_receipt_details', 'return_out_details'])->comment('Tipe dokumen sumber (misal: goods_receipt_details atau lainnya)');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
