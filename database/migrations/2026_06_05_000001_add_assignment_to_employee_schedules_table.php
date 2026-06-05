<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_schedules', function (Blueprint $table) {
            $table->foreignId('employee_schedule_assignment_id')
                ->nullable()
                ->after('employee_id')
                ->constrained('employee_schedule_assignments')
                ->nullOnDelete();

            $table->index(
                ['employee_schedule_assignment_id', 'schedule_date'],
                'employee_schedules_assignment_date_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('employee_schedules', function (Blueprint $table) {
            $table->dropIndex('employee_schedules_assignment_date_index');
            $table->dropConstrainedForeignId('employee_schedule_assignment_id');
        });
    }
};
