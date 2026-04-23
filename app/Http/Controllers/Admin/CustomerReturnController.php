<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerReturn;
use App\Models\CustomerReturnItem;
use App\Models\DamagedGood;
use App\Models\DamagedGoodItem;
use App\Models\Item;
use App\Models\Resi;
use App\Models\Warehouse;
use App\Support\BundleService;
use App\Support\StockService;
use App\Support\WarehouseService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerReturnController extends Controller
{
    public function index()
    {
        return view('admin.inventory.customer-returns.index', [
            'dataUrl' => route('admin.inventory.customer-returns.data'),
            'finalizeUrl' => route('admin.inventory.customer-returns.finalize'),
            'createUrl' => route('admin.inventory.customer-returns.create'),
            'displayWarehouseLabel' => $this->displayWarehouseLabel(),
            'damagedWarehouseLabel' => $this->damagedWarehouseLabel(),
        ]);
    }

    public function create()
    {
        return view('admin.inventory.customer-returns.form', $this->formViewData(
            customerReturn: null,
            readOnly: false,
            pageTitle: 'Tambah Retur Customer',
            pageHeading: 'Tambah Retur Customer',
            submitLabel: 'Simpan',
            formAction: route('admin.inventory.customer-returns.store'),
            backUrl: route('admin.inventory.customer-returns.index')
        ));
    }

    public function data(Request $request)
    {
        $query = CustomerReturn::query()
            ->with(['items.item', 'creator', 'inspector', 'finalizer', 'damagedGood'])
            ->orderByDesc('received_at')
            ->orderByDesc('id');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('customer_returns.code', 'like', "%{$search}%")
                    ->orWhere('customer_returns.resi_no', 'like', "%{$search}%")
                    ->orWhere('customer_returns.order_ref', 'like', "%{$search}%")
                    ->orWhere('customer_returns.note', 'like', "%{$search}%")
                    ->orWhereHas('items.item', function ($itemQ) use ($search) {
                        $itemQ->where('sku', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    });
            });
        }

        $status = trim((string) $request->input('status', ''));
        if ($status !== '') {
            $query->where('customer_returns.status', $status);
        }

        $recordsTotal = CustomerReturn::count();
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $rows = $query->get();
        $data = $rows->map(function (CustomerReturn $row) {
            $items = $row->items ?? collect();
            $itemSummary = $items->map(function (CustomerReturnItem $itemRow) {
                $sku = trim((string) ($itemRow->item?->sku ?? ''));
                if ($sku === '') {
                    return '';
                }

                return sprintf(
                    '%s (%d/%d/%d)',
                    $sku,
                    (int) $itemRow->received_qty,
                    (int) $itemRow->good_qty,
                    (int) $itemRow->damaged_qty
                );
            })->filter()->values()->implode(', ');

            return [
                'id' => $row->id,
                'code' => $row->code,
                'resi_no' => $row->resi_no,
                'order_ref' => $row->order_ref ?? '-',
                'status' => $row->status,
                'matched' => (bool) $row->resi_id,
                'received_at' => $row->received_at?->format('Y-m-d H:i') ?? '',
                'inspected_at' => $row->inspected_at?->format('Y-m-d H:i') ?? '',
                'finalized_at' => $row->finalized_at?->format('Y-m-d H:i') ?? '',
                'submit_by' => $row->creator?->name ?? '-',
                'inspected_by' => $row->inspector?->name ?? '-',
                'finalized_by' => $row->finalizer?->name ?? '-',
                'item_summary' => $itemSummary ?: '-',
                'total_expected' => (int) $items->sum('expected_qty'),
                'total_received' => (int) $items->sum('received_qty'),
                'total_good' => (int) $items->sum('good_qty'),
                'total_damaged' => (int) $items->sum('damaged_qty'),
                'note' => $row->note ?? '',
                'damaged_good_code' => $row->damagedGood?->code,
                'can_finalize' => !$row->isCompleted(),
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function lookup(Request $request)
    {
        $validated = $request->validate([
            'resi_no' => ['required', 'string', 'max:100'],
        ]);

        $resiNo = trim((string) $validated['resi_no']);
        $resi = Resi::query()
            ->with('details')
            ->where('no_resi', $resiNo)
            ->first();

        if (!$resi) {
            return response()->json([
                'matched' => false,
                'resi_no' => $resiNo,
                'order_ref' => null,
                'items' => [],
                'missing_skus' => [],
            ]);
        }

        [$items, $missingSkus] = $this->buildLookupItems($resi);

        return response()->json([
            'matched' => true,
            'resi' => [
                'id' => $resi->id,
                'no_resi' => $resi->no_resi,
                'order_ref' => $resi->id_pesanan,
                'tanggal_pesanan' => optional($resi->tanggal_pesanan)->format('Y-m-d'),
            ],
            'items' => $items,
            'missing_skus' => $missingSkus,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        DB::beginTransaction();
        try {
            $customerReturn = CustomerReturn::create([
                'code' => $this->generateCode('CRT'),
                'resi_id' => $validated['resi_id'],
                'resi_no' => $validated['resi_no'],
                'order_ref' => $validated['order_ref'],
                'received_at' => $validated['received_at'],
                'inspected_at' => $validated['received_at'],
                'status' => CustomerReturn::STATUS_INSPECTED,
                'note' => $validated['note'],
                'created_by' => auth()->id(),
                'inspected_by' => auth()->id(),
            ]);

            $this->persistItems($customerReturn, $validated['items']);

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();

            if (!$request->expectsJson()) {
                return back()->withErrors([
                    'customer_return' => 'Gagal menyimpan retur customer: '.$e->getMessage(),
                ])->withInput();
            }

            return response()->json([
                'message' => 'Gagal menyimpan retur customer.',
                'error' => $e->getMessage(),
            ], 500);
        }

        $message = 'Retur customer berhasil disimpan dan siap difinalisasi.';

        if (!$request->expectsJson()) {
            return redirect()
                ->route('admin.inventory.customer-returns.index')
                ->with('success', $message);
        }

        return response()->json([
            'message' => $message,
        ]);
    }

    public function show(int $id)
    {
        $customerReturn = CustomerReturn::with(['items.item', 'damagedGood', 'creator', 'inspector', 'finalizer', 'resi'])
            ->findOrFail($id);

        return view('admin.inventory.customer-returns.show', [
            'customerReturn' => $customerReturn,
            'pageTitle' => 'Detail Retur Customer',
            'pageHeading' => 'Detail Retur Customer',
            'backUrl' => route('admin.inventory.customer-returns.index'),
            'displayWarehouseLabel' => $this->displayWarehouseLabel(),
            'damagedWarehouseLabel' => $this->damagedWarehouseLabel(),
        ]);
    }

    public function edit(int $id)
    {
        $customerReturn = CustomerReturn::with(['items.item', 'damagedGood'])
            ->findOrFail($id);

        if ($customerReturn->isCompleted()) {
            return redirect()
                ->route('admin.inventory.customer-returns.show', $customerReturn->id)
                ->withErrors([
                    'customer_return' => 'Retur customer yang sudah difinalisasi tidak bisa diubah.',
                ]);
        }

        return view('admin.inventory.customer-returns.form', $this->formViewData(
            customerReturn: $customerReturn,
            readOnly: false,
            pageTitle: 'Edit Retur Customer',
            pageHeading: 'Edit Retur Customer',
            submitLabel: 'Simpan Perubahan',
            formAction: route('admin.inventory.customer-returns.update', $customerReturn->id),
            backUrl: route('admin.inventory.customer-returns.index')
        ));
    }

    public function update(Request $request, int $id)
    {
        $validated = $this->validatePayload($request);

        DB::beginTransaction();
        try {
            $customerReturn = CustomerReturn::with('items')
                ->lockForUpdate()
                ->findOrFail($id);

            if ($customerReturn->isCompleted()) {
                throw ValidationException::withMessages([
                    'status' => 'Retur customer yang sudah difinalisasi tidak bisa diubah.',
                ]);
            }

            $customerReturn->update([
                'resi_id' => $validated['resi_id'],
                'resi_no' => $validated['resi_no'],
                'order_ref' => $validated['order_ref'],
                'received_at' => $validated['received_at'],
                'inspected_at' => $validated['received_at'],
                'note' => $validated['note'],
                'inspected_by' => auth()->id(),
                'status' => CustomerReturn::STATUS_INSPECTED,
            ]);

            CustomerReturnItem::where('customer_return_id', $customerReturn->id)->delete();
            $this->persistItems($customerReturn, $validated['items']);

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();

            if (!$request->expectsJson()) {
                return back()->withErrors([
                    'customer_return' => 'Gagal memperbarui retur customer: '.$e->getMessage(),
                ])->withInput();
            }

            return response()->json([
                'message' => 'Gagal memperbarui retur customer.',
                'error' => $e->getMessage(),
            ], 500);
        }

        $message = 'Retur customer berhasil diperbarui.';

        if (!$request->expectsJson()) {
            return redirect()
                ->route('admin.inventory.customer-returns.index')
                ->with('success', $message);
        }

        return response()->json([
            'message' => $message,
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        DB::beginTransaction();
        try {
            $customerReturn = CustomerReturn::with('items')
                ->lockForUpdate()
                ->findOrFail($id);

            if ($customerReturn->isCompleted()) {
                throw ValidationException::withMessages([
                    'status' => 'Retur customer yang sudah difinalisasi tidak bisa dihapus.',
                ]);
            }

            $customerReturn->delete();
            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();

            if (!$request->expectsJson()) {
                return back()->withErrors([
                    'customer_return' => 'Gagal menghapus retur customer: '.$e->getMessage(),
                ]);
            }

            return response()->json([
                'message' => 'Gagal menghapus retur customer.',
                'error' => $e->getMessage(),
            ], 500);
        }

        $message = 'Retur customer berhasil dihapus.';

        if (!$request->expectsJson()) {
            return redirect()
                ->route('admin.inventory.customer-returns.index')
                ->with('success', $message);
        }

        return response()->json([
            'message' => $message,
        ]);
    }

    public function finalize(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'exists:customer_returns,id'],
        ]);

        $ids = collect($validated['ids'])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            throw ValidationException::withMessages([
                'ids' => 'Minimal 1 retur customer dipilih untuk finalisasi.',
            ]);
        }

        DB::beginTransaction();
        try {
            $customerReturns = CustomerReturn::query()
                ->with(['items.item'])
                ->whereIn('id', $ids->all())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($ids as $id) {
                $customerReturn = $customerReturns->get($id);
                if (!$customerReturn) {
                    throw ValidationException::withMessages([
                        'ids' => 'Data retur customer tidak ditemukan.',
                    ]);
                }

                $this->finalizeReturn($customerReturn);
            }

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Gagal memfinalisasi retur customer.',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => sprintf('%d retur customer berhasil difinalisasi.', $ids->count()),
        ]);
    }

    private function finalizeReturn(CustomerReturn $customerReturn): void
    {
        if ($customerReturn->isCompleted()) {
            throw ValidationException::withMessages([
                'status' => "Retur {$customerReturn->code} sudah difinalisasi.",
            ]);
        }

        $items = $customerReturn->items ?? collect();
        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => "Retur {$customerReturn->code} belum memiliki item inspeksi.",
            ]);
        }

        $hasQty = $items->contains(function (CustomerReturnItem $item) {
            return (int) $item->good_qty > 0 || (int) $item->damaged_qty > 0;
        });

        if (!$hasQty) {
            throw ValidationException::withMessages([
                'items' => "Retur {$customerReturn->code} belum memiliki qty bagus atau rusak untuk difinalisasi.",
            ]);
        }

        $finalizedAt = now();
        $displayWarehouseId = WarehouseService::displayWarehouseId();
        foreach ($items as $itemRow) {
            $goodQty = (int) $itemRow->good_qty;
            if ($goodQty <= 0) {
                continue;
            }

            StockService::mutate([
                'item_id' => $itemRow->item_id,
                'warehouse_id' => $displayWarehouseId,
                'direction' => 'in',
                'qty' => $goodQty,
                'source_type' => 'customer_return',
                'source_subtype' => 'good',
                'source_id' => $customerReturn->id,
                'source_code' => $customerReturn->code,
                'note' => $itemRow->note,
                'occurred_at' => $finalizedAt,
                'created_by' => auth()->id(),
            ]);
        }

        $damagedRows = $items->filter(fn (CustomerReturnItem $item) => (int) $item->damaged_qty > 0)->values();
        $damagedGood = null;
        if ($damagedRows->isNotEmpty()) {
            $damagedGood = $this->createApprovedDamagedGood($customerReturn, $damagedRows, $finalizedAt);
        }

        $customerReturn->update([
            'status' => CustomerReturn::STATUS_COMPLETED,
            'finalized_at' => $finalizedAt,
            'finalized_by' => auth()->id(),
            'damaged_good_id' => $damagedGood?->id,
        ]);
    }

    private function createApprovedDamagedGood(CustomerReturn $customerReturn, Collection $damagedRows, Carbon $finalizedAt): DamagedGood
    {
        $damagedWarehouseId = WarehouseService::damagedWarehouseId();
        $damagedWarehouse = $damagedWarehouseId > 0 ? Warehouse::find($damagedWarehouseId) : null;
        if (!$damagedWarehouse) {
            throw ValidationException::withMessages([
                'warehouse' => 'Gudang Rusak belum tersedia. Jalankan migrasi terbaru terlebih dahulu.',
            ]);
        }

        $sourceRef = $customerReturn->resi_no;
        if (!empty($customerReturn->order_ref)) {
            $sourceRef .= ' / '.$customerReturn->order_ref;
        }

        $damagedGood = DamagedGood::create([
            'code' => $this->generateCode('DMG'),
            'source_type' => 'customer_return',
            'source_warehouse_id' => null,
            'source_ref' => $sourceRef,
            'note' => $customerReturn->note,
            'transacted_at' => $finalizedAt,
            'created_by' => auth()->id(),
            'status' => 'approved',
            'approved_at' => $finalizedAt,
            'approved_by' => auth()->id(),
        ]);

        foreach ($damagedRows as $row) {
            $damageItem = DamagedGoodItem::create([
                'damaged_good_id' => $damagedGood->id,
                'item_id' => $row->item_id,
                'qty' => (int) $row->damaged_qty,
                'note' => $row->note,
            ]);

            StockService::mutate([
                'item_id' => $damageItem->item_id,
                'warehouse_id' => $damagedWarehouseId,
                'direction' => 'in',
                'qty' => (int) $damageItem->qty,
                'source_type' => 'damaged',
                'source_subtype' => 'customer_return',
                'source_id' => $damagedGood->id,
                'source_code' => $damagedGood->code,
                'note' => $damageItem->note,
                'occurred_at' => $finalizedAt,
                'created_by' => auth()->id(),
            ]);
        }

        return $damagedGood;
    }

    private function persistItems(CustomerReturn $customerReturn, Collection $items): void
    {
        foreach ($items as $row) {
            CustomerReturnItem::create([
                'customer_return_id' => $customerReturn->id,
                'item_id' => (int) $row['item_id'],
                'expected_qty' => (int) $row['expected_qty'],
                'received_qty' => (int) $row['received_qty'],
                'good_qty' => (int) $row['good_qty'],
                'damaged_qty' => (int) $row['damaged_qty'],
                'note' => $row['note'] ?? null,
            ]);
        }
    }

    private function validatePayload(Request $request): array
    {
        $validated = $request->validate([
            'resi_no' => ['required', 'string', 'max:100'],
            'resi_id' => ['nullable', 'integer', 'exists:resis,id'],
            'order_ref' => ['nullable', 'string', 'max:100'],
            'received_at' => ['required', 'date'],
            'note' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'items.*.expected_qty' => ['nullable', 'integer', 'min:0'],
            'items.*.received_qty' => ['required', 'integer', 'min:0'],
            'items.*.good_qty' => ['required', 'integer', 'min:0'],
            'items.*.damaged_qty' => ['required', 'integer', 'min:0'],
            'items.*.note' => ['nullable', 'string'],
        ]);

        $rows = collect($validated['items'] ?? [])
            ->map(function ($row) {
                return [
                    'item_id' => (int) ($row['item_id'] ?? 0),
                    'expected_qty' => (int) ($row['expected_qty'] ?? 0),
                    'received_qty' => (int) ($row['received_qty'] ?? 0),
                    'good_qty' => (int) ($row['good_qty'] ?? 0),
                    'damaged_qty' => (int) ($row['damaged_qty'] ?? 0),
                    'note' => $row['note'] ?? null,
                ];
            })
            ->filter(fn ($row) => (int) $row['item_id'] > 0)
            ->values();

        if ($rows->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Minimal 1 item retur diperlukan.',
            ]);
        }

        $duplicates = $rows->groupBy('item_id')->filter(fn ($group) => $group->count() > 1);
        if ($duplicates->isNotEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Item pada retur customer tidak boleh duplikat.',
            ]);
        }

        $hasReceivedQty = false;
        foreach ($rows as $index => $row) {
            if (((int) $row['good_qty'] + (int) $row['damaged_qty']) !== (int) $row['received_qty']) {
                throw ValidationException::withMessages([
                    "items.{$index}.received_qty" => 'Qty bagus + qty rusak harus sama dengan qty diterima.',
                ]);
            }

            if ((int) $row['received_qty'] > 0) {
                $hasReceivedQty = true;
            }
        }

        if (!$hasReceivedQty) {
            throw ValidationException::withMessages([
                'items' => 'Minimal 1 item harus memiliki qty diterima lebih dari 0.',
            ]);
        }

        BundleService::assertPhysicalItems(
            $rows->pluck('item_id')->all(),
            'Bundle tidak bisa digunakan pada retur customer karena tidak memiliki stok fisik.'
        );

        $resolvedResi = $this->resolveResiReference(
            trim((string) $validated['resi_no']),
            isset($validated['resi_id']) ? (int) $validated['resi_id'] : null,
            $validated['order_ref'] ?? null
        );

        $validated['resi_id'] = $resolvedResi['resi_id'];
        $validated['resi_no'] = $resolvedResi['resi_no'];
        $validated['order_ref'] = $resolvedResi['order_ref'];
        $validated['received_at'] = Carbon::parse($validated['received_at']);
        $validated['note'] = $validated['note'] ?? null;
        $validated['items'] = $rows;

        return $validated;
    }

    private function resolveResiReference(string $resiNo, ?int $resiId, ?string $orderRef): array
    {
        $resiNo = trim($resiNo);
        $orderRef = $orderRef ? trim($orderRef) : null;

        $resi = null;
        if ($resiId && $resiId > 0) {
            $resi = Resi::find($resiId);
            if (!$resi) {
                throw ValidationException::withMessages([
                    'resi_no' => 'Data resi tidak ditemukan. Silakan scan ulang.',
                ]);
            }
            if (strcasecmp((string) $resi->no_resi, $resiNo) !== 0) {
                throw ValidationException::withMessages([
                    'resi_no' => 'Nomor resi tidak sinkron dengan data hasil scan. Silakan scan ulang.',
                ]);
            }
        }

        if (!$resi && $resiNo !== '') {
            $resi = Resi::query()
                ->where('no_resi', $resiNo)
                ->first();
        }

        if ($resi) {
            return [
                'resi_id' => (int) $resi->id,
                'resi_no' => (string) $resi->no_resi,
                'order_ref' => (string) ($resi->id_pesanan ?? $orderRef),
            ];
        }

        return [
            'resi_id' => null,
            'resi_no' => $resiNo,
            'order_ref' => $orderRef,
        ];
    }

    private function buildLookupItems(Resi $resi): array
    {
        $qtyBySku = $resi->details
            ->groupBy(fn ($detail) => trim((string) $detail->sku))
            ->map(fn ($rows) => (int) $rows->sum('qty'))
            ->filter(fn ($qty, $sku) => $qty > 0 && $sku !== '');

        if ($qtyBySku->isEmpty()) {
            return [[], []];
        }

        $itemsBySku = Item::query()
            ->where('item_type', Item::TYPE_SINGLE)
            ->whereIn('sku', $qtyBySku->keys()->all())
            ->get(['id', 'sku', 'name'])
            ->keyBy('sku');

        $rows = [];
        $missingSkus = [];
        foreach ($qtyBySku as $sku => $qty) {
            $item = $itemsBySku->get($sku);
            if (!$item) {
                $missingSkus[] = [
                    'sku' => $sku,
                    'expected_qty' => (int) $qty,
                ];
                continue;
            }

            $rows[] = [
                'item_id' => (int) $item->id,
                'item_sku' => (string) $item->sku,
                'item_name' => (string) $item->name,
                'expected_qty' => (int) $qty,
                'received_qty' => 0,
                'good_qty' => 0,
                'damaged_qty' => 0,
                'note' => null,
            ];
        }

        return [$rows, $missingSkus];
    }

    private function serializeCustomerReturn(CustomerReturn $customerReturn): array
    {
        return [
            'id' => $customerReturn->id,
            'code' => $customerReturn->code,
            'resi_id' => $customerReturn->resi_id,
            'resi_no' => $customerReturn->resi_no,
            'order_ref' => $customerReturn->order_ref,
            'status' => $customerReturn->status,
            'received_at' => $customerReturn->received_at?->format('Y-m-d H:i'),
            'inspected_at' => $customerReturn->inspected_at?->format('Y-m-d H:i'),
            'finalized_at' => $customerReturn->finalized_at?->format('Y-m-d H:i'),
            'note' => $customerReturn->note,
            'damaged_good_code' => $customerReturn->damagedGood?->code,
            'items' => $customerReturn->items->map(function (CustomerReturnItem $row) {
                return [
                    'item_id' => $row->item_id,
                    'item_sku' => $row->item?->sku,
                    'item_name' => $row->item?->name,
                    'expected_qty' => (int) $row->expected_qty,
                    'received_qty' => (int) $row->received_qty,
                    'good_qty' => (int) $row->good_qty,
                    'damaged_qty' => (int) $row->damaged_qty,
                    'note' => $row->note,
                ];
            })->values(),
        ];
    }

    private function formViewData(
        ?CustomerReturn $customerReturn,
        bool $readOnly,
        string $pageTitle,
        string $pageHeading,
        ?string $submitLabel,
        string $formAction,
        string $backUrl
    ): array {
        $items = Item::query()
            ->where('item_type', Item::TYPE_SINGLE)
            ->orderBy('name')
            ->get(['id', 'sku', 'name']);

        return [
            'customerReturn' => $customerReturn,
            'readOnly' => $readOnly,
            'pageTitle' => $pageTitle,
            'pageHeading' => $pageHeading,
            'submitLabel' => $submitLabel,
            'formAction' => $formAction,
            'backUrl' => $backUrl,
            'lookupUrl' => route('admin.inventory.customer-returns.lookup'),
            'items' => $items,
            'displayWarehouseLabel' => $this->displayWarehouseLabel(),
            'damagedWarehouseLabel' => $this->damagedWarehouseLabel(),
        ];
    }

    private function displayWarehouseLabel(): string
    {
        return Warehouse::where('id', WarehouseService::displayWarehouseId())->value('name') ?? 'Gudang Display';
    }

    private function damagedWarehouseLabel(): string
    {
        return Warehouse::where('id', WarehouseService::damagedWarehouseId())->value('name') ?? 'Gudang Rusak';
    }

    private function generateCode(string $prefix): string
    {
        return $prefix.'-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));
    }
}
