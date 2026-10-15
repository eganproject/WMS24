<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ScanOutReportExport;
use App\Http\Controllers\Controller;
use App\Models\Resi;
use App\Models\ShipmentScanOut;
use App\Models\User;
use App\Support\ResiStoreFilter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class ScanOutReportController extends Controller
{
    public function index()
    {
        $operatorIds = ShipmentScanOut::query()
            ->whereNotNull('scanned_by')
            ->distinct()
            ->pluck('scanned_by');

        $operators = User::query()
            ->whereIn('id', $operatorIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.reports.scan-out-reports.index', [
            'dataUrl' => route('admin.reports.scan-out-reports.data'),
            'exportUrl' => route('admin.reports.scan-out-reports.export'),
            'operators' => $operators,
            'stores' => ResiStoreFilter::options(),
            'today' => now()->toDateString(),
        ]);
    }

    public function export(Request $request)
    {
        $request->validate(['store_id' => ResiStoreFilter::rule()]);
        $filters = $request->query();
        if ($request->filled('operator_id')) {
            $filters['operator_name'] = User::query()->whereKey($request->input('operator_id'))->value('name');
        }

        return Excel::download(
            new ScanOutReportExport($filters),
            'laporan-scan-out-'.now()->format('Ymd_His').'.xlsx'
        );
    }

    public function data(Request $request)
    {
        $request->validate(['store_id' => ResiStoreFilter::rule()]);
        $storeFilter = ResiStoreFilter::normalize($request->input('store_id'));

        $query = DB::table('shipment_scan_outs as so')
            ->join('users as u', 'u.id', '=', 'so.scanned_by')
            ->selectRaw('so.scan_date, so.scanned_by, u.name as operator_name, COUNT(*) as total_scan, COUNT(DISTINCT so.scan_code) as unique_scan, MIN(so.scanned_at) as first_scan, MAX(so.scanned_at) as last_scan')
            ->groupBy('so.scan_date', 'so.scanned_by', 'u.name');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $exact = $this->isExactSearch($request);
            $query->where(function ($q) use ($search, $exact) {
                $this->applyTextSearch($q, 'u.name', $search, $exact);
                $this->applyTextSearch($q, 'so.scan_date', $search, $exact, 'or');
            });
        }

        if ($storeFilter !== null) {
            $query->join('resis as r', 'r.id', '=', 'so.resi_id');
            ResiStoreFilter::apply($query, 'r.store_id', $storeFilter);
        }

        $operatorId = $request->input('operator_id');
        if ($operatorId) {
            $query->where('so.scanned_by', $operatorId);
        }

        $dateFrom = $this->parseDate($request->input('date_from'));
        $dateTo = $this->parseDate($request->input('date_to'));
        if ($dateFrom) {
            $query->where('so.scan_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->where('so.scan_date', '<=', $dateTo);
        }

        $rows = $query
            ->orderByDesc('so.scan_date')
            ->orderBy('operator_name')
            ->limit(500)
            ->get();

        $data = $rows->map(function ($row) {
            $first = $row->first_scan ? Carbon::parse($row->first_scan) : null;
            $last = $row->last_scan ? Carbon::parse($row->last_scan) : null;
            $durationHours = null;
            if ($first && $last) {
                $minutes = max(0, $first->diffInMinutes($last));
                $durationHours = $minutes > 0 ? $minutes / 60 : null;
            }
            $avgPerHour = $durationHours && $durationHours > 0
                ? round($row->total_scan / $durationHours, 2)
                : (int) $row->total_scan;

            return [
                'date' => Carbon::parse($row->scan_date)->format('Y-m-d'),
                'operator' => $row->operator_name ?? '-',
                'total_scan' => (int) $row->total_scan,
                'unique_scan' => (int) $row->unique_scan,
                'avg_per_hour' => $avgPerHour,
                'first_scan' => $first?->format('H:i') ?? '-',
                'last_scan' => $last?->format('H:i') ?? '-',
            ];
        });

        $comparison = $this->buildComparisonData($dateFrom, $dateTo, $storeFilter);

        return response()->json([
            'data' => $data,
            'comparison' => $comparison,
        ]);
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

    private function buildComparisonData(?string $dateFrom, ?string $dateTo, ?string $storeFilter): array
    {
        $resiQuery = Resi::query();
        if ($dateFrom) {
            $resiQuery->whereDate('tanggal_upload', '>=', $dateFrom);
        }
        if ($dateTo) {
            $resiQuery->whereDate('tanggal_upload', '<=', $dateTo);
        }
        ResiStoreFilter::apply($resiQuery, 'resis.store_id', $storeFilter);

        $canceledTotal = (clone $resiQuery)->where('status', 'canceled')->count();
        $activeResiQuery = (clone $resiQuery)->where(function ($q) {
            $q->whereNull('status')
                ->orWhere('status', '!=', 'canceled');
        });

        $importTotal = (clone $activeResiQuery)->count();
        $scannedTotal = $importTotal > 0
            ? ShipmentScanOut::query()
                ->whereIn('resi_id', (clone $activeResiQuery)->select('id'))
                ->distinct('resi_id')
                ->count('resi_id')
            : 0;

        $missingBase = (clone $activeResiQuery)
            ->leftJoin('shipment_scan_outs as so', 'so.resi_id', '=', 'resis.id')
            ->leftJoin('stores as st', 'st.id', '=', 'resis.store_id')
            ->whereNull('so.id')
            ->select('resis.id_pesanan', 'resis.no_resi', 'resis.tanggal_upload', 'st.name as store_name');

        $missingTotal = (clone $missingBase)->count();
        $missingSamples = (clone $missingBase)
            ->orderByDesc('resis.tanggal_upload')
            ->limit(50)
            ->get()
            ->map(function ($row) {
                return [
                    'id_pesanan' => (string) ($row->id_pesanan ?? '-'),
                    'no_resi' => (string) ($row->no_resi ?? '-'),
                    'store' => (string) ($row->store_name ?? ResiStoreFilter::NONE_LABEL),
                    'tanggal_upload' => $row->tanggal_upload
                        ? Carbon::parse($row->tanggal_upload)->format('Y-m-d')
                        : '-',
                ];
            });

        return [
            'import_total' => $importTotal,
            'scanned_total' => $scannedTotal,
            'missing_total' => $missingTotal,
            'missing_samples' => $missingSamples,
            'canceled_total' => $canceledTotal,
            'stores' => $this->storeBreakdown($dateFrom, $dateTo, $storeFilter),
        ];
    }

    private function storeBreakdown(?string $dateFrom, ?string $dateTo, ?string $storeFilter): array
    {
        $active = "(resis.status IS NULL OR resis.status <> 'canceled')";
        $query = DB::table('resis')
            ->leftJoin('stores as st', 'st.id', '=', 'resis.store_id')
            ->leftJoin('shipment_scan_outs as so', 'so.resi_id', '=', 'resis.id')
            ->selectRaw('resis.store_id, st.name as store_name')
            ->selectRaw("SUM(CASE WHEN {$active} THEN 1 ELSE 0 END) as import_total")
            ->selectRaw("SUM(CASE WHEN {$active} AND so.id IS NOT NULL THEN 1 ELSE 0 END) as scanned_total")
            ->selectRaw("SUM(CASE WHEN resis.status = 'canceled' THEN 1 ELSE 0 END) as canceled_total")
            ->groupBy('resis.store_id', 'st.name');

        if ($dateFrom) {
            $query->whereDate('resis.tanggal_upload', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('resis.tanggal_upload', '<=', $dateTo);
        }
        ResiStoreFilter::apply($query, 'resis.store_id', $storeFilter);

        return $query->get()
            ->map(function ($row) {
                $import = (int) $row->import_total;
                $scanned = (int) $row->scanned_total;

                return [
                    'store' => (string) ($row->store_name ?? ResiStoreFilter::NONE_LABEL),
                    'import_total' => $import,
                    'scanned_total' => $scanned,
                    'missing_total' => max(0, $import - $scanned),
                    'canceled_total' => (int) $row->canceled_total,
                    'scanned_percentage' => $import > 0 ? round($scanned * 100 / $import, 1) : 0,
                ];
            })
            ->sortByDesc('import_total')
            ->values()
            ->all();
    }
}
