<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('item_barcode_scan_misses', function (Blueprint $table) {
            $table->id();
            $table->string('context', 50);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_code', 100)->nullable();
            $table->text('scan_code');
            $table->text('normalized_code');
            $table->char('normalized_hash', 64);
            $table->unsignedInteger('scan_count')->default(1);
            $table->timestamp('last_scanned_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_item_id')->nullable()->constrained('items')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['context', 'normalized_hash']);
            $table->index('resolved_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_barcode_scan_misses');
    }
};
