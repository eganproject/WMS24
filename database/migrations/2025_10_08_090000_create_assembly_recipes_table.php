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
        Schema::create('assembly_recipes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->integer('finished_item_id');
            $table->decimal('output_quantity', 20, 2)->default(1);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('assembly_recipe_items', function (Blueprint $table) {
            $table->id();
            $table->integer('assembly_recipe_id');
            $table->integer('item_id');
            $table->decimal('quantity', 20, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assembly_recipe_items');
        Schema::dropIfExists('assembly_recipes');
    }
};
