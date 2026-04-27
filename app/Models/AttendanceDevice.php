<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'serial_number',
        'ip_address',
        'port',
        'location',
        'device_type',
        'is_active',
        'last_synced_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    public function fingerprints()
    {
        return $this->hasMany(EmployeeFingerprint::class);
    }

    public function rawLogs()
    {
        return $this->hasMany(AttendanceRawLog::class);
    }
}
