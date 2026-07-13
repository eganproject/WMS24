<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('inbound_items', 'input_unit')) {
            Schema::table('inbound_items', function (Blueprint $table) {
                $table->string('input_unit', 10)->default('koli')->after('koli');
            });
        }

        if (!Schema::hasColumn('inbound_scan_session_items', 'input_unit')) {
            Schema::table('inbound_scan_session_items', function (Blueprint $table) {
                $table->string('input_unit', 10)->default('koli')->after('item_name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('inbound_scan_session_items', 'input_unit')) {
            Schema::table('inbound_scan_session_items', function (Blueprint $table) {
                $table->dropColumn('input_unit');
            });
        }

        if (Schema::hasColumn('inbound_items', 'input_unit')) {
            Schema::table('inbound_items', function (Blueprint $table) {
                $table->dropColumn('input_unit');
            });
        }
    }
};
