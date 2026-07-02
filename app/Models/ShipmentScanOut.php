<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShipmentScanOut extends Model
{
    use HasFactory;

    protected $table = 'shipment_scan_outs';

    protected $fillable = [
        'resi_id',
        'kurir_id',
        'scan_type',
        'scan_code',
        'scan_date',
        'scanned_at',
        'scanned_by',
        'packed_employee_id',
        'packed_at',
        'packing_confirmed_by',
    ];

    protected $casts = [
        'scan_date' => 'date',
        'scanned_at' => 'datetime',
        'packed_at' => 'datetime',
    ];

    public function resi()
    {
        return $this->belongsTo(Resi::class, 'resi_id');
    }

    public function scanner()
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }

    public function packedEmployee()
    {
        return $this->belongsTo(Employee::class, 'packed_employee_id');
    }

    public function packingConfirmer()
    {
        return $this->belongsTo(User::class, 'packing_confirmed_by');
    }

    public function kurir()
    {
        return $this->belongsTo(Kurir::class, 'kurir_id');
    }
}
