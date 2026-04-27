<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeScheduleAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'weekly_schedule_template_id',
        'effective_from',
        'effective_until',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_until' => 'date',
    ];
}
