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
        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('shipment_id')->constrained('shipments')->onDelete('cascade');
            $table->enum('type', ['transfer', 'pengadaan'])->default('transfer');
            $table->foreignId('warehouse_id')->constrained('warehouses')->onDelete('restrict');
            $table->date('receipt_date');
            $table->enum('status', ['draft', 'completed'])->default('draft');
            $table->mediumText('description')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('verified_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index([ 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goods_receipts');
    }
};

