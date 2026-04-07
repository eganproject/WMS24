<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'from_warehouse_id',
        'to_warehouse_id',
        'transacted_at',
        'note',
        'status',
        'qc_at',
        'qc_by',
        'created_by',
    ];

    protected $casts = [
        'transacted_at' => 'datetime',
        'qc_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(StockTransferItem::class, 'stock_transfer_id');
    }

    public function fromWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function qcBy()
    {
        return $this->belongsTo(User::class, 'qc_by');
    }
}
