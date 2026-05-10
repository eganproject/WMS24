<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QcResiScanSubstitution extends Model
{
    use HasFactory;

    protected $fillable = [
        'qc_resi_scan_id',
        'original_sku',
        'replacement_sku',
        'qty',
        'reason',
        'buyer_note_snapshot',
        'created_by',
    ];

    protected $casts = [
        'qty' => 'integer',
    ];

    public function qcScan()
    {
        return $this->belongsTo(QcResiScan::class, 'qc_resi_scan_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
