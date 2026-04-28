<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkShift extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'start_time',
        'end_time',
        'break_start_time',
        'break_end_time',
        'late_tolerance_minutes',
        'checkout_tolerance_minutes',
        'overtime_start_after_minutes',
        'minimum_overtime_minutes',
        'crosses_midnight',
        'is_active',
    ];

    protected $casts = [
        'crosses_midnight' => 'boolean',
        'is_active' => 'boolean',
    ];
}
