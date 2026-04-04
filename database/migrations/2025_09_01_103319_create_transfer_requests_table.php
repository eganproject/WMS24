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
        Schema::create('transfer_requests', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->date('date');
            $table->foreignId('from_warehouse_id')->constrained('warehouses')->onDelete('cascade');
            $table->foreignId('to_warehouse_id')->constrained('warehouses')->onDelete('cascade');
            $table->mediumText('description')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'on_progress', 'completed'])->default('pending');
            $table->bigInteger('requested_by')->nullable()->constrained('users')->onDelete('set null');
            $table->bigInteger('shipped_by')->nullable()->constrained('users')->onDelete('set null');
            $table->bigInteger('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->bigInteger('received_by')->nullable()->constrained('users')->onDelete('set null');
            $table->date('shipped_at')->nullable();
            $table->date('approved_at')->nullable();
            $table->date('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transfer_requests');
    }
};
