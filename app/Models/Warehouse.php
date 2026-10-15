<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'type',
    ];

    public function stocks()
    {
        return $this->hasMany(ItemStock::class, 'warehouse_id');
    }

    public function areas()
    {
        return $this->hasMany(Area::class, 'warehouse_id');
    }
}
