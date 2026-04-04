<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockInOrderItemDistribution extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'stock_in_order_item_id',
        'to_warehouse_id',
        'quantity',
        'koli',
        'note',
    ];

    public function item()
    {
        return $this->belongsTo(StockInOrderItem::class, 'stock_in_order_item_id');
    }

    public function toWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }
}
