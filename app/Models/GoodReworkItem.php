<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoodReworkItem extends Model
{
    protected $fillable = [
        'good_rework_id',
        'line_type',
        'item_id',
        'qty',
        'note',
    ];

    public function rework()
    {
        return $this->belongsTo(GoodRework::class, 'good_rework_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
