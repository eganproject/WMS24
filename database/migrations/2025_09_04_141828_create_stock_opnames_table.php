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
        Schema::create('stock_opnames', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('warehouse_id')->constrained('warehouses')->onDelete('cascade');
            $table->mediumText('description')->nullable();
            $table->foreignId( 'started_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId( 'completed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->date('start_date');
            $table->date('completed_date')->nullable();
            $table->enum('status', ['in_progress', 'completed'])->default('in_progress');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_opnames');
    }
};
