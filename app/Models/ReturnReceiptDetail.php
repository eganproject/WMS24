<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnReceiptDetail extends Model
{
    protected $fillable = [
        'return_receipt_id',
        'item_id',
        'quantity',
        'koli',
        'accepted_quantity',
        'accepted_koli',
        'rejected_quantity',
        'rejected_koli',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'accepted_quantity' => 'integer',
        'rejected_quantity' => 'integer',
        'koli' => 'float',
        'accepted_koli' => 'float',
        'rejected_koli' => 'float',
    ];

    public function returnReceipt()
    {
        return $this->belongsTo(ReturnReceipt::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}

