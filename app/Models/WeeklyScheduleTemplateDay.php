<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WeeklyScheduleTemplateDay extends Model
{
    use HasFactory;

    protected $fillable = [
        'weekly_schedule_template_id',
        'day_of_week',
        'work_shift_id',
        'schedule_type',
    ];

    public function template()
    {
        return $this->belongsTo(WeeklyScheduleTemplate::class, 'weekly_schedule_template_id');
    }

    public function shift()
    {
        return $this->belongsTo(WorkShift::class, 'work_shift_id');
    }
}
