<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InboundScanSessionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'inbound_scan_session_id',
        'item_id',
        'sku',
        'item_name',
        'qty_per_koli',
        'expected_qty',
        'expected_koli',
        'scanned_qty',
        'scanned_koli',
        'note',
    ];

    protected $casts = [
        'qty_per_koli' => 'integer',
        'expected_qty' => 'integer',
        'expected_koli' => 'integer',
        'scanned_qty' => 'integer',
        'scanned_koli' => 'integer',
    ];

    public function session()
    {
        return $this->belongsTo(InboundScanSession::class, 'inbound_scan_session_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
}
