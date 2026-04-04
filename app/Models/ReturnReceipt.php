<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnReceipt extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'code',
        'warehouse_id',
        'return_date',
        'status',
        'description',
        'received_by',
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

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function details()
    {
        return $this->hasMany(ReturnReceiptDetail::class);
    }
}

