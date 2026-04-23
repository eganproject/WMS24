<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('damaged_good_items', function (Blueprint $table) {
            if (!Schema::hasColumn('damaged_good_items', 'reason_code')) {
                $table->string('reason_code', 40)
                    ->nullable()
                    ->after('qty');
            }
        });

        if (!Schema::hasColumn('damaged_good_items', 'reason_code') || !Schema::hasTable('damaged_goods')) {
            return;
        }

        DB::table('damaged_good_items')
            ->join('damaged_goods', 'damaged_goods.id', '=', 'damaged_good_items.damaged_good_id')
            ->whereNull('damaged_good_items.reason_code')
            ->where('damaged_goods.source_type', 'customer_return')
            ->update(['damaged_good_items.reason_code' => 'customer_return']);

        DB::table('damaged_good_items')
            ->whereNull('reason_code')
            ->update(['reason_code' => 'other']);
    }

    public function down(): void
    {
        Schema::table('damaged_good_items', function (Blueprint $table) {
            if (Schema::hasColumn('damaged_good_items', 'reason_code')) {
                $table->dropColumn('reason_code');
            }
        });
    }
};
