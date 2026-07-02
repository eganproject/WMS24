<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            if (!Schema::hasColumn('items', 'status')) {
                $table->string('status', 20)->default('active')->after('item_type')->index();
            }
        });

        Schema::table('item_stocks', function (Blueprint $table) {
            if (!Schema::hasColumn('item_stocks', 'is_stock_monitored')) {
                $table->boolean('is_stock_monitored')->default(true)->after('safety_stock')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('item_stocks', function (Blueprint $table) {
            if (Schema::hasColumn('item_stocks', 'is_stock_monitored')) {
                $table->dropColumn('is_stock_monitored');
            }
        });

        Schema::table('items', function (Blueprint $table) {
            if (Schema::hasColumn('items', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
