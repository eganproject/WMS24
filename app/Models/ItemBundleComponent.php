<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemBundleComponent extends Model
{
    use HasFactory;

    protected $fillable = [
        'bundle_item_id',
        'component_item_id',
        'required_qty',
    ];

    protected $casts = [
        'required_qty' => 'integer',
    ];

    public function bundle()
    {
        return $this->belongsTo(Item::class, 'bundle_item_id');
    }

    public function component()
    {
        return $this->belongsTo(Item::class, 'component_item_id');
    }
}
