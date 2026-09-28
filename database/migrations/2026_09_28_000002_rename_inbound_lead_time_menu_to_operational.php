<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('menus')) {
            return;
        }

        DB::table('menus')
            ->where('slug', 'report-inbound-lead-time')
            ->where('name', 'Lead Time Inbound')
            ->update(['name' => 'Lead Time Operasional', 'updated_at' => now()]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('menus')) {
            return;
        }

        DB::table('menus')
            ->where('slug', 'report-inbound-lead-time')
            ->where('name', 'Lead Time Operasional')
            ->update(['name' => 'Lead Time Inbound', 'updated_at' => now()]);
    }
};
