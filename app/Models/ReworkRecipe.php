<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReworkRecipe extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'target_warehouse_id',
        'note',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function items()
    {
        return $this->hasMany(ReworkRecipeItem::class, 'rework_recipe_id');
    }

    public function inputItems()
    {
        return $this->hasMany(ReworkRecipeItem::class, 'rework_recipe_id')
            ->where('line_type', 'input');
    }

    public function outputItems()
    {
        return $this->hasMany(ReworkRecipeItem::class, 'rework_recipe_id')
            ->where('line_type', 'output');
    }

    public function targetWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'target_warehouse_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function allocations()
    {
        return $this->hasMany(DamagedAllocation::class, 'recipe_id');
    }
}
