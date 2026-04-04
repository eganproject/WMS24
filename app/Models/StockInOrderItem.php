<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockInOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_in_order_id',
        'item_id',
        'quantity',
        'koli',
        'remaining_quantity',
        'remaining_koli',
        'status',
        'description',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function distributions()
    {
        return $this->hasMany(StockInOrderItemDistribution::class, 'stock_in_order_item_id');
    }
}
