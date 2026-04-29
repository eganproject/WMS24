<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('outbound_qc_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outbound_transaction_id')->unique()->constrained('outbound_transactions')->cascadeOnDelete();
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->foreignId('last_scanned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_scanned_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('reset_count')->default(0);
            $table->foreignId('reset_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reset_at')->nullable();
            $table->text('reset_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('outbound_qc_session_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outbound_qc_session_id')->constrained('outbound_qc_sessions')->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('items')->nullOnDelete();
            $table->string('sku', 100);
            $table->string('item_name', 150)->nullable();
            $table->unsignedInteger('expected_qty');
            $table->unsignedInteger('scanned_qty')->default(0);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['outbound_qc_session_id', 'item_id'], 'uq_out_qc_sess_item');
            $table->index('sku');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbound_qc_session_items');
        Schema::dropIfExists('outbound_qc_sessions');
    }
};
