<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $table = 'stock_movements';

    protected $fillable = [
        'item_id',
        'warehouse_id',
        'date',
        'quantity',
        'koli',
        'stock_before',
        'stock_after',
        'type',
        'description',
        'user_id',
        'reference_id',
        'reference_type',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
