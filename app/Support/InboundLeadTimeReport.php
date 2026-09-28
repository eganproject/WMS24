<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InboundLeadTimeReport
{
    private const ROLES = [
        'picker' => 'Picker',
        'packer' => 'Packer',
        'inbound' => 'Inbound',
        'customer_return' => 'Retur Customer',
    ];

    private const INBOUND_TYPES = [
        'receipt' => 'Penerimaan Barang',
        'return' => 'Retur',
        'manual' => 'Manual',
        'opening' => 'Saldo Awal',
    ];

    public function build(array $filters = []): array
    {
        $filters = $this->normalizeFilters($filters);
        $details = collect();
        $sources = [
            'picker' => fn () => $this->pickerRows($filters),
            'packer' => fn () => $this->packerRows($filters),
            'inbound' => fn () => $this->inboundRows($filters),
            'customer_return' => fn () => $this->customerReturnRows($filters),
        ];
        foreach ($sources as $role => $source) {
            if (! $filters['role'] || $filters['role'] === $role) {
                $details = $details->concat($source());
            }
        }

        $details = $details
            ->filter(fn (array $row) => $this->matchesStatus($row, $filters['status']))
            ->sortByDesc('started_at')->values();
        $completed = $details->where('status', 'completed');
        $open = $details->where('status', 'open');
        $durations = $completed->pluck('lead_minutes')->filter(fn ($value) => $value !== null);
        $inbound = $details->where('role', 'inbound');

        return [
            'period' => [
                'date_from' => $filters['date_from'], 'date_to' => $filters['date_to'],
                'role' => $filters['role'], 'type' => $filters['type'],
                'status' => $filters['status'], 'search' => $filters['q'],
            ],
            'summary' => [
                'total_documents' => $details->count(),
                'completed_documents' => $completed->count(),
                'open_documents' => $open->count(),
                'pending_documents' => $inbound->where('source_status', InboundScanStatus::PENDING_SCAN)->count(),
                'scanning_documents' => $inbound->where('source_status', InboundScanStatus::SCANNING)->count(),
                'completion_rate' => $this->rate($completed->count(), $details->count()),
                'avg_lead_minutes' => $this->average($durations),
                'median_lead_minutes' => $this->percentile($durations, 50),
                'p90_lead_minutes' => $this->percentile($durations, 90),
                'max_lead_minutes' => $durations->isNotEmpty() ? round((float) $durations->max(), 2) : 0,
                'oldest_open_minutes' => $open->isNotEmpty() ? round((float) $open->max('aging_minutes'), 2) : 0,
                'missing_pic_documents' => $details->where('pic_missing', true)->count(),
                'missing_position_documents' => $details->where('position_missing', true)->count(),
                // Kept for existing endpoint consumers.
                'avg_waiting_minutes' => $this->average($inbound->pluck('waiting_minutes')->filter(fn ($value) => $value !== null)),
                'avg_scan_minutes' => $this->average($inbound->pluck('scan_minutes')->filter(fn ($value) => $value !== null)),
                'total_expected_qty' => (int) $inbound->sum('expected_qty'),
                'total_scanned_qty' => (int) $inbound->sum('scanned_qty'),
                'variance_documents' => $inbound->where('status', 'completed')->filter(fn ($row) => $row['qty_variance'] !== 0)->count(),
                'reset_count' => (int) $inbound->sum('reset_count'),
            ],
            'roles' => collect(self::ROLES)->map(fn ($label, $key) => $this->performance($details->where('role', $key), [
                'key' => $key, 'label' => $label, 'definition' => $this->definition($key),
            ]))->values()->all(),
            'positions' => $this->groupPerformance($details, fn ($row) => $row['role'].'|'.$row['position'], fn ($rows) => [
                'role' => $rows->first()['role'], 'role_label' => $rows->first()['role_label'], 'position' => $rows->first()['position'],
            ]),
            'operators' => $this->groupPerformance($details->where('pic_missing', false), fn ($row) => $row['role'].'|'.$row['pic'].'|'.$row['position'], fn ($rows) => [
                'role' => $rows->first()['role'], 'role_label' => $rows->first()['role_label'],
                'pic' => $rows->first()['pic'], 'position' => $rows->first()['position'],
            ]),
            'charts' => [
                'daily' => $this->dailyRows($details),
                'status' => [['label' => 'Selesai', 'total' => $completed->count()], ['label' => 'Belum Selesai', 'total' => $open->count()]],
            ],
            'details' => $details->all(),
        ];
    }

    private function pickerRows(array $filters): Collection
    {
        $items = DB::table('resi_details')->selectRaw('resi_id, COUNT(*) total_sku, COALESCE(SUM(qty), 0) total_qty')->groupBy('resi_id');
        $query = DB::table('resis as r')
            ->leftJoin('qc_resi_scans as q', 'q.resi_id', '=', 'r.id')
            ->leftJoinSub($items, 'it', 'it.resi_id', '=', 'r.id')
            ->leftJoin('users as uploader', 'uploader.id', '=', 'r.uploader_id')
            ->leftJoin('users as starter', 'starter.id', '=', 'q.scanned_by')
            ->leftJoin('users as completer', 'completer.id', '=', 'q.completed_by')
            ->leftJoin('employees as e', 'e.user_id', '=', DB::raw('COALESCE(q.completed_by, q.scanned_by)'))
            ->leftJoin('employee_positions as p', 'p.id', '=', 'e.position_id')
            ->selectRaw('r.id, r.id_pesanan, r.no_resi, r.created_at, q.status source_status, q.completed_at, uploader.name start_actor, starter.name starter_name, completer.name end_actor, e.name employee_name, COALESCE(p.name, e.position) position_name, COALESCE(it.total_sku, 0) total_sku, COALESCE(it.total_qty, 0) total_qty');
        $this->applyDate($query, 'r.created_at', $filters);
        $query->where(fn ($q) => $q->whereNull('r.status')->orWhere('r.status', '!=', 'canceled'));
        $this->applySearch($query, $filters['q'], ['r.id_pesanan', 'r.no_resi', 'uploader.name', 'starter.name', 'completer.name', 'e.name', 'p.name']);

        return $query->get()->map(function ($data) {
            $end = $data->completed_at ? Carbon::parse($data->completed_at) : null;

            return $this->row([
                'key' => 'picker:'.$data->id, 'source_id' => (int) $data->id, 'role' => 'picker',
                'code' => (string) ($data->no_resi ?: $data->id_pesanan), 'reference' => (string) $data->id_pesanan,
                'source_status' => (string) ($data->source_status ?: 'not_started'),
                'stage_label' => $end ? 'QC Selesai' : ($data->source_status === 'hold' ? 'QC Ditunda' : ($data->source_status ? 'QC Berjalan' : 'Belum QC')),
                'started_at' => Carbon::parse($data->created_at), 'completed_at' => $end,
                'pic' => $data->employee_name ?: $data->end_actor ?: $data->starter_name,
                'position' => $data->position_name, 'start_actor' => $data->start_actor, 'end_actor' => $data->end_actor,
                'total_sku' => (int) $data->total_sku, 'total_qty' => (int) $data->total_qty,
            ]);
        });
    }

    private function packerRows(array $filters): Collection
    {
        $items = DB::table('resi_details')->selectRaw('resi_id, COUNT(*) total_sku, COALESCE(SUM(qty), 0) total_qty')->groupBy('resi_id');
        $query = DB::table('qc_resi_scans as q')
            ->join('resis as r', 'r.id', '=', 'q.resi_id')
            ->leftJoin('shipment_scan_outs as so', 'so.resi_id', '=', 'r.id')
            ->leftJoinSub($items, 'it', 'it.resi_id', '=', 'r.id')
            ->leftJoin('users as qc_user', 'qc_user.id', '=', 'q.completed_by')
            ->leftJoin('users as scan_user', 'scan_user.id', '=', 'so.scanned_by')
            ->leftJoin('employees as pe', 'pe.id', '=', 'so.packed_employee_id')
            ->leftJoin('employee_positions as pp', 'pp.id', '=', 'pe.position_id')
            ->leftJoin('employees as se', 'se.user_id', '=', 'so.scanned_by')
            ->leftJoin('employee_positions as sp', 'sp.id', '=', 'se.position_id')
            ->whereNotNull('q.completed_at')->where('q.status', 'passed')
            ->selectRaw('q.id, r.id_pesanan, r.no_resi, q.completed_at, so.scanned_at, qc_user.name start_actor, scan_user.name end_actor, pe.name packer_name, COALESCE(pp.name, pe.position, sp.name, se.position) position_name, COALESCE(it.total_sku, 0) total_sku, COALESCE(it.total_qty, 0) total_qty');
        $this->applyDate($query, 'q.completed_at', $filters);
        $this->applySearch($query, $filters['q'], ['r.id_pesanan', 'r.no_resi', 'qc_user.name', 'scan_user.name', 'pe.name', 'pp.name', 'sp.name']);

        return $query->get()->map(function ($data) {
            $end = $data->scanned_at ? Carbon::parse($data->scanned_at) : null;

            return $this->row([
                'key' => 'packer:'.$data->id, 'source_id' => (int) $data->id, 'role' => 'packer',
                'code' => (string) ($data->no_resi ?: $data->id_pesanan), 'reference' => (string) $data->id_pesanan,
                'source_status' => $end ? 'scanned_out' : 'ready_scan_out',
                'stage_label' => $end ? 'Scan Out Selesai' : 'Menunggu Scan Out',
                'started_at' => Carbon::parse($data->completed_at), 'completed_at' => $end,
                'pic' => $data->packer_name ?: $data->end_actor, 'position' => $data->position_name,
                'start_actor' => $data->start_actor, 'end_actor' => $data->end_actor,
                'total_sku' => (int) $data->total_sku, 'total_qty' => (int) $data->total_qty,
            ]);
        });
    }

    private function inboundRows(array $filters): Collection
    {
        $items = DB::table('inbound_items')->selectRaw('inbound_transaction_id, COUNT(*) total_sku, COALESCE(SUM(qty), 0) expected_qty')->groupBy('inbound_transaction_id');
        $scans = DB::table('inbound_scan_session_items')->selectRaw('inbound_scan_session_id, COALESCE(SUM(scanned_qty), 0) scanned_qty')->groupBy('inbound_scan_session_id');
        $query = DB::table('inbound_transactions as i')
            ->leftJoin('inbound_scan_sessions as s', 's.inbound_transaction_id', '=', 'i.id')
            ->leftJoinSub($items, 'it', 'it.inbound_transaction_id', '=', 'i.id')
            ->leftJoinSub($scans, 'st', 'st.inbound_scan_session_id', '=', 's.id')
            ->leftJoin('users as creator', 'creator.id', '=', 'i.created_by')
            ->leftJoin('users as starter', 'starter.id', '=', 's.started_by')
            ->leftJoin('users as completer', 'completer.id', '=', 's.completed_by')
            ->leftJoin('users as approver', 'approver.id', '=', 'i.approved_by')
            ->leftJoin('employees as e', 'e.user_id', '=', DB::raw('COALESCE(s.completed_by, s.started_by, i.approved_by, i.created_by)'))
            ->leftJoin('employee_positions as p', 'p.id', '=', 'e.position_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'i.warehouse_id')
            ->leftJoin('suppliers as supplier', 'supplier.id', '=', 'i.supplier_id')
            ->selectRaw('i.id, i.code, i.type, i.status source_status, i.ref_no, i.created_at, i.approved_at, s.id session_id, s.started_at scan_started_at, s.completed_at, s.reset_count, creator.name start_actor, starter.name starter_name, COALESCE(completer.name, approver.name) end_actor, e.name employee_name, COALESCE(p.name, e.position) position_name, w.name warehouse_name, supplier.name supplier_name, COALESCE(it.total_sku, 0) total_sku, COALESCE(it.expected_qty, 0) expected_qty, COALESCE(st.scanned_qty, 0) scanned_qty');
        $this->applyDate($query, 'i.created_at', $filters);
        if ($filters['type']) {
            $query->where('i.type', $filters['type']);
        }
        $this->applySearch($query, $filters['q'], ['i.code', 'i.ref_no', 'creator.name', 'starter.name', 'completer.name', 'approver.name', 'e.name', 'p.name', 'w.name', 'supplier.name']);

        return $query->get()->map(function ($data) {
            $start = Carbon::parse($data->created_at);
            $scanStart = $data->scan_started_at ? Carbon::parse($data->scan_started_at) : null;
            $completedValue = $data->completed_at ?: $data->approved_at;
            $end = $completedValue ? Carbon::parse($completedValue) : null;
            $expected = (int) $data->expected_qty;
            $scanned = $end && ! $data->session_id ? $expected : (int) $data->scanned_qty;

            return $this->row([
                'key' => 'inbound:'.$data->id, 'source_id' => (int) $data->id, 'role' => 'inbound',
                'code' => (string) $data->code, 'reference' => (string) ($data->ref_no ?: $data->supplier_name ?: '-'),
                'source_status' => (string) ($end ? InboundScanStatus::COMPLETED : ($data->source_status ?: InboundScanStatus::PENDING_SCAN)),
                'stage_label' => $end ? 'Inbound Completed' : ($data->source_status === InboundScanStatus::SCANNING ? 'Sedang Scan' : 'Menunggu Scan'),
                'started_at' => $start, 'completed_at' => $end,
                'pic' => $data->employee_name ?: $data->end_actor ?: $data->starter_name ?: $data->start_actor,
                'position' => $data->position_name, 'start_actor' => $data->start_actor, 'end_actor' => $data->end_actor,
                'total_sku' => (int) $data->total_sku, 'total_qty' => $expected,
                'type' => (string) $data->type, 'type_label' => self::INBOUND_TYPES[$data->type] ?? ucfirst((string) $data->type),
                'warehouse' => (string) ($data->warehouse_name ?: '-'),
                'waiting_minutes' => $scanStart ? $this->minutes($start, $scanStart) : null,
                'scan_minutes' => $scanStart && $end ? $this->minutes($scanStart, $end) : null,
                'expected_qty' => $expected, 'scanned_qty' => $scanned, 'qty_variance' => $scanned - $expected,
                'reset_count' => (int) ($data->reset_count ?? 0),
            ]);
        });
    }

    private function customerReturnRows(array $filters): Collection
    {
        $items = DB::table('customer_return_items')->selectRaw('customer_return_id, COUNT(*) total_sku, COALESCE(SUM(received_qty), 0) total_qty')->groupBy('customer_return_id');
        $query = DB::table('customer_returns as cr')
            ->leftJoinSub($items, 'it', 'it.customer_return_id', '=', 'cr.id')
            ->leftJoin('users as creator', 'creator.id', '=', 'cr.created_by')
            ->leftJoin('users as inspector', 'inspector.id', '=', 'cr.inspected_by')
            ->leftJoin('users as finalizer', 'finalizer.id', '=', 'cr.finalized_by')
            ->leftJoin('employees as e', 'e.user_id', '=', DB::raw('COALESCE(cr.finalized_by, cr.inspected_by, cr.created_by)'))
            ->leftJoin('employee_positions as p', 'p.id', '=', 'e.position_id')
            ->selectRaw('cr.id, cr.code, cr.resi_no, cr.order_ref, cr.status source_status, cr.received_at, cr.finalized_at, creator.name start_actor, inspector.name inspector_name, finalizer.name end_actor, e.name employee_name, COALESCE(p.name, e.position) position_name, COALESCE(it.total_sku, 0) total_sku, COALESCE(it.total_qty, 0) total_qty');
        $this->applyDate($query, 'cr.received_at', $filters);
        $this->applySearch($query, $filters['q'], ['cr.code', 'cr.resi_no', 'cr.order_ref', 'creator.name', 'inspector.name', 'finalizer.name', 'e.name', 'p.name']);

        return $query->get()->map(function ($data) {
            $end = $data->finalized_at ? Carbon::parse($data->finalized_at) : null;

            return $this->row([
                'key' => 'customer_return:'.$data->id, 'source_id' => (int) $data->id, 'role' => 'customer_return',
                'code' => (string) $data->code, 'reference' => (string) ($data->resi_no ?: $data->order_ref ?: '-'),
                'source_status' => (string) $data->source_status,
                'stage_label' => $end ? ($data->source_status === 'no_received' ? 'Finalisasi: Tidak Diterima' : 'Finalisasi Selesai') : 'Menunggu Finalisasi',
                'started_at' => Carbon::parse($data->received_at), 'completed_at' => $end,
                'pic' => $data->employee_name ?: $data->end_actor ?: $data->inspector_name ?: $data->start_actor,
                'position' => $data->position_name, 'start_actor' => $data->start_actor, 'end_actor' => $data->end_actor,
                'total_sku' => (int) $data->total_sku, 'total_qty' => (int) $data->total_qty,
            ]);
        });
    }

    private function row(array $values): array
    {
        $start = $values['started_at'];
        $end = $values['completed_at'];
        $picMissing = empty($values['pic']);
        $positionMissing = empty($values['position']);
        $defaults = [
            'role_label' => self::ROLES[$values['role']], 'process_label' => $this->definition($values['role']),
            'status' => $end ? 'completed' : 'open', 'status_label' => $end ? 'Selesai' : 'Belum Selesai',
            'lead_minutes' => $end ? $this->minutes($start, $end) : null,
            'aging_minutes' => $end ? null : $this->minutes($start, now()),
            'pic' => $values['pic'] ?: 'PIC belum tercatat', 'position' => $values['position'] ?: 'Jabatan belum terhubung',
            'pic_missing' => $picMissing, 'position_missing' => $positionMissing,
            'start_actor' => $values['start_actor'] ?: '-', 'end_actor' => $values['end_actor'] ?: '-',
            'total_sku' => (int) ($values['total_sku'] ?? 0), 'total_qty' => (int) ($values['total_qty'] ?? 0),
            'waiting_minutes' => null, 'scan_minutes' => null, 'expected_qty' => 0,
            'scanned_qty' => 0, 'qty_variance' => 0, 'reset_count' => 0,
        ];

        return array_merge($defaults, $values, [
            'started_at' => $start->format('Y-m-d H:i:s'), 'completed_at' => $end?->format('Y-m-d H:i:s'),
            'lead_minutes' => $end ? $this->minutes($start, $end) : null,
            'aging_minutes' => $end ? null : $this->minutes($start, now()),
            'pic' => $values['pic'] ?: 'PIC belum tercatat', 'position' => $values['position'] ?: 'Jabatan belum terhubung',
            'pic_missing' => $picMissing, 'position_missing' => $positionMissing,
        ]);
    }

    private function groupPerformance(Collection $rows, callable $key, callable $identity): array
    {
        return $rows->groupBy($key)->map(fn ($group) => $this->performance($group, $identity($group)))
            ->sortBy([['role_label', 'asc'], ['avg_lead_minutes', 'asc']])->values()->all();
    }

    private function performance(Collection $rows, array $identity): array
    {
        $completed = $rows->where('status', 'completed');
        $open = $rows->where('status', 'open');
        $durations = $completed->pluck('lead_minutes')->filter(fn ($value) => $value !== null);

        return array_merge($identity, [
            'total' => $rows->count(), 'completed' => $completed->count(), 'open' => $open->count(),
            'completion_rate' => $this->rate($completed->count(), $rows->count()),
            'avg_lead_minutes' => $this->average($durations), 'median_lead_minutes' => $this->percentile($durations, 50),
            'p90_lead_minutes' => $this->percentile($durations, 90),
            'max_lead_minutes' => $durations->isNotEmpty() ? round((float) $durations->max(), 2) : 0,
            'oldest_open_minutes' => $open->isNotEmpty() ? round((float) $open->max('aging_minutes'), 2) : 0,
        ]);
    }

    private function dailyRows(Collection $details): array
    {
        return $details->groupBy(fn ($row) => substr($row['started_at'], 0, 10).'|'.$row['role'])->map(function ($rows) {
            $completed = $rows->where('status', 'completed');
            $first = $rows->first();

            return [
                'date' => substr($first['started_at'], 0, 10), 'role' => $first['role'], 'role_label' => $first['role_label'],
                'started' => $rows->count(), 'completed' => $completed->count(), 'open' => $rows->where('status', 'open')->count(),
                'avg_lead_minutes' => $this->average($completed->pluck('lead_minutes')->filter(fn ($value) => $value !== null)),
            ];
        })->sortBy('date')->values()->all();
    }

    private function definition(string $role): string
    {
        return match ($role) {
            'picker' => 'Input resi → QC scan selesai',
            'packer' => 'QC selesai → Scan out selesai',
            'inbound' => 'Input receipt → Completion',
            'customer_return' => 'Input retur → Finalisasi',
        };
    }

    private function normalizeFilters(array $filters): array
    {
        $role = (string) ($filters['role'] ?? '');
        $type = (string) ($filters['type'] ?? '');
        $status = (string) ($filters['status'] ?? '');
        $normalizedRole = array_key_exists($role, self::ROLES) ? $role : null;
        $normalizedType = array_key_exists($type, self::INBOUND_TYPES) ? $type : null;
        if ($normalizedType && ! $normalizedRole) {
            $normalizedRole = 'inbound';
        }

        return [
            'date_from' => $this->date($filters['date_from'] ?? null), 'date_to' => $this->date($filters['date_to'] ?? null),
            'role' => $normalizedRole,
            'type' => $normalizedType,
            'status' => in_array($status, ['open', 'completed', InboundScanStatus::PENDING_SCAN, InboundScanStatus::SCANNING], true) ? $status : null,
            'q' => trim((string) ($filters['q'] ?? '')),
        ];
    }

    private function matchesStatus(array $row, ?string $status): bool
    {
        if (! $status) {
            return true;
        }
        if (in_array($status, [InboundScanStatus::PENDING_SCAN, InboundScanStatus::SCANNING], true)) {
            return $row['role'] === 'inbound' && $row['source_status'] === $status;
        }

        return $row['status'] === $status;
    }

    private function applyDate($query, string $column, array $filters): void
    {
        if ($filters['date_from']) {
            $query->where($column, '>=', Carbon::parse($filters['date_from'])->startOfDay());
        }
        if ($filters['date_to']) {
            $query->where($column, '<=', Carbon::parse($filters['date_to'])->endOfDay());
        }
    }

    private function applySearch($query, string $search, array $columns): void
    {
        if ($search === '') {
            return;
        }
        $query->where(function ($q) use ($columns, $search) {
            foreach ($columns as $index => $column) {
                $q->{$index ? 'orWhere' : 'where'}($column, 'like', '%'.$search.'%');
            }
        });
    }

    private function average(Collection $values): float
    {
        return $values->isNotEmpty() ? round((float) $values->avg(), 2) : 0;
    }

    private function percentile(Collection $values, int $percentile): float
    {
        $sorted = $values->map(fn ($value) => (float) $value)->sort()->values();
        if ($sorted->isEmpty()) {
            return 0;
        }

        return round((float) $sorted[max(0, (int) ceil($percentile / 100 * $sorted->count()) - 1)], 2);
    }

    private function rate(int $part, int $total): float
    {
        return $total ? round($part * 100 / $total, 2) : 0;
    }

    private function minutes(Carbon $from, Carbon $to): float
    {
        return round(max(0, $to->getTimestamp() - $from->getTimestamp()) / 60, 2);
    }

    private function date(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }
        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
