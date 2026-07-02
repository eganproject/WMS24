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
        'safety_source',
    ];

    protected $casts = [
        'stock' => 'integer',
        'safety_stock' => 'integer',
        'gap' => 'integer',
    ];

    public function snapshot()
    {
        return $this->belongsTo(LowStockSnapshot::class, 'low_stock_snapshot_id');
    }
}
