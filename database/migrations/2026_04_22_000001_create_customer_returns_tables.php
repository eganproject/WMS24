<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customer_returns', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->foreignId('resi_id')->nullable()->constrained('resis')->nullOnDelete();
            $table->foreignId('damaged_good_id')->nullable()->constrained('damaged_goods')->nullOnDelete();
            $table->string('resi_no', 100);
            $table->string('order_ref', 100)->nullable();
            $table->timestamp('received_at')->useCurrent();
            $table->timestamp('inspected_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->string('status', 20)->default('inspected');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('inspected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'received_at']);
            $table->index('resi_no');
        });

        Schema::create('customer_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_return_id')->constrained('customer_returns')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->integer('expected_qty')->default(0);
            $table->integer('received_qty')->default(0);
            $table->integer('good_qty')->default(0);
            $table->integer('damaged_qty')->default(0);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['customer_return_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_return_items');
        Schema::dropIfExists('customer_returns');
    }
};
