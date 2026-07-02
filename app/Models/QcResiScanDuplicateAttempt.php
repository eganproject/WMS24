<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QcResiScanDuplicateAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'qc_resi_scan_id',
        'resi_id',
        'scan_type',
        'scan_code',
        'existing_status',
        'qc_completed_at',
        'qc_completed_by',
        'scanned_by',
        'scanned_at',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'qc_completed_at' => 'datetime',
        'scanned_at' => 'datetime',
    ];

    public function qcScan()
    {
        return $this->belongsTo(QcResiScan::class, 'qc_resi_scan_id');
    }

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
        return $this->belongsTo(User::class, 'qc_completed_by');
    }
}
