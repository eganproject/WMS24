<?php

namespace App\Http\Controllers\Admin\StokMasuk;

use App\Http\Controllers\Controller;
use App\Models\TransferRequest;
use App\Models\Shipment;
use App\Models\GoodsReceipt;
use App\Models\TransferRequestItem;
use App\Models\UserActivity;
use App\Models\Warehouse;
use App\Models\Item;
use App\Models\Inventory;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RequestTransferController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $searchValue = $request->input('search.value', '');
            $start = $request->input('start', 0);
            $length = $request->input('length', 10);
            $draw = $request->input('draw', 0);
            $fromWarehouseFilter = $request->input('from_warehouse_id');
            $toWarehouseFilter = $request->input('to_warehouse_id');
            $statusFilter = $request->input('status');
            $dateFilter = $request->input('date');

            // We force order by date to avoid column index mismatch with dynamic columns in view
            $orderByColumnName = 'tr.date';
            $orderDirection = $request->input('order.0.dir', 'desc');

            $query = TransferRequest::query()
                ->from('transfer_requests as tr')
                ->leftJoin('warehouses as fw', 'tr.from_warehouse_id', '=', 'fw.id')
                ->leftJoin('warehouses as tw', 'tr.to_warehouse_id', '=', 'tw.id')
                ->leftJoin('users as u', 'tr.requested_by', '=', 'u.id')
                ->addSelect(DB::raw("(
                    SELECT GROUP_CONCAT(CONCAT(items.sku, ' (', FORMAT(tri.quantity,0), ')') SEPARATOR ', ')
                    FROM transfer_request_items tri
                    JOIN items ON items.id = tri.item_id
                    WHERE tri.transfer_request_id = tr.id
                ) AS items_list"));

            // Apply user's warehouse filter if not null
            if (auth()->user()->warehouse_id !== null) {
                $query->where(function ($q) {
                    $q->where('tr.to_warehouse_id', auth()->user()->warehouse_id);
                });
            }

            $totalRecords = $query->count();

            if (!empty($searchValue)) {
                $query->where(function ($q) use ($searchValue) {
                    $q->where('tr.code', 'LIKE', "%{$searchValue}%")
                        ->orWhere('fw.name', 'LIKE', "%{$searchValue}%")
                        ->orWhere('tw.name', 'LIKE', "%{$searchValue}%")
                        ->orWhere('u.name', 'LIKE', "%{$searchValue}%")
                        ->orWhere('tr.status', 'LIKE', "%{$searchValue}%");
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

            $totalFiltered = $query->count();

            $data = $query->addSelect(
                'tr.id',
                'tr.code',
                'tr.date',
                'tr.status',
                'fw.name as from_warehouse_name',
                'tw.name as to_warehouse_name',
                'u.name as requester_name'
            )
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
        return view('admin.stok_masuk.request_transfer.index', compact('warehouses'));
    }

    public function create()
    {
        $warehouses = Warehouse::all();
        $items = Item::all();
        $code = $this->generateTransferRequestCode();
        return view('admin.stok_masuk.request_transfer.create', compact('warehouses', 'items', 'code'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'from_warehouse_id' => 'required|exists:warehouses,id',
            'to_warehouse_id' => 'required|exists:warehouses,id|different:from_warehouse_id',
            'date' => 'required|date',
            'description' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.quantity' => 'required|numeric|gt:0',
            'items.*.koli' => 'nullable|numeric|min:0',
            'items.*.description' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $transferRequest = TransferRequest::create([
                'code' => $this->generateTransferRequestCode(),
                'from_warehouse_id' => $request->from_warehouse_id,
                'to_warehouse_id' => $request->to_warehouse_id,
                'date' => $request->date,
                'description' => $request->description,
                'status' => 'pending',
                'requested_by' => auth()->id(),
            ]);

            foreach ($request->items as $item) {
                TransferRequestItem::create([
                    'transfer_request_id' => $transferRequest->id,
                    'item_id' => $item['item_id'],
                    'quantity' => $item['quantity'],
                    'koli' => $item['koli'] ?? 0,
                    'remaining_quantity' => $item['quantity'],
                    'remaining_koli' => $item['koli'] ?? 0,
                    'description' => $item['description'],
                ]);
            }

            UserActivity::create([
                'user_id' => Auth::id(),
                'activity' => 'created',
                'menu' => 'Request Transfer',
                'description' => 'Membuat Request Transfer pada kode : ' . $transferRequest->code,
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);

            DB::commit();

            return redirect()->route('admin.stok-masuk.request-transfer.index')->with('success', 'Request transfer berhasil dibuat.');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal membuat request transfer: ' . $e->getMessage())->withInput();
        }
    }

    public function calculateItemValues(Request $request)
    {
        $request->validate([
            'item_id' => 'required|exists:items,id',
            'quantity' => 'nullable|numeric|min:0',
            'koli' => 'nullable|numeric|min:0',
        ]);

        $item = Item::find($request->item_id);

        if (!$item) {
            return response()->json(['error' => 'Item not found'], 404);
        }

        $koliPerUnit = $item->koli ?? 1; // Use 'koli' column, default to 1 if null

        $quantity = $request->input('quantity');
        $koli = $request->input('koli');

        if (isset($quantity)) {
            // Calculate koli from quantity
            $calculatedKoli = $koliPerUnit > 0 ? $quantity / $koliPerUnit : 0;
            return response()->json([
                'quantity' => (float) $quantity,
                'koli' => (float) $calculatedKoli,
            ]);
        } elseif (isset($koli)) {
            // Calculate quantity from koli
            $calculatedQuantity = $koli * $koliPerUnit;
            return response()->json([
                'quantity' => (float) $calculatedQuantity,
                'koli' => (float) $koli,
            ]);
        }

        return response()->json(['error' => 'Invalid input. Either quantity or koli must be provided.'], 400);
    }

    private function generateTransferRequestCode(): string
    {
        $today = date('Ymd');
        $prefix = 'TR-' . $today . '-';
        $maxNumber = TransferRequest::where('code', 'like', $prefix . '%')
            ->selectRaw('MAX(CAST(RIGHT(code, 4) AS UNSIGNED)) as max_num')
            ->value('max_num');
        $nextNumber = $maxNumber ? intval($maxNumber) + 1 : 1;

        return $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    public function show(TransferRequest $transferRequest)
    {
        $transferRequest->load(['fromWarehouse', 'toWarehouse', 'items', 'items.item']);

        $shipments = Shipment::where('reference_type', Shipment::REFERENCE_TYPE_TRANSFER_REQUEST)
            ->where('reference_id', $transferRequest->id)
            ->with(['itemDetails.item'])
            ->orderBy('shipping_date')
            ->get();

        return view('admin.stok_masuk.request_transfer.show', compact('transferRequest', 'shipments'));
    }

    public function edit(TransferRequest $transferRequest)
    {
        if($transferRequest->status != 'pending'){
            return redirect()->route('admin.stok-masuk.request-transfer.index')->with('error', 'Hanya request transfer dengan status pending yang dapat diubah.');
        }
        $warehouses = Warehouse::all();
        $items = Item::all();
        $transferRequest->load(['items', 'items.item']);
        return view('admin.stok_masuk.request_transfer.edit', compact('transferRequest', 'warehouses', 'items'));
    }

    public function update(Request $request, TransferRequest $transferRequest)
    {
        $request->validate([
            'from_warehouse_id' => 'required|exists:warehouses,id',
            'to_warehouse_id' => 'required|exists:warehouses,id|different:from_warehouse_id',
            'date' => 'required|date',
            'description' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.quantity' => 'required|numeric|gt:0',
            'items.*.koli' => 'nullable|numeric|min:0',
            'items.*.description' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $transferRequest->update([
                'from_warehouse_id' => $request->from_warehouse_id,
                'to_warehouse_id' => $request->to_warehouse_id,
                'date' => $request->date,
                'description' => $request->description,
                'status' => $request->status ?? $transferRequest->status,
            ]);

            $transferRequest->items()->delete();

            foreach ($request->items as $item) {
                TransferRequestItem::create([
                    'transfer_request_id' => $transferRequest->id,
                    'item_id' => $item['item_id'],
                    'quantity' => $item['quantity'],
                    'koli' => $item['koli'] ?? 0,
                    'remaining_quantity' => $item['quantity'],
                    'remaining_koli' => $item['koli'] ?? 0,
                    'description' => $item['description'],
                ]);
            }

            UserActivity::create([
                'user_id' => Auth::id(),
                'activity' => 'updated',
                'menu' => 'Request Transfer',
                'description' => 'Mengubah Request Transfer pada kode : ' . $transferRequest->code,
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);

            DB::commit();

            return redirect()->route('admin.stok-masuk.request-transfer.index')->with('success', 'Request transfer berhasil diperbarui.');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal memperbarui request transfer: ' . $e->getMessage())->withInput();
        }
    }

    public function destroy(Request $request, TransferRequest $transferRequest)
    {
        if ($transferRequest->status != 'pending') {
            return redirect()->route('admin.stok-masuk.request-transfer.index')->with('error', 'Hanya request transfer dengan status pending yang dapat dihapus.');
        }

        try {
            DB::beginTransaction();

            UserActivity::create([
                'user_id' => Auth::id(),
                'activity' => 'deleted',
                'menu' => 'Request Transfer',
                'description' => 'Menghapus Request Transfer pada kode : ' . $transferRequest->code,
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);
            $transferRequest->items()->delete();
            $transferRequest->delete();
            DB::commit();
            return redirect()->route('admin.stok-masuk.request-transfer.index')->with('success', 'Request transfer berhasil dihapus.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('admin.stok-masuk.request-transfer.index')->with('error', 'Gagal menghapus request transfer.');
        }
    }

    public function getItemsByWarehouse($warehouse_id)
    {
        $items = Inventory::with('item')
            ->where('warehouse_id', $warehouse_id)
            ->where('quantity', '>', 0)
            ->get();

        return response()->json($items);
    }

    public function getStatusCounts(Request $request)
    {
        $dateFilter = $request->input('date');
        $fromWarehouseFilter = $request->input('from_warehouse_id');
        $toWarehouseFilter = $request->input('to_warehouse_id');
        $statusFilter = $request->input('status');

        $query = TransferRequest::query();

        if (auth()->user()->warehouse_id !== null) {
            $query->where('to_warehouse_id', auth()->user()->warehouse_id);
        }

        if ($dateFilter && $dateFilter !== 'semua') {
            $query->whereDate('date', $dateFilter);
        }

        if ($fromWarehouseFilter && $fromWarehouseFilter !== 'semua') {
            $query->where('from_warehouse_id', $fromWarehouseFilter);
        }

        if ($toWarehouseFilter && $toWarehouseFilter !== 'semua') {
            $query->where('to_warehouse_id', $toWarehouseFilter);
        }
        
        if ($statusFilter && $statusFilter !== 'semua') {
            $query->where('status', $statusFilter);
        }

        $statusCounts = $query->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        // Align with migration: statuses are pending, approved, rejected, on_progress, completed
        $result = [
            'requested'    => $statusCounts->get('pending', 0),
            'approved'     => $statusCounts->get('approved', 0),
            'on_progress'  => $statusCounts->get('on_progress', 0),
            'completed'    => $statusCounts->get('completed', 0),
            'rejected'     => $statusCounts->get('rejected', 0),
        ];

        return response()->json($result);
    }
}
