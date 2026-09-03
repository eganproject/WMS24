<?php

namespace App\Http\Controllers\Admin;

use App\Exports\InboundManualTemplateExport;
use App\Exports\InboundReceiptsExport;
use App\Exports\InboundReceiptTemplateExport;
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
use App\Support\InboundKoliUnitService;
use App\Support\InboundReceiptQrPdfService;
use App\Support\InboundScanExpectation;
use App\Support\InboundScanStatus;
use App\Support\Permission;
use App\Support\SimpleBarcodeService;
use App\Support\WarehouseService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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
        $pdf = $service->pdfForTransaction($transaction);

        return response(
            $pdf,
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$service->downloadFilename($transaction).'"',
                'Content-Length' => (string) strlen($pdf),
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
            ]
        );
    }

    public function suratJalanImage(int $id)
    {
        $transaction = InboundTransaction::findOrFail($id);
        $path = $this->storageRelativePath($transaction->surat_jalan_image_path);

        abort_if(!$path || !Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path, null, [
            'Cache-Control' => 'private, max-age=86400',
        ]);
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

    public function receiptsTemplate()
    {
        $filename = 'penerimaan-barang-template-'.now()->format('YmdHis').'.xlsx';

        return Excel::download(new InboundReceiptTemplateExport(), $filename);
    }

    public function receiptsExport(Request $request)
    {
        $filename = 'penerimaan-barang-'.now()->format('YmdHis').'.xlsx';

        return Excel::download(new InboundReceiptsExport($request->query()), $filename);
    }

    public function manualsImport(Request $request)
    {
        return $this->importGroups(
            $request,
            new InboundReceiptsImport(false),
            'manual',
            'MNL',
            'Import inbound manual berhasil',
            'Gagal import inbound manual'
        );
    }

    public function returnsImport(Request $request)
    {
        $warehouse = Warehouse::find((int) $request->input('warehouse_id'));
        $allowPcs = $warehouse && in_array($warehouse->code, [
            WarehouseService::displayWarehouseCode(),
            WarehouseService::damagedWarehouseCode(),
        ], true);

        return $this->importGroups(
            $request,
            new InboundReturnsImport($allowPcs),
            'return',
            'RET',
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
            'RCV',
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

        $defaultDateRange = $type === 'receipt' ? $this->defaultDateRange() : ['from' => null, 'to' => null];

        return view('admin.stock-flow.index', [
            'pageTitle' => $pageTitle,
            'dataUrl' => route("admin.inbound.{$routeBase}.data"),
            'defaultDateFrom' => $defaultDateRange['from'],
            'defaultDateTo' => $defaultDateRange['to'],
            'exportUrl' => $type === 'receipt' ? route('admin.inbound.receipts.export') : null,
            'storeUrl' => route("admin.inbound.{$routeBase}.store"),
            'showUrlTpl' => route("admin.inbound.{$routeBase}.show", ':id'),
            'updateUrlTpl' => route("admin.inbound.{$routeBase}.update", ':id'),
            'deleteUrlTpl' => route("admin.inbound.{$routeBase}.destroy", ':id'),
            'detailUrlTpl' => route("admin.inbound.{$routeBase}.detail", ':id'),
            'items' => $items,
            'warehouses' => $warehouses,
            'warehouseOptions' => $type === 'return'
                ? $warehouses->map(fn (Warehouse $warehouse) => [
                    'id' => $warehouse->id,
                    'name' => $warehouse->name,
                    'code' => $warehouse->code,
                ])->values()
                : collect(),
            'defaultWarehouseId' => WarehouseService::defaultWarehouseId(),
            'displayWarehouseId' => WarehouseService::displayWarehouseId(),
            'typeOptions' => $typeOptions,
            'typeDefault' => $type,
            'routeMap' => $routeMap,
            'enableKoli' => true,
            'koliFlowTypes' => [$type],
            'koliRequiresDefaultWarehouse' => false,
            'enableWarehouseSelect' => $type === 'return',
            'requireExplicitWarehouseSelection' => $type === 'return',
            'enableInputUnitSelect' => $type === 'return',
            'pcsInputWarehouseIds' => $type === 'return'
                ? $warehouses
                    ->whereIn('code', [WarehouseService::displayWarehouseCode(), WarehouseService::damagedWarehouseCode()])
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->values()
                : collect(),
            'showApproveAction' => false,
            'showScanProgressColumn' => true,
            'enhancedItemList' => $type === 'return',
            'statusLabels' => $statusLabels,
            'statusFilterOptions' => [
                InboundScanStatus::PENDING_SCAN => 'Menunggu Scan',
                InboundScanStatus::SCANNING => 'Sedang Scan',
                InboundScanStatus::COMPLETED => 'Scan Selesai',
                'approved' => 'Selesai / Approved',
            ],
            'lockedStatuses' => [InboundScanStatus::SCANNING, InboundScanStatus::COMPLETED, 'approved'],
            'showDeliveryNoteFields' => true,
            'deliveryNoteColumnLabel' => match ($type) {
                'return' => 'Referensi Retur',
                default => 'Surat Jalan',
            },
            'deliveryNoteNoLabel' => match ($type) {
                'return' => 'No Referensi Retur',
                default => 'No Surat Jalan',
            },
            'deliveryNoteDateLabel' => match ($type) {
                'return' => 'Tanggal Retur',
                default => 'Tanggal Surat Jalan',
            },
            'deliveryNoteImageLabel' => match ($type) {
                'return' => 'Gambar Barang',
                default => 'Gambar Surat Jalan',
            },
            'deliveryNoteImageLinkLabel' => match ($type) {
                'return' => 'Lihat Gambar Barang',
                default => 'Lihat Gambar',
            },
            'deliveryNotePrefixMap' => [
                'receipt' => 'SJ-RCV',
                'return' => 'SJ-RET',
                'manual' => 'SJ-MNL',
            ],
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
            'templateUrl' => match ($type) {
                'receipt' => route('admin.inbound.receipts.template'),
                'manual' => route('admin.inbound.manuals.template'),
                default => null,
            },
            'templateLabel' => match ($type) {
                'receipt' => 'Download Template Penerimaan Barang',
                'manual' => 'Download Template Inbound Manual',
                default => 'Download Template',
            },
            'templateNote' => match ($type) {
                'receipt' => 'Header: sku, qty atau koli, supplier. Opsional: ref_no, surat_jalan_no, surat_jalan_at, note, item_note, transacted_at.',
                'manual' => 'Header: sku, qty atau koli. Opsional: ref_no, surat_jalan_no, surat_jalan_at, note, item_note, transacted_at.',
                default => null,
            },
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
                'inbound_transactions.surat_jalan_image_path',
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

        $statusFilter = $request->input('status');
        if ($statusFilter !== null && $statusFilter !== '' && $statusFilter !== 'all') {
            $query->where('inbound_transactions.status', $statusFilter);
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
            $expectedKoli = (int) $items->sum(fn ($item) => ($item->input_unit ?: 'koli') === 'pcs'
                ? (int) ($item->qty ?? 0)
                : (int) ($item->koli ?? 0));
            $scannedQty = (int) $scanItems->sum('scanned_qty');
            $scannedKoli = (int) $scanItems->sum('scanned_koli');
            $itemDetails = $items->map(function (InboundItem $item) {
                return [
                    'sku' => $item->item?->sku ?? '-',
                    'name' => $item->item?->name ?? '-',
                    'qty' => (int) ($item->qty ?? 0),
                    'koli' => (int) ($item->koli ?? 0),
                    'input_unit' => $item->input_unit ?: 'koli',
                    'note' => $item->note ?? '',
                ];
            })->values();

            return [
                'id' => $row->id,
                'code' => $row->code,
                'transacted_at' => $ts,
                'submit_by' => $row->creator?->name ?? '-',
                'warehouse' => $row->warehouse?->name ?? $defaultWarehouseLabel,
                'warehouse_id' => $row->warehouse_id,
                'supplier' => $row->supplier?->name ?? '-',
                'item' => $labels->implode(', ') ?: '-',
                'item_details' => $itemDetails,
                'sku_count' => $itemDetails->count(),
                'qty' => $expectedQty,
                'scan_progress' => [
                    'unit_label' => $row->type === 'return' ? 'Unit' : 'Koli',
                    'expected_koli' => $expectedKoli,
                    'scanned_koli' => $scannedKoli,
                    'expected_qty' => $expectedQty,
                    'scanned_qty' => $scannedQty,
                ],
                'note' => $row->note ?? '',
                'surat_jalan_no' => $row->surat_jalan_no ?? '',
                'surat_jalan_at' => $row->surat_jalan_at?->format('Y-m-d') ?? '',
                'surat_jalan_image_url' => $this->suratJalanImageUrl($row),
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
            'surat_jalan_image_url' => $this->suratJalanImageUrl($transaction),
            'has_surat_jalan_image' => !empty($transaction->surat_jalan_image_path),
            'note' => $transaction->note,
            'status' => $transaction->status ?? InboundScanStatus::PENDING_SCAN,
            'warehouse_id' => $transaction->warehouse_id,
            'transacted_at' => $transaction->transacted_at?->format('Y-m-d\TH:i'),
            'items' => $transaction->items->map(function (InboundItem $item) {
                return [
                    'item_id' => $item->item_id,
                    'qty' => $item->qty,
                    'koli' => $item->koli,
                    'input_unit' => $item->input_unit ?: 'koli',
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
            'creator:id,name',
            'scanSession.items',
            'scanSession.starter:id,name',
            'scanSession.lastScanner:id,name',
            'scanSession.completer:id,name',
            'scanSession.resetter:id,name',
        ])->where('type', $type)->findOrFail($id);

        $totalQty = (int) $transaction->items->sum('qty');
        $totalKoli = (int) $transaction->items
            ->where('input_unit', 'koli')
            ->sum(fn ($row) => (int) ($row->koli ?? 0));
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
            'documentMode' => true,
            'documentTitle' => match ($type) {
                'receipt' => 'Dokumen Penerimaan Barang',
                'return' => 'Dokumen Retur Inbound',
                'manual' => 'Dokumen Inbound Manual',
                default => 'Dokumen Inbound',
            },
            'documentCodeLabel' => match ($type) {
                'receipt' => 'No. Penerimaan',
                'return' => 'No. Retur Inbound',
                'manual' => 'No. Inbound Manual',
                default => 'No. Inbound',
            },
        ]);
    }

    private function store(Request $request, string $type)
    {
        $validated = $this->validatePayload($request, $type);
        $prefix = $this->prefixForType($type);
        $storedSuratJalanImage = null;

        DB::beginTransaction();
        try {
            if ($request->hasFile('surat_jalan_image')) {
                $storedSuratJalanImage = $request->file('surat_jalan_image')->store($this->imageDirectoryForType($type), 'public');
            }

            $transaction = InboundTransaction::create([
                'code' => $this->generateCode($prefix),
                'type' => $type,
                'ref_no' => $validated['ref_no'] ?? null,
                'supplier_id' => $validated['supplier_id'] ?? null,
                'surat_jalan_no' => $this->resolveDeliveryNoteNo($validated['surat_jalan_no'] ?? null, 'SJ-'.$prefix),
                'surat_jalan_at' => $validated['surat_jalan_at'] ?? null,
                'surat_jalan_image_path' => $storedSuratJalanImage ? 'storage/'.$storedSuratJalanImage : null,
                'note' => $validated['note'] ?? null,
                'warehouse_id' => $type === 'return'
                    ? (int) $validated['warehouse_id']
                    : WarehouseService::defaultWarehouseId(),
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
                    'input_unit' => $row['input_unit'],
                    'note' => $row['note'] ?? null,
                ]);
            }

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            if ($storedSuratJalanImage) {
                Storage::disk('public')->delete($storedSuratJalanImage);
            }
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            if ($storedSuratJalanImage) {
                Storage::disk('public')->delete($storedSuratJalanImage);
            }

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
        $newSuratJalanImage = null;
        $oldSuratJalanImage = null;

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

            $update = [
                'ref_no' => $validated['ref_no'] ?? null,
                'supplier_id' => $validated['supplier_id'] ?? null,
                'surat_jalan_no' => $this->resolveDeliveryNoteNo($validated['surat_jalan_no'] ?? null, 'SJ-'.$this->prefixForType($type)),
                'surat_jalan_at' => $validated['surat_jalan_at'] ?? null,
                'note' => $validated['note'] ?? null,
                'warehouse_id' => $type === 'return'
                    ? (int) $validated['warehouse_id']
                    : $transaction->warehouse_id,
                'transacted_at' => $validated['transacted_at'] ?? $transaction->transacted_at,
            ];

            if ($request->hasFile('surat_jalan_image')) {
                $newSuratJalanImage = $request->file('surat_jalan_image')->store($this->imageDirectoryForType($type), 'public');
                $update['surat_jalan_image_path'] = 'storage/'.$newSuratJalanImage;
                $oldSuratJalanImage = $this->storageRelativePath($transaction->surat_jalan_image_path);
            } elseif ($request->boolean('remove_surat_jalan_image')) {
                $update['surat_jalan_image_path'] = null;
                $oldSuratJalanImage = $this->storageRelativePath($transaction->surat_jalan_image_path);
            }

            $transaction->update($update);

            foreach ($validated['items'] as $row) {
                InboundItem::create([
                    'inbound_transaction_id' => $transaction->id,
                    'item_id' => $row['item_id'],
                    'qty' => $row['qty'],
                    'koli' => $row['koli'],
                    'input_unit' => $row['input_unit'],
                    'note' => $row['note'] ?? null,
                ]);
            }

            DB::commit();

            if ($oldSuratJalanImage) {
                Storage::disk('public')->delete($oldSuratJalanImage);
            }
        } catch (ValidationException $e) {
            DB::rollBack();
            if ($newSuratJalanImage) {
                Storage::disk('public')->delete($newSuratJalanImage);
            }
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            if ($newSuratJalanImage) {
                Storage::disk('public')->delete($newSuratJalanImage);
            }

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

            $suratJalanImage = $this->storageRelativePath($transaction->surat_jalan_image_path);
            $transaction->delete();

            DB::commit();

            if ($suratJalanImage) {
                Storage::disk('public')->delete($suratJalanImage);
            }
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
            'items.*.input_unit' => $type === 'return'
                ? ['nullable', Rule::in(['pcs', 'koli'])]
                : ['nullable', Rule::in(['koli'])],
            'items.*.note' => ['nullable', 'string'],
            'ref_no' => ['nullable', 'string', 'max:100'],
            'supplier_id' => $usesSupplier
                ? ['required', 'integer', 'exists:suppliers,id']
                : ['nullable'],
            'surat_jalan_no' => ['nullable', 'string', 'max:100'],
            'surat_jalan_at' => ['nullable', 'date'],
            'surat_jalan_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_surat_jalan_image' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string'],
            'transacted_at' => ['required', 'date'],
            'warehouse_id' => $type === 'return'
                ? ['required', 'integer', 'exists:warehouses,id']
                : ['nullable', 'integer', 'exists:warehouses,id'],
        ]);

        if (!$usesSupplier && $request->filled('supplier_id')) {
            throw ValidationException::withMessages([
                'supplier_id' => 'Supplier hanya digunakan untuk inbound penerimaan barang.',
            ]);
        }

        $items = collect($validated['items'] ?? [])
            ->filter(fn ($row) => (int) ($row['qty'] ?? 0) > 0 && (int) ($row['item_id'] ?? 0) > 0)
            ->map(function ($row) use ($type) {
                return [
                    'item_id' => (int) $row['item_id'],
                    'qty' => (int) $row['qty'],
                    'koli' => isset($row['koli']) && (int) $row['koli'] > 0 ? (int) $row['koli'] : null,
                    'input_unit' => $type === 'return' && in_array(($row['input_unit'] ?? 'koli'), ['pcs', 'koli'], true)
                        ? ($row['input_unit'] ?? 'koli')
                        : 'koli',
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

        $destinationWarehouse = $type === 'return'
            ? Warehouse::find((int) ($validated['warehouse_id'] ?? 0))
            : null;
        $pcsWarehouseCodes = [WarehouseService::displayWarehouseCode(), WarehouseService::damagedWarehouseCode()];

        $normalized = $items->map(function ($row, $index) use ($itemMap, $type, $destinationWarehouse, $pcsWarehouseCodes) {
            $item = $itemMap->get($row['item_id']);
            if (!$item) {
                throw ValidationException::withMessages([
                    "items.{$index}.item_id" => 'Item inbound tidak ditemukan.',
                ]);
            }

            if ($row['input_unit'] === 'pcs') {
                if ($type !== 'return' || !$destinationWarehouse || !in_array($destinationWarehouse->code, $pcsWarehouseCodes, true)) {
                    throw ValidationException::withMessages([
                        "items.{$index}.input_unit" => 'Input PCS hanya diperbolehkan untuk Gudang Display atau Gudang Rusak. Gudang Besar wajib menggunakan Koli.',
                    ]);
                }

                $resolved = [
                    'qty' => (int) $row['qty'],
                    'koli' => null,
                ];
            } else {
                try {
                    $resolved = InboundScanExpectation::resolve($item, (int) $row['qty'], $row['koli']);
                } catch (ValidationException $e) {
                    $message = collect($e->errors())->flatten()->first() ?? $e->getMessage();
                    throw ValidationException::withMessages([
                        "items.{$index}.qty" => $message,
                        "items.{$index}.koli" => $message,
                    ]);
                }
            }

            return [
                'item_id' => (int) $row['item_id'],
                'qty' => $resolved['qty'],
                'koli' => $resolved['koli'],
                'input_unit' => $row['input_unit'],
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

    private function suratJalanImageUrl(InboundTransaction $transaction): ?string
    {
        if (!$transaction->surat_jalan_image_path) {
            return null;
        }

        return route('admin.inbound.surat-jalan-image', $transaction->id);
    }

    private function imageDirectoryForType(string $type): string
    {
        return $type === 'return'
            ? 'inbound-return-item-images'
            : 'inbound-surat-jalan';
    }

    private function storageRelativePath(?string $path): ?string
    {
        if (!$path || !str_starts_with($path, 'storage/')) {
            return null;
        }

        return Str::after($path, 'storage/');
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
            'warehouse_id' => $type === 'return'
                ? ['required', 'integer', 'exists:warehouses,id']
                : ['nullable', 'integer', 'exists:warehouses,id'],
        ]);

        $destinationWarehouseId = $type === 'return'
            ? (int) $request->input('warehouse_id')
            : WarehouseService::defaultWarehouseId();

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
                    'surat_jalan_no' => $this->resolveDeliveryNoteNo($group['surat_jalan_no'] ?? null, 'SJ-'.$prefix),
                    'surat_jalan_at' => $suratJalanAt,
                    'note' => $group['note'] ?? null,
                    'warehouse_id' => $destinationWarehouseId,
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
                        'input_unit' => $row['input_unit'] ?? 'koli',
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
            'warehouse_id' => $type === 'return'
                ? ['required', 'integer', 'exists:warehouses,id']
                : ['nullable', 'integer', 'exists:warehouses,id'],
        ]);

        try {
            $warehouse = $type === 'return' ? Warehouse::find((int) $request->input('warehouse_id')) : null;
            $allowPcs = $warehouse && in_array($warehouse->code, [
                WarehouseService::displayWarehouseCode(),
                WarehouseService::damagedWarehouseCode(),
            ], true);
            $import = new InboundFormItemsImport($type === 'return', $allowPcs);
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
                    'input_unit' => $row['input_unit'] ?? 'koli',
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

    /** Rentang tanggal awal halaman: 7 hari terakhir termasuk hari ini. */
    private function defaultDateRange(): array
    {
        return [
            'from' => now()->subDays(6)->format('Y-m-d'),
            'to' => now()->format('Y-m-d'),
        ];
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
        $prefix = preg_replace('/[^A-Z0-9-]+/i', '-', trim($prefix)) ?: 'INB';
        $prefix = trim(Str::upper($prefix), '-');

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $code = $prefix.'-'.now()->format('ymdHis').'-'.Str::upper(Str::random(3));
            if (str_starts_with($prefix, 'SJ-') || !InboundTransaction::where('code', $code)->exists()) {
                return $code;
            }
        }

        return $prefix.'-'.now()->format('ymdHis').'-'.Str::upper(Str::random(6));
    }

    private function prefixForType(string $type): string
    {
        return match ($type) {
            'receipt' => 'RCV',
            'return' => 'RET',
            default => 'MNL',
        };
    }

    private function resolveDeliveryNoteNo(?string $value, string $prefix): string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : $this->generateCode($prefix);
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
        app(InboundKoliUnitService::class)->syncForTransaction($transaction);
        $transaction->loadMissing(['items.koliUnits']);
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
                    'koli_qr_count' => (int) $row->koliUnits->count(),
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
