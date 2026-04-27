<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    use HasFactory;

    protected $fillable = [
        'holiday_date',
        'name',
        'type',
        'is_paid',
    ];

    protected $casts = [
        'holiday_date' => 'date',
        'is_paid' => 'boolean',
    ];
}
