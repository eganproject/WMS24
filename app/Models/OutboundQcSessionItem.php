<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OutboundQcSessionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'outbound_qc_session_id',
        'item_id',
        'sku',
        'item_name',
        'expected_qty',
        'scanned_qty',
        'note',
    ];

    public function session()
    {
        return $this->belongsTo(OutboundQcSession::class, 'outbound_qc_session_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
}
