<?php


namespace App\Http\Controllers\Admin\StokMasuk;

use App\Http\Controllers\Controller;

use App\Models\StockInOrder;
use App\Models\StockInOrderItem;
use App\Models\Shipment;
use App\Models\UserActivity;
use App\Models\Warehouse;
use App\Models\Item;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PengadaanController extends Controller
{
    private function generateNewCode()
    {
        $prefix = 'IN';
        $date = now()->format('Ymd');
        $latestOrder = StockInOrder::where('code', 'LIKE', "$prefix-$date-%")->latest('id')->first();

        if ($latestOrder) {
            $sequence = (int) substr($latestOrder->code, -4) + 1;
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
            $warehouseFilter = $request->input('warehouse');

            // Define columns for sorting (mirror client columns)
            if (is_null(auth()->user()->warehouse_id)) {
                // Columns: code, date, type, warehouse, items, status, actions
                $columns = [
                    0 => 'sio.code',
                    1 => 'sio.date',
                    2 => 'sio.type',
                    3 => 'warehouse_name',
                    4 => 'items_name',
                    5 => 'sio.status',
                    6 => 'sio.id',
                ];
            } else {
                // Columns: code, date, type, items, status, actions
                $columns = [
                    0 => 'sio.code',
                    1 => 'sio.date',
                    2 => 'sio.type',
                    3 => 'items_name',
                    4 => 'sio.status',
                    5 => 'sio.id',
                ];
            }
            $orderByColumnIndex = $request->input('order.0.column', 0);
            $orderByColumnName = $columns[$orderByColumnIndex] ?? $columns[0];
            $orderDirection = $request->input('order.0.dir', 'asc');

            // Base query
            $query = StockInOrder::query()
                ->from('stock_in_orders as sio')
                ->leftJoin('warehouses as w', 'sio.warehouse_id', '=', 'w.id')
                ->leftJoin('stock_in_order_items as si', 'sio.id', '=', 'si.stock_in_order_id')
                ->leftJoin('items as i', 'si.item_id', '=', 'i.id')
                // Group by all non-aggregated columns to satisfy ONLY_FULL_GROUP_BY
                ->groupBy('sio.id', 'sio.code', 'sio.date', 'sio.type', 'sio.status', 'w.name');

            if (auth()->user()->warehouse_id) {
                $query->where('sio.warehouse_id', auth()->user()->warehouse_id);
            } else {
                if ($warehouseFilter && $warehouseFilter !== 'semua') {
                    $query->where('sio.warehouse_id', $warehouseFilter);
                }
            }

            // Total records
            $totalRecords = $query->count();

            // Apply filters
            if (!empty($searchValue)) {
                $query->where(function ($q) use ($searchValue) {
                    $q->where('sio.code', 'LIKE', "%{$searchValue}%")
                        ->orWhere('sio.date', 'LIKE', "%{$searchValue}%")
                        ->orWhere('w.name', 'LIKE', "%{$searchValue}%")
                        ->orWhere('sio.status', 'LIKE', "%{$searchValue}%");
                });
            }

            if ($statusFilter && $statusFilter !== 'semua') {
                $query->where('sio.status', $statusFilter);
            }

            if ($dateFilter && $dateFilter !== 'semua') {
                if (strpos($dateFilter, ' to ') !== false) {
                    [$startDate, $endDate] = explode(' to ', $dateFilter);
                    $query->whereBetween('sio.date', [$startDate, $endDate]);
                } else {
                    $query->whereDate('sio.date', $dateFilter);
                }
            }

            // Total filtered records
            $totalFiltered = $query->count();

            // Data query
            $data = $query->select(
                'sio.id',
                'sio.code',
                'sio.date',
                'sio.type',
                'sio.status',
                'w.name as warehouse_name',
                // Use CONCAT inside GROUP_CONCAT and single-quoted SQL strings to avoid ANSI_QUOTES issues
                DB::raw("GROUP_CONCAT(CONCAT(i.sku, ' (Qty:', FORMAT(si.quantity,0), ')') SEPARATOR ', ') as items_name"),
                DB::raw('SUM(si.remaining_quantity) as remaining_quantity_sum'),
                DB::raw('SUM(si.remaining_koli) as remaining_koli_sum')
            )->orderBy($orderByColumnName, $orderDirection)
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

        $warehouses = [];
        if (is_null(auth()->user()->warehouse_id)) {
            $warehouses = Warehouse::all();
        }

        return view('admin.stok_masuk.pengadaan.index', compact('warehouses'));
    }

    public function getStatusCounts(Request $request)
    {
        $dateFilter = $request->input('date');
        $warehouseFilter = $request->input('warehouse');

        $baseQuery = StockInOrder::query();

        if (auth()->user()->warehouse_id) {
            $baseQuery->where('warehouse_id', auth()->user()->warehouse_id);
        } else {
            if ($warehouseFilter && $warehouseFilter !== 'semua') {
                $baseQuery->where('warehouse_id', $warehouseFilter);
            }
        }

        if ($dateFilter && $dateFilter !== 'semua') {
            if (strpos($dateFilter, ' to ') !== false) {
                [$startDate, $endDate] = explode(' to ', $dateFilter);
                $baseQuery->whereBetween('date', [$startDate, $endDate]);
            } else {
                $baseQuery->whereDate('date', $dateFilter);
            }
        }

        $statusCounts = [
            'requested' => (clone $baseQuery)->where('status', 'requested')->count(),
            'on_progress' => (clone $baseQuery)->where('status', 'on_progress')->count(),
            'completed' => (clone $baseQuery)->where('status', 'completed')->count(),
            'rejected' => (clone $baseQuery)->where('status', 'rejected')->count(),
        ];

        return response()->json($statusCounts);
    }

    public function show(StockInOrder $stockInOrder)
    {
        // Load base relations
        $stockInOrder->load([
            'warehouse',
            'fromWarehouse',
            'requestedBy.jabatan',
            'items.item.uom',
            // For type import, show distributions incl. destination warehouse
            'items.distributions.toWarehouse',
        ]);

        // Load shipments referencing this Stock In Order (with details)
        $shipments = Shipment::where('reference_type', Shipment::REFERENCE_TYPE_STOCK_IN_ORDER)
            ->where('reference_id', $stockInOrder->id)
            ->with(['itemDetails.item'])
            ->orderBy('shipping_date')
            ->get();

        // Collect related goods receipts from these shipments (if any)
        $receipts = collect();
        if ($shipments->isNotEmpty()) {
            $shipmentIds = $shipments->pluck('id');
            $receipts = \App\Models\GoodsReceipt::whereIn('shipment_id', $shipmentIds)
                ->with(['warehouse', 'details'])
                ->orderBy('receipt_date')
                ->get();
        }

        return view('admin.stok_masuk.pengadaan.show', compact('stockInOrder', 'shipments', 'receipts'));
    }

    public function create()
    {
        $warehouses = Warehouse::all();
        $items = Item::all();
        $newCode = $this->generateNewCode();
        return view('admin.stok_masuk.pengadaan.create', compact('warehouses', 'items', 'newCode'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'warehouse_id' => 'required|exists:warehouses,id',
            'type' => 'required|in:import,produksi,lainnya',
            'from_warehouse_id' => 'nullable|required_unless:type,import|exists:warehouses,id',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id|distinct',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.koli' => 'nullable|numeric|min:0',
        ], [
            'from_warehouse_id.required_unless' => 'Dari gudang wajib diisi untuk tipe selain import.',
            'items.*.item_id.distinct' => 'Tidak boleh ada item yang sama pada daftar.',
        ]);

        DB::beginTransaction();
        try {
            $code = $this->generateNewCode();
            $stockInOrder = StockInOrder::create([
                'code' => $code,
                'date' => $request->date,
                'warehouse_id' => $request->warehouse_id,
                'type' => $request->type,
                'from_warehouse_id' => $request->type === 'import' ? null : $request->from_warehouse_id,
                'description' => $request->description,
                'requested_at' => $request->date,
                'status' => 'requested', // Default status
                'requested_by' => auth()->id(),
            ]);

            foreach ($request->items as $itemData) {
                $quantity = $itemData['quantity'];
                $koli = $itemData['koli'] ?? 0;

                $stockInOrder->items()->create([
                    'item_id' => $itemData['item_id'],
                    'quantity' => $quantity,
                    'koli' => $koli,
                    'remaining_quantity' => $quantity,
                    'remaining_koli' => $koli,
                    'status' => 'pending',
                ]);
            }


            // Log user activity (do not fail main tx if logging fails)
            try {
                $itemsCount = is_array($request->items ?? null) ? count($request->items) : 0;
                UserActivity::create([
                    'user_id' => Auth::id(),
                    'activity' => 'created',
                    'menu' => 'Pengadaan',
                    'description' => 'Menambahkan Pengadaan: ' . $code . ' (Tipe: ' . ($request->type ?? '-') . ', Gudang ID: ' . ($request->warehouse_id ?? '-') . ', Items: ' . $itemsCount . ')',
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->header('User-Agent'),
                ]);
            } catch (\Throwable $e) { /* ignore activity errors */ }

            DB::commit();

            return redirect()->route('admin.stok-masuk.pengadaan.index')->with('success', 'Pengadaan berhasil dibuat.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal membuat pengadaan: ' . $e->getMessage())->withInput();
        }
    }

    public function edit(StockInOrder $stockInOrder)
    {
        $stockInOrder->load('items.item');
        $warehouses = Warehouse::all();
        $items = Item::all();
        return view('admin.stok_masuk.pengadaan.edit', compact('stockInOrder', 'warehouses', 'items'));
    }

    public function update(Request $request, StockInOrder $stockInOrder)
    {
        $request->validate([
            'code' => 'required|unique:stock_in_orders,code,' . $stockInOrder->id,
            'date' => 'required|date',
            'warehouse_id' => 'required|exists:warehouses,id',
            'type' => 'required|in:import,produksi,lainnya',
            'from_warehouse_id' => 'nullable|required_unless:type,import|exists:warehouses,id',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id|distinct',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.koli' => 'nullable|numeric|min:0',
        ], [
            'from_warehouse_id.required_unless' => 'Dari gudang wajib diisi untuk tipe selain import.',
            'items.*.item_id.distinct' => 'Tidak boleh ada item yang sama pada daftar.',
        ]);
        if ($stockInOrder->status !== 'requested') {
            return redirect()->back()->with('error', 'Pengadaan hanya dapat diubah saat status masih requested.');
        }

        DB::beginTransaction();
        try {
            $stockInOrder->update([
                'code' => $request->code,
                'date' => $request->date,
                'warehouse_id' => $request->warehouse_id,
                'type' => $request->type,
                'from_warehouse_id' => $request->type === 'import' ? null : $request->from_warehouse_id,
                'description' => $request->description,
                'requested_at' => $request->date,
            ]);

            // Hapus item lama dan buat yang baru
            $stockInOrder->items()->delete();
            foreach ($request->items as $itemData) {
                $quantity = $itemData['quantity'];
                $koli = $itemData['koli'] ?? 0;

                $stockInOrder->items()->create([
                    'item_id' => $itemData['item_id'],
                    'quantity' => $quantity,
                    'koli' => $koli,
                    'remaining_quantity' => $quantity,
                    'remaining_koli' => $koli,
                    'status' => 'pending',
                ]);
            }

            // Log user activity (do not fail main tx if logging fails)
            try {
                $itemsCount = is_array($request->items ?? null) ? count($request->items) : 0;
                UserActivity::create([
                    'user_id' => Auth::id(),
                    'activity' => 'updated',
                    'menu' => 'Pengadaan',
                    'description' => 'Mengubah Pengadaan: ' . ($request->code ?? $stockInOrder->code) . ' (Tipe: ' . ($request->type ?? $stockInOrder->type) . ', Gudang ID: ' . ($request->warehouse_id ?? $stockInOrder->warehouse_id) . ', Items: ' . $itemsCount . ')',
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->header('User-Agent'),
                ]);
            } catch (\Throwable $e) { /* ignore activity errors */ }

            DB::commit();

            return redirect()->route('admin.stok-masuk.pengadaan.index')->with('success', 'Pengadaan berhasil diperbarui.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal memperbarui pengadaan: ' . $e->getMessage())->withInput();
        }
    }

    public function destroy(Request $request, StockInOrder $stockInOrder)
    {
        DB::beginTransaction();
        try {
            $stockInOrder->items()->delete();
            $stockInOrder->delete();

            UserActivity::create([
                'user_id' => Auth::id(),
                'activity' => 'deleted',
                'menu' => 'Pengadaan',
                'description' => 'Menghapus Pengadaan : ' . $stockInOrder->code,
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);
            DB::commit();
            return response()->json(['success' => true, 'message' => 'Dokumen berhasil dihapus.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal menghapus dokumen.'], 500);
        }
    }

    // Fitur Kirim Barang dihapus pada modul Pengadaan: endpoint getItemsToShip, createShipment, suratJalan dihapus

    public function details(StockInOrder $stockInOrder)
    {
        $stockInOrder->load(['items.item', 'warehouse']);

        $items = $stockInOrder->items->map(function($it){
            return [
                'id' => $it->id,
                'item_id' => $it->item_id,
                'sku' => $it->item->sku ?? '-',
                'name' => ($it->item->nama_barang ?? $it->item->name ?? '-'),
                'remaining_quantity' => (float) ($it->remaining_quantity ?? 0),
                'remaining_koli' => (float) ($it->remaining_koli ?? 0),
                'item_koli_ratio' => (float) optional($it->item)->koli ?? 1,
                'status' => $it->status,
            ];
        });

        return response()->json([
            'id' => $stockInOrder->id,
            'code' => $stockInOrder->code,
            'date' => $stockInOrder->date,
            'type' => $stockInOrder->type,
            'status' => $stockInOrder->status,
            'warehouse' => $stockInOrder->warehouse ? $stockInOrder->warehouse->name : null,
            'warehouse_id' => $stockInOrder->warehouse_id,
            'items' => $items,
            'warehouses' => \App\Models\Warehouse::orderBy('name')->get(['id','name'])->map(function($w){ return ['id'=>$w->id,'name'=>$w->name]; }),
        ]);
    }

    public function distributions(StockInOrder $stockInOrder)
    {
        $stockInOrder->load([
            'warehouse',
            'fromWarehouse',
            'requestedBy',
            'items.item.uom',
            'items.distributions.toWarehouse',
        ]);

        // Warehouses list for editing distribution target warehouse
        $warehouses = Warehouse::orderBy('name')->get();

        return view('admin.stok_masuk.pengadaan.distributions', compact('stockInOrder', 'warehouses'));
    }

    public function saveDistributions(Request $request, StockInOrder $stockInOrder)
    {
        // dd($request);
        $request->validate([
            'date' => 'nullable|date',
            'distributions' => 'required|array',
            'distributions.*.stock_in_order_item_id' => 'required|exists:stock_in_order_items,id',
            // to_warehouse_id is only required when qty/koli > 0; validate existence conditionally below
            'distributions.*.to_warehouse_id' => 'nullable|exists:warehouses,id',
            'distributions.*.quantity' => 'nullable|numeric|min:0',
            'distributions.*.koli' => 'nullable|numeric|min:0',
            'distributions.*.note' => 'nullable|string',
            'distributions.*.date' => 'nullable|date',
        ]);

        // Ensure there is at least one non-zero line to distribute
        $hasAnyDistribution = false;
        foreach ($request->input('distributions', []) as $dist) {
            $qty = (float) ($dist['quantity'] ?? 0);
            $koli = (float) ($dist['koli'] ?? 0);
            if ($qty > 0 || $koli > 0) { $hasAnyDistribution = true; break; }
        }
        if (!$hasAnyDistribution) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada distribusi yang disalurkan. Isi minimal satu Qty/Koli distribusi.'
            ], 422);
        }

        DB::beginTransaction();
        try {
            $createdCount = 0;
            $createdIds = [];
            foreach ($request->input('distributions', []) as $dist) {
                $qty = (float) ($dist['quantity'] ?? 0);
                $koli = (float) ($dist['koli'] ?? 0);
                if ($qty <= 0 && $koli <= 0) { continue; }

                if (empty($dist['to_warehouse_id'])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Tujuan gudang wajib diisi untuk setiap baris distribusi yang memiliki Qty/Koli.'
                    ], 422);
                }

                $item = StockInOrderItem::find($dist['stock_in_order_item_id']);
                if (!$item || (int)$item->stock_in_order_id !== (int)$stockInOrder->id) {
                    return response()->json(['success' => false, 'message' => 'Baris item tidak valid untuk dokumen ini.'], 422);
                }
                $remaining = (float) ($item->remaining_quantity ?? 0);
                $remainingKoli = (float) ($item->remaining_koli ?? 0);
                if ($qty > $remaining) {
                    return response()->json(['success' => false, 'message' => 'Qty distribusi melebihi sisa untuk item ' . ($item->item->nama_barang ?? $item->item_id)], 422);
                }
                if ($koli > $remainingKoli) {
                    return response()->json(['success' => false, 'message' => 'Koli distribusi melebihi sisa untuk item ' . ($item->item->nama_barang ?? $item->item_id)], 422);
                }
                $distDate = $dist['date'] ?? $request->input('date') ?? now()->toDateString();
                $created = \App\Models\StockInOrderItemDistribution::create([
                    'date' => $distDate,
                    'stock_in_order_item_id' => $item->id,
                    'to_warehouse_id' => (int) $dist['to_warehouse_id'],
                    'quantity' => $qty,
                    'koli' => $koli,
                    'note' => $dist['note'] ?? null,
                    'status' => 'draft',
                ]);
                $createdCount++;
                $createdIds[] = $created->id;
            }
            DB::commit();
            // Log user activity (summary)
            try {
                UserActivity::create([
                    'user_id' => Auth::id(),
                    'activity' => 'created',
                    'menu' => 'Pengadaan',
                    'description' => 'Menyimpan distribusi untuk dokumen ' . ($stockInOrder->code ?? $stockInOrder->id) . ' (' . $createdCount . ' baris, ID: ' . implode(',', $createdIds) . ')',
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->header('User-Agent'),
                ]);
            } catch (\Throwable $e) {
                // ignore activity failures
            }
            return response()->json(['success' => true, 'message' => 'Distribusi disimpan dan menunggu persetujuan.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan distribusi: ' . $e->getMessage()], 500);
        }
    }

    public function approveDistribution(Request $request, \App\Models\StockInOrderItemDistribution $distribution)
    {
        $item = StockInOrderItem::find($distribution->stock_in_order_item_id);
        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Distribusi tidak valid.'], 404);
        }
        $stockInOrder = StockInOrder::find($item->stock_in_order_id);
        if (!$stockInOrder) {
            return response()->json(['success' => false, 'message' => 'Dokumen pengadaan tidak ditemukan.'], 404);
        }
        if ($distribution->status === 'completed') {
            return response()->json(['success' => false, 'message' => 'Distribusi sudah disetujui.'], 422);
        }

        DB::beginTransaction();
        try {
            $qty = (float) ($distribution->quantity ?? 0);
            $koli = (float) ($distribution->koli ?? 0);
            $remaining = (float) ($item->remaining_quantity ?? 0);
            $remainingKoli = (float) ($item->remaining_koli ?? 0);

            if ($qty > $remaining) {
                return response()->json(['success' => false, 'message' => 'Qty distribusi melebihi sisa pada saat persetujuan.'], 422);
            }
            if ($koli > $remainingKoli) {
                return response()->json(['success' => false, 'message' => 'Koli distribusi melebihi sisa pada saat persetujuan.'], 422);
            }

            // Apply deduction now
            $item->remaining_quantity = max(0, $remaining - $qty);
            $item->remaining_koli = max(0, $remainingKoli - $koli);
            if (($item->remaining_quantity <= 0) && ($item->remaining_koli <= 0)) {
                $item->status = 'completed';
            } else {
                $initialQty = (float) ($item->quantity ?? 0);
                $initialKoli = (float) ($item->koli ?? 0);
                $reduced = ($item->remaining_quantity < $initialQty) || ($item->remaining_koli < $initialKoli);
                if ($reduced && $item->status !== 'completed') {
                    $item->status = 'on_progress';
                }
            }
            $item->save();

            // Mark distribution completed
            $distribution->status = 'completed';
            $distribution->save();

            // Update SIO shipping_at and status
            $distDate = $distribution->date ?? now()->toDateString();
            if (empty($stockInOrder->shipping_at)) {
                $stockInOrder->shipping_at = $distDate;
            }
            $totalRemainingQty = (float) $stockInOrder->items()->sum('remaining_quantity');
            if ($totalRemainingQty <= 0) {
                $stockInOrder->status = 'completed';
                if (empty($stockInOrder->completed_at)) {
                    $stockInOrder->completed_at = $distDate;
                }
            } else {
                $stockInOrder->status = 'on_shipping';
            }
            $stockInOrder->save();

            DB::commit();
            // Log user activity
            try {
                $whName = optional($distribution->toWarehouse)->name ?? ('#' . $distribution->to_warehouse_id);
                UserActivity::create([
                    'user_id' => Auth::id(),
                    'activity' => 'approved',
                    'menu' => 'Pengadaan',
                    'description' => 'Menyetujui distribusi ID ' . $distribution->id . ' dokumen ' . ($stockInOrder->code ?? $stockInOrder->id) . ' (Qty: ' . (float)$distribution->quantity . ', Koli: ' . (float)$distribution->koli . ', Gudang: ' . $whName . ')',
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->header('User-Agent'),
                ]);
            } catch (\Throwable $e) {
                // ignore activity failures
            }
            return response()->json(['success' => true, 'message' => 'Distribusi disetujui.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal menyetujui distribusi: ' . $e->getMessage()], 500);
        }
    }
    public function showDistribution(\App\Models\StockInOrderItemDistribution $distribution)
    {
        $item = StockInOrderItem::find($distribution->stock_in_order_item_id);
        return response()->json([
            'id' => $distribution->id,
            'date' => $distribution->date,
            'stock_in_order_item_id' => $distribution->stock_in_order_item_id,
            'to_warehouse_id' => $distribution->to_warehouse_id,
            'quantity' => (float) $distribution->quantity,
            'koli' => (float) $distribution->koli,
            'note' => $distribution->note,
            'status' => $distribution->status,
            'item' => $item ? [
                'id' => $item->id,
                'name' => optional($item->item)->nama_barang ?? optional($item->item)->name ?? '-',
                'sku' => optional($item->item)->sku ?? '-',
                'remaining_quantity' => (float) ($item->remaining_quantity ?? 0),
                'remaining_koli' => (float) ($item->remaining_koli ?? 0),
            ] : null,
        ]);
    }

    public function updateDistribution(Request $request, \App\Models\StockInOrderItemDistribution $distribution)
    {
        if ($distribution->status === 'completed') {
            return response()->json(['success' => false, 'message' => 'Distribusi sudah completed dan tidak dapat diedit.'], 422);
        }

        $request->validate([
            'date' => 'nullable|date',
            'to_warehouse_id' => 'nullable|exists:warehouses,id',
            'quantity' => 'nullable|numeric|min:0',
            'koli' => 'nullable|numeric|min:0',
            'note' => 'nullable|string',
        ]);

        $item = StockInOrderItem::find($distribution->stock_in_order_item_id);
        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Item distribusi tidak valid.'], 404);
        }

        $qty = (float) ($request->input('quantity', $distribution->quantity) ?? 0);
        $koli = (float) ($request->input('koli', $distribution->koli) ?? 0);
        if ($qty <= 0 && $koli <= 0) {
            return response()->json(['success' => false, 'message' => 'Isi minimal salah satu Qty atau Koli.'], 422);
        }

        $remainingQty = (float) ($item->remaining_quantity ?? 0);
        $remainingKoli = (float) ($item->remaining_koli ?? 0);
        if ($qty > $remainingQty) {
            return response()->json(['success' => false, 'message' => 'Qty distribusi melebihi sisa untuk item.'], 422);
        }
        if ($koli > $remainingKoli) {
            return response()->json(['success' => false, 'message' => 'Koli distribusi melebihi sisa untuk item.'], 422);
        }

        $before = [
            'date' => $distribution->date,
            'to_warehouse_id' => $distribution->to_warehouse_id,
            'quantity' => (float) $distribution->quantity,
            'koli' => (float) $distribution->koli,
            'note' => $distribution->note,
        ];

        $distribution->date = $request->input('date', $distribution->date) ?: $distribution->date;
        if ($request->filled('to_warehouse_id')) {
            $distribution->to_warehouse_id = (int) $request->input('to_warehouse_id');
        }
        $distribution->quantity = $qty;
        $distribution->koli = $koli;
        $distribution->note = $request->input('note');
        $distribution->save();

        try {
            $stockInOrder = StockInOrder::find($item->stock_in_order_id);
            $after = [
                'date' => $distribution->date,
                'to_warehouse_id' => $distribution->to_warehouse_id,
                'quantity' => (float) $distribution->quantity,
                'koli' => (float) $distribution->koli,
                'note' => $distribution->note,
            ];
            $desc = 'Mengubah distribusi ID ' . $distribution->id . ' dokumen ' . ($stockInOrder->code ?? $stockInOrder->id)
                . ' (qty ' . $before['quantity'] . '→' . $after['quantity']
                . ', koli ' . $before['koli'] . '→' . $after['koli']
                . ', gudang ' . ($before['to_warehouse_id'] ?? '-') . '→' . ($after['to_warehouse_id'] ?? '-') . ')';
            UserActivity::create([
                'user_id' => Auth::id(),
                'activity' => 'updated',
                'menu' => 'Pengadaan',
                'description' => $desc,
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);
        } catch (\Throwable $e) {
            // ignore activity failures
        }

        return response()->json(['success' => true, 'message' => 'Distribusi berhasil diperbarui.']);
    }
}
