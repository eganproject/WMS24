<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QcResiScan extends Model
{
    use HasFactory;

    protected $fillable = [
        'resi_id',
        'scan_type',
        'scan_code',
        'status',
        'started_at',
        'completed_at',
        'scanned_by',
        'completed_by',
        'last_scanned_by',
        'last_scanned_at',
        'reset_count',
        'reset_by',
        'reset_at',
        'reset_reason',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'last_scanned_at' => 'datetime',
        'reset_at' => 'datetime',
    ];

    public function resi()
    {
        return $this->belongsTo(Resi::class, 'resi_id');
    }

    public function scanner()
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }

    public function completer()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function lastScanner()
    {
        return $this->belongsTo(User::class, 'last_scanned_by');
    }

    public function resetter()
    {
        return $this->belongsTo(User::class, 'reset_by');
    }

    public function items()
    {
        return $this->hasMany(QcResiScanItem::class, 'qc_resi_scan_id');
    }
}
