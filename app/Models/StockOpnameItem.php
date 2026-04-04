<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockOpnameItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_opname_id',
        'item_id',
        'system_quantity',
        'system_koli',
        'physical_quantity',
        'physical_koli',
        'discrepancy_quantity',
        'discrepancy_koli',
        'description',
    ];

    public function stockOpname()
    {
        return $this->belongsTo(StockOpname::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
