<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DamagedAllocation;
use App\Models\DamagedGood;
use App\Models\InboundTransaction;
use App\Models\OutboundTransaction;
use App\Models\StockAdjustment;
use App\Models\StockOpname;
use App\Models\StockTransfer;
use App\Models\Kurir;
use App\Models\QcResiScan;
use App\Models\Resi;
use App\Models\ShipmentScanOut;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $currentDate = now()->toDateString();
        [$dateFrom, $dateTo] = $this->dashboardDateRange($request);

        $activeResiQuery = $this->applyDateRange(Resi::query(), 'tanggal_upload', $dateFrom, $dateTo)
            ->where(function ($q) {
                $q->whereNull('status')
                    ->orWhere('status', '!=', 'canceled');
            });
        $totalResi = (clone $activeResiQuery)->count();
        $totalCanceled = $this->applyDateRange(Resi::query(), 'tanggal_upload', $dateFrom, $dateTo)
            ->where('status', 'canceled')
            ->count();
        $totalQcScan = QcResiScan::where('status', 'passed')
            ->whereHas('resi', function ($q) use ($dateFrom, $dateTo) {
                $this->applyDateRange($q, 'tanggal_upload', $dateFrom, $dateTo)
                    ->where(function ($resiQuery) {
                        $resiQuery->whereNull('status')
                            ->orWhere('status', '!=', 'canceled');
                    });
            })
            ->count();
        $totalScanOut = $this->applyDateRange(ShipmentScanOut::query(), 'scan_date', $dateFrom, $dateTo)->count();
        $totalResiUpdatedAt = (clone $activeResiQuery)->max('updated_at');
        $totalCanceledUpdatedAt = $this->applyDateRange(Resi::query(), 'tanggal_upload', $dateFrom, $dateTo)
            ->where('status', 'canceled')
            ->max('canceled_at');
        $totalQcUpdatedAt = QcResiScan::where('status', 'passed')
            ->whereHas('resi', function ($q) use ($dateFrom, $dateTo) {
                $this->applyDateRange($q, 'tanggal_upload', $dateFrom, $dateTo)
                    ->where(function ($resiQuery) {
                        $resiQuery->whereNull('status')
                            ->orWhere('status', '!=', 'canceled');
                    });
            })
            ->max('completed_at');
        $totalScanUpdatedAt = $this->applyDateRange(ShipmentScanOut::query(), 'scan_date', $dateFrom, $dateTo)->max('scanned_at');
        $totalResiUpdated = $this->formatOperationalUpdate($totalResiUpdatedAt, $dateFrom, $dateTo);
        $totalCanceledUpdated = $this->formatOperationalUpdate($totalCanceledUpdatedAt, $dateFrom, $dateTo);
        $totalQcUpdated = $this->formatOperationalUpdate($totalQcUpdatedAt, $dateFrom, $dateTo);
        $totalScanUpdated = $this->formatOperationalUpdate($totalScanUpdatedAt, $dateFrom, $dateTo);
        $scanOutOverCount = $this->scanOutOverQuery($dateFrom, $dateTo)->count();
        $scanOutUnderCount = $this->scanOutUnderQuery($dateFrom, $dateTo)->count();
        $scanOutDifference = $totalScanOut - $totalResi;
        $duplicateResiRows = $this->duplicateResiRows($dateFrom, $dateTo);
        $duplicateResiGroupCount = $duplicateResiRows->count();
        $duplicateResiTotal = (int) $duplicateResiRows->sum('total');

        $manualOutboundQuery = OutboundTransaction::query()
            ->where('type', 'manual');
        $this->applyDateRange($manualOutboundQuery, 'transacted_at', $dateFrom, $dateTo);
        $manualOutboundStatusCounts = (clone $manualOutboundQuery)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');
        $manualOutboundTotal = (clone $manualOutboundQuery)->count();
        $manualOutboundQty = DB::table('outbound_items as oi')
            ->join('outbound_transactions as ot', 'ot.id', '=', 'oi.outbound_transaction_id')
            ->where('ot.type', 'manual');
        $this->applyDateRange($manualOutboundQty, 'ot.transacted_at', $dateFrom, $dateTo);
        $manualOutboundQty = (int) $manualOutboundQty->sum('oi.qty');
        $manualOutboundCompleted = (int) ($manualOutboundStatusCounts['approved'] ?? 0);
        $manualOutboundSummary = [
            'total' => $manualOutboundTotal,
            'qty' => $manualOutboundQty,
            'pending' => (int) ($manualOutboundStatusCounts['pending'] ?? 0),
            'pending_qc' => (int) ($manualOutboundStatusCounts['pending_qc'] ?? 0),
            'qc_scanning' => (int) ($manualOutboundStatusCounts['qc_scanning'] ?? 0),
            'completed' => $manualOutboundCompleted,
            'outstanding' => max(0, $manualOutboundTotal - $manualOutboundCompleted),
            'url' => route('admin.outbound.manuals.index', [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ]),
        ];

        $resiCounts = Resi::select('kurir_id', DB::raw('count(*) as total'))
            ->groupBy('kurir_id')
            ->orderBy('kurir_id');
        $this->applyDateRange($resiCounts, 'tanggal_upload', $dateFrom, $dateTo);
        $resiCounts = $resiCounts->pluck('total', 'kurir_id')->toArray();

        $scanCounts = ShipmentScanOut::select('kurir_id', DB::raw('count(*) as total'))
            ->groupBy('kurir_id')
            ->orderBy('kurir_id');
        $this->applyDateRange($scanCounts, 'scan_date', $dateFrom, $dateTo);
        $scanCounts = $scanCounts->pluck('total', 'kurir_id')->toArray();

        $resiLatest = Resi::select('kurir_id', DB::raw('max(updated_at) as latest'))
            ->groupBy('kurir_id')
            ->orderBy('kurir_id');
        $this->applyDateRange($resiLatest, 'tanggal_upload', $dateFrom, $dateTo);
        $resiLatest = $resiLatest->pluck('latest', 'kurir_id')->toArray();

        $resiCanceledCounts = Resi::select('kurir_id', DB::raw('count(*) as total'))
            ->where('status', 'canceled')
            ->groupBy('kurir_id')
            ->orderBy('kurir_id');
        $this->applyDateRange($resiCanceledCounts, 'tanggal_upload', $dateFrom, $dateTo);
        $resiCanceledCounts = $resiCanceledCounts->pluck('total', 'kurir_id')->toArray();

        $scanLatest = ShipmentScanOut::select('kurir_id', DB::raw('max(scanned_at) as latest'))
            ->groupBy('kurir_id')
            ->orderBy('kurir_id');
        $this->applyDateRange($scanLatest, 'scan_date', $dateFrom, $dateTo);
        $scanLatest = $scanLatest->pluck('latest', 'kurir_id')->toArray();

        $kurirs = Kurir::orderBy('name')
            ->get(['id', 'name'])
            ->map(function ($kurir) use ($resiCounts, $scanCounts, $resiLatest, $scanLatest, $resiCanceledCounts, $dateFrom, $dateTo) {
                $resiTotal = (int) ($resiCounts[$kurir->id] ?? 0);
                $scanTotal = (int) ($scanCounts[$kurir->id] ?? 0);
                $canceledTotal = (int) ($resiCanceledCounts[$kurir->id] ?? 0);
                $activeTotal = max(0, $resiTotal - $canceledTotal);
                $latestResi = $resiLatest[$kurir->id] ?? null;
                $latestScan = $scanLatest[$kurir->id] ?? null;
                $latestRaw = $latestResi && $latestScan
                    ? (Carbon::parse($latestResi)->greaterThan(Carbon::parse($latestScan)) ? $latestResi : $latestScan)
                    : ($latestResi ?: $latestScan);
                $latestTime = $this->formatOperationalUpdate($latestRaw, $dateFrom, $dateTo);
                return [
                    'id' => $kurir->id,
                    'name' => $kurir->name,
                    'resi_total' => $activeTotal,
                    'scan_total' => $scanTotal,
                    'canceled_total' => $canceledTotal,
                    'remaining' => max(0, $activeTotal - $scanTotal),
                    'last_update' => $latestTime,
                ];
            })
            ->sort(function (array $a, array $b) {
                $byResiTotal = $b['resi_total'] <=> $a['resi_total'];

                return $byResiTotal !== 0
                    ? $byResiTotal
                    : strcasecmp($a['name'], $b['name']);
            })
            ->filter(fn (array $kurir) => $kurir['resi_total'] > 0)
            ->values();

        return view('admin.dashboard', [
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'currentDate' => $currentDate,
            'totalResi' => $totalResi,
            'totalCanceled' => $totalCanceled,
            'totalQcScan' => $totalQcScan,
            'totalScanOut' => $totalScanOut,
            'totalResiUpdated' => $totalResiUpdated,
            'totalCanceledUpdated' => $totalCanceledUpdated,
            'totalQcUpdated' => $totalQcUpdated,
            'totalScanUpdated' => $totalScanUpdated,
            'scanOutOverCount' => $scanOutOverCount,
            'scanOutUnderCount' => $scanOutUnderCount,
            'scanOutDifference' => $scanOutDifference,
            'duplicateResiRows' => $duplicateResiRows,
            'duplicateResiGroupCount' => $duplicateResiGroupCount,
            'duplicateResiTotal' => $duplicateResiTotal,
            'kurirs' => $kurirs,
            'manualOutboundSummary' => $manualOutboundSummary,
            'emptyStockSummary' => $this->emptyStockSummary(),
            'emptyStockRows' => $this->emptyStockRows(),
            'pendingApprovalSummary' => $this->pendingApprovalSummary(),
            'resiReportDefaultFrom' => now()->subDays(6)->toDateString(),
            'resiReportDefaultTo' => $currentDate,
        ]);
    }

    public function resiReport(Request $request)
    {
        $dateTo = $this->parseDate($request->input('date_to')) ?: now()->toDateString();
        $dateFrom = $this->parseDate($request->input('date_from')) ?: Carbon::parse($dateTo)->subDays(6)->toDateString();

        if (Carbon::parse($dateFrom)->greaterThan(Carbon::parse($dateTo))) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $countsByDate = Resi::query()
            ->selectRaw('DATE(tanggal_upload) as date')
            ->selectRaw("SUM(CASE WHEN status IS NULL OR status <> 'canceled' THEN 1 ELSE 0 END) as active_total")
            ->selectRaw("SUM(CASE WHEN status = 'canceled' THEN 1 ELSE 0 END) as canceled_total")
            ->whereDate('tanggal_upload', '>=', $dateFrom)
            ->whereDate('tanggal_upload', '<=', $dateTo)
            ->groupByRaw('DATE(tanggal_upload)')
            ->get()
            ->keyBy('date');

        $start = Carbon::parse($dateFrom);
        $end = Carbon::parse($dateTo);
        $rows = collect();
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $day = $date->toDateString();
            $rows->push([
                'date' => $day,
                'active_total' => (int) ($countsByDate->get($day)?->active_total ?? 0),
                'canceled_total' => (int) ($countsByDate->get($day)?->canceled_total ?? 0),
            ]);
        }

        $days = $rows->count();
        $totalActive = (int) $rows->sum('active_total');
        $peak = $rows->sortByDesc('active_total')->first();

        return response()->json([
            'meta' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'days' => $days,
                'total_resi' => $totalActive,
                'total_canceled' => (int) $rows->sum('canceled_total'),
                'daily_average' => $days > 0 ? round($totalActive / $days, 2) : 0,
                'peak_date' => $peak['date'] ?? null,
                'peak_total' => (int) ($peak['active_total'] ?? 0),
            ],
            'data' => $rows->values(),
        ]);
    }

    private function duplicateResiRows(string $dateFrom, string $dateTo)
    {
        $query = Resi::query()
            ->select([
                'no_resi',
                DB::raw('COUNT(*) as total'),
                DB::raw('MAX(updated_at) as latest_update'),
            ])
            ->whereNotNull('no_resi')
            ->where('no_resi', '<>', '')
            ->groupBy('no_resi')
            ->havingRaw('COUNT(*) > 1')
            ->orderByDesc('total')
            ->orderByDesc('latest_update')
            ->limit(10);
        $this->applyDateRange($query, 'tanggal_upload', $dateFrom, $dateTo);

        return $query->get()
            ->map(function ($row) use ($dateFrom, $dateTo) {
                $resiQuery = Resi::query()
                    ->where('no_resi', $row->no_resi)
                    ->orderBy('id');
                $this->applyDateRange($resiQuery, 'tanggal_upload', $dateFrom, $dateTo);
                $idPesananList = $resiQuery
                    ->pluck('id_pesanan')
                    ->implode(', ');

                return [
                    'no_resi' => (string) $row->no_resi,
                    'total' => (int) $row->total,
                    'id_pesanan_list' => $idPesananList !== '' ? $idPesananList : '-',
                    'url' => route('admin.inventory.resi-import.index', [
                        'date_from' => $dateFrom,
                        'date_to' => $dateTo,
                        'q' => $row->no_resi,
                        'search_mode' => 'exact',
                    ]),
                ];
            });
    }

    private function emptyStockSummary()
    {
        $stockExpr = 'COALESCE(s.stock, 0)';

        $rows = DB::table('warehouses as w')
            ->crossJoin('items as i')
            ->leftJoin('item_stocks as s', function ($join) {
                $join->on('s.item_id', '=', 'i.id')
                    ->on('s.warehouse_id', '=', 'w.id');
            })
            ->where(function ($query) {
                $query->whereNull('i.item_type')
                    ->orWhere('i.item_type', '!=', 'bundle');
            })
            ->where('i.status', 'active')
            ->whereRaw('COALESCE(s.is_stock_monitored, 1) = 1')
            ->where(function ($query) {
                $query->whereNull('w.type')
                    ->orWhere('w.type', '!=', 'damaged');
            })
            ->whereRaw("{$stockExpr} <= 0")
            ->select([
                'w.id',
                'w.name',
                'w.code',
                DB::raw('COUNT(*) as total_empty'),
                DB::raw('MAX(s.updated_at) as latest_update'),
            ])
            ->groupBy('w.id', 'w.name', 'w.code')
            ->orderByDesc('total_empty')
            ->orderBy('w.name')
            ->get();

        $totalEmpty = (int) $rows->sum('total_empty');

        return [
            'total_empty' => $totalEmpty,
            'warehouse_total' => $rows->where('total_empty', '>', 0)->count(),
            'warehouses' => $rows->map(function ($row) use ($totalEmpty) {
                $total = (int) ($row->total_empty ?? 0);

                return [
                    'id' => (int) $row->id,
                    'name' => $row->name ?? '-',
                    'code' => $row->code ?? '-',
                    'total_empty' => $total,
                    'percent' => $totalEmpty > 0 ? round($total / $totalEmpty * 100, 1) : 0,
                    'latest_update' => $row->latest_update
                        ? Carbon::parse($row->latest_update)->format('Y-m-d H:i')
                        : '-',
                ];
            })->values(),
        ];
    }

    private function emptyStockRows()
    {
        $stockExpr = 'COALESCE(s.stock, 0)';
        $safetyExpr = 'COALESCE(s.safety_stock, i.safety_stock, 0)';

        return DB::table('warehouses as w')
            ->crossJoin('items as i')
            ->leftJoin('item_stocks as s', function ($join) {
                $join->on('s.item_id', '=', 'i.id')
                    ->on('s.warehouse_id', '=', 'w.id');
            })
            ->leftJoin('categories as c', 'c.id', '=', 'i.category_id')
            ->where(function ($query) {
                $query->whereNull('i.item_type')
                    ->orWhere('i.item_type', '!=', 'bundle');
            })
            ->where('i.status', 'active')
            ->whereRaw('COALESCE(s.is_stock_monitored, 1) = 1')
            ->where(function ($query) {
                $query->whereNull('w.type')
                    ->orWhere('w.type', '!=', 'damaged');
            })
            ->whereRaw("{$stockExpr} <= 0")
            ->select([
                'i.sku',
                'i.name',
                'i.address',
                'w.name as warehouse',
                'w.code as warehouse_code',
                DB::raw("{$stockExpr} as stock"),
                DB::raw("{$safetyExpr} as safety_stock"),
                DB::raw("CASE WHEN i.category_id = 0 THEN 'Tanpa Kategori' ELSE COALESCE(c.name, '-') END as category"),
                's.updated_at',
            ])
            ->orderBy('w.name')
            ->orderBy('i.sku')
            ->limit(50)
            ->get()
            ->map(function ($row) {
                return [
                    'sku' => $row->sku ?? '-',
                    'name' => $row->name ?? '-',
                    'warehouse' => $row->warehouse ?? '-',
                    'warehouse_code' => $row->warehouse_code ?? '-',
                    'category' => $row->category ?? '-',
                    'address' => $row->address ?? '-',
                    'stock' => (int) ($row->stock ?? 0),
                    'safety_stock' => (int) ($row->safety_stock ?? 0),
                    'updated_at' => $row->updated_at ? Carbon::parse($row->updated_at)->format('Y-m-d H:i') : '-',
                ];
            });
    }

    private function pendingApprovalSummary(): array
    {
        $items = [
            [
                'label' => 'Inbound Penerimaan',
                'group' => 'Inbound',
                'count' => InboundTransaction::where('type', 'receipt')->where('status', 'pending')->count(),
                'url' => route('admin.inbound.receipts.index', ['status' => 'pending']),
            ],
            [
                'label' => 'Inbound Retur',
                'group' => 'Inbound',
                'count' => InboundTransaction::where('type', 'return')->where('status', 'pending')->count(),
                'url' => route('admin.inbound.returns.index', ['status' => 'pending']),
            ],
            [
                'label' => 'Inbound Manual',
                'group' => 'Inbound',
                'count' => InboundTransaction::where('type', 'manual')->where('status', 'pending')->count(),
                'url' => route('admin.inbound.manuals.index', ['status' => 'pending']),
            ],
            [
                'label' => 'Outbound Picker',
                'group' => 'Outbound',
                'count' => OutboundTransaction::where('type', 'picker')->where('status', 'pending')->count(),
                'url' => route('admin.outbound.pickers.index', ['status' => 'pending']),
            ],
            [
                'label' => 'Outbound Manual',
                'group' => 'Outbound',
                'count' => OutboundTransaction::where('type', 'manual')->where('status', 'pending')->count(),
                'url' => route('admin.outbound.manuals.index', ['status' => 'pending']),
            ],
            [
                'label' => 'Outbound Retur',
                'group' => 'Outbound',
                'count' => OutboundTransaction::where('type', 'return')->where('status', 'pending')->count(),
                'url' => route('admin.outbound.returns.index', ['status' => 'pending']),
            ],
            [
                'label' => 'Barang Rusak',
                'group' => 'Inventory',
                'count' => DamagedGood::where('status', 'pending')->count(),
                'url' => route('admin.inventory.damaged-goods.index', ['status' => 'pending']),
            ],
            [
                'label' => 'Alokasi Rusak',
                'group' => 'Inventory',
                'count' => DamagedAllocation::where('status', 'pending')->count(),
                'url' => route('admin.inventory.damaged-allocations.index', ['status' => 'pending']),
            ],
            [
                'label' => 'Stock Adjustment',
                'group' => 'Inventory',
                'count' => StockAdjustment::where('status', 'pending')->count(),
                'url' => route('admin.inventory.stock-adjustments.index', ['status' => 'pending']),
            ],
            [
                'label' => 'Stock Opname Berjalan',
                'group' => 'Inventory',
                'count' => StockOpname::where('status', 'open')->count(),
                'url' => route('admin.inventory.stock-opname.index', ['status' => 'open']),
            ],
            [
                'label' => 'Transfer Gudang QC',
                'group' => 'Inventory',
                'count' => StockTransfer::where('status', 'qc_pending')->count(),
                'url' => route('admin.inventory.stock-transfers.index', ['status' => 'qc_pending']),
            ],
        ];

        return [
            'total' => array_sum(array_column($items, 'count')),
            'items' => collect($items)
                ->sortByDesc('count')
                ->values(),
        ];
    }

    public function scanOutDiscrepancy(Request $request)
    {
        [$dateFrom, $dateTo] = $this->dashboardDateRange($request);
        $period = $this->periodLabel($dateFrom, $dateTo);

        $overRows = $this->scanOutOverQuery($dateFrom, $dateTo)
            ->with(['resi.kurir:id,name', 'resi.details:id,resi_id,sku,qty'])
            ->orderByDesc('scanned_at')
            ->orderByDesc('id')
            ->get()
            ->map(function (ShipmentScanOut $scanOut) use ($dateFrom, $dateTo, $period) {
                $resi = $scanOut->resi;
                $tanggalUpload = $resi?->tanggal_upload
                    ? Carbon::parse($resi->tanggal_upload)->format('Y-m-d')
                    : '-';
                $isCanceled = ($resi?->status ?? 'active') === 'canceled';
                $reason = $isCanceled
                    ? 'Resi canceled tetapi ada scan out pada periode ini'
                    : 'Tanggal upload di luar '.$period;

                return $this->formatDiscrepancyRow($resi, [
                    'type' => 'over',
                    'type_label' => 'Lebih Scan Out',
                    'reason' => $reason,
                    'scanned_at' => $scanOut->scanned_at
                        ? Carbon::parse($scanOut->scanned_at)->format('Y-m-d H:i')
                        : '-',
                    'tanggal_upload' => $tanggalUpload,
                ]);
            })
            ->values();

        $underRows = $this->scanOutUnderQuery($dateFrom, $dateTo)
            ->with(['kurir:id,name', 'details:id,resi_id,sku,qty', 'scanOut'])
            ->orderBy('tanggal_upload')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get(['id', 'id_pesanan', 'no_resi', 'kurir_id', 'tanggal_upload', 'status', 'updated_at'])
            ->map(function (Resi $resi) use ($period) {
                $otherScanOut = $resi->scanOut;
                $reason = $otherScanOut
                    ? 'Scan out tercatat di luar periode'
                    : 'Belum ada scan out pada '.$period;

                return $this->formatDiscrepancyRow($resi, [
                    'type' => 'under',
                    'type_label' => 'Kurang Scan Out',
                    'reason' => $reason,
                    'scanned_at' => $otherScanOut?->scanned_at
                        ? Carbon::parse($otherScanOut->scanned_at)->format('Y-m-d H:i')
                        : '-',
                ]);
            })
            ->values();

        return response()->json([
            'meta' => [
                'date' => $dateFrom === $dateTo ? $dateFrom : null,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'period' => $period,
                'over_total' => $overRows->count(),
                'under_total' => $underRows->count(),
                'difference' => $overRows->count() - $underRows->count(),
            ],
            'data' => [
                'over' => $overRows,
                'under' => $underRows,
            ],
        ]);
    }

    public function kurirDetail(Request $request)
    {
        $validated = $request->validate([
            'kurir_id' => ['required', 'integer', 'exists:kurirs,id'],
            'date' => ['nullable', 'date'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'type' => ['nullable', 'in:total,scanned,remaining,canceled'],
            'search' => ['nullable', 'string', 'max:10000'],
        ]);

        [$dateFrom, $dateTo] = $this->dashboardDateRange($request);
        $type = $validated['type'] ?? 'remaining';
        $kurir = Kurir::query()->findOrFail((int) $validated['kurir_id'], ['id', 'name']);

        $resis = Resi::query()
            ->with(['details:id,resi_id,sku,qty'])
            ->where('kurir_id', $kurir->id)
            ->orderByDesc('updated_at')
            ->orderByDesc('id');
        $this->applyDateRange($resis, 'tanggal_upload', $dateFrom, $dateTo);
        $resis = $resis->get(['id', 'id_pesanan', 'no_resi', 'tanggal_upload', 'status']);

        $scanOuts = ShipmentScanOut::query()
            ->with(['scanner:id,name', 'resi.details:id,resi_id,sku,qty'])
            ->where('kurir_id', $kurir->id)
            ->orderByDesc('scanned_at');
        $this->applyDateRange($scanOuts, 'scan_date', $dateFrom, $dateTo);
        $scanOuts = $scanOuts
            ->get(['id', 'resi_id', 'scan_type', 'scan_code', 'scanned_at', 'scanned_by'])
            ->unique('resi_id')
            ->values();

        $scanOutsByResi = $scanOuts->keyBy('resi_id');

        $activeResis = $resis->filter(function ($resi) {
            return ($resi->status ?? 'active') !== 'canceled';
        })->values();

        $pendingResis = $activeResis->filter(function ($resi) use ($scanOutsByResi) {
            return !$scanOutsByResi->has($resi->id);
        })->values();

        $scannedResis = $scanOuts
            ->map(fn ($scanOut) => $scanOut->resi)
            ->filter()
            ->values();

        $canceledResis = $resis->filter(function ($resi) {
            return ($resi->status ?? 'active') === 'canceled';
        })->values();

        $selectedResis = match ($type) {
            'total' => $activeResis,
            'scanned' => $scannedResis,
            'canceled' => $canceledResis,
            default => $pendingResis,
        };

        $searchTerms = $this->parseResiSearchTerms($validated['search'] ?? '');
        $matchedTerms = collect();
        if ($searchTerms !== []) {
            $normalizedTerms = collect($searchTerms)
                ->map(fn (string $term) => mb_strtolower($term))
                ->flip();

            $selectedResis = $selectedResis->filter(function (Resi $resi) use ($normalizedTerms, &$matchedTerms) {
                $values = [
                    trim((string) $resi->no_resi),
                    trim((string) $resi->id_pesanan),
                ];

                $matches = collect($values)
                    ->filter()
                    ->map(fn (string $value) => mb_strtolower($value))
                    ->filter(fn (string $value) => $normalizedTerms->has($value));

                $matchedTerms = $matchedTerms->merge($matches);

                return $matches->isNotEmpty();
            })->values();
        }

        $data = $selectedResis->map(function ($resi) use ($scanOutsByResi) {
            $scanOut = $scanOutsByResi->get($resi->id);
            $isCanceled = ($resi->status ?? 'active') === 'canceled';
            return [
                'id_pesanan' => $resi->id_pesanan ?? '-',
                'no_resi' => $resi->no_resi ?? '-',
                'sku' => $this->formatSkuSummary($resi),
                'status' => $isCanceled
                    ? 'Canceled'
                    : ($scanOut ? 'Scan Out' : 'Siap Scan Out'),
                'tanggal_upload' => $resi->tanggal_upload
                    ? Carbon::parse($resi->tanggal_upload)->format('Y-m-d')
                    : '-',
                'scanned_at' => $scanOut?->scanned_at
                    ? Carbon::parse($scanOut->scanned_at)->format('Y-m-d H:i')
                    : null,
            ];
        })->values();

        return response()->json([
            'meta' => [
                'kurir_name' => $kurir->name,
                'date' => $dateFrom === $dateTo ? $dateFrom : null,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'period' => $this->periodLabel($dateFrom, $dateTo),
                'type' => $type,
                'total_resi' => $activeResis->count(),
                'scanned_total' => $scannedResis->count(),
                'remaining_total' => $pendingResis->count(),
                'canceled_total' => $canceledResis->count(),
                'search_terms' => $searchTerms,
                'unmatched_search_terms' => collect($searchTerms)
                    ->reject(fn (string $term) => $matchedTerms->contains(mb_strtolower($term)))
                    ->values(),
            ],
            'data' => $data,
        ]);
    }

    /** @return array<int, string> */
    private function parseResiSearchTerms(string $search): array
    {
        return collect(preg_split('/[\s,;]+/u', trim($search), -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn (string $term) => trim($term))
            ->filter()
            ->unique(fn (string $term) => mb_strtolower($term))
            ->take(200)
            ->values()
            ->all();
    }

    private function scanOutOverQuery(string $dateFrom, string $dateTo)
    {
        $query = ShipmentScanOut::query()
            ->whereHas('resi')
            ->where(function ($q) use ($dateFrom, $dateTo) {
                $q->whereHas('resi', function ($resiQuery) {
                    $resiQuery->where('status', 'canceled');
                })->orWhereHas('resi', function ($resiQuery) use ($dateFrom, $dateTo) {
                    $resiQuery->where(function ($outsideRange) use ($dateFrom, $dateTo) {
                        $outsideRange->whereDate('tanggal_upload', '<', $dateFrom)
                            ->orWhereDate('tanggal_upload', '>', $dateTo);
                    });
                });
            });

        return $this->applyDateRange($query, 'scan_date', $dateFrom, $dateTo);
    }

    private function scanOutUnderQuery(string $dateFrom, string $dateTo)
    {
        $query = Resi::query()
            ->where(function ($q) {
                $q->whereNull('status')
                    ->orWhere('status', '!=', 'canceled');
            })
            ->whereDoesntHave('scanOut', function ($q) use ($dateFrom, $dateTo) {
                $this->applyDateRange($q, 'scan_date', $dateFrom, $dateTo);
            });

        return $this->applyDateRange($query, 'tanggal_upload', $dateFrom, $dateTo);
    }

    private function formatDiscrepancyRow(?Resi $resi, array $extra): array
    {
        return [
            'type' => $extra['type'],
            'type_label' => $extra['type_label'],
            'reason' => $extra['reason'],
            'id_pesanan' => $resi?->id_pesanan ?? '-',
            'no_resi' => $resi?->no_resi ?? '-',
            'kurir' => $resi?->kurir?->name ?? '-',
            'sku' => $resi ? $this->formatSkuSummary($resi) : '-',
            'tanggal_upload' => $extra['tanggal_upload']
                ?? ($resi?->tanggal_upload ? Carbon::parse($resi->tanggal_upload)->format('Y-m-d') : '-'),
            'scanned_at' => $extra['scanned_at'],
        ];
    }

    private function formatSkuSummary(Resi $resi): string
    {
        $skuSummary = $resi->details
            ->map(function ($detail) {
                $sku = trim((string) ($detail->sku ?? ''));
                if ($sku === '') {
                    return null;
                }

                return $sku.' ('.(int) $detail->qty.')';
            })
            ->filter()
            ->implode(', ');

        return $skuSummary ?: '-';
    }

    private function dashboardDateRange(Request $request): array
    {
        $currentDate = now()->toDateString();
        $legacyDate = $this->parseDate($request->input('date'));
        $dateFrom = $this->parseDate($request->input('date_from')) ?: $legacyDate ?: $currentDate;
        $dateTo = $this->parseDate($request->input('date_to')) ?: $legacyDate ?: $currentDate;

        if ($dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        return [$dateFrom, $dateTo];
    }

    private function applyDateRange($query, string $column, string $dateFrom, string $dateTo)
    {
        return $query
            ->whereDate($column, '>=', $dateFrom)
            ->whereDate($column, '<=', $dateTo);
    }

    private function periodLabel(string $dateFrom, string $dateTo): string
    {
        return $dateFrom === $dateTo ? $dateFrom : $dateFrom.' s.d. '.$dateTo;
    }

    private function formatOperationalUpdate(mixed $value, string $dateFrom, string $dateTo): string
    {
        if (!$value) {
            return '-';
        }

        return Carbon::parse($value)->format($dateFrom === $dateTo ? 'H:i' : 'Y-m-d H:i');
    }

    private function parseDate(?string $value): ?string
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
