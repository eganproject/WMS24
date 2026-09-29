<?php

namespace App\Support;

use App\Models\CustomerReturnItem;
use App\Models\QcResiScanEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Laporan akurasi picker berbasis atribusi picker pada QC resi.
 *
 * Satu resi dianggap bermasalah bila QC menemukan kesalahan picking (salah SKU, qty berlebih,
 * atau hold/reset dengan kategori kesalahan picker), atau bila resi tersebut kembali sebagai
 * retur dengan root cause salah barang / barang kurang.
 */
class PickerAccuracyReport
{
    public const ESCAPED_ROOT_CAUSES = [
        CustomerReturnItem::ROOT_CAUSE_WRONG_ITEM,
        CustomerReturnItem::ROOT_CAUSE_INCOMPLETE_ITEM,
    ];

    public function build(array $filters = []): array
    {
        $filters = $this->normalizeFilters($filters);
        $resiRows = $this->resiRows($filters);
        $pickers = $this->pickerRows($resiRows);
        $events = $this->eventRows($filters);
        $totalResi = $resiRows->count();

        $summary = [
            'total_resi' => $totalResi,
            'total_pickers' => $pickers->count(),
            'total_qty' => (int) $resiRows->sum('total_qty'),
            'accurate_resi' => $resiRows->where('is_accurate', true)->count(),
            'accuracy_rate' => $this->percentage($resiRows->where('is_accurate', true)->count(), $totalResi),
            'caught_error_resi' => $resiRows->where('caught_error', true)->count(),
            'escaped_error_resi' => $resiRows->where('escaped_error', true)->count(),
            'first_pass_rate' => $this->percentage($resiRows->where('first_pass', true)->count(), $totalResi),
            'wrong_sku_events' => (int) $resiRows->sum('wrong_sku'),
            'over_qty_events' => (int) $resiRows->sum('over_qty'),
            'picker_fault_events' => (int) $resiRows->sum('picker_fault'),
            'unknown_barcode_events' => (int) $resiRows->sum('unknown_barcode'),
            'substitution_events' => (int) $resiRows->sum('substitution'),
            'unattributed_resi' => $this->unattributedResi($filters),
        ];

        return [
            'period' => [
                'date_from' => $filters['date_from'],
                'date_to' => $filters['date_to'],
                'picker_id' => $filters['picker_id'],
                'picker_name' => $filters['picker_name'],
                'search' => $filters['q'],
            ],
            'summary' => $summary,
            'charts' => $this->charts($resiRows, $pickers),
            'pickers' => $pickers->values()->all(),
            'sku_pairs' => $this->skuPairRows($events)->values()->all(),
            'reasons' => $this->reasonRows($events)->values()->all(),
            'events' => $events->values()->all(),
        ];
    }

    private function resiRows(array $filters): Collection
    {
        $faultPlaceholders = implode(', ', array_fill(0, count(QcReasonCategory::PICKER_FAULT_CODES), '?'));
        $eventTotals = DB::table('qc_resi_scan_events')
            ->selectRaw(
                'qc_resi_scan_id,
                SUM(CASE WHEN event_type = ? THEN 1 ELSE 0 END) as wrong_sku,
                SUM(CASE WHEN event_type = ? THEN 1 ELSE 0 END) as unknown_barcode,
                SUM(CASE WHEN event_type = ? THEN 1 ELSE 0 END) as over_qty,
                SUM(CASE WHEN event_type = ? THEN 1 ELSE 0 END) as hold_count,
                SUM(CASE WHEN event_type = ? THEN 1 ELSE 0 END) as reset_count,
                SUM(CASE WHEN event_type = ? THEN 1 ELSE 0 END) as substitution_count,
                SUM(CASE WHEN event_type IN (?, ?) AND reason_code IN ('.$faultPlaceholders.') THEN 1 ELSE 0 END) as picker_fault',
                [
                    QcResiScanEvent::TYPE_WRONG_SKU,
                    QcResiScanEvent::TYPE_UNKNOWN_BARCODE,
                    QcResiScanEvent::TYPE_OVER_QTY,
                    QcResiScanEvent::TYPE_HOLD,
                    QcResiScanEvent::TYPE_RESET,
                    QcResiScanEvent::TYPE_SUBSTITUTION,
                    QcResiScanEvent::TYPE_HOLD,
                    QcResiScanEvent::TYPE_RESET,
                    ...QcReasonCategory::PICKER_FAULT_CODES,
                ]
            )
            ->groupBy('qc_resi_scan_id');

        $itemTotals = DB::table('qc_resi_scan_items')
            ->selectRaw('qc_resi_scan_id, COALESCE(SUM(expected_qty), 0) as total_qty, COUNT(*) as total_sku')
            ->groupBy('qc_resi_scan_id');

        $returnTotals = DB::table('customer_return_items as cri')
            ->join('customer_returns as cr', 'cr.id', '=', 'cri.customer_return_id')
            ->whereNotNull('cr.resi_id')
            ->whereIn('cri.root_cause', self::ESCAPED_ROOT_CAUSES)
            ->selectRaw('cr.resi_id, COUNT(DISTINCT cr.id) as escaped_returns')
            ->groupBy('cr.resi_id');

        $query = $this->baseQcQuery($filters)
            ->leftJoinSub($eventTotals, 'ev', 'ev.qc_resi_scan_id', '=', 'qc.id')
            ->leftJoinSub($itemTotals, 'it', 'it.qc_resi_scan_id', '=', 'qc.id')
            ->leftJoinSub($returnTotals, 'rt', 'rt.resi_id', '=', 'qc.resi_id')
            ->selectRaw('qc.id, qc.resi_id, qc.status, qc.started_at, qc.completed_at, qc.picker_employee_id,
                e.name as picker_name, e.employee_code as picker_code, r.id_pesanan, r.no_resi,
                COALESCE(it.total_qty, 0) as total_qty, COALESCE(it.total_sku, 0) as total_sku,
                COALESCE(ev.wrong_sku, 0) as wrong_sku, COALESCE(ev.unknown_barcode, 0) as unknown_barcode,
                COALESCE(ev.over_qty, 0) as over_qty, COALESCE(ev.hold_count, 0) as hold_count,
                COALESCE(ev.reset_count, 0) as reset_count, COALESCE(ev.substitution_count, 0) as substitution_count,
                COALESCE(ev.picker_fault, 0) as picker_fault, COALESCE(rt.escaped_returns, 0) as escaped_returns');

        return $query->orderBy('qc.started_at')->orderBy('qc.id')->get()->map(function ($row) {
            $startedAt = Carbon::parse($row->started_at);
            $wrongSku = (int) $row->wrong_sku;
            $overQty = (int) $row->over_qty;
            $pickerFault = (int) $row->picker_fault;
            $escapedReturns = (int) $row->escaped_returns;
            $caughtError = $wrongSku > 0 || $overQty > 0 || $pickerFault > 0;
            $escapedError = $escapedReturns > 0;

            return [
                'id' => (int) $row->id,
                'date' => $startedAt->toDateString(),
                'started_at' => $startedAt->format('Y-m-d H:i:s'),
                'picker_id' => (int) $row->picker_employee_id,
                'picker' => (string) $row->picker_name,
                'picker_code' => (string) ($row->picker_code ?? ''),
                'id_pesanan' => (string) ($row->id_pesanan ?? '-'),
                'no_resi' => (string) ($row->no_resi ?? '-'),
                'status' => (string) $row->status,
                'total_qty' => (int) $row->total_qty,
                'total_sku' => (int) $row->total_sku,
                'wrong_sku' => $wrongSku,
                'unknown_barcode' => (int) $row->unknown_barcode,
                'over_qty' => $overQty,
                'hold' => (int) $row->hold_count,
                'reset' => (int) $row->reset_count,
                'substitution' => (int) $row->substitution_count,
                'picker_fault' => $pickerFault,
                'escaped_returns' => $escapedReturns,
                'caught_error' => $caughtError,
                'escaped_error' => $escapedError,
                'is_accurate' => !$caughtError && !$escapedError,
                'first_pass' => $wrongSku === 0 && $overQty === 0 && (int) $row->hold_count === 0 && (int) $row->reset_count === 0,
            ];
        });
    }

    private function pickerRows(Collection $resiRows): Collection
    {
        return $resiRows
            ->groupBy('picker_id')
            ->map(function (Collection $rows) {
                $first = $rows->first();
                $total = $rows->count();

                return [
                    'picker_id' => $first['picker_id'],
                    'picker' => $first['picker'],
                    'picker_code' => $first['picker_code'],
                    'total_resi' => $total,
                    'total_qty' => (int) $rows->sum('total_qty'),
                    'accurate_resi' => $rows->where('is_accurate', true)->count(),
                    'accuracy_rate' => $this->percentage($rows->where('is_accurate', true)->count(), $total),
                    'caught_error_resi' => $rows->where('caught_error', true)->count(),
                    'escaped_error_resi' => $rows->where('escaped_error', true)->count(),
                    'first_pass_rate' => $this->percentage($rows->where('first_pass', true)->count(), $total),
                    'wrong_sku' => (int) $rows->sum('wrong_sku'),
                    'over_qty' => (int) $rows->sum('over_qty'),
                    'picker_fault' => (int) $rows->sum('picker_fault'),
                    'hold' => (int) $rows->sum('hold'),
                    'reset' => (int) $rows->sum('reset'),
                    'substitution' => (int) $rows->sum('substitution'),
                    'unknown_barcode' => (int) $rows->sum('unknown_barcode'),
                    'last_activity' => Carbon::parse($rows->max('started_at'))->format('Y-m-d H:i'),
                ];
            })
            // Picker dengan akurasi terendah tampil paling atas agar mudah ditindaklanjuti.
            ->sortBy([['accuracy_rate', 'asc'], ['total_resi', 'desc']])
            ->values();
    }

    private function eventRows(array $filters): Collection
    {
        $query = $this->baseQcQuery($filters)
            ->join('qc_resi_scan_events as ev', 'ev.qc_resi_scan_id', '=', 'qc.id')
            ->leftJoin('users as actor', 'actor.id', '=', 'ev.created_by')
            ->selectRaw('ev.id, ev.event_type, ev.scan_code, ev.sku, ev.expected_sku, ev.qty, ev.expected_qty, ev.scanned_qty,
                ev.reason_code, ev.reason, ev.occurred_at, qc.picker_employee_id, e.name as picker_name,
                r.id_pesanan, r.no_resi, COALESCE(actor.name, \'-\') as qc_operator');

        $rows = $query->orderByDesc('ev.occurred_at')->orderByDesc('ev.id')->get();
        $items = $this->itemLookup($rows->pluck('sku')->merge($rows->pluck('expected_sku'))->filter()->unique()->values());
        $labels = QcResiScanEvent::typeLabels();

        return $rows->map(function ($row) use ($items, $labels) {
            $action = match ($row->event_type) {
                QcResiScanEvent::TYPE_HOLD => QcReasonCategory::HOLD,
                QcResiScanEvent::TYPE_RESET => QcReasonCategory::RESET,
                QcResiScanEvent::TYPE_SUBSTITUTION => QcReasonCategory::SUBSTITUTION,
                default => null,
            };
            $sku = $row->sku ? $items->get(strtolower($row->sku)) : null;
            $expected = $row->expected_sku ? $items->get(strtolower($row->expected_sku)) : null;

            return [
                'id' => (int) $row->id,
                'occurred_at' => Carbon::parse($row->occurred_at)->format('Y-m-d H:i:s'),
                'event_type' => (string) $row->event_type,
                'event_label' => $labels[$row->event_type] ?? $row->event_type,
                'picker_id' => (int) $row->picker_employee_id,
                'picker' => (string) $row->picker_name,
                'qc_operator' => (string) $row->qc_operator,
                'id_pesanan' => (string) ($row->id_pesanan ?? '-'),
                'no_resi' => (string) ($row->no_resi ?? '-'),
                'scan_code' => (string) ($row->scan_code ?? ''),
                'sku' => (string) ($row->sku ?? ''),
                'sku_name' => $sku['name'] ?? '',
                'sku_location' => $sku['location'] ?? '',
                'expected_sku' => (string) ($row->expected_sku ?? ''),
                'expected_sku_name' => $expected['name'] ?? '',
                'expected_sku_location' => $expected['location'] ?? '',
                'qty' => $row->qty === null ? null : (int) $row->qty,
                'expected_qty' => $row->expected_qty === null ? null : (int) $row->expected_qty,
                'scanned_qty' => $row->scanned_qty === null ? null : (int) $row->scanned_qty,
                'reason_code' => (string) ($row->reason_code ?? ''),
                'reason_label' => $action ? (QcReasonCategory::label($action, $row->reason_code) ?? '') : '',
                'reason' => (string) ($row->reason ?? ''),
                'is_picker_fault' => in_array($row->event_type, [QcResiScanEvent::TYPE_WRONG_SKU, QcResiScanEvent::TYPE_OVER_QTY], true)
                    || QcReasonCategory::isPickerFault($row->reason_code),
            ];
        });
    }

    private function skuPairRows(Collection $events): Collection
    {
        return $events
            ->where('event_type', QcResiScanEvent::TYPE_WRONG_SKU)
            ->groupBy(fn (array $row) => strtolower($row['expected_sku']).'|'.strtolower($row['sku']))
            ->map(function (Collection $rows) {
                $first = $rows->first();

                return [
                    'expected_sku' => $first['expected_sku'] !== '' ? $first['expected_sku'] : '(lebih dari satu SKU tersisa)',
                    'expected_sku_name' => $first['expected_sku_name'],
                    'expected_sku_location' => $first['expected_sku_location'],
                    'picked_sku' => $first['sku'],
                    'picked_sku_name' => $first['sku_name'],
                    'picked_sku_location' => $first['sku_location'],
                    'occurrences' => $rows->count(),
                    'pickers' => $rows->pluck('picker')->unique()->count(),
                    'last_occurred_at' => $rows->max('occurred_at'),
                ];
            })
            ->sortByDesc('occurrences')
            ->values();
    }

    private function reasonRows(Collection $events): Collection
    {
        return $events
            ->whereIn('event_type', [QcResiScanEvent::TYPE_HOLD, QcResiScanEvent::TYPE_RESET, QcResiScanEvent::TYPE_SUBSTITUTION])
            ->groupBy(fn (array $row) => $row['event_type'].'|'.$row['reason_code'])
            ->map(function (Collection $rows) {
                $first = $rows->first();

                return [
                    'event_type' => $first['event_type'],
                    'event_label' => $first['event_label'],
                    'reason_code' => $first['reason_code'],
                    'reason_label' => $first['reason_label'] !== '' ? $first['reason_label'] : 'Tanpa kategori (data lama)',
                    'is_picker_fault' => QcReasonCategory::isPickerFault($first['reason_code']),
                    'occurrences' => $rows->count(),
                    'pickers' => $rows->pluck('picker')->unique()->count(),
                ];
            })
            ->sortByDesc('occurrences')
            ->values();
    }

    private function charts(Collection $resiRows, Collection $pickers): array
    {
        $daily = $resiRows->groupBy('date')->sortKeys()->map(function (Collection $rows, string $date) {
            $errors = $rows->where('is_accurate', false)->count();

            return [
                'date' => $date,
                'total_resi' => $rows->count(),
                'error_resi' => $errors,
                'accuracy_rate' => $this->percentage($rows->count() - $errors, $rows->count()),
            ];
        });

        return [
            'daily' => $daily->values()->all(),
            'pickers' => $pickers->map(fn (array $row) => [
                'picker' => $row['picker'],
                'accuracy_rate' => $row['accuracy_rate'],
                'error_resi' => $row['caught_error_resi'] + $row['escaped_error_resi'],
            ])->values()->all(),
        ];
    }

    private function unattributedResi(array $filters): int
    {
        $query = DB::table('qc_resi_scans as qc')
            ->whereNull('qc.picker_employee_id')
            ->where('qc.status', '!=', QcTransitStatus::CANCELED);
        $this->applyPeriod($query, 'qc.started_at', $filters);

        return $query->count();
    }

    private function baseQcQuery(array $filters)
    {
        $query = DB::table('qc_resi_scans as qc')
            ->join('employees as e', 'e.id', '=', 'qc.picker_employee_id')
            ->leftJoin('resis as r', 'r.id', '=', 'qc.resi_id')
            ->where('qc.status', '!=', QcTransitStatus::CANCELED);

        $this->applyPeriod($query, 'qc.started_at', $filters);

        if ($filters['picker_id']) {
            $query->where('qc.picker_employee_id', $filters['picker_id']);
        }

        if ($filters['q'] !== '') {
            $value = '%'.$filters['q'].'%';
            $query->where(function ($q) use ($value) {
                $q->where('e.name', 'like', $value)
                    ->orWhere('e.employee_code', 'like', $value)
                    ->orWhere('r.id_pesanan', 'like', $value)
                    ->orWhere('r.no_resi', 'like', $value)
                    ->orWhere('qc.scan_code', 'like', $value);
            });
        }

        return $query;
    }

    private function itemLookup(Collection $skus): Collection
    {
        if ($skus->isEmpty()) {
            return collect();
        }

        return DB::table('items as i')
            ->leftJoin('locations as l', 'l.id', '=', 'i.location_id')
            ->whereIn('i.sku', $skus->all())
            ->get(['i.sku', 'i.name', 'i.address', 'l.code as location_code'])
            ->mapWithKeys(fn ($row) => [strtolower((string) $row->sku) => [
                'name' => (string) ($row->name ?? ''),
                'location' => (string) ($row->location_code ?? $row->address ?? ''),
            ]]);
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

    private function percentage(int|float $part, int|float $total): float
    {
        return $total > 0 ? round(($part / $total) * 100, 2) : 0.0;
    }

    private function normalizeFilters(array $filters): array
    {
        return [
            'date_from' => $this->date($filters['date_from'] ?? null),
            'date_to' => $this->date($filters['date_to'] ?? null),
            'picker_id' => !empty($filters['picker_id']) ? (int) $filters['picker_id'] : null,
            'picker_name' => trim((string) ($filters['picker_name'] ?? '')),
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
