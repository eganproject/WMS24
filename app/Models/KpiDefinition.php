<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KpiDefinition extends Model
{
    use HasFactory;

    protected $fillable = [
        'role_name',
        'metric_name',
        'description',
        'target_operator',
        'target_value',
        'unit',
        'weight',
        'period_type',
        'source_type',
        'formula_key',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'target_value' => 'decimal:4',
        'weight' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function assignments()
    {
        return $this->hasMany(KpiEmployeeAssignment::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
