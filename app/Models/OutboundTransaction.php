<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OutboundTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'type',
        'ref_no',
        'supplier_id',
        'transacted_at',
        'note',
        'warehouse_id',
        'status',
        'approved_at',
        'created_by',
        'approved_by',
    ];

    protected $casts = [
        'transacted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(OutboundItem::class, 'outbound_transaction_id');
    }

    public function qcSession()
    {
        return $this->hasOne(OutboundQcSession::class, 'outbound_transaction_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
