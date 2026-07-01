<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customer_return_items', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_return_items', 'root_cause')) {
                $table->string('root_cause', 50)->nullable()->after('damaged_qty')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_return_items', function (Blueprint $table) {
            if (Schema::hasColumn('customer_return_items', 'root_cause')) {
                $table->dropIndex(['root_cause']);
                $table->dropColumn('root_cause');
            }
        });
    }
};
