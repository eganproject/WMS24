<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Item extends Model
{
    use HasFactory;

    protected $fillable = [
        'koli',
        'sku',
        'uom_id',
        'nama_barang',
        'deskripsi',
        'product_code',
        'item_category_id',
    ];

    public function uom()
    {
        return $this->belongsTo(Uom::class);
    }

    public function itemCategory()
    {
        return $this->belongsTo(ItemCategory::class);
    }
}