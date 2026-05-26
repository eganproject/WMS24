<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customer_returns', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_returns', 'item_image_path')) {
                $table->string('item_image_path')->nullable()->after('note');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_returns', function (Blueprint $table) {
            if (Schema::hasColumn('customer_returns', 'item_image_path')) {
                $table->dropColumn('item_image_path');
            }
        });
    }
};
