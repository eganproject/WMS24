<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LowStockSnapshotItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'low_stock_snapshot_id',
        'item_id',
        'warehouse_id',
        'sku',
        'name',
        'warehouse',
        'category',
        'address',
        'stock',
        'safety_stock',
        'gap',
        'status',
        'resolution_status',
        'resolved_at',
        'resolved_snapshot_id',
        'resolved_stock',
        'resolved_safety_stock',
        'safety_source',
    ];

    protected $casts = [
        'stock' => 'integer',
        'safety_stock' => 'integer',
        'gap' => 'integer',
        'resolved_at' => 'datetime',
        'resolved_stock' => 'integer',
        'resolved_safety_stock' => 'integer',
    ];

    public function snapshot()
    {
        return $this->belongsTo(LowStockSnapshot::class, 'low_stock_snapshot_id');
    }

    public function resolvedSnapshot()
    {
        return $this->belongsTo(LowStockSnapshot::class, 'resolved_snapshot_id');
    }
}
