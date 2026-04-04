<?php
namespace App\Http\Controllers\Admin\ManajemenStok;
use App\Http\Controllers\Controller;
use App\Exports\WarehouseStokExport;
use App\Models\Inventory;
use App\Models\Warehouse;
use App\Models\Item;
use App\Models\StockMovement;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
class WarehouseStokController extends Controller
{
    public function index(Request $request)
    {
        $today = Carbon::now();
        $defaultStart = $today->copy()->startOfMonth();
        $defaultEnd = $today->copy();
        if ($request->ajax()) {
            $searchValue = $request->input('search.value', '');
            $start = (int) $request->input('start', 0);
            $length = (int) $request->input('length', 10);
            $draw = (int) $request->input('draw', 0);
            $warehouseFilter = $request->input('warehouse_filter');
            $categoryFilter = $request->input('category_filter');
            $dateFromInput = $request->input('date_from');
            $dateToInput = $request->input('date_to');
            try {
                $dateFrom = $dateFromInput ? Carbon::parse($dateFromInput)->startOfDay() : $defaultStart->copy()->startOfDay();
            } catch (\Throwable $th) {
                $dateFrom = $defaultStart->copy()->startOfDay();
            }
            try {
                $dateTo = $dateToInput ? Carbon::parse($dateToInput)->endOfDay() : $defaultEnd->copy()->endOfDay();
            } catch (\Throwable $th) {
                $dateTo = $defaultEnd->copy()->endOfDay();
            }
            if ($dateFrom->gt($dateTo)) {
                $tmpFrom = $dateFrom->copy();
                $dateFrom = $dateTo->copy()->startOfDay();
                $dateTo = $tmpFrom->copy()->endOfDay();
            }
            $columnMap = [];
            $columnIndex = 0;
            if (Auth::user()->warehouse_id === null) {
                $columnMap[$columnIndex++] = 'warehouses.name';
            }
            $columnMap[$columnIndex++] = 'items.nama_barang';
            $columnMap[$columnIndex++] = 'items.sku';
            $orderByColumnIndex = (int) $request->input('order.0.column', 0);
            $orderByColumnName = $columnMap[$orderByColumnIndex] ?? ($columnMap[array_key_first($columnMap)] ?? 'items.nama_barang');
            $orderDirection = $request->input('order.0.dir', 'asc');
            $query = Inventory::query()
                ->join('items', 'inventories.item_id', '=', 'items.id')
                ->join('warehouses', 'inventories.warehouse_id', '=', 'warehouses.id');
            $userWarehouseId = Auth::user()->warehouse_id;
            if ($userWarehouseId) {
                $query->where('inventories.warehouse_id', $userWarehouseId);
            } elseif ($warehouseFilter && $warehouseFilter !== 'semua') {
                $query->where('inventories.warehouse_id', $warehouseFilter);
            }
            if ($categoryFilter && $categoryFilter !== 'semua') {
                $query->where('items.item_category_id', $categoryFilter);
            }
            if (!empty($searchValue)) {
                $query->where(function ($q) use ($searchValue) {
                    $q->where('items.nama_barang', 'LIKE', "%{$searchValue}%")
                        ->orWhere('items.sku', 'LIKE', "%{$searchValue}%")
                        ->orWhere('warehouses.name', 'LIKE', "%{$searchValue}%");
                });
            }
            $totalRecordsQuery = Inventory::query();
            if ($userWarehouseId) {
                $totalRecordsQuery->where('warehouse_id', $userWarehouseId);
            } elseif ($warehouseFilter && $warehouseFilter !== 'semua') {
                $totalRecordsQuery->where('warehouse_id', $warehouseFilter);
            }
            if ($categoryFilter && $categoryFilter !== 'semua') {
                $totalRecordsQuery->whereHas('item', function ($q) use ($categoryFilter) {
                    $q->where('item_category_id', $categoryFilter);
                });
            }
            $totalRecords = $totalRecordsQuery->count();
            $totalFiltered = (clone $query)->count();
            $query->select([
                'items.sku as sku',
                'items.nama_barang as item_name',
                'warehouses.name as warehouse_name',
                'inventories.quantity',
                'inventories.koli',
                'inventories.item_id',
                'inventories.warehouse_id',
            ]);
            $query->addSelect([
                'within_qty' => StockMovement::query()
                    ->selectRaw('COALESCE(SUM(quantity),0)')
                    ->whereColumn('stock_movements.item_id', 'inventories.item_id')
                    ->whereColumn('stock_movements.warehouse_id', 'inventories.warehouse_id')
                    ->whereBetween('date', [$dateFrom->copy(), $dateTo->copy()])
                    ->whereIn('type', ['stock_in', 'stock_out']),
                'within_koli' => StockMovement::query()
                    ->selectRaw('COALESCE(SUM(koli),0)')
                    ->whereColumn('stock_movements.item_id', 'inventories.item_id')
                    ->whereColumn('stock_movements.warehouse_id', 'inventories.warehouse_id')
                    ->whereBetween('date', [$dateFrom->copy(), $dateTo->copy()])
                    ->whereIn('type', ['stock_in', 'stock_out']),
                'incoming_qty' => StockMovement::query()
                    ->selectRaw('COALESCE(SUM(quantity),0)')
                    ->whereColumn('stock_movements.item_id', 'inventories.item_id')
                    ->whereColumn('stock_movements.warehouse_id', 'inventories.warehouse_id')
                    ->whereBetween('date', [$dateFrom->copy(), $dateTo->copy()])
                    ->where('type', 'stock_in'),
                'incoming_koli' => StockMovement::query()
                    ->selectRaw('COALESCE(SUM(koli),0)')
                    ->whereColumn('stock_movements.item_id', 'inventories.item_id')
                    ->whereColumn('stock_movements.warehouse_id', 'inventories.warehouse_id')
                    ->whereBetween('date', [$dateFrom->copy(), $dateTo->copy()])
                    ->where('type', 'stock_in'),
                'outgoing_qty' => StockMovement::query()
                    ->selectRaw('COALESCE(SUM(ABS(quantity)),0)')
                    ->whereColumn('stock_movements.item_id', 'inventories.item_id')
                    ->whereColumn('stock_movements.warehouse_id', 'inventories.warehouse_id')
                    ->whereBetween('date', [$dateFrom->copy(), $dateTo->copy()])
                    ->where('type', 'stock_out'),
                'outgoing_koli' => StockMovement::query()
                    ->selectRaw('COALESCE(SUM(ABS(koli)),0)')
                    ->whereColumn('stock_movements.item_id', 'inventories.item_id')
                    ->whereColumn('stock_movements.warehouse_id', 'inventories.warehouse_id')
                    ->whereBetween('date', [$dateFrom->copy(), $dateTo->copy()])
                    ->where('type', 'stock_out'),
                'after_qty' => StockMovement::query()
                    ->selectRaw('COALESCE(SUM(quantity),0)')
                    ->whereColumn('stock_movements.item_id', 'inventories.item_id')
                    ->whereColumn('stock_movements.warehouse_id', 'inventories.warehouse_id')
                    ->where('date', '>', $dateTo->copy())
                    ->whereIn('type', ['stock_in', 'stock_out']),
                'after_koli' => StockMovement::query()
                    ->selectRaw('COALESCE(SUM(koli),0)')
                    ->whereColumn('stock_movements.item_id', 'inventories.item_id')
                    ->whereColumn('stock_movements.warehouse_id', 'inventories.warehouse_id')
                    ->where('date', '>', $dateTo->copy())
                    ->whereIn('type', ['stock_in', 'stock_out']),
            ]);
            $query->orderBy($orderByColumnName, $orderDirection === 'desc' ? 'desc' : 'asc');
            $data = $query->offset($start)
                ->limit($length)
                ->get()
                ->map(function ($row) {
                    $quantity = (float) $row->quantity;
                    $koli = (float) $row->koli;
                    $withinQty = (float) ($row->within_qty ?? 0);
                    $withinKoli = (float) ($row->within_koli ?? 0);
                    $afterQty = (float) ($row->after_qty ?? 0);
                    $afterKoli = (float) ($row->after_koli ?? 0);
                    $incomingQty = (float) ($row->incoming_qty ?? 0);
                    $incomingKoli = (float) ($row->incoming_koli ?? 0);
                    $outgoingQty = (float) ($row->outgoing_qty ?? 0);
                    $outgoingKoli = (float) ($row->outgoing_koli ?? 0);
                    $closingQty = $quantity - $afterQty;
                    $closingKoli = $koli - $afterKoli;
                    $openingQty = $closingQty - $withinQty;
                    $openingKoli = $closingKoli - $withinKoli;
                    return [
                        'warehouse_name' => $row->warehouse_name,
                        'item_name' => $row->item_name,
                        'sku' => $row->sku,
                        'item_id' => (int) $row->item_id,
                        'warehouse_id' => (int) $row->warehouse_id,
                        'opening_qty' => round($openingQty, 2),
                        'opening_koli' => round($openingKoli, 2),
                        'incoming_qty' => round($incomingQty, 2),
                        'incoming_koli' => round($incomingKoli, 2),
                        'outgoing_qty' => round($outgoingQty, 2),
                        'outgoing_koli' => round($outgoingKoli, 2),
                        'closing_qty' => round($closingQty, 2),
                        'closing_koli' => round($closingKoli, 2),
                    ];
                })
                ->values();
            return response()->json([
                'draw' => $draw,
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $totalFiltered,
                'data' => $data,
            ]);
        }
        $warehouses = Warehouse::all();
        $categories = \App\Models\ItemCategory::all();
        return view('admin.manajemenstok.warehousestok.index', [
            'warehouses' => $warehouses,
            'categories' => $categories,
            'defaultDateFrom' => $defaultStart->toDateString(),
            'defaultDateTo' => $defaultEnd->toDateString(),
        ]);
    }
public function export(Request $request)
{
    $today = Carbon::now();
    $defaultStart = $today->copy()->startOfMonth();
    $defaultEnd = $today->copy();
    $warehouseFilter = $request->input('warehouse_filter');
    $categoryFilter = $request->input('category_filter');
    $dateFromInput = $request->input('date_from');
    $dateToInput = $request->input('date_to');
    $searchValue = $request->input('search.value');
    if (is_null($searchValue)) {
        $search = $request->input('search');
        if (is_array($search)) {
            $searchValue = $search['value'] ?? '';
        } else {
            $searchValue = $search ?? '';
        }
    }
    $searchValue = trim((string) $searchValue);
    try {
        $dateFrom = $dateFromInput ? Carbon::parse($dateFromInput)->startOfDay() : $defaultStart->copy()->startOfDay();
    } catch (\Throwable $th) {
        $dateFrom = $defaultStart->copy()->startOfDay();
    }
    try {
        $dateTo = $dateToInput ? Carbon::parse($dateToInput)->endOfDay() : $defaultEnd->copy()->endOfDay();
    } catch (\Throwable $th) {
        $dateTo = $defaultEnd->copy()->endOfDay();
    }
    if ($dateFrom->gt($dateTo)) {
        $tmpFrom = $dateFrom->copy();
        $dateFrom = $dateTo->copy()->startOfDay();
        $dateTo = $tmpFrom->copy()->endOfDay();
    }
    $columnMap = [];
    $columnIndex = 0;
    if (Auth::user()->warehouse_id === null) {
        $columnMap[$columnIndex++] = 'warehouses.name';
    }
    $columnMap[$columnIndex++] = 'items.nama_barang';
    $columnMap[$columnIndex++] = 'items.sku';
    $orderByColumnIndex = (int) $request->input('order.0.column', 0);
    $orderByColumnName = $columnMap[$orderByColumnIndex] ?? ($columnMap[array_key_first($columnMap)] ?? 'items.nama_barang');
    $orderDirection = $request->input('order.0.dir', 'asc');
    $query = Inventory::query()
        ->join('items', 'inventories.item_id', '=', 'items.id')
        ->join('warehouses', 'inventories.warehouse_id', '=', 'warehouses.id');
    $userWarehouseId = Auth::user()->warehouse_id;
    if ($userWarehouseId) {
        $query->where('inventories.warehouse_id', $userWarehouseId);
    } elseif ($warehouseFilter && $warehouseFilter !== 'semua') {
        $query->where('inventories.warehouse_id', $warehouseFilter);
    }
    if ($categoryFilter && $categoryFilter !== 'semua') {
        $query->where('items.item_category_id', $categoryFilter);
    }
    if ($searchValue !== '') {
        $query->where(function ($q) use ($searchValue) {
            $q->where('items.nama_barang', 'LIKE', "%{$searchValue}%")
                ->orWhere('items.sku', 'LIKE', "%{$searchValue}%")
                ->orWhere('warehouses.name', 'LIKE', "%{$searchValue}%");
        });
    }
    $query->select([
        'items.sku as sku',
        'items.nama_barang as item_name',
        'warehouses.name as warehouse_name',
        'inventories.quantity',
        'inventories.koli',
        'inventories.item_id',
        'inventories.warehouse_id',
    ]);
    $query->addSelect([
        'within_qty' => StockMovement::query()
            ->selectRaw('COALESCE(SUM(quantity),0)')
            ->whereColumn('stock_movements.item_id', 'inventories.item_id')
            ->whereColumn('stock_movements.warehouse_id', 'inventories.warehouse_id')
            ->whereBetween('date', [$dateFrom->copy(), $dateTo->copy()])
            ->whereIn('type', ['stock_in', 'stock_out']),
        'within_koli' => StockMovement::query()
            ->selectRaw('COALESCE(SUM(koli),0)')
            ->whereColumn('stock_movements.item_id', 'inventories.item_id')
            ->whereColumn('stock_movements.warehouse_id', 'inventories.warehouse_id')
            ->whereBetween('date', [$dateFrom->copy(), $dateTo->copy()])
            ->whereIn('type', ['stock_in', 'stock_out']),
        'incoming_qty' => StockMovement::query()
            ->selectRaw('COALESCE(SUM(quantity),0)')
            ->whereColumn('stock_movements.item_id', 'inventories.item_id')
            ->whereColumn('stock_movements.warehouse_id', 'inventories.warehouse_id')
            ->whereBetween('date', [$dateFrom->copy(), $dateTo->copy()])
            ->where('type', 'stock_in'),
        'incoming_koli' => StockMovement::query()
            ->selectRaw('COALESCE(SUM(koli),0)')
            ->whereColumn('stock_movements.item_id', 'inventories.item_id')
            ->whereColumn('stock_movements.warehouse_id', 'inventories.warehouse_id')
            ->whereBetween('date', [$dateFrom->copy(), $dateTo->copy()])
            ->where('type', 'stock_in'),
        'outgoing_qty' => StockMovement::query()
            ->selectRaw('COALESCE(SUM(ABS(quantity)),0)')
            ->whereColumn('stock_movements.item_id', 'inventories.item_id')
            ->whereColumn('stock_movements.warehouse_id', 'inventories.warehouse_id')
            ->whereBetween('date', [$dateFrom->copy(), $dateTo->copy()])
            ->where('type', 'stock_out'),
        'outgoing_koli' => StockMovement::query()
            ->selectRaw('COALESCE(SUM(ABS(koli)),0)')
            ->whereColumn('stock_movements.item_id', 'inventories.item_id')
            ->whereColumn('stock_movements.warehouse_id', 'inventories.warehouse_id')
            ->whereBetween('date', [$dateFrom->copy(), $dateTo->copy()])
            ->where('type', 'stock_out'),
        'after_qty' => StockMovement::query()
            ->selectRaw('COALESCE(SUM(quantity),0)')
            ->whereColumn('stock_movements.item_id', 'inventories.item_id')
            ->whereColumn('stock_movements.warehouse_id', 'inventories.warehouse_id')
            ->where('date', '>', $dateTo->copy())
            ->whereIn('type', ['stock_in', 'stock_out']),
        'after_koli' => StockMovement::query()
            ->selectRaw('COALESCE(SUM(koli),0)')
            ->whereColumn('stock_movements.item_id', 'inventories.item_id')
            ->whereColumn('stock_movements.warehouse_id', 'inventories.warehouse_id')
            ->where('date', '>', $dateTo->copy())
            ->whereIn('type', ['stock_in', 'stock_out']),
    ]);
    $query->orderBy($orderByColumnName, $orderDirection === 'desc' ? 'desc' : 'asc');
    $rows = $query->get()
        ->map(function ($row) {
            $quantity = (float) $row->quantity;
            $koli = (float) $row->koli;
            $withinQty = (float) ($row->within_qty ?? 0);
            $withinKoli = (float) ($row->within_koli ?? 0);
            $afterQty = (float) ($row->after_qty ?? 0);
            $afterKoli = (float) ($row->after_koli ?? 0);
            $incomingQty = (float) ($row->incoming_qty ?? 0);
            $incomingKoli = (float) ($row->incoming_koli ?? 0);
            $outgoingQty = (float) ($row->outgoing_qty ?? 0);
            $outgoingKoli = (float) ($row->outgoing_koli ?? 0);
            $closingQty = $quantity - $afterQty;
            $closingKoli = $koli - $afterKoli;
            $openingQty = $closingQty - $withinQty;
            $openingKoli = $closingKoli - $withinKoli;
            return [
                'warehouse_name' => $row->warehouse_name,
                'item_name' => $row->item_name,
                'sku' => $row->sku,
                'opening_qty' => round($openingQty, 2),
                'opening_koli' => round($openingKoli, 2),
                'incoming_qty' => round($incomingQty, 2),
                'incoming_koli' => round($incomingKoli, 2),
                'outgoing_qty' => round($outgoingQty, 2),
                'outgoing_koli' => round($outgoingKoli, 2),
                'closing_qty' => round($closingQty, 2),
                'closing_koli' => round($closingKoli, 2),
            ];
        })
        ->values();
    $showWarehouseColumn = $userWarehouseId === null;
    $headings = [];
    if ($showWarehouseColumn) {
        $headings[] = 'Gudang';
    }
    $headings = array_merge($headings, [
        'Nama Barang',
        'SKU',
        'Stok Awal (Qty)',
        'Stok Awal (Koli)',
        'Stok Masuk (Qty)',
        'Stok Masuk (Koli)',
        'Stok Keluar (Qty)',
        'Stok Keluar (Koli)',
        'Stok Akhir (Qty)',
        'Stok Akhir (Koli)',
    ]);
    $dataRows = [];
    foreach ($rows as $row) {
        $line = [];
        if ($showWarehouseColumn) {
            $line[] = $row['warehouse_name'] ?? '';
        }
        $line[] = $row['item_name'] ?? '';
        $line[] = $row['sku'] ?? '';
        $line[] = (float) ($row['opening_qty'] ?? 0);
        $line[] = (float) ($row['opening_koli'] ?? 0);
        $line[] = (float) ($row['incoming_qty'] ?? 0);
        $line[] = (float) ($row['incoming_koli'] ?? 0);
        $line[] = (float) ($row['outgoing_qty'] ?? 0);
        $line[] = (float) ($row['outgoing_koli'] ?? 0);
        $line[] = (float) ($row['closing_qty'] ?? 0);
        $line[] = (float) ($row['closing_koli'] ?? 0);
        $dataRows[] = $line;
    }
    $fileName = 'warehouse_stok_' . now()->format('Ymd_His') . '.xlsx';
    return Excel::download(new WarehouseStokExport($dataRows, $headings), $fileName);
}
    public function show(Warehouse $warehouse, Item $item, Request $request)
    {
        // Render shell; data is fetched via AJAX
        return view('admin.manajemenstok.warehousestok.show', compact('warehouse', 'item'));
    }
    public function data(Warehouse $warehouse, Item $item, Request $request)
    {
        $type = $request->input('type', 'all');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $perPage = (int) $request->input('per_page', 20);
        $dateFrom = $dateFrom ? \Carbon\Carbon::parse($dateFrom)->startOfDay() : now()->subDays(14)->startOfDay();
        $dateTo = $dateTo ? \Carbon\Carbon::parse($dateTo)->endOfDay() : now()->endOfDay();
        $q = StockMovement::where('warehouse_id', $warehouse->id)
            ->where('item_id', $item->id)
            ->whereBetween('date', [$dateFrom, $dateTo]);
        if ($type !== 'all') {
            $q->where('type', $type);
        }
        $movements = $q->orderBy('date', 'desc')->orderBy('id', 'desc')->paginate($perPage);
        // Summary window
        $summaryQ = StockMovement::where('warehouse_id', $warehouse->id)
            ->where('item_id', $item->id)
            ->whereBetween('date', [$dateFrom, $dateTo]);
        $in = (clone $summaryQ)->where('type', 'stock_in')->sum('quantity');
        $out = abs((clone $summaryQ)->where('type', 'stock_out')->sum('quantity'));
        $currentStock = optional(Inventory::where('warehouse_id', $warehouse->id)->where('item_id', $item->id)->first())->quantity ?? 0;
        // Daily snapshot (grouped deltas within range)
        $dailyGrouped = StockMovement::where('warehouse_id', $warehouse->id)
            ->where('item_id', $item->id)
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->selectRaw('DATE(date) as d, SUM(quantity) as delta')
            ->groupBy('d')
            ->orderBy('d', 'desc')
            ->get();
        // Build continuous daily series from start to end with delta and stock per day
        $deltaMap = $dailyGrouped->pluck('delta', 'd');
        $currentStock = optional(Inventory::where('warehouse_id', $warehouse->id)->where('item_id', $item->id)->first())->quantity ?? 0;
        $sumWithin = (float) $dailyGrouped->sum('delta');
        $sumAfter = (float) StockMovement::where('warehouse_id', $warehouse->id)
            ->where('item_id', $item->id)
            ->where('date', '>', $dateTo)
            ->whereIn('type', ['stock_in', 'stock_out'])
            ->sum('quantity');
        // opening stock at start date: current = opening + within + after
        $opening = (float) $currentStock - $sumWithin - $sumAfter;
        $daily = [];
        $running = 0.0;
        $iterDate = $dateFrom->copy();
        while ($iterDate->lte($dateTo)) {
            $key = $iterDate->toDateString();
            $delta = (float) ($deltaMap[$key] ?? 0);
            $running += $delta;
            $daily[] = [
                'd' => $key,
                'delta' => $delta,
                'stock' => $opening + $running,
            ];
            $iterDate->addDay();
        }
        // newest to oldest
        $daily = array_reverse($daily);
        // paginate daily
        $dailyPage = (int) $request->input('daily_page', 1);
        $dailyPerPage = (int) $request->input('daily_per_page', 10);
        $dailyTotal = count($daily);
        $dailyLastPage = (int) ceil($dailyTotal / max($dailyPerPage, 1));
        $dailyData = array_slice($daily, max(0, ($dailyPage - 1) * $dailyPerPage), $dailyPerPage);
        return response()->json([
            'summary' => [
                'in' => (float) $in,
                'out' => (float) $out,
                'last_stock' => (float) $currentStock,
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
            ],
            'daily' => [
                'data' => $dailyData,
                'current_page' => $dailyPage,
                'last_page' => $dailyLastPage,
                'per_page' => $dailyPerPage,
                'total' => $dailyTotal,
            ],
            'movements' => [
                'data' => $movements->items(),
                'current_page' => $movements->currentPage(),
                'last_page' => $movements->lastPage(),
                'per_page' => $movements->perPage(),
                'total' => $movements->total(),
            ],
        ]);
    }
}
