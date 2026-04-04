<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockOut extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'date',
        'warehouse_id',
        'description',
        'created_by',
        'status',
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items()
    {
        return $this->hasMany(StockOutItem::class);
    }

     public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

}