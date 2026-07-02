<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LowStockSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'snapshot_at',
        'scope',
        'warehouse_id',
        'warehouse_name',
        'total_low',
        'total_out_of_stock',
        'total_gap',
        'source',
        'created_by',
    ];

    protected $casts = [
        'snapshot_at' => 'datetime',
        'total_low' => 'integer',
        'total_out_of_stock' => 'integer',
        'total_gap' => 'integer',
    ];

    public function items()
    {
        return $this->hasMany(LowStockSnapshotItem::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
