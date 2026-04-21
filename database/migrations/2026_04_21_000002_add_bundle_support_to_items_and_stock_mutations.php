<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('items') && !Schema::hasColumn('items', 'item_type')) {
            Schema::table('items', function (Blueprint $table) {
                $table->string('item_type', 20)->default('single')->after('name');
            });
        }

        if (!Schema::hasTable('item_bundle_components')) {
            Schema::create('item_bundle_components', function (Blueprint $table) {
                $table->id();
                $table->foreignId('bundle_item_id')->constrained('items')->cascadeOnDelete();
                $table->foreignId('component_item_id')->constrained('items')->cascadeOnDelete();
                $table->unsignedInteger('required_qty');
                $table->timestamps();

                $table->unique(['bundle_item_id', 'component_item_id'], 'bundle_component_unique');
            });
        }

        if (Schema::hasTable('stock_mutations') && !Schema::hasColumn('stock_mutations', 'reference_item_id')) {
            Schema::table('stock_mutations', function (Blueprint $table) {
                $table->foreignId('reference_item_id')->nullable()->after('item_id')->constrained('items')->nullOnDelete();
            });
        }

        if (Schema::hasTable('stock_mutations') && !Schema::hasColumn('stock_mutations', 'reference_sku')) {
            Schema::table('stock_mutations', function (Blueprint $table) {
                $table->string('reference_sku', 100)->nullable()->after('reference_item_id');
            });
        }

        if (Schema::hasTable('stock_mutations')) {
            DB::table('stock_mutations')
                ->whereNull('reference_item_id')
                ->orderBy('id')
                ->chunkById(200, function ($mutations) {
                    $itemMap = DB::table('items')
                        ->whereIn('id', $mutations->pluck('item_id')->filter()->unique()->all())
                        ->pluck('sku', 'id');

                    foreach ($mutations as $mutation) {
                        DB::table('stock_mutations')
                            ->where('id', $mutation->id)
                            ->update([
                                'reference_item_id' => $mutation->item_id,
                                'reference_sku' => $itemMap[$mutation->item_id] ?? null,
                            ]);
                    }
                });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('stock_mutations') && Schema::hasColumn('stock_mutations', 'reference_sku')) {
            Schema::table('stock_mutations', function (Blueprint $table) {
                $table->dropColumn('reference_sku');
            });
        }

        if (Schema::hasTable('stock_mutations') && Schema::hasColumn('stock_mutations', 'reference_item_id')) {
            Schema::table('stock_mutations', function (Blueprint $table) {
                $table->dropConstrainedForeignId('reference_item_id');
            });
        }

        Schema::dropIfExists('item_bundle_components');

        if (Schema::hasTable('items') && Schema::hasColumn('items', 'item_type')) {
            Schema::table('items', function (Blueprint $table) {
                $table->dropColumn('item_type');
            });
        }
    }
};
