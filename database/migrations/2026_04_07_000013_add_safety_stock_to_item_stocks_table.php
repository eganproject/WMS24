<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('item_stocks', function (Blueprint $table) {
            if (!Schema::hasColumn('item_stocks', 'safety_stock')) {
                $table->integer('safety_stock')->nullable()->after('stock');
            }
        });
    }

    public function down(): void
    {
        Schema::table('item_stocks', function (Blueprint $table) {
            if (Schema::hasColumn('item_stocks', 'safety_stock')) {
                $table->dropColumn('safety_stock');
            }
        });
    }
};
