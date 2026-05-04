<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('outbound_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('outbound_transactions', 'surat_jalan_no')) {
                $table->string('surat_jalan_no', 100)->nullable()->after('ref_no');
            }

            if (!Schema::hasColumn('outbound_transactions', 'surat_jalan_at')) {
                $table->timestamp('surat_jalan_at')->nullable()->after('surat_jalan_no');
            }
        });
    }

    public function down(): void
    {
        Schema::table('outbound_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('outbound_transactions', 'surat_jalan_at')) {
                $table->dropColumn('surat_jalan_at');
            }

            if (Schema::hasColumn('outbound_transactions', 'surat_jalan_no')) {
                $table->dropColumn('surat_jalan_no');
            }
        });
    }
};
