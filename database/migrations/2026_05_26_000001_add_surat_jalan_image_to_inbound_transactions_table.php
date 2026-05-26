<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('inbound_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('inbound_transactions', 'surat_jalan_image_path')) {
                $table->string('surat_jalan_image_path')->nullable()->after('surat_jalan_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inbound_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('inbound_transactions', 'surat_jalan_image_path')) {
                $table->dropColumn('surat_jalan_image_path');
            }
        });
    }
};
