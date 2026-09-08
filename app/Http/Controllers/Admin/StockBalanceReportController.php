<?php

namespace App\Http\Controllers\Admin;

use App\Exports\StockBalanceReportExport;
use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use App\Support\Permission;
use App\Support\StockBalanceReportService;
use App\Support\StockMovementAnalysisService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class StockBalanceReportController extends Controller
{
    public function index()
    {
        return view('admin.reports.stock-balance.index', [
            'dataUrl' => route('admin.reports.stock-balance.data'),
            'exportUrl' => route('admin.reports.stock-balance.export'),
            'warehouses' => Warehouse::query()->orderBy('name')->get(['id', 'code', 'name']),
            'defaultDateFrom' => now()->startOfMonth()->toDateString(),
            'defaultDateTo' => now()->toDateString(),
        ]);
    }

    public function data(
        Request $request,
        StockBalanceReportService $reportService,
        StockMovementAnalysisService $movementAnalysis
    ) {
        $filters = $this->validatedFilters($request);

        if ($request->input('analysis') === 'movement') {
            return $this->movementData($request, $filters, $movementAnalysis);
        }

        $query = $reportService->query($filters);

        $totalFilters = $filters;
        $totalFilters['q'] = '';
        $recordsTotal = $reportService->query($totalFilters)->count();
        $recordsFiltered = (clone $query)->count();
        $summary = $reportService->summary($query);

        $sortColumns = [
            1 => 'items.sku',
            2 => 'items.name',
            3 => 'warehouses.name',
            4 => 'opening_stock',
            5 => 'stock_in',
            6 => 'stock_out',
            7 => 'ending_stock',
        ];
        $orderColumn = (int) $request->input('order.0.column', 2);
        $orderDirection = strtolower((string) $request->input('order.0.dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        if (isset($sortColumns[$orderColumn])) {
            $query->orderBy($sortColumns[$orderColumn], $orderDirection);
        } else {
            $query->orderBy('items.name')->orderBy('warehouses.name');
        }

        $start = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', 25);
        if ($length > 0) {
            $query->skip($start)->take(min($length, 500));
        }

        $canViewMutations = Permission::can($request->user(), 'admin.inventory.stock-mutations.index');
        $rows = $query->get()->map(fn ($row) => [
            'item_id' => (int) $row->item_id,
            'sku' => $row->sku,
            'item_name' => $row->item_name,
            'item_status' => $row->item_status,
            'warehouse_id' => (int) $row->warehouse_id,
            'warehouse_code' => $row->warehouse_code,
            'warehouse_name' => $row->warehouse_name,
            'opening_stock' => (int) $row->opening_stock,
            'stock_in' => (int) $row->stock_in,
            'stock_out' => (int) $row->stock_out,
            'ending_stock' => (int) $row->ending_stock,
            'mutation_url' => $canViewMutations ? route('admin.inventory.stock-mutations.index', [
                'item_id' => $row->item_id,
                'warehouse_id' => $row->warehouse_id,
                'date_from' => $filters['date_from'],
                'date_to' => $filters['date_to'],
            ]) : null,
        ]);

        return response()->json([
            'draw' => (int) $request->input('draw', 0),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'period' => [
                'date_from' => $filters['date_from'],
                'date_to' => $filters['date_to'],
            ],
            'summary' => [
                'total_rows' => (int) ($summary->total_rows ?? 0),
                'total_items' => (int) ($summary->total_items ?? 0),
                'total_warehouses' => (int) ($summary->total_warehouses ?? 0),
                'opening_stock' => (int) ($summary->opening_stock ?? 0),
                'stock_in' => (int) ($summary->stock_in ?? 0),
                'stock_out' => (int) ($summary->stock_out ?? 0),
                'ending_stock' => (int) ($summary->ending_stock ?? 0),
            ],
            'data' => $rows,
        ]);
    }

    private function movementData(
        Request $request,
        array $filters,
        StockMovementAnalysisService $movementAnalysis
    ) {
        $summaryFilters = $filters;
        $summaryFilters['movement_category'] = '';
        $summaryQuery = $movementAnalysis->query($summaryFilters);
        $summary = $movementAnalysis->summary($summaryQuery);

        $query = $movementAnalysis->query($filters);
        $recordsFiltered = (clone $query)->count();

        $totalFilters = $summaryFilters;
        $totalFilters['q'] = '';
        $recordsTotal = $movementAnalysis->query($totalFilters)->count();

        $sortColumns = [
            1 => 'movement_analysis.sku',
            2 => 'movement_analysis.item_name',
            3 => 'movement_analysis.movement_category',
            4 => 'movement_analysis.demand_out',
            5 => 'movement_analysis.average_daily_out',
            6 => 'movement_analysis.ending_stock',
            7 => 'movement_analysis.stock_coverage_days',
            8 => 'movement_analysis.last_out_at',
        ];
        $orderColumn = (int) $request->input('order.0.column', 5);
        $orderDirection = strtolower((string) $request->input('order.0.dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        if (isset($sortColumns[$orderColumn])) {
            $query->orderBy($sortColumns[$orderColumn], $orderDirection);
        } else {
            $query->orderByDesc('movement_analysis.average_daily_out')
                ->orderBy('movement_analysis.sku');
        }

        $start = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', 25);
        if ($length > 0) {
            $query->skip($start)->take(min($length, 500));
        }

        $warehouseIds = (array) $filters['warehouse_ids'];
        $canViewMutations = Permission::can($request->user(), 'admin.inventory.stock-mutations.index');
        $rows = $query->get()->map(fn ($row) => [
            'item_id' => (int) $row->item_id,
            'sku' => $row->sku,
            'item_name' => $row->item_name,
            'item_status' => $row->item_status,
            'movement_category' => $row->movement_category,
            'demand_out' => (int) $row->demand_out,
            'demand_documents' => (int) $row->demand_documents,
            'average_daily_out' => round((float) $row->average_daily_out, 2),
            'ending_stock' => (int) $row->ending_stock,
            'turnover_rate' => $row->turnover_rate !== null ? round((float) $row->turnover_rate, 2) : null,
            'stock_coverage_days' => $row->stock_coverage_days !== null ? round((float) $row->stock_coverage_days, 1) : null,
            'last_out_at' => $row->last_out_at,
            'mutation_url' => $canViewMutations ? route('admin.inventory.stock-mutations.index', array_filter([
                'item_id' => $row->item_id,
                'warehouse_id' => count($warehouseIds) === 1 ? (int) $warehouseIds[0] : 'all',
                'direction' => 'out',
                'date_from' => $filters['date_from'],
                'date_to' => $filters['date_to'],
            ], fn ($value) => $value !== null && $value !== '')) : null,
        ]);

        return response()->json([
            'draw' => (int) $request->input('draw', 0),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'period' => [
                'date_from' => $filters['date_from'],
                'date_to' => $filters['date_to'],
                'days' => (int) Carbon::parse($filters['date_from'])
                    ->diffInDays(Carbon::parse($filters['date_to'])) + 1,
            ],
            'summary' => [
                'total_items' => (int) ($summary->total_items ?? 0),
                'fast_items' => (int) ($summary->fast_items ?? 0),
                'medium_items' => (int) ($summary->medium_items ?? 0),
                'slow_items' => (int) ($summary->slow_items ?? 0),
                'dead_stock_items' => (int) ($summary->dead_stock_items ?? 0),
                'no_stock_items' => (int) ($summary->no_stock_items ?? 0),
                'demand_out' => (int) ($summary->demand_out ?? 0),
                'ending_stock' => (int) ($summary->ending_stock ?? 0),
            ],
            'data' => $rows,
        ]);
    }

    public function export(Request $request)
    {
        $filters = $this->validatedFilters($request);
        $filename = sprintf(
            'laporan-saldo-stok-%s-sd-%s-%s.xlsx',
            $filters['date_from'],
            $filters['date_to'],
            now()->format('His')
        );

        return Excel::download(new StockBalanceReportExport($filters), $filename);
    }

    private function validatedFilters(Request $request): array
    {
        $validated = $request->validate([
            'date_from' => ['required', 'date_format:Y-m-d'],
            'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'warehouse_ids' => ['nullable', 'array'],
            'warehouse_ids.*' => ['integer', 'distinct', 'exists:warehouses,id'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'q' => ['nullable', 'string', 'max:150'],
            'movement_category' => ['nullable', 'string', 'in:fast,medium,slow,dead_stock,no_stock'],
        ], [
            'date_from.required' => 'Tanggal awal wajib diisi.',
            'date_to.required' => 'Tanggal akhir wajib diisi.',
            'date_to.after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal awal.',
        ]);

        $warehouseIds = collect($validated['warehouse_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        // Tetap menerima parameter lama warehouse_id untuk URL/bookmark yang sudah tersimpan.
        if ($warehouseIds->isEmpty() && ! empty($validated['warehouse_id'])) {
            $warehouseIds->push((int) $validated['warehouse_id']);
        }

        return [
            'date_from' => $validated['date_from'],
            'date_to' => $validated['date_to'],
            'warehouse_ids' => $warehouseIds->all(),
            'q' => trim((string) ($validated['q'] ?? '')),
            'movement_category' => trim((string) ($validated['movement_category'] ?? '')),
        ];
    }
}
