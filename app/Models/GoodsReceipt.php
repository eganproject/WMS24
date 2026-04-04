<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoodsReceipt extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_COMPLETED = 'completed';

    // Keep reference type constants for controller logic, even though
    // reference columns are no longer stored in this table.
    public const REFERENCE_TYPE_TRANSFER_REQUEST = 'transfer request';
    public const REFERENCE_TYPE_STOCK_IN_ORDER = 'stock in order';

    protected $fillable = [
        'code',
        'type',
        'shipment_id',
        'warehouse_id',
        'receipt_date',
        'status',
        'description',
        'received_by',
        'verified_by',
        'completed_at',
    ];

    protected $casts = [
        'receipt_date' => 'date',
        'completed_at' => 'datetime',
    ];

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function details()
    {
        return $this->hasMany(GoodsReceiptDetail::class);
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
