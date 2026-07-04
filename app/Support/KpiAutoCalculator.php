<?php

namespace App\Support;

use App\Models\KpiScoreItem;
use App\Models\KpiScoreSnapshot;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class KpiAutoCalculator
{
    public function recalculateSnapshot(KpiScoreSnapshot $snapshot): array
    {
        $updated = 0;
        $skipped = 0;

        $snapshot->items()->with('employee')->chunkById(100, function ($items) use ($snapshot, &$updated, &$skipped) {
            foreach ($items as $item) {
                $actual = $this->actualValue($item, $snapshot);

                if ($actual === null) {
                    $skipped++;
                    continue;
                }

                $this->applyActualValue($item, $actual);
                $updated++;
            }
        });

        return ['updated' => $updated, 'skipped' => $skipped];
    }

    public function applyActualValue(KpiScoreItem $item, float $actual): void
    {
        $result = $this->calculateScore($item->target_operator, (float) $item->target_value, $actual, (float) $item->weight);

        $item->update([
            'actual_value' => round($actual, 4),
            'achievement_percent' => $result['achievement_percent'],
            'score' => $result['score'],
            'weighted_score' => $result['weighted_score'],
            'note' => trim((string) $item->note) ?: 'Dihitung otomatis dari formula '.$item->formula_key.'.',
            'calculated_at' => now(),
        ]);
    }

    public function actualValue(KpiScoreItem $item, KpiScoreSnapshot $snapshot): ?float
    {
        $formula = (string) $item->formula_key;

        return match ($formula) {
            'packing_output' => $this->packingOutput($item, $snapshot),
            'packing_confirmation_rate' => $this->packingConfirmationRate($snapshot),
            'scan_out_productivity' => $this->scanOutProductivity($item, $snapshot),
            'missing_scan_out_rate' => $this->missingScanOutRate($snapshot),
            'qc_duplicate_scan_rate' => $this->qcDuplicateScanRate($item, $snapshot),
            'qc_processing_speed' => $this->qcProcessingSpeed($item, $snapshot),
            'qc_first_pass_rate' => $this->qcFirstPassRate($item, $snapshot),
            'qc_substitution_rate' => $this->qcSubstitutionRate($item, $snapshot),
            'root_cause_completion_rate' => $this->rootCauseCompletionRate($snapshot),
            'below_safety_resolution_rate' => $this->belowSafetyResolutionRate($snapshot),
            'attendance_data_completeness' => $this->attendanceDataCompleteness($snapshot),
            'barcode_miss_resolution_rate' => $this->barcodeMissResolutionRate($snapshot),
            'resi_cancel_control' => $this->resiCancelControl($snapshot),
            'return_finalization_lead_time' => $this->returnFinalizationLeadTime($snapshot),
            default => null,
        };
    }

    private function packingOutput(KpiScoreItem $item, KpiScoreSnapshot $snapshot): ?float
    {
        if (!$item->employee_id || !$this->hasTable('shipment_scan_outs') || !$this->hasColumn('shipment_scan_outs', 'packed_employee_id')) {
            return null;
        }

        return (float) $this->scanOutDateQuery($snapshot)
            ->where('packed_employee_id', $item->employee_id)
            ->count();
    }

    private function packingConfirmationRate(KpiScoreSnapshot $snapshot): ?float
    {
        if (!$this->hasTable('shipment_scan_outs') || !$this->hasColumn('shipment_scan_outs', 'packed_employee_id')) {
            return null;
        }

        $total = $this->scanOutDateQuery($snapshot)->count();
        if ($total === 0) {
            return 0.0;
        }

        $confirmed = $this->scanOutDateQuery($snapshot)->whereNotNull('packed_employee_id')->count();

        return $this->percentage($confirmed, $total);
    }

    private function scanOutProductivity(KpiScoreItem $item, KpiScoreSnapshot $snapshot): ?float
    {
        $userId = $item->employee?->user_id;
        if (!$userId || !$this->hasTable('shipment_scan_outs')) {
            return null;
        }

        return (float) $this->scanOutDateQuery($snapshot)
            ->where('scanned_by', $userId)
            ->count();
    }

    private function missingScanOutRate(KpiScoreSnapshot $snapshot): ?float
    {
        if (!$this->hasTable('resis') || !$this->hasTable('shipment_scan_outs')) {
            return null;
        }

        $total = DB::table('resis')
            ->whereDate('tanggal_upload', '>=', $snapshot->period_start)
            ->whereDate('tanggal_upload', '<=', $snapshot->period_end)
            ->when($this->hasColumn('resis', 'status'), fn ($query) => $query->where('status', '!=', 'canceled'))
            ->count();

        if ($total === 0) {
            return 0.0;
        }

        $scanned = DB::table('resis')
            ->whereDate('tanggal_upload', '>=', $snapshot->period_start)
            ->whereDate('tanggal_upload', '<=', $snapshot->period_end)
            ->when($this->hasColumn('resis', 'status'), fn ($query) => $query->where('status', '!=', 'canceled'))
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('shipment_scan_outs')
                    ->whereColumn('shipment_scan_outs.resi_id', 'resis.id');
            })
            ->count();

        return $this->percentage(max(0, $total - $scanned), $total);
    }

    private function qcDuplicateScanRate(KpiScoreItem $item, KpiScoreSnapshot $snapshot): ?float
    {
        if (!$this->hasTable('qc_resi_scan_duplicate_attempts') || !$this->hasTable('qc_resi_scans')) {
            return null;
        }

        $userId = $item->employee?->user_id;

        $duplicateQuery = DB::table('qc_resi_scan_duplicate_attempts')
            ->whereBetween('scanned_at', $this->dateTimeRange($snapshot));
        $qcQuery = DB::table('qc_resi_scans')
            ->whereBetween('started_at', $this->dateTimeRange($snapshot));

        if ($userId) {
            $duplicateQuery->where('scanned_by', $userId);
            $qcQuery->where('scanned_by', $userId);
        }

        $totalQc = $qcQuery->count();
        if ($totalQc === 0) {
            return 0.0;
        }

        return $this->percentage($duplicateQuery->count(), $totalQc);
    }

    private function qcProcessingSpeed(KpiScoreItem $item, KpiScoreSnapshot $snapshot): ?float
    {
        if (!$this->hasTable('qc_resi_scans')) {
            return null;
        }

        $userId = $item->employee?->user_id;
        $query = DB::table('qc_resi_scans')
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', $this->dateTimeRange($snapshot));

        if ($userId && $this->hasColumn('qc_resi_scans', 'completed_by')) {
            $query->where('completed_by', $userId);
        } elseif ($userId) {
            $query->where('scanned_by', $userId);
        }

        $averageSeconds = $query->selectRaw('AVG(TIMESTAMPDIFF(SECOND, started_at, completed_at)) as avg_seconds')->value('avg_seconds');

        return $averageSeconds === null ? 0.0 : round((float) $averageSeconds, 4);
    }

    private function qcFirstPassRate(KpiScoreItem $item, KpiScoreSnapshot $snapshot): ?float
    {
        if (!$this->hasTable('qc_resi_scans')) {
            return null;
        }

        $userId = $item->employee?->user_id;
        $base = DB::table('qc_resi_scans')
            ->where('status', 'passed')
            ->whereBetween('completed_at', $this->dateTimeRange($snapshot));

        if ($userId && $this->hasColumn('qc_resi_scans', 'completed_by')) {
            $base->where('completed_by', $userId);
        } elseif ($userId) {
            $base->where('scanned_by', $userId);
        }

        $total = (clone $base)->count();
        if ($total === 0) {
            return 0.0;
        }

        $firstPass = (clone $base)
            ->where(function ($query) {
                $query->whereNull('reset_count')->orWhere('reset_count', 0);
            })
            ->when($this->hasTable('qc_resi_scan_substitutions'), function ($query) {
                $query->whereNotExists(function ($subQuery) {
                    $subQuery->selectRaw('1')
                        ->from('qc_resi_scan_substitutions')
                        ->whereColumn('qc_resi_scan_substitutions.qc_resi_scan_id', 'qc_resi_scans.id');
                });
            })
            ->count();

        return $this->percentage($firstPass, $total);
    }

    private function qcSubstitutionRate(KpiScoreItem $item, KpiScoreSnapshot $snapshot): ?float
    {
        if (!$this->hasTable('qc_resi_scans') || !$this->hasTable('qc_resi_scan_substitutions')) {
            return null;
        }

        $userId = $item->employee?->user_id;
        $base = DB::table('qc_resi_scans')
            ->whereBetween('completed_at', $this->dateTimeRange($snapshot));

        if ($userId && $this->hasColumn('qc_resi_scans', 'completed_by')) {
            $base->where('completed_by', $userId);
        } elseif ($userId) {
            $base->where('scanned_by', $userId);
        }

        $total = (clone $base)->count();
        if ($total === 0) {
            return 0.0;
        }

        $withSubstitution = (clone $base)
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('qc_resi_scan_substitutions')
                    ->whereColumn('qc_resi_scan_substitutions.qc_resi_scan_id', 'qc_resi_scans.id');
            })
            ->count();

        return $this->percentage($withSubstitution, $total);
    }

    private function rootCauseCompletionRate(KpiScoreSnapshot $snapshot): ?float
    {
        if (!$this->hasTable('customer_returns') || !$this->hasTable('customer_return_items') || !$this->hasColumn('customer_return_items', 'root_cause')) {
            return null;
        }

        $base = DB::table('customer_return_items')
            ->join('customer_returns', 'customer_returns.id', '=', 'customer_return_items.customer_return_id')
            ->whereBetween('customer_returns.received_at', $this->dateTimeRange($snapshot));

        $total = (clone $base)->count();
        if ($total === 0) {
            return 0.0;
        }

        $completed = (clone $base)
            ->whereNotNull('customer_return_items.root_cause')
            ->where('customer_return_items.root_cause', '!=', '')
            ->count();

        return $this->percentage($completed, $total);
    }

    private function belowSafetyResolutionRate(KpiScoreSnapshot $snapshot): ?float
    {
        if (!$this->hasTable('low_stock_snapshot_items') || !$this->hasColumn('low_stock_snapshot_items', 'resolution_status')) {
            return null;
        }

        $base = DB::table('low_stock_snapshot_items')
            ->join('low_stock_snapshots', 'low_stock_snapshots.id', '=', 'low_stock_snapshot_items.low_stock_snapshot_id')
            ->whereBetween('low_stock_snapshots.snapshot_at', $this->dateTimeRange($snapshot));

        $total = (clone $base)->count();
        if ($total === 0) {
            return 0.0;
        }

        $resolved = (clone $base)->where('low_stock_snapshot_items.resolution_status', 'resolved')->count();

        return $this->percentage($resolved, $total);
    }

    private function attendanceDataCompleteness(KpiScoreSnapshot $snapshot): ?float
    {
        if (!$this->hasTable('employees') || !$this->hasTable('employee_schedules') || !$this->hasTable('attendances')) {
            return null;
        }

        $activeEmployees = DB::table('employees')->where('employment_status', 'active')->count();
        if ($activeEmployees === 0) {
            return 0.0;
        }

        $complete = DB::table('employees')
            ->where('employment_status', 'active')
            ->whereExists(function ($query) use ($snapshot) {
                $query->selectRaw('1')
                    ->from('employee_schedules')
                    ->whereColumn('employee_schedules.employee_id', 'employees.id')
                    ->whereDate('employee_schedules.schedule_date', '>=', $snapshot->period_start)
                    ->whereDate('employee_schedules.schedule_date', '<=', $snapshot->period_end);
            })
            ->whereExists(function ($query) use ($snapshot) {
                $query->selectRaw('1')
                    ->from('attendances')
                    ->whereColumn('attendances.employee_id', 'employees.id')
                    ->whereDate('attendances.attendance_date', '>=', $snapshot->period_start)
                    ->whereDate('attendances.attendance_date', '<=', $snapshot->period_end);
            })
            ->count();

        return $this->percentage($complete, $activeEmployees);
    }

    private function barcodeMissResolutionRate(KpiScoreSnapshot $snapshot): ?float
    {
        if (!$this->hasTable('item_barcode_scan_misses') || !$this->hasColumn('item_barcode_scan_misses', 'resolved_at')) {
            return null;
        }

        $base = DB::table('item_barcode_scan_misses')
            ->whereBetween('created_at', $this->dateTimeRange($snapshot));

        $total = (clone $base)->count();
        if ($total === 0) {
            return 0.0;
        }

        return $this->percentage((clone $base)->whereNotNull('resolved_at')->count(), $total);
    }

    private function resiCancelControl(KpiScoreSnapshot $snapshot): ?float
    {
        if (!$this->hasTable('resis') || !$this->hasColumn('resis', 'status')) {
            return null;
        }

        $base = DB::table('resis')
            ->whereDate('tanggal_upload', '>=', $snapshot->period_start)
            ->whereDate('tanggal_upload', '<=', $snapshot->period_end);

        $total = (clone $base)->count();
        if ($total === 0) {
            return 0.0;
        }

        return $this->percentage((clone $base)->where('status', 'canceled')->count(), $total);
    }

    private function returnFinalizationLeadTime(KpiScoreSnapshot $snapshot): ?float
    {
        if (
            !$this->hasTable('customer_returns')
            || !$this->hasColumn('customer_returns', 'received_at')
            || !$this->hasColumn('customer_returns', 'finalized_at')
        ) {
            return null;
        }

        $averageMinutes = DB::table('customer_returns')
            ->whereNotNull('received_at')
            ->whereNotNull('finalized_at')
            ->whereBetween('finalized_at', $this->dateTimeRange($snapshot))
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, received_at, finalized_at)) as avg_minutes')
            ->value('avg_minutes');

        return $averageMinutes === null ? 0.0 : round(((float) $averageMinutes) / 60, 4);
    }

    private function scanOutDateQuery(KpiScoreSnapshot $snapshot)
    {
        $query = DB::table('shipment_scan_outs');

        if ($this->hasColumn('shipment_scan_outs', 'scan_date')) {
            return $query->whereDate('scan_date', '>=', $snapshot->period_start)
                ->whereDate('scan_date', '<=', $snapshot->period_end);
        }

        return $query->whereBetween('scanned_at', $this->dateTimeRange($snapshot));
    }

    private function dateTimeRange(KpiScoreSnapshot $snapshot): array
    {
        return [
            Carbon::parse($snapshot->period_start)->startOfDay(),
            Carbon::parse($snapshot->period_end)->endOfDay(),
        ];
    }

    private function percentage(int|float $part, int|float $total): float
    {
        return $total > 0 ? round(($part / $total) * 100, 4) : 0.0;
    }

    private function calculateScore(string $operator, float $target, float $actual, float $weight): array
    {
        if ($operator === '<=') {
            $achievement = $actual <= $target ? 100 : ($target / max($actual, 0.0001)) * 100;
        } elseif ($operator === '=') {
            $achievement = $target == 0.0
                ? ($actual == 0.0 ? 100 : 0)
                : max(0, 100 - (abs($actual - $target) / abs($target) * 100));
        } else {
            $achievement = $target == 0.0 ? ($actual > 0 ? 100 : 0) : ($actual / $target) * 100;
        }

        $score = min(max($achievement, 0), 100);

        return [
            'achievement_percent' => round($achievement, 2),
            'score' => round($score, 2),
            'weighted_score' => round($score * ($weight / 100), 2),
        ];
    }

    private function hasTable(string $table): bool
    {
        return Schema::hasTable($table);
    }

    private function hasColumn(string $table, string $column): bool
    {
        return Schema::hasColumn($table, $column);
    }
}
