<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Adjustment extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'adjustment_date',
        'warehouse_id',
        'user_id',
        'notes',
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function adjustmentItems()
    {
        return $this->hasMany(AdjustmentItem::class);
    }
}