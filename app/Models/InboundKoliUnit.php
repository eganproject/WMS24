<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InboundKoliUnit extends Model
{
    use HasFactory;

    public const STATUS_AVAILABLE = 'available';
    public const STATUS_RESERVED = 'reserved';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_NOT_RECEIVED = 'not_received';

    protected $fillable = [
        'code',
        'inbound_transaction_id',
        'inbound_item_id',
        'item_id',
        'sku',
        'koli_no',
        'qty_per_koli',
        'qty',
        'status',
        'reserved_transfer_id',
    ];

    protected $casts = [
        'koli_no' => 'integer',
        'qty_per_koli' => 'integer',
        'qty' => 'integer',
    ];

    public function transaction()
    {
        return $this->belongsTo(InboundTransaction::class, 'inbound_transaction_id');
    }

    public function inboundItem()
    {
        return $this->belongsTo(InboundItem::class, 'inbound_item_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function transferScan()
    {
        return $this->hasOne(StockTransferKoliScan::class, 'inbound_koli_unit_id');
    }
}
