<?php

namespace App\Http\Controllers\Admin\StokKeluar;

use App\Http\Controllers\Controller;
use App\Models\ReturnOut;
use App\Models\ReturnOutDetail;
use App\Models\Warehouse;
use App\Models\Item;
use App\Models\UserActivity;
use App\Models\Inventory;
use App\Models\StockMovement;
use App\Models\GoodsReceipt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use App\Support\MenuPermissionResolver;

class ReturnOutController extends Controller
{
    protected $permissionResolver;

    public function __construct(MenuPermissionResolver $permissionResolver)
    {
        $this->permissionResolver = $permissionResolver;
    }

    private function generateNewCode(): string
    {
        $prefix = 'RO';
        $date = now()->format('Ymd');
        $latest = ReturnOut::where('code', 'LIKE', "$prefix-$date-%")->latest('id')->first();
        $seq = $latest ? ((int) substr($latest->code, -4)) + 1 : 1;
        return sprintf('%s-%s-%04d', $prefix, $date, $seq);
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

            $base = ReturnOut::query()
                ->from('return_outs as ro')
                ->leftJoin('warehouses as w', 'ro.warehouse_id', '=', 'w.id')
                ->leftJoin('return_out_details as rod', 'ro.id', '=', 'rod.return_out_id')
                ->leftJoin('items as i', 'rod.item_id', '=', 'i.id');

            if ($forcedWarehouseId !== null) {
                $base->where('ri.warehouse_id', $forcedWarehouseId);
            }

            $total = (clone $base)->count();

            $q = (clone $base);
            if ($statusFilter && $statusFilter !== 'semua') {
                $q->where('ri.status', $statusFilter);
            }
            if ($search) {
                $q->where(function($s) use ($search){
                    $s->where('ri.code','like',"%$search%")
                      ->orWhere('ri.status','like',"%$search%")
                      ->orWhere('w.name','like',"%$search%")
                      ->orWhere('i.sku','like',"%$search%");
                });
            }

            $filtered = (clone $q)->distinct('ro.id')->count('ro.id');

            $rows = $q->select(
                    'ro.id','ro.code','ro.return_date','ro.status', 'w.name as warehouse_name',
                    DB::raw("GROUP_CONCAT(DISTINCT CONCAT(i.sku, ' (', FORMAT((SELECT SUM(d2.quantity) FROM return_out_details d2 WHERE d2.return_out_id = ro.id AND d2.item_id = i.id), 0), ')') ORDER BY i.sku SEPARATOR ', ') as items_list")
                )
                ->groupBy('ro.id','ro.code','ro.return_date','ro.status','w.name')
                ->orderBy('ro.return_date', 'desc')
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

        return view('admin.stok_keluar.retur_out.index');
    }

    public function getStatusCounts(Request $request)
    {
        $forcedWarehouseId = auth()->user()->warehouse_id ?? null;
        $base = ReturnOut::query();
        if ($forcedWarehouseId !== null) {
            $base->where('warehouse_id', $forcedWarehouseId);
        }
        return response()->json([
            'draft' => (clone $base)->where('status', ReturnOut::STATUS_DRAFT)->count(),
            'completed' => (clone $base)->where('status', ReturnOut::STATUS_COMPLETED)->count(),
        ]);
    }

    public function create()
    {
        $warehouses = Warehouse::orderBy('name')->get();
        $items = Item::orderBy('nama_barang')->get();
        // Build inventory grouped by warehouse like other stok-keluar modules
        $inventory = Inventory::with(['item.uom', 'item'])
            ->where('quantity', '>', 0)
            ->orWhere(function ($query) {
                $query->where('quantity', 0)
                      ->whereIn('item_id', function ($subQuery) {
                          $subQuery->select('item_id')
                                   ->from('return_out_details');
                      });
            })
            ->get()
            ->groupBy('warehouse_id');
        $code = $this->generateNewCode();
        return view('admin.stok_keluar.retur_out.create', compact('warehouses','items','inventory','code'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required','string','max:50','unique:return_outs,code'],
            'warehouse_id' => ['required','exists:warehouses,id'],
            'destination_warehouse_id' => ['nullable','exists:warehouses,id'],
            'return_date' => ['required','date'],
            'description' => ['nullable','string'],
            'details' => ['required','array','min:1'],
            'details.*.item_id' => ['required','exists:items,id'],
            'details.*.quantity' => [
                'required','numeric','min:0',
                function ($attribute, $value, $fail) use ($request) {
                    $parts = explode('.', $attribute);
                    $index = $parts[1] ?? null;
                    $itemId = $request->input("details.$index.item_id");
                    $warehouseId = $request->input('warehouse_id');
                    if (!$itemId || !$warehouseId) return;
                    $inv = Inventory::where('warehouse_id', $warehouseId)->where('item_id', $itemId)->with('item')->first();
                    $availableQty = $inv ? (float) $inv->quantity : 0;
                    $availableKoli = $inv ? (float) ($inv->koli ?? 0) : 0;
                    $koliVal = (float) $request->input("details.$index.koli") ?: 0;
                    // determine item koli factor (pcs per koli)
                    $itemKoli = 1;
                    if ($inv && $inv->relationLoaded('item') && $inv->item) {
                        $itemKoli = (float) ($inv->item->koli ?? 1);
                    } else {
                        $item = \App\Models\Item::find($itemId);
                        $itemKoli = $item ? (float) ($item->koli ?? 1) : 1;
                    }
                    $derivedQtyFromKoli = $koliVal > 0 ? ceil($koliVal * $itemKoli) : 0;
                    $effectiveQty = max((float) $value, $derivedQtyFromKoli);
                    if ($availableQty <= 0 && $availableKoli <= 0) {
                        $fail('tidak ada item digudang');
                        return;
                    }
                    if ($effectiveQty > $availableQty) {
                        $fail('Jumlah melebihi stok tersedia');
                        return;
                    }
                    if ($koliVal > $availableKoli) {
                        $fail('Koli melebihi stok koli tersedia');
                        return;
                    }
                }
            ],
            'details.*.koli' => ['nullable','numeric','min:0'],
            'details.*.notes' => ['nullable','string'],
        ]);

        DB::beginTransaction();
        try {
            $ri = ReturnOut::create([
                'code' => $validated['code'],
                'warehouse_id' => $validated['warehouse_id'],
                'destination_warehouse_id' => $validated['destination_warehouse_id'] ?? null,
                'return_date' => $validated['return_date'],
                'status' => ReturnOut::STATUS_DRAFT,
                'description' => $validated['description'] ?? null,
                'sent_by' => Auth::id(),
            ]);

            foreach ($validated['details'] as $d) {
                // Skip zero lines to keep DB clean
                $inputQty = (float) ($d['quantity'] ?? 0);
                $inputKoli = (float) ($d['koli'] ?? 0);
                if ($inputQty <= 0 && $inputKoli <= 0) continue;
                // get inventory to cap values
                $inv = Inventory::where('warehouse_id', $validated['warehouse_id'])->where('item_id', $d['item_id'])->first();
                $availableQty = $inv ? (float) $inv->quantity : 0;
                $availableKoli = $inv ? (float) ($inv->koli ?? 0) : 0;
                // determine itemKoli
                $item = \App\Models\Item::find($d['item_id']);
                $itemKoli = $item ? (float) ($item->koli ?? 1) : 1;
                // if koli provided, compute qty from koli
                $qty = $inputQty;
                $koli = $inputKoli;
                if ($koli > 0) {
                    $qtyFromKoli = ceil($koli * $itemKoli);
                    $qty = max($qty, $qtyFromKoli);
                }
                // cap by available
                if ($availableQty <= 0 && $availableKoli <= 0) continue; // nothing to save
                if ($qty > $availableQty) {
                    $qty = $availableQty;
                    // adjust koli down accordingly
                    $koli = floor($qty / $itemKoli);
                }
                if ($koli > $availableKoli) {
                    $koli = $availableKoli;
                    $qty = ceil($koli * $itemKoli);
                }
                $ri->details()->create([
                    'item_id' => $d['item_id'],
                    'quantity' => $qty,
                    'koli' => $koli,
                    'notes' => $d['notes'] ?? null,
                ]);
            }

            UserActivity::create([
                'user_id' => Auth::id(),
                'activity' => 'created',
                'menu' => 'Retur Out',
                'description' => 'Menambahkan retur out: ' . $ri->code,
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);

            DB::commit();
            return redirect()->route('admin.stok-keluar.retur-out.index')->with('success','Retur out berhasil dibuat.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['general' => 'Gagal menyimpan: '.$e->getMessage()])->withInput();
        }
    }

    public function show(ReturnOut $retur_out)
    {
        $ri = $retur_out->load(['warehouse','destinationWarehouse','details.item']);
        return view('admin.stok_keluar.retur_out.show', compact('ri'));
    }

    public function edit(ReturnOut $retur_out)
    {
        $ri = $retur_out->load(['details.item']);
        $warehouses = Warehouse::orderBy('name')->get();
        $items = Item::orderBy('nama_barang')->get();
        $inventory = Inventory::with(['item.uom','item'])
            ->where('quantity', '>', 0)
            ->orWhere(function ($query) {
                $query->where('quantity', 0)
                      ->whereIn('item_id', function ($subQuery) {
                          $subQuery->select('item_id')->from('return_out_details');
                      });
            })
            ->get()
            ->groupBy('warehouse_id');
        return view('admin.stok_keluar.retur_out.edit', compact('ri','warehouses','items','inventory'));
    }

    public function update(Request $request, ReturnOut $retur_out)
    {
        $validated = $request->validate([
            'code' => ['required','string','max:50', Rule::unique('return_outs','code')->ignore($retur_out->id)],
            'warehouse_id' => ['required','exists:warehouses,id'],
            'destination_warehouse_id' => ['nullable','exists:warehouses,id'],
            'return_date' => ['required','date'],
            'description' => ['nullable','string'],
            'details' => ['required','array','min:1'],
            'details.*.item_id' => ['required','exists:items,id'],
            'details.*.quantity' => [
                'required','numeric','min:0',
                function ($attribute, $value, $fail) use ($request) {
                    $parts = explode('.', $attribute);
                    $index = $parts[1] ?? null;
                    $itemId = $request->input("details.$index.item_id");
                    $warehouseId = $request->input('warehouse_id');
                    if (!$itemId || !$warehouseId) return;
                    $inv = Inventory::where('warehouse_id', $warehouseId)->where('item_id', $itemId)->first();
                    $availableQty = $inv ? (float) $inv->quantity : 0;
                    $availableKoli = $inv ? (float) ($inv->koli ?? 0) : 0;
                    $koliVal = (float) $request->input("details.$index.koli") ?: 0;
                    if ($availableQty <= 0 && $availableKoli <= 0) {
                        $fail('tidak ada item digudang');
                        return;
                    }
                    if ((float) $value > $availableQty) {
                        $fail('Jumlah melebihi stok tersedia');
                        return;
                    }
                    if ($koliVal > $availableKoli) {
                        $fail('Koli melebihi stok koli tersedia');
                        return;
                    }
                }
            ],
            'details.*.koli' => ['nullable','numeric','min:0'],
            'details.*.notes' => ['nullable','string'],
        ]);

        DB::beginTransaction();
        try {
            $retur_out->update([
                'code' => $validated['code'],
                'warehouse_id' => $validated['warehouse_id'],
                'destination_warehouse_id' => $validated['destination_warehouse_id'] ?? null,
                'return_date' => $validated['return_date'],
                'description' => $validated['description'] ?? null,
            ]);

            $retur_out->details()->delete();
            foreach ($validated['details'] as $d) {
                $inputQty = (float) ($d['quantity'] ?? 0);
                $inputKoli = (float) ($d['koli'] ?? 0);
                if ($inputQty <= 0 && $inputKoli <= 0) continue;
                $inv = Inventory::where('warehouse_id', $validated['warehouse_id'])->where('item_id', $d['item_id'])->first();
                $availableQty = $inv ? (float) $inv->quantity : 0;
                $availableKoli = $inv ? (float) ($inv->koli ?? 0) : 0;
                $item = \App\Models\Item::find($d['item_id']);
                $itemKoli = $item ? (float) ($item->koli ?? 1) : 1;
                $qty = $inputQty;
                $koli = $inputKoli;
                if ($koli > 0) {
                    $qtyFromKoli = ceil($koli * $itemKoli);
                    $qty = max($qty, $qtyFromKoli);
                }
                if ($availableQty <= 0 && $availableKoli <= 0) continue;
                if ($qty > $availableQty) {
                    $qty = $availableQty;
                    $koli = floor($qty / $itemKoli);
                }
                if ($koli > $availableKoli) {
                    $koli = $availableKoli;
                    $qty = ceil($koli * $itemKoli);
                }
                $retur_out->details()->create([
                    'item_id' => $d['item_id'],
                    'quantity' => $qty,
                    'koli' => $koli,
                    'notes' => $d['notes'] ?? null,
                ]);
            }

            UserActivity::create([
                'user_id' => Auth::id(),
                'activity' => 'updated',
                'menu' => 'Retur Out',
                'description' => 'Mengupdate retur out: ' . $retur_out->code,
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);

            DB::commit();
            return redirect()->route('admin.stok-keluar.retur-out.index')->with('success','Retur out berhasil diupdate.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['general' => 'Gagal mengupdate: '.$e->getMessage()])->withInput();
        }
    }

    public function destroy(ReturnOut $retur_out)
    {
        DB::beginTransaction();
        try {
            $code = $retur_out->code;
            $retur_out->details()->delete();
            $retur_out->delete();

            UserActivity::create([
                'user_id' => Auth::id(),
                'activity' => 'deleted',
                'menu' => 'Retur Out',
                'description' => 'Menghapus retur out: ' . $code,
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

    public function complete(Request $request, ReturnOut $retur_out)
    {
        if (!$this->permissionResolver->userCan('approve')) {
            return response()->json(['success' => false, 'message' => 'Anda tidak memiliki akses untuk menyetujui retur out.'], 403);
        }
        if ($retur_out->status === ReturnOut::STATUS_COMPLETED) {
            return response()->json(['success' => false, 'message' => 'Dokumen sudah berstatus completed.'], 422);
        }

        try {
            DB::beginTransaction();
            $retur_out->load('details');

            foreach ($retur_out->details as $detail) {
                $qtyOut = (float) ($detail->quantity ?? 0);
                $koliOut = (float) ($detail->koli ?? 0);
                if ($qtyOut == 0 && $koliOut == 0) { continue; }

                // Kurangi stok di gudang asal
                $inventory = Inventory::firstOrNew([
                    'warehouse_id' => $retur_out->warehouse_id,
                    'item_id' => $detail->item_id,
                ]);
                $before = (float) ($inventory->quantity ?? 0);
                $inventory->quantity = $before - $qtyOut;
                $inventory->koli = (float) ($inventory->koli ?? 0) - $koliOut;
                $inventory->save();

                StockMovement::create([
                    'item_id' => $detail->item_id,
                    'warehouse_id' => $retur_out->warehouse_id,
                    'date' => $retur_out->return_date ?? now()->toDateString(),
                    'quantity' => -$qtyOut,
                    'koli' => -$koliOut,
                    'stock_before' => $before,
                    'stock_after' => $inventory->quantity,
                    'type' => 'stock_out',
                    'description' => 'Retur Out ' . $retur_out->code,
                    'user_id' => Auth::id(),
                    'reference_id' => $detail->id,
                    'reference_type' => 'return_out_details',
                ]);

                // Jika ada tujuan gudang (retur ke gudang asal pada request transfer), tambahkan stok di sana
                if (!empty($retur_out->destination_warehouse_id)) {
                    $destInventory = Inventory::firstOrNew([
                        'warehouse_id' => $retur_out->destination_warehouse_id,
                        'item_id' => $detail->item_id,
                    ]);
                    $destBefore = (float) ($destInventory->quantity ?? 0);
                    $destInventory->quantity = $destBefore + $qtyOut;
                    $destInventory->koli = (float) ($destInventory->koli ?? 0) + $koliOut;
                    $destInventory->save();

                    StockMovement::create([
                        'item_id' => $detail->item_id,
                        'warehouse_id' => $retur_out->destination_warehouse_id,
                        'date' => $retur_out->return_date ?? now()->toDateString(),
                        'quantity' => $qtyOut,
                        'koli' => $koliOut,
                        'stock_before' => $destBefore,
                        'stock_after' => $destInventory->quantity,
                        'type' => 'stock_in',
                        'description' => 'Retur Out (Tujuan) ' . $retur_out->code,
                        'user_id' => Auth::id(),
                        'reference_id' => $detail->id,
                        'reference_type' => 'return_out_details',
                    ]);
                }
            }

            $retur_out->update([
                'status' => ReturnOut::STATUS_COMPLETED,
                'verified_by' => Auth::id(),
                'completed_at' => now(),
            ]);

            UserActivity::create([
                'user_id' => Auth::id(),
                'activity' => 'approved',
                'menu' => 'Retur Out',
                'description' => 'Menyetujui retur out: ' . $retur_out->code,
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Retur out disetujui dan diselesaikan.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal menyelesaikan retur out: ' . $e->getMessage()], 500);
        }
    }
}
