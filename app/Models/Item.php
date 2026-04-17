<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    use HasFactory;

    protected $fillable = [
        'sku',
        'name',
        'category_id',
        'lane_id',
        'location_id',
        'address',
        'description',
        'safety_stock',
        'koli_qty',
    ];

    protected $casts = [
        'safety_stock' => 'integer',
        'koli_qty' => 'integer',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function stock()
    {
        return $this->hasOne(ItemStock::class, 'item_id')
            ->where('warehouse_id', \App\Support\WarehouseService::defaultWarehouseId());
    }

    public function lane()
    {
        return $this->belongsTo(Lane::class, 'lane_id');
    }

    public function stocks()
    {
        return $this->hasMany(ItemStock::class, 'item_id');
    }

    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function resolvedLane(): ?Lane
    {
        return $this->location?->lane ?: $this->lane;
    }

    public function resolvedAddress(): string
    {
        return $this->location?->code ?? (string) ($this->address ?? '');
    }
}
