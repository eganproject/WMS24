<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OutboundItem;
use App\Models\OutboundTransaction;
use App\Models\Item;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Models\StockMutation;
use App\Imports\OutboundReturnsImport;
use App\Support\BundleService;
use App\Support\OutboundKoliExpectation;
use App\Support\StockService;
use App\Support\Permission;
use App\Support\WarehouseService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class OutboundController extends Controller
{
    public function pickers()
    {
        return $this->index('picker', 'Outbound - Picker', 'pickers');
    }

    public function manuals()
    {
        return $this->index('manual', 'Outbound - Manual', 'manuals');
    }

    public function returns()
    {
        return $this->index('return', 'Outbound - Retur', 'returns');
    }

    public function pickersData(Request $request)
    {
        return $this->data($request, 'picker');
    }

    public function manualsData(Request $request)
    {
        return $this->data($request, 'manual');
    }

    public function returnsData(Request $request)
    {
        return $this->data($request, 'return');
    }

    public function pickersStore(Request $request)
    {
        return $this->store($request, 'picker');
    }

    public function manualsStore(Request $request)
    {
        return $this->store($request, 'manual');
    }

    public function returnsStore(Request $request)
    {
        return $this->store($request, 'return');
    }

    public function pickersShow(int $id)
    {
        return $this->show('picker', $id);
    }

    public function manualsShow(int $id)
    {
        return $this->show('manual', $id);
    }

    public function returnsShow(int $id)
    {
        return $this->show('return', $id);
    }

    public function pickersDetail(int $id)
    {
        return $this->detail('picker', 'Outbound - Picker', 'pickers', $id);
    }

    public function manualsDetail(int $id)
    {
        return $this->detail('manual', 'Outbound - Manual', 'manuals', $id);
    }

    public function returnsDetail(int $id)
    {
        return $this->detail('return', 'Outbound - Retur', 'returns', $id);
    }

    public function pickersUpdate(Request $request, int $id)
    {
        return $this->update($request, 'picker', $id);
    }

    public function manualsUpdate(Request $request, int $id)
    {
        return $this->update($request, 'manual', $id);
    }

    public function returnsUpdate(Request $request, int $id)
    {
        return $this->update($request, 'return', $id);
    }

    public function pickersDestroy(int $id)
    {
        return $this->destroy('picker', $id);
    }

    public function manualsDestroy(int $id)
    {
        return $this->destroy('manual', $id);
    }

    public function returnsDestroy(int $id)
    {
        return $this->destroy('return', $id);
    }

    public function pickersApprove(int $id)
    {
        return $this->approve('picker', $id);
    }

    public function manualsApprove(int $id)
    {
        return $this->approve('manual', $id);
    }

    public function returnsApprove(int $id)
    {
        return $this->approve('return', $id);
    }

    public function manualsImport(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'],
        ]);

        $import = new OutboundReturnsImport(false);
        DB::beginTransaction();
        try {
            Excel::import($import, $request->file('file'));
            $groups = $import->groups ?? [];
            if (empty($groups)) {
                throw ValidationException::withMessages([
                    'file' => 'Tidak ada data valid untuk diimport',
                ]);
            }

            $createdTx = 0;
            $createdItems = 0;
            foreach ($groups as $group) {
                $warehouseId = (int) ($group['warehouse_id'] ?? 0);
                if ($warehouseId <= 0) {
                    $warehouseId = WarehouseService::displayWarehouseId();
                }
                $transactedAt = now();
                if (!empty($group['transacted_at'])) {
                    try {
                        $transactedAt = Carbon::parse($group['transacted_at']);
                    } catch (\Throwable $e) {
                        throw ValidationException::withMessages([
                            'file' => 'Format transacted_at tidak valid: '.$group['transacted_at'],
                        ]);
                    }
                }

                $tx = OutboundTransaction::create([
                    'code' => $this->generateCode('OUT-MNL'),
                    'type' => 'manual',
                    'ref_no' => $group['ref_no'] ?? null,
                    'supplier_id' => null,
                    'note' => $group['note'] ?? null,
                    'warehouse_id' => $warehouseId,
                    'transacted_at' => $transactedAt,
                    'created_by' => auth()->id(),
                    'status' => 'pending',
                ]);
                $createdTx++;

                foreach ($group['items'] as $row) {
                    OutboundItem::create([
                        'outbound_transaction_id' => $tx->id,
                        'item_id' => $row['item_id'],
                        'qty' => $row['qty'],
                        'note' => $row['note'] ?? null,
                    ]);
                    $createdItems++;
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Import outbound manual berhasil',
                'transactions' => $createdTx,
                'items' => $createdItems,
            ]);
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal import outbound manual',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function returnsImport(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'],
        ]);

        $import = new OutboundReturnsImport(true);
        $warehouseId = WarehouseService::displayWarehouseId();
        DB::beginTransaction();
        try {
            Excel::import($import, $request->file('file'));
            $groups = $import->groups ?? [];
            if (empty($groups)) {
                throw ValidationException::withMessages([
                    'file' => 'Tidak ada data valid untuk diimport',
                ]);
            }

            $createdTx = 0;
            $createdItems = 0;
            foreach ($groups as $group) {
                $transactedAt = now();
                if (!empty($group['transacted_at'])) {
                    try {
                        $transactedAt = Carbon::parse($group['transacted_at']);
                    } catch (\Throwable $e) {
                        throw ValidationException::withMessages([
                            'file' => 'Format transacted_at tidak valid: '.$group['transacted_at'],
                        ]);
                    }
                }

                $tx = OutboundTransaction::create([
                    'code' => $this->generateCode('OUT-RET'),
                    'type' => 'return',
                    'ref_no' => $group['ref_no'] ?? null,
                    'supplier_id' => $group['supplier_id'] ?? null,
                    'note' => $group['note'] ?? null,
                    'warehouse_id' => $warehouseId,
                    'transacted_at' => $transactedAt,
                    'created_by' => auth()->id(),
                    'status' => 'pending',
                ]);
                $createdTx++;

                foreach ($group['items'] as $row) {
                    OutboundItem::create([
                        'outbound_transaction_id' => $tx->id,
                        'item_id' => $row['item_id'],
                        'qty' => $row['qty'],
                        'note' => $row['note'] ?? null,
                    ]);
                    $createdItems++;
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Import retur outbound berhasil',
                'transactions' => $createdTx,
                'items' => $createdItems,
            ]);
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal import retur outbound',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function index(string $type, string $pageTitle, string $routeBase)
    {
        $items = Item::orderBy('name')->get(['id', 'sku', 'name', 'koli_qty', 'item_type']);
        $warehouses = Warehouse::orderBy('name')->get(['id', 'name', 'code']);
        $suppliers = $this->usesSupplier($type)
            ? Supplier::orderBy('name')->get(['id', 'name'])
            : collect();
        $baseOptions = $this->typeOptions();
        $typeOptions = ['all' => 'Semua'] + $baseOptions;
        $routeMap = [
            'picker' => [
                'store' => route('admin.outbound.pickers.store'),
                'show' => route('admin.outbound.pickers.show', ':id'),
                'update' => route('admin.outbound.pickers.update', ':id'),
                'delete' => route('admin.outbound.pickers.destroy', ':id'),
                'detail' => route('admin.outbound.pickers.detail', ':id'),
                'approve' => route('admin.outbound.pickers.approve', ':id'),
            ],
            'manual' => [
                'store' => route('admin.outbound.manuals.store'),
                'show' => route('admin.outbound.manuals.show', ':id'),
                'update' => route('admin.outbound.manuals.update', ':id'),
                'delete' => route('admin.outbound.manuals.destroy', ':id'),
                'detail' => route('admin.outbound.manuals.detail', ':id'),
                'approve' => route('admin.outbound.manuals.approve', ':id'),
            ],
            'return' => [
                'store' => route('admin.outbound.returns.store'),
                'show' => route('admin.outbound.returns.show', ':id'),
                'update' => route('admin.outbound.returns.update', ':id'),
                'delete' => route('admin.outbound.returns.destroy', ':id'),
                'detail' => route('admin.outbound.returns.detail', ':id'),
                'approve' => route('admin.outbound.returns.approve', ':id'),
            ],
        ];

        return view('admin.stock-flow.index', [
            'pageTitle' => $pageTitle,
            'dataUrl' => route("admin.outbound.{$routeBase}.data"),
            'storeUrl' => route("admin.outbound.{$routeBase}.store"),
            'showUrlTpl' => route("admin.outbound.{$routeBase}.show", ':id'),
            'updateUrlTpl' => route("admin.outbound.{$routeBase}.update", ':id'),
            'deleteUrlTpl' => route("admin.outbound.{$routeBase}.destroy", ':id'),
            'detailUrlTpl' => route("admin.outbound.{$routeBase}.detail", ':id'),
            'items' => $items,
            'warehouses' => $warehouses,
            'warehouseOptions' => $warehouses->map(fn ($w) => [
                'id' => $w->id,
                'name' => $w->name,
                'code' => $w->code,
            ])->values(),
            'defaultWarehouseId' => WarehouseService::defaultWarehouseId(),
            'displayWarehouseId' => WarehouseService::displayWarehouseId(),
            'enableWarehouseSelect' => $type === 'manual',
            'enableKoli' => $type === 'return',
            'allowKoliImport' => $type === 'return',
            'suppliers' => $suppliers,
            'supplierFlowTypes' => $this->usesSupplier($type) ? [$type] : [],
            'showSupplierColumn' => $this->usesSupplier($type),
            'supplierManageUrl' => $this->usesSupplier($type) && Permission::can(auth()->user(), 'admin.masterdata.suppliers.index')
                ? route('admin.masterdata.suppliers.index')
                : null,
            'importRequiresSupplier' => $this->usesSupplier($type),
            'typeOptions' => $typeOptions,
            'typeDefault' => $type,
            'routeMap' => $routeMap,
            'importUrl' => match ($type) {
                'return' => route('admin.outbound.returns.import'),
                'manual' => route('admin.outbound.manuals.import'),
                default => null,
            },
            'importTitle' => match ($type) {
                'return' => 'Import Retur Outbound',
                'manual' => 'Import Manual Outbound',
                default => null,
            },
        ]);
    }

    private function data(Request $request, string $type)
    {
        $allowed = array_keys($this->typeOptions());
        $filterType = $request->input('type');
        $baseType = null;
        if ($filterType === 'all') {
            $baseType = null;
        } elseif (in_array($filterType, $allowed, true)) {
            $baseType = $filterType;
        } else {
            $baseType = $type;
        }

        $query = OutboundTransaction::query()
            ->with(['items.item', 'creator', 'warehouse', 'supplier'])
            ->select([
                'outbound_transactions.id',
                'outbound_transactions.code',
                'outbound_transactions.transacted_at',
                'outbound_transactions.type',
                'outbound_transactions.ref_no',
                'outbound_transactions.supplier_id',
                'outbound_transactions.note',
                'outbound_transactions.warehouse_id',
                'outbound_transactions.status',
                'outbound_transactions.created_by',
            ])
            ->orderBy('outbound_transactions.transacted_at', 'desc');
        if ($baseType) {
            $query->where('outbound_transactions.type', $baseType);
        }

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('outbound_transactions.code', 'like', "%{$search}%")
                    ->orWhere('outbound_transactions.ref_no', 'like', "%{$search}%")
                    ->orWhereHas('supplier', function ($supplierQ) use ($search) {
                        $supplierQ->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('items.item', function ($itemQ) use ($search) {
                        $itemQ->where('sku', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    });
            });
        }

        $this->applyDateFilter($query, $request);

        $warehouseFilter = $request->input('warehouse_id');
        if ($warehouseFilter !== null && $warehouseFilter !== '' && $warehouseFilter !== 'all') {
            $query->where('outbound_transactions.warehouse_id', (int) $warehouseFilter);
        }

        $recordsTotalQuery = OutboundTransaction::query();
        if ($baseType) {
            $recordsTotalQuery->where('type', $baseType);
        }
        $recordsTotal = $recordsTotalQuery->count();
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $data = $query->get()->map(function ($row) {
            $ts = $row->transacted_at ? Carbon::parse($row->transacted_at)->format('Y-m-d H:i') : '';
            $items = $row->items ?? collect();
            $labels = $items->map(function ($it) {
                $sku = trim($it->item?->sku ?? '');
                if ($sku === '') {
                    return '';
                }
                $qty = (int) ($it->qty ?? 0);
                return sprintf('%s (%d)', $sku, $qty);
            })->filter()->values();
            $itemLabel = $labels->implode(', ');
            $totalQty = (int) $items->sum('qty');
            return [
                'id' => $row->id,
                'code' => $row->code,
                'transacted_at' => $ts,
                'submit_by' => $row->creator?->name ?? '-',
                'warehouse' => $row->warehouse?->name ?? '-',
                'warehouse_id' => $row->warehouse_id,
                'supplier' => $row->supplier?->name ?? '-',
                'item' => $itemLabel ?: '-',
                'qty' => $totalQty,
                'note' => $row->note ?? '',
                'type' => $row->type,
                'status' => $row->status ?? 'pending',
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    private function show(string $type, int $id)
    {
        $tx = OutboundTransaction::with(['items.item:id,koli_qty', 'supplier'])
            ->where('type', $type)
            ->findOrFail($id);

        return response()->json([
            'id' => $tx->id,
            'code' => $tx->code,
            'ref_no' => $tx->ref_no,
            'supplier_id' => $tx->supplier_id,
            'supplier' => $tx->supplier?->name,
            'note' => $tx->note,
            'status' => $tx->status ?? 'pending',
            'warehouse_id' => $tx->warehouse_id,
            'transacted_at' => $tx->transacted_at?->format('Y-m-d\TH:i'),
            'items' => $tx->items->map(function ($item) use ($type) {
                $qty = (int) $item->qty;
                $qtyPerKoli = (int) ($item->item?->koli_qty ?? 0);
                $koli = null;
                if ($type === 'return' && $qty > 0 && $qtyPerKoli > 0 && $qty % $qtyPerKoli === 0) {
                    $koli = (int) ($qty / $qtyPerKoli);
                }

                return [
                    'item_id' => $item->item_id,
                    'qty' => $qty,
                    'koli' => $koli,
                    'note' => $item->note ?? '',
                ];
            })->values(),
        ]);
    }

    private function detail(string $type, string $pageTitle, string $routeBase, int $id)
    {
        $tx = OutboundTransaction::with(['items.item', 'warehouse', 'supplier'])
            ->where('type', $type)
            ->findOrFail($id);

        $totalQty = $tx->items->sum('qty');

        return view('admin.stock-flow.detail', [
            'pageTitle' => $pageTitle,
            'transaction' => $tx,
            'totalQty' => $totalQty,
            'showSupplierField' => $this->usesSupplier($type),
            'warehouseLabel' => $tx->warehouse?->name,
            'backUrl' => route("admin.outbound.{$routeBase}.index"),
        ]);
    }

    private function store(Request $request, string $type)
    {
        $validated = $this->validatePayload($request, $type);

        $warehouseId = (int) ($validated['warehouse_id'] ?? 0);
        if ($type === 'manual') {
            if ($warehouseId <= 0) {
                throw ValidationException::withMessages([
                    'warehouse_id' => 'Gudang wajib dipilih',
                ]);
            }
        } else {
            $warehouseId = WarehouseService::displayWarehouseId();
        }

        $prefix = match ($type) {
            'picker' => 'OUT-PCK',
            'return' => 'OUT-RET',
            default => 'OUT-MNL',
        };

        $code = $this->generateCode($prefix);
        $transactedAt = $validated['transacted_at'] ?? now();

        DB::beginTransaction();
        try {
            $tx = OutboundTransaction::create([
                'code' => $code,
                'type' => $type,
                'ref_no' => $validated['ref_no'] ?? null,
                'supplier_id' => $validated['supplier_id'] ?? null,
                'note' => $validated['note'] ?? null,
                'warehouse_id' => $warehouseId,
                'transacted_at' => $transactedAt,
                'created_by' => auth()->id(),
                'status' => 'pending',
            ]);

            foreach ($validated['items'] as $row) {
                OutboundItem::create([
                    'outbound_transaction_id' => $tx->id,
                    'item_id' => $row['item_id'],
                    'qty' => $row['qty'],
                    'note' => $row['note'] ?? null,
                ]);
            }

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menyimpan outbound',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Outbound berhasil disimpan dan menunggu approval.',
        ]);
    }

    private function update(Request $request, string $type, int $id)
    {
        $validated = $this->validatePayload($request, $type);

        $warehouseId = (int) ($validated['warehouse_id'] ?? 0);
        if ($type === 'manual') {
            if ($warehouseId <= 0) {
                throw ValidationException::withMessages([
                    'warehouse_id' => 'Gudang wajib dipilih',
                ]);
            }
        } else {
            $warehouseId = WarehouseService::displayWarehouseId();
        }

        DB::beginTransaction();
        try {
            $tx = OutboundTransaction::where('type', $type)->findOrFail($id);
            if (($tx->status ?? 'pending') === 'approved') {
                DB::rollBack();
                return response()->json(['message' => 'Data sudah disetujui dan tidak bisa diubah'], 422);
            }

            StockService::rollbackBySource('outbound', $tx->id);
            StockMutation::where('source_type', 'outbound')->where('source_id', $tx->id)->delete();
            OutboundItem::where('outbound_transaction_id', $tx->id)->delete();

            $tx->update([
                'ref_no' => $validated['ref_no'] ?? null,
                'supplier_id' => $validated['supplier_id'] ?? null,
                'note' => $validated['note'] ?? null,
                'warehouse_id' => $warehouseId,
                'transacted_at' => $validated['transacted_at'] ?? $tx->transacted_at,
            ]);

            foreach ($validated['items'] as $row) {
                OutboundItem::create([
                    'outbound_transaction_id' => $tx->id,
                    'item_id' => $row['item_id'],
                    'qty' => $row['qty'],
                    'note' => $row['note'] ?? null,
                ]);
            }

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal memperbarui outbound',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Outbound berhasil diperbarui',
        ]);
    }

    private function destroy(string $type, int $id)
    {
        DB::beginTransaction();
        try {
            $tx = OutboundTransaction::where('type', $type)->findOrFail($id);
            if (($tx->status ?? 'pending') === 'approved') {
                DB::rollBack();
                return response()->json(['message' => 'Data sudah disetujui dan tidak bisa dihapus'], 422);
            }

            StockService::rollbackBySource('outbound', $tx->id);
            StockMutation::where('source_type', 'outbound')->where('source_id', $tx->id)->delete();
            $tx->delete();

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            $msg = collect($e->errors())->flatten()->first() ?? $e->getMessage();
            return response()->json([
                'message' => $msg,
            ], 422);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menghapus outbound',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Outbound berhasil dihapus',
        ]);
    }

    private function approve(string $type, int $id)
    {
        DB::beginTransaction();
        try {
            $tx = OutboundTransaction::with('items')
                ->where('type', $type)
                ->lockForUpdate()
                ->findOrFail($id);

            if (($tx->status ?? 'pending') === 'approved') {
                DB::rollBack();
                return response()->json(['message' => 'Data sudah disetujui']);
            }

            $hasMutations = StockMutation::where('source_type', 'outbound')
                ->where('source_id', $tx->id)
                ->exists();

            $approvedAt = now();
            if (!$hasMutations) {
                StockService::depleteSellableRows($tx->items, (int) $tx->warehouse_id, [
                    'source_type' => 'outbound',
                    'source_subtype' => $tx->type,
                    'source_id' => $tx->id,
                    'source_code' => $tx->code,
                    'note' => 'Outbound approved',
                    'occurred_at' => $approvedAt,
                    'created_by' => auth()->id(),
                ]);
            }

            $tx->status = 'approved';
            $tx->approved_at = $approvedAt;
            $tx->approved_by = auth()->id();
            $tx->save();

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menyetujui outbound',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json(['message' => 'Outbound berhasil disetujui']);
    }

    private function validatePayload(Request $request, string $type): array
    {
        $usesSupplier = $this->usesSupplier($type);
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.koli' => ['nullable', 'integer', 'min:1'],
            'items.*.note' => ['nullable', 'string'],
            'ref_no' => ['nullable', 'string', 'max:100'],
            'supplier_id' => $usesSupplier
                ? ['required', 'integer', 'exists:suppliers,id']
                : ['nullable'],
            'note' => ['nullable', 'string'],
            'transacted_at' => ['required', 'date'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
        ]);

        if (!$usesSupplier && $request->filled('supplier_id')) {
            throw ValidationException::withMessages([
                'supplier_id' => 'Supplier hanya digunakan untuk outbound retur.',
            ]);
        }

        $rawItems = collect($validated['items'] ?? [])->values();
        $itemMap = Item::query()
            ->whereIn('id', $rawItems->pluck('item_id')->filter()->map(fn ($id) => (int) $id)->all())
            ->get(['id', 'sku', 'item_type', 'koli_qty'])
            ->keyBy('id');

        $items = $rawItems
            ->filter(fn ($row) => (int) ($row['qty'] ?? 0) > 0 && (int) ($row['item_id'] ?? 0) > 0)
            ->map(function ($row, $index) use ($itemMap, $type) {
                $itemId = (int) ($row['item_id'] ?? 0);
                $item = $itemMap->get($itemId);

                if (!$item) {
                    throw ValidationException::withMessages([
                        "items.{$index}.item_id" => 'Item outbound tidak ditemukan.',
                    ]);
                }

                $qty = (int) ($row['qty'] ?? 0);
                if ($type === 'return') {
                    try {
                        $qty = OutboundKoliExpectation::resolve($item, $qty, $row['koli'] ?? null)['qty'];
                    } catch (ValidationException $e) {
                        $message = collect($e->errors())->flatten()->first() ?? $e->getMessage();
                        throw ValidationException::withMessages([
                            "items.{$index}.qty" => $message,
                            "items.{$index}.koli" => $message,
                        ]);
                    }
                }

                return [
                    'item_id' => $itemId,
                    'qty' => $qty,
                    'note' => $row['note'] ?? null,
                ];
            })->values();

        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Minimal 1 item diperlukan',
            ]);
        }

        $duplicates = $items->groupBy('item_id')->filter(fn ($rows) => $rows->count() > 1);
        if ($duplicates->isNotEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Item tidak boleh duplikat pada outbound',
            ]);
        }

        $normalized = $items->groupBy('item_id')->map(function ($rows, $itemId) {
            $qty = $rows->sum('qty');
            $note = $rows->pluck('note')->first(fn ($n) => $n !== null && $n !== '') ?? null;
            return [
                'item_id' => (int) $itemId,
                'qty' => $qty,
                'note' => $note,
            ];
        })->values()->all();

        $validated['items'] = $normalized;
        $itemMap = Item::query()
            ->whereIn('id', collect($normalized)->pluck('item_id')->all())
            ->get(['id', 'sku', 'item_type'])
            ->keyBy('id');

        foreach ($normalized as $row) {
            $item = $itemMap->get((int) $row['item_id']);
            if (!$item) {
                throw ValidationException::withMessages([
                    'items' => 'Item outbound tidak ditemukan.',
                ]);
            }
            if ($item->isBundle()) {
                BundleService::validateComponents($item, $item->bundleComponents()->get(['component_item_id as item_id', 'required_qty as qty'])->toArray());
            }
        }

        $validated['supplier_id'] = $usesSupplier ? (int) ($validated['supplier_id'] ?? 0) : null;
        if (!empty($validated['transacted_at'])) {
            $validated['transacted_at'] = Carbon::parse($validated['transacted_at']);
        } else {
            $validated['transacted_at'] = null;
        }

        return $validated;
    }

    private function typeOptions(): array
    {
        return [
            'picker' => 'Picker',
            'manual' => 'Manual',
            'return' => 'Retur',
        ];
    }

    private function applyDateFilter($query, Request $request): void
    {
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        try {
            if ($dateFrom) {
                $from = Carbon::parse($dateFrom)->startOfDay();
                $query->where('outbound_transactions.transacted_at', '>=', $from);
            }
            if ($dateTo) {
                $to = Carbon::parse($dateTo)->endOfDay();
                $query->where('outbound_transactions.transacted_at', '<=', $to);
            }
        } catch (\Throwable) {
            // ignore invalid date filters
        }
    }

    private function generateCode(string $prefix): string
    {
        return $prefix.'-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));
    }

    private function usesSupplier(string $type): bool
    {
        return $type === 'return';
    }
}
