<?php

namespace App\Http\Controllers\Admin\ManajemenStok;

use App\Http\Controllers\Controller;
use App\Models\Adjustment;
use App\Models\AdjustmentItem;
use App\Models\Inventory;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\UserActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class AdjustmentController extends Controller
{
    private function generateNewCode()
    {
        $prefix = 'ADJ';
        $date = now()->format('Ymd');
        $latestAdjustment = Adjustment::where('code', 'LIKE', "$prefix-$date-%")->latest('id')->first();

        if ($latestAdjustment) {
            $sequence = (int) substr($latestAdjustment->code, -4) + 1;
        } else {
            $sequence = 1;
        }

        return sprintf('%s-%s-%04d', $prefix, $date, $sequence);
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $searchValue = $request->input('search.value', '');
            $start = $request->input('start', 0);
            $length = $request->input('length', 10);
            $draw = $request->input('draw', 0);
            $statusFilter = $request->input('status');
            $dateFilter = $request->input('date');
            $warehouseFilter = $request->input('warehouse_id');

            // Define columns for sorting
            $columns = [
                0 => 'adj.code',
                1 => 'adj.adjustment_date',
                2 => 'warehouse_name',
                3 => 'items_name',
                4 => 'adj.status',
                5 => 'adj.id', // Actions column, not sortable
            ];
            $orderByColumnIndex = $request->input('order.0.column', 0);
            $orderByColumnName = $columns[$orderByColumnIndex] ?? $columns[0];
            $orderDirection = $request->input('order.0.dir', 'asc');

            $query = Adjustment::query()->from('adjustments as adj')
                ->leftJoin('warehouses as w', 'adj.warehouse_id', '=', 'w.id')
                ->leftJoin('adjustment_items as ai', 'adj.id', '=', 'ai.adjustment_id')
                ->leftJoin('items as i', 'ai.item_id', '=', 'i.id');

            $totalRecordsQuery = Adjustment::query();
            if (Auth::user()->warehouse_id) {
                $totalRecordsQuery->where('warehouse_id', Auth::user()->warehouse_id);
                $query->where('adj.warehouse_id', Auth::user()->warehouse_id);
            } else {
                if ($warehouseFilter && $warehouseFilter !== 'semua') {
                    $query->where('adj.warehouse_id', $warehouseFilter);
                }
            }
            $totalRecords = $totalRecordsQuery->count();


            // Apply filters
            if (!empty($searchValue)) {
                $query->where(function ($q) use ($searchValue) {
                    $q->where('adj.code', 'LIKE', "%{$searchValue}%")
                        ->orWhere('adj.adjustment_date', 'LIKE', "%{$searchValue}%")
                        ->orWhere('w.name', 'LIKE', "%{$searchValue}%")
                        ->orWhere('i.sku', 'LIKE', "%{$searchValue}%")
                        ->orWhere('adj.status', 'LIKE', "%{$searchValue}%");
                });
            }

            if ($statusFilter && $statusFilter !== 'semua') {
                $query->where('adj.status', $statusFilter);
            }

            if ($dateFilter && $dateFilter !== 'semua') {
                if (strpos($dateFilter, ' to ') !== false) {
                    [$startDate, $endDate] = explode(' to ', $dateFilter);
                    $query->whereBetween('adj.adjustment_date', [$startDate, $endDate]);
                } else {
                    $query->whereDate('adj.adjustment_date', $dateFilter);
                }
            }

            // Total filtered records
            $totalFiltered = $query->distinct('adj.id')->count();


            // Data query
            $data = $query->select(
                'adj.id',
                'adj.code',
                'adj.adjustment_date',
                'adj.status',
                'w.name as warehouse_name',
                DB::raw('GROUP_CONCAT(i.sku, " (Qty:", FORMAT(ai.quantity,0), ")" SEPARATOR ", ") as items_name')
            )
            ->groupBy('adj.id', 'adj.code', 'adj.adjustment_date', 'adj.status', 'w.name')
            ->orderBy($orderByColumnName, $orderDirection)
                ->offset($start)
                ->limit($length)
                ->get();

            return response()->json([
                'draw' => intval($draw),
                'recordsTotal' => intval($totalRecords),
                'recordsFiltered' => intval($totalFiltered),
                'data' => $data,
            ]);
        }

        $warehouses = Warehouse::all();
        return view('admin.manajemenstok.adjustment.index', compact('warehouses'));
    }

    public function create()
    {
        $warehouses = Warehouse::all();
        $items = Item::with('uom')->get();
        $newCode = $this->generateNewCode();
        return view('admin.manajemenstok.adjustment.create', compact('warehouses', 'items', 'newCode'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required|unique:adjustments,code',
            'adjustment_date' => 'required|date',
            'warehouse_id' => 'required|exists:warehouses,id',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|distinct|exists:items,id',
            'items.*.quantity' => 'required|numeric',
            'items.*.koli' => 'nullable|numeric',
        ]);

        // Custom validation: negative quantities cannot exceed available stock in selected warehouse
        $errors = [];
        $warehouseId = (int) $request->warehouse_id;
        foreach ($request->items as $idx => $itemData) {
            $qty = (float) ($itemData['quantity'] ?? 0);
            if ($qty < 0) {
                $inv = Inventory::where('warehouse_id', $warehouseId)
                    ->where('item_id', $itemData['item_id'])
                    ->first();
                $available = (float) ($inv->quantity ?? 0);
                if (abs($qty) > $available) {
                    $item = Item::find($itemData['item_id']);
                    $name = $item->nama_barang ?? ('ID: ' . $itemData['item_id']);
                    $errors["items.$idx.quantity"] = ["Qty minus melebihi stok ($available) untuk $name."];
                }
            }
        }
        if (!empty($errors)) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $errors,
            ], 422);
        }

        DB::beginTransaction();
        try {
            $adjustment = Adjustment::create([
                'code' => $request->code,
                'adjustment_date' => $request->adjustment_date,
                'warehouse_id' => $request->warehouse_id,
                'user_id' => auth()->id(),
                'notes' => $request->notes,
                'status' => 'pending'
            ]);

            foreach ($request->items as $itemData) {
                AdjustmentItem::create([
                    'adjustment_id' => $adjustment->id,
                    'item_id' => $itemData['item_id'],
                    'quantity' => $itemData['quantity'],
                    'koli' => $itemData['koli'] ?? null,
                ]);
            }

            UserActivity::create([
                'user_id' => auth()->id(),
                'menu' => 'Penyesuaian Stok',
                'description' => 'Membuat penyesuaian stok baru dengan kode ' . $adjustment->code,
                'activity' => 'created',
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            DB::commit();
            return redirect()
                ->route('admin.manajemenstok.adjustment.index')
                ->with('success', 'Penyesuaian stok berhasil dibuat.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()
                ->back()
                ->with('error', 'Gagal membuat penyesuaian stok: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function show(Adjustment $adjustment)
    {
        $adjustment->load('warehouse', 'adjustmentItems.item', 'user');
        return view('admin.manajemenstok.adjustment.show', compact('adjustment'));
    }

    public function edit(Adjustment $adjustment)
    {
        if ($adjustment->status !== 'pending') {
            return redirect()
                ->route('admin.manajemenstok.adjustment.index')
                ->with('error', 'Hanya penyesuaian dengan status pending yang dapat diubah.');
        }

        $adjustment->load('adjustmentItems.item');
        $warehouses = Warehouse::all();
        $items = Item::with('uom')->get();
        return view('admin.manajemenstok.adjustment.edit', compact('adjustment', 'warehouses', 'items'));
    }

    public function update(Request $request, Adjustment $adjustment)
    {
        if ($adjustment->status !== 'pending') {
            return redirect()
                ->route('admin.manajemenstok.adjustment.index')
                ->with('error', 'Hanya penyesuaian dengan status pending yang dapat diubah.');
        }

        $request->validate([
            'code' => 'required|unique:adjustments,code,' . $adjustment->id,
            'adjustment_date' => 'required|date',
            'warehouse_id' => 'required|exists:warehouses,id',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|distinct|exists:items,id',
            'items.*.quantity' => 'required|numeric',
            'items.*.koli' => 'nullable|numeric',
        ]);

        // Custom validation: negative quantities cannot exceed available stock in selected warehouse
        $errors = [];
        $warehouseId = (int) $request->warehouse_id;
        foreach ($request->items as $idx => $itemData) {
            $qty = (float) ($itemData['quantity'] ?? 0);
            if ($qty < 0) {
                $inv = Inventory::where('warehouse_id', $warehouseId)
                    ->where('item_id', $itemData['item_id'])
                    ->first();
                $available = (float) ($inv->quantity ?? 0);
                if (abs($qty) > $available) {
                    $item = Item::find($itemData['item_id']);
                    $name = $item->nama_barang ?? ('ID: ' . $itemData['item_id']);
                    $errors["items.$idx.quantity"] = ["Qty minus melebihi stok ($available) untuk $name."];
                }
            }
        }
        if (!empty($errors)) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $errors,
            ], 422);
        }

        DB::beginTransaction();
        try {
            $adjustment->update([
                'code' => $request->code,
                'adjustment_date' => $request->adjustment_date,
                'warehouse_id' => $request->warehouse_id,
                'notes' => $request->notes
            ]);

            // Delete existing items
            $adjustment->adjustmentItems()->delete();

            // Create new items
            foreach ($request->items as $itemData) {
                AdjustmentItem::create([
                    'adjustment_id' => $adjustment->id,
                    'item_id' => $itemData['item_id'],
                    'quantity' => $itemData['quantity'],
                    'koli' => $itemData['koli'] ?? null,
                ]);
            }

            UserActivity::create([
                'user_id' => auth()->id(),
                'description' => 'Mengubah penyesuaian stok dengan kode ' . $adjustment->code,
                'activity' => 'updated',
                'menu' => 'Penyesuaian Stok',
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            DB::commit();
            return redirect()
                ->route('admin.manajemenstok.adjustment.index')
                ->with('success', 'Penyesuaian stok berhasil diperbarui.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()
                ->back()
                ->with('error', 'Gagal memperbarui penyesuaian stok: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function destroy(Adjustment $adjustment)
    {
        if ($adjustment->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya penyesuaian dengan status pending yang dapat dihapus.'
            ], 422);
        }

        DB::beginTransaction();
        try {
            UserActivity::create([
                'user_id' => auth()->id(),
                'description' => 'Menghapus penyesuaian stok dengan kode ' . $adjustment->code,
                'activity' => 'deleted',
                'menu' => 'Penyesuaian Stok',
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            $adjustment->adjustmentItems()->delete();
            $adjustment->delete();

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Penyesuaian stok berhasil dihapus.'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus penyesuaian stok: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateStatus(Request $request, Adjustment $adjustment)
    {
        if ($adjustment->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Status penyesuaian sudah diubah sebelumnya.'
            ], 422);
        }

        $request->validate([
            'status' => 'required|in:completed',
        ]);

        DB::beginTransaction();
        try {
            $adjustment->load('adjustmentItems.item');

            foreach ($adjustment->adjustmentItems as $item) {
                $inventory = Inventory::firstOrNew([
                    'warehouse_id' => $adjustment->warehouse_id,
                    'item_id' => $item->item_id
                ]);

                if (!$inventory->exists && $item->quantity < 0) {
                    throw new \Exception("Tidak dapat mengurangi stok. Stok tidak ditemukan untuk item: " . ($item->item->nama_barang ?? 'N/A'));
                }

                StockMovement::create([
                    'warehouse_id' => $adjustment->warehouse_id,
                    'item_id' => $item->item_id,
                    'date' => $adjustment->adjustment_date,
                    'quantity' => $item->quantity,
                    'koli' => $item->koli ?? 0,
                    'type' => $item->quantity >= 0 ? 'stock_in' : 'stock_out',
                    'stock_before' => $inventory->quantity ?? 0,
                    'stock_after' => ($inventory->quantity ?? 0) + $item->quantity,
                    'description' => 'Penyesuaian stok: ' . $adjustment->code,
                    'user_id' => auth()->id(),
                    'reference_id' => $item->id,
                    'reference_type' => 'adjustment_items'
                ]);

                $inventory->quantity = ($inventory->quantity ?? 0) + $item->quantity;
                $inventory->koli = ($inventory->koli ?? 0) + ($item->koli ?? 0);
                $inventory->save();
            }

            $adjustment->status = 'completed';
            $adjustment->completed_at = now();
            $adjustment->save();

            UserActivity::create([
                'user_id' => auth()->id(),
                'description' => 'Menyelesaikan penyesuaian stok dengan kode ' . $adjustment->code,
                'activity' => 'approved',
                'menu' => 'Penyesuaian Stok',
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Status penyesuaian berhasil diperbarui.'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui status: ' . $e->getMessage()
            ], 500);
        }
    }
}
