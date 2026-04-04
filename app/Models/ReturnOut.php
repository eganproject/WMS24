<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnOut extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'code',
        'warehouse_id',
        'destination_warehouse_id',
        'return_date',
        'status',
        'description',
        'sent_by',
        'verified_by',
        'completed_at',
    ];

    protected $casts = [
        'return_date' => 'date',
        'completed_at' => 'datetime',
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function destinationWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
    }

    public function goodsReceipt()
    {
        // Column 'goods_receipt_id' was removed from the migration.
        // Keep stub for compatibility; do not attempt to eager-load.
        return null;
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function details()
    {
        return $this->hasMany(ReturnOutDetail::class, 'return_out_id');
    }

    // Asal dokumen (transfer request atau pengadaan) dapat ditelusuri via goods receipt -> shipment
    public function originType(): ?string
    {
        // No goods_receipt_id stored on return_outs in current migrations.
        return null;
    }

    public function originId(): ?int
    {
        // No goods_receipt_id stored on return_outs in current migrations.
        return null;
    }
}
