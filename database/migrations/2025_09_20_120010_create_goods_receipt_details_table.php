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
        Schema::create('goods_receipt_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained('goods_receipts')->onDelete('cascade');
            $table->foreignId('shipment_item_id');
            $table->foreignId('item_id')->constrained('items')->onDelete('restrict');
            $table->decimal('ordered_quantity', 20, 2)->default(0);
            $table->decimal('ordered_koli', 20, 2)->default(0);
            $table->decimal('received_quantity', 20, 2)->default(0);
            $table->decimal('received_koli', 20, 2)->default(0);
            $table->decimal('accepted_quantity', 20, 2)->default(0);
            $table->decimal('accepted_koli', 20, 2)->default(0);
            $table->decimal('rejected_quantity', 20, 2)->default(0);
            $table->decimal('rejected_koli', 20, 2)->default(0);
            $table->mediumText('notes')->nullable();
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_details');
    }
};

