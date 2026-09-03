@extends('layouts.admin')

@section('title', $pageTitle)
@section('page_title', $pageTitle)

@php
    use App\Support\Permission as Perm;
    $permMap = [];
    if (isset($routeMap['receipt'])) {
        $permMap = [
            'receipt' => [
                'create' => Perm::can(auth()->user(), 'admin.inbound.receipts.index', 'create'),
                'update' => Perm::can(auth()->user(), 'admin.inbound.receipts.index', 'update'),
                'delete' => Perm::can(auth()->user(), 'admin.inbound.receipts.index', 'delete'),
            ],
            'return' => [
                'create' => Perm::can(auth()->user(), 'admin.inbound.returns.index', 'create'),
                'update' => Perm::can(auth()->user(), 'admin.inbound.returns.index', 'update'),
                'delete' => Perm::can(auth()->user(), 'admin.inbound.returns.index', 'delete'),
            ],
            'manual' => [
                'create' => Perm::can(auth()->user(), 'admin.inbound.manuals.index', 'create'),
                'update' => Perm::can(auth()->user(), 'admin.inbound.manuals.index', 'update'),
                'delete' => Perm::can(auth()->user(), 'admin.inbound.manuals.index', 'delete'),
            ],
        ];
    } elseif (isset($routeMap['picker'])) {
        $permMap = [
            'picker' => [
                'create' => Perm::can(auth()->user(), 'admin.outbound.pickers.index', 'create'),
                'update' => Perm::can(auth()->user(), 'admin.outbound.pickers.index', 'update'),
                'delete' => Perm::can(auth()->user(), 'admin.outbound.pickers.index', 'delete'),
            ],
            'manual' => [
                'create' => Perm::can(auth()->user(), 'admin.outbound.manuals.index', 'create'),
                'update' => Perm::can(auth()->user(), 'admin.outbound.manuals.index', 'update'),
                'delete' => Perm::can(auth()->user(), 'admin.outbound.manuals.index', 'delete'),
            ],
            'return' => [
                'create' => Perm::can(auth()->user(), 'admin.outbound.returns.index', 'create'),
                'update' => Perm::can(auth()->user(), 'admin.outbound.returns.index', 'update'),
                'delete' => Perm::can(auth()->user(), 'admin.outbound.returns.index', 'delete'),
            ],
        ];
    }
    $defaultType = $typeDefault ?? '';
    $canCreateDefault = $permMap[$defaultType]['create'] ?? false;
    $canImport = !empty($importUrl ?? null) && $canCreateDefault;
@endphp

@if(!empty($enhancedItemList ?? false))
    @push('styles')
    <style>
        .return-in-list-card {
            background: linear-gradient(180deg, #ffffff 0%, #f8faff 100%);
            overflow: hidden;
        }

        .return-in-list-card > .card-header {
            gap: 1rem;
            background: rgba(255, 255, 255, 0.92);
            border-bottom: 1px solid #eef2f7 !important;
        }

        .return-in-list-card .card-toolbar,
        .return-in-list-card .card-toolbar > .d-flex {
            flex-wrap: wrap;
        }

        .return-in-table-wrap {
            padding: 0.25rem;
        }

        #stock_flow_table.return-in-table {
            width: 100% !important;
            min-width: 0;
            table-layout: fixed;
            border-collapse: separate !important;
            border-spacing: 0 0.7rem !important;
        }

        #stock_flow_table.return-in-table thead th {
            border: 0 !important;
            padding-top: 0;
            padding-bottom: 0.35rem;
            white-space: nowrap;
        }

        #stock_flow_table.return-in-table tbody td {
            padding-top: 1rem;
            padding-bottom: 1rem;
            vertical-align: middle;
            background: #fff;
            border-top: 1px solid #edf1f7 !important;
            border-bottom: 1px solid #edf1f7 !important;
            white-space: normal;
        }

        #stock_flow_table.return-in-table tbody td:first-child {
            border-left: 1px solid #edf1f7 !important;
            border-radius: 0.9rem 0 0 0.9rem;
        }

        #stock_flow_table.return-in-table tbody td:last-child {
            border-right: 1px solid #edf1f7 !important;
            border-radius: 0 0.9rem 0.9rem 0;
        }

        #stock_flow_table.return-in-table tbody tr:hover td {
            background: #fbfcff;
            border-color: #dce9fb !important;
        }

        #stock_flow_table.return-in-table .stock-flow-item-column {
            width: 32%;
        }

        #stock_flow_table.return-in-table .return-in-document-cell { width: 17%; }
        #stock_flow_table.return-in-table .return-in-status-cell { width: 10%; }
        #stock_flow_table.return-in-table .return-in-reference-cell { width: 18%; }
        #stock_flow_table.return-in-table .return-in-progress-cell { width: 14%; }

        #stock_flow_table.return-in-table .return-in-action-cell {
            position: sticky;
            right: 0;
            z-index: 2;
            width: 9%;
            min-width: 92px;
            box-shadow: -10px 0 18px -18px rgba(24, 28, 50, 0.75);
        }

        #stock_flow_table.return-in-table thead .return-in-action-cell {
            z-index: 3;
            background: #f8faff;
        }

        .return-in-document {
            min-width: 0;
        }

        .return-in-document__code {
            display: block;
            overflow: hidden;
            color: #181c32;
            font-weight: 700;
            line-height: 1.35;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .return-in-document__meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.25rem 0.55rem;
            margin-top: 0.35rem;
            color: #7e8299;
            font-size: 0.72rem;
            line-height: 1.35;
        }

        .return-in-document__note {
            display: -webkit-box;
            margin-top: 0.55rem;
            overflow: hidden;
            color: #5e6278;
            font-size: 0.74rem;
            line-height: 1.35;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
        }

        .return-in-reference {
            display: grid;
            gap: 0.4rem;
            min-width: 0;
        }

        .return-in-reference__number {
            display: block;
            overflow: hidden;
            color: #3f4254;
            font-size: 0.78rem;
            font-weight: 600;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .return-in-reference__meta {
            color: #7e8299;
            font-size: 0.7rem;
        }

        .return-in-reference__image {
            width: fit-content;
            font-size: 0.68rem;
        }

        .return-in-item-card {
            display: block;
            width: 100%;
            min-width: 0;
            padding: 0;
            overflow: hidden;
            text-align: left;
            color: #3f4254;
            background: #fff;
            border: 1px solid #e7ecf4;
            border-radius: 0.85rem;
            transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
        }

        .return-in-item-card:hover,
        .return-in-item-card:focus-visible {
            border-color: #9ec5fe;
            box-shadow: 0 0.45rem 1.1rem rgba(0, 82, 204, 0.10);
            transform: translateY(-1px);
            outline: 0;
        }

        .return-in-item-card__header,
        .return-in-item-card__footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
        }

        .return-in-item-card__header {
            padding: 0.7rem 0.8rem;
            background: #f1f7ff;
            border-bottom: 1px solid #e3edfb;
        }

        .return-in-item-card__title {
            color: #181c32;
            font-weight: 700;
        }

        .return-in-item-card__body {
            display: grid;
            gap: 0.5rem;
            padding: 0.65rem 0.8rem;
        }

        .return-in-item-line {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 0.75rem;
            align-items: center;
        }

        .return-in-item-line + .return-in-item-line {
            padding-top: 0.5rem;
            border-top: 1px dashed #e7ecf4;
        }

        .return-in-item-line__identity {
            min-width: 0;
        }

        .return-in-item-line__sku {
            display: block;
            color: #009ef7;
            font-size: 0.78rem;
            font-weight: 700;
            line-height: 1.2;
        }

        .return-in-item-line__name {
            display: block;
            margin-top: 0.15rem;
            overflow: hidden;
            color: #5e6278;
            font-size: 0.78rem;
            line-height: 1.25;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .return-in-item-line__amount {
            text-align: right;
            white-space: nowrap;
        }

        .return-in-item-line__amount strong {
            display: block;
            color: #181c32;
            font-size: 0.85rem;
        }

        .return-in-item-line__amount small {
            display: block;
            margin-top: 0.1rem;
            color: #7e8299;
            font-size: 0.7rem;
        }

        .return-in-item-card__footer {
            padding: 0.55rem 0.8rem;
            color: #009ef7;
            background: #fbfcff;
            border-top: 1px solid #edf1f7;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .return-in-total-qty {
            min-width: 76px;
            padding: 0.55rem 0.65rem;
            text-align: center;
            background: #f1f7ff;
            border: 1px solid #dce9fb;
            border-radius: 0.75rem;
        }

        .return-in-total-qty strong,
        .return-in-total-qty span {
            display: block;
        }

        .return-in-total-qty strong {
            color: #181c32;
            font-size: 1rem;
            line-height: 1.1;
        }

        .return-in-total-qty span {
            margin-top: 0.2rem;
            color: #7e8299;
            font-size: 0.68rem;
            font-weight: 700;
        }

        .return-in-note {
            max-width: 220px;
            padding: 0.55rem 0.65rem;
            color: #5e6278;
            background: #fff8dd;
            border-radius: 0.7rem;
            font-size: 0.78rem;
            line-height: 1.4;
        }

        .return-in-detail-table thead th {
            position: sticky;
            top: 0;
            z-index: 1;
            padding: 0.85rem 0.75rem;
            background: #f5f8fa;
            white-space: nowrap;
        }

        .return-in-detail-table tbody td {
            padding: 0.85rem 0.75rem;
            vertical-align: middle;
        }

        .return-in-detail-table tbody tr:nth-child(even) td {
            background: #fbfcff;
        }

        @media (max-width: 991.98px) {
            .return-in-list-card .card-header,
            .return-in-list-card .card-title,
            .return-in-list-card .card-toolbar {
                width: 100%;
            }

            .return-in-list-card .card-toolbar > .d-flex {
                margin-right: 0 !important;
            }

            #stock_flow_table.return-in-table {
                min-width: 850px;
            }
        }
    </style>
    @endpush
@endif

@section('content')
<div class="card {{ !empty($enhancedItemList ?? false) ? 'return-in-list-card' : '' }}">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div class="d-flex align-items-center position-relative my-1">
                <span class="svg-icon svg-icon-1 position-absolute ms-6">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <rect opacity="0.5" x="17.0365" y="15.1223" width="8.15546" height="2" rx="1" transform="rotate(45 17.0365 15.1223)" fill="black" />
                        <path d="M11 19C6.55556 19 3 15.4444 3 11C3 6.55556 6.55556 3 11 3C15.4444 3 19 6.55556 19 11C19 15.4444 15.4444 19 11 19ZM11 5C7.53333 5 5 7.53333 5 11C5 14.4667 7.53333 17 11 17C14.4667 17 17 14.4667 17 11C17 7.53333 14.4667 5 11 5Z" fill="black" />
                    </svg>
                </span>
                <input type="text" class="form-control form-control-solid w-250px ps-14" placeholder="Search" data-kt-filter="search" value="{{ $initialSearch ?? '' }}" />
            </div>
        </div>
        <div class="card-toolbar">
            @if(!empty($supplierManageUrl ?? null))
                <a href="{{ $supplierManageUrl }}" class="btn btn-light-info me-3">
                    Kelola Supplier
                </a>
            @endif
            <div class="d-flex align-items-center gap-2 me-4">
                @if(!empty($warehouses ?? []))
                    <select class="form-select form-select-solid w-200px" id="filter_warehouse">
                        <option value="all">Semua Gudang</option>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}" @selected((string) ($initialWarehouseId ?? '') === (string) $wh->id)>{{ $wh->name }}</option>
                        @endforeach
                    </select>
                @endif
                <select class="form-select form-select-solid w-200px" id="filter_status">
                    <option value="all">Semua Status</option>
                    @foreach(($statusFilterOptions ?? []) as $statusValue => $statusLabel)
                        <option value="{{ $statusValue }}" @selected((string) ($initialStatus ?? '') === (string) $statusValue)>{{ $statusLabel }}</option>
                    @endforeach
                </select>
                <input type="text" class="form-control form-control-solid w-150px" id="filter_date_from" placeholder="Dari" value="{{ $defaultDateFrom ?? '' }}" />
                <input type="text" class="form-control form-control-solid w-150px" id="filter_date_to" placeholder="Sampai" value="{{ $defaultDateTo ?? '' }}" />
                <button type="button" class="btn btn-light" id="filter_apply">Filter</button>
                <button type="button" class="btn btn-light" id="filter_reset">Reset</button>
            </div>
            @if(!empty($exportUrl ?? null))
                <button type="button" class="btn btn-light-success me-3" id="btn_export_flow">
                    Export Excel
                </button>
            @endif
            @if($canImport)
                <button type="button" class="btn btn-light-primary me-3" id="btn_import_flow" data-bs-toggle="modal" data-bs-target="#modal_import_flow">
                    Import Excel
                </button>
            @endif
            @if($canCreateDefault)
                <button type="button" class="btn btn-primary" id="btn_open_create_flow" data-bs-toggle="modal" data-bs-target="#modal_stock_flow">
                    Tambah
                </button>
            @endif
        </div>
    </div>
    <div class="card-body py-6">
        <div class="table-responsive {{ !empty($enhancedItemList ?? false) ? 'return-in-table-wrap' : '' }}">
            <table class="table align-middle table-row-dashed fs-6 gy-5 {{ !empty($enhancedItemList ?? false) ? 'return-in-table' : '' }}" id="stock_flow_table">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                        <th>ID</th>
                        <th class="{{ !empty($enhancedItemList ?? false) ? 'return-in-document-cell' : '' }}">{{ !empty($enhancedItemList ?? false) ? 'Dokumen Retur' : 'Kode' }}</th>
                        <th>Jenis</th>
                        <th class="{{ !empty($enhancedItemList ?? false) ? 'return-in-status-cell' : '' }}">Status</th>
                        <th>Tanggal</th>
                        <th>Submit By</th>
                        <th class="{{ !empty($enhancedItemList ?? false) ? 'return-in-reference-cell' : '' }}">{{ !empty($enhancedItemList ?? false) ? 'Gudang & Referensi' : 'Gudang' }}</th>
                        @if(!empty($showDeliveryNoteFields ?? false))
                            <th>{{ $deliveryNoteColumnLabel ?? 'Surat Jalan' }}</th>
                        @endif
                        @if(!empty($showSupplierColumn ?? false))
                            <th>Supplier</th>
                        @endif
                        @if(!empty($showRecipientFields ?? false))
                            <th>Penerima</th>
                        @endif
                        <th class="stock-flow-item-column">{{ !empty($enhancedItemList ?? false) ? 'Ringkasan Item Retur' : 'Item' }}</th>
                        <th>Qty</th>
                        @if(!empty($showScanProgressColumn ?? false))
                            <th class="{{ !empty($enhancedItemList ?? false) ? 'return-in-progress-cell' : '' }}">Progress Scan</th>
                        @endif
                        <th>Catatan</th>
                        <th class="text-end {{ !empty($enhancedItemList ?? false) ? 'return-in-action-cell' : '' }}">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modal_stock_flow" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable mw-900px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bolder" id="flow_modal_title">Tambah</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <span class="svg-icon svg-icon-1">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black" />
                            <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black" />
                        </svg>
                    </span>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <form class="form" id="stock_flow_form">
                    @csrf
                    <div class="alert alert-primary p-5 mb-7">
                        <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap mb-2">
                            <div class="fw-bold">Input Cepat Item</div>
                            <button type="button" class="btn btn-sm btn-light" id="btn_toggle_quick_items">Sembunyikan Form</button>
                        </div>
                        <div id="flow_quick_items_body">
                            <div class="text-muted fs-7 mb-3" id="flow_quick_item_hint">
                                Tulis satu item per baris. Format: SKU koli/qty catatan.
                            </div>
                            @if(!empty($enableInputUnitSelect ?? false))
                                <div class="w-200px mb-3">
                                    <label class="form-label fw-bold mb-1">Satuan Input Cepat</label>
                                    <select class="form-select form-select-solid" id="flow_quick_input_unit">
                                        <option value="koli" selected>Koli</option>
                                        <option value="pcs">PCS</option>
                                    </select>
                                    <div class="form-text">PCS hanya untuk Gudang Display atau Gudang Rusak.</div>
                                </div>
                            @endif
                            <textarea class="form-control form-control-solid mb-3" id="flow_quick_items" rows="4" placeholder="SKU-001 2&#10;SKU-002 5 Catatan item"></textarea>
                            <div class="d-flex align-items-center gap-3 flex-wrap">
                                <button type="button" class="btn btn-light-primary" id="btn_add_quick_items">Tambahkan dari Teks</button>
                                <div class="text-danger fs-7" id="flow_quick_items_error"></div>
                            </div>
                        </div>
                    </div>
                    <div id="flow_items_container"></div>
                    <div class="mb-7 d-flex flex-wrap gap-3">
                        <button type="button" class="btn btn-light" id="btn_add_flow_item">Tambah Item</button>
                        @if(!empty($itemImportUrl ?? null))
                            <button type="button" class="btn btn-light-primary" id="btn_open_item_import">
                                Import Item Excel
                            </button>
                        @endif
                    </div>
                    @if(count($warehouseOptions ?? []) > 0)
                        <div class="fv-row mb-7" id="flow_warehouse_row" style="display:none;">
                            <label class="required fs-6 fw-bold form-label mb-2">Gudang Tujuan</label>
                            <select class="form-select form-select-solid" name="warehouse_id" id="flow_warehouse_id">
                                <option value="">Pilih Gudang</option>
                                @foreach($warehouseOptions as $wh)
                                    <option value="{{ $wh['id'] }}">{{ $wh['name'] }}</option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback" id="error_warehouse_id"></div>
                        </div>
                    @endif
                    <div class="fv-row mb-7">
                        <label class="fs-6 fw-bold form-label mb-2">Tanggal</label>
                        <input type="text" class="form-control form-control-solid" name="transacted_at" id="flow_transacted_at" placeholder="YYYY-MM-DD HH:mm" />
                        <div class="invalid-feedback" id="error_transacted_at"></div>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fs-6 fw-bold form-label mb-2">Ref No</label>
                        <input type="text" class="form-control form-control-solid" name="ref_no" id="flow_ref_no" />
                        <div class="invalid-feedback" id="error_ref_no"></div>
                    </div>
                    @if(!empty($showRecipientFields ?? false))
                        <div id="flow_recipient_fields">
                            <div class="row g-4 mb-7">
                                <div class="col-md-6">
                                    <label class="fs-6 fw-bold form-label mb-2">Nama Penerima</label>
                                    <input type="text" class="form-control form-control-solid" name="recipient_name" id="flow_recipient_name" />
                                    <div class="invalid-feedback" id="error_recipient_name"></div>
                                </div>
                                <div class="col-md-6">
                                    <label class="fs-6 fw-bold form-label mb-2">Telepon/Kontak Penerima</label>
                                    <input type="text" class="form-control form-control-solid" name="recipient_phone" id="flow_recipient_phone" />
                                    <div class="invalid-feedback" id="error_recipient_phone"></div>
                                </div>
                            </div>
                            <div class="fv-row mb-7">
                                <label class="fs-6 fw-bold form-label mb-2">Alamat Penerima</label>
                                <textarea class="form-control form-control-solid" name="recipient_address" id="flow_recipient_address" rows="3"></textarea>
                                <div class="invalid-feedback" id="error_recipient_address"></div>
                            </div>
                        </div>
                    @endif
                    @if(isset($supplierFlowTypes) && count($supplierFlowTypes) > 0)
                        <div class="fv-row mb-7" id="flow_supplier_row" style="display:none;">
                            <label class="fs-6 fw-bold form-label mb-2">Supplier</label>
                            <select class="form-select form-select-solid" name="supplier_id" id="flow_supplier_id">
                                <option value="">Pilih Supplier</option>
                                @foreach(($suppliers ?? []) as $supplier)
                                    <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback" id="error_supplier_id"></div>
                        </div>
                    @endif
                    @if(!empty($showDeliveryNoteFields ?? false))
                        <div class="fv-row mb-7">
                            <label class="fs-6 fw-bold form-label mb-2">{{ $deliveryNoteNoLabel ?? 'No Surat Jalan' }}</label>
                            <input type="text" class="form-control form-control-solid" name="surat_jalan_no" id="flow_surat_jalan_no" />
                            <div class="invalid-feedback" id="error_surat_jalan_no"></div>
                        </div>
                        <div class="fv-row mb-7">
                            <label class="fs-6 fw-bold form-label mb-2">{{ $deliveryNoteDateLabel ?? 'Tanggal Surat Jalan' }}</label>
                            <input type="text" class="form-control form-control-solid" name="surat_jalan_at" id="flow_surat_jalan_at" placeholder="YYYY-MM-DD" />
                            <div class="invalid-feedback" id="error_surat_jalan_at"></div>
                        </div>
                        <div class="fv-row mb-7">
                            <label class="fs-6 fw-bold form-label mb-2">{{ $deliveryNoteImageLabel ?? 'Gambar Surat Jalan' }}</label>
                            <input type="file" class="form-control form-control-solid" name="surat_jalan_image" id="flow_surat_jalan_image" accept="image/jpeg,image/png,image/webp" />
                            <div class="form-text">Opsional. Format JPG, PNG, atau WEBP. Maksimal 2 MB.</div>
                            <div class="invalid-feedback d-block" id="error_surat_jalan_image"></div>
                            <div class="mt-3" id="flow_surat_jalan_image_preview" style="display:none;">
                                <a href="#" target="_blank" rel="noopener" class="btn btn-light-primary btn-sm" id="flow_surat_jalan_image_link">{{ $deliveryNoteImageLinkLabel ?? 'Lihat gambar saat ini' }}</a>
                                <label class="form-check form-check-sm form-check-custom form-check-solid mt-3">
                                    <input class="form-check-input" type="checkbox" name="remove_surat_jalan_image" id="flow_remove_surat_jalan_image" value="1" />
                                    <span class="form-check-label">Hapus gambar saat ini</span>
                                </label>
                            </div>
                        </div>
                    @endif
                    <div class="fv-row mb-7">
                        <label class="fs-6 fw-bold form-label mb-2">Catatan</label>
                        <textarea class="form-control form-control-solid" name="note" id="flow_note" rows="3"></textarea>
                        <div class="invalid-feedback" id="error_note"></div>
                    </div>
                    <div class="text-end pt-3">
                        <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">
                            <span class="indicator-label">Simpan</span>
                            <span class="indicator-progress">Please wait...
                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@if($canImport)
    <div class="modal fade" id="modal_import_flow" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered mw-650px">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="fw-bolder">{{ $importTitle ?? 'Import Data' }}</h2>
                    <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                        <span class="svg-icon svg-icon-1">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black" />
                                <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black" />
                            </svg>
                        </span>
                    </div>
                </div>
                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    <div class="mb-6">
                        <div class="text-muted fs-7">
                            @if(!empty($allowKoliImport ?? ($enableKoli ?? false)))
                                Header minimal: <strong>sku</strong>, <strong>qty</strong> atau <strong>koli</strong>
                            @else
                                Header minimal: <strong>sku</strong>, <strong>qty</strong>
                            @endif
                            @if(!empty($importRequiresSupplier ?? false))
                                , <strong>supplier</strong>
                            @endif
                            .<br>
                            Opsional: <strong>ref_no</strong>
                            @if(!empty($showDeliveryNoteFields ?? false))
                                , <strong>surat_jalan_no</strong>, <strong>surat_jalan_at</strong>
                            @endif
                            , <strong>note</strong>, <strong>item_note</strong>, <strong>transacted_at</strong>
                            @if(!empty($enableInputUnitSelect ?? false))
                                , <strong>input_unit</strong> (isi <strong>pcs</strong> atau <strong>koli</strong>)
                            @endif
                            @if(!empty($showRecipientFields ?? false))
                                , <strong>recipient_name</strong>, <strong>recipient_phone</strong>, <strong>recipient_address</strong>
                            @endif
                            .
                        </div>
                        @if(!empty($templateUrl ?? null))
                            <div class="mt-3">
                                @if(!empty($templateNote ?? null))
                                    <div class="text-muted fs-7 mb-2">{{ $templateNote }}</div>
                                @endif
                                <a href="{{ $templateUrl }}" class="btn btn-light-primary btn-sm">
                                    <i class="fas fa-file-excel me-2"></i>{{ $templateLabel ?? 'Download Template' }}
                                </a>
                            </div>
                        @endif
                    </div>
                    <div class="fv-row mb-6">
                        <label class="required fs-6 fw-bold form-label mb-2">File Excel</label>
                        <input type="file" class="form-control form-control-solid" id="import_flow_file" accept=".xlsx,.xls" />
                        <div class="invalid-feedback d-block" id="error_import_flow_file"></div>
                    </div>
                    @if(!empty($requireExplicitWarehouseSelection ?? false) && count($warehouseOptions ?? []) > 0)
                        <div class="fv-row mb-6">
                            <label class="required fs-6 fw-bold form-label mb-2">Gudang Tujuan Import</label>
                            <select class="form-select form-select-solid" id="import_flow_warehouse_id">
                                <option value="">Pilih Gudang</option>
                                @foreach($warehouseOptions as $wh)
                                    <option value="{{ $wh['id'] }}">{{ $wh['name'] }}</option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback d-block" id="error_import_flow_warehouse_id"></div>
                            <div class="form-text">Seluruh transaksi dalam file ini akan masuk ke gudang yang dipilih.</div>
                        </div>
                    @endif
                    <div class="text-end">
                        <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Batal</button>
                        <button type="button" class="btn btn-primary" id="btn_import_flow_submit">Import</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif

@if(!empty($itemImportUrl ?? null))
    <div class="modal fade" id="modal_import_flow_items" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered mw-650px">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="fw-bolder">Import Item ke Form</h2>
                    <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                        <span class="svg-icon svg-icon-1">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black" />
                                <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black" />
                            </svg>
                        </span>
                    </div>
                </div>
                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    <div class="alert alert-light-primary border border-primary border-dashed mb-6">
                        <div class="fw-bold mb-2">Format file item</div>
                        <div class="text-muted fs-7">
                            Header minimal: <strong>sku</strong>, <strong>qty</strong> atau <strong>koli</strong>.<br>
                            @if(!empty($enableInputUnitSelect ?? false))
                                Opsional: <strong>input_unit</strong> (<strong>pcs</strong>/<strong>koli</strong>). Pilih gudang tujuan pada form terlebih dahulu.<br>
                            @endif
                            Opsional: <strong>item_note</strong> atau <strong>note</strong>.<br>
                            Import ini hanya mengisi daftar item di form aktif dan akan mengganti item yang sedang ada setelah Anda konfirmasi.
                        </div>
                        @if(!empty($itemTemplateUrl ?? null))
                            <div class="mt-4">
                                <a href="{{ $itemTemplateUrl }}" class="btn btn-light-primary btn-sm">
                                    <i class="fas fa-file-excel me-2"></i>Unduh Template Item
                                </a>
                            </div>
                        @endif
                    </div>
                    <div class="fv-row mb-6">
                        <label class="required fs-6 fw-bold form-label mb-2">File Excel Item</label>
                        <input type="file" class="form-control form-control-solid" id="import_flow_items_file" accept=".xlsx,.xls" />
                        <div class="invalid-feedback d-block" id="error_import_flow_items_file"></div>
                    </div>
                    <div class="text-end">
                        <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Batal</button>
                        <button type="button" class="btn btn-primary" id="btn_import_flow_items_submit">Import ke Form</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif

<div class="modal fade" id="modal_flow_item_detail" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered {{ !empty($enhancedItemList ?? false) ? 'modal-xl' : 'modal-lg' }} modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 class="fw-bolder mb-1" id="flow_item_detail_title">{{ !empty($enhancedItemList ?? false) ? 'Rincian Item Retur' : 'Detail Item' }}</h2>
                    <div class="text-muted" id="flow_item_detail_subtitle">-</div>
                </div>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <span class="svg-icon svg-icon-1">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black" />
                            <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black" />
                        </svg>
                    </span>
                </div>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle fs-7 mb-0 {{ !empty($enhancedItemList ?? false) ? 'return-in-detail-table' : '' }}">
                        <thead>
                            <tr class="text-gray-400 fw-bolder text-uppercase">
                                @if(!empty($enhancedItemList ?? false))
                                    <th class="text-center">No</th>
                                @endif
                                <th>SKU</th>
                                <th>Nama Item</th>
                                <th class="text-end" data-flow-item-detail-koli-head style="display:none;">{{ !empty($enableInputUnitSelect ?? false) ? 'Input' : 'Koli' }}</th>
                                <th class="text-end">{{ !empty($enableInputUnitSelect ?? false) ? 'Qty PCS' : 'Qty' }}</th>
                                <th>Catatan</th>
                            </tr>
                        </thead>
                        <tbody id="flow_item_detail_rows"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    const dataUrl = '{{ $dataUrl }}';
    const storeUrl = '{{ $storeUrl }}';
    const showUrlTpl = '{{ $showUrlTpl }}';
    const updateUrlTpl = '{{ $updateUrlTpl }}';
    const deleteUrlTpl = '{{ $deleteUrlTpl }}';
    const detailUrlTpl = '{{ $detailUrlTpl }}';
    const approveUrlTpl = '{{ $approveUrlTpl ?? '' }}';
    const importUrl = '{{ $importUrl ?? '' }}';
    const itemImportUrl = '{{ $itemImportUrl ?? '' }}';
    const routeMap = @json($routeMap ?? []);
    const typeLabelMap = @json($typeOptions ?? []);
    const csrfToken = '{{ csrf_token() }}';
    const itemOptionsHtml = `@foreach($items as $item)<option value="{{ $item->id }}" data-sku="{{ $item->sku }}" data-name="{{ $item->name }}" data-koli-qty="{{ (int) ($item->koli_qty ?? 0) }}" data-item-type="{{ $item->item_type ?? 'single' }}">{{ $item->sku }} - {{ $item->name }}@if(($item->item_type ?? 'single') === 'bundle') [Bundle]@endif</option>@endforeach`;
    const itemCatalog = {!! ($items ?? collect())->map(function ($item) {
        return [
            'id' => $item->id,
            'sku' => $item->sku,
            'name' => $item->name,
            'koli_qty' => (int) ($item->koli_qty ?? 0),
            'item_type' => $item->item_type ?? 'single',
        ];
    })->values()->toJson() !!};
    const defaultTypeFilter = '{{ $typeDefault ?? '' }}';
    const permMap = @json($permMap ?? []);
    const canCreateDefault = {{ $canCreateDefault ? 'true' : 'false' }};
    const enableKoli = {{ !empty($enableKoli ?? false) ? 'true' : 'false' }};
    const enableInputUnitSelect = {{ !empty($enableInputUnitSelect ?? false) ? 'true' : 'false' }};
    const pcsInputWarehouseIds = @json($pcsInputWarehouseIds ?? []);
    const koliFlowTypes = @json($koliFlowTypes ?? []);
    const koliRequiresDefaultWarehouse = {{ !empty($koliRequiresDefaultWarehouse ?? false) ? 'true' : 'false' }};
    const enableWarehouseSelect = {{ !empty($enableWarehouseSelect ?? false) ? 'true' : 'false' }};
    const supplierFlowTypes = @json($supplierFlowTypes ?? []);
    const displayWarehouseId = {{ isset($displayWarehouseId) ? (int) $displayWarehouseId : 'null' }};
    const defaultWarehouseId = {{ isset($defaultWarehouseId) ? (int) $defaultWarehouseId : 'null' }};
    const statusLabels = @json($statusLabels ?? []);
    const lockedStatuses = @json($lockedStatuses ?? ['approved']);
    const deleteLockedStatuses = @json($deleteLockedStatuses ?? ($lockedStatuses ?? ['approved']));
    const showApproveAction = {{ isset($showApproveAction) ? ($showApproveAction ? 'true' : 'false') : 'true' }};
    const deleteWarningText = @json($deleteWarningText ?? 'Data akan dihapus dan stok akan dikembalikan');
    const showScanProgressColumn = {{ !empty($showScanProgressColumn ?? false) ? 'true' : 'false' }};
    const deliveryNotePrefixMap = @json($deliveryNotePrefixMap ?? []);
    const deliveryNoteImageLinkLabel = @json($deliveryNoteImageLinkLabel ?? 'Lihat Gambar');
    const showRecipientFields = {{ !empty($showRecipientFields ?? false) ? 'true' : 'false' }};
    const requireExplicitWarehouseSelection = {{ !empty($requireExplicitWarehouseSelection ?? false) ? 'true' : 'false' }};
    const enhancedItemList = {{ !empty($enhancedItemList ?? false) ? 'true' : 'false' }};
    const exportUrl = @json($exportUrl ?? null);
    const defaultDateFrom = @json($defaultDateFrom ?? '');
    const defaultDateTo = @json($defaultDateTo ?? '');

    document.addEventListener('DOMContentLoaded', () => {
        const tableEl = $('#stock_flow_table');
        const searchInput = document.querySelector('[data-kt-filter="search"]');
        const form = document.getElementById('stock_flow_form');
        const modalEl = document.getElementById('modal_stock_flow');
        const modal = modalEl ? new bootstrap.Modal(modalEl) : null;
        const modalContentEl = modalEl?.querySelector('.modal-content') || modalEl;
        const itemDetailModalEl = document.getElementById('modal_flow_item_detail');
        const itemDetailModal = itemDetailModalEl ? new bootstrap.Modal(itemDetailModalEl) : null;
        const itemDetailTitle = document.getElementById('flow_item_detail_title');
        const itemDetailSubtitle = document.getElementById('flow_item_detail_subtitle');
        const itemDetailRows = document.getElementById('flow_item_detail_rows');
        const itemDetailKoliHead = document.querySelector('[data-flow-item-detail-koli-head]');
        const itemsContainer = document.getElementById('flow_items_container');
        const quickItemsBody = document.getElementById('flow_quick_items_body');
        const quickItemsToggle = document.getElementById('btn_toggle_quick_items');
        const quickItemsInput = document.getElementById('flow_quick_items');
        const quickItemsBtn = document.getElementById('btn_add_quick_items');
        const quickItemsError = document.getElementById('flow_quick_items_error');
        const quickItemsHint = document.getElementById('flow_quick_item_hint');
        const quickInputUnit = document.getElementById('flow_quick_input_unit');
        const addItemBtn = document.getElementById('btn_add_flow_item');
        const openCreateBtn = document.getElementById('btn_open_create_flow');
        const modalTitle = document.getElementById('flow_modal_title');
        const dateFromEl = document.getElementById('filter_date_from');
        const dateToEl = document.getElementById('filter_date_to');
        const transactedAtEl = document.getElementById('flow_transacted_at');
        const warehouseFilter = document.getElementById('filter_warehouse');
        const statusFilter = document.getElementById('filter_status');
        const filterApplyBtn = document.getElementById('filter_apply');
        const filterResetBtn = document.getElementById('filter_reset');
        const exportBtn = document.getElementById('btn_export_flow');
        const importBtn = document.getElementById('btn_import_flow');
        const importModalEl = document.getElementById('modal_import_flow');
        const importModal = importModalEl ? new bootstrap.Modal(importModalEl) : null;
        const importInput = document.getElementById('import_flow_file');
        const importError = document.getElementById('error_import_flow_file');
        const importWarehouseSelect = document.getElementById('import_flow_warehouse_id');
        const importWarehouseError = document.getElementById('error_import_flow_warehouse_id');
        const importSubmit = document.getElementById('btn_import_flow_submit');
        const itemImportBtn = document.getElementById('btn_open_item_import');
        const itemImportModalEl = document.getElementById('modal_import_flow_items');
        const itemImportModal = itemImportModalEl ? new bootstrap.Modal(itemImportModalEl) : null;
        const itemImportInput = document.getElementById('import_flow_items_file');
        const itemImportError = document.getElementById('error_import_flow_items_file');
        const itemImportSubmit = document.getElementById('btn_import_flow_items_submit');
        const warehouseRow = document.getElementById('flow_warehouse_row');
        const warehouseSelect = document.getElementById('flow_warehouse_id');
        const supplierRow = document.getElementById('flow_supplier_row');
        const supplierSelect = document.getElementById('flow_supplier_id');
        const recipientFields = document.getElementById('flow_recipient_fields');
        const recipientNameEl = document.getElementById('flow_recipient_name');
        const recipientPhoneEl = document.getElementById('flow_recipient_phone');
        const recipientAddressEl = document.getElementById('flow_recipient_address');
        const suratJalanNoEl = document.getElementById('flow_surat_jalan_no');
        const suratJalanAtEl = document.getElementById('flow_surat_jalan_at');
        const suratJalanImageEl = document.getElementById('flow_surat_jalan_image');
        const suratJalanImagePreview = document.getElementById('flow_surat_jalan_image_preview');
        const suratJalanImageLink = document.getElementById('flow_surat_jalan_image_link');
        const removeSuratJalanImageEl = document.getElementById('flow_remove_surat_jalan_image');
        let fpFrom = null;
        let fpTo = null;
        let fpTransacted = null;
        let fpSuratJalan = null;

        /** Kembalikan filter tanggal ke rentang default halaman (kosong bila tidak ada default). */
        const applyDefaultDateRange = () => {
            const setDate = (picker, input, value) => {
                if (!input) return;
                if (picker) {
                    if (value) picker.setDate(value, false); else picker.clear();
                    return;
                }
                input.value = value || '';
            };
            setDate(fpFrom, dateFromEl, defaultDateFrom);
            setDate(fpTo, dateToEl, defaultDateTo);
        };

        const formatDateTime = (date) => {
            const pad = (n) => String(n).padStart(2, '0');
            return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`;
        };

        const getJakartaNow = () => {
            const jkt = new Date(new Date().toLocaleString('en-US', { timeZone: 'Asia/Jakarta' }));
            return formatDateTime(jkt);
        };

        const generateDeliveryNoteNo = (flowType = '') => {
            const prefix = deliveryNotePrefixMap?.[flowType] || 'SJ';
            const now = new Date(new Date().toLocaleString('en-US', { timeZone: 'Asia/Jakarta' }));
            const pad = (n) => String(n).padStart(2, '0');
            const stamp = `${now.getFullYear()}${pad(now.getMonth() + 1)}${pad(now.getDate())}${pad(now.getHours())}${pad(now.getMinutes())}${pad(now.getSeconds())}`;
            const random = Math.random().toString(36).slice(2, 6).toUpperCase();
            return `${prefix}-${stamp}-${random}`;
        };

        const resolveRoute = (type, key) => {
            if (routeMap && routeMap[type] && routeMap[type][key]) return routeMap[type][key];
            if (routeMap && routeMap[defaultTypeFilter] && routeMap[defaultTypeFilter][key]) return routeMap[defaultTypeFilter][key];
            return { store: storeUrl, show: showUrlTpl, update: updateUrlTpl, delete: deleteUrlTpl, detail: detailUrlTpl, approve: approveUrlTpl }[key] || '';
        };

        const statusLabel = (status) => {
            if (status && statusLabels?.[status]) {
                const klass = status === 'completed' || status === 'approved'
                    ? 'badge-light-success'
                    : (status === 'scanning' || status === 'qc_scanning' ? 'badge-light-primary' : 'badge-light-warning');
                return `<span class="badge ${klass}">${statusLabels[status]}</span>`;
            }
            if (status === 'approved') return '<span class="badge badge-light-success">Disetujui</span>';
            return '<span class="badge badge-light-warning">Menunggu</span>';
        };

        const warehouseBadgeClass = (warehouseId) => {
            const id = Number(warehouseId || 0);
            if (displayWarehouseId && id === Number(displayWarehouseId)) return 'badge-light-success';
            if (defaultWarehouseId && id === Number(defaultWarehouseId)) return 'badge-light-primary';
            return 'badge-light-secondary';
        };

        const renderWarehouseBadge = (label, warehouseId) => {
            const text = label || '-';
            return `<span class="badge ${warehouseBadgeClass(warehouseId)}">${escapeHtml(text)}</span>`;
        };

        const isDefaultWarehouse = (warehouseId) => defaultWarehouseId && Number(warehouseId || 0) === Number(defaultWarehouseId);

        const escapeHtml = (value) => String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');

        const formatCompactDate = (value, includeTime = false) => {
            const text = String(value || '').trim();
            const match = text.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}:\d{2}))?/);
            if (!match) return text || '-';
            return `${match[3]}/${match[2]}/${match[1]}${includeTime && match[4] ? ` ${match[4]}` : ''}`;
        };

        const renderReturnDocument = (value, renderType, row) => {
            if (renderType !== 'display' || !enhancedItemList || row?.type !== 'return') {
                return value || '-';
            }
            const note = String(row?.note || '').trim();
            return `
                <div class="return-in-document">
                    <span class="return-in-document__code" title="${escapeHtml(value || '-')}">
                        <i class="fas fa-file-alt text-primary me-2"></i>${escapeHtml(value || '-')}
                    </span>
                    <span class="return-in-document__meta">
                        <span><i class="far fa-calendar-alt me-1"></i>${escapeHtml(formatCompactDate(row?.transacted_at, true))}</span>
                        <span><i class="far fa-user me-1"></i>${escapeHtml(row?.submit_by || '-')}</span>
                    </span>
                    ${note ? `<span class="return-in-document__note" title="${escapeHtml(note)}"><i class="far fa-sticky-note text-warning me-1"></i>${escapeHtml(note)}</span>` : ''}
                </div>
            `;
        };

        const renderReturnWarehouseReference = (label, renderType, row) => {
            if (renderType !== 'display' || !enhancedItemList || row?.type !== 'return') {
                return renderWarehouseBadge(label, row?.warehouse_id);
            }
            const reference = String(row?.surat_jalan_no || '').trim();
            const referenceDate = String(row?.surat_jalan_at || '').trim();
            const imageUrl = String(row?.surat_jalan_image_url || '').trim();
            return `
                <div class="return-in-reference">
                    <div>${renderWarehouseBadge(label, row?.warehouse_id)}</div>
                    <span class="return-in-reference__number" title="${escapeHtml(reference || 'Tanpa referensi')}">
                        <i class="fas fa-undo-alt text-muted me-1"></i>${escapeHtml(reference || 'Tanpa referensi')}
                    </span>
                    ${referenceDate ? `<span class="return-in-reference__meta">Tanggal retur: ${escapeHtml(formatCompactDate(referenceDate))}</span>` : ''}
                    ${imageUrl ? `<a href="${escapeHtml(imageUrl)}" target="_blank" rel="noopener" class="badge badge-light-info return-in-reference__image"><i class="far fa-image me-1"></i>${escapeHtml(deliveryNoteImageLinkLabel)}</a>` : ''}
                </div>
            `;
        };

        const renderItemSummary = (row) => {
            if (enhancedItemList && row?.type === 'return') {
                const details = Array.isArray(row?.item_details) ? row.item_details : [];
                if (!details.length) {
                    return '<span class="text-muted fs-7">Belum ada item</span>';
                }

                const totalQty = details.reduce((sum, item) => sum + Number(item?.qty || 0), 0);
                const visibleItems = details.slice(0, 2);
                const remaining = Math.max(0, details.length - visibleItems.length);
                const itemLines = visibleItems.map((item) => {
                    const qty = Number(item?.qty || 0);
                    const koli = Number(item?.koli || 0);
                    const usesPcs = item?.input_unit === 'pcs';
                    const inputLabel = usesPcs
                        ? 'Input PCS'
                        : `${koli.toLocaleString('id-ID')} Koli`;

                    return `
                        <span class="return-in-item-line">
                            <span class="return-in-item-line__identity">
                                <span class="return-in-item-line__sku">${escapeHtml(item?.sku || '-')}</span>
                                <span class="return-in-item-line__name" title="${escapeHtml(item?.name || '-')}">${escapeHtml(item?.name || '-')}</span>
                            </span>
                            <span class="return-in-item-line__amount">
                                <strong>${qty.toLocaleString('id-ID')} PCS</strong>
                                <small>${escapeHtml(inputLabel)}</small>
                            </span>
                        </span>
                    `;
                }).join('');
                const remainingLabel = remaining > 0 ? `+${remaining.toLocaleString('id-ID')} item lainnya` : 'Semua item ditampilkan';

                return `
                    <button type="button" class="return-in-item-card btn-flow-item-detail" title="Buka seluruh rincian item retur">
                        <span class="return-in-item-card__header">
                            <span class="return-in-item-card__title"><i class="fas fa-box-open text-primary me-2"></i>${details.length.toLocaleString('id-ID')} SKU</span>
                            <span class="badge badge-light-primary">${totalQty.toLocaleString('id-ID')} PCS</span>
                        </span>
                        <span class="return-in-item-card__body">${itemLines}</span>
                        <span class="return-in-item-card__footer">
                            <span>${escapeHtml(remainingLabel)}</span>
                            <span>Lihat rincian <i class="fas fa-chevron-right ms-1"></i></span>
                        </span>
                    </button>
                `;
            }

            const useCompactSummary = defaultTypeFilter === 'receipt'
                || (row?.type === 'manual' && showRecipientFields);

            if (!useCompactSummary) {
                return escapeHtml(row?.item || '-');
            }

            const skuCount = Number(row?.sku_count || 0);
            const qty = Number(row?.qty || 0);
            const label = `${skuCount.toLocaleString('id-ID')} SKU / ${qty.toLocaleString('id-ID')} Qty`;
            return `
                <button type="button" class="btn btn-sm btn-light-primary btn-flow-item-detail" title="Lihat detail item">
                    <i class="fas fa-boxes me-1"></i>${escapeHtml(label)}
                </button>
            `;
        };

        const renderTotalQty = (value, renderType, row) => {
            if (renderType !== 'display' || !enhancedItemList || row?.type !== 'return') {
                return value;
            }
            const qty = Number(value || 0);
            return `
                <div class="return-in-total-qty" title="Total kuantitas item retur">
                    <strong>${qty.toLocaleString('id-ID')}</strong>
                    <span>PCS</span>
                </div>
            `;
        };

        const renderFlowNote = (value, renderType, row) => {
            if (renderType !== 'display' || !enhancedItemList || row?.type !== 'return') {
                return value || '-';
            }
            const note = String(value || '').trim();
            if (!note) return '<span class="text-muted fs-7">Tidak ada catatan</span>';
            return `<div class="return-in-note"><i class="fas fa-sticky-note text-warning me-2"></i>${escapeHtml(note)}</div>`;
        };

        const showItemDetail = (row) => {
            if (!row || !itemDetailModal) return;
            const details = Array.isArray(row.item_details) ? row.item_details : [];
            const totalQty = details.reduce((sum, item) => sum + Number(item?.qty || 0), 0);
            const totalKoli = details.reduce((sum, item) => sum + Number(item?.koli || 0), 0);
            const showInput = enableInputUnitSelect || isDefaultWarehouse(row.warehouse_id);

            if (itemDetailTitle) {
                itemDetailTitle.textContent = enhancedItemList && row?.type === 'return'
                    ? `Rincian Item Retur ${row.code || ''}`.trim()
                    : `Detail Item ${row.code || ''}`.trim();
            }
            if (itemDetailSubtitle) {
                const warehouse = renderWarehouseBadge(row.warehouse || '-', row.warehouse_id);
                const summary = `${details.length.toLocaleString('id-ID')} SKU / ${totalQty.toLocaleString('id-ID')} Qty`;
                const koliSummary = !enableInputUnitSelect && showInput ? ` / ${totalKoli.toLocaleString('id-ID')} Koli` : '';
                itemDetailSubtitle.innerHTML = `${warehouse}<span class="ms-2">${escapeHtml(summary + koliSummary)}</span>`;
            }
            if (itemDetailKoliHead) itemDetailKoliHead.style.display = showInput ? '' : 'none';
            if (itemDetailRows) {
                itemDetailRows.innerHTML = details.length
                    ? details.map((item, index) => {
                        const inputUnit = item?.input_unit === 'pcs' ? 'PCS' : 'KOLI';
                        const inputAmount = item?.input_unit === 'pcs' ? item?.qty : item?.koli;
                        return `
                        <tr>
                            ${enhancedItemList ? `<td class="text-center text-muted fw-bold">${index + 1}</td>` : ''}
                            <td class="fw-bold">${escapeHtml(item.sku || '-')}</td>
                            <td>${escapeHtml(item.name || '-')}</td>
                            ${showInput ? `<td class="text-end">${Number(enableInputUnitSelect ? inputAmount : item?.koli || 0).toLocaleString('id-ID')}${enableInputUnitSelect ? ` ${inputUnit}` : ''}</td>` : ''}
                            <td class="text-end">${Number(item.qty || 0).toLocaleString('id-ID')}</td>
                            <td>${escapeHtml(item.note || '-')}</td>
                        </tr>
                    `;
                    }).join('')
                    : `<tr><td colspan="${(showInput ? 5 : 4) + (enhancedItemList ? 1 : 0)}" class="text-center text-muted py-8">Tidak ada item.</td></tr>`;
            }
            itemDetailModal.show();
        };

        const renderScanProgress = (progress) => {
            const unitLabel = progress?.unit_label || 'Koli';
            const expectedKoli = Number(progress?.expected_koli || 0);
            const expectedQty = Number(progress?.expected_qty || 0);
            if (!expectedKoli && expectedQty) {
                const scannedQtyOnly = Number(progress?.scanned_qty || 0);
                const qtyDone = scannedQtyOnly >= expectedQty;
                const qtyKlass = qtyDone
                    ? 'badge-light-success'
                    : (scannedQtyOnly > 0 ? 'badge-light-primary' : 'badge-light-warning');
                return `<div><span class="badge ${qtyKlass}">Qty ${scannedQtyOnly}/${expectedQty}</span></div>`;
            }
            if (!expectedKoli) return '-';

            const scannedKoli = Number(progress?.scanned_koli || 0);
            const scannedQty = Number(progress?.scanned_qty || 0);
            const isDone = scannedKoli >= expectedKoli;
            const klass = isDone
                ? 'badge-light-success'
                : (scannedKoli > 0 ? 'badge-light-primary' : 'badge-light-warning');

            const qtyLine = expectedQty
                ? `<div class="text-muted fs-7">Qty ${scannedQty}/${expectedQty}</div>`
                : '';

            return `<div><span class="badge ${klass}">${escapeHtml(unitLabel)} ${scannedKoli}/${expectedKoli}</span>${qtyLine}</div>`;
        };

        const clearErrors = () => {
            ['error_transacted_at','error_ref_no','error_recipient_name','error_recipient_phone','error_recipient_address','error_supplier_id','error_surat_jalan_no','error_surat_jalan_at','error_surat_jalan_image','error_note','error_warehouse_id'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.textContent = '';
            });
            itemsContainer?.querySelectorAll('[data-error-for]')?.forEach(el => { el.textContent = ''; });
            itemsContainer?.querySelectorAll('.flow-item-select.is-invalid')?.forEach(el => { el.classList.remove('is-invalid'); });
            itemsContainer?.querySelectorAll('input[data-name="qty"].is-invalid')?.forEach(el => { el.classList.remove('is-invalid'); });
            itemsContainer?.querySelectorAll('input[data-name="koli"].is-invalid')?.forEach(el => { el.classList.remove('is-invalid'); });
        };

        const hasMeaningfulItemRows = () => {
            if (!itemsContainer) return false;
            return Array.from(itemsContainer.querySelectorAll('.flow-item-row')).some((row) => {
                const itemId = row.querySelector('.flow-item-select')?.value || '';
                const qty = row.querySelector('input[data-name="qty"]')?.value || '';
                const koli = row.querySelector('input[data-name="koli"]')?.value || '';
                const note = row.querySelector('input[data-name="note"]')?.value || '';
                return itemId !== '' || qty !== '' || koli !== '' || note.trim() !== '';
            });
        };

        const validateUniqueItems = () => {
            if (!itemsContainer) return true;
            const rows = Array.from(itemsContainer.querySelectorAll('.flow-item-row'));
            const counts = {};
            rows.forEach((row) => {
                const selectEl = row.querySelector('.flow-item-select');
                const val = selectEl?.value;
                if (val) {
                    counts[val] = (counts[val] || 0) + 1;
                }
            });
            let hasDuplicate = false;
            rows.forEach((row) => {
                const selectEl = row.querySelector('.flow-item-select');
                const val = selectEl?.value;
                const errEl = row.querySelector('[data-error-for="item_id"]');
                if (selectEl && val && counts[val] > 1) {
                    hasDuplicate = true;
                    if (errEl) errEl.textContent = 'Item tidak boleh duplikat';
                    selectEl.classList.add('is-invalid');
                } else {
                    if (errEl && errEl.textContent === 'Item tidak boleh duplikat') {
                        errEl.textContent = '';
                    }
                    selectEl?.classList.remove('is-invalid');
                }
            });
            return !hasDuplicate;
        };

        const getSelectedKoliQty = (selectEl) => {
            if (!selectEl) return 0;
            const opt = selectEl.selectedOptions?.[0];
            const raw = opt?.getAttribute('data-koli-qty') || opt?.dataset?.koliQty || '';
            const val = parseInt(raw, 10);
            return Number.isFinite(val) ? val : 0;
        };

        const getPositiveIntValue = (inputEl) => {
            if (!inputEl) return null;
            const raw = String(inputEl.value || '').trim();
            if (raw === '') return null;
            if (!/^[1-9]\d*$/.test(raw)) return null;
            const val = Number(raw);
            return Number.isFinite(val) && val > 0 ? val : null;
        };

        const getRowInputUnit = (row) => {
            if (!enableInputUnitSelect) return 'koli';
            return row?.querySelector('select[data-name="input_unit"]')?.value === 'koli' ? 'koli' : 'pcs';
        };

        const isPcsInputAllowed = () => {
            if (!enableInputUnitSelect || !warehouseSelect?.value) return false;
            return (Array.isArray(pcsInputWarehouseIds) ? pcsInputWarehouseIds : [])
                .map(Number)
                .includes(Number(warehouseSelect.value));
        };

        const normalizeSku = (value) => String(value || '').trim().toLowerCase();
        const itemBySku = new Map((Array.isArray(itemCatalog) ? itemCatalog : [])
            .map((item) => [normalizeSku(item.sku), item]));

        const isKoliActive = () => {
            if (!enableKoli) return false;
            const flowType = form?.dataset?.flowType || defaultTypeFilter || '';
            const activeFlowTypes = Array.isArray(koliFlowTypes) && koliFlowTypes.length
                ? koliFlowTypes
                : ['manual', 'return'];
            if (!activeFlowTypes.includes(flowType)) return false;

            if (!koliRequiresDefaultWarehouse) return true;

            const selectedWarehouseId = Number(warehouseSelect?.value || 0);
            return !!defaultWarehouseId && selectedWarehouseId === Number(defaultWarehouseId);
        };

        const updateQuickItemHint = () => {
            if (!quickItemsHint) return;
            const unit = enableInputUnitSelect ? (quickInputUnit?.value || 'pcs') : (isKoliActive() ? 'koli' : 'pcs');
            quickItemsHint.textContent = unit === 'koli'
                ? 'Tulis satu item per baris. Format: SKU jumlah_koli catatan. Qty PCS dihitung otomatis dari isi/koli.'
                : 'Tulis satu item per baris. Format: SKU jumlah_pcs catatan.';
        };

        const applyInputUnitAvailability = () => {
            if (!enableInputUnitSelect) return;
            const pcsAllowed = isPcsInputAllowed();
            const quickPcsOption = quickInputUnit?.querySelector('option[value="pcs"]');
            if (quickPcsOption) quickPcsOption.disabled = !pcsAllowed;
            if (quickInputUnit && !pcsAllowed && quickInputUnit.value === 'pcs') {
                quickInputUnit.value = 'koli';
            }

            itemsContainer?.querySelectorAll('.flow-item-row').forEach((row) => {
                const unitSelect = row.querySelector('.flow-item-unit');
                const pcsOption = unitSelect?.querySelector('option[value="pcs"]');
                if (pcsOption) pcsOption.disabled = !pcsAllowed;
                if (unitSelect && !pcsAllowed && unitSelect.value === 'pcs') {
                    unitSelect.value = 'koli';
                    row.dataset.qtyKoliSource = 'qty';
                }
                updateKoliVisibility(row);
                syncQtyKoliRow(row, 'qty');
            });
            updateQuickItemHint();
        };

        const setQuickItemsVisible = (visible) => {
            if (quickItemsBody) quickItemsBody.style.display = visible ? '' : 'none';
            if (quickItemsToggle) quickItemsToggle.textContent = visible ? 'Sembunyikan Form' : 'Tampilkan Form';
        };

        const setKoliInfo = (row, message, tone = 'muted') => {
            if (!enableKoli || !row) return;
            const infoEl = row.querySelector('[data-koli-info]');
            if (!infoEl) return;
            infoEl.textContent = message;
            infoEl.classList.remove('text-muted', 'text-danger');
            infoEl.classList.add(tone === 'danger' ? 'text-danger' : 'text-muted');
        };

        const setQtyValidation = (row, message = '') => {
            const qtyEl = row?.querySelector('input[data-name="qty"]');
            const errEl = row?.querySelector('[data-error-for="qty"]');
            if (!qtyEl || !errEl) return;

            if (message) {
                qtyEl.classList.add('is-invalid');
                errEl.textContent = message;
                return;
            }

            qtyEl.classList.remove('is-invalid');
            errEl.textContent = '';
        };

        const updateKoliVisibility = (row) => {
            if (!enableKoli || !row) return;
            const active = isKoliActive() && getRowInputUnit(row) === 'koli';
            const koliCol = row.querySelector('[data-koli-col]');
            const koliEl = row.querySelector('input[data-name="koli"]');
            const qtyCol = row.querySelector('[data-qty-col]');

            if (koliCol) koliCol.style.display = active ? '' : 'none';
            if (qtyCol) qtyCol.style.display = active && enableInputUnitSelect ? 'none' : '';
            if (koliEl) {
                koliEl.disabled = !active;
                if (!active) {
                    koliEl.value = '';
                    koliEl.classList.remove('is-invalid');
                }
            }

            if (!active) {
                setKoliInfo(row, 'Isi/Koli: -');
                setQtyValidation(row);
            }
        };

        const updateAllKoliVisibility = () => {
            if (!enableKoli || !itemsContainer) return;
            itemsContainer.querySelectorAll('.flow-item-row').forEach((row) => {
                updateKoliVisibility(row);
                syncQtyKoliRow(row);
            });
        };

        const updateKoliInfo = (row) => {
            if (!enableKoli || !row) return;
            if (!isKoliActive() || getRowInputUnit(row) !== 'koli') {
                updateKoliVisibility(row);
                setQtyValidation(row);
                return;
            }

            const selectEl = row.querySelector('.flow-item-select');
            const qtyEl = row.querySelector('input[data-name="qty"]');
            const qtyVal = getPositiveIntValue(qtyEl);
            const koliEl = row.querySelector('input[data-name="koli"]');
            const koliVal = getPositiveIntValue(koliEl);
            if (!selectEl?.value) {
                setQtyValidation(row);
                setKoliInfo(row, 'Isi/Koli: -');
                return;
            }
            const koliQty = getSelectedKoliQty(selectEl);
            const hasInput = !!qtyVal || !!koliVal;

            if (koliQty <= 0) {
                setQtyValidation(row, hasInput ? 'Isi/Koli item belum diset di master item.' : '');
                setKoliInfo(row, 'Isi/Koli: belum diset di master item', hasInput ? 'danger' : 'muted');
                return;
            }

            if (qtyVal && qtyVal % koliQty !== 0) {
                setQtyValidation(row, `Qty harus kelipatan ${koliQty}.`);
                setKoliInfo(row, `Isi/Koli: ${koliQty} pcs | Qty harus kelipatan ${koliQty}`, 'danger');
                return;
            }

            setQtyValidation(row);
            if (qtyVal && qtyVal % koliQty === 0) {
                setKoliInfo(row, `Isi/Koli: ${koliQty} pcs | ${qtyVal / koliQty} koli`);
                return;
            }

            setKoliInfo(row, `Isi/Koli: ${koliQty} pcs`);
        };

        const syncQtyFromKoli = (row) => {
            if (!enableKoli || !row) return;
            if (!isKoliActive()) {
                updateKoliVisibility(row);
                return;
            }

            const koliEl = row.querySelector('input[data-name="koli"]');
            const qtyEl = row.querySelector('input[data-name="qty"]');
            const selectEl = row.querySelector('.flow-item-select');
            if (!koliEl || !qtyEl || !selectEl) return;
            const koliVal = getPositiveIntValue(koliEl);
            if (!koliVal) {
                if ((row.dataset.qtyKoliSource || '') === 'koli') {
                    qtyEl.value = '';
                }
                updateKoliInfo(row);
                return;
            }
            const koliQty = getSelectedKoliQty(selectEl);
            if (koliQty <= 0) {
                if ((row.dataset.qtyKoliSource || '') === 'koli') {
                    qtyEl.value = '';
                }
                updateKoliInfo(row);
                return;
            }
            qtyEl.value = String(koliVal * koliQty);
            updateKoliInfo(row);
        };

        const syncKoliFromQty = (row) => {
            if (!enableKoli || !row) return;
            if (!isKoliActive()) {
                updateKoliVisibility(row);
                return;
            }

            const qtyEl = row.querySelector('input[data-name="qty"]');
            const koliEl = row.querySelector('input[data-name="koli"]');
            const selectEl = row.querySelector('.flow-item-select');
            if (!qtyEl || !koliEl || !selectEl) return;

            const qtyVal = getPositiveIntValue(qtyEl);
            if (!qtyVal) {
                if ((row.dataset.qtyKoliSource || '') === 'qty') {
                    koliEl.value = '';
                }
                updateKoliInfo(row);
                return;
            }

            const koliQty = getSelectedKoliQty(selectEl);
            if (koliQty <= 0) {
                updateKoliInfo(row);
                return;
            }

            if (qtyVal % koliQty !== 0) {
                koliEl.value = '';
                updateKoliInfo(row);
                return;
            }

            koliEl.value = String(qtyVal / koliQty);
            updateKoliInfo(row);
        };

        const syncQtyKoliRow = (row, preferredSource = '') => {
            if (!enableKoli || !row) return;
            updateKoliVisibility(row);
            if (getRowInputUnit(row) === 'pcs') {
                const koliEl = row.querySelector('input[data-name="koli"]');
                if (koliEl) koliEl.value = '';
                row.dataset.qtyKoliSource = 'qty';
                setQtyValidation(row);
                return;
            }
            if (!isKoliActive()) return;

            const qtyEl = row.querySelector('input[data-name="qty"]');
            const koliEl = row.querySelector('input[data-name="koli"]');
            if (!qtyEl || !koliEl) return;

            const activeSource = preferredSource || row.dataset.qtyKoliSource || '';
            const qtyVal = getPositiveIntValue(qtyEl);
            const koliVal = getPositiveIntValue(koliEl);

            if (activeSource === 'qty' && qtyVal) {
                syncKoliFromQty(row);
                return;
            }

            if (activeSource === 'koli' && koliVal) {
                syncQtyFromKoli(row);
                return;
            }

            if (koliVal) {
                syncQtyFromKoli(row);
                return;
            }

            if (qtyVal) {
                syncKoliFromQty(row);
                return;
            }

            updateKoliInfo(row);
        };

        const resolveQtyKoliSource = (row) => {
            if (!enableKoli || !row) return '';
            if (getRowInputUnit(row) === 'pcs') return 'qty';
            const explicitSource = row.dataset.qtyKoliSource || '';
            if (explicitSource === 'qty' || explicitSource === 'koli') {
                return explicitSource;
            }

            const qtyVal = getPositiveIntValue(row.querySelector('input[data-name="qty"]'));
            const koliVal = getPositiveIntValue(row.querySelector('input[data-name="koli"]'));

            if (koliVal) return 'koli';
            if (qtyVal) return 'qty';
            return '';
        };

        const handleItemSelectionChange = (selectEl) => {
            if (!selectEl?.matches('.flow-item-select')) return;
            validateUniqueItems();
            const row = selectEl.closest('.flow-item-row');
            if (!row) return;
            const source = resolveQtyKoliSource(row);
            row.dataset.qtyKoliSource = source;
            window.setTimeout(() => {
                syncQtyKoliRow(row, source);
            }, 0);
        };

        const initSelect2 = (selectEl) => {
            if (selectEl && typeof $ !== 'undefined' && $.fn.select2) {
                $(selectEl).select2({
                    placeholder: 'Pilih item',
                    allowClear: true,
                    width: '100%',
                    dropdownParent: modalContentEl,
                    minimumResultsForSearch: 0,
                })
                    .on('change.qtyKoli select2:select.qtyKoli select2:clear.qtyKoli', () => handleItemSelectionChange(selectEl))
                    .on('select2:opening select2:closing select2:close', function(e){ e.stopPropagation(); });
                selectEl.dataset.qtyKoliBound = '1';
                return;
            }

            if (selectEl && !selectEl.dataset.qtyKoliBound) {
                selectEl.addEventListener('change', () => handleItemSelectionChange(selectEl));
                selectEl.dataset.qtyKoliBound = '1';
            }
        };

        const applyWarehouseVisibility = (flowType) => {
            if (!warehouseRow || !warehouseSelect) return;
            const shouldShow = enableWarehouseSelect && ['manual', 'return'].includes(flowType);
            warehouseRow.style.display = shouldShow ? '' : 'none';
            warehouseSelect.required = shouldShow;
            if (shouldShow) {
                if (!warehouseSelect.value && !requireExplicitWarehouseSelection) {
                    const fallbackId = displayWarehouseId || defaultWarehouseId || '';
                    if (fallbackId) warehouseSelect.value = String(fallbackId);
                }
            } else {
                warehouseSelect.value = '';
            }
            applyInputUnitAvailability();
        };

        const applySupplierVisibility = (flowType) => {
            if (!supplierRow || !supplierSelect) return;
            const shouldShow = Array.isArray(supplierFlowTypes) && supplierFlowTypes.includes(flowType);
            supplierRow.style.display = shouldShow ? '' : 'none';
            supplierSelect.required = shouldShow;
            if (!shouldShow) {
                supplierSelect.value = '';
                if (typeof $ !== 'undefined' && $(supplierSelect).data('select2')) {
                    $(supplierSelect).val('').trigger('change.select2');
                }
            }
        };

        const applyRecipientVisibility = (flowType) => {
            if (!recipientFields) return;
            const shouldShow = showRecipientFields && flowType === 'manual';
            recipientFields.style.display = shouldShow ? '' : 'none';
            if (!shouldShow) {
                if (recipientNameEl) recipientNameEl.value = '';
                if (recipientPhoneEl) recipientPhoneEl.value = '';
                if (recipientAddressEl) recipientAddressEl.value = '';
            }
        };

        if (typeof flatpickr !== 'undefined') {
            if (dateFromEl) {
                fpFrom = flatpickr(dateFromEl, { dateFormat: 'Y-m-d', allowInput: true });
            }
            if (dateToEl) {
                fpTo = flatpickr(dateToEl, { dateFormat: 'Y-m-d', allowInput: true });
            }
            if (transactedAtEl) {
                fpTransacted = flatpickr(transactedAtEl, { enableTime: true, dateFormat: 'Y-m-d H:i', allowInput: true });
            }
            if (suratJalanAtEl) {
                fpSuratJalan = flatpickr(suratJalanAtEl, { dateFormat: 'Y-m-d', allowInput: true });
            }
        }

        if (warehouseFilter && typeof $ !== 'undefined' && $.fn.select2) {
            $(warehouseFilter).select2({ placeholder: 'Semua Gudang', allowClear: true, width: '200px' });
        }
        if (statusFilter && typeof $ !== 'undefined' && $.fn.select2) {
            $(statusFilter).select2({ placeholder: 'Semua Status', allowClear: true, width: '200px' });
        }
        if (supplierSelect && typeof $ !== 'undefined' && $.fn.select2) {
            $(supplierSelect).select2({
                placeholder: 'Pilih supplier',
                allowClear: true,
                width: '100%',
                dropdownParent: modalContentEl,
                minimumResultsForSearch: 0,
            });
        }

        warehouseSelect?.addEventListener('change', () => {
            applyInputUnitAvailability();
            updateAllKoliVisibility();
        });
        quickInputUnit?.addEventListener('change', updateQuickItemHint);

        const renumberRows = () => {
            const rows = itemsContainer.querySelectorAll('.flow-item-row');
            rows.forEach((row, idx) => {
                row.querySelectorAll('[data-name]')?.forEach((el) => {
                    const key = el.getAttribute('data-name');
                    el.name = `items[${idx}][${key}]`;
                });
            });
        };

        const createItemRow = (data = {}) => {
            const row = document.createElement('div');
            row.className = 'row g-3 align-items-end mb-4 flow-item-row';
            const itemColSize = enableInputUnitSelect ? 'col-md-4' : (enableKoli ? 'col-md-5' : 'col-md-6');
            const noteColSize = enableInputUnitSelect ? 'col-md-3' : (enableKoli ? 'col-md-2' : 'col-md-3');
            const initialInputUnit = enableInputUnitSelect && isPcsInputAllowed() && data.input_unit === 'pcs' ? 'pcs' : 'koli';
            const koliDisplay = isKoliActive() && initialInputUnit === 'koli' ? '' : ' style="display:none;"';
            const unitCol = enableInputUnitSelect ? `
                <div class="col-md-2">
                    <label class="required fs-6 fw-bold form-label mb-2">Satuan</label>
                    <select class="form-select form-select-solid flow-item-unit" data-name="input_unit" required>
                        <option value="koli"${initialInputUnit === 'koli' ? ' selected' : ''}>Koli</option>
                        <option value="pcs"${initialInputUnit === 'pcs' ? ' selected' : ''}${isPcsInputAllowed() ? '' : ' disabled'}>PCS</option>
                    </select>
                    <div class="invalid-feedback" data-error-for="input_unit"></div>
                </div>
            ` : '';
            const koliCol = enableKoli ? `
                <div class="col-md-2" data-koli-col${koliDisplay}>
                    <label class="fs-6 fw-bold form-label mb-2">Koli</label>
                    <input type="number" min="1" step="1" class="form-control form-control-solid" data-name="koli" />
                    <div class="invalid-feedback" data-error-for="koli"></div>
                    <div class="form-text small text-muted" data-koli-info>Isi/Koli: -</div>
                </div>
            ` : '';
            row.innerHTML = `
                <div class="${itemColSize}">
                    <label class="required fs-6 fw-bold form-label mb-2">Item</label>
                    <select class="form-select form-select-solid flow-item-select" data-name="item_id" required>
                        <option value=""></option>
                        ${itemOptionsHtml}
                    </select>
                    <div class="invalid-feedback" data-error-for="item_id"></div>
                </div>
                ${unitCol}
                ${koliCol}
                <div class="col-md-2" data-qty-col${enableInputUnitSelect && initialInputUnit === 'koli' ? ' style="display:none;"' : ''}>
                    <label class="required fs-6 fw-bold form-label mb-2">${enableInputUnitSelect ? 'Qty PCS' : 'Qty'}</label>
                    <input type="number" min="1" step="1" class="form-control form-control-solid" data-name="qty" required />
                    <div class="invalid-feedback" data-error-for="qty"></div>
                </div>
                <div class="${noteColSize}">
                    <label class="fs-6 fw-bold form-label mb-2">Catatan Item</label>
                    <input type="text" class="form-control form-control-solid" data-name="note" />
                </div>
                <div class="col-md-1 text-end">
                    <button type="button" class="btn btn-light btn-sm btn-remove-item">Hapus</button>
                </div>
            `;
            itemsContainer.appendChild(row);

            const selectEl = row.querySelector('.flow-item-select');
            if (data.item_id) {
                selectEl.value = String(data.item_id);
            }
            const qtyEl = row.querySelector('input[data-name="qty"]');
            if (qtyEl) qtyEl.value = data.qty ?? '';
            const koliEl = row.querySelector('input[data-name="koli"]');
            if (koliEl) koliEl.value = data.koli ?? '';
            const noteEl = row.querySelector('input[data-name="note"]');
            if (noteEl) noteEl.value = data.note ?? '';
            row.dataset.qtyKoliSource = initialInputUnit === 'pcs'
                ? 'qty'
                : (getPositiveIntValue(koliEl)
                ? 'koli'
                : (getPositiveIntValue(qtyEl) ? 'qty' : ''));

            updateKoliVisibility(row);
            initSelect2(selectEl);
            syncQtyKoliRow(row);
            renumberRows();
            validateUniqueItems();
        };

        const parseQuickItemLine = (line) => {
            const normalized = String(line || '').trim();
            if (!normalized) return null;

            let parts = normalized.includes(',') || normalized.includes(';') || normalized.includes('\t')
                ? normalized.split(/[,\t;]+/).map((part) => part.trim()).filter(Boolean)
                : normalized.split(/\s+/);

            const sku = parts.shift() || '';
            const amountText = parts.shift() || '';
            const amount = Number(amountText);
            const note = parts.join(' ').trim();

            if (!sku || !Number.isInteger(amount) || amount < 1) {
                throw new Error(`Format tidak valid: ${line}`);
            }

            const item = itemBySku.get(normalizeSku(sku));
            if (!item) {
                throw new Error(`SKU tidak ditemukan: ${sku}`);
            }

            if ((item.item_type || 'single') === 'bundle') {
                throw new Error(`SKU bundle tidak bisa dipakai: ${sku}`);
            }

            const inputUnit = enableInputUnitSelect ? (quickInputUnit?.value || 'pcs') : (isKoliActive() ? 'koli' : 'pcs');
            if (inputUnit === 'koli') {
                const koliQty = Number(item.koli_qty || 0);
                if (!Number.isFinite(koliQty) || koliQty < 1) {
                    throw new Error(`Isi/koli belum diset untuk SKU: ${sku}`);
                }

                return {
                    item_id: item.id,
                    qty: amount * koliQty,
                    koli: amount,
                    input_unit: 'koli',
                    note,
                };
            }

            return {
                item_id: item.id,
                qty: amount,
                koli: '',
                input_unit: 'pcs',
                note,
            };
        };

        const addQuickItems = () => {
            if (!quickItemsInput) return;
            if (quickItemsError) quickItemsError.textContent = '';

            const lines = quickItemsInput.value.split(/\r?\n/).map((line) => line.trim()).filter(Boolean);
            if (!lines.length) {
                if (quickItemsError) quickItemsError.textContent = 'Isi daftar SKU terlebih dahulu.';
                return;
            }

            let parsed = [];
            try {
                parsed = lines.map(parseQuickItemLine).filter(Boolean);
            } catch (err) {
                if (quickItemsError) quickItemsError.textContent = err.message || 'Input cepat tidak valid.';
                return;
            }

            const duplicateSku = parsed
                .map((row) => String(row.item_id))
                .find((itemId, index, ids) => ids.indexOf(itemId) !== index);
            if (duplicateSku) {
                if (quickItemsError) quickItemsError.textContent = 'SKU pada input cepat tidak boleh duplikat.';
                return;
            }

            itemsContainer.innerHTML = '';
            parsed.forEach((item) => createItemRow(item));
            quickItemsInput.value = '';
            clearErrors();
            validateUniqueItems();
        };

        const resetForm = () => {
            form?.reset();
            form.dataset.editId = '';
            form.dataset.flowType = defaultTypeFilter || '';
            if (modalTitle) modalTitle.textContent = 'Tambah';
            const nowJkt = getJakartaNow();
            if (fpTransacted) {
                fpTransacted.setDate(nowJkt, true, 'Y-m-d H:i');
            } else if (transactedAtEl) {
                transactedAtEl.value = nowJkt;
            }
            applyWarehouseVisibility(defaultTypeFilter || '');
            applySupplierVisibility(defaultTypeFilter || '');
            applyRecipientVisibility(defaultTypeFilter || '');
            if (supplierSelect) {
                supplierSelect.value = '';
                if (typeof $ !== 'undefined' && $(supplierSelect).data('select2')) {
                    $(supplierSelect).val('').trigger('change.select2');
                }
            }
            if (suratJalanNoEl) {
                suratJalanNoEl.value = generateDeliveryNoteNo(defaultTypeFilter || '');
            }
            if (fpSuratJalan) {
                fpSuratJalan.clear();
            } else if (suratJalanAtEl) {
                suratJalanAtEl.value = '';
            }
            if (suratJalanImageEl) suratJalanImageEl.value = '';
            if (removeSuratJalanImageEl) removeSuratJalanImageEl.checked = false;
            if (suratJalanImagePreview) suratJalanImagePreview.style.display = 'none';
            if (suratJalanImageLink) suratJalanImageLink.href = '#';
            if (quickItemsInput) quickItemsInput.value = '';
            if (quickItemsError) quickItemsError.textContent = '';
            setQuickItemsVisible(true);
            updateQuickItemHint();
            itemsContainer.innerHTML = '';
            createItemRow();
            clearErrors();
            validateUniqueItems();
        };

        const replaceItemsFromImport = (items) => {
            itemsContainer.innerHTML = '';
            (items || []).forEach((item) => {
                createItemRow({
                    item_id: item.item_id,
                    qty: item.qty,
                    koli: item.koli > 0 ? item.koli : '',
                    input_unit: item.input_unit || 'koli',
                    note: item.note || '',
                });
            });
            if (!itemsContainer.querySelector('.flow-item-row')) {
                createItemRow();
            }
            clearErrors();
            validateUniqueItems();
        };

        addItemBtn?.addEventListener('click', () => createItemRow());
        quickItemsToggle?.addEventListener('click', () => {
            const isVisible = !quickItemsBody || quickItemsBody.style.display !== 'none';
            setQuickItemsVisible(!isVisible);
        });
        quickItemsBtn?.addEventListener('click', addQuickItems);
        quickItemsInput?.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                e.preventDefault();
                addQuickItems();
                return;
            }

            if (e.key === 'Enter') {
                e.preventDefault();
                e.stopPropagation();

                const start = quickItemsInput.selectionStart ?? quickItemsInput.value.length;
                const end = quickItemsInput.selectionEnd ?? start;
                const before = quickItemsInput.value.slice(0, start);
                const after = quickItemsInput.value.slice(end);
                quickItemsInput.value = `${before}\n${after}`;
                quickItemsInput.selectionStart = start + 1;
                quickItemsInput.selectionEnd = start + 1;
            }
        });
        if (!canCreateDefault && openCreateBtn) {
            openCreateBtn.remove();
        } else {
            openCreateBtn?.addEventListener('click', resetForm);
        }

        itemsContainer?.addEventListener('input', (e) => {
            if (!enableKoli) return;
            if (e.target.matches('input[data-name="koli"]')) {
                const row = e.target.closest('.flow-item-row');
                if (row) row.dataset.qtyKoliSource = 'koli';
                syncQtyFromKoli(row);
                return;
            }
            if (e.target.matches('input[data-name="qty"]')) {
                const row = e.target.closest('.flow-item-row');
                if (row) row.dataset.qtyKoliSource = 'qty';
                syncKoliFromQty(row);
            }
        });

        itemsContainer?.addEventListener('change', (e) => {
            if (!e.target.matches('.flow-item-unit')) return;
            const row = e.target.closest('.flow-item-row');
            if (!row) return;
            row.dataset.qtyKoliSource = 'qty';
            syncQtyKoliRow(row, 'qty');
        });

        itemsContainer?.addEventListener('click', (e) => {
            const btn = e.target.closest('.btn-remove-item');
            if (!btn) return;
            const row = btn.closest('.flow-item-row');
            if (row) row.remove();
            if (itemsContainer.querySelectorAll('.flow-item-row').length === 0) {
                createItemRow();
            } else {
                renumberRows();
            }
            validateUniqueItems();
        });

        if (!tableEl.length || !$.fn.DataTable) {
            console.error('DataTables unavailable');
            return;
        }

        const refreshMenus = () => { if (window.KTMenu) KTMenu.createInstances(); };

        const collectFilterParams = () => {
            const params = { q: searchInput?.value || '' };
            if (params.q && typeof window.resolveTableSearchMode === 'function') {
                params.search_mode = window.resolveTableSearchMode(tableEl?.[0] || null);
            }
            if (warehouseFilter?.value) params.warehouse_id = warehouseFilter.value;
            if (statusFilter?.value) params.status = statusFilter.value;
            if (dateFromEl?.value) params.date_from = dateFromEl.value;
            if (dateToEl?.value) params.date_to = dateToEl.value;
            return params;
        };

        const dt = tableEl.DataTable({
            processing: true,
            serverSide: true,
            dom: 'rtip',
            order: [[0, 'desc']],
            ajax: {
                url: dataUrl,
                dataSrc: 'data',
                data: function(params) {
                    Object.assign(params, collectFilterParams());
                }
            },
            columns: [
                { data: 'id', visible: !enhancedItemList },
                { data: 'code', className: enhancedItemList ? 'return-in-document-cell' : '', render: (data, type, row) => renderReturnDocument(data, type, row) },
                { data: 'type', visible: !enhancedItemList, render: (data) => typeLabelMap?.[data] || data || '-' },
                { data: 'status', className: enhancedItemList ? 'return-in-status-cell' : '', orderable:false, searchable:false, render: (data) => statusLabel(data) },
                { data: 'transacted_at', visible: !enhancedItemList },
                { data: 'submit_by', visible: !enhancedItemList },
                { data: 'warehouse', className: enhancedItemList ? 'return-in-reference-cell' : '', render: (data, type, row) => renderReturnWarehouseReference(data, type, row) },
                @if(!empty($showDeliveryNoteFields ?? false))
                    { data: 'surat_jalan_no', visible: !enhancedItemList, orderable: false, searchable: false, render: (data, type, row) => {
                        const no = data || '';
                        const at = row?.surat_jalan_at || '';
                        const imageUrl = row?.surat_jalan_image_url || '';
                        if (!no && !at && !imageUrl) return '<span class="text-muted">-</span>';
                        const parts = [
                            no || '-',
                            at ? `<div class="text-muted fs-8">${at}</div>` : '',
                            imageUrl ? `<div class="mt-1"><a href="${escapeHtml(imageUrl)}" target="_blank" rel="noopener" class="badge badge-light-primary">${escapeHtml(deliveryNoteImageLinkLabel)}</a></div>` : '',
                        ].filter(Boolean);
                        return parts.join('');
                    }},
                @endif
                @if(!empty($showSupplierColumn ?? false))
                    { data: 'supplier' },
                @endif
                @if(!empty($showRecipientFields ?? false))
                    { data: 'recipient_name', orderable: false, searchable: false, render: (data, type, row) => {
                        const name = data || '';
                        const phone = row?.recipient_phone || '';
                        const address = row?.recipient_address || '';
                        if (!name && !phone && !address) return '<span class="text-muted">-</span>';
                        const lines = [
                            name ? `<div class="fw-bold">${escapeHtml(name)}</div>` : '',
                            phone ? `<div class="text-muted fs-8">${escapeHtml(phone)}</div>` : '',
                            address ? `<div class="text-muted fs-8">${escapeHtml(address).replace(/\n/g, '<br>')}</div>` : '',
                        ].filter(Boolean);
                        return lines.join('');
                    }},
                @endif
                { data: 'item', className: enhancedItemList ? 'stock-flow-item-column' : '', orderable: false, searchable: false, render: (data, type, row) => renderItemSummary(row) },
                { data: 'qty', visible: !enhancedItemList, render: (data, type, row) => renderTotalQty(data, type, row) },
                @if(!empty($showScanProgressColumn ?? false))
                    { data: 'scan_progress', className: enhancedItemList ? 'return-in-progress-cell' : '', orderable:false, searchable:false, render: (data) => renderScanProgress(data) },
                @endif
                { data: 'note', visible: !enhancedItemList, render: (data, type, row) => renderFlowNote(data, type, row) },
                { data: 'id', orderable:false, searchable:false, className: enhancedItemList ? 'text-end return-in-action-cell' : 'text-end', render: (data, type, row)=>{
                    const rowType = row?.type || defaultTypeFilter;
                    const perms = permMap?.[rowType] || {};
                    const isLocked = Array.isArray(lockedStatuses)
                        ? lockedStatuses.includes(row?.status)
                        : row?.status === 'approved';
                    const isDeleteLocked = Array.isArray(deleteLockedStatuses)
                        ? deleteLockedStatuses.includes(row?.status)
                        : row?.status === 'approved';
                    const detailItem = `<div class="menu-item px-3"><a href="${resolveRoute(rowType, 'detail').replace(':id', data)}" class="menu-link px-3">Detail</a></div>`;
                    const deliveryNoteRoute = resolveRoute(rowType, 'delivery_note');
                    const deliveryNotePrintRoute = resolveRoute(rowType, 'delivery_note_print');
                    const canPrintDeliveryNote = row?.status === 'approved';
                    const deliveryNoteItem = (['manual', 'return'].includes(rowType) && row?.surat_jalan_no && canPrintDeliveryNote && deliveryNoteRoute)
                        ? `<div class="menu-item px-3"><a href="${deliveryNoteRoute.replace(':id', data)}" class="menu-link px-3">Detail Surat Jalan</a></div>`
                        : '';
                    const deliveryNotePrintItem = (['manual', 'return'].includes(rowType) && row?.surat_jalan_no && canPrintDeliveryNote && deliveryNotePrintRoute)
                        ? `<div class="menu-item px-3"><a href="${deliveryNotePrintRoute.replace(':id', data)}" class="menu-link px-3" target="_blank" rel="noopener">Cetak Surat Jalan</a></div>`
                        : '';
                    const qrPdfRoute = resolveRoute(rowType, 'qr_pdf');
                    const qrPdfItem = (rowType === 'receipt' && qrPdfRoute)
                        ? `<div class="menu-item px-3"><a href="#" class="menu-link px-3 btn-download-qr" data-url="${qrPdfRoute.replace(':id', data)}">Unduh QR Code</a></div>`
                        : '';
                    const canApprove = showApproveAction && perms.update && (
                        rowType === 'manual'
                            ? row?.status === 'pending'
                            : !isLocked
                    );
                    const approveItem = canApprove
                        ? `<div class="menu-item px-3"><a href="#" class="menu-link px-3 text-success btn-approve" data-id="${data}" data-type="${rowType}">Approve</a></div>`
                        : '';
                    const editItem = (!isLocked && perms.update)
                        ? `<div class="menu-item px-3"><a href="#" class="menu-link px-3 btn-edit" data-id="${data}" data-type="${rowType}">Edit</a></div>`
                        : '';
                    const delItem = (!isDeleteLocked && perms.delete)
                        ? `<div class="menu-item px-3"><a href="#" class="menu-link px-3 text-danger btn-delete" data-id="${data}" data-type="${rowType}">Hapus</a></div>`
                        : '';
                    const actions = `${detailItem}${deliveryNoteItem}${deliveryNotePrintItem}${qrPdfItem}${approveItem}${editItem}${delItem}`;
                    if (!actions) return '';
                    return `
                        <div class="text-end">
                            <a href="#" class="btn btn-sm btn-light btn-active-light-primary" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                Actions
                                <span class="svg-icon svg-icon-5 m-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                        <path d="M11.4343 12.7344L7.25 8.55005C6.83579 8.13583 6.16421 8.13584 5.75 8.55005C5.33579 8.96426 5.33579 9.63583 5.75 10.05L11.2929 15.5929C11.6834 15.9835 12.3166 15.9835 12.7071 15.5929L18.25 10.05C18.6642 9.63584 18.6642 8.96426 18.25 8.55005C17.8358 8.13584 17.1642 8.13584 16.75 8.55005L12.5657 12.7344C12.2533 13.0468 11.7467 13.0468 11.4343 12.7344Z" fill="black"></path>
                                    </svg>
                                </span>
                            </a>
                            <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-600 menu-state-bg-light-primary fw-bold fs-7 w-175px py-3" data-kt-menu="true">
                                ${actions}
                            </div>
                        </div>
                    `;
                }}
            ]
        });
        refreshMenus();
        dt.on('draw', refreshMenus);

        tableEl.on('click', '.btn-flow-item-detail', function () {
            const tr = $(this).closest('tr');
            const row = dt.row(tr.hasClass('child') ? tr.prev() : tr).data();
            showItemDetail(row);
        });

        const reloadTable = () => dt.ajax.reload();
        searchInput?.addEventListener('keyup', reloadTable);
        warehouseFilter?.addEventListener('change', reloadTable);
        statusFilter?.addEventListener('change', reloadTable);
        filterApplyBtn?.addEventListener('click', reloadTable);
        filterResetBtn?.addEventListener('click', () => {
            if (searchInput) searchInput.value = '';
            if (warehouseFilter) {
                warehouseFilter.value = 'all';
                if (typeof $ !== 'undefined' && $(warehouseFilter).data('select2')) {
                    $(warehouseFilter).val('all').trigger('change.select2');
                }
            }
            if (statusFilter) {
                statusFilter.value = 'all';
                if (typeof $ !== 'undefined' && $(statusFilter).data('select2')) {
                    $(statusFilter).val('all').trigger('change.select2');
                }
            }
            applyDefaultDateRange();
            reloadTable();
        });

        exportBtn?.addEventListener('click', async () => {
            if (!exportUrl) return;
            const params = new URLSearchParams();
            Object.entries(collectFilterParams()).forEach(([key, value]) => {
                if (value !== '' && value !== null && value !== undefined) params.set(key, value);
            });
            const query = params.toString();
            const originalContent = exportBtn.innerHTML;
            exportBtn.disabled = true;
            exportBtn.setAttribute('aria-busy', 'true');
            exportBtn.innerHTML = '<span class="spinner-border spinner-border-sm align-middle me-2" role="status" aria-hidden="true"></span>Menyiapkan Excel...';

            try {
                const response = await fetch(query ? `${exportUrl}?${query}` : exportUrl, {
                    headers: { 'Accept': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' },
                });
                if (!response.ok) {
                    throw new Error(response.status === 504
                        ? 'Proses export melewati batas waktu server. Persempit periode atau filter data lalu coba lagi.'
                        : 'Gagal membuat file Excel.');
                }
                const filename = filenameFromDisposition(
                    response.headers.get('Content-Disposition'),
                    'outbound-manual.xlsx'
                );
                saveBlob(await response.blob(), filename);
            } catch (error) {
                const message = error?.message || 'Gagal membuat file Excel.';
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Export gagal', message, 'error');
                } else {
                    alert(message);
                }
            } finally {
                exportBtn.disabled = false;
                exportBtn.removeAttribute('aria-busy');
                exportBtn.innerHTML = originalContent;
            }
        });

        const filenameFromDisposition = (disposition, fallback) => {
            const value = String(disposition || '');
            const utfMatch = value.match(/filename\*=UTF-8''([^;]+)/i);
            if (utfMatch?.[1]) {
                try {
                    return decodeURIComponent(utfMatch[1].replace(/"/g, '').trim());
                } catch (err) {
                    return utfMatch[1].replace(/"/g, '').trim();
                }
            }
            const match = value.match(/filename="?([^";]+)"?/i);
            return match?.[1]?.trim() || fallback;
        };

        const updateQrDownloadProgress = (percent, text = '') => {
            const safePercent = Math.max(0, Math.min(100, Math.round(percent)));
            const bar = document.getElementById('qr_download_progress_bar');
            const label = document.getElementById('qr_download_progress_label');
            const info = document.getElementById('qr_download_progress_info');
            if (bar) {
                bar.style.width = `${safePercent}%`;
                bar.setAttribute('aria-valuenow', String(safePercent));
            }
            if (label) label.textContent = `${safePercent}%`;
            if (info && text) info.textContent = text;
        };

        const showQrDownloadProgress = () => {
            if (typeof Swal === 'undefined') return;
            Swal.fire({
                title: 'Menyiapkan QR Code',
                html: `
                    <div class="text-muted mb-4" id="qr_download_progress_info">Menghubungi server...</div>
                    <div class="progress h-8px bg-light-primary mb-3">
                        <div id="qr_download_progress_bar" class="progress-bar bg-primary" role="progressbar" style="width:0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <div class="fw-bold" id="qr_download_progress_label">0%</div>
                `,
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => updateQrDownloadProgress(0, 'Menghubungi server...'),
            });
        };

        const saveBlob = (blob, filename) => {
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.setTimeout(() => URL.revokeObjectURL(url), 1000);
        };

        const downloadQrPdfWithProgress = async (url) => {
            showQrDownloadProgress();
            updateQrDownloadProgress(2, 'Membuat file QR...');

            const response = await fetch(url, {
                headers: { 'Accept': 'application/pdf' },
            });

            if (!response.ok) {
                throw new Error('Gagal mengunduh QR Code.');
            }

            const filename = filenameFromDisposition(response.headers.get('Content-Disposition'), 'qr-inbound.pdf');
            const total = Number(response.headers.get('Content-Length') || 0);
            const reader = response.body?.getReader();

            if (!reader) {
                updateQrDownloadProgress(70, 'Menyusun file...');
                const blob = await response.blob();
                updateQrDownloadProgress(100, 'Download siap.');
                saveBlob(blob, filename);
                return;
            }

            const chunks = [];
            let received = 0;
            let simulated = 10;
            while (true) {
                const { done, value } = await reader.read();
                if (done) break;
                chunks.push(value);
                received += value.length;
                if (total > 0) {
                    updateQrDownloadProgress((received / total) * 100, 'Mengunduh file QR...');
                } else {
                    simulated = Math.min(95, simulated + 8);
                    updateQrDownloadProgress(simulated, 'Mengunduh file QR...');
                }
            }

            updateQrDownloadProgress(98, 'Menyiapkan file...');
            const blob = new Blob(chunks, { type: response.headers.get('Content-Type') || 'application/pdf' });
            saveBlob(blob, filename);
            updateQrDownloadProgress(100, 'Download selesai.');
        };

        importBtn?.addEventListener('click', () => {
            if (importInput) importInput.value = '';
            if (importError) importError.textContent = '';
            if (importWarehouseSelect) importWarehouseSelect.value = '';
            if (importWarehouseError) importWarehouseError.textContent = '';
        });

        itemImportBtn?.addEventListener('click', (event) => {
            event.preventDefault();
            if (itemImportInput) itemImportInput.value = '';
            if (itemImportError) itemImportError.textContent = '';
            itemImportModal?.show();
        });

        importSubmit?.addEventListener('click', async () => {
            if (!importUrl) return;
            if (importError) importError.textContent = '';
            if (importWarehouseError) importWarehouseError.textContent = '';
            const file = importInput?.files?.[0];
            if (!file) {
                if (importError) importError.textContent = 'Pilih file Excel terlebih dahulu.';
                return;
            }
            if (requireExplicitWarehouseSelection && !importWarehouseSelect?.value) {
                if (importWarehouseError) importWarehouseError.textContent = 'Pilih gudang tujuan import.';
                return;
            }
            const formData = new FormData();
            formData.append('file', file);
            if (importWarehouseSelect?.value) formData.append('warehouse_id', importWarehouseSelect.value);
            const originalContent = importSubmit.innerHTML;
            importSubmit.disabled = true;
            importSubmit.setAttribute('aria-busy', 'true');
            importSubmit.innerHTML = '<span class="spinner-border spinner-border-sm align-middle me-2" role="status" aria-hidden="true"></span>Memproses import...';
            try {
                const res = await fetch(importUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: formData,
                });
                const text = await res.text();
                let json;
                try { json = JSON.parse(text); } catch (err) {
                    if (typeof Swal !== 'undefined') Swal.fire('Error', 'Respons server tidak valid', 'error');
                    return;
                }
                if (!res.ok) {
                    const warehouseMessage = json?.errors?.warehouse_id?.[0];
                    const msg = json?.errors?.file?.[0] || warehouseMessage || json?.message;
                    if (warehouseMessage && importWarehouseError) importWarehouseError.textContent = warehouseMessage;
                    if (typeof Swal !== 'undefined') {
                        Swal.fire('Error', msg || 'Gagal import', 'error');
                    } else if (importError) {
                        importError.textContent = msg || 'Gagal import';
                    }
                    return;
                }
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Berhasil', json.message || 'Import berhasil', 'success');
                }
                if (importInput) importInput.value = '';
                importModal?.hide();
                reloadTable();
            } catch (err) {
                if (typeof Swal !== 'undefined') Swal.fire('Error', 'Gagal import', 'error');
            } finally {
                importSubmit.disabled = false;
                importSubmit.removeAttribute('aria-busy');
                importSubmit.innerHTML = originalContent;
            }
        });

        itemImportSubmit?.addEventListener('click', async () => {
            if (!itemImportUrl) return;
            if (itemImportError) itemImportError.textContent = '';

            const file = itemImportInput?.files?.[0];
            if (!file) {
                if (itemImportError) itemImportError.textContent = 'Pilih file Excel item terlebih dahulu.';
                return;
            }
            if (requireExplicitWarehouseSelection && !warehouseSelect?.value) {
                if (itemImportError) itemImportError.textContent = 'Pilih gudang tujuan pada form sebelum import item.';
                return;
            }

            let confirmed = true;
            if (hasMeaningfulItemRows() && typeof Swal !== 'undefined') {
                const confirmation = await Swal.fire({
                    title: 'Ganti daftar item saat ini?',
                    text: 'Item hasil import akan menggantikan seluruh daftar item di form ini agar datanya tidak tercampur.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, ganti item',
                    cancelButtonText: 'Batal',
                    buttonsStyling: false,
                    customClass: {
                        confirmButton: 'btn btn-primary',
                        cancelButton: 'btn btn-light',
                    },
                });
                confirmed = confirmation.isConfirmed;
            }

            if (!confirmed) {
                return;
            }

            const formData = new FormData();
            formData.append('file', file);
            if (warehouseSelect?.value) formData.append('warehouse_id', warehouseSelect.value);
            const originalContent = itemImportSubmit.innerHTML;
            itemImportSubmit.disabled = true;
            itemImportSubmit.setAttribute('aria-busy', 'true');
            itemImportSubmit.innerHTML = '<span class="spinner-border spinner-border-sm align-middle me-2" role="status" aria-hidden="true"></span>Memproses item...';

            try {
                const res = await fetch(itemImportUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: formData,
                });

                const text = await res.text();
                let json;
                try { json = JSON.parse(text); } catch (err) {
                    if (typeof Swal !== 'undefined') Swal.fire('Error', 'Respons server tidak valid', 'error');
                    return;
                }

                if (!res.ok) {
                    const msg = json?.errors?.file?.[0] || json?.message || 'Gagal import item';
                    if (typeof Swal !== 'undefined') {
                        Swal.fire('Error', msg, 'error');
                    } else if (itemImportError) {
                        itemImportError.textContent = msg;
                    }
                    return;
                }

                replaceItemsFromImport(json.items || []);
                if (itemImportInput) itemImportInput.value = '';
                itemImportModal?.hide();
                window.setTimeout(() => {
                    modal?.show();
                }, 180);

                if (typeof Swal !== 'undefined') {
                    const summary = json?.summary || {};
                    const parts = [
                        summary.count ? `${summary.count} item` : null,
                        summary.qty ? `Qty ${summary.qty}` : null,
                        summary.koli ? `Koli ${summary.koli}` : null,
                    ].filter(Boolean).join(' | ');

                    Swal.fire('Berhasil', parts ? `${json.message || 'Import item berhasil'} (${parts})` : (json.message || 'Import item berhasil'), 'success');
                }
            } catch (err) {
                console.error(err);
                if (typeof Swal !== 'undefined') Swal.fire('Error', 'Gagal import item', 'error');
            } finally {
                itemImportSubmit.disabled = false;
                itemImportSubmit.removeAttribute('aria-busy');
                itemImportSubmit.innerHTML = originalContent;
            }
        });

        tableEl.on('click', '.btn-download-qr', async function(e) {
            e.preventDefault();
            const url = this.getAttribute('data-url');
            if (!url) return;

            try {
                await downloadQrPdfWithProgress(url);
                if (typeof Swal !== 'undefined') {
                    window.setTimeout(() => {
                        Swal.fire({
                            title: 'Selesai',
                            text: 'File QR Code berhasil disiapkan.',
                            icon: 'success',
                            timer: 1200,
                            showConfirmButton: false,
                        });
                    }, 250);
                }
            } catch (err) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Error', err?.message || 'Gagal mengunduh QR Code.', 'error');
                }
            }
        });

        tableEl.on('click', '.btn-edit', async function(e) {
            e.preventDefault();
            const id = this.getAttribute('data-id');
            if (!id) return;
            try {
            const rowType = this.getAttribute('data-type') || defaultTypeFilter;
            const res = await fetch(resolveRoute(rowType, 'show').replace(':id', id), { headers: { 'Accept': 'application/json' }});
            const json = await res.json();
            if (!res.ok) {
                if (typeof Swal !== 'undefined') Swal.fire('Error', json.message || 'Gagal memuat data', 'error');
                return;
            }
            form.dataset.editId = id;
            form.dataset.flowType = rowType;
            if (modalTitle) modalTitle.textContent = `Edit ${json.code || ''}`.trim();
                document.getElementById('flow_ref_no').value = json.ref_no || '';
                applySupplierVisibility(rowType);
                applyRecipientVisibility(rowType);
                if (recipientNameEl) recipientNameEl.value = json.recipient_name || '';
                if (recipientPhoneEl) recipientPhoneEl.value = json.recipient_phone || '';
                if (recipientAddressEl) recipientAddressEl.value = json.recipient_address || '';
                if (supplierSelect) {
                    supplierSelect.value = json.supplier_id ? String(json.supplier_id) : '';
                    if (typeof $ !== 'undefined' && $(supplierSelect).data('select2')) {
                        $(supplierSelect).val(supplierSelect.value).trigger('change.select2');
                    }
                }
                if (suratJalanNoEl) suratJalanNoEl.value = json.surat_jalan_no || '';
                if (suratJalanImageEl) suratJalanImageEl.value = '';
                if (removeSuratJalanImageEl) removeSuratJalanImageEl.checked = false;
                if (json.surat_jalan_image_url && suratJalanImagePreview && suratJalanImageLink) {
                    suratJalanImageLink.href = json.surat_jalan_image_url;
                    suratJalanImagePreview.style.display = '';
                } else {
                    if (suratJalanImageLink) suratJalanImageLink.href = '#';
                    if (suratJalanImagePreview) suratJalanImagePreview.style.display = 'none';
                }
                document.getElementById('flow_note').value = json.note || '';
                applyWarehouseVisibility(rowType);
                if (warehouseSelect) {
                    const fallbackId = displayWarehouseId || defaultWarehouseId || '';
                    warehouseSelect.value = json.warehouse_id ? String(json.warehouse_id) : (fallbackId ? String(fallbackId) : '');
                    applyInputUnitAvailability();
                }
                if (fpTransacted) {
                    fpTransacted.setDate(json.transacted_at || null, true, 'Y-m-d\\TH:i');
                } else {
                    document.getElementById('flow_transacted_at').value = json.transacted_at || '';
                }
                if (fpSuratJalan) {
                    fpSuratJalan.setDate(json.surat_jalan_at || null, true, 'Y-m-d');
                } else if (suratJalanAtEl) {
                    suratJalanAtEl.value = json.surat_jalan_at || '';
                }

                itemsContainer.innerHTML = '';
                (json.items || []).forEach(item => createItemRow(item));
                if ((json.items || []).length === 0) {
                    createItemRow();
                }
                clearErrors();
                validateUniqueItems();
                modal?.show();
            } catch (err) {
                console.error(err);
                if (typeof Swal !== 'undefined') Swal.fire('Error', 'Gagal memuat data', 'error');
            }
        });

        tableEl.on('click', '.btn-delete', async function(e) {
            e.preventDefault();
            const id = this.getAttribute('data-id');
            const rowType = this.getAttribute('data-type') || defaultTypeFilter;
            if (!id) return;
            let confirmed = true;
            if (typeof Swal !== 'undefined') {
                const res = await Swal.fire({
                    title: 'Apakah Anda yakin?',
                    text: deleteWarningText,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Hapus',
                    cancelButtonText: 'Batal',
                    buttonsStyling: false,
                    customClass: {
                        confirmButton: 'btn btn-danger',
                        cancelButton: 'btn btn-light'
                    }
                });
                confirmed = res.isConfirmed;
            }
            if (!confirmed) return;
            try {
                const res = await fetch(resolveRoute(rowType, 'delete').replace(':id', id), {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'Accept': 'application/json',
                    },
                    body: new URLSearchParams({ _method: 'DELETE' }),
                });
                const json = await res.json();
                if (!res.ok) {
                    if (typeof Swal !== 'undefined') Swal.fire('Error', json.message || 'Gagal menghapus', 'error');
                    return;
                }
                if (typeof Swal !== 'undefined') Swal.fire('Berhasil', json.message || 'Berhasil', 'success');
                reloadTable();
            } catch (err) {
                console.error(err);
                if (typeof Swal !== 'undefined') Swal.fire('Error', 'Gagal menghapus', 'error');
            }
        });

        tableEl.on('click', '.btn-approve', async function(e) {
            e.preventDefault();
            const id = this.getAttribute('data-id');
            const rowType = this.getAttribute('data-type') || defaultTypeFilter;
            if (!id) return;
            const approveUrl = resolveRoute(rowType, 'approve')?.replace(':id', id);
            if (!approveUrl) return;
            let confirmed = true;
            if (typeof Swal !== 'undefined') {
                const res = await Swal.fire({
                    title: 'Setujui data ini?',
                    text: 'Setelah disetujui, data tidak bisa diubah atau dihapus.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Approve',
                    cancelButtonText: 'Batal',
                    buttonsStyling: false,
                    customClass: {
                        confirmButton: 'btn btn-success',
                        cancelButton: 'btn btn-light'
                    }
                });
                confirmed = res.isConfirmed;
            }
            if (!confirmed) return;
            try {
                const res = await fetch(approveUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                });
                const json = await res.json();
                if (!res.ok) {
                    if (typeof Swal !== 'undefined') Swal.fire('Error', json.message || 'Gagal menyetujui', 'error');
                    return;
                }
                if (typeof Swal !== 'undefined') Swal.fire('Berhasil', json.message || 'Berhasil', 'success');
                reloadTable();
            } catch (err) {
                if (typeof Swal !== 'undefined') Swal.fire('Error', 'Gagal menyetujui', 'error');
            }
        });

        form?.addEventListener('submit', async (e) => {
            e.preventDefault();
            clearErrors();
            if (!validateUniqueItems()) {
                if (typeof Swal !== 'undefined') Swal.fire('Error', 'Item tidak boleh duplikat', 'error');
                return;
            }

            const isEdit = !!form.dataset.editId;
            const flowType = form.dataset.flowType || defaultTypeFilter || '';
            const url = isEdit
                ? resolveRoute(flowType, 'update').replace(':id', form.dataset.editId)
                : resolveRoute(flowType, 'store');
            const formData = new FormData(form);
            if (isEdit) formData.append('_method', 'PUT');

            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: formData,
                });
                const text = await res.text();
                let json;
                try { json = JSON.parse(text); } catch (err) {
                    console.error('Invalid JSON', text);
                    if (typeof Swal !== 'undefined') Swal.fire('Error', 'Respons server tidak valid', 'error');
                    return;
                }
                if (!res.ok) {
                    if (json?.errors) {
                        const unhandled = [];
                        Object.entries(json.errors).forEach(([key, msgs]) => {
                            if (key.startsWith('items.')) {
                                const parts = key.split('.');
                                const idx = parseInt(parts[1], 10);
                                const field = parts[2];
                                const row = itemsContainer.querySelectorAll('.flow-item-row')[idx];
                                const errEl = row ? row.querySelector(`[data-error-for="${field}"]`) : null;
                                const fieldEl = row
                                    ? (field === 'item_id'
                                        ? row.querySelector('.flow-item-select')
                                        : row.querySelector(`[data-name="${field}"]`))
                                    : null;
                                if (errEl) errEl.textContent = msgs.join(', ');
                                else unhandled.push(msgs.join(', '));
                                if (fieldEl) fieldEl.classList.add('is-invalid');
                            } else {
                                const errEl = document.getElementById(`error_${key}`);
                                if (errEl) errEl.textContent = msgs.join(', ');
                                else unhandled.push(msgs.join(', '));
                            }
                        });
                        if (unhandled.length && typeof Swal !== 'undefined') {
                            Swal.fire('Error', unhandled.join(', '), 'error');
                        }
                    } else if (typeof Swal !== 'undefined') {
                        Swal.fire('Error', json.message || 'Gagal menyimpan', 'error');
                    }
                    return;
                }
                if (typeof Swal !== 'undefined') Swal.fire('Berhasil', json.message || 'Berhasil', 'success');
                modal?.hide();
                reloadTable();
            } catch (err) {
                console.error(err);
                if (typeof Swal !== 'undefined') Swal.fire('Error', 'Gagal menyimpan', 'error');
            }
        });
    });
</script>
@endpush

@include('layouts.partials.form-submit-confirmation')
