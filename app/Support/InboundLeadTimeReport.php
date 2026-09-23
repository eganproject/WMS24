<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InboundLeadTimeReport
{
    private const TYPE_LABELS = [
        'receipt' => 'Penerimaan Barang',
        'return' => 'Retur',
        'manual' => 'Manual',
        'opening' => 'Saldo Awal',
    ];

    private const STATUS_LABELS = [
        InboundScanStatus::PENDING_SCAN => 'Menunggu Scan',
        InboundScanStatus::SCANNING => 'Sedang Scan',
        InboundScanStatus::COMPLETED => 'Selesai',
        'approved' => 'Selesai',
    ];

    public function build(array $filters = []): array
    {
        $filters = $this->normalizeFilters($filters);
        $details = $this->detailRows($filters);
        $completed = $details->whereNotNull('completed_at');
        $started = $details->whereNotNull('started_at');
        $waitingDurations = $started->pluck('waiting_minutes')->filter(fn ($value) => $value !== null);
        $scanDurations = $completed->pluck('scan_minutes')->filter(fn ($value) => $value !== null);
        $leadDurations = $completed->pluck('lead_minutes')->filter(fn ($value) => $value !== null);
        $incomplete = $details->whereNull('completed_at');

        return [
            'period' => [
                'date_from' => $filters['date_from'],
                'date_to' => $filters['date_to'],
                'type' => $filters['type'],
                'status' => $filters['status'],
                'search' => $filters['q'],
            ],
            'summary' => [
                'total_documents' => $details->count(),
                'completed_documents' => $completed->count(),
                'pending_documents' => $details->where('status', InboundScanStatus::PENDING_SCAN)->count(),
                'scanning_documents' => $details->where('status', InboundScanStatus::SCANNING)->count(),
                'completion_rate' => $details->isNotEmpty() ? round($completed->count() * 100 / $details->count(), 2) : 0,
                'avg_waiting_minutes' => $this->average($waitingDurations),
                'avg_scan_minutes' => $this->average($scanDurations),
                'avg_lead_minutes' => $this->average($leadDurations),
                'max_lead_minutes' => $leadDurations->isNotEmpty() ? round((float) $leadDurations->max(), 2) : 0,
                'oldest_open_minutes' => $incomplete->isNotEmpty() ? round((float) $incomplete->max('aging_minutes'), 2) : 0,
                'total_expected_qty' => (int) $details->sum('expected_qty'),
                'total_scanned_qty' => (int) $details->sum('scanned_qty'),
                'variance_documents' => $completed->filter(fn (array $row) => $row['qty_variance'] !== 0)->count(),
                'reset_count' => (int) $details->sum('reset_count'),
            ],
            'charts' => [
                'daily' => $this->dailyRows($details)->values()->all(),
                'status' => $this->breakdown($details, 'status_label'),
                'types' => $this->breakdown($details, 'type_label'),
            ],
            'details' => $details->values()->all(),
        ];
    }

    private function detailRows(array $filters): Collection
    {
        $itemTotals = DB::table('inbound_items')
            ->selectRaw('inbound_transaction_id, COUNT(*) as total_sku, COALESCE(SUM(qty), 0) as expected_qty, COALESCE(SUM(koli), 0) as expected_koli')
            ->groupBy('inbound_transaction_id');

        $scanTotals = DB::table('inbound_scan_session_items')
            ->selectRaw('inbound_scan_session_id, COALESCE(SUM(expected_qty), 0) as session_expected_qty, COALESCE(SUM(expected_koli), 0) as session_expected_koli, COALESCE(SUM(scanned_qty), 0) as scanned_qty, COALESCE(SUM(scanned_koli), 0) as scanned_koli')
            ->groupBy('inbound_scan_session_id');

        $query = DB::table('inbound_transactions as inbound')
            ->leftJoin('inbound_scan_sessions as session', 'session.inbound_transaction_id', '=', 'inbound.id')
            ->leftJoinSub($itemTotals, 'item_totals', 'item_totals.inbound_transaction_id', '=', 'inbound.id')
            ->leftJoinSub($scanTotals, 'scan_totals', 'scan_totals.inbound_scan_session_id', '=', 'session.id')
            ->leftJoin('users as creator', 'creator.id', '=', 'inbound.created_by')
            ->leftJoin('users as starter', 'starter.id', '=', 'session.started_by')
            ->leftJoin('users as completer', 'completer.id', '=', 'session.completed_by')
            ->leftJoin('users as approver', 'approver.id', '=', 'inbound.approved_by')
            ->leftJoin('warehouses as warehouse', 'warehouse.id', '=', 'inbound.warehouse_id')
            ->leftJoin('suppliers as supplier', 'supplier.id', '=', 'inbound.supplier_id')
            ->selectRaw("inbound.id, inbound.code, inbound.type, inbound.status, inbound.ref_no, inbound.surat_jalan_no, inbound.transacted_at, inbound.created_at, inbound.approved_at, creator.name as creator_name, session.id as session_id, session.started_at, session.completed_at as session_completed_at, session.reset_count, starter.name as starter_name, COALESCE(completer.name, approver.name, '-') as completer_name, warehouse.name as warehouse_name, supplier.name as supplier_name, COALESCE(item_totals.total_sku, 0) as total_sku, COALESCE(scan_totals.session_expected_qty, item_totals.expected_qty, 0) as expected_qty, COALESCE(scan_totals.session_expected_koli, item_totals.expected_koli, 0) as expected_koli, COALESCE(scan_totals.scanned_qty, 0) as scanned_qty, COALESCE(scan_totals.scanned_koli, 0) as scanned_koli");

        if ($filters['date_from']) {
            $query->where('inbound.created_at', '>=', Carbon::parse($filters['date_from'])->startOfDay());
        }
        if ($filters['date_to']) {
            $query->where('inbound.created_at', '<=', Carbon::parse($filters['date_to'])->endOfDay());
        }
        if ($filters['type']) {
            $query->where('inbound.type', $filters['type']);
        }
        if ($filters['status']) {
            if ($filters['status'] === InboundScanStatus::COMPLETED) {
                $query->whereIn('inbound.status', [InboundScanStatus::COMPLETED, 'approved']);
            } else {
                $query->where('inbound.status', $filters['status']);
            }
        }
        if ($filters['q'] !== '') {
            $value = '%'.$filters['q'].'%';
            $query->where(function ($q) use ($value) {
                $q->where('inbound.code', 'like', $value)
                    ->orWhere('inbound.ref_no', 'like', $value)
                    ->orWhere('inbound.surat_jalan_no', 'like', $value)
                    ->orWhere('creator.name', 'like', $value)
                    ->orWhere('starter.name', 'like', $value)
                    ->orWhere('completer.name', 'like', $value)
                    ->orWhere('approver.name', 'like', $value)
                    ->orWhere('warehouse.name', 'like', $value)
                    ->orWhere('supplier.name', 'like', $value);
            });
        }

        $now = now();

        return $query->orderByDesc('inbound.created_at')->orderByDesc('inbound.id')->get()->map(function ($row) use ($now) {
            $createdAt = Carbon::parse($row->created_at);
            $startedAt = $row->started_at ? Carbon::parse($row->started_at) : null;
            $completedValue = $row->session_completed_at ?: $row->approved_at;
            $completedAt = $completedValue ? Carbon::parse($completedValue) : null;
            $status = $completedAt ? InboundScanStatus::COMPLETED : (string) ($row->status ?: InboundScanStatus::PENDING_SCAN);
            $expectedQty = (int) $row->expected_qty;
            $scannedQty = (int) $row->scanned_qty;
            $expectedKoli = (int) $row->expected_koli;
            $scannedKoli = (int) $row->scanned_koli;

            // Data approve lama tidak memiliki sesi scan. Anggap qty dokumen diterima penuh,
            // sama seperti fallback yang dipakai layar detail Scan Inbound.
            if ($completedAt && !$row->session_id) {
                $scannedQty = $expectedQty;
                $scannedKoli = $expectedKoli;
            }

            return [
                'id' => (int) $row->id,
                'code' => (string) $row->code,
                'type' => (string) $row->type,
                'type_label' => self::TYPE_LABELS[$row->type] ?? ucfirst((string) $row->type),
                'status' => $status,
                'status_label' => self::STATUS_LABELS[$status] ?? ucfirst(str_replace('_', ' ', $status)),
                'ref_no' => (string) ($row->ref_no ?: '-'),
                'surat_jalan_no' => (string) ($row->surat_jalan_no ?: '-'),
                'warehouse' => (string) ($row->warehouse_name ?: '-'),
                'supplier' => (string) ($row->supplier_name ?: '-'),
                'creator' => (string) ($row->creator_name ?: '-'),
                'starter' => (string) ($row->starter_name ?: '-'),
                'completer' => (string) ($row->completer_name ?: '-'),
                'transaction_at' => $row->transacted_at ? Carbon::parse($row->transacted_at)->format('Y-m-d H:i:s') : null,
                'created_at' => $createdAt->format('Y-m-d H:i:s'),
                'started_at' => $startedAt?->format('Y-m-d H:i:s'),
                'completed_at' => $completedAt?->format('Y-m-d H:i:s'),
                'waiting_minutes' => $startedAt ? $this->minutesBetween($createdAt, $startedAt) : null,
                'scan_minutes' => $startedAt && $completedAt ? $this->minutesBetween($startedAt, $completedAt) : null,
                'lead_minutes' => $completedAt ? $this->minutesBetween($createdAt, $completedAt) : null,
                'aging_minutes' => $completedAt ? null : $this->minutesBetween($createdAt, $now),
                'total_sku' => (int) $row->total_sku,
                'expected_qty' => $expectedQty,
                'scanned_qty' => $scannedQty,
                'qty_variance' => $scannedQty - $expectedQty,
                'expected_koli' => $expectedKoli,
                'scanned_koli' => $scannedKoli,
                'progress_percent' => $expectedQty > 0 ? round(min(100, $scannedQty * 100 / $expectedQty), 2) : 0,
                'reset_count' => (int) ($row->reset_count ?? 0),
            ];
        });
    }

    private function dailyRows(Collection $details): Collection
    {
        return $details->groupBy(fn (array $row) => substr($row['created_at'], 0, 10))
            ->map(function (Collection $rows, string $date) {
                $completed = $rows->whereNotNull('completed_at');
                $leads = $completed->pluck('lead_minutes')->filter(fn ($value) => $value !== null);

                return [
                    'date' => $date,
                    'created' => $rows->count(),
                    'completed' => $completed->count(),
                    'open' => $rows->count() - $completed->count(),
                    'avg_lead_minutes' => $this->average($leads),
                ];
            })->sortKeys();
    }

    private function breakdown(Collection $details, string $key): array
    {
        return $details->countBy($key)->sortDesc()->map(fn (int $total, string $label) => [
            'label' => $label,
            'total' => $total,
        ])->values()->all();
    }

    private function normalizeFilters(array $filters): array
    {
        $type = (string) ($filters['type'] ?? '');
        $status = (string) ($filters['status'] ?? '');

        return [
            'date_from' => $this->date($filters['date_from'] ?? null),
            'date_to' => $this->date($filters['date_to'] ?? null),
            'type' => array_key_exists($type, self::TYPE_LABELS) ? $type : null,
            'status' => in_array($status, [InboundScanStatus::PENDING_SCAN, InboundScanStatus::SCANNING, InboundScanStatus::COMPLETED], true) ? $status : null,
            'q' => trim((string) ($filters['q'] ?? '')),
        ];
    }

    private function average(Collection $values): float
    {
        return $values->isNotEmpty() ? round((float) $values->avg(), 2) : 0;
    }

    private function minutesBetween(Carbon $from, Carbon $to): float
    {
        return round(max(0, $to->getTimestamp() - $from->getTimestamp()) / 60, 2);
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
