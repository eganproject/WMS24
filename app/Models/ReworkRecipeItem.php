<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReworkRecipeItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'rework_recipe_id',
        'line_type',
        'item_id',
        'qty',
        'note',
    ];

    public function recipe()
    {
        return $this->belongsTo(ReworkRecipe::class, 'rework_recipe_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
}
