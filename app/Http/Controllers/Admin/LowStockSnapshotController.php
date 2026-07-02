<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LowStockSnapshot;
use App\Models\LowStockSnapshotItem;
use App\Support\LowStockService;
use Illuminate\Http\Request;

class LowStockSnapshotController extends Controller
{
    public function index()
    {
        $warehouses = app(LowStockService::class)->safetyReportWarehouses()
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.reports.low-stock-snapshots.index', [
            'warehouses' => $warehouses,
            'dataUrl' => route('admin.reports.low-stock-snapshots.data'),
            'storeUrl' => route('admin.reports.low-stock-snapshots.store'),
            'detailDataUrlTpl' => route('admin.reports.low-stock-snapshots.items', ':id'),
        ]);
    }

    public function data(Request $request)
    {
        $query = LowStockSnapshot::query()->with('warehouse')->orderByDesc('snapshot_at')->orderByDesc('id');

        if ($request->filled('warehouse_id')) {
            $warehouseId = $request->input('warehouse_id');
            if ($warehouseId === 'all') {
                $query->where('scope', 'all');
            } elseif (is_numeric($warehouseId)) {
                $query->where('warehouse_id', (int) $warehouseId);
            }
        }

        $dateFrom = trim((string) $request->input('date_from', ''));
        if ($dateFrom !== '') {
            $query->whereDate('snapshot_at', '>=', $dateFrom);
        }

        $dateTo = trim((string) $request->input('date_to', ''));
        if ($dateTo !== '') {
            $query->whereDate('snapshot_at', '<=', $dateTo);
        }

        $recordsTotal = LowStockSnapshot::count();
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $data = $query->get()->map(fn (LowStockSnapshot $snapshot) => [
            'id' => $snapshot->id,
            'snapshot_at' => $snapshot->snapshot_at?->format('Y-m-d H:i') ?? '-',
            'scope' => $snapshot->scope,
            'warehouse' => $snapshot->scope === 'all' ? 'Semua Gudang' : ($snapshot->warehouse_name ?? $snapshot->warehouse?->name ?? '-'),
            'total_low' => $snapshot->total_low,
            'total_out_of_stock' => $snapshot->total_out_of_stock,
            'total_gap' => $snapshot->total_gap,
            'source' => $snapshot->source,
        ]);

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'warehouse_id' => ['nullable'],
        ]);

        $warehouseId = $validated['warehouse_id'] ?? 'all';
        $snapshot = app(LowStockService::class)->createSnapshot($warehouseId, 'manual', auth()->id());

        return response()->json([
            'message' => 'Snapshot low stock berhasil dibuat.',
            'snapshot_id' => $snapshot->id,
            'total_low' => $snapshot->total_low,
        ]);
    }

    public function items(Request $request, int $snapshot)
    {
        $query = LowStockSnapshotItem::query()
            ->where('low_stock_snapshot_id', $snapshot)
            ->orderByDesc('gap')
            ->orderBy('warehouse')
            ->orderBy('sku');

        $status = $request->input('status');
        if ($status === 'out') {
            $query->where('status', 'Out of Stock');
        } elseif ($status === 'low') {
            $query->where('status', 'Low Stock');
        }

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('sku', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        $recordsTotal = LowStockSnapshotItem::where('low_stock_snapshot_id', $snapshot)->count();
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $query->get(),
        ]);
    }
}
