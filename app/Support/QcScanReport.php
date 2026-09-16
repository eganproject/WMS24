<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class QcScanReport
{
    public function build(array $filters = []): array
    {
        $filters = $this->normalizeFilters($filters);
        $details = $this->detailRows($filters);
        $hourly = $this->hourlyRows($details);
        $operators = $this->operatorRows($details);
        $totalResi = $details->count();
        $durationRows = $details->whereNotNull('duration_minutes');
        $peak = $details->groupBy('hour')->map->count()->sortDesc()->keys()->first();
        $peakTotal = $peak === null ? 0 : $details->where('hour', $peak)->count();

        $summary = [
            'total_resi' => $totalResi,
            'total_operators' => $details->pluck('operator_id')->filter()->unique()->count(),
            'total_sku' => (int) $details->sum('total_sku'),
            'total_qty' => (int) $details->sum('total_qty'),
            'active_operator_hours' => $hourly->count(),
            'avg_resi_per_hour' => $hourly->isNotEmpty() ? round($totalResi / $hourly->count(), 2) : 0,
            'avg_duration_minutes' => $durationRows->isNotEmpty() ? round((float) $durationRows->avg('duration_minutes'), 2) : 0,
            'reset_count' => (int) $details->sum('reset_count'),
            'substitution_count' => (int) $details->sum('substitution_count'),
            'duplicate_attempts' => $this->duplicateAttempts($filters),
            'hold_events' => $this->holdEvents($filters),
            'peak_hour' => $peak === null
                ? '-'
                : str_pad((string) $peak, 2, '0', STR_PAD_LEFT).':00 - '.str_pad((string) (($peak + 1) % 24), 2, '0', STR_PAD_LEFT).':00',
            'peak_hour_resi' => $peakTotal,
        ];

        return [
            'period' => [
                'date_from' => $filters['date_from'],
                'date_to' => $filters['date_to'],
                'operator_id' => $filters['operator_id'],
                'operator_name' => $filters['operator_name'],
                'search' => $filters['q'],
            ],
            'summary' => $summary,
            'charts' => $this->charts($details, $operators),
            'hourly' => $hourly->values()->all(),
            'operators' => $operators->values()->all(),
            'details' => $details->values()->all(),
        ];
    }

    private function detailRows(array $filters): Collection
    {
        $itemTotals = DB::table('qc_resi_scan_items')
            ->selectRaw('qc_resi_scan_id, COUNT(*) as total_sku, COALESCE(SUM(expected_qty), 0) as expected_qty, COALESCE(SUM(scanned_qty), 0) as total_qty')
            ->groupBy('qc_resi_scan_id');

        $substitutionTotals = DB::table('qc_resi_scan_substitutions')
            ->selectRaw('qc_resi_scan_id, COUNT(*) as substitution_count, COALESCE(SUM(qty), 0) as substitution_qty')
            ->groupBy('qc_resi_scan_id');

        $duplicateTotals = DB::table('qc_resi_scan_duplicate_attempts')
            ->selectRaw('qc_resi_scan_id, COUNT(*) as duplicate_attempts')
            ->groupBy('qc_resi_scan_id');
        $this->applyPeriod($duplicateTotals, 'scanned_at', $filters);
        if ($filters['operator_id']) {
            $duplicateTotals->where('scanned_by', $filters['operator_id']);
        }

        $query = DB::table('qc_resi_scans as qc')
            ->leftJoin('users as completed_user', 'completed_user.id', '=', 'qc.completed_by')
            ->leftJoin('users as started_user', 'started_user.id', '=', 'qc.scanned_by')
            ->leftJoin('resis as r', 'r.id', '=', 'qc.resi_id')
            ->leftJoin('kurirs as k', 'k.id', '=', 'r.kurir_id')
            ->leftJoinSub($itemTotals, 'item_totals', 'item_totals.qc_resi_scan_id', '=', 'qc.id')
            ->leftJoinSub($substitutionTotals, 'sub_totals', 'sub_totals.qc_resi_scan_id', '=', 'qc.id')
            ->leftJoinSub($duplicateTotals, 'dup_totals', 'dup_totals.qc_resi_scan_id', '=', 'qc.id')
            ->where('qc.status', QcTransitStatus::PASSED)
            ->whereNotNull('qc.completed_at')
            ->selectRaw("qc.id, qc.resi_id, qc.scan_type, qc.scan_code, qc.started_at, qc.completed_at, qc.reset_count, COALESCE(qc.completed_by, qc.scanned_by) as operator_id, COALESCE(completed_user.name, started_user.name, '-') as operator_name, r.id_pesanan, r.no_resi, COALESCE(k.name, '-') as expedition, COALESCE(item_totals.total_sku, 0) as total_sku, COALESCE(item_totals.expected_qty, 0) as expected_qty, COALESCE(item_totals.total_qty, 0) as total_qty, COALESCE(sub_totals.substitution_count, 0) as substitution_count, COALESCE(sub_totals.substitution_qty, 0) as substitution_qty, COALESCE(dup_totals.duplicate_attempts, 0) as duplicate_attempts");

        $this->applyCompletedPeriod($query, $filters);

        if ($filters['operator_id']) {
            $query->whereRaw('COALESCE(qc.completed_by, qc.scanned_by) = ?', [$filters['operator_id']]);
        }

        if ($filters['q'] !== '') {
            $value = '%'.$filters['q'].'%';
            $query->where(function ($q) use ($value) {
                $q->where('completed_user.name', 'like', $value)
                    ->orWhere('started_user.name', 'like', $value)
                    ->orWhere('r.id_pesanan', 'like', $value)
                    ->orWhere('r.no_resi', 'like', $value)
                    ->orWhere('qc.scan_code', 'like', $value)
                    ->orWhere('k.name', 'like', $value);
            });
        }

        return $query->orderBy('qc.completed_at')->orderBy('qc.id')->get()->map(function ($row) {
            $completedAt = Carbon::parse($row->completed_at);
            $startedAt = $row->started_at ? Carbon::parse($row->started_at) : null;
            $duration = $startedAt ? max(0, $startedAt->diffInSeconds($completedAt, false)) / 60 : null;

            return [
                'id' => (int) $row->id,
                'date' => $completedAt->toDateString(),
                'hour' => (int) $completedAt->format('H'),
                'hour_label' => $completedAt->format('H:00').' - '.$completedAt->copy()->addHour()->format('H:00'),
                'completed_at' => $completedAt->format('Y-m-d H:i:s'),
                'started_at' => $startedAt?->format('Y-m-d H:i:s'),
                'duration_minutes' => $duration === null ? null : round($duration, 2),
                'operator_id' => (int) $row->operator_id,
                'operator' => (string) $row->operator_name,
                'id_pesanan' => (string) ($row->id_pesanan ?? '-'),
                'no_resi' => (string) ($row->no_resi ?? '-'),
                'expedition' => (string) ($row->expedition ?? '-'),
                'scan_type' => (string) ($row->scan_type ?? '-'),
                'scan_code' => (string) ($row->scan_code ?? '-'),
                'total_sku' => (int) $row->total_sku,
                'expected_qty' => (int) $row->expected_qty,
                'total_qty' => (int) $row->total_qty,
                'reset_count' => (int) ($row->reset_count ?? 0),
                'substitution_count' => (int) $row->substitution_count,
                'substitution_qty' => (int) $row->substitution_qty,
                'duplicate_attempts' => (int) $row->duplicate_attempts,
            ];
        });
    }

    private function hourlyRows(Collection $details): Collection
    {
        return $details
            ->groupBy(fn (array $row) => $row['date'].'|'.$row['hour'].'|'.$row['operator_id'])
            ->map(function (Collection $rows) {
                $first = $rows->first();
                $durations = $rows->whereNotNull('duration_minutes');

                return [
                    'date' => $first['date'],
                    'hour' => $first['hour'],
                    'hour_label' => $first['hour_label'],
                    'operator_id' => $first['operator_id'],
                    'operator' => $first['operator'],
                    'total_resi' => $rows->count(),
                    'total_sku' => (int) $rows->sum('total_sku'),
                    'total_qty' => (int) $rows->sum('total_qty'),
                    'avg_duration_minutes' => $durations->isNotEmpty() ? round((float) $durations->avg('duration_minutes'), 2) : 0,
                    'reset_count' => (int) $rows->sum('reset_count'),
                    'substitution_count' => (int) $rows->sum('substitution_count'),
                    'duplicate_attempts' => (int) $rows->sum('duplicate_attempts'),
                    'first_completion' => Carbon::parse($rows->min('completed_at'))->format('H:i'),
                    'last_completion' => Carbon::parse($rows->max('completed_at'))->format('H:i'),
                ];
            })
            ->sortByDesc(fn (array $row) => $row['date'].' '.str_pad((string) $row['hour'], 2, '0', STR_PAD_LEFT).' '.$row['operator'])
            ->values();
    }

    private function operatorRows(Collection $details): Collection
    {
        return $details
            ->groupBy('operator_id')
            ->map(function (Collection $rows) {
                $first = $rows->first();
                $activeHours = $rows->map(fn (array $row) => $row['date'].'|'.$row['hour'])->unique()->count();
                $durations = $rows->whereNotNull('duration_minutes');

                return [
                    'operator_id' => $first['operator_id'],
                    'operator' => $first['operator'],
                    'total_resi' => $rows->count(),
                    'active_hours' => $activeHours,
                    'avg_resi_per_hour' => $activeHours > 0 ? round($rows->count() / $activeHours, 2) : 0,
                    'total_sku' => (int) $rows->sum('total_sku'),
                    'total_qty' => (int) $rows->sum('total_qty'),
                    'avg_duration_minutes' => $durations->isNotEmpty() ? round((float) $durations->avg('duration_minutes'), 2) : 0,
                    'reset_count' => (int) $rows->sum('reset_count'),
                    'substitution_count' => (int) $rows->sum('substitution_count'),
                    'duplicate_attempts' => (int) $rows->sum('duplicate_attempts'),
                    'first_completion' => Carbon::parse($rows->min('completed_at'))->format('Y-m-d H:i'),
                    'last_completion' => Carbon::parse($rows->max('completed_at'))->format('Y-m-d H:i'),
                ];
            })
            ->sortByDesc('total_resi')
            ->values();
    }

    private function charts(Collection $details, Collection $operators): array
    {
        $byHour = array_fill(0, 24, 0);
        foreach ($details as $row) {
            $byHour[$row['hour']]++;
        }

        $daily = $details->groupBy('date')->map->count()->sortKeys();

        return [
            'hours' => collect($byHour)->map(fn (int $total, int $hour) => [
                'label' => str_pad((string) $hour, 2, '0', STR_PAD_LEFT).':00',
                'total' => $total,
            ])->values()->all(),
            'daily' => $daily->map(fn (int $total, string $date) => ['date' => $date, 'total' => $total])->values()->all(),
            'operators' => $operators->map(fn (array $row) => [
                'operator' => $row['operator'],
                'total' => $row['total_resi'],
            ])->values()->all(),
        ];
    }

    private function duplicateAttempts(array $filters): int
    {
        $query = DB::table('qc_resi_scan_duplicate_attempts as duplicate')
            ->leftJoin('users as actor', 'actor.id', '=', 'duplicate.scanned_by')
            ->leftJoin('resis as r', 'r.id', '=', 'duplicate.resi_id')
            ->leftJoin('kurirs as k', 'k.id', '=', 'r.kurir_id');
        $this->applyPeriod($query, 'duplicate.scanned_at', $filters);
        if ($filters['operator_id']) {
            $query->where('duplicate.scanned_by', $filters['operator_id']);
        }
        $this->applyEventSearch($query, $filters, 'duplicate.scan_code', 'actor.name');

        return $query->count();
    }

    private function holdEvents(array $filters): int
    {
        $query = DB::table('qc_resi_scans as qc')
            ->leftJoin('users as actor', 'actor.id', '=', 'qc.hold_by')
            ->leftJoin('resis as r', 'r.id', '=', 'qc.resi_id')
            ->leftJoin('kurirs as k', 'k.id', '=', 'r.kurir_id')
            ->whereNotNull('qc.hold_at');
        $this->applyPeriod($query, 'qc.hold_at', $filters);
        if ($filters['operator_id']) {
            $query->where('qc.hold_by', $filters['operator_id']);
        }
        $this->applyEventSearch($query, $filters, 'qc.scan_code', 'actor.name');

        return $query->count();
    }

    private function applyEventSearch($query, array $filters, string $scanColumn, string $actorColumn): void
    {
        if ($filters['q'] === '') {
            return;
        }

        $value = '%'.$filters['q'].'%';
        $query->where(function ($q) use ($value, $scanColumn, $actorColumn) {
            $q->where($scanColumn, 'like', $value)
                ->orWhere($actorColumn, 'like', $value)
                ->orWhere('r.id_pesanan', 'like', $value)
                ->orWhere('r.no_resi', 'like', $value)
                ->orWhere('k.name', 'like', $value);
        });
    }

    private function applyCompletedPeriod($query, array $filters): void
    {
        $this->applyPeriod($query, 'qc.completed_at', $filters);
    }

    private function applyPeriod($query, string $column, array $filters): void
    {
        if ($filters['date_from']) {
            $query->where($column, '>=', Carbon::parse($filters['date_from'])->startOfDay());
        }
        if ($filters['date_to']) {
            $query->where($column, '<=', Carbon::parse($filters['date_to'])->endOfDay());
        }
    }

    private function normalizeFilters(array $filters): array
    {
        return [
            'date_from' => $this->date($filters['date_from'] ?? null),
            'date_to' => $this->date($filters['date_to'] ?? null),
            'operator_id' => !empty($filters['operator_id']) ? (int) $filters['operator_id'] : null,
            'operator_name' => trim((string) ($filters['operator_name'] ?? '')),
            'q' => trim((string) ($filters['q'] ?? '')),
        ];
    }

    private function date(mixed $value): ?string
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
