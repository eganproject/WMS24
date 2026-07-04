<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScanOutFailedAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'resi_id',
        'qc_resi_scan_id',
        'shipment_scan_out_id',
        'scan_type',
        'scan_code',
        'reason_code',
        'message',
        'resi_status',
        'qc_status',
        'qc_completed_at',
        'existing_scanned_at',
        'attempted_by',
        'attempted_at',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'qc_completed_at' => 'datetime',
        'existing_scanned_at' => 'datetime',
        'attempted_at' => 'datetime',
    ];

    public function resi()
    {
        return $this->belongsTo(Resi::class, 'resi_id');
    }

    public function qcScan()
    {
        return $this->belongsTo(QcResiScan::class, 'qc_resi_scan_id');
    }

    public function scanOut()
    {
        return $this->belongsTo(ShipmentScanOut::class, 'shipment_scan_out_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'attempted_by');
    }
}
