<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->foreignId('area_id')->nullable()->constrained('areas')->nullOnDelete();
            $table->string('employee_code', 50)->unique();
            $table->string('name', 150);
            $table->string('phone', 50)->nullable();
            $table->string('position', 100)->nullable();
            $table->date('join_date')->nullable();
            $table->string('employment_status', 30)->default('active');
            $table->timestamps();
        });

        Schema::create('attendance_devices', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('serial_number', 100)->nullable()->unique();
            $table->string('ip_address', 45)->nullable();
            $table->unsignedInteger('port')->default(4370);
            $table->string('location', 150)->nullable();
            $table->string('device_type', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_fingerprints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('attendance_device_id')->nullable()->constrained('attendance_devices')->nullOnDelete();
            $table->string('device_user_id', 100);
            $table->string('fingerprint_uid', 150)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('enrolled_at')->nullable();
            $table->timestamps();
            $table->unique(['attendance_device_id', 'device_user_id'], 'employee_fingerprints_device_user_unique');
        });

        Schema::create('work_shifts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->time('start_time');
            $table->time('end_time');
            $table->time('break_start_time')->nullable();
            $table->time('break_end_time')->nullable();
            $table->unsignedInteger('late_tolerance_minutes')->default(0);
            $table->unsignedInteger('checkout_tolerance_minutes')->default(0);
            $table->boolean('crosses_midnight')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('weekly_schedule_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('weekly_schedule_template_days', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('weekly_schedule_template_id');
            $table->unsignedTinyInteger('day_of_week');
            $table->foreignId('work_shift_id')->nullable()->constrained('work_shifts')->nullOnDelete();
            $table->string('schedule_type', 30)->default('work');
            $table->timestamps();
            $table->foreign('weekly_schedule_template_id', 'wstd_template_fk')
                ->references('id')
                ->on('weekly_schedule_templates')
                ->cascadeOnDelete();
            $table->unique(['weekly_schedule_template_id', 'day_of_week'], 'weekly_template_day_unique');
        });

        Schema::create('employee_schedule_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->unsignedBigInteger('weekly_schedule_template_id');
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->timestamps();
            $table->foreign('weekly_schedule_template_id', 'esa_template_fk')
                ->references('id')
                ->on('weekly_schedule_templates')
                ->cascadeOnDelete();
            $table->index(['employee_id', 'effective_from', 'effective_until'], 'employee_schedule_assignments_lookup_index');
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('holiday_date')->unique();
            $table->string('name', 150);
            $table->string('type', 30)->default('company');
            $table->boolean('is_paid')->default(true);
            $table->timestamps();
        });

        Schema::create('employee_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('work_shift_id')->nullable()->constrained('work_shifts')->nullOnDelete();
            $table->date('schedule_date');
            $table->string('schedule_type', 30)->default('work');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['employee_id', 'schedule_date']);
        });

        Schema::create('employee_leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('leave_type', 30);
            $table->date('start_date');
            $table->date('end_date');
            $table->text('reason')->nullable();
            $table->string('status', 30)->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'start_date', 'end_date']);
        });

        Schema::create('attendance_raw_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_device_id')->constrained('attendance_devices')->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('device_user_id', 100);
            $table->dateTime('scan_at');
            $table->string('verify_type', 50)->nullable();
            $table->string('state', 50)->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->unique(['attendance_device_id', 'device_user_id', 'scan_at'], 'attendance_raw_logs_unique_scan');
            $table->index(['employee_id', 'scan_at']);
            $table->index('scan_at');
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('attendance_date');
            $table->foreignId('work_shift_id')->nullable()->constrained('work_shifts')->nullOnDelete();
            $table->dateTime('check_in_at')->nullable();
            $table->dateTime('check_out_at')->nullable();
            $table->unsignedInteger('late_minutes')->default(0);
            $table->unsignedInteger('early_leave_minutes')->default(0);
            $table->unsignedInteger('work_minutes')->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->string('status', 30)->default('absent');
            $table->text('note')->nullable();
            $table->string('source', 30)->default('system');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'attendance_date']);
            $table->index(['attendance_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('attendance_raw_logs');
        Schema::dropIfExists('employee_leaves');
        Schema::dropIfExists('employee_schedules');
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('employee_schedule_assignments');
        Schema::dropIfExists('weekly_schedule_template_days');
        Schema::dropIfExists('weekly_schedule_templates');
        Schema::dropIfExists('work_shifts');
        Schema::dropIfExists('employee_fingerprints');
        Schema::dropIfExists('attendance_devices');
        Schema::dropIfExists('employees');
    }
};
