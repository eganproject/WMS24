<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\InboundItem;
use App\Models\InboundTransaction;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\Lane;
use App\Exports\ItemsTemplateExport;
use App\Imports\ItemsImport;
use App\Support\LocationService;
use App\Support\StockService;
use App\Support\InboundScanStatus;
use App\Support\WarehouseService;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ItemController extends Controller
{
    protected ?int $defaultCategoryId = null;
    public function index()
    {
        $categories = Category::orderBy('name')->get(['id', 'name']);
        $lanes = Lane::orderBy('code')->get(['id', 'code', 'name']);
        return view('admin.masterdata.items.index', compact('categories', 'lanes'));
    }

    public function data(Request $request)
    {
        $query = Item::with(['category', 'location.lane'])->orderBy('name');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('sku', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('location', function ($locQ) use ($search) {
                        $locQ->where('code', 'like', "%{$search}%")
                            ->orWhere('rack_code', 'like', "%{$search}%")
                            ->orWhereHas('lane', function ($laneQ) use ($search) {
                                $laneQ->where('code', 'like', "%{$search}%")
                                    ->orWhere('name', 'like', "%{$search}%");
                            });
                    });
            });
        }

        $catFilter = $request->input('category_id');
        if ($catFilter !== null && $catFilter !== '') {
            if ((int)$catFilter === 0) {
                $query->where('category_id', 0);
            } else {
                $query->where('category_id', (int)$catFilter);
            }
        }

        $recordsTotal = Item::count();
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $data = $query->get()->map(function ($i) {
            $location = $i->location;
            $lane = $location?->lane;
            $address = $location?->code ?? ($i->address ?? '');
            return [
                'id' => $i->id,
                'sku' => $i->sku,
                'name' => $i->name,
                'category' => $i->category?->name ?? '-',
                'category_id' => $i->category_id,
                'address' => $address,
                'lane_id' => $lane?->id,
                'lane_code' => $lane?->code ?? '',
                'rack_code' => $location?->rack_code ?? '',
                'column_no' => $location?->column_no ?? '',
                'row_no' => $location?->row_no ?? '',
                'description' => $i->description ?? '',
                'safety_stock' => (int) ($i->safety_stock ?? 0),
                'koli_qty' => $i->koli_qty !== null ? (int) $i->koli_qty : null,
            ];
        });

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
            'sku' => ['required', 'string', 'max:100', 'unique:items,sku'],
            'name' => ['required', 'string', 'max:150'],
            'category_id' => ['nullable', 'integer', 'min:0', function($attr, $value, $fail) {
                if ((int)$value === 0) return;
                if (!Category::where('id', $value)->exists()) {
                    $fail('Kategori tidak valid');
                }
            }],
            'lane_id' => ['nullable', 'integer', 'exists:lanes,id'],
            'rack_code' => ['nullable', 'string', 'max:20'],
            'column_no' => ['nullable', 'integer', 'min:1'],
            'row_no' => ['nullable', 'integer', 'min:1'],
            'address' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'safety_stock' => ['nullable', 'integer', 'min:0'],
            'koli_qty' => ['nullable', 'integer', 'min:0'],
        ]);

        $catId = $request->input('category_id');
        $validated['category_id'] = ($catId === null || (int)$catId === 0) ? 0 : $catId;
        if (array_key_exists('safety_stock', $validated)) {
            $validated['safety_stock'] = max(0, (int) $validated['safety_stock']);
        }
        if (array_key_exists('koli_qty', $validated)) {
            $validated['koli_qty'] = $validated['koli_qty'] === null || $validated['koli_qty'] === ''
                ? null
                : max(0, (int) $validated['koli_qty']);
        }
        if (array_key_exists('koli_qty', $validated)) {
            $validated['koli_qty'] = $validated['koli_qty'] === null || $validated['koli_qty'] === ''
                ? null
                : max(0, (int) $validated['koli_qty']);
        }

        $this->applyLocationPayload($validated);

        DB::beginTransaction();
        try {
            $item = Item::create($validated);
            $warehouseId = WarehouseService::defaultWarehouseId();
            ItemStock::firstOrCreate(
                ['item_id' => $item->id, 'warehouse_id' => $warehouseId],
                ['stock' => 0]
            );
            DB::commit();

            return response()->json([
                'message' => 'Item berhasil dibuat',
                'item' => [
                    'id' => $item->id,
                    'sku' => $item->sku,
                    'name' => $item->name,
                    'category_id' => $item->category_id,
                    'koli_qty' => $item->koli_qty,
                ]
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal membuat item',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, Item $item)
    {
        $validated = $request->validate([
            'sku' => ['required', 'string', 'max:100', Rule::unique('items', 'sku')->ignore($item->id)],
            'name' => ['required', 'string', 'max:150'],
            'category_id' => ['nullable', 'integer', 'min:0', function($attr, $value, $fail) {
                if ((int)$value === 0) return;
                if (!Category::where('id', $value)->exists()) {
                    $fail('Kategori tidak valid');
                }
            }],
            'lane_id' => ['nullable', 'integer', 'exists:lanes,id'],
            'rack_code' => ['nullable', 'string', 'max:20'],
            'column_no' => ['nullable', 'integer', 'min:1'],
            'row_no' => ['nullable', 'integer', 'min:1'],
            'address' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'safety_stock' => ['nullable', 'integer', 'min:0'],
            'koli_qty' => ['nullable', 'integer', 'min:0'],
        ]);

        $catId = $request->input('category_id');
        $validated['category_id'] = ($catId === null || (int)$catId === 0) ? 0 : $catId;
        if (array_key_exists('safety_stock', $validated)) {
            $validated['safety_stock'] = max(0, (int) $validated['safety_stock']);
        }

        $this->applyLocationPayload($validated);

        DB::beginTransaction();
        try {
            $item->update($validated);
            DB::commit();

            return response()->json([
                'message' => 'Item berhasil diperbarui',
                'item' => [
                    'id' => $item->id,
                    'sku' => $item->sku,
                    'name' => $item->name,
                    'category_id' => $item->category_id,
                    'koli_qty' => $item->koli_qty,
                ]
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal memperbarui item',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Item $item)
    {
        DB::beginTransaction();
        try {
            $item->delete();
            DB::commit();
            return response()->json(['message' => 'Item berhasil dihapus']);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menghapus item',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'],
        ]);

        $created = 0;
        $updated = 0;
        DB::beginTransaction();
        try {
            $import = new ItemsImport();
            Excel::import($import, $request->file('file'));
            $created = $import->created;
            $updated = $import->updated;

            $initialStocksByWarehouse = $import->initialStocksByWarehouse ?? [];
            if (empty($initialStocksByWarehouse)) {
                $initialStocks = $import->initialStocks ?? [];
                if (!empty($initialStocks)) {
                    $initialStocksByWarehouse = [
                        WarehouseService::defaultWarehouseId() => $initialStocks,
                    ];
                }
            }

            foreach ($initialStocksByWarehouse as $warehouseId => $stocks) {
                if (empty($stocks)) {
                    continue;
                }
                $code = 'INB-OPN-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));
                $transactedAt = now();

                $tx = InboundTransaction::create([
                    'code' => $code,
                    'type' => 'opening',
                    'ref_no' => null,
                    'note' => 'Saldo awal dari import items',
                    'warehouse_id' => (int) $warehouseId,
                    'transacted_at' => $transactedAt,
                    'created_by' => auth()->id(),
                    'status' => InboundScanStatus::COMPLETED,
                    'approved_at' => $transactedAt,
                    'approved_by' => auth()->id(),
                ]);

                foreach ($stocks as $itemId => $qty) {
                    $qty = (int) $qty;
                    if ($qty <= 0) {
                        continue;
                    }
                    InboundItem::create([
                        'inbound_transaction_id' => $tx->id,
                        'item_id' => $itemId,
                        'qty' => $qty,
                        'koli' => null,
                        'note' => 'Saldo awal import',
                    ]);

                    StockService::mutate([
                        'item_id' => $itemId,
                        'warehouse_id' => (int) $warehouseId,
                        'direction' => 'in',
                        'qty' => $qty,
                        'source_type' => 'inbound',
                        'source_subtype' => 'opening',
                        'source_id' => $tx->id,
                        'source_code' => $tx->code,
                        'note' => 'Saldo awal import',
                        'occurred_at' => $transactedAt,
                        'created_by' => auth()->id(),
                    ]);
                }
            }
            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal import: '.$e->getMessage()], 500);
        }

        return response()->json([
            'message' => 'Import selesai',
            'created' => $created,
            'updated' => $updated,
        ]);
    }

    public function template()
    {
        $filename = 'items-template-'.now()->format('YmdHis').'.xlsx';
        return Excel::download(new ItemsTemplateExport(), $filename);
    }

    protected function findOrCreateCategory(string $name, int $parentId = 0): ?Category
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            return null;
        }
        $normalized = mb_strtolower($trimmed);
        $category = Category::whereRaw('LOWER(name) = ?', [$normalized])->first();
        if ($category) {
            if ($parentId !== 0 && $category->parent_id !== $parentId) {
                $category->parent_id = $parentId;
                $category->save();
            }
            return $category;
        }
        return Category::create([
            'name' => $trimmed,
            'parent_id' => $parentId,
        ]);
    }

    protected function getDefaultCategoryId(): int
    {
        if ($this->defaultCategoryId !== null) {
            return $this->defaultCategoryId;
        }
        $default = Category::firstOrCreate(
            ['name' => 'Tanpa Kategori'],
            ['parent_id' => 0]
        );
        $this->defaultCategoryId = $default->id;
        return $this->defaultCategoryId;
    }

    private function applyLocationPayload(array &$validated): void
    {
        $laneId = $validated['lane_id'] ?? null;
        $rack = $validated['rack_code'] ?? null;
        $col = $validated['column_no'] ?? null;
        $row = $validated['row_no'] ?? null;

        $hasLaneParts = ($laneId !== null && $laneId !== '') ||
            ($rack !== null && $rack !== '') ||
            ($col !== null && $col !== '') ||
            ($row !== null && $row !== '');

        if ($hasLaneParts) {
            if ($laneId === null || $laneId === '' || $rack === null || $rack === '' || $col === null || $col === '' || $row === null || $row === '') {
                throw ValidationException::withMessages([
                    'rack_code' => 'Lengkapi lane, rack, kolom, dan baris.',
                ]);
            }

            $location = LocationService::resolveLocationFromParts((int) $laneId, (string) $rack, (int) $col, (int) $row);
            if ($location) {
                $validated['location_id'] = $location->id;
                $validated['address'] = $location->code;
                return;
            }
        }

        if (array_key_exists('address', $validated)) {
            $address = (string) ($validated['address'] ?? '');
            $location = LocationService::resolveLocation($address);
            if ($location) {
                $validated['location_id'] = $location->id;
                $validated['address'] = $location->code;
            } else {
                $validated['location_id'] = null;
            }
        }
    }
}
