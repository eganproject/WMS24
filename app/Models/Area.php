<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Area extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function locations()
    {
        return $this->hasMany(Location::class, 'area_id');
    }

    public function items()
    {
        return $this->hasMany(Item::class, 'area_id');
    }

    public function users()
    {
        return $this->hasMany(User::class, 'area_id');
    }
}
