<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerReturnItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_return_id',
        'item_id',
        'expected_qty',
        'received_qty',
        'good_qty',
        'damaged_qty',
        'note',
    ];

    protected $casts = [
        'expected_qty' => 'integer',
        'received_qty' => 'integer',
        'good_qty' => 'integer',
        'damaged_qty' => 'integer',
    ];

    public function customerReturn()
    {
        return $this->belongsTo(CustomerReturn::class, 'customer_return_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
}
