<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customer_returns', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_returns', 'resi_source')) {
                $table->string('resi_source', 20)->nullable()->after('resi_no');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_returns', function (Blueprint $table) {
            if (Schema::hasColumn('customer_returns', 'resi_source')) {
                $table->dropColumn('resi_source');
            }
        });
    }
};
