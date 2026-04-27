<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeSchedule extends Model
{
    use HasFactory;

    public const TYPE_WORK = 'work';
    public const TYPE_DAY_OFF = 'day_off';
    public const TYPE_HOLIDAY = 'holiday';
    public const TYPE_LEAVE = 'leave';

    protected $fillable = [
        'employee_id',
        'work_shift_id',
        'schedule_date',
        'schedule_type',
        'note',
        'created_by',
    ];

    protected $casts = [
        'schedule_date' => 'date',
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
