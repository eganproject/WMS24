<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceRawLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_device_id',
        'employee_id',
        'device_user_id',
        'scan_at',
        'verify_type',
        'state',
        'raw_payload',
        'synced_at',
    ];

    protected $casts = [
        'scan_at' => 'datetime',
        'raw_payload' => 'array',
        'synced_at' => 'datetime',
    ];

    public function device()
    {
        return $this->belongsTo(AttendanceDevice::class, 'attendance_device_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
