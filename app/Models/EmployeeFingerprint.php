<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeFingerprint extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'attendance_device_id',
        'device_user_id',
        'fingerprint_uid',
        'is_active',
        'enrolled_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'enrolled_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function device()
    {
        return $this->belongsTo(AttendanceDevice::class, 'attendance_device_id');
    }
}
