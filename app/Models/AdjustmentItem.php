<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdjustmentItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'adjustment_id',
        'item_id',
        'quantity',
        'koli',
    ];

    public function adjustment()
    {
        return $this->belongsTo(Adjustment::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

   
}