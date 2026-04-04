<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnOutDetail extends Model
{
    protected $fillable = [
        'return_out_id',
        'item_id',
        'quantity',
        'koli',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'float',
        'koli' => 'float',
    ];

    public function returnOut()
    {
        return $this->belongsTo(ReturnOut::class, 'return_out_id');
    }

    public function goodsReceiptDetail()
    {
        // Column 'goods_receipt_detail_id' removed from migration. No relation available.
        return null;
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
