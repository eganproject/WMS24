<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockOpname extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'warehouse_id',
        'description',
        'started_by',
        'completed_by',
        'start_date',
        'completed_date',
        'status',
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function startedBy()
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function items()
    {
        return $this->hasMany(StockOpnameItem::class);
    }
}
