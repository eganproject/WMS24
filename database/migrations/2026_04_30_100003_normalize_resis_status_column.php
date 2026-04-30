<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Ensure all rows have a valid status value before making it NOT NULL
        DB::table('resis')->whereNull('status')->update(['status' => 'active']);
        DB::table('resis')->whereNotIn('status', ['active', 'canceled'])->update(['status' => 'active']);

        try {
            Schema::table('resis', function (Blueprint $table) {
                $table->string('status', 20)->default('active')->nullable(false)->change();
            });
        } catch (\Throwable) {
            // ignore on drivers that don't support column change
        }
    }

    public function down(): void
    {
        try {
            Schema::table('resis', function (Blueprint $table) {
                $table->string('status', 20)->default('active')->nullable()->change();
            });
        } catch (\Throwable) {
            // ignore
        }
    }
};
