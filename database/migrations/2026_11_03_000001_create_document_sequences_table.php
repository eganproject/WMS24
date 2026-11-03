<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('document_sequences')) {
            return;
        }

        // Counter per prefix+periode, dipakai untuk nomor dokumen berurutan
        // (mis. nomor surat jalan outbound manual: SJM-261103-0001).
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('sequence_key', 100)->unique();
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};
