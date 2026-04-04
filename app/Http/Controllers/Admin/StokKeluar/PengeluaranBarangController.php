<?php

namespace App\Http\Controllers\Admin\StokKeluar;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\StockOut;
use App\Models\StockOutItem;
use App\Models\UserActivity;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PengeluaranBarangController extends Controller
{
    private function generateNewCode()
    {
        $prefix = 'OUT';
        $date = now()->format('Ymd');
        $latestOrder = StockOut::where('code', 'LIKE', "$prefix-$date-%")->latest('id')->first();

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
            $warehouseFilter = $request->input('warehouse_id');
            $statusFilter = $request->input('status');
            $dateFilter = $request->input('date');

            $query = StockOut::with(['warehouse', 'user'])
                ->select('stock_outs.*')
                ->addSelect(DB::raw("(
                    SELECT GROUP_CONCAT(CONCAT(items.sku, ' (', FORMAT(soi.quantity,0), ')') SEPARATOR ', ')
                    FROM stock_out_items soi
                    JOIN items ON items.id = soi.item_id
                    WHERE soi.stock_out_id = stock_outs.id
                ) AS items_list"));

            $userWarehouseId = auth()->user()->warehouse_id;
            if ($userWarehouseId) {
                $query->where('warehouse_id', $userWarehouseId);
            } else {
                if ($warehouseFilter && $warehouseFilter !== 'semua') {
                    $query->where('warehouse_id', $warehouseFilter);
                }
            }

            $totalRecords = $query->count();

            if (!empty($searchValue)) {
                $query->where(function ($q) use ($searchValue) {
                    $q->where('id', 'LIKE', "%{$searchValue}%")
                      ->orWhereHas('warehouse', function ($subQuery) use ($searchValue) {
                          $subQuery->where('name', 'LIKE', "%{$searchValue}%");
                      })
                      ->orWhereHas('user', function ($subQuery) use ($searchValue) {
                          $subQuery->where('name', 'LIKE', "%{$searchValue}%");
                      });
                });
            }
            
            if ($statusFilter && $statusFilter !== 'semua') {
                $query->where('status', $statusFilter);
            }

            if ($dateFilter && $dateFilter !== 'semua') {
                $query->whereDate('date', $dateFilter);
            }

            $totalFiltered = $query->count();

            $data = $query->latest()->offset($start)->limit($length)->get();

            return response()->json([
                'draw' => intval($draw),
                'recordsTotal' => intval($totalRecords),
                'recordsFiltered' => intval($totalFiltered),
                'data' => $data,
            ]);
        }

        $userWarehouseId = auth()->user()->warehouse_id;

        $warehouses = $userWarehouseId ? collect() : Warehouse::all();
        return view('admin.stok_keluar.index', compact('warehouses'));
    }

    public function getStatusCounts(Request $request)
    {
        $warehouseFilter = $request->input('warehouse_id');
        $dateFilter = $request->input('date');

        $pendingCountQuery = StockOut::where('status', 'pending');
        $completedCountQuery = StockOut::where('status', 'completed');

        $userWarehouseId = auth()->user()->warehouse_id;
        if ($userWarehouseId) {
            $pendingCountQuery->where('warehouse_id', $userWarehouseId);
            $completedCountQuery->where('warehouse_id', $userWarehouseId);
        } else {
            if ($warehouseFilter && $warehouseFilter !== 'semua') {
                $pendingCountQuery->where('warehouse_id', $warehouseFilter);
                $completedCountQuery->where('warehouse_id', $warehouseFilter);
            }
        }

        if ($dateFilter && $dateFilter !== 'semua') {
            $pendingCountQuery->whereDate('date', $dateFilter);
            $completedCountQuery->whereDate('date', $dateFilter);
        }

        $pendingCount = $pendingCountQuery->count();
        $completedCount = $completedCountQuery->count();

        return response()->json([
            'pending' => $pendingCount,
            'completed' => $completedCount,
        ]);
    }

    public function create()
    {
        $warehouses = Warehouse::all();
        $inventory = Inventory::with(['item.uom', 'item'])
            ->where('quantity', '>', 0)
            ->orWhere(function ($query) {
                $query->where('quantity', 0)
                      ->whereIn('item_id', function ($subQuery) {
                          $subQuery->select('item_id')
                                   ->from('stock_out_items');
                      });
            })
            ->get()
            ->groupBy('warehouse_id');
        $newCode = $this->generateNewCode();
        return view('admin.stok_keluar.create', compact('warehouses', 'inventory', 'newCode'));
    }

    public function store(Request $request)
    {
        // dd($request->all());
                $request->validate([
            'code' => 'required|string|unique:stock_outs,code',
            'warehouse_id' => 'required|exists:warehouses,id',
            'date' => 'required|date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.koli' => 'required|min:0',
            'items.*.quantity' => [
                'required',
                'numeric',
                'min:1',
                function ($attribute, $value, $fail) use ($request) {
                    $index = explode('.', $attribute)[1];
                    $itemId = $request->input("items.$index.item_id");
                    $warehouseId = $request->input('warehouse_id');

                    $inventory = Inventory::where('warehouse_id', $warehouseId)
                                        ->where('item_id', $itemId)
                                        ->first();

                    if (!$inventory || $inventory->quantity < $value) {
                        $fail('tidak ada item digudang');
                    }
                },
            ],
        ]);

        DB::transaction(function () use ($request) {
            $stockOut = StockOut::create([
                'code' => $request->code,
                'warehouse_id' => $request->warehouse_id,
                'user_id' => auth()->id(),
                'date' => $request->date,
                'created_by' => auth()->id(),
                'notes' => $request->notes,
                'status' => 'completed',
            ]);

            foreach ($request->items as $itemData) {
                $stockOutItem = $stockOut->items()->create([
                    'item_id' => $itemData['item_id'],
                    'quantity' => $itemData['quantity'],
                    'koli' => $itemData['koli'],
                ]);

                $inventory = Inventory::where('warehouse_id', $request->warehouse_id)
                                    ->where('item_id', $itemData['item_id'])
                                    ->first();
                
                $stockBefore = $inventory->quantity;
                $inventory->decrement('quantity', $itemData['quantity']);
                // Update koli in inventory as well
                $inventory->decrement('koli', $itemData['koli']);
                $stockAfter = $inventory->quantity;

                StockMovement::create([
                    'item_id' => $itemData['item_id'],
                    'warehouse_id' => $request->warehouse_id,
                    'quantity' => -$itemData['quantity'],
                    'koli' => -$itemData['koli'],
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockAfter,
                    'type' => 'stock_out',
                    'description' => 'Pengeluaran Barang',
                    'reference_id' => $stockOutItem->id,
                    'reference_type' => 'stock_out_items',
                    'user_id' => auth()->id(),
                    'date' => $request->date,
                ]);
            }

            UserActivity::create([
                'user_id' => auth()->id(),
                'activity' => 'created',
                'menu' => 'Stok Keluar',
                'description' => 'Membuat data pengeluaran barang baru dengan kode ' . $stockOut->code,
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('user-agent'),
            ]);
        });

        return redirect()->route('admin.stok-keluar.pengeluaran-barang.index')->with('success', 'Data pengeluaran barang berhasil disimpan.');
    }

    public function show(StockOut $pengeluaranBarang)
    {
        $pengeluaranBarang->load(['warehouse', 'user', 'items.item']);
        return view('admin.stok_keluar.show', compact('pengeluaranBarang'));
    }

    public function edit(StockOut $pengeluaranBarang)
    {
        $warehouses = Warehouse::all();
        $inventory = Inventory::with(['item.uom', 'item'])
            ->where('quantity', '>', 0)
            ->orWhere(function ($query) {
                $query->where('quantity', 0)
                      ->whereIn('item_id', function ($subQuery) {
                          $subQuery->select('item_id')
                                   ->from('stock_out_items');
                      });
            })
            ->get()
            ->groupBy('warehouse_id');
        $pengeluaranBarang->load('items.item');
        return view('admin.stok_keluar.edit', compact('pengeluaranBarang', 'warehouses', 'inventory'));
    }

    public function update(Request $request, StockOut $pengeluaranBarang)
    {
        $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'date' => 'required|date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.koli' => 'required|numeric|min:0',
            'items.*.quantity' => [
                'required',
                'numeric',
                'min:0',
                function ($attribute, $value, $fail) use ($request, $pengeluaranBarang) {
                    $index = explode('.', $attribute)[1];
                    $itemId = $request->input("items.$index.item_id");
                    
                    $inventory = Inventory::where('warehouse_id', $request->warehouse_id)
                                        ->where('item_id', $itemId)
                                        ->first();

                    $originalQuantity = 0;
                    if ($pengeluaranBarang->warehouse_id == $request->warehouse_id) {
                        $originalItem = $pengeluaranBarang->items()->where('item_id', $itemId)->first();
                        if($originalItem) {
                           $originalQuantity = $originalItem->quantity;
                        }
                    }

                    $currentStock = $inventory ? $inventory->quantity : 0;
                    
                    if (($currentStock + $originalQuantity) < $value) {
                        $fail('tidak ada item digudang');
                    }
                },
            ],
        ]);

        DB::transaction(function () use ($request, $pengeluaranBarang) {
            // Revert old stock quantities
            foreach ($pengeluaranBarang->items as $oldItem) {
                $inventory = Inventory::where('warehouse_id', $pengeluaranBarang->warehouse_id)
                         ->where('item_id', $oldItem->item_id)->first();

                $stockBefore = $inventory->quantity;
                $inventory->increment('quantity', $oldItem->quantity);
                // Revert koli back to inventory
                $inventory->increment('koli', $oldItem->koli);
                $stockAfter = $inventory->quantity;

                StockMovement::create([
                    'item_id' => $oldItem->item_id,
                    'warehouse_id' => $pengeluaranBarang->warehouse_id,
                    'quantity' => $oldItem->quantity,
                    'koli' => $oldItem->koli,
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockAfter,
                    'type' => 'stock_out',
                    'description' => 'Revisi Pengeluaran Barang (Increment)',
                    'reference_id' => $oldItem->id,
                    'reference_type' => 'stock_out_items',
                    'user_id' => auth()->id(),
                    'date' => $pengeluaranBarang->date,
                ]);
            }

            $pengeluaranBarang->items()->delete();

            $pengeluaranBarang->update([
                'warehouse_id' => $request->warehouse_id,
                'date' => $request->date,
                'notes' => $request->notes,
            ]);

            foreach ($request->items as $itemData) {
                $stockOutItem = $pengeluaranBarang->items()->create([
                    'item_id' => $itemData['item_id'],
                    'quantity' => $itemData['quantity'],
                    'koli' => $itemData['koli'],
                ]);
                $inventory = Inventory::where('warehouse_id', $request->warehouse_id)
                         ->where('item_id', $itemData['item_id'])
                         ->first();

                $stockBefore = $inventory->quantity;
                $inventory->decrement('quantity', $itemData['quantity']);
                // Decrease koli in inventory to match stock out
                $inventory->decrement('koli', $itemData['koli']);
                $stockAfter = $inventory->quantity;

                StockMovement::create([
                    'item_id' => $itemData['item_id'],
                    'warehouse_id' => $request->warehouse_id,
                    'quantity' => -$itemData['quantity'],
                    'koli' => -$itemData['koli'],
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockAfter,
                    'type' => 'stock_out',
                    'description' => 'Revisi Pengeluaran Barang (Decrement)',
                    'reference_id' => $stockOutItem->id,
                    'reference_type' => 'stock_out_items',
                    'user_id' => auth()->id(),
                    'date' => $request->date,
                ]);
            }

            UserActivity::create([
                'user_id' => auth()->id(),
                'activity' => 'updated',
                'menu' => 'Stok Keluar',
                'description' => 'Memperbarui data pengeluaran barang dengan kode ' . $pengeluaranBarang->code,
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('user-agent'),
            ]);
        });

        return redirect()->route('admin.stok-keluar.pengeluaran-barang.index')->with('success', 'Data pengeluaran barang berhasil diperbarui.');
    }

    public function destroy(StockOut $pengeluaranBarang)
    {
        DB::transaction(function () use ($pengeluaranBarang) {
            foreach ($pengeluaranBarang->items as $item) {
                $inventory = Inventory::where('warehouse_id', $pengeluaranBarang->warehouse_id)
                         ->where('item_id', $item->item_id)->first();
                
                $stockBefore = $inventory->quantity;
                $inventory->increment('quantity', $item->quantity);
                // Return koli back to inventory when deleting stock out
                $inventory->increment('koli', $item->koli);
                $stockAfter = $inventory->quantity;

                StockMovement::create([
                    'item_id' => $item->item_id,
                    'warehouse_id' => $pengeluaranBarang->warehouse_id,
                    'quantity' => $item->quantity,
                    'koli' => $item->koli,
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockAfter,
                    'type' => 'stock_out',
                    'description' => 'Hapus Pengeluaran Barang',
                    'reference_id' => $item->id,
                    'reference_type' => 'stock_out_items',
                    'user_id' => auth()->id(),
                    'date' => $pengeluaranBarang->date,
                ]);
            }
            $pengeluaranBarang->delete();

            UserActivity::create([
                'user_id' => auth()->id(),
                'activity' => 'deleted',
                'menu' => 'Stok Keluar',
                'description' => 'Menghapus data pengeluaran barang dengan kode ' . $pengeluaranBarang->code,
                'ip_address' => request()->ip(),
                'user_agent' => request()->header('user-agent'),
            ]);
        });

        return redirect()->route('admin.stok-keluar.pengeluaran-barang.index')->with('success', 'Data pengeluaran barang berhasil dihapus.');
    }
}
