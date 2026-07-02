<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customer_return_items', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_return_items', 'packaging_damaged_qty')) {
                $table->integer('packaging_damaged_qty')->default(0)->after('good_qty');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_return_items', function (Blueprint $table) {
            if (Schema::hasColumn('customer_return_items', 'packaging_damaged_qty')) {
                $table->dropColumn('packaging_damaged_qty');
            }
        });
    }
};
