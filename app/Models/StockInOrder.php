<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockInOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'date',
        'warehouse_id',
        'type',
        'from_warehouse_id',
        'status',
        'description',
        'requested_at',
        'requested_by',
        'approved_by',
        'on_progress_at',
        'completed_at',
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function fromWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function items()
    {
        return $this->hasMany(StockInOrderItem::class);
    }
}
