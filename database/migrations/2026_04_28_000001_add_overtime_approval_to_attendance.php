<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_shifts', function (Blueprint $table) {
            $table->unsignedInteger('overtime_start_after_minutes')->default(0)->after('checkout_tolerance_minutes');
            $table->unsignedInteger('minimum_overtime_minutes')->default(0)->after('overtime_start_after_minutes');
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->unsignedInteger('calculated_overtime_minutes')->default(0)->after('overtime_minutes');
            $table->unsignedInteger('approved_overtime_minutes')->nullable()->after('calculated_overtime_minutes');
            $table->string('overtime_status', 30)->default('none')->after('approved_overtime_minutes');
            $table->text('overtime_note')->nullable()->after('overtime_status');
            $table->index(['attendance_date', 'overtime_status'], 'attendances_overtime_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex('attendances_overtime_status_index');
            $table->dropColumn([
                'calculated_overtime_minutes',
                'approved_overtime_minutes',
                'overtime_status',
                'overtime_note',
            ]);
        });

        Schema::table('work_shifts', function (Blueprint $table) {
            $table->dropColumn([
                'overtime_start_after_minutes',
                'minimum_overtime_minutes',
            ]);
        });
    }
};
