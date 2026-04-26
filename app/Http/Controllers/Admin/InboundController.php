<?php

namespace App\Http\Controllers\Admin;

use App\Exports\InboundManualTemplateExport;
use App\Http\Controllers\Controller;
use App\Imports\InboundFormItemsImport;
use App\Imports\InboundReceiptsImport;
use App\Imports\InboundReturnsImport;
use App\Models\InboundItem;
use App\Models\InboundTransaction;
use App\Models\Item;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Support\BundleService;
use App\Support\InboundReceiptQrPdfService;
use App\Support\InboundScanExpectation;
use App\Support\InboundScanStatus;
use App\Support\Permission;
use App\Support\SimpleBarcodeService;
use App\Support\WarehouseService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class InboundController extends Controller
{
    public function receipts()
    {
        return $this->index('receipt', 'Inbound - Penerimaan Barang', 'receipts');
    }

    public function returns()
    {
        return $this->index('return', 'Inbound - Retur', 'returns');
    }

    public function manuals()
    {
        return $this->index('manual', 'Inbound - Manual', 'manuals');
    }

    public function receiptsData(Request $request)
    {
        return $this->data($request, 'receipt');
    }

    public function returnsData(Request $request)
    {
        return $this->data($request, 'return');
    }

    public function manualsData(Request $request)
    {
        return $this->data($request, 'manual');
    }

    public function receiptsStore(Request $request)
    {
        return $this->store($request, 'receipt');
    }

    public function returnsStore(Request $request)
    {
        return $this->store($request, 'return');
    }

    public function manualsStore(Request $request)
    {
        return $this->store($request, 'manual');
    }

    public function receiptsShow(int $id)
    {
        return $this->show('receipt', $id);
    }

    public function returnsShow(int $id)
    {
        return $this->show('return', $id);
    }

    public function manualsShow(int $id)
    {
        return $this->show('manual', $id);
    }

    public function receiptsDetail(int $id)
    {
        return $this->detail('receipt', 'Inbound - Penerimaan Barang', 'receipts', $id);
    }

    public function returnsDetail(int $id)
    {
        return $this->detail('return', 'Inbound - Retur', 'returns', $id);
    }

    public function manualsDetail(int $id)
    {
        return $this->detail('manual', 'Inbound - Manual', 'manuals', $id);
    }

    public function receiptsUpdate(Request $request, int $id)
    {
        return $this->update($request, 'receipt', $id);
    }

    public function returnsUpdate(Request $request, int $id)
    {
        return $this->update($request, 'return', $id);
    }

    public function manualsUpdate(Request $request, int $id)
    {
        return $this->update($request, 'manual', $id);
    }

    public function receiptsDestroy(int $id)
    {
        return $this->destroy('receipt', $id);
    }

    public function returnsDestroy(int $id)
    {
        return $this->destroy('return', $id);
    }

    public function manualsDestroy(int $id)
    {
        return $this->destroy('manual', $id);
    }

    public function receiptsApprove(int $id)
    {
        return $this->approve('receipt', $id);
    }

    public function receiptsQrPreview(int $id)
    {
        $transaction = $this->qrTransaction('receipt', $id);

        return response()->json($this->receiptQrPayload($transaction));
    }

    public function receiptsQrPdf(int $id)
    {
        $transaction = $this->qrTransaction('receipt', $id);
        $service = app(InboundReceiptQrPdfService::class);

        return response(
            $service->pdfForTransaction($transaction),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$service->downloadFilename($transaction).'"',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
            ]
        );
    }

    public function returnsApprove(int $id)
    {
        return $this->approve('return', $id);
    }

    public function manualsApprove(int $id)
    {
        return $this->approve('manual', $id);
    }

    public function manualsTemplate()
    {
        $filename = 'inbound-manual-template-'.now()->format('YmdHis').'.xlsx';

        return Excel::download(new InboundManualTemplateExport(), $filename);
    }

    public function manualsImport(Request $request)
    {
        return $this->importGroups(
            $request,
            new InboundReceiptsImport(false),
            'manual',
            'INB-MNL',
            'Import inbound manual berhasil',
            'Gagal import inbound manual'
        );
    }

    public function returnsImport(Request $request)
    {
        return $this->importGroups(
            $request,
            new InboundReturnsImport(),
            'return',
            'INB-RET',
            'Import retur inbound berhasil',
            'Gagal import retur inbound'
        );
    }

    public function receiptsImport(Request $request)
    {
        return $this->importGroups(
            $request,
            new InboundReceiptsImport(true),
            'receipt',
            'INB-RCV',
            'Import penerimaan barang berhasil',
            'Gagal import penerimaan barang'
        );
    }

    public function receiptsItemsImport(Request $request)
    {
        return $this->importFormItems($request, 'receipt');
    }

    public function returnsItemsImport(Request $request)
    {
        return $this->importFormItems($request, 'return');
    }

    public function manualsItemsImport(Request $request)
    {
        return $this->importFormItems($request, 'manual');
    }

    private function index(string $type, string $pageTitle, string $routeBase)
    {
        $items = Item::query()
            ->where('item_type', Item::TYPE_SINGLE)
            ->orderBy('name')
            ->get(['id', 'sku', 'name', 'koli_qty']);
        $warehouses = Warehouse::orderBy('name')->get(['id', 'name', 'code']);
        $suppliers = $this->usesSupplier($type)
            ? Supplier::orderBy('name')->get(['id', 'name'])
            : collect();
        $baseOptions = $this->typeOptions();
        $typeOptions = ['all' => 'Semua'] + $baseOptions;
        $statusLabels = [
            InboundScanStatus::PENDING_SCAN => 'Menunggu Scan',
            InboundScanStatus::SCANNING => 'Sedang Scan',
            InboundScanStatus::COMPLETED => 'Selesai',
            'approved' => 'Selesai',
        ];
        $routeMap = [
            'receipt' => [
                'store' => route('admin.inbound.receipts.store'),
                'show' => route('admin.inbound.receipts.show', ':id'),
                'update' => route('admin.inbound.receipts.update', ':id'),
                'delete' => route('admin.inbound.receipts.destroy', ':id'),
                'detail' => route('admin.inbound.receipts.detail', ':id'),
                'approve' => route('admin.inbound.receipts.approve', ':id'),
                'qr_preview' => route('admin.inbound.receipts.qr-preview', ':id'),
                'qr_pdf' => route('admin.inbound.receipts.qr-pdf', ':id'),
            ],
            'return' => [
                'store' => route('admin.inbound.returns.store'),
                'show' => route('admin.inbound.returns.show', ':id'),
                'update' => route('admin.inbound.returns.update', ':id'),
                'delete' => route('admin.inbound.returns.destroy', ':id'),
                'detail' => route('admin.inbound.returns.detail', ':id'),
                'approve' => route('admin.inbound.returns.approve', ':id'),
            ],
            'manual' => [
                'store' => route('admin.inbound.manuals.store'),
                'show' => route('admin.inbound.manuals.show', ':id'),
                'update' => route('admin.inbound.manuals.update', ':id'),
                'delete' => route('admin.inbound.manuals.destroy', ':id'),
                'detail' => route('admin.inbound.manuals.detail', ':id'),
                'approve' => route('admin.inbound.manuals.approve', ':id'),
            ],
        ];

        return view('admin.stock-flow.index', [
            'pageTitle' => $pageTitle,
            'dataUrl' => route("admin.inbound.{$routeBase}.data"),
            'storeUrl' => route("admin.inbound.{$routeBase}.store"),
            'showUrlTpl' => route("admin.inbound.{$routeBase}.show", ':id'),
            'updateUrlTpl' => route("admin.inbound.{$routeBase}.update", ':id'),
            'deleteUrlTpl' => route("admin.inbound.{$routeBase}.destroy", ':id'),
            'detailUrlTpl' => route("admin.inbound.{$routeBase}.detail", ':id'),
            'items' => $items,
            'warehouses' => $warehouses,
            'defaultWarehouseId' => WarehouseService::defaultWarehouseId(),
            'displayWarehouseId' => WarehouseService::displayWarehouseId(),
            'typeOptions' => $typeOptions,
            'typeDefault' => $type,
            'routeMap' => $routeMap,
            'enableKoli' => true,
            'showApproveAction' => false,
            'showScanProgressColumn' => true,
            'statusLabels' => $statusLabels,
            'lockedStatuses' => [InboundScanStatus::SCANNING, InboundScanStatus::COMPLETED, 'approved'],
            'showDeliveryNoteFields' => true,
            'suppliers' => $suppliers,
            'supplierFlowTypes' => $this->usesSupplier($type) ? [$type] : [],
            'showSupplierColumn' => $this->usesSupplier($type),
            'supplierManageUrl' => $this->usesSupplier($type) && Permission::can(auth()->user(), 'admin.masterdata.suppliers.index')
                ? route('admin.masterdata.suppliers.index')
                : null,
            'importRequiresSupplier' => $this->usesSupplier($type),
            'deleteWarningText' => 'Data akan dihapus sebelum proses scan inbound.',
            'importUrl' => match ($type) {
                'receipt' => route('admin.inbound.receipts.import'),
                'return' => route('admin.inbound.returns.import'),
                'manual' => route('admin.inbound.manuals.import'),
                default => null,
            },
            'itemImportUrl' => match ($type) {
                'receipt' => route('admin.inbound.receipts.items-import'),
                'return' => route('admin.inbound.returns.items-import'),
                'manual' => route('admin.inbound.manuals.items-import'),
                default => null,
            },
            'importTitle' => match ($type) {
                'receipt' => 'Import Penerimaan Barang',
                'return' => 'Import Retur Inbound',
                'manual' => 'Import Manual Inbound',
                default => null,
            },
            'templateUrl' => $type === 'manual'
                ? route('admin.inbound.manuals.template')
                : null,
            'templateLabel' => 'Download Template Inbound Manual',
            'templateNote' => 'Header: sku, qty atau koli. Opsional: ref_no, surat_jalan_no, surat_jalan_at, note, item_note, transacted_at.',
        ]);
    }

    private function data(Request $request, string $type)
    {
        $allowed = array_keys($this->typeOptions());
        $filterType = $request->input('type');
        if ($filterType === 'all') {
            $baseType = null;
        } elseif (in_array($filterType, $allowed, true)) {
            $baseType = $filterType;
        } else {
            $baseType = $type;
        }

        $query = InboundTransaction::query()
            ->with(['items.item', 'creator', 'warehouse', 'supplier', 'scanSession.items'])
            ->select([
                'inbound_transactions.id',
                'inbound_transactions.code',
                'inbound_transactions.transacted_at',
                'inbound_transactions.type',
                'inbound_transactions.ref_no',
                'inbound_transactions.supplier_id',
                'inbound_transactions.surat_jalan_no',
                'inbound_transactions.surat_jalan_at',
                'inbound_transactions.note',
                'inbound_transactions.warehouse_id',
                'inbound_transactions.status',
                'inbound_transactions.created_by',
            ])
            ->orderBy('inbound_transactions.transacted_at', 'desc');

        if ($baseType) {
            $query->where('inbound_transactions.type', $baseType);
        }

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $exact = $this->isExactSearch($request);
            $query->where(function ($q) use ($search, $exact) {
                $this->applyTextSearch($q, 'inbound_transactions.code', $search, $exact);
                $this->applyTextSearch($q, 'inbound_transactions.ref_no', $search, $exact, 'or');
                $this->applyTextSearch($q, 'inbound_transactions.surat_jalan_no', $search, $exact, 'or');
                $q->orWhereHas('supplier', function ($supplierQ) use ($search, $exact) {
                    $this->applyTextSearch($supplierQ, 'name', $search, $exact);
                })->orWhereHas('items.item', function ($itemQ) use ($search, $exact) {
                    $this->applyTextSearch($itemQ, 'sku', $search, $exact);
                    $this->applyTextSearch($itemQ, 'name', $search, $exact, 'or');
                });
            });
        }

        $this->applyDateFilter($query, $request);

        $warehouseFilter = $request->input('warehouse_id');
        if ($warehouseFilter !== null && $warehouseFilter !== '' && $warehouseFilter !== 'all') {
            $query->where('inbound_transactions.warehouse_id', (int) $warehouseFilter);
        }

        $recordsTotalQuery = InboundTransaction::query();
        if ($baseType) {
            $recordsTotalQuery->where('type', $baseType);
        }

        $recordsTotal = $recordsTotalQuery->count();
        $recordsFiltered = (clone $query)->count();
        $defaultWarehouseLabel = Warehouse::where('id', WarehouseService::defaultWarehouseId())->value('name') ?? 'Gudang Besar';

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $data = $query->get()->map(function (InboundTransaction $row) use ($defaultWarehouseLabel) {
            $ts = $row->transacted_at?->format('Y-m-d H:i') ?? '';
            $items = $row->items ?? collect();
            $scanItems = $row->scanSession?->items ?? collect();
            $labels = $items->map(function (InboundItem $item) {
                $sku = trim($item->item?->sku ?? '');
                if ($sku === '') {
                    return '';
                }

                return sprintf('%s (%d)', $sku, (int) ($item->qty ?? 0));
            })->filter()->values();

            $expectedQty = (int) $items->sum('qty');
            $expectedKoli = (int) $items->sum(fn ($item) => (int) ($item->koli ?? 0));
            $scannedQty = (int) $scanItems->sum('scanned_qty');
            $scannedKoli = (int) $scanItems->sum('scanned_koli');

            return [
                'id' => $row->id,
                'code' => $row->code,
                'transacted_at' => $ts,
                'submit_by' => $row->creator?->name ?? '-',
                'warehouse' => $row->warehouse?->name ?? $defaultWarehouseLabel,
                'warehouse_id' => $row->warehouse_id,
                'supplier' => $row->supplier?->name ?? '-',
                'item' => $labels->implode(', ') ?: '-',
                'qty' => $expectedQty,
                'scan_progress' => [
                    'expected_koli' => $expectedKoli,
                    'scanned_koli' => $scannedKoli,
                    'expected_qty' => $expectedQty,
                    'scanned_qty' => $scannedQty,
                ],
                'note' => $row->note ?? '',
                'surat_jalan_no' => $row->surat_jalan_no ?? '',
                'surat_jalan_at' => $row->surat_jalan_at?->format('Y-m-d') ?? '',
                'type' => $row->type,
                'status' => $row->status ?? InboundScanStatus::PENDING_SCAN,
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
        $transaction = InboundTransaction::with(['items', 'supplier'])
            ->where('type', $type)
            ->findOrFail($id);

        return response()->json([
            'id' => $transaction->id,
            'code' => $transaction->code,
            'ref_no' => $transaction->ref_no,
            'supplier_id' => $transaction->supplier_id,
            'supplier' => $transaction->supplier?->name,
            'surat_jalan_no' => $transaction->surat_jalan_no,
            'surat_jalan_at' => $transaction->surat_jalan_at?->format('Y-m-d'),
            'note' => $transaction->note,
            'status' => $transaction->status ?? InboundScanStatus::PENDING_SCAN,
            'warehouse_id' => $transaction->warehouse_id,
            'transacted_at' => $transaction->transacted_at?->format('Y-m-d\TH:i'),
            'items' => $transaction->items->map(function (InboundItem $item) {
                return [
                    'item_id' => $item->item_id,
                    'qty' => $item->qty,
                    'koli' => $item->koli,
                    'note' => $item->note ?? '',
                ];
            })->values(),
        ]);
    }

    private function detail(string $type, string $pageTitle, string $routeBase, int $id)
    {
        $transaction = InboundTransaction::with([
            'items.item',
            'warehouse',
            'supplier',
            'scanSession.items',
            'scanSession.starter:id,name',
            'scanSession.lastScanner:id,name',
            'scanSession.completer:id,name',
            'scanSession.resetter:id,name',
        ])->where('type', $type)->findOrFail($id);

        $totalQty = (int) $transaction->items->sum('qty');
        $totalKoli = (int) $transaction->items->sum(fn ($row) => (int) ($row->koli ?? 0));
        $warehouseLabel = $transaction->warehouse?->name;
        if (!$warehouseLabel) {
            $warehouseLabel = Warehouse::where('id', WarehouseService::defaultWarehouseId())->value('name') ?? 'Gudang Besar';
        }

        $scanItems = $transaction->scanSession?->items ?? collect();
        $scanSummary = [
            'expected_qty' => (int) $scanItems->sum('expected_qty'),
            'expected_koli' => (int) $scanItems->sum('expected_koli'),
            'scanned_qty' => (int) $scanItems->sum('scanned_qty'),
            'scanned_koli' => (int) $scanItems->sum('scanned_koli'),
        ];

        return view('admin.stock-flow.detail', [
            'pageTitle' => $pageTitle,
            'transaction' => $transaction,
            'totalQty' => $totalQty,
            'totalKoli' => $totalKoli,
            'showKoli' => true,
            'showSupplierField' => $this->usesSupplier($type),
            'warehouseLabel' => $warehouseLabel,
            'backUrl' => route("admin.inbound.{$routeBase}.index"),
            'scanSession' => $transaction->scanSession,
            'scanSummary' => $scanSummary,
            'statusLabel' => InboundScanStatus::label($transaction->status),
        ]);
    }

    private function store(Request $request, string $type)
    {
        $validated = $this->validatePayload($request, $type);
        $prefix = match ($type) {
            'receipt' => 'INB-RCV',
            'return' => 'INB-RET',
            default => 'INB-MNL',
        };

        DB::beginTransaction();
        try {
            $transaction = InboundTransaction::create([
                'code' => $this->generateCode($prefix),
                'type' => $type,
                'ref_no' => $validated['ref_no'] ?? null,
                'supplier_id' => $validated['supplier_id'] ?? null,
                'surat_jalan_no' => $validated['surat_jalan_no'] ?? null,
                'surat_jalan_at' => $validated['surat_jalan_at'] ?? null,
                'note' => $validated['note'] ?? null,
                'warehouse_id' => WarehouseService::defaultWarehouseId(),
                'transacted_at' => $validated['transacted_at'] ?? now(),
                'created_by' => auth()->id(),
                'status' => InboundScanStatus::PENDING_SCAN,
            ]);

            foreach ($validated['items'] as $row) {
                InboundItem::create([
                    'inbound_transaction_id' => $transaction->id,
                    'item_id' => $row['item_id'],
                    'qty' => $row['qty'],
                    'koli' => $row['koli'],
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
                'message' => 'Gagal menyimpan inbound',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Inbound berhasil disimpan dan menunggu scan inbound.',
        ]);
    }

    private function update(Request $request, string $type, int $id)
    {
        $validated = $this->validatePayload($request, $type);

        DB::beginTransaction();
        try {
            $transaction = InboundTransaction::with('scanSession')
                ->where('type', $type)
                ->findOrFail($id);

            if (($transaction->status ?? InboundScanStatus::PENDING_SCAN) !== InboundScanStatus::PENDING_SCAN || $transaction->scanSession) {
                DB::rollBack();

                return response()->json([
                    'message' => 'Inbound yang sudah mulai discan tidak bisa diubah.',
                ], 422);
            }

            InboundItem::where('inbound_transaction_id', $transaction->id)->delete();

            $transaction->update([
                'ref_no' => $validated['ref_no'] ?? null,
                'supplier_id' => $validated['supplier_id'] ?? null,
                'surat_jalan_no' => $validated['surat_jalan_no'] ?? null,
                'surat_jalan_at' => $validated['surat_jalan_at'] ?? null,
                'note' => $validated['note'] ?? null,
                'transacted_at' => $validated['transacted_at'] ?? $transaction->transacted_at,
            ]);

            foreach ($validated['items'] as $row) {
                InboundItem::create([
                    'inbound_transaction_id' => $transaction->id,
                    'item_id' => $row['item_id'],
                    'qty' => $row['qty'],
                    'koli' => $row['koli'],
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
                'message' => 'Gagal memperbarui inbound',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Inbound berhasil diperbarui.',
        ]);
    }

    private function destroy(string $type, int $id)
    {
        DB::beginTransaction();
        try {
            $transaction = InboundTransaction::with('scanSession')
                ->where('type', $type)
                ->findOrFail($id);

            if (($transaction->status ?? InboundScanStatus::PENDING_SCAN) !== InboundScanStatus::PENDING_SCAN || $transaction->scanSession) {
                DB::rollBack();

                return response()->json([
                    'message' => 'Inbound yang sudah mulai discan tidak bisa dihapus.',
                ], 422);
            }

            $transaction->delete();

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            $message = collect($e->errors())->flatten()->first() ?? $e->getMessage();

            return response()->json([
                'message' => $message,
            ], 422);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Gagal menghapus inbound',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Inbound berhasil dihapus.',
        ]);
    }

    private function approve(string $type, int $id)
    {
        InboundTransaction::where('type', $type)->findOrFail($id);

        return response()->json([
            'message' => 'Inbound sekarang diselesaikan melalui Scan Inbound, bukan approve manual.',
        ], 422);
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
            'surat_jalan_no' => ['nullable', 'string', 'max:100'],
            'surat_jalan_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string'],
            'transacted_at' => ['required', 'date'],
        ]);

        if (!$usesSupplier && $request->filled('supplier_id')) {
            throw ValidationException::withMessages([
                'supplier_id' => 'Supplier hanya digunakan untuk inbound penerimaan barang.',
            ]);
        }

        $items = collect($validated['items'] ?? [])
            ->filter(fn ($row) => (int) ($row['qty'] ?? 0) > 0 && (int) ($row['item_id'] ?? 0) > 0)
            ->map(function ($row) {
                return [
                    'item_id' => (int) $row['item_id'],
                    'qty' => (int) $row['qty'],
                    'koli' => isset($row['koli']) && (int) $row['koli'] > 0 ? (int) $row['koli'] : null,
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
                'items' => 'Item tidak boleh duplikat pada inbound',
            ]);
        }

        BundleService::assertPhysicalItems(
            $items->pluck('item_id')->all(),
            'Bundle tidak bisa digunakan pada inbound karena tidak memiliki stok fisik.'
        );

        $itemMap = Item::whereIn('id', $items->pluck('item_id')->all())
            ->get(['id', 'sku', 'name', 'koli_qty', 'item_type'])
            ->keyBy('id');

        $normalized = $items->map(function ($row) use ($itemMap) {
            $item = $itemMap->get($row['item_id']);
            if (!$item) {
                throw ValidationException::withMessages([
                    'items' => 'Item inbound tidak ditemukan.',
                ]);
            }

            $resolved = InboundScanExpectation::resolve($item, (int) $row['qty'], $row['koli']);

            return [
                'item_id' => (int) $row['item_id'],
                'qty' => $resolved['qty'],
                'koli' => $resolved['koli'],
                'note' => $row['note'],
            ];
        })->values()->all();

        $validated['items'] = $normalized;
        $validated['supplier_id'] = $usesSupplier ? (int) ($validated['supplier_id'] ?? 0) : null;
        $validated['transacted_at'] = !empty($validated['transacted_at'])
            ? Carbon::parse($validated['transacted_at'])
            : null;
        $validated['surat_jalan_at'] = !empty($validated['surat_jalan_at'])
            ? Carbon::parse($validated['surat_jalan_at'])
            : null;

        return $validated;
    }

    private function importGroups(
        Request $request,
        object $import,
        string $type,
        string $prefix,
        string $successMessage,
        string $failureMessage
    ) {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'],
        ]);

        DB::beginTransaction();
        try {
            Excel::import($import, $request->file('file'));
            $groups = $import->groups ?? [];
            if (empty($groups)) {
                throw ValidationException::withMessages([
                    'file' => 'Tidak ada data valid untuk diimport',
                ]);
            }

            $createdTransactions = 0;
            $createdItems = 0;
            foreach ($groups as $group) {
                BundleService::assertPhysicalItems(
                    collect($group['items'] ?? [])->pluck('item_id')->all(),
                    'Bundle tidak bisa digunakan pada inbound karena tidak memiliki stok fisik.'
                );

                $transactedAt = $this->parseImportedDate($group['transacted_at'] ?? null, 'transacted_at');
                $suratJalanAt = $this->parseImportedDate($group['surat_jalan_at'] ?? null, 'surat_jalan_at', false);

                $transaction = InboundTransaction::create([
                    'code' => $this->generateCode($prefix),
                    'type' => $type,
                    'ref_no' => $group['ref_no'] ?? null,
                    'supplier_id' => $this->usesSupplier($type) ? ($group['supplier_id'] ?? null) : null,
                    'surat_jalan_no' => $group['surat_jalan_no'] ?? null,
                    'surat_jalan_at' => $suratJalanAt,
                    'note' => $group['note'] ?? null,
                    'warehouse_id' => WarehouseService::defaultWarehouseId(),
                    'transacted_at' => $transactedAt,
                    'created_by' => auth()->id(),
                    'status' => InboundScanStatus::PENDING_SCAN,
                ]);
                $createdTransactions++;

                foreach (($group['items'] ?? []) as $row) {
                    InboundItem::create([
                        'inbound_transaction_id' => $transaction->id,
                        'item_id' => $row['item_id'],
                        'qty' => $row['qty'],
                        'koli' => $row['koli'],
                        'note' => $row['note'] ?? null,
                    ]);
                    $createdItems++;
                }
            }

            DB::commit();

            return response()->json([
                'message' => $successMessage,
                'transactions' => $createdTransactions,
                'items' => $createdItems,
            ]);
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => $failureMessage,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function importFormItems(Request $request, string $type)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'],
        ]);

        try {
            $import = new InboundFormItemsImport();
            Excel::import($import, $request->file('file'));
            BundleService::assertPhysicalItems(
                collect($import->items)->pluck('item_id')->all(),
                'Bundle tidak bisa digunakan pada inbound karena tidak memiliki stok fisik.'
            );

            return response()->json([
                'message' => sprintf('Import item %s berhasil.', $this->typeOptions()[$type] ?? 'inbound'),
                'items' => collect($import->items)->map(fn (array $row) => [
                    'item_id' => (int) $row['item_id'],
                    'qty' => (int) $row['qty'],
                    'koli' => (int) ($row['koli'] ?? 0),
                    'note' => $row['note'] ?? null,
                    'sku' => $row['sku'],
                    'name' => $row['name'],
                ])->values(),
                'summary' => [
                    'count' => count($import->items),
                    'qty' => (int) collect($import->items)->sum('qty'),
                    'koli' => (int) collect($import->items)->sum('koli'),
                ],
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Gagal import item inbound.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function typeOptions(): array
    {
        return [
            'receipt' => 'Penerimaan Barang',
            'return' => 'Retur',
            'manual' => 'Manual',
            'opening' => 'Saldo Awal',
        ];
    }

    private function usesSupplier(string $type): bool
    {
        return $type === 'receipt';
    }

    private function applyDateFilter($query, Request $request): void
    {
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        try {
            if ($dateFrom) {
                $query->where('inbound_transactions.transacted_at', '>=', Carbon::parse($dateFrom)->startOfDay());
            }

            if ($dateTo) {
                $query->where('inbound_transactions.transacted_at', '<=', Carbon::parse($dateTo)->endOfDay());
            }
        } catch (\Throwable) {
            // Ignore invalid date filters.
        }
    }

    private function parseImportedDate(?string $value, string $field, bool $required = true): ?Carbon
    {
        $value = trim((string) $value);
        if ($value === '') {
            return $required ? now() : null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'file' => "Format {$field} tidak valid: {$value}",
            ]);
        }
    }

    private function generateCode(string $prefix): string
    {
        return $prefix.'-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));
    }

    private function qrTransaction(string $type, int $id): InboundTransaction
    {
        return InboundTransaction::with(['items.item', 'supplier'])
            ->where('type', $type)
            ->findOrFail($id);
    }

    private function receiptQrPayload(InboundTransaction $transaction): array
    {
        $barcodeService = app(SimpleBarcodeService::class);
        $items = $transaction->items
            ->filter(fn (InboundItem $row) => $row->item !== null && trim((string) $row->item->sku) !== '')
            ->values()
            ->map(function (InboundItem $row) {
                $item = $row->item;

                return [
                    'item_id' => $row->item_id,
                    'sku' => trim((string) ($item?->sku ?? '-')),
                    'name' => trim((string) ($item?->name ?? '-')),
                    'qty' => (int) ($row->qty ?? 0),
                    'koli' => (int) ($row->koli ?? 0),
                    'qr_url' => route('admin.masterdata.items.qr-code', ['item' => $row->item_id]),
                ];
            })
            ->all();

        return [
            'id' => $transaction->id,
            'code' => $transaction->code,
            'ref_no' => $transaction->ref_no,
            'supplier' => $transaction->supplier?->name,
            'transacted_at' => $transaction->transacted_at?->format('Y-m-d H:i'),
            'transacted_period' => $transaction->transacted_at?->format('m.y'),
            'items_count' => count($items),
            'items' => $items,
            'code_barcode_data_url' => 'data:image/png;base64,'.base64_encode(
                $barcodeService->pngForValue((string) $transaction->code, 560, 120)
            ),
            'pdf_url' => route('admin.inbound.receipts.qr-pdf', $transaction->id),
        ];
    }
}
