<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InboundTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'type',
        'ref_no',
        'supplier_id',
        'surat_jalan_no',
        'surat_jalan_at',
        'surat_jalan_image_path',
        'transacted_at',
        'note',
        'warehouse_id',
        'status',
        'approved_at',
        'created_by',
        'approved_by',
    ];

    protected $casts = [
        'surat_jalan_at' => 'datetime',
        'transacted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(InboundItem::class, 'inbound_transaction_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scanSession()
    {
        return $this->hasOne(InboundScanSession::class, 'inbound_transaction_id');
    }

    public function stockMutations()
    {
        return $this->hasMany(StockMutation::class, 'source_id')->where('source_type', 'inbound');
    }

    public function canDeleteReturnScan(?User $user): bool
    {
        if ($this->type !== 'return'
            || $this->status !== \App\Support\InboundScanStatus::SCANNING
            || ! $this->scanSession
            || $this->approved_at
            || $this->scanSession->completed_at
            || ($this->stock_mutations_exists ?? $this->stockMutations()->exists())) {
            return false;
        }

        return $user?->email === 'admin@gmail.com'
            || ($this->scanSession->items->every(fn ($item) => (int) $item->scanned_qty === 0 && (int) $item->scanned_koli === 0));
    }

    public function koliUnits()
    {
        return $this->hasMany(InboundKoliUnit::class, 'inbound_transaction_id');
    }
}
