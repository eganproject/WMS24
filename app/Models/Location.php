<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    use HasFactory;

    protected $fillable = [
        'area_id',
        'rack_code',
        'column_no',
        'row_no',
        'code',
    ];

    protected $casts = [
        'column_no' => 'integer',
        'row_no' => 'integer',
    ];

    public function area()
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

    public function items()
    {
        return $this->hasMany(Item::class, 'location_id');
    }
}
