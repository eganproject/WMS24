<?php

namespace App\Models;

use App\Support\ResiOperationalStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Resi extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_pesanan',
        'tanggal_pesanan',
        'tanggal_upload',
        'no_resi',
        'kurir_id',
        'status',
        'canceled_at',
        'canceled_by',
        'cancel_reason',
        'uncanceled_at',
        'uncanceled_by',
        'uploader_id',
    ];

    protected $casts = [
        'tanggal_pesanan' => 'date',
        'tanggal_upload' => 'date',
        'canceled_at' => 'datetime',
        'uncanceled_at' => 'datetime',
    ];

    public function details()
    {
        return $this->hasMany(ResiDetail::class, 'resi_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }

    public function canceler()
    {
        return $this->belongsTo(User::class, 'canceled_by');
    }

    public function uncanceler()
    {
        return $this->belongsTo(User::class, 'uncanceled_by');
    }

    public function kurir()
    {
        return $this->belongsTo(Kurir::class, 'kurir_id');
    }

    public function qcScan()
    {
        return $this->hasOne(QcResiScan::class, 'resi_id');
    }

    public function packerScan()
    {
        return $this->hasOne(PackerResiScan::class, 'resi_id');
    }

    public function scanOut()
    {
        return $this->hasOne(PackerScanOut::class, 'resi_id');
    }

    public function getOperationalStatusAttribute(): string
    {
        $qcScan = $this->relationLoaded('qcScan') ? $this->getRelation('qcScan') : $this->qcScan()->first();
        $packerScan = $this->relationLoaded('packerScan') ? $this->getRelation('packerScan') : $this->packerScan()->first();
        $scanOut = $this->relationLoaded('scanOut') ? $this->getRelation('scanOut') : $this->scanOut()->first();

        return ResiOperationalStatus::resolve(
            $this->status,
            $qcScan !== null,
            ($qcScan?->status ?? '') === 'passed',
            $packerScan !== null,
            $scanOut !== null
        );
    }
}
