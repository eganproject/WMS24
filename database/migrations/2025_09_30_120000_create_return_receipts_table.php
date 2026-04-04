<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('warehouse_id')->constrained('warehouses')->onDelete('restrict');
            $table->date('return_date');
            $table->enum('status', ['draft', 'completed'])->default('draft');
            $table->mediumText('description')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('verified_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['return_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_receipts');
    }
};

