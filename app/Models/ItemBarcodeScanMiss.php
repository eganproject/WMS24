<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemBarcodeScanMiss extends Model
{
    use HasFactory;

    protected $fillable = [
        'context',
        'source_id',
        'source_code',
        'scan_code',
        'normalized_code',
        'normalized_hash',
        'scan_count',
        'last_scanned_at',
        'created_by',
        'resolved_item_id',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'last_scanned_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function resolvedItem()
    {
        return $this->belongsTo(Item::class, 'resolved_item_id');
    }
}
