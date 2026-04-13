<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InboundTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'type',
        'ref_no',
        'surat_jalan_no',
        'surat_jalan_at',
        'transacted_at',
        'note',
        'warehouse_id',
        'status',
        'approved_at',
        'created_by',
        'approved_by',
    ];

    protected $casts = [
        'surat_jalan_at' => 'datetime',
        'transacted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(InboundItem::class, 'inbound_transaction_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scanSession()
    {
        return $this->hasOne(InboundScanSession::class, 'inbound_transaction_id');
    }
}
