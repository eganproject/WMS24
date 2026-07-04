<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KpiEmployeeAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'kpi_definition_id',
        'effective_from',
        'effective_until',
        'target_value',
        'weight',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_until' => 'date',
        'target_value' => 'decimal:4',
        'weight' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function definition()
    {
        return $this->belongsTo(KpiDefinition::class, 'kpi_definition_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
