<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KpiScoreSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'period_start',
        'period_end',
        'status',
        'note',
        'created_by',
        'locked_by',
        'locked_at',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'locked_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(KpiScoreItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
