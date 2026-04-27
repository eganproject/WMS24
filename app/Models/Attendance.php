<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    public const STATUS_PRESENT = 'present';
    public const STATUS_LATE = 'late';
    public const STATUS_ABSENT = 'absent';
    public const STATUS_LEAVE = 'leave';
    public const STATUS_HOLIDAY = 'holiday';
    public const STATUS_DAY_OFF = 'day_off';
    public const STATUS_INCOMPLETE = 'incomplete';

    protected $fillable = [
        'employee_id',
        'attendance_date',
        'work_shift_id',
        'check_in_at',
        'check_out_at',
        'late_minutes',
        'early_leave_minutes',
        'work_minutes',
        'overtime_minutes',
        'status',
        'note',
        'source',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'check_in_at' => 'datetime',
        'check_out_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift()
    {
        return $this->belongsTo(WorkShift::class, 'work_shift_id');
    }
}
