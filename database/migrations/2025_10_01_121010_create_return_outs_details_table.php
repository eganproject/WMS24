<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_out_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_out_id')->constrained('return_outs')->onDelete('cascade');
            // Jika retur ini berasal dari detail penerimaan tertentu, tautkan agar bisa validasi kuantitas
            $table->foreignId('item_id')->constrained('items')->onDelete('restrict');
            $table->decimal('quantity', 20, 2)->default(0);
            $table->decimal('koli', 20, 2)->default(0);
            $table->mediumText('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_out_details');
    }
};

