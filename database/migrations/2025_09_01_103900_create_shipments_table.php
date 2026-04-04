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
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->unsignedBigInteger('reference_id');
            $table->enum('reference_type', ['transfer request', 'stock in order']);
            $table->date('shipping_date');
            $table->string('vehicle_type')->nullable();                  
            $table->string('license_plate')->nullable();                  
            $table->string('driver_name')->nullable();    
            $table->string('driver_contact')->nullable();
            $table->mediumText('description')->nullable();
            $table->enum('status',['dalam perjalanan', 'selesai'])->default('dalam perjalanan');
            $table->integer('shipped_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};

