<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransferRequestItem extends Model
{
    protected $table = 'transfer_request_items';
    protected $fillable = [
        'transfer_request_id',
        'item_id',
        'quantity',
        'koli',
        'remaining_quantity',
        'remaining_koli',
        'description',
    ];

    public function transferRequest()
    {
        return $this->belongsTo(TransferRequest::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
