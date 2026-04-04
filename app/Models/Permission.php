<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    use HasFactory;

    protected $fillable = [
        'jabatan_id',
        'menu_id',
        'can_read',
        'can_create',
        'can_edit',
        'can_delete',
        'can_approve',
    ];
}
