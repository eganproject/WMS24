<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssemblyRecipe extends Model
{
    protected $fillable = [
        'code',
        'finished_item_id',
        'output_quantity',
        'description',
        'is_active',
    ];

    public function items()
    {
        return $this->hasMany(AssemblyRecipeItem::class, 'assembly_recipe_id');
    }

    public function finishedItem()
    {
        return $this->belongsTo(Item::class, 'finished_item_id');
    }
}
