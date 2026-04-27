<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'user_id',
        'area_id',
        'position_id',
        'employee_code',
        'name',
        'phone',
        'position',
        'join_date',
        'employment_status',
    ];

    protected $casts = [
        'join_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    public function positionRelation()
    {
        return $this->belongsTo(EmployeePosition::class, 'position_id');
    }

    public function fingerprints()
    {
        return $this->hasMany(EmployeeFingerprint::class);
    }

    public function schedules()
    {
        return $this->hasMany(EmployeeSchedule::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }
}
