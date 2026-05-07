<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockTransferKoliScan extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_transfer_id',
        'stock_transfer_item_id',
        'inbound_koli_unit_id',
        'item_id',
        'qty',
        'qty_ok',
        'qty_reject',
        'qty_short',
        'qc_note',
        'scanned_by',
        'scanned_at',
    ];

    protected $casts = [
        'qty' => 'integer',
        'qty_ok' => 'integer',
        'qty_reject' => 'integer',
        'qty_short' => 'integer',
        'scanned_at' => 'datetime',
    ];

    public function transfer()
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id');
    }

    public function transferItem()
    {
        return $this->belongsTo(StockTransferItem::class, 'stock_transfer_item_id');
    }

    public function koliUnit()
    {
        return $this->belongsTo(InboundKoliUnit::class, 'inbound_koli_unit_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function scanner()
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }
}
