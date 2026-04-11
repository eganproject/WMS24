<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QcResiScanItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'qc_resi_scan_id',
        'sku',
        'expected_qty',
        'scanned_qty',
    ];

    public function qcScan()
    {
        return $this->belongsTo(QcResiScan::class, 'qc_resi_scan_id');
    }
}
