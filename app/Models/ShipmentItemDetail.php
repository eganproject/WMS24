<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentItemDetail extends Model
{
    public const REFERENCE_TYPE_STOCK_IN_ORDER_ITEM = 'stock_in_order_items';
    public const REFERENCE_TYPE_TRANSFER_REQUEST_ITEM = 'transfer_request_items';

    protected $fillable = [
        'shipment_id',
        'item_id',
        'reference_id',
        'reference_type',
        'quantity_shipped',
        'koli_shipped',
        'description',
    ];

    protected $casts = [
        'quantity_shipped' => 'float',
        'koli_shipped' => 'float',
    ];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}

