<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InboundScanSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'inbound_transaction_id',
        'started_by',
        'started_at',
        'last_scanned_by',
        'last_scanned_at',
        'completed_by',
        'completed_at',
        'reset_count',
        'reset_by',
        'reset_at',
        'reset_reason',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'last_scanned_at' => 'datetime',
        'completed_at' => 'datetime',
        'reset_at' => 'datetime',
        'reset_count' => 'integer',
    ];

    public function transaction()
    {
        return $this->belongsTo(InboundTransaction::class, 'inbound_transaction_id');
    }

    public function starter()
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function lastScanner()
    {
        return $this->belongsTo(User::class, 'last_scanned_by');
    }

    public function completer()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function resetter()
    {
        return $this->belongsTo(User::class, 'reset_by');
    }

    public function items()
    {
        return $this->hasMany(InboundScanSessionItem::class, 'inbound_scan_session_id');
    }
}
