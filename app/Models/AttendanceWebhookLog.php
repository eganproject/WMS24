<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceWebhookLog extends Model
{
    protected $fillable = [
        'ip_address',
        'serial_number',
        'attendance_device_id',
        'device_user_id',
        'request_payload',
        'http_status',
        'response_payload',
        'status',
        'raw_log_id',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
    ];

    public function device()
    {
        return $this->belongsTo(AttendanceDevice::class, 'attendance_device_id');
    }

    public function rawLog()
    {
        return $this->belongsTo(AttendanceRawLog::class, 'raw_log_id');
    }

    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }
}
