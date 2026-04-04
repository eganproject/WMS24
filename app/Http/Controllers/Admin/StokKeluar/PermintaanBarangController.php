<?php

namespace App\Http\Controllers\Admin\StokKeluar;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\Shipment;
use App\Models\ShipmentItemDetail;
use App\Models\StockMovement;
use App\Models\TransferRequestItem;
use App\Models\UserActivity;
use Auth;
use Illuminate\Http\Request;
use App\Models\TransferRequest;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PermintaanBarangController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $searchValue = $request->input('search.value', '');
            $start = $request->input('start', 0);
            $length = $request->input('length', 10);
            $draw = $request->input('draw', 0);
            $toWarehouseFilter = $request->input('to_warehouse_id');
            $fromWarehouseFilter = $request->input('from_warehouse_id');
            $statusFilter = $request->input('status');
            $dateFilter = $request->input('date');

            $columns = [
                0 => 'tr.code',
                1 => 'tr.date',
                2 => 'from_warehouse_name',
                3 => 'to_warehouse_name',
                4 => 'items_list',
                5 => 'tr.status',
                6 => 'requester_name',
                7 => 'tr.id',
            ];
            $orderByColumnIndex = $request->input('order.0.column', 0);
            $orderByColumnName = $columns[$orderByColumnIndex] ?? $columns[0];
            $orderDirection = $request->input('order.0.dir', 'asc');

            $totalRecordsQuery = TransferRequest::query();
            if (auth()->user()->warehouse_id) {
                $totalRecordsQuery->where('from_warehouse_id', auth()->user()->warehouse_id);
            }
            $totalRecords = $totalRecordsQuery->count();

            $query = TransferRequest::query()
                ->from('transfer_requests as tr')
                ->leftJoin('warehouses as fw', 'tr.from_warehouse_id', '=', 'fw.id')
                ->leftJoin('warehouses as tw', 'tr.to_warehouse_id', '=', 'tw.id')
                ->leftJoin('users as u', 'tr.requested_by', '=', 'u.id')
                ->leftJoin('transfer_request_items as tri', 'tr.id', '=', 'tri.transfer_request_id')
                ->leftJoin('items', 'tri.item_id', '=', 'items.id');

            if (auth()->user()->warehouse_id) {
                $query->where('tr.from_warehouse_id', auth()->user()->warehouse_id);
            }

            if (!empty($searchValue)) {
                $query->where(function ($q) use ($searchValue) {
                    $q->where('tr.code', 'LIKE', "%{$searchValue}%")
                        ->orWhere('fw.name', 'LIKE', "%{$searchValue}%")
                        ->orWhere('tw.name', 'LIKE', "%{$searchValue}%")
                        ->orWhere('u.name', 'LIKE', "%{$searchValue}%")
                        ->orWhere('tr.status', 'LIKE', "%{$searchValue}%")
                        ->orWhere('items.sku', 'LIKE', "%{$searchValue}%");
                });
            }

            if ($fromWarehouseFilter && $fromWarehouseFilter !== 'semua') {
                $query->where('tr.from_warehouse_id', $fromWarehouseFilter);
            }

            if ($toWarehouseFilter && $toWarehouseFilter !== 'semua') {
                $query->where('tr.to_warehouse_id', $toWarehouseFilter);
            }

            if ($statusFilter && $statusFilter !== 'semua') {
                $query->where('tr.status', $statusFilter);
            }

            if ($dateFilter && $dateFilter !== 'semua') {
                $query->whereDate('tr.date', $dateFilter);
            }

            $totalFiltered = $query->distinct('tr.id')->count();

            $data = $query->select(
                'tr.id',
                'tr.code',
                'tr.date',
                'tr.status',
                'fw.name as from_warehouse_name',
                'tw.name as to_warehouse_name',
                'u.name as requester_name',
                DB::raw("GROUP_CONCAT(items.sku, ' (', FORMAT(tri.quantity,0), ')' SEPARATOR ', ') as items_list")
            )
                ->groupBy('tr.id', 'tr.code', 'tr.date', 'tr.status', 'fw.name', 'tw.name', 'u.name')
                ->orderBy($orderByColumnName, $orderDirection)
                ->offset($start)
                ->limit($length)
                ->get();

            foreach ($data as $row) {
                $row->action = '<a href="' . route('admin.stok-keluar.permintaan-barang.show', $row->id) . '" class="btn btn-info btn-sm">Detail</a>';
            }

            return response()->json([
                'draw' => intval($draw),
                'recordsTotal' => intval($totalRecords),
                'recordsFiltered' => intval($totalFiltered),
                'data' => $data,
            ]);
        }

        $warehouses = Warehouse::all();
        return view('admin.stok-keluar.permintaan-barang.index', compact('warehouses'));
    }

    public function getStatusCounts(Request $request)
    {
        $toWarehouseFilter = $request->input('to_warehouse_id');
        $fromWarehouseFilter = $request->input('from_warehouse_id');
        $dateFilter = $request->input('date');

        $baseQuery = TransferRequest::query();

        if (auth()->user()->warehouse_id) {
            $baseQuery->where('from_warehouse_id', auth()->user()->warehouse_id);
        }

        if ($fromWarehouseFilter && $fromWarehouseFilter !== 'semua') {
            $baseQuery->where('from_warehouse_id', $fromWarehouseFilter);
        }

        if ($toWarehouseFilter && $toWarehouseFilter !== 'semua') {
            $baseQuery->where('to_warehouse_id', $toWarehouseFilter);
        }

        if ($dateFilter && $dateFilter !== 'semua') {
            $baseQuery->whereDate('date', $dateFilter);
        }

        $statusCounts = [
            'requested' => (clone $baseQuery)->where('status', 'pending')->count(),
            'approved' => (clone $baseQuery)->where('status', 'approved')->count(),
            'on_progress' => (clone $baseQuery)->where('status', 'on_progress')->count(),
            'completed' => (clone $baseQuery)->where('status', 'completed')->count(),
            'rejected' => (clone $baseQuery)->where('status', 'rejected')->count(),
        ];

        return response()->json($statusCounts);
    }

    public function show(TransferRequest $transferRequest)
    {
        $transferRequest->load([
            'fromWarehouse',
            'toWarehouse',
            'requester',
            'items.item',
            'shipments.itemDetails.item',
        ]);
        return view('admin.stok-keluar.permintaan-barang.show', compact('transferRequest'));
    }

    public function updateStatus(Request $request, TransferRequest $transferRequest)
    {
        $request->validate([
            'status' => 'required|in:approved',
        ]);

        $newStatus = $request->status;

        if ($newStatus === 'approved' && $transferRequest->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Hanya permintaan dengan status pending yang bisa disetujui.'], 422);
        }

        try {
            DB::beginTransaction();

            $transferRequest->approved_by = Auth::id();
            $transferRequest->approved_at = now();
            $transferRequest->status = $newStatus;
            $transferRequest->save();

            UserActivity::create([
                'user_id' => Auth::id(),
                'activity' => 'approved',
                'menu' => 'Permintaan Barang',
                'description' => 'Menyetujui Permintaan Barang menjadi '.$newStatus.'dengan kode ' . $transferRequest->code,
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Status berhasil diperbarui.']);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['success' => false, 'message' => 'Gagal memperbarui status: ' . $e->getMessage()], 500);
        }
    }

    public function getItemsToShip(TransferRequest $transferRequest)
    {
        $items = $transferRequest->items()
            ->where('remaining_quantity', '>', 0)
            ->with('item:id,nama_barang,koli,sku')
            ->get();

        return response()->json($items);
    }

    public function createShipment(Request $request, TransferRequest $transferRequest)
    {
        $validated = $request->validate([
            'shipping_date' => 'required|date',
            'vehicle_type' => 'required|string|max:255',
            'license_plate' => 'required|string|max:255',
            'driver_name' => 'required|string|max:255',
            'driver_contact' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'items' => 'required|array',
            'items.*.quantity' => 'nullable|numeric|min:0',
            'items.*.koli' => 'nullable|numeric|min:0',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.description' => 'nullable|string|max:255',
        ]);

        if (!in_array($transferRequest->status, ['approved', 'on_progress'])) {
            return response()->json(['success' => false, 'message' => 'Hanya permintaan yang sudah disetujui atau sedang dalam proses yang bisa dikirim.'], 422);
        }

        $itemsToShip = array_filter($validated['items'], function ($item) {
            return ($item['quantity'] > 0 || $item['koli'] > 0);
        });

        if (empty($itemsToShip)) {
            return response()->json(['success' => false, 'message' => 'Tidak ada item yang dipilih untuk dikirim.'], 422);
        }

        try {
            DB::transaction(function () use ($validated, $transferRequest, $itemsToShip, $request) {
                // 1. Validasi Stok dan Kuantitas
                foreach ($itemsToShip as $id => $itemData) {
                    $transferRequestItem = TransferRequestItem::findOrFail($id);
                    $quantityToShip = $itemData['quantity'] ?? 0;
                    $koliToShip = $itemData['koli'] ?? 0;

                    if ($quantityToShip > $transferRequestItem->remaining_quantity || $koliToShip > $transferRequestItem->remaining_koli) {
                        throw ValidationException::withMessages(['items' => 'Kuantitas kirim untuk item ' . $transferRequestItem->item->name . ' melebihi sisa yang diminta.']);
                    }

                    $inventory = Inventory::where('warehouse_id', $transferRequest->from_warehouse_id)
                        ->where('item_id', $transferRequestItem->item_id)
                        ->first();

                    if (!$inventory || $inventory->quantity < $quantityToShip) {
                        throw ValidationException::withMessages(['items' => 'Stok untuk item ' . $transferRequestItem->item->name . ' tidak mencukupi di gudang asal.']);
                    }
                }

                // 2. Buat Shipment Header
                $shipment = Shipment::create([
                    'code' => 'SHIP-' . date('Ymd') . '-' . str_pad(Shipment::count() + 1, 4, '0', STR_PAD_LEFT),
                    'reference_id' => $transferRequest->id,
                    'reference_type' => Shipment::REFERENCE_TYPE_TRANSFER_REQUEST,
                    'shipping_date' => $validated['shipping_date'],
                    'vehicle_type' => $validated['vehicle_type'],
                    'license_plate' => $validated['license_plate'],
                    'driver_name' => $validated['driver_name'],
                    'driver_contact' => $validated['driver_contact'],
                    'description' => $validated['description'],
                    'shipped_by' => auth()->id(),
                ]);

                // 3. Proses setiap item
                foreach ($itemsToShip as $id => $itemData) {
                    $transferRequestItem = TransferRequestItem::findOrFail($id);
                    $quantityToShip = $itemData['quantity'] ?? 0;
                    $koliToShip = $itemData['koli'] ?? 0;

                    // Buat Shipment Item Detail
                    ShipmentItemDetail::create([
                        'shipment_id' => $shipment->id,
                        'reference_id' => $transferRequestItem->id,
                        'reference_type' => 'transfer_request_items',
                        'item_id' => $transferRequestItem->item_id,
                        'quantity_shipped' => $quantityToShip,
                        'koli_shipped' => $koliToShip,
                        'description' => $itemData['description'] ?? null,
                    ]);

                    // Kurangi stok dari gudang asal
                    $fromInventory = Inventory::where('warehouse_id', $transferRequest->from_warehouse_id)
                        ->where('item_id', $transferRequestItem->item_id)
                        ->first();

                    $stockBefore = $fromInventory->quantity;
                    $fromInventory->quantity -= $quantityToShip;
                    $fromInventory->koli -= $koliToShip;
                    $fromInventory->save();

                    // Buat Catatan Pergerakan Stok
                    StockMovement::create([
                        'item_id' => $transferRequestItem->item_id,
                        'date' => $validated['shipping_date'],
                        'warehouse_id' => $transferRequest->from_warehouse_id,
                        'quantity' => -$quantityToShip,
                        'stock_before' => $stockBefore,
                        'description' => 'Pengiriman permintaan transfer ' . $transferRequest->code,
                        'stock_after' => $fromInventory->quantity,
                        'koli' => -$koliToShip,
                        'type' => 'stock_out',
                        'user_id' => auth()->id(),
                        // Mengacu pada baris item permintaan transfer sesuai enum reference_type
                        'reference_id' => $transferRequestItem->id,
                        'reference_type' => 'transfer_request_items',
                    ]);

                    // Update sisa kuantitas di item permintaan transfer
                    $transferRequestItem->remaining_quantity -= $quantityToShip;
                    $transferRequestItem->remaining_koli -= $koliToShip;
                    $transferRequestItem->save();
                }

                // 4. Update Status Transfer Request to 'on_progress'
                $transferRequest->status = 'on_progress';
                $transferRequest->shipped_at = now();
                $transferRequest->shipped_by = auth()->id();
                $transferRequest->save();

                UserActivity::create([
                    'user_id' => Auth::id(),
                    'activity' => 'shipped',
                    'menu' => 'Permintaan Barang',
                    'description' => 'Membuat pengiriman parsial/penuh untuk kode ' . $transferRequest->code,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->header('User-Agent'),
                ]);
            });

            return response()->json(['success' => true, 'message' => 'Data pengiriman berhasil disimpan.']);

        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Gagal membuat data pengiriman: ' . $e->getMessage()], 500);
        }
    }
}
