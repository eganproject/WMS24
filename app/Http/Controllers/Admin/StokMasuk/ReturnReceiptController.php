<?php

namespace App\Http\Controllers\Admin\StokMasuk;

use App\Http\Controllers\Controller;
use App\Models\ReturnReceipt;
use App\Models\ReturnReceiptDetail;
use App\Models\Warehouse;
use App\Models\Item;
use App\Models\UserActivity;
use App\Models\Inventory;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use App\Support\MenuPermissionResolver;

class ReturnReceiptController extends Controller
{
    protected $permissionResolver;

    public function __construct(MenuPermissionResolver $permissionResolver)
    {
        $this->permissionResolver = $permissionResolver;
    }
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $search = $request->input('search.value', '');
            $start = (int) $request->input('start', 0);
            $length = (int) $request->input('length', 10);
            $draw = (int) $request->input('draw', 0);
            $statusFilter = $request->input('status');

            $forcedWarehouseId = auth()->user()->warehouse_id ?? null;

            $base = ReturnReceipt::query()
                ->from('return_receipts as rr')
                ->leftJoin('warehouses as w', 'rr.warehouse_id', '=', 'w.id')
                ->leftJoin('return_receipt_details as rrd', 'rr.id', '=', 'rrd.return_receipt_id')
                ->leftJoin('items as i', 'rrd.item_id', '=', 'i.id');
            if ($forcedWarehouseId !== null) {
                $base->where('warehouse_id', $forcedWarehouseId);
            }

            $total = (clone $base)->count();

            $q = (clone $base);

            if ($statusFilter && $statusFilter !== 'semua') {
                $q->where('status', $statusFilter);
            }

            if ($search) {
                $q->where(function($s) use ($search){
                    $s->where('rr.code','like',"%$search%")
                      ->orWhere('rr.status','like',"%$search%")
                      ->orWhere('w.name','like',"%$search%")
                      ->orWhere('i.sku','like',"%$search%");
                });
            }

            $filtered = (clone $q)->distinct('rr.id')->count('rr.id');

            // Handle ordering: map datatable columns to DB columns (items col uses rr.id fallback)
            $columns = [
                0 => 'rr.code',
                1 => 'rr.return_date',
                2 => 'w.name',
                3 => 'rr.id', // items (non-orderable in view)
                4 => 'rr.status',
                5 => 'rr.id',
            ];
            $orderColumnIndex = (int) $request->input('order.0.column', 1);
            $orderColumnName = $columns[$orderColumnIndex] ?? 'rr.return_date';
            $orderDirection = $request->input('order.0.dir', 'desc') === 'asc' ? 'asc' : 'desc';

            $rows = $q->select(
                    'rr.id',
                    'rr.code',
                    'rr.return_date',
                    'rr.status',
                    'w.name as warehouse_name',
                    DB::raw("GROUP_CONCAT(DISTINCT CONCAT(i.sku, ' (', FORMAT((SELECT SUM(d2.quantity) FROM return_receipt_details d2 WHERE d2.return_receipt_id = rr.id AND d2.item_id = i.id), 0), ')') ORDER BY i.sku SEPARATOR ', ') as items_list")
                )
                ->groupBy('rr.id','rr.code','rr.return_date','rr.status','w.name')
                ->orderBy($orderColumnName, $orderDirection)
                ->offset($start)
                ->limit($length)
                ->get()
                ->map(function($r){
                    return [
                        'id' => $r->id,
                        'code' => $r->code,
                        'return_date' => optional($r->return_date)->format('Y-m-d'),
                        'warehouse' => $r->warehouse_name ?? '-',
                        'items' => $r->items_list ?? '-',
                        'status' => $r->status,
                    ];
                });

            return response()->json([
                'draw' => $draw,
                'recordsTotal' => $total,
                'recordsFiltered' => $filtered,
                'data' => $rows,
            ]);
        }

        return view('admin.stok_masuk.penerimaan_retur.index');
    }

    public function getStatusCounts(Request $request)
    {
        $forcedWarehouseId = auth()->user()->warehouse_id ?? null;
        $base = ReturnReceipt::query();
        if ($forcedWarehouseId !== null) {
            $base->where('warehouse_id', $forcedWarehouseId);
        }
        return response()->json([
            'draft' => (clone $base)->where('status', ReturnReceipt::STATUS_DRAFT)->count(),
            'completed' => (clone $base)->where('status', ReturnReceipt::STATUS_COMPLETED)->count(),
        ]);
    }

    public function create()
    {
        $warehouses = Warehouse::orderBy('name')->get();
        $items = Item::orderBy('nama_barang')->get();
        $code = 'RR-' . date('Ymd') . '-' . str_pad(ReturnReceipt::count() + 1, 4, '0', STR_PAD_LEFT);
        return view('admin.stok_masuk.penerimaan_retur.create', compact('warehouses','items','code'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required','string','max:50','unique:return_receipts,code'],
            'warehouse_id' => ['required','exists:warehouses,id'],
            'return_date' => ['required','date'],
            'description' => ['nullable','string'],
            'details' => ['required','array','min:1'],
            'details.*.item_id' => ['required','exists:items,id'],
            'details.*.quantity' => ['required','integer','min:0'],
            'details.*.koli' => ['nullable','numeric','min:0'],
            'details.*.notes' => ['nullable','string'],
        ]);

        DB::beginTransaction();
        try {
            $rr = ReturnReceipt::create([
                'code' => $validated['code'],
                'warehouse_id' => $validated['warehouse_id'],
                'return_date' => $validated['return_date'],
                'status' => ReturnReceipt::STATUS_DRAFT,
                'description' => $validated['description'] ?? null,
                'received_by' => Auth::id(),
            ]);

            foreach ($validated['details'] as $d) {
                $qty = (int) ($d['quantity'] ?? 0);
                $koli = (float) ($d['koli'] ?? 0);
                $rr->details()->create([
                    'item_id' => $d['item_id'],
                    'quantity' => $qty,
                    'koli' => round($koli,2),
                    'accepted_quantity' => $qty,
                    'accepted_koli' => round($koli,2),
                    'rejected_quantity' => 0,
                    'rejected_koli' => 0,
                    'notes' => $d['notes'] ?? null,
                ]);
            }

            // Log user activity
            UserActivity::create([
                'user_id' => Auth::id(),
                'activity' => 'created',
                'menu' => 'Penerimaan Retur',
                'description' => 'Menambahkan penerimaan retur: ' . $rr->code,
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);

            DB::commit();
            return redirect()->route('admin.stok-masuk.penerimaan-retur.index')->with('success','Penerimaan retur berhasil dibuat.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['general' => 'Gagal menyimpan: '.$e->getMessage()])->withInput();
        }
    }

    public function show(ReturnReceipt $penerimaan_retur)
    {
        $rr = $penerimaan_retur->load(['warehouse','details.item','receiver','verifier']);
        return view('admin.stok_masuk.penerimaan_retur.show', ['rr' => $rr]);
    }

    public function edit(ReturnReceipt $penerimaan_retur)
    {
        $rr = $penerimaan_retur->load(['details.item']);
        $warehouses = Warehouse::orderBy('name')->get();
        $items = Item::orderBy('nama_barang')->get();
        return view('admin.stok_masuk.penerimaan_retur.edit', compact('rr','warehouses','items'));
    }

    public function update(Request $request, ReturnReceipt $penerimaan_retur)
    {
        $validated = $request->validate([
            'code' => ['required','string','max:50', Rule::unique('return_receipts','code')->ignore($penerimaan_retur->id)],
            'warehouse_id' => ['required','exists:warehouses,id'],
            'return_date' => ['required','date'],
            'description' => ['nullable','string'],
            'details' => ['required','array','min:1'],
            'details.*.item_id' => ['required','exists:items,id'],
            'details.*.quantity' => ['required','integer','min:0'],
            'details.*.koli' => ['nullable','numeric','min:0'],
            'details.*.notes' => ['nullable','string'],
        ]);

        DB::beginTransaction();
        try {
            $penerimaan_retur->update([
                'code' => $validated['code'],
                'warehouse_id' => $validated['warehouse_id'],
                'return_date' => $validated['return_date'],
                'description' => $validated['description'] ?? null,
            ]);

            $penerimaan_retur->details()->delete();
            foreach ($validated['details'] as $d) {
                $penerimaan_retur->details()->create([
                    'item_id' => $d['item_id'],
                    'quantity' => (int) ($d['quantity'] ?? 0),
                    'koli' => round((float) ($d['koli'] ?? 0),2),
                    'accepted_quantity' => (int) ($d['quantity'] ?? 0),
                    'accepted_koli' => round((float) ($d['koli'] ?? 0),2),
                    'rejected_quantity' => 0,
                    'rejected_koli' => 0,
                    'notes' => $d['notes'] ?? null,
                ]);
            }

            // Log user activity
            UserActivity::create([
                'user_id' => Auth::id(),
                'activity' => 'updated',
                'menu' => 'Penerimaan Retur',
                'description' => 'Mengupdate penerimaan retur: ' . $penerimaan_retur->code,
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);

            DB::commit();
            return redirect()->route('admin.stok-masuk.penerimaan-retur.index')->with('success','Penerimaan retur berhasil diupdate.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['general' => 'Gagal mengupdate: '.$e->getMessage()])->withInput();
        }
    }

    public function destroy(ReturnReceipt $penerimaan_retur)
    {
        DB::beginTransaction();
        try {
            $code = $penerimaan_retur->code;
            $penerimaan_retur->details()->delete();
            $penerimaan_retur->delete();

            // Log user activity
            UserActivity::create([
                'user_id' => Auth::id(),
                'activity' => 'deleted',
                'menu' => 'Penerimaan Retur',
                'description' => 'Menghapus penerimaan retur: ' . $code,
                'ip_address' => request()->ip(),
                'user_agent' => request()->header('User-Agent'),
            ]);
            DB::commit();
            return response()->json(['success'=>true]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success'=>false], 500);
        }
    }

    public function complete(Request $request, ReturnReceipt $penerimaan_retur)
    {
        if (!$this->permissionResolver->userCan('approve')) {
            return response()->json(['success' => false, 'message' => 'Anda tidak memiliki akses untuk menyetujui penerimaan retur.'], 403);
        }

        if ($penerimaan_retur->status === ReturnReceipt::STATUS_COMPLETED) {
            return response()->json(['success' => false, 'message' => 'Dokumen sudah berstatus completed.'], 422);
        }

        try {
            DB::beginTransaction();

            // Load details to process inventory updates
            $penerimaan_retur->load('details');

            foreach ($penerimaan_retur->details as $detail) {
                $qtyIn = (float) ($detail->accepted_quantity ?? $detail->quantity ?? 0);
                $koliIn = (float) ($detail->accepted_koli ?? $detail->koli ?? 0);
                if ($qtyIn == 0 && $koliIn == 0) { continue; }

                // Get or create inventory record
                $inventory = Inventory::firstOrNew([
                    'warehouse_id' => $penerimaan_retur->warehouse_id,
                    'item_id' => $detail->item_id,
                ]);
                $before = (float) ($inventory->quantity ?? 0);
                $inventory->quantity = $before + $qtyIn;
                $inventory->koli = (float) ($inventory->koli ?? 0) + $koliIn;
                $inventory->save();

                // Log stock movement
                StockMovement::create([
                    'item_id' => $detail->item_id,
                    'warehouse_id' => $penerimaan_retur->warehouse_id,
                    'date' => $penerimaan_retur->return_date ?? now()->toDateString(),
                    'quantity' => $qtyIn,
                    'koli' => $koliIn,
                    'stock_before' => $before,
                    'stock_after' => $inventory->quantity,
                    'type' => 'stock_in',
                    'description' => 'Penerimaan Retur ' . $penerimaan_retur->code,
                    'user_id' => Auth::id(),
                    'reference_id' => $detail->id,
                    'reference_type' => 'return_receipt_details',
                ]);
            }

            $penerimaan_retur->update([
                'status' => ReturnReceipt::STATUS_COMPLETED,
                'verified_by' => Auth::id(),
                'completed_at' => now(),
            ]);

            UserActivity::create([
                'user_id' => Auth::id(),
                'activity' => 'approved',
                'menu' => 'Penerimaan Retur',
                'description' => 'Menyetujui penerimaan retur: ' . $penerimaan_retur->code,
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Penerimaan retur disetujui dan diselesaikan.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal menyelesaikan penerimaan retur: ' . $e->getMessage()], 500);
        }
    }
}
