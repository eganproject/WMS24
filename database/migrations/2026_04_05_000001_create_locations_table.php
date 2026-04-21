<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->constrained('areas')->cascadeOnDelete();
            $table->string('rack_code', 20);
            $table->unsignedSmallInteger('column_no');
            $table->unsignedSmallInteger('row_no');
            $table->string('code', 50)->unique();
            $table->timestamps();

            $table->unique(['area_id', 'rack_code', 'column_no', 'row_no'], 'locations_unique_slot');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
