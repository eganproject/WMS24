<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssemblyRecipeItem extends Model
{
    protected $fillable = [
        'assembly_recipe_id',
        'item_id',
        'quantity',
    ];

    public function recipe()
    {
        return $this->belongsTo(AssemblyRecipe::class, 'assembly_recipe_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
}

