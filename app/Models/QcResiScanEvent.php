<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QcResiScanEvent extends Model
{
    use HasFactory;

    public const TYPE_WRONG_SKU = 'wrong_sku';
    public const TYPE_UNKNOWN_BARCODE = 'unknown_barcode';
    public const TYPE_OVER_QTY = 'over_qty';
    public const TYPE_HOLD = 'hold';
    public const TYPE_RESET = 'reset';
    public const TYPE_SUBSTITUTION = 'substitution';

    protected $fillable = [
        'qc_resi_scan_id',
        'resi_id',
        'picker_employee_id',
        'event_type',
        'scan_code',
        'sku',
        'expected_sku',
        'qty',
        'expected_qty',
        'scanned_qty',
        'reason_code',
        'reason',
        'meta',
        'created_by',
        'occurred_at',
    ];

    protected $casts = [
        'qty' => 'integer',
        'expected_qty' => 'integer',
        'scanned_qty' => 'integer',
        'meta' => 'array',
        'occurred_at' => 'datetime',
    ];

    public static function typeLabels(): array
    {
        return [
            self::TYPE_WRONG_SKU => 'Salah Ambil SKU',
            self::TYPE_UNKNOWN_BARCODE => 'Barcode Tidak Dikenal',
            self::TYPE_OVER_QTY => 'Qty Berlebih',
            self::TYPE_HOLD => 'QC Ditunda',
            self::TYPE_RESET => 'QC Direset',
            self::TYPE_SUBSTITUTION => 'Substitusi SKU',
        ];
    }

    public function qcScan()
    {
        return $this->belongsTo(QcResiScan::class, 'qc_resi_scan_id');
    }

    public function resi()
    {
        return $this->belongsTo(Resi::class, 'resi_id');
    }

    public function pickerEmployee()
    {
        return $this->belongsTo(Employee::class, 'picker_employee_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
