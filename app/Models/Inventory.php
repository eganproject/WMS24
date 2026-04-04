<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    protected $table = 'inventories';
    protected $fillable = ['warehouse_id', 'item_id', 'quantity', 'koli'];

    public function item(){
        return $this->belongsTo(Item::class);
    }

    public function warehouse(){
        return $this->belongsTo(Warehouse::class);
    }
}
