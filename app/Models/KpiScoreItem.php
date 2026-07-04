<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KpiScoreItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'kpi_score_snapshot_id',
        'kpi_definition_id',
        'employee_id',
        'role_name',
        'metric_name',
        'target_operator',
        'target_value',
        'actual_value',
        'achievement_percent',
        'score',
        'weight',
        'weighted_score',
        'source_type',
        'formula_key',
        'note',
        'calculated_at',
    ];

    protected $casts = [
        'target_value' => 'decimal:4',
        'actual_value' => 'decimal:4',
        'achievement_percent' => 'decimal:2',
        'score' => 'decimal:2',
        'weight' => 'decimal:2',
        'weighted_score' => 'decimal:2',
        'calculated_at' => 'datetime',
    ];

    public function snapshot()
    {
        return $this->belongsTo(KpiScoreSnapshot::class, 'kpi_score_snapshot_id');
    }

    public function definition()
    {
        return $this->belongsTo(KpiDefinition::class, 'kpi_definition_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
