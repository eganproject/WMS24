<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoodRework extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';

    protected $fillable = [
        'code',
        'source_warehouse_id',
        'target_warehouse_id',
        'recipe_id',
        'recipe_multiplier',
        'source_ref',
        'transacted_at',
        'note',
        'status',
        'approved_at',
        'created_by',
        'approved_by',
    ];

    protected $casts = [
        'transacted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(GoodReworkItem::class, 'good_rework_id');
    }

    public function inputItems()
    {
        return $this->hasMany(GoodReworkItem::class, 'good_rework_id')->where('line_type', 'input');
    }

    public function outputItems()
    {
        return $this->hasMany(GoodReworkItem::class, 'good_rework_id')->where('line_type', 'output');
    }

    public function sourceWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'source_warehouse_id');
    }

    public function targetWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'target_warehouse_id');
    }

    public function recipe()
    {
        return $this->belongsTo(ReworkRecipe::class, 'recipe_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }
}
