<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\InboundItem;
use App\Models\InboundTransaction;
use App\Models\Item;
use App\Models\ItemBarcode;
use App\Models\ItemBarcodeScanMiss;
use App\Models\ItemStock;
use App\Models\Area;
use App\Exports\ItemBarcodeTemplateExport;
use App\Exports\ItemBundleTemplateExport;
use App\Exports\ItemsTemplateExport;
use App\Imports\ItemBarcodesImport;
use App\Imports\ItemBundleImport;
use App\Imports\ItemsImport;
use App\Models\ItemBundleComponent;
use App\Support\BundleService;
use App\Support\ItemBarcodeResolver;
use App\Support\ItemQrCodeService;
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
        $areas = Area::orderBy('code')->get(['id', 'code', 'name']);
        $componentItems = Item::query()
            ->where('item_type', Item::TYPE_SINGLE)
            ->orderBy('sku')
            ->get(['id', 'sku', 'name']);

        return view('admin.masterdata.items.index', compact('categories', 'areas', 'componentItems'));
    }

    public function data(Request $request)
    {
        $query = Item::with(['category', 'location.area', 'area', 'barcodes', 'bundleComponents.component'])->orderBy('name');

        $statusFilter = (string) $request->input('status', 'active');
        if ($statusFilter === Item::STATUS_INACTIVE) {
            $query->where('status', Item::STATUS_INACTIVE);
        } elseif ($statusFilter !== 'all') {
            $query->where('status', Item::STATUS_ACTIVE);
        }

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $exact = $this->isExactSearch($request);
            $query->where(function ($q) use ($search, $exact) {
                $this->applyTextSearch($q, 'sku', $search, $exact);
                $this->applyTextSearch($q, 'name', $search, $exact, 'or');
                $this->applyTextSearch($q, 'address', $search, $exact, 'or');
                $this->applyTextSearch($q, 'description', $search, $exact, 'or');
                $q->orWhereHas('area', function ($areaQ) use ($search, $exact) {
                    $this->applyTextSearch($areaQ, 'code', $search, $exact);
                    $this->applyTextSearch($areaQ, 'name', $search, $exact, 'or');
                })->orWhereHas('location', function ($locQ) use ($search, $exact) {
                    $this->applyTextSearch($locQ, 'code', $search, $exact);
                    $this->applyTextSearch($locQ, 'rack_code', $search, $exact, 'or');
                    $locQ->orWhereHas('area', function ($areaQ) use ($search, $exact) {
                        $this->applyTextSearch($areaQ, 'code', $search, $exact);
                        $this->applyTextSearch($areaQ, 'name', $search, $exact, 'or');
                    });
                })->orWhereHas('barcodes', function ($barcodeQ) use ($search, $exact) {
                    $this->applyTextSearch($barcodeQ, 'barcode_value', $search, $exact);
                    $this->applyTextSearch($barcodeQ, 'source_name', $search, $exact, 'or');
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

        $recordsTotalQuery = Item::query();
        if ($statusFilter === Item::STATUS_INACTIVE) {
            $recordsTotalQuery->where('status', Item::STATUS_INACTIVE);
        } elseif ($statusFilter !== 'all') {
            $recordsTotalQuery->where('status', Item::STATUS_ACTIVE);
        }
        $recordsTotal = $recordsTotalQuery->count();
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $data = $query->get()->map(function ($i) {
            $location = $i->location;
            $area = $i->resolvedArea();
            $address = $i->resolvedAddress();
            return [
                'id' => $i->id,
                'sku' => $i->sku,
                'name' => $i->name,
                'item_type' => $i->item_type ?: Item::TYPE_SINGLE,
                'type_label' => $i->isBundle() ? 'Bundle' : 'Single',
                'status' => $i->status ?: Item::STATUS_ACTIVE,
                'status_label' => ($i->status ?: Item::STATUS_ACTIVE) === Item::STATUS_ACTIVE ? 'Aktif' : 'Nonaktif',
                'category' => $i->category?->name ?? '-',
                'category_id' => $i->category_id,
                'address' => $address,
                'area_id' => $area?->id,
                'area_code' => $area?->code ?? '',
                'rack_code' => $location?->rack_code ?? '',
                'column_no' => $location?->column_no ?? '',
                'row_no' => $location?->row_no ?? '',
                'description' => $i->description ?? '',
                'safety_stock' => (int) ($i->safety_stock ?? 0),
                'koli_qty' => $i->koli_qty !== null ? (int) $i->koli_qty : null,
                'bundle_summary' => BundleService::summarize($i),
                'bundle_components' => $i->bundleComponents->map(fn ($row) => [
                    'component_item_id' => (int) $row->component_item_id,
                    'required_qty' => (int) $row->required_qty,
                    'component_sku' => $row->component?->sku,
                    'component_name' => $row->component?->name,
                ])->values()->all(),
                'external_barcodes' => $i->barcodes->map(fn (ItemBarcode $row) => [
                    'barcode_value' => $row->barcode_value,
                    'source_name' => $row->source_name,
                    'note' => $row->note,
                ])->values()->all(),
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
            'item_type' => ['required', Rule::in([Item::TYPE_SINGLE, Item::TYPE_BUNDLE])],
            'status' => ['nullable', Rule::in([Item::STATUS_ACTIVE, Item::STATUS_INACTIVE])],
            'category_id' => ['nullable', 'integer', 'min:0', function($attr, $value, $fail) {
                if ((int)$value === 0) return;
                if (!Category::where('id', $value)->exists()) {
                    $fail('Kategori tidak valid');
                }
            }],
            'area_id' => ['nullable', 'integer', 'exists:areas,id'],
            'rack_code' => ['nullable', 'string', 'max:20'],
            'column_no' => ['nullable', 'integer', 'min:1'],
            'row_no' => ['nullable', 'integer', 'min:1'],
            'address' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'safety_stock' => ['nullable', 'integer', 'min:0'],
            'koli_qty' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'bundle_components' => ['nullable', 'array'],
            'bundle_components.*.component_item_id' => ['nullable', 'integer', 'exists:items,id'],
            'bundle_components.*.required_qty' => ['nullable', 'integer', 'min:1'],
            'external_barcodes' => ['nullable', 'array'],
            'external_barcodes.*.barcode_value' => ['nullable', 'string'],
            'external_barcodes.*.source_name' => ['nullable', 'string', 'max:100'],
            'external_barcodes.*.note' => ['nullable', 'string'],
        ]);
        $this->assertSkuDoesNotCollideWithBarcodeAlias($validated['sku']);

        $catId = $request->input('category_id');
        $validated['category_id'] = ($catId === null || (int)$catId === 0) ? 0 : $catId;
        $validated['status'] = $validated['status'] ?? Item::STATUS_ACTIVE;
        if (array_key_exists('safety_stock', $validated)) {
            $validated['safety_stock'] = max(0, (int) $validated['safety_stock']);
        }
        if (array_key_exists('koli_qty', $validated)) {
            $validated['koli_qty'] = $validated['koli_qty'] === null || $validated['koli_qty'] === ''
                ? null
                : max(0, (int) $validated['koli_qty']);
        }

        $bundleComponents = $validated['bundle_components'] ?? [];
        $externalBarcodes = $validated['external_barcodes'] ?? [];
        unset($validated['bundle_components']);
        unset($validated['external_barcodes']);

        if (($validated['item_type'] ?? Item::TYPE_SINGLE) === Item::TYPE_BUNDLE) {
            $this->normalizeBundlePayload($validated);
            BundleService::validateComponents(null, $bundleComponents);
            $externalBarcodes = [];
        } else {
            $this->applyLocationPayload($validated);
            $externalBarcodes = $this->normalizeBarcodeRows($externalBarcodes, $validated['sku']);
        }

        DB::beginTransaction();
        try {
            $item = Item::create($validated);
            BundleService::syncComponents($item, $bundleComponents);
            $this->syncExternalBarcodes($item, $externalBarcodes);

            if ($item->isSingle()) {
                $warehouseId = WarehouseService::defaultWarehouseId();
                ItemStock::firstOrCreate(
                    ['item_id' => $item->id, 'warehouse_id' => $warehouseId],
                    ['stock' => 0]
                );
            }
            DB::commit();

            return response()->json([
                'message' => 'Item berhasil dibuat',
                'item' => [
                    'id' => $item->id,
                    'sku' => $item->sku,
                    'name' => $item->name,
                    'item_type' => $item->item_type,
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
            'item_type' => ['required', Rule::in([Item::TYPE_SINGLE, Item::TYPE_BUNDLE])],
            'status' => ['nullable', Rule::in([Item::STATUS_ACTIVE, Item::STATUS_INACTIVE])],
            'category_id' => ['nullable', 'integer', 'min:0', function($attr, $value, $fail) {
                if ((int)$value === 0) return;
                if (!Category::where('id', $value)->exists()) {
                    $fail('Kategori tidak valid');
                }
            }],
            'area_id' => ['nullable', 'integer', 'exists:areas,id'],
            'rack_code' => ['nullable', 'string', 'max:20'],
            'column_no' => ['nullable', 'integer', 'min:1'],
            'row_no' => ['nullable', 'integer', 'min:1'],
            'address' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'safety_stock' => ['nullable', 'integer', 'min:0'],
            'koli_qty' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'bundle_components' => ['nullable', 'array'],
            'bundle_components.*.component_item_id' => ['nullable', 'integer', 'exists:items,id'],
            'bundle_components.*.required_qty' => ['nullable', 'integer', 'min:1'],
            'external_barcodes' => ['nullable', 'array'],
            'external_barcodes.*.barcode_value' => ['nullable', 'string'],
            'external_barcodes.*.source_name' => ['nullable', 'string', 'max:100'],
            'external_barcodes.*.note' => ['nullable', 'string'],
        ]);
        $this->assertSkuDoesNotCollideWithBarcodeAlias($validated['sku'], $item);

        $catId = $request->input('category_id');
        $validated['category_id'] = ($catId === null || (int)$catId === 0) ? 0 : $catId;
        $validated['status'] = $validated['status'] ?? Item::STATUS_ACTIVE;
        if (array_key_exists('safety_stock', $validated)) {
            $validated['safety_stock'] = max(0, (int) $validated['safety_stock']);
        }
        if (array_key_exists('koli_qty', $validated)) {
            $validated['koli_qty'] = $validated['koli_qty'] === null || $validated['koli_qty'] === ''
                ? null
                : max(0, (int) $validated['koli_qty']);
        }

        $bundleComponents = $validated['bundle_components'] ?? [];
        $externalBarcodes = $validated['external_barcodes'] ?? [];
        unset($validated['bundle_components']);
        unset($validated['external_barcodes']);

        $switchingToBundle = $item->isSingle() && ($validated['item_type'] ?? Item::TYPE_SINGLE) === Item::TYPE_BUNDLE;
        $switchingToSingle = $item->isBundle() && ($validated['item_type'] ?? Item::TYPE_SINGLE) === Item::TYPE_SINGLE;

        if (($validated['item_type'] ?? Item::TYPE_SINGLE) === Item::TYPE_BUNDLE) {
            if ($switchingToBundle && ItemStock::query()->where('item_id', $item->id)->where('stock', '>', 0)->exists()) {
                throw ValidationException::withMessages([
                    'item_type' => 'Item tidak bisa diubah menjadi bundle selama masih memiliki stok fisik.',
                ]);
            }

            $this->normalizeBundlePayload($validated);
            BundleService::validateComponents($item, $bundleComponents);
            $externalBarcodes = [];
        } else {
            $this->applyLocationPayload($validated);
            $externalBarcodes = $this->normalizeBarcodeRows($externalBarcodes, $validated['sku'], $item);
        }

        DB::beginTransaction();
        try {
            $item->update($validated);
            BundleService::syncComponents($item->fresh(), $bundleComponents);
            $this->syncExternalBarcodes($item->fresh(), $externalBarcodes);

            if ($switchingToSingle) {
                ItemStock::firstOrCreate(
                    ['item_id' => $item->id, 'warehouse_id' => WarehouseService::defaultWarehouseId()],
                    ['stock' => 0]
                );
            }

            if (($validated['item_type'] ?? Item::TYPE_SINGLE) === Item::TYPE_BUNDLE) {
                ItemStock::query()->where('item_id', $item->id)->update(['safety_stock' => null]);
            }
            DB::commit();

            return response()->json([
                'message' => 'Item berhasil diperbarui',
                'item' => [
                    'id' => $item->id,
                    'sku' => $item->sku,
                    'name' => $item->name,
                    'item_type' => $item->item_type,
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

    public function barcodeTemplate()
    {
        $filename = 'item-barcode-template-'.now()->format('YmdHis').'.xlsx';
        return Excel::download(new ItemBarcodeTemplateExport(), $filename);
    }

    public function barcodeImport(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'],
        ]);

        DB::beginTransaction();
        try {
            $import = new ItemBarcodesImport();
            Excel::import($import, $request->file('file'));
            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal import barcode alias: '.$e->getMessage()], 500);
        }

        return response()->json([
            'message' => 'Import barcode alias selesai',
            'created' => $import->created,
            'updated' => $import->updated,
        ]);
    }

    public function barcodeMissesData(Request $request)
    {
        $query = ItemBarcodeScanMiss::query()
            ->with(['creator:id,name', 'resolvedItem:id,sku,name'])
            ->orderByRaw('resolved_at is not null')
            ->orderByDesc('last_scanned_at');

        $status = trim((string) $request->input('status', 'open'));
        if ($status === 'resolved') {
            $query->whereNotNull('resolved_at');
        } elseif ($status !== 'all') {
            $query->whereNull('resolved_at');
        }

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $exact = $this->isExactSearch($request);
            $query->where(function ($q) use ($search, $exact) {
                $this->applyTextSearch($q, 'scan_code', $search, $exact);
                $this->applyTextSearch($q, 'context', $search, $exact, 'or');
                $this->applyTextSearch($q, 'source_code', $search, $exact, 'or');
            });
        }

        $recordsTotal = ItemBarcodeScanMiss::count();
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $data = $query->get()->map(function (ItemBarcodeScanMiss $row) {
            return [
                'id' => $row->id,
                'context' => $this->barcodeMissContextLabel((string) $row->context),
                'raw_context' => $row->context,
                'scan_code' => $row->scan_code,
                'source_code' => $row->source_code ?? '-',
                'scan_count' => (int) $row->scan_count,
                'last_scanned_at' => $row->last_scanned_at?->format('Y-m-d H:i') ?? '-',
                'created_by' => $row->creator?->name ?? '-',
                'resolved_item' => $row->resolvedItem
                    ? trim($row->resolvedItem->sku.' - '.$row->resolvedItem->name)
                    : '-',
                'resolved_at' => $row->resolved_at?->format('Y-m-d H:i') ?? '-',
                'is_resolved' => $row->resolved_at !== null,
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function resolveBarcodeMiss(Request $request, string $miss)
    {
        $miss = ItemBarcodeScanMiss::query()->findOrFail((int) $miss);

        $validated = $request->validate([
            'item_id' => ['required', 'integer', 'exists:items,id'],
            'source_name' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string'],
        ]);

        if ($miss->resolved_at !== null) {
            return response()->json(['message' => 'Barcode tidak dikenal ini sudah diselesaikan.'], 422);
        }

        $item = Item::query()->findOrFail((int) $validated['item_id']);
        if (!$item->isSingle()) {
            return response()->json(['message' => 'Barcode alias hanya bisa dihubungkan ke item stok fisik.'], 422);
        }

        DB::beginTransaction();
        try {
            $barcodeValue = trim((string) $miss->scan_code);
            $normalized = ItemBarcodeResolver::normalize($barcodeValue);
            if ($normalized === '') {
                throw ValidationException::withMessages([
                    'item_id' => 'Barcode tidak valid.',
                ]);
            }

            if ($normalized === ItemBarcodeResolver::normalize((string) $item->sku)) {
                throw ValidationException::withMessages([
                    'item_id' => 'Barcode eksternal tidak boleh sama dengan SKU item.',
                ]);
            }

            $hash = ItemBarcodeResolver::hash($normalized);
            $skuConflict = Item::query()
                ->whereRaw('LOWER(sku) = ?', [$normalized])
                ->where('id', '!=', $item->id)
                ->exists();
            if ($skuConflict) {
                throw ValidationException::withMessages([
                    'item_id' => 'Barcode bentrok dengan SKU item lain.',
                ]);
            }

            $existingBarcode = ItemBarcode::query()
                ->where('normalized_hash', $hash)
                ->first();

            if ($existingBarcode && (int) $existingBarcode->item_id !== (int) $item->id) {
                throw ValidationException::withMessages([
                    'item_id' => 'Barcode sudah digunakan item lain.',
                ]);
            }

            if (!$existingBarcode) {
                $item->barcodes()->create([
                    'barcode_value' => $barcodeValue,
                    'normalized_barcode' => $normalized,
                    'normalized_hash' => $hash,
                    'source_name' => $this->nullableTrim($validated['source_name'] ?? null) ?? $this->barcodeMissContextLabel((string) $miss->context),
                    'note' => $this->nullableTrim($validated['note'] ?? null) ?? 'Dari log barcode tidak dikenal',
                    'is_active' => true,
                ]);
            }
            $miss->resolved_item_id = $item->id;
            $miss->resolved_by = auth()->id();
            $miss->resolved_at = now();
            $miss->save();

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal menghubungkan barcode: '.$e->getMessage()], 500);
        }

        return response()->json([
            'message' => 'Barcode berhasil dihubungkan ke item.',
        ]);
    }

    public function bundleImport(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'],
        ]);

        $processed = 0;
        DB::beginTransaction();
        try {
            $import = new ItemBundleImport();
            Excel::import($import, $request->file('file'));

            foreach ($import->groups as $group) {
                BundleService::syncComponents($group['bundle'], $group['components']);
                $processed++;
            }

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal import bundle: '.$e->getMessage()], 500);
        }

        return response()->json([
            'message' => 'Import bundle selesai',
            'processed' => $processed,
        ]);
    }

    public function bundleTemplate()
    {
        $filename = 'bundle-template-'.now()->format('YmdHis').'.xlsx';
        return Excel::download(new ItemBundleTemplateExport(), $filename);
    }

    public function template()
    {
        $filename = 'items-template-'.now()->format('YmdHis').'.xlsx';
        return Excel::download(new ItemsTemplateExport(), $filename);
    }

    public function qrCode(Request $request, int $item)
    {
        $qrCodeService = app(ItemQrCodeService::class);
        $item = Item::query()->findOrFail($item);
        $download = $request->boolean('download');

        return response(
            $qrCodeService->pngForItem($item),
            200,
            [
                'Content-Type' => 'image/png',
                'Content-Disposition' => ($download ? 'attachment' : 'inline').'; filename="'.$qrCodeService->downloadFilename($item).'"',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
            ]
        );
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
        $areaId = $validated['area_id'] ?? null;
        $rack = trim((string) ($validated['rack_code'] ?? ''));
        $col = $validated['column_no'] ?? null;
        $row = $validated['row_no'] ?? null;

        $hasDetailedParts = $rack !== '' ||
            ($col !== null && $col !== '') ||
            ($row !== null && $row !== '');

        if ($hasDetailedParts) {
            if ($areaId === null || $areaId === '' || $rack === '' || $col === null || $col === '' || $row === null || $row === '') {
                throw ValidationException::withMessages([
                    'rack_code' => 'Lengkapi area, rack, kolom, dan baris.',
                ]);
            }

            $location = LocationService::resolveLocationFromParts((int) $areaId, (string) $rack, (int) $col, (int) $row);
            if ($location) {
                $validated['area_id'] = $location->area_id;
                $validated['location_id'] = $location->id;
                $validated['address'] = $location->code;
                return;
            }
        }

        if ($areaId !== null && $areaId !== '') {
            $area = Area::find((int) $areaId);
            if ($area) {
                $validated['area_id'] = $area->id;
                $validated['location_id'] = null;
                $validated['address'] = $area->code;
                return;
            }
        }

        if (array_key_exists('address', $validated)) {
            $address = trim((string) ($validated['address'] ?? ''));
            $location = LocationService::resolveLocation($address);
            if ($location) {
                $validated['area_id'] = $location->area_id;
                $validated['location_id'] = $location->id;
                $validated['address'] = $location->code;
                return;
            }

            $area = LocationService::resolveArea($address);
            if ($area) {
                $validated['area_id'] = $area->id;
                $validated['location_id'] = null;
                $validated['address'] = $area->code;
                return;
            }

            $validated['area_id'] = null;
            $validated['location_id'] = null;
            $validated['address'] = $address;
        }
    }

    private function normalizeBundlePayload(array &$validated): void
    {
        $validated['area_id'] = null;
        $validated['location_id'] = null;
        $validated['address'] = null;
        $validated['safety_stock'] = 0;
        $validated['koli_qty'] = null;
        unset($validated['rack_code'], $validated['column_no'], $validated['row_no']);
    }

    private function assertSkuDoesNotCollideWithBarcodeAlias(string $sku, ?Item $item = null): void
    {
        $normalized = ItemBarcodeResolver::normalize($sku);
        if ($normalized === '') {
            return;
        }

        $exists = ItemBarcode::query()
            ->where('normalized_hash', ItemBarcodeResolver::hash($normalized))
            ->when($item, fn ($q) => $q->where('item_id', '!=', $item->id))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'sku' => 'SKU bentrok dengan barcode eksternal item lain.',
            ]);
        }
    }

    /**
     * @return array<int,array{barcode_value:string,normalized_barcode:string,normalized_hash:string,source_name:?string,note:?string,is_active:bool}>
     */
    private function normalizeBarcodeRows(array $rows, string $itemSku, ?Item $item = null): array
    {
        $normalizedSku = ItemBarcodeResolver::normalize($itemSku);
        $normalizedRows = [];
        $seen = [];

        foreach ($rows as $idx => $row) {
            $value = trim((string) ($row['barcode_value'] ?? ''));
            if ($value === '') {
                continue;
            }

            $normalized = ItemBarcodeResolver::normalize($value);
            if ($normalized === $normalizedSku) {
                throw ValidationException::withMessages([
                    "external_barcodes.{$idx}.barcode_value" => 'Barcode eksternal tidak boleh sama dengan SKU item.',
                ]);
            }

            $hash = ItemBarcodeResolver::hash($normalized);
            if (isset($seen[$hash])) {
                throw ValidationException::withMessages([
                    "external_barcodes.{$idx}.barcode_value" => 'Barcode eksternal tidak boleh duplikat pada item yang sama.',
                ]);
            }
            $seen[$hash] = true;

            $skuConflict = Item::query()
                ->whereRaw('LOWER(sku) = ?', [$normalized])
                ->when($item, fn ($q) => $q->where('id', '!=', $item->id))
                ->exists();
            if ($skuConflict) {
                throw ValidationException::withMessages([
                    "external_barcodes.{$idx}.barcode_value" => 'Barcode eksternal bentrok dengan SKU item lain.',
                ]);
            }

            $barcodeConflict = ItemBarcode::query()
                ->where('normalized_hash', $hash)
                ->when($item, fn ($q) => $q->where('item_id', '!=', $item->id))
                ->exists();
            if ($barcodeConflict) {
                throw ValidationException::withMessages([
                    "external_barcodes.{$idx}.barcode_value" => 'Barcode eksternal sudah digunakan item lain.',
                ]);
            }

            $normalizedRows[] = [
                'barcode_value' => $value,
                'normalized_barcode' => $normalized,
                'normalized_hash' => $hash,
                'source_name' => $this->nullableTrim($row['source_name'] ?? null),
                'note' => $this->nullableTrim($row['note'] ?? null),
                'is_active' => true,
            ];
        }

        return $normalizedRows;
    }

    private function syncExternalBarcodes(Item $item, array $rows): void
    {
        ItemBarcode::query()->where('item_id', $item->id)->delete();

        if ($item->isBundle()) {
            return;
        }

        foreach ($rows as $row) {
            $item->barcodes()->create($row);
        }
    }

    private function nullableTrim(mixed $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));
        return $trimmed === '' ? null : $trimmed;
    }

    private function barcodeMissContextLabel(string $context): string
    {
        return match ($context) {
            'qc_scan_sku' => 'QC Scan SKU',
            'stock_opname' => 'Stock Opname',
            default => $context,
        };
    }
}
