<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_receipt_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_receipt_id')->constrained('return_receipts')->onDelete('cascade');
            $table->foreignId('item_id')->constrained('items')->onDelete('restrict');
            $table->integer('quantity')->default(0);
            $table->decimal('koli', 20, 2)->default(0);
            $table->integer('accepted_quantity')->default(0);
            $table->decimal('accepted_koli', 20, 2)->default(0);
            $table->integer('rejected_quantity')->default(0);
            $table->decimal('rejected_koli', 20, 2)->default(0);
            $table->mediumText('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_receipt_details');
    }
};

