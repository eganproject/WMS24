<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    public const REFERENCE_TYPE_TRANSFER_REQUEST = 'transfer request';
    public const REFERENCE_TYPE_STOCK_IN_ORDER = 'stock in order';

    protected $fillable = [
        'code',
        'reference_id',
        'reference_type',
        'shipping_date',
        'vehicle_type',
        'license_plate',
        'driver_name',
        'driver_contact',
        'description',
        'status',
        'shipped_by',
    ];

    public function transferRequest()
    {
        return $this->belongsTo(TransferRequest::class, 'reference_id')
            ->where('reference_type', self::REFERENCE_TYPE_TRANSFER_REQUEST);
    }

    public function goodsReceipts()
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    public function itemDetails()
    {
        return $this->hasMany(ShipmentItemDetail::class, 'shipment_id');
    }
}
