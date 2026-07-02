<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('low_stock_snapshots', function (Blueprint $table) {
            $table->id();
            $table->timestamp('snapshot_at')->index();
            $table->string('scope', 20)->default('all');
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->string('warehouse_name')->nullable();
            $table->unsignedInteger('total_low')->default(0);
            $table->unsignedInteger('total_out_of_stock')->default(0);
            $table->unsignedInteger('total_gap')->default(0);
            $table->string('source', 20)->default('manual');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['scope', 'warehouse_id']);
        });

        Schema::create('low_stock_snapshot_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('low_stock_snapshot_id')->constrained('low_stock_snapshots')->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('items')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->string('sku')->index();
            $table->string('name');
            $table->string('warehouse')->nullable();
            $table->string('category')->nullable();
            $table->string('address')->nullable();
            $table->integer('stock')->default(0);
            $table->integer('safety_stock')->default(0);
            $table->integer('gap')->default(0);
            $table->string('status', 20)->default('low');
            $table->string('safety_source', 30)->nullable();
            $table->timestamps();

            $table->index(['low_stock_snapshot_id', 'warehouse_id']);
            $table->index(['low_stock_snapshot_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('low_stock_snapshot_items');
        Schema::dropIfExists('low_stock_snapshots');
    }
};
