<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('damaged_allocations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('type', 30);
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('target_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->string('source_ref', 100)->nullable();
            $table->timestamp('transacted_at')->useCurrent();
            $table->text('note')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['type', 'status']);
        });

        Schema::create('damaged_allocation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('damaged_allocation_id')->constrained('damaged_allocations')->cascadeOnDelete();
            $table->string('line_type', 20);
            $table->foreignId('damaged_good_item_id')->nullable()->constrained('damaged_good_items')->nullOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->integer('qty');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['damaged_good_item_id', 'line_type'], 'damaged_alloc_items_source_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('damaged_allocation_items');
        Schema::dropIfExists('damaged_allocations');
    }
};
