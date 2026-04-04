<?php

namespace App\Http\Controllers\Admin\ManajemenStok;

use App\Http\Controllers\Controller;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\Warehouse;
use App\Models\Item;
use App\Models\UserActivity;
use App\Models\Inventory;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class StockOpnameController extends Controller
{
    private function generateNewCode()
    {
        $prefix = 'OPN';
        $date = now()->format('Ymd');
        $latest = StockOpname::where('code', 'LIKE', "$prefix-$date-%")->latest('id')->first();
        $seq = $latest ? ((int) substr($latest->code, -4)) + 1 : 1;
        return sprintf('%s-%s-%04d', $prefix, $date, $seq);
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $searchValue = $request->input('search.value', '');
            $start = (int) $request->input('start', 0);
            $length = (int) $request->input('length', 10);
            $draw = (int) $request->input('draw', 0);
            $warehouseFilter = $request->input('warehouse');
            $dateFilter = $request->input('date');

            $columns = [
                0 => 'so.code',
                1 => 'so.start_date',
                2 => 'w.name',
                3 => DB::raw('items_count'),
                4 => DB::raw('status_text'),
                5 => 'so.id',
            ];

            $orderByColumnIndex = $request->input('order.0.column', 0);
            $orderByColumnName = $columns[$orderByColumnIndex] ?? 'so.start_date';
            $orderDirection = $request->input('order.0.dir', 'desc');

            $query = StockOpname::from('stock_opnames as so')
                ->leftJoin('warehouses as w', 'so.warehouse_id', '=', 'w.id')
                ->leftJoin('users as us', 'so.started_by', '=', 'us.id')
                ->leftJoin('users as uc', 'so.completed_by', '=', 'uc.id')
                ->select(
                    'so.id', 'so.code', 'so.start_date', 'so.completed_date', 'so.warehouse_id', 'w.name as warehouse_name',
                    'us.name as started_by_name', 'uc.name as completed_by_name',
                    DB::raw('(select count(*) from stock_opname_items soi where soi.stock_opname_id = so.id) as items_count'),
                    DB::raw("CASE WHEN so.completed_by IS NULL THEN 'in_progress' ELSE 'completed' END as status_text")
                );

            $totalRecordsQuery = StockOpname::query();
            if (auth()->user()->warehouse_id) {
                $totalRecordsQuery->where('warehouse_id', auth()->user()->warehouse_id);
                $query->where('so.warehouse_id', auth()->user()->warehouse_id);
            }
            $totalRecords = $totalRecordsQuery->count();

            if ($warehouseFilter && $warehouseFilter !== 'semua' && auth()->user()->warehouse_id === null) {
                $query->where('so.warehouse_id', $warehouseFilter);
            }

            if ($dateFilter && $dateFilter !== 'semua') {
                if (strpos($dateFilter, ' to ') !== false) {
                    [$startDate, $endDate] = explode(' to ', $dateFilter);
                    $query->whereBetween('so.start_date', [$startDate, $endDate]);
                } else {
                    $query->whereDate('so.start_date', $dateFilter);
                }
            }

            if (!empty($searchValue)) {
                $query->where(function ($q) use ($searchValue) {
                    $q->where('so.code', 'LIKE', "%{$searchValue}%")
                      ->orWhere('w.name', 'LIKE', "%{$searchValue}%");
                });
            }

            $totalFiltered = (clone $query)->count();

            $data = $query
                ->orderBy($orderByColumnName, $orderDirection)
                ->offset($start)
                ->limit($length)
                ->get();

            return response()->json([
                'draw' => $draw,
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $totalFiltered,
                'data' => $data,
            ]);
        }

        $warehouses = auth()->user()->warehouse_id ? [] : Warehouse::all();
        return view('admin.manajemenstok.stok-opname.index', compact('warehouses'));
    }

    public function create()
    {
        $warehouses = Warehouse::all();
        $items = Item::with('uom')->get();
        $newCode = $this->generateNewCode();
        return view('admin.manajemenstok.stok-opname.create', compact('warehouses', 'items', 'newCode'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required|unique:stock_opnames,code',
            'warehouse_id' => 'required|exists:warehouses,id',
            'start_date' => 'required|date',
            'description' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|distinct|exists:items,id',
            'items.*.system_quantity' => 'required|numeric',
            'items.*.system_koli' => 'nullable|numeric',
            'items.*.physical_quantity' => 'required|numeric',
            'items.*.physical_koli' => 'nullable|numeric',
            'items.*.description' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $stockOpname = StockOpname::create([
                'code' => $request->code,
                'warehouse_id' => $request->warehouse_id,
                'description' => $request->description,
                'started_by' => Auth::id(),
                'completed_by' => null,
                'start_date' => $request->start_date,
                'completed_date' => null,
                'status' => 'in_progress',
            ]);

            foreach ($request->items as $item) {
                $sysQty = (float) ($item['system_quantity'] ?? 0);
                $phyQty = (float) ($item['physical_quantity'] ?? 0);
                $sysKoli = (float) ($item['system_koli'] ?? 0);
                $phyKoli = (float) ($item['physical_koli'] ?? 0);

                StockOpnameItem::create([
                    'stock_opname_id' => $stockOpname->id,
                    'item_id' => $item['item_id'],
                    'system_quantity' => $sysQty,
                    'system_koli' => $sysKoli,
                    'physical_quantity' => $phyQty,
                    'physical_koli' => $phyKoli,
                    'discrepancy_quantity' => $phyQty - $sysQty,
                    'discrepancy_koli' => $phyKoli - $sysKoli,
                    'description' => $item['description'] ?? null,
                ]);
            }

            // Log user activity (do not fail main tx if logging fails)
            try {
                $itemsCount = is_array($request->items ?? null) ? count($request->items) : 0;
                UserActivity::create([
                    'user_id' => Auth::id(),
                    'activity' => 'created',
                    'menu' => 'Stock Opname',
                    'description' => 'Menambahkan Stock Opname: ' . $stockOpname->code . ' (Gudang ID: ' . ($request->warehouse_id ?? '-') . ', Items: ' . $itemsCount . ')',
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->header('User-Agent'),
                ]);
            } catch (\Throwable $e) { /* ignore activity errors */ }

            DB::commit();
            return redirect()->route('admin.manajemenstok.stok-opname.index')->with('success', 'Stock Opname berhasil dibuat.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal menyimpan: ' . $e->getMessage())->withInput();
        }
    }

    public function edit(StockOpname $stok_opname)
    {
        if (($stok_opname->status ?? null) === 'completed' || !is_null($stok_opname->completed_by)) {
            return redirect()
                ->route('admin.manajemenstok.stok-opname.index')
                ->with('error', 'Dokumen stock opname yang sudah selesai tidak dapat diedit.');
        }
        $stok_opname->load('items.item');
        $warehouses = Warehouse::all();
        $items = Item::with('uom')->get();
        return view('admin.manajemenstok.stok-opname.edit', compact('stok_opname', 'warehouses', 'items'));
    }

    public function update(Request $request, StockOpname $stok_opname)
    {
        if (($stok_opname->status ?? null) === 'completed' || !is_null($stok_opname->completed_by)) {
            return redirect()
                ->route('admin.manajemenstok.stok-opname.index')
                ->with('error', 'Dokumen stock opname yang sudah selesai tidak dapat diedit.');
        }
        $request->validate([
            'code' => 'required|unique:stock_opnames,code,' . $stok_opname->id,
            'warehouse_id' => 'required|exists:warehouses,id',
            'start_date' => 'required|date',
            'description' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|distinct|exists:items,id',
            'items.*.system_quantity' => 'required|numeric',
            'items.*.system_koli' => 'nullable|numeric',
            'items.*.physical_quantity' => 'required|numeric',
            'items.*.physical_koli' => 'nullable|numeric',
            'items.*.description' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $stok_opname->update([
                'code' => $request->code,
                'warehouse_id' => $request->warehouse_id,
                'description' => $request->description,
                'start_date' => $request->start_date,
            ]);

            $stok_opname->items()->delete();
            foreach ($request->items as $item) {
                $sysQty = (float) ($item['system_quantity'] ?? 0);
                $phyQty = (float) ($item['physical_quantity'] ?? 0);
                $sysKoli = (float) ($item['system_koli'] ?? 0);
                $phyKoli = (float) ($item['physical_koli'] ?? 0);

                StockOpnameItem::create([
                    'stock_opname_id' => $stok_opname->id,
                    'item_id' => $item['item_id'],
                    'system_quantity' => $sysQty,
                    'system_koli' => $sysKoli,
                    'physical_quantity' => $phyQty,
                    'physical_koli' => $phyKoli,
                    'discrepancy_quantity' => $phyQty - $sysQty,
                    'discrepancy_koli' => $phyKoli - $sysKoli,
                    'description' => $item['description'] ?? null,
                ]);
            }

            // Log user activity (do not fail main tx if logging fails)
            try {
                $itemsCount = is_array($request->items ?? null) ? count($request->items) : 0;
                UserActivity::create([
                    'user_id' => Auth::id(),
                    'activity' => 'updated',
                    'menu' => 'Stock Opname',
                    'description' => 'Mengubah Stock Opname: ' . ($request->code ?? $stok_opname->code) . ' (Gudang ID: ' . ($request->warehouse_id ?? $stok_opname->warehouse_id) . ', Items: ' . $itemsCount . ')',
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->header('User-Agent'),
                ]);
            } catch (\Throwable $e) { /* ignore activity errors */ }

            DB::commit();
            return redirect()->route('admin.manajemenstok.stok-opname.index')->with('success', 'Stock Opname berhasil diperbarui.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal memperbarui: ' . $e->getMessage())->withInput();
        }
    }

    public function destroy(Request $request, StockOpname $stok_opname)
    {
        DB::beginTransaction();
        try {
            $code = $stok_opname->code;
            $stok_opname->items()->delete();
            $stok_opname->delete();

            try {
                UserActivity::create([
                    'user_id' => Auth::id(),
                    'activity' => 'deleted',
                    'menu' => 'Stock Opname',
                    'description' => 'Menghapus Stock Opname: ' . $code,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->header('User-Agent'),
                ]);
            } catch (\Throwable $e) { /* ignore activity errors */ }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Stock Opname berhasil dihapus.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal menghapus: ' . $e->getMessage()], 500);
        }
    }

    public function getSystemStock(Request $request)
    {
        $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'item_id' => 'required|exists:items,id',
        ]);

        $inv = Inventory::where('warehouse_id', $request->warehouse_id)
            ->where('item_id', $request->item_id)
            ->first();

        return response()->json([
            'quantity' => (float) ($inv->quantity ?? 0),
            'koli' => (float) ($inv->koli ?? 0),
        ]);
    }

    public function updateStatus(Request $request, StockOpname $stok_opname)
    {
        $request->validate([
            'status' => 'required|in:completed',
        ]);

        DB::beginTransaction();
        try {
            if ($request->status === 'completed') {
                $stok_opname->status = 'completed';
                $stok_opname->completed_by = Auth::id();
                if (!$stok_opname->completed_date) {
                    $stok_opname->completed_date = now()->toDateString();
                }
                $stok_opname->load('items');

                // Apply physical counts to inventory and create stock movements for adjustments
                foreach ($stok_opname->items as $soItem) {
                    $inv = Inventory::firstOrCreate(
                        ['warehouse_id' => $stok_opname->warehouse_id, 'item_id' => $soItem->item_id],
                        ['quantity' => 0, 'koli' => 0]
                    );

                    $beforeQty = (float) ($inv->quantity ?? 0);
                    $beforeKoli = (float) ($inv->koli ?? 0);
                    $targetQty = (float) ($soItem->physical_quantity ?? 0);
                    $targetKoli = (float) ($soItem->physical_koli ?? 0);
                    $deltaQty = $targetQty - $beforeQty;
                    $deltaKoli = $targetKoli - $beforeKoli;

                    if ($deltaQty != 0 || $deltaKoli != 0) {
                        StockMovement::create([
                            'warehouse_id' => $stok_opname->warehouse_id,
                            'item_id' => $soItem->item_id,
                            'date' => $stok_opname->completed_date,
                            'quantity' => $deltaQty,
                            'koli' => $deltaKoli,
                            'stock_before' => $beforeQty,
                            'stock_after' => $targetQty,
                            'type' => $deltaQty > 0 ? 'stock_in' : ($deltaQty < 0 ? 'stock_out' : 'stock_opname'),
                            'description' => 'Penyesuaian Stock Opname: ' . $stok_opname->code,
                            'user_id' => Auth::id(),
                            'reference_id' => $soItem->id,
                            'reference_type' => 'stock_opname_items',
                        ]);

                        $inv->quantity = $targetQty;
                        $inv->koli = $targetKoli;
                        $inv->save();
                    }
                }

                $stok_opname->save();

            try {
                UserActivity::create([
                    'user_id' => Auth::id(),
                    'activity' => 'approved',
                    'menu' => 'Stock Opname',
                    'description' => 'Menyelesaikan Stock Opname: ' . $stok_opname->code,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->header('User-Agent'),
                ]);
            } catch (\Throwable $e) { /* ignore activity errors */ }
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Status berhasil diperbarui.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal memperbarui status: '.$e->getMessage()], 500);
        }
    }

    public function show(StockOpname $stok_opname)
    {
        $stok_opname->load(['warehouse', 'startedBy', 'completedBy', 'items.item']);
        $totals = [
            'count_items' => $stok_opname->items->count(),
            'system_qty' => $stok_opname->items->sum('system_quantity'),
            'system_koli' => $stok_opname->items->sum('system_koli'),
            'physical_qty' => $stok_opname->items->sum('physical_quantity'),
            'physical_koli' => $stok_opname->items->sum('physical_koli'),
            'disc_qty' => $stok_opname->items->sum('discrepancy_quantity'),
            'disc_koli' => $stok_opname->items->sum('discrepancy_koli'),
        ];

        return view('admin.manajemenstok.stok-opname.show', compact('stok_opname', 'totals'));
    }
}
