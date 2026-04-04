<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoodsReceiptDetail extends Model
{
    protected $fillable = [
        'goods_receipt_id',
        'shipment_item_id',
        'item_id',
        'ordered_quantity',
        'ordered_koli',
        'received_quantity',
        'received_koli',
        'accepted_quantity',
        'accepted_koli',
        'rejected_quantity',
        'rejected_koli',
        'notes',
    ];

    protected $casts = [
        'ordered_quantity' => 'float',
        'ordered_koli' => 'float',
        'received_quantity' => 'float',
        'received_koli' => 'float',
        'accepted_quantity' => 'float',
        'accepted_koli' => 'float',
        'rejected_quantity' => 'float',
        'rejected_koli' => 'float',
    ];

    public function goodsReceipt()
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function shipmentItem()
    {
        return $this->belongsTo(ShipmentItemDetail::class, 'shipment_item_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
