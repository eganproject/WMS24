<?php

namespace App\Support;

use App\Models\OutboundItem;
use App\Models\OutboundTransaction;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Satu sumber filter untuk tabel dan seluruh sheet export Outbound Manual. */
class OutboundManualReport
{
    private ?int $documentCount = null;

    private ?int $detailCount = null;

    private ?object $totals = null;

    private ?Collection $dailySummary = null;

    private ?Collection $statusSummary = null;

    private ?Collection $warehouseSummary = null;

    private ?Collection $recipientSummary = null;

    private ?Collection $itemSummary = null;

    public function __construct(private array $filters = [])
    {
    }

    public function transactionQuery(bool $withRelations = true, bool $ordered = true): Builder
    {
        $query = OutboundTransaction::query()
            ->where('outbound_transactions.type', 'manual');

        if ($withRelations) {
            $query->with([
                'items.item:id,sku,name,koli_qty',
                'creator:id,name',
                'warehouse:id,code,name',
                'supplier:id,name',
                'qcSession.items',
            ])->select([
                'outbound_transactions.id',
                'outbound_transactions.code',
                'outbound_transactions.transacted_at',
                'outbound_transactions.type',
                'outbound_transactions.ref_no',
                'outbound_transactions.supplier_id',
                'outbound_transactions.recipient_name',
                'outbound_transactions.recipient_phone',
                'outbound_transactions.recipient_address',
                'outbound_transactions.surat_jalan_no',
                'outbound_transactions.surat_jalan_at',
                'outbound_transactions.note',
                'outbound_transactions.warehouse_id',
                'outbound_transactions.status',
                'outbound_transactions.created_by',
                'outbound_transactions.approved_at',
            ]);
        }

        $this->applySearch($query);
        $this->applyDateFilter($query);
        $this->applyWarehouseFilter($query);
        $this->applyStatusFilter($query);

        if ($ordered) {
            $query->orderByDesc('outbound_transactions.transacted_at')
                ->orderByDesc('outbound_transactions.id');
        }

        return $query;
    }

    public function baseQuery(): Builder
    {
        return $this->transactionQuery(false, false);
    }

    public function documentCount(): int
    {
        return $this->documentCount ??= $this->baseQuery()->count();
    }

    public function detailCount(): int
    {
        return $this->detailCount ??= $this->detailRowsQuery()->reorder()->count();
    }

    public function documentRowsQuery(): Builder
    {
        $itemStats = DB::table('outbound_items')
            ->select('outbound_transaction_id')
            ->selectRaw('COUNT(DISTINCT item_id) as sku_count')
            ->selectRaw('SUM(qty) as planned_qty')
            ->groupBy('outbound_transaction_id');
        $qcStats = DB::table('outbound_qc_sessions as oqs')
            ->join('outbound_qc_session_items as oqsi', 'oqsi.outbound_qc_session_id', '=', 'oqs.id')
            ->select('oqs.outbound_transaction_id')
            ->selectRaw('SUM(oqsi.expected_qty) as expected_qty')
            ->selectRaw('SUM(oqsi.scanned_qty) as scanned_qty')
            ->groupBy('oqs.outbound_transaction_id');

        return $this->baseQuery()
            ->leftJoinSub($itemStats, 'item_stats', 'item_stats.outbound_transaction_id', '=', 'outbound_transactions.id')
            ->leftJoinSub($qcStats, 'qc_stats', 'qc_stats.outbound_transaction_id', '=', 'outbound_transactions.id')
            ->leftJoin('warehouses as report_warehouses', 'report_warehouses.id', '=', 'outbound_transactions.warehouse_id')
            ->leftJoin('users as report_users', 'report_users.id', '=', 'outbound_transactions.created_by')
            ->select([
                'outbound_transactions.id',
                'outbound_transactions.code',
                'outbound_transactions.transacted_at',
                'outbound_transactions.ref_no',
                'outbound_transactions.recipient_name',
                'outbound_transactions.recipient_phone',
                'outbound_transactions.recipient_address',
                'outbound_transactions.surat_jalan_no',
                'outbound_transactions.surat_jalan_at',
                'outbound_transactions.status',
                'outbound_transactions.note',
                'outbound_transactions.approved_at',
                'report_warehouses.code as warehouse_code',
                'report_warehouses.name as warehouse_name',
                'report_users.name as creator_name',
            ])
            ->selectRaw('COALESCE(item_stats.sku_count, 0) as sku_count')
            ->selectRaw('COALESCE(item_stats.planned_qty, 0) as planned_qty')
            ->selectRaw($this->expectedExpression().' as expected_qty')
            ->selectRaw($this->scannedExpression().' as scanned_qty')
            ->orderByDesc('outbound_transactions.transacted_at')
            ->orderByDesc('outbound_transactions.id');
    }

    public function detailRowsQuery(): Builder
    {
        $filteredIds = $this->baseQuery()->select('outbound_transactions.id');

        return OutboundItem::query()
            ->join('outbound_transactions as report_transactions', 'report_transactions.id', '=', 'outbound_items.outbound_transaction_id')
            ->leftJoin('items as report_items', 'report_items.id', '=', 'outbound_items.item_id')
            ->leftJoin('warehouses as report_warehouses', 'report_warehouses.id', '=', 'report_transactions.warehouse_id')
            ->leftJoin('outbound_qc_sessions as report_qc_sessions', 'report_qc_sessions.outbound_transaction_id', '=', 'report_transactions.id')
            ->leftJoin('outbound_qc_session_items as report_qc_items', function ($join) {
                $join->on('report_qc_items.outbound_qc_session_id', '=', 'report_qc_sessions.id')
                    ->on('report_qc_items.item_id', '=', 'outbound_items.item_id');
            })
            ->whereIn('outbound_items.outbound_transaction_id', $filteredIds)
            ->select([
                'outbound_items.id as outbound_item_id',
                'outbound_items.outbound_transaction_id',
                'outbound_items.item_id',
                'outbound_items.qty',
                'outbound_items.note as item_note',
                'report_transactions.code',
                'report_transactions.transacted_at',
                'report_transactions.status',
                'report_transactions.recipient_name',
                'report_transactions.surat_jalan_no',
                'report_transactions.warehouse_id',
                'report_warehouses.code as warehouse_code',
                'report_warehouses.name as warehouse_name',
                'report_items.sku',
                'report_items.name as item_name',
                'report_items.koli_qty',
            ])
            ->selectRaw('COALESCE(report_qc_items.expected_qty, outbound_items.qty) as expected_qty')
            ->selectRaw("CASE WHEN report_qc_items.id IS NOT NULL THEN report_qc_items.scanned_qty WHEN report_transactions.status = 'approved' THEN outbound_items.qty ELSE 0 END as scanned_qty")
            ->orderByDesc('report_transactions.transacted_at')
            ->orderByDesc('report_transactions.id')
            ->orderBy('report_items.sku');
    }

    public function totals(): object
    {
        if ($this->totals !== null) {
            return $this->totals;
        }

        $stats = DB::query()->fromSub($this->documentRowsQuery()->reorder(), 'document_stats');

        return $this->totals = $stats
            ->selectRaw('COUNT(*) as document_count')
            ->selectRaw('COALESCE(SUM(sku_count), 0) as document_sku_count')
            ->selectRaw('COALESCE(SUM(planned_qty), 0) as planned_qty')
            ->selectRaw('COALESCE(SUM(expected_qty), 0) as expected_qty')
            ->selectRaw('COALESCE(SUM(scanned_qty), 0) as scanned_qty')
            ->first();
    }

    public function dailySummary(): Collection
    {
        if ($this->dailySummary !== null) {
            return $this->dailySummary;
        }

        return $this->dailySummary = DB::query()
            ->fromSub($this->documentRowsQuery()->reorder(), 'document_stats')
            ->selectRaw('DATE(transacted_at) as period_date')
            ->selectRaw('COUNT(*) as document_count')
            ->selectRaw('SUM(sku_count) as sku_count')
            ->selectRaw('SUM(planned_qty) as planned_qty')
            ->selectRaw('SUM(expected_qty) as expected_qty')
            ->selectRaw('SUM(scanned_qty) as scanned_qty')
            ->groupByRaw('DATE(transacted_at)')
            ->orderByDesc('period_date')
            ->get();
    }

    public function statusSummary(): Collection
    {
        if ($this->statusSummary !== null) {
            return $this->statusSummary;
        }

        return $this->statusSummary = DB::query()
            ->fromSub($this->documentRowsQuery()->reorder(), 'document_stats')
            ->select('status')
            ->selectRaw('COUNT(*) as document_count')
            ->selectRaw('SUM(planned_qty) as planned_qty')
            ->selectRaw('SUM(expected_qty) as expected_qty')
            ->selectRaw('SUM(scanned_qty) as scanned_qty')
            ->groupBy('status')
            ->orderByDesc('document_count')
            ->get();
    }

    public function warehouseSummary(): Collection
    {
        if ($this->warehouseSummary !== null) {
            return $this->warehouseSummary;
        }

        return $this->warehouseSummary = DB::query()
            ->fromSub($this->documentRowsQuery()->reorder(), 'document_stats')
            ->select(['warehouse_code', 'warehouse_name'])
            ->selectRaw('COUNT(*) as document_count')
            ->selectRaw('SUM(sku_count) as sku_count')
            ->selectRaw('SUM(planned_qty) as planned_qty')
            ->selectRaw('SUM(expected_qty) as expected_qty')
            ->selectRaw('SUM(scanned_qty) as scanned_qty')
            ->groupBy('warehouse_code', 'warehouse_name')
            ->orderByDesc('planned_qty')
            ->get();
    }

    public function recipientSummary(int $limit = 10): Collection
    {
        if ($this->recipientSummary !== null) {
            return $this->recipientSummary->take($limit)->values();
        }

        $this->recipientSummary = DB::query()
            ->fromSub($this->documentRowsQuery()->reorder(), 'document_stats')
            ->select('recipient_name')
            ->selectRaw('COUNT(*) as document_count')
            ->selectRaw('SUM(planned_qty) as planned_qty')
            ->groupBy('recipient_name')
            ->orderByDesc('planned_qty')
            ->get();

        return $this->recipientSummary->take($limit)->values();
    }

    public function itemSummary(): Collection
    {
        if ($this->itemSummary !== null) {
            return $this->itemSummary;
        }

        return $this->itemSummary = DB::query()
            ->fromSub($this->detailRowsQuery()->reorder(), 'detail_stats')
            ->select(['item_id', 'sku', 'item_name'])
            ->selectRaw('COUNT(DISTINCT outbound_transaction_id) as document_count')
            ->selectRaw('SUM(qty) as planned_qty')
            ->selectRaw('SUM(expected_qty) as expected_qty')
            ->selectRaw('SUM(scanned_qty) as scanned_qty')
            ->selectRaw('AVG(qty) as average_qty')
            ->selectRaw('MAX(qty) as maximum_qty')
            ->selectRaw('MAX(transacted_at) as last_outbound_at')
            ->groupBy('item_id', 'sku', 'item_name')
            ->orderByDesc('planned_qty')
            ->get();
    }

    public function filterSummary(): string
    {
        $parts = [];
        $dateFrom = trim((string) ($this->filters['date_from'] ?? ''));
        $dateTo = trim((string) ($this->filters['date_to'] ?? ''));
        $parts[] = ($dateFrom !== '' || $dateTo !== '')
            ? 'Periode: '.($dateFrom ?: 'awal').' s/d '.($dateTo ?: 'akhir')
            : 'Periode: Semua tanggal';

        $warehouseId = $this->filters['warehouse_id'] ?? null;
        $parts[] = ($warehouseId === null || $warehouseId === '' || $warehouseId === 'all')
            ? 'Gudang: Semua Gudang'
            : 'Gudang: '.(Warehouse::whereKey((int) $warehouseId)->value('name') ?? $warehouseId);

        $status = trim((string) ($this->filters['status'] ?? ''));
        if ($status !== '' && $status !== 'all') {
            $parts[] = 'Status: '.$this->statusLabel($status);
        }
        $search = trim((string) ($this->filters['q'] ?? ''));
        if ($search !== '') {
            $parts[] = 'Pencarian: '.$search;
        }

        return implode(' | ', $parts);
    }

    public function statusLabel(?string $status): string
    {
        return OutboundManualQcStatus::labels()[$status ?? ''] ?? ($status ?: '-');
    }

    private function applySearch(Builder $query): void
    {
        $search = trim((string) ($this->filters['q'] ?? ''));
        if ($search === '') {
            return;
        }
        $exact = trim(strtolower((string) ($this->filters['search_mode'] ?? ''))) === 'exact';
        $query->where(function (Builder $q) use ($search, $exact) {
            foreach (['code', 'ref_no', 'surat_jalan_no', 'recipient_name', 'recipient_phone', 'recipient_address', 'note'] as $index => $column) {
                $this->applyTextSearch($q, 'outbound_transactions.'.$column, $search, $exact, $index === 0 ? 'and' : 'or');
            }
            $q->orWhereHas('items', function (Builder $itemRowQ) use ($search, $exact) {
                $this->applyTextSearch($itemRowQ, 'note', $search, $exact);
                $itemRowQ->orWhereHas('item', function (Builder $itemQ) use ($search, $exact) {
                    $this->applyTextSearch($itemQ, 'sku', $search, $exact);
                    $this->applyTextSearch($itemQ, 'name', $search, $exact, 'or');
                });
            });
            $q->orWhereHas('creator', function (Builder $creatorQ) use ($search, $exact) {
                $this->applyTextSearch($creatorQ, 'name', $search, $exact);
            });
            $q->orWhereHas('warehouse', function (Builder $warehouseQ) use ($search, $exact) {
                $this->applyTextSearch($warehouseQ, 'name', $search, $exact);
                $this->applyTextSearch($warehouseQ, 'code', $search, $exact, 'or');
            });
        });
    }

    private function applyDateFilter(Builder $query): void
    {
        try {
            if (!empty($this->filters['date_from'])) {
                $query->where('outbound_transactions.transacted_at', '>=', Carbon::parse($this->filters['date_from'])->startOfDay());
            }
            if (!empty($this->filters['date_to'])) {
                $query->where('outbound_transactions.transacted_at', '<=', Carbon::parse($this->filters['date_to'])->endOfDay());
            }
        } catch (\Throwable) {
            // Pertahankan perilaku halaman: tanggal tidak valid tidak menggagalkan request.
        }
    }

    private function applyWarehouseFilter(Builder $query): void
    {
        $warehouseId = $this->filters['warehouse_id'] ?? null;
        if ($warehouseId !== null && $warehouseId !== '' && $warehouseId !== 'all') {
            $query->where('outbound_transactions.warehouse_id', (int) $warehouseId);
        }
    }

    private function applyStatusFilter(Builder $query): void
    {
        $status = $this->filters['status'] ?? null;
        if ($status !== null && $status !== '' && $status !== 'all') {
            $query->where('outbound_transactions.status', $status);
        }
    }

    private function applyTextSearch(Builder $query, string $column, string $search, bool $exact, string $boolean = 'and'): void
    {
        if ($exact) {
            $method = $boolean === 'or' ? 'orWhereRaw' : 'whereRaw';
            $query->{$method}('LOWER('.$column.') = ?', [mb_strtolower($search)]);
            return;
        }
        $method = $boolean === 'or' ? 'orWhere' : 'where';
        $query->{$method}($column, 'like', '%'.$search.'%');
    }

    private function expectedExpression(): string
    {
        return 'COALESCE(qc_stats.expected_qty, item_stats.planned_qty, 0)';
    }

    private function scannedExpression(): string
    {
        return "CASE WHEN qc_stats.outbound_transaction_id IS NOT NULL THEN COALESCE(qc_stats.scanned_qty, 0) WHEN outbound_transactions.status = 'approved' THEN COALESCE(item_stats.planned_qty, 0) ELSE 0 END";
    }
}
