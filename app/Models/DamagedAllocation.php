<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DamagedAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'type',
        'recipe_id',
        'recipe_multiplier',
        'supplier_id',
        'target_warehouse_id',
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
        'recipe_multiplier' => 'integer',
    ];

    public function items()
    {
        return $this->hasMany(DamagedAllocationItem::class, 'damaged_allocation_id');
    }

    public function sourceItems()
    {
        return $this->hasMany(DamagedAllocationItem::class, 'damaged_allocation_id')
            ->where('line_type', 'source');
    }

    public function outputItems()
    {
        return $this->hasMany(DamagedAllocationItem::class, 'damaged_allocation_id')
            ->where('line_type', 'output');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function recipe()
    {
        return $this->belongsTo(ReworkRecipe::class, 'recipe_id');
    }

    public function targetWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'target_warehouse_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
