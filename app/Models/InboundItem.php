<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InboundItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'inbound_transaction_id',
        'item_id',
        'qty',
        'koli',
        'note',
    ];

    protected $casts = [
        'qty' => 'integer',
        'koli' => 'integer',
    ];

    public function transaction()
    {
        return $this->belongsTo(InboundTransaction::class, 'inbound_transaction_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
}
