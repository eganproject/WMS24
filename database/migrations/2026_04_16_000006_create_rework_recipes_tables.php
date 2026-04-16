<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('rework_recipes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->foreignId('target_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('rework_recipe_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rework_recipe_id')->constrained('rework_recipes')->cascadeOnDelete();
            $table->string('line_type', 20);
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->integer('qty');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['rework_recipe_id', 'line_type'], 'rework_recipe_items_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rework_recipe_items');
        Schema::dropIfExists('rework_recipes');
    }
};
