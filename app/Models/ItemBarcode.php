<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemBarcode extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_id',
        'barcode_value',
        'normalized_barcode',
        'normalized_hash',
        'source_name',
        'note',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
}
