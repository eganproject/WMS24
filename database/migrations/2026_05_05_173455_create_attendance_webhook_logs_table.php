<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address', 45)->nullable();
            $table->string('serial_number', 100)->nullable();
            $table->foreignId('attendance_device_id')->nullable()->constrained('attendance_devices')->nullOnDelete();
            $table->string('device_user_id', 100)->nullable();
            $table->json('request_payload')->nullable();
            $table->smallInteger('http_status')->nullable();
            $table->json('response_payload')->nullable();
            // success | unauthorized | device_not_found | validation_error | error
            $table->string('status', 30)->default('success');
            $table->foreignId('raw_log_id')->nullable()->constrained('attendance_raw_logs')->nullOnDelete();
            $table->timestamps();

            $table->index('created_at');
            $table->index('attendance_device_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_webhook_logs');
    }
};
