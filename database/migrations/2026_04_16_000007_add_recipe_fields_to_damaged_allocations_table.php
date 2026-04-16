<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('damaged_allocations', function (Blueprint $table) {
            if (!Schema::hasColumn('damaged_allocations', 'recipe_id')) {
                $table->foreignId('recipe_id')
                    ->nullable()
                    ->after('type')
                    ->constrained('rework_recipes')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('damaged_allocations', 'recipe_multiplier')) {
                $table->integer('recipe_multiplier')
                    ->nullable()
                    ->after('recipe_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('damaged_allocations', function (Blueprint $table) {
            if (Schema::hasColumn('damaged_allocations', 'recipe_id')) {
                $table->dropConstrainedForeignId('recipe_id');
            }
            if (Schema::hasColumn('damaged_allocations', 'recipe_multiplier')) {
                $table->dropColumn('recipe_multiplier');
            }
        });
    }
};
