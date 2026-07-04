@extends('layouts.admin')

@section('title', 'Item Stocks')
@section('page_title', 'Item Stocks')

@php
    use App\Support\Permission as Perm;
    $canCreateStockAdjustment = Perm::can(auth()->user(), 'admin.inventory.stock-adjustments.index', 'create');
@endphp

@push('styles')
<style>
    .item-stock-toolbar {
        gap: 1rem;
    }

    .item-stock-search {
        width: min(100%, 680px);
    }

    .item-stock-search .form-label,
    .item-stock-actions .form-label {
        margin-bottom: 0.35rem;
    }

    .item-stock-search-box {
        position: relative;
    }

    .item-stock-search-box .svg-icon {
        top: 0.95rem;
        left: 1.25rem;
        opacity: 0.6;
        pointer-events: none;
    }

    .item-stock-search-box textarea {
        min-height: 74px;
        resize: vertical;
    }

    .item-stock-actions {
        gap: 0.75rem;
    }

    @media (max-width: 991.98px) {
        .item-stock-search,
        .item-stock-actions,
        .item-stock-actions .btn {
            width: 100%;
        }

        .item-stock-actions {
            align-items: stretch !important;
        }
    }
</style>
@endpush

@section('content')
<div class="card">
    <div class="card-header border-0 pt-6 item-stock-toolbar align-items-start flex-column flex-lg-row">
        <div class="card-title flex-column align-items-stretch my-0 item-stock-search">
            <label class="form-label fw-bold text-gray-700">Pencarian item</label>
            <div class="d-flex flex-column gap-2">
                <div class="item-stock-search-box">
                    <span class="svg-icon svg-icon-1 position-absolute">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <rect opacity="0.5" x="17.0365" y="15.1223" width="8.15546" height="2" rx="1" transform="rotate(45 17.0365 15.1223)" fill="black" />
                            <path d="M11 19C6.55556 19 3 15.4444 3 11C3 6.55556 6.55556 3 11 3C15.4444 3 19 6.55556 19 11C19 15.4444 15.4444 19 11 19ZM11 5C7.53333 5 5 7.53333 5 11C5 14.4667 7.53333 17 11 17C14.4667 17 17 14.4667 17 11C17 7.53333 14.4667 5 11 5Z" fill="black" />
                        </svg>
                    </span>
                    <textarea class="form-control form-control-solid ps-14" rows="2" placeholder="SKU atau nama item" data-kt-filter="search"></textarea>
                </div>
                <div class="d-flex align-items-end gap-3 flex-wrap">
                    <div class="w-150px">
                        <label class="form-label fs-7 text-muted">Mode</label>
                        <select class="form-select form-select-solid" id="filter_item_stocks_search_mode" data-search-mode-control="1" aria-label="Mode pencarian">
                            <option value="contains" selected>Mirip</option>
                            <option value="exact">Persis</option>
                        </select>
                    </div>
                    <div class="form-text text-muted pb-3">Pisahkan beberapa SKU/nama dengan koma atau baris baru.</div>
                </div>
            </div>
        </div>
        <div class="card-toolbar align-self-stretch align-self-lg-start">
            <div class="d-flex justify-content-end align-items-end flex-wrap item-stock-actions">
                <div class="w-125px">
                    <label class="form-label fs-7 text-muted">Tampil</label>
                    <select class="form-select form-select-solid" id="filter_item_stocks_limit" aria-label="Jumlah data">
                        <option value="10" selected>10 data</option>
                        <option value="20">20 data</option>
                        <option value="50">50 data</option>
                        <option value="100">100 data</option>
                    </select>
                </div>
                <div class="w-150px">
                    <label class="form-label fs-7 text-muted">Status SKU</label>
                    <select class="form-select form-select-solid" id="filter_item_stocks_status" aria-label="Status SKU">
                        <option value="active" selected>Aktif</option>
                        <option value="inactive">Nonaktif</option>
                        <option value="all">Semua</option>
                    </select>
                </div>
                <div class="w-200px">
                    <label class="form-label fs-7 text-muted">Stok Pengaman</label>
                    <select class="form-select form-select-solid" id="filter_item_stocks_safety" aria-label="Filter stok pengaman">
                        <option value="all" selected>Semua</option>
                        <option value="below_any">Di bawah pengaman</option>
                        <option value="below_main">Di bawah {{ $defaultWarehouseLabel ?? 'Gudang Besar' }}</option>
                        <option value="below_display">Di bawah {{ $displayWarehouseLabel ?? 'Gudang Display' }}</option>
                        <option value="normal">Normal</option>
                        <option value="unmonitored">Tidak dimonitor</option>
                    </select>
                </div>
                <button type="button" class="btn btn-light-primary" id="btn_export_item_stocks">Export Excel</button>
                <button type="button" class="btn btn-primary" id="btn_bulk_safety_main" disabled>Ubah Safety Gudang Besar</button>
                <button type="button" class="btn btn-primary" id="btn_bulk_safety_display" disabled>Ubah Safety Display</button>
            </div>
        </div>
    </div>
    <div class="card-body py-6">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5" id="item_stocks_table">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                        <th class="w-30px">
                            <div class="form-check form-check-sm form-check-custom form-check-solid">
                                <input class="form-check-input" type="checkbox" id="item_stocks_select_all" aria-label="Pilih semua item di halaman ini" />
                            </div>
                        </th>
                        <th>ID</th>
                        <th>SKU</th>
                        <th>Nama</th>
                        <th>Tipe</th>
                        <th class="text-end">Stok {{ $defaultWarehouseLabel ?? 'Gudang Besar' }}</th>
                        <th class="text-end">Safety {{ $defaultWarehouseLabel ?? 'Gudang Besar' }}</th>
                        <th class="text-end">Stok {{ $displayWarehouseLabel ?? 'Gudang Display' }}</th>
                        <th class="text-end">Safety {{ $displayWarehouseLabel ?? 'Gudang Display' }}</th>
                        <th class="text-end">Stok {{ $damagedWarehouseLabel ?? 'Gudang Rusak' }}</th>
                        <th class="text-end">Total Stok Baik</th>
                        <th class="text-end">Total Fisik</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modal_item_detail" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 class="fw-bolder mb-1">Detail Item</h2>
                    <div class="text-muted fs-7" id="item_detail_subtitle">-</div>
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
            <div class="modal-body py-6">
                <div class="row g-6 mb-6">
                    <div class="col-md-3">
                        <div class="text-muted fs-7">SKU</div>
                        <div class="fw-bold fs-6" id="item_detail_sku">-</div>
                    </div>
                    <div class="col-md-5">
                        <div class="text-muted fs-7">Nama Item</div>
                        <div class="fw-bold fs-6" id="item_detail_name">-</div>
                    </div>
                    <div class="col-md-2">
                        <div class="text-muted fs-7">Tipe</div>
                        <div id="item_detail_type">-</div>
                    </div>
                    <div class="col-md-2">
                        <div class="text-muted fs-7">Kategori</div>
                        <div class="fw-bold" id="item_detail_category">-</div>
                    </div>
                </div>

                <div class="row g-6 mb-6">
                    <div class="col-md-4">
                        <div class="text-muted fs-7">Alamat</div>
                        <div class="fw-bold" id="item_detail_address">-</div>
                    </div>
                    <div class="col-md-2">
                        <div class="text-muted fs-7">Area</div>
                        <div class="fw-bold" id="item_detail_area">-</div>
                    </div>
                    <div class="col-md-2">
                        <div class="text-muted fs-7">Rack</div>
                        <div class="fw-bold" id="item_detail_rack">-</div>
                    </div>
                    <div class="col-md-2">
                        <div class="text-muted fs-7">Column</div>
                        <div class="fw-bold" id="item_detail_column">-</div>
                    </div>
                    <div class="col-md-2">
                        <div class="text-muted fs-7">Row</div>
                        <div class="fw-bold" id="item_detail_row">-</div>
                    </div>
                </div>

                <div class="separator my-6"></div>

                <div class="row g-6 mb-6">
                    <div class="col-md-3">
                        <div class="text-muted fs-7">{{ $defaultWarehouseLabel ?? 'Gudang Besar' }}</div>
                        <div class="fw-bolder fs-5" id="item_detail_stock_main">0 pcs</div>
                        <div id="item_detail_koli_info" class="mt-1"></div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-muted fs-7">Safety {{ $defaultWarehouseLabel ?? 'Gudang Besar' }}</div>
                        <div class="fw-bold" id="item_detail_safety_main">0</div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-muted fs-7">{{ $displayWarehouseLabel ?? 'Gudang Display' }}</div>
                        <div class="fw-bolder fs-5" id="item_detail_stock_display">0 pcs</div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-muted fs-7">{{ $damagedWarehouseLabel ?? 'Gudang Rusak' }}</div>
                        <div class="fw-bolder fs-5" id="item_detail_stock_damaged">0 pcs</div>
                    </div>
                </div>

                <div class="row g-6 mb-6">
                    <div class="col-md-3">
                        <div class="text-muted fs-7">Total Stok Baik</div>
                        <div class="fw-bold" id="item_detail_stock_good_total">0 pcs</div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-muted fs-7">Total Fisik</div>
                        <div class="fw-bold" id="item_detail_stock_total">0 pcs</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted fs-7">Deskripsi</div>
                        <div class="fw-bold" id="item_detail_description">-</div>
                    </div>
                </div>

                <div id="item_detail_bundle_section" class="mt-6" style="display:none;">
                    <div class="separator my-6"></div>
                    <div class="fw-bolder fs-5 mb-3">Komponen Bundle</div>
                    <div class="table-responsive">
                        <table class="table align-middle table-row-dashed fs-6 gy-4 mb-0">
                            <thead>
                                <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                    <th>SKU Komponen</th>
                                    <th>Nama Komponen</th>
                                    <th class="text-end">Qty Dibutuhkan</th>
                                </tr>
                            </thead>
                            <tbody id="item_detail_bundle_components"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal_edit_stock" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 class="fw-bolder mb-1">Edit Stok</h2>
                    <div class="text-muted fs-7" id="edit_stock_subtitle">-</div>
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
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <form class="form" id="edit_stock_form">
                    @csrf
                    <input type="hidden" id="edit_stock_item_id" />
                    <input type="hidden" id="edit_stock_warehouse_id" />
                    <input type="hidden" id="edit_stock_mode" />
                    <input type="hidden" id="edit_stock_current" />
                    <input type="hidden" id="edit_stock_koli_qty" />
                    <input type="hidden" id="edit_stock_current_koli" />
                    <input type="hidden" id="edit_stock_current_remainder" />

                    <div class="row g-6 mb-6">
                        <div class="col-md-7">
                            <div class="text-muted fs-7">Item</div>
                            <div class="fw-bold" id="edit_stock_item_label">-</div>
                        </div>
                        <div class="col-md-5">
                            <div class="text-muted fs-7">Gudang</div>
                            <div class="fw-bold" id="edit_stock_warehouse_label">-</div>
                        </div>
                    </div>

                    <div class="row g-6 mb-6">
                        <div class="col-md-6">
                            <label class="fs-6 fw-bold form-label mb-2">Stok Saat Ini</label>
                            <input type="text" class="form-control form-control-solid" id="edit_stock_current_label" readonly />
                        </div>
                        <div class="col-md-6" id="edit_stock_target_pcs_wrap">
                            <label class="required fs-6 fw-bold form-label mb-2">Stok Akhir (pcs)</label>
                            <input type="number" min="0" step="1" class="form-control form-control-solid" id="edit_stock_target_pcs" />
                        </div>
                        <div class="col-md-6" id="edit_stock_target_koli_wrap" style="display:none;">
                            <label class="required fs-6 fw-bold form-label mb-2">Stok Akhir (koli)</label>
                            <input type="number" min="0" step="1" class="form-control form-control-solid" id="edit_stock_target_koli" />
                            <div class="form-text text-muted" id="edit_stock_koli_hint"></div>
                        </div>
                    </div>

                    <div class="alert alert-primary d-flex align-items-start p-5 mb-6">
                        <span class="svg-icon svg-icon-2hx svg-icon-primary me-4">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                <path opacity="0.3" d="M12 22C17.5 22 22 17.5 22 12S17.5 2 12 2 2 6.5 2 12s4.5 10 10 10Z" fill="black"/>
                                <path d="M12 7C11.4 7 11 7.4 11 8s.4 1 1 1 1-.4 1-1-.4-1-1-1Zm1 5c0-.6-.4-1-1-1s-1 .4-1 1v5c0 .6.4 1 1 1s1-.4 1-1v-5Z" fill="black"/>
                            </svg>
                        </span>
                        <div>
                            <div class="fw-bold" id="edit_stock_adjustment_title">Penyesuaian stok</div>
                            <div class="text-muted" id="edit_stock_adjustment_preview">Isi stok akhir untuk melihat selisih.</div>
                        </div>
                    </div>

                    <div class="fv-row mb-6">
                        <label class="fs-6 fw-bold form-label mb-2">Catatan</label>
                        <textarea class="form-control form-control-solid" id="edit_stock_note" rows="3"></textarea>
                    </div>

                    <div class="text-end pt-3">
                        <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">
                            <span class="indicator-label">Simpan Penyesuaian</span>
                            <span class="indicator-progress">Please wait...
                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal_safety_stock" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-550px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bolder" id="safety_modal_title">Safety Stock per Gudang</h2>
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
                <form class="form" id="safety_stock_form">
                    @csrf
                    <input type="hidden" name="item_id" id="safety_item_id" />
                    <div class="mb-6">
                        <div class="fw-bold">Item</div>
                        <div id="safety_item_label" class="text-muted">-</div>
                    </div>
                    <div class="fv-row mb-6" id="safety_main_wrap">
                        <label class="fs-6 fw-bold form-label mb-2">Safety {{ $defaultWarehouseLabel ?? 'Gudang Besar' }}</label>
                        <input type="number" min="0" class="form-control form-control-solid" name="safety_main" id="safety_main" />
                        <div class="form-text text-muted" id="safety_main_hint">Kosongkan untuk gunakan safety default item.</div>
                        <div class="form-check form-switch form-check-custom form-check-solid mt-4">
                            <input class="form-check-input" type="checkbox" value="1" id="monitor_main" checked />
                            <label class="form-check-label" for="monitor_main">Monitoring {{ $defaultWarehouseLabel ?? 'Gudang Besar' }}</label>
                        </div>
                    </div>
                    <div class="fv-row mb-6" id="safety_display_wrap">
                        <label class="fs-6 fw-bold form-label mb-2">Safety {{ $displayWarehouseLabel ?? 'Gudang Display' }}</label>
                        <input type="number" min="0" class="form-control form-control-solid" name="safety_display" id="safety_display" />
                        <div class="form-text text-muted" id="safety_display_hint">Kosongkan untuk gunakan safety default item.</div>
                        <div class="form-check form-switch form-check-custom form-check-solid mt-4">
                            <input class="form-check-input" type="checkbox" value="1" id="monitor_display" checked />
                            <label class="form-check-label" for="monitor_display">Monitoring {{ $displayWarehouseLabel ?? 'Gudang Display' }}</label>
                        </div>
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
@endsection

@push('scripts')
<script>
    const dataUrl = '{{ route('admin.inventory.item-stocks.data') }}';
    const exportUrl = '{{ route('admin.inventory.item-stocks.export') }}';
    const updateSafetyUrl = '{{ $updateSafetyUrl ?? '' }}';
    const stockAdjustmentStoreUrl = '{{ route('admin.inventory.stock-adjustments.store') }}';
    const canCreateStockAdjustment = {{ $canCreateStockAdjustment ? 'true' : 'false' }};
    const mutationIndexUrl = '{{ route('admin.inventory.stock-mutations.index') }}';
    const defaultWarehouseId = {{ !empty($defaultWarehouseId) ? (int) $defaultWarehouseId : 'null' }};
    const displayWarehouseId = {{ !empty($displayWarehouseId) ? (int) $displayWarehouseId : 'null' }};
    const damagedWarehouseId = {{ !empty($damagedWarehouseId) ? (int) $damagedWarehouseId : 'null' }};
    const csrfToken = '{{ csrf_token() }}';

    document.addEventListener('DOMContentLoaded', () => {
        const tableEl = $('#item_stocks_table');
        const searchInput = document.querySelector('[data-kt-filter="search"]');
        const searchModeSelect = document.getElementById('filter_item_stocks_search_mode');
        const limitSelect = document.getElementById('filter_item_stocks_limit');
        const statusSelect = document.getElementById('filter_item_stocks_status');
        const safetySelect = document.getElementById('filter_item_stocks_safety');
        const exportBtn = document.getElementById('btn_export_item_stocks');
        const bulkSafetyMainBtn = document.getElementById('btn_bulk_safety_main');
        const bulkSafetyDisplayBtn = document.getElementById('btn_bulk_safety_display');
        const selectAllCheckbox = document.getElementById('item_stocks_select_all');
        const safetyModalEl = document.getElementById('modal_safety_stock');
        const safetyModal = safetyModalEl ? new bootstrap.Modal(safetyModalEl) : null;
        const safetyForm = document.getElementById('safety_stock_form');
        const safetyModalTitle = document.getElementById('safety_modal_title');
        const safetyItemId = document.getElementById('safety_item_id');
        const safetyItemLabel = document.getElementById('safety_item_label');
        const safetyMainWrap = document.getElementById('safety_main_wrap');
        const safetyMainHint = document.getElementById('safety_main_hint');
        const safetyMain = document.getElementById('safety_main');
        const monitorMain = document.getElementById('monitor_main');
        const safetyDisplayWrap = document.getElementById('safety_display_wrap');
        const safetyDisplay = document.getElementById('safety_display');
        const safetyDisplayHint = document.getElementById('safety_display_hint');
        const monitorDisplay = document.getElementById('monitor_display');
        const itemDetailModalEl = document.getElementById('modal_item_detail');
        const itemDetailModal = itemDetailModalEl ? new bootstrap.Modal(itemDetailModalEl) : null;
        const editStockModalEl = document.getElementById('modal_edit_stock');
        const editStockModal = editStockModalEl ? new bootstrap.Modal(editStockModalEl) : null;
        const editStockForm = document.getElementById('edit_stock_form');

        if (!tableEl.length || !$.fn.DataTable) {
            console.error('DataTables unavailable');
            return;
        }

        const formatStockNumber = (value) => {
            const numeric = Number(value);
            return Number.isFinite(numeric) ? numeric.toLocaleString('id-ID') : '0';
        };

        const selectedItemIds = new Set();
        let safetyBulkMode = false;
        let safetyBulkTarget = null;

        const escapeHtml = (value) => String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');

        const renderMainWarehouseKoli = (row, align = 'end') => {
            const koliQty = Number(row.koli_qty || 0);
            if (!Number.isFinite(koliQty) || koliQty <= 0) {
                return '<div class="text-muted fs-8 mt-1">Isi/koli belum diset</div>';
            }

            const fullKoli = Number.isFinite(Number(row.stock_main_koli)) ? Number(row.stock_main_koli) : 0;
            const remainder = Number.isFinite(Number(row.stock_main_koli_remainder)) ? Number(row.stock_main_koli_remainder) : 0;
            const remainderBadge = remainder > 0
                ? `<span class="badge badge-light-warning">+ ${formatStockNumber(remainder)} pcs</span>`
                : '';

            return `
                <div class="d-flex justify-content-${align} gap-1 flex-wrap mt-1">
                    <span class="badge badge-light-primary">${formatStockNumber(fullKoli)} koli</span>
                    ${remainderBadge}
                    <span class="badge badge-light-secondary">isi ${formatStockNumber(koliQty)}/koli</span>
                </div>
            `;
        };

        const renderEditStockButton = (row, options) => {
            if (!canCreateStockAdjustment || row.item_type === 'bundle' || !options?.warehouseId) {
                return '';
            }

            return `
                <button type="button"
                    class="btn btn-icon btn-sm btn-light-primary ms-2 btn-edit-stock"
                    title="Edit stok"
                    data-id="${row.id}"
                    data-warehouse-id="${options.warehouseId}"
                    data-warehouse-label="${escapeHtml(options.warehouseLabel || '')}"
                    data-stock-key="${options.stockKey}"
                    data-mode="${options.mode || 'pcs'}">
                    <i class="fas fa-pencil-alt fs-8"></i>
                </button>
            `;
        };

        const renderWarehouseStock = (value, type, row, virtualKey, lowFlagKey, options = {}) => {
            if (row.item_type === 'bundle') {
                const virtualValue = Number.isFinite(Number(row[virtualKey])) ? Number(row[virtualKey]) : 0;
                if (type !== 'display') return virtualValue;
                return `<span class="fw-bold text-primary">${virtualValue}</span><div class="text-muted fs-8">virtual</div>`;
            }

            const stockValue = Number.isFinite(Number(value)) ? Number(value) : 0;
            if (type !== 'display') return stockValue;

            if (row[lowFlagKey]) {
                const stockHtml = `<span class="fw-bold text-danger">${formatStockNumber(stockValue)}</span>`;
                const stockWithButton = `<span class="d-inline-flex align-items-center justify-content-end">${stockHtml}${renderEditStockButton(row, options)}</span>`;
                return options.showKoli ? stockWithButton + renderMainWarehouseKoli(row) : stockWithButton;
            }

            const stockHtml = `<span class="fw-bold">${formatStockNumber(stockValue)}</span>`;
            const stockWithButton = `<span class="d-inline-flex align-items-center justify-content-end">${stockHtml}${renderEditStockButton(row, options)}</span>`;
            return options.showKoli ? stockWithButton + renderMainWarehouseKoli(row) : stockWithButton;
        };

        const renderItemTypeBadge = (type) => type === 'bundle'
            ? '<span class="badge badge-light-primary">Bundle</span>'
            : '<span class="badge badge-light-success">Single</span>';

        const renderSafetyValue = (data, row, monitorKey) => {
            if (row.item_type === 'bundle') return '-';
            const value = data ?? 0;
            const monitor = row[monitorKey] !== false;
            const badge = monitor
                ? '<div class="badge badge-light-success mt-1">Dimonitor</div>'
                : '<div class="badge badge-light-secondary mt-1">Tidak dimonitor</div>';
            return `<span>${formatStockNumber(value)}</span>${badge}`;
        };

        const detailText = (id, value) => {
            const el = document.getElementById(id);
            if (el) el.textContent = value === null || value === undefined || value === '' ? '-' : value;
        };

        const detailHtml = (id, value) => {
            const el = document.getElementById(id);
            if (el) el.innerHTML = value || '-';
        };

        const stockLabel = (value) => `${formatStockNumber(value)} pcs`;

        const formatDateTime = (date) => {
            const pad = (n) => String(n).padStart(2, '0');
            return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`;
        };

        const getJakartaNow = () => {
            const jkt = new Date(new Date().toLocaleString('en-US', { timeZone: 'Asia/Jakarta' }));
            return formatDateTime(jkt);
        };

        const setInputValue = (id, value) => {
            const el = document.getElementById(id);
            if (el) el.value = value ?? '';
        };

        const setModalText = (id, value) => {
            const el = document.getElementById(id);
            if (el) el.textContent = value ?? '-';
        };

        const findTableRow = (trigger) => {
            const id = String(trigger?.getAttribute('data-id') || '');
            const rows = dt.rows().data().toArray();
            return rows.find((row) => String(row.id) === id) || null;
        };

        const calculateEditStockPayload = () => {
            const mode = document.getElementById('edit_stock_mode')?.value || 'pcs';
            const currentStock = parseInt(document.getElementById('edit_stock_current')?.value || '0', 10);
            const itemId = document.getElementById('edit_stock_item_id')?.value || '';
            const warehouseId = document.getElementById('edit_stock_warehouse_id')?.value || '';
            const note = document.getElementById('edit_stock_note')?.value || '';
            const current = Number.isFinite(currentStock) ? currentStock : 0;

            if (mode === 'koli') {
                const koliQty = parseInt(document.getElementById('edit_stock_koli_qty')?.value || '0', 10);
                const currentKoli = parseInt(document.getElementById('edit_stock_current_koli')?.value || '0', 10);
                const currentRemainder = parseInt(document.getElementById('edit_stock_current_remainder')?.value || '0', 10);
                const targetKoli = parseInt(document.getElementById('edit_stock_target_koli')?.value || '', 10);

                if (!Number.isFinite(koliQty) || koliQty <= 0) {
                    return { error: 'Isi/koli item belum diset. Penyesuaian Gudang Besar wajib satuan koli.' };
                }
                if (!Number.isFinite(targetKoli) || targetKoli < 0) {
                    return { error: 'Stok akhir koli wajib diisi dengan angka valid.' };
                }

                const deltaKoli = targetKoli - currentKoli;
                if (deltaKoli === 0) {
                    return { error: 'Stok akhir harus berbeda dari stok saat ini.' };
                }

                const adjustmentKoli = Math.abs(deltaKoli);
                const qty = adjustmentKoli * koliQty;
                return {
                    itemId,
                    warehouseId,
                    direction: deltaKoli > 0 ? 'in' : 'out',
                    qty,
                    koli: adjustmentKoli,
                    targetStock: (targetKoli * koliQty) + (Number.isFinite(currentRemainder) ? currentRemainder : 0),
                    note,
                };
            }

            const targetStock = parseInt(document.getElementById('edit_stock_target_pcs')?.value || '', 10);
            if (!Number.isFinite(targetStock) || targetStock < 0) {
                return { error: 'Stok akhir wajib diisi dengan angka valid.' };
            }

            const delta = targetStock - current;
            if (delta === 0) {
                return { error: 'Stok akhir harus berbeda dari stok saat ini.' };
            }

            return {
                itemId,
                warehouseId,
                direction: delta > 0 ? 'in' : 'out',
                qty: Math.abs(delta),
                koli: '',
                targetStock,
                note,
            };
        };

        const syncEditStockPreview = () => {
            const payload = calculateEditStockPayload();
            const preview = document.getElementById('edit_stock_adjustment_preview');
            const title = document.getElementById('edit_stock_adjustment_title');
            if (!preview || !title) return;

            if (payload.error) {
                title.textContent = 'Penyesuaian stok';
                preview.textContent = payload.error;
                return;
            }

            const sign = payload.direction === 'in' ? '+' : '-';
            title.textContent = payload.direction === 'in' ? 'Akan dibuat penyesuaian tambah' : 'Akan dibuat penyesuaian kurang';
            const koliText = payload.koli ? ` (${payload.koli} koli)` : '';
            preview.textContent = `Selisih ${sign}${formatStockNumber(payload.qty)} pcs${koliText}. Stok akhir setelah approve: ${formatStockNumber(payload.targetStock)} pcs.`;
        };

        const openEditStockModal = (trigger) => {
            const row = findTableRow(trigger);
            if (!row || row.item_type === 'bundle') return;

            const mode = trigger.getAttribute('data-mode') || 'pcs';
            const warehouseId = trigger.getAttribute('data-warehouse-id') || '';
            const warehouseLabel = trigger.getAttribute('data-warehouse-label') || '';
            const stockKey = trigger.getAttribute('data-stock-key') || 'stock_display';
            const currentStock = Number.isFinite(Number(row[stockKey])) ? Number(row[stockKey]) : 0;
            const koliQty = Number.isFinite(Number(row.koli_qty)) ? Number(row.koli_qty) : 0;
            const currentKoli = Number.isFinite(Number(row.stock_main_koli)) ? Number(row.stock_main_koli) : 0;
            const currentRemainder = Number.isFinite(Number(row.stock_main_koli_remainder)) ? Number(row.stock_main_koli_remainder) : 0;

            setModalText('edit_stock_subtitle', `${row.sku || '-'} - ${row.name || '-'}`);
            setModalText('edit_stock_item_label', `${row.sku || '-'} - ${row.name || '-'}`);
            setModalText('edit_stock_warehouse_label', warehouseLabel || '-');
            setInputValue('edit_stock_item_id', row.id);
            setInputValue('edit_stock_warehouse_id', warehouseId);
            setInputValue('edit_stock_mode', mode);
            setInputValue('edit_stock_current', currentStock);
            setInputValue('edit_stock_koli_qty', koliQty);
            setInputValue('edit_stock_current_koli', currentKoli);
            setInputValue('edit_stock_current_remainder', currentRemainder);
            setInputValue('edit_stock_note', `Edit stok ${row.sku || ''} dari halaman Item Stocks.`);

            const pcsWrap = document.getElementById('edit_stock_target_pcs_wrap');
            const koliWrap = document.getElementById('edit_stock_target_koli_wrap');
            if (mode === 'koli') {
                if (pcsWrap) pcsWrap.style.display = 'none';
                if (koliWrap) koliWrap.style.display = '';
                setInputValue('edit_stock_current_label', `${formatStockNumber(currentStock)} pcs (${formatStockNumber(currentKoli)} koli${currentRemainder > 0 ? ` + ${formatStockNumber(currentRemainder)} pcs` : ''})`);
                setInputValue('edit_stock_target_pcs', '');
                setInputValue('edit_stock_target_koli', currentKoli);
                setModalText('edit_stock_koli_hint', koliQty > 0 ? `Isi/koli: ${formatStockNumber(koliQty)} pcs. Penyesuaian Gudang Besar hanya menerima kolian bulat.` : 'Isi/koli item belum diset.');
            } else {
                if (pcsWrap) pcsWrap.style.display = '';
                if (koliWrap) koliWrap.style.display = 'none';
                setInputValue('edit_stock_current_label', `${formatStockNumber(currentStock)} pcs`);
                setInputValue('edit_stock_target_pcs', currentStock);
                setInputValue('edit_stock_target_koli', '');
                setModalText('edit_stock_koli_hint', '');
            }

            syncEditStockPreview();
            editStockModal?.show();
            setTimeout(() => {
                document.getElementById(mode === 'koli' ? 'edit_stock_target_koli' : 'edit_stock_target_pcs')?.focus();
            }, 150);
        };

        const showItemDetail = (row) => {
            if (!row) return;

            detailText('item_detail_subtitle', `${row.sku || '-'} - ${row.name || '-'}`);
            detailText('item_detail_sku', row.sku);
            detailText('item_detail_name', row.name);
            detailHtml('item_detail_type', renderItemTypeBadge(row.item_type));
            detailText('item_detail_category', row.category);
            detailText('item_detail_address', row.address);
            detailText('item_detail_area', row.area_code);
            detailText('item_detail_rack', row.rack_code);
            detailText('item_detail_column', row.column_no);
            detailText('item_detail_row', row.row_no);
            detailText('item_detail_description', row.description);

            if (row.item_type === 'bundle') {
                detailText('item_detail_stock_main', stockLabel(row.virtual_main || 0));
                detailHtml('item_detail_koli_info', '<span class="badge badge-light-primary">virtual</span>');
                detailText('item_detail_safety_main', '-');
                detailText('item_detail_stock_display', stockLabel(row.virtual_display || 0));
                detailText('item_detail_stock_damaged', '-');
                detailText('item_detail_stock_good_total', stockLabel(row.virtual_total || 0));
                detailText('item_detail_stock_total', '-');
            } else {
                detailText('item_detail_stock_main', stockLabel(row.stock_main || 0));
                detailHtml('item_detail_koli_info', renderMainWarehouseKoli(row, 'start'));
                detailText('item_detail_safety_main', formatStockNumber(row.safety_main || 0));
                detailText('item_detail_stock_display', stockLabel(row.stock_display || 0));
                detailText('item_detail_stock_damaged', stockLabel(row.stock_damaged || 0));
                detailText('item_detail_stock_good_total', stockLabel(row.stock_good_total || 0));
                detailText('item_detail_stock_total', stockLabel(row.stock_total || 0));
            }

            const bundleSection = document.getElementById('item_detail_bundle_section');
            const bundleRows = document.getElementById('item_detail_bundle_components');
            if (row.item_type === 'bundle' && bundleSection && bundleRows) {
                bundleSection.style.display = '';
                bundleRows.innerHTML = (row.bundle_components || []).map((component) => `
                    <tr>
                        <td>${escapeHtml(component.component_sku || '-')}</td>
                        <td>${escapeHtml(component.component_name || '-')}</td>
                        <td class="text-end">${formatStockNumber(component.required_qty || 0)}</td>
                    </tr>
                `).join('') || '<tr><td colspan="3" class="text-muted">Komponen belum diset.</td></tr>';
            } else if (bundleSection && bundleRows) {
                bundleSection.style.display = 'none';
                bundleRows.innerHTML = '';
            }

            itemDetailModal?.show();
        };

        const dt = tableEl.DataTable({
            processing: true,
            serverSide: true,
            dom: 'rtip',
            order: [[1, 'desc']],
            pageLength: Number(limitSelect?.value || 10),
            ajax: {
                url: dataUrl,
                dataSrc: 'data',
                data: function(params) {
                    params.q = searchInput?.value || '';
                    params.search_mode = searchModeSelect?.value || 'contains';
                    params.status = statusSelect?.value || 'active';
                    params.safety_filter = safetySelect?.value || 'all';
                }
            },
            columns: [
                { data: 'id', orderable: false, searchable: false, className: 'text-center', render: (data, type, row) => {
                    if (type !== 'display' || row.item_type === 'bundle') {
                        return '';
                    }

                    const checked = selectedItemIds.has(String(data)) ? 'checked' : '';
                    return `
                        <div class="form-check form-check-sm form-check-custom form-check-solid justify-content-center">
                            <input class="form-check-input item-stock-row-check" type="checkbox" value="${data}" ${checked} aria-label="Pilih item ${escapeHtml(row.sku || '')}" />
                        </div>
                    `;
                }},
                { data: 'id' },
                { data: 'sku' },
                { data: 'name' },
                { data: 'item_type', render: (data) => renderItemTypeBadge(data) },
                { data: 'stock_main', className: 'text-end', render: (data, type, row) => renderWarehouseStock(data, type, row, 'virtual_main', 'is_main_below_safety', { showKoli: true, warehouseId: defaultWarehouseId, warehouseLabel: @json($defaultWarehouseLabel ?? 'Gudang Besar'), stockKey: 'stock_main', mode: 'koli' }) },
                { data: 'safety_main', className: 'text-end', render: (data, type, row) => renderSafetyValue(data, row, 'monitor_main') },
                { data: 'stock_display', className: 'text-end', render: (data, type, row) => renderWarehouseStock(data, type, row, 'virtual_display', 'is_display_below_safety', { warehouseId: displayWarehouseId, warehouseLabel: @json($displayWarehouseLabel ?? 'Gudang Display'), stockKey: 'stock_display', mode: 'pcs' }) },
                { data: 'safety_display', className: 'text-end', render: (data, type, row) => renderSafetyValue(data, row, 'monitor_display') },
                { data: 'stock_damaged', className: 'text-end', render: (data, type, row) => row.item_type === 'bundle' ? '-' : (data ?? 0) },
                { data: 'stock_good_total', className: 'text-end', render: (data, type, row) => row.item_type === 'bundle' ? `<span class="fw-bold text-primary">${row.virtual_total ?? 0}</span><div class="text-muted fs-8">virtual total</div>` : (data ?? 0) },
                { data: 'stock_total', className: 'text-end', render: (data, type, row) => row.item_type === 'bundle' ? '-' : (data ?? 0) },
                { data: 'id', orderable:false, searchable:false, className: 'text-end', render: (data, type, row) => {
                    const safeSku = escapeHtml(row.sku || '');
                    const safeName = escapeHtml(row.name || '');
                    const detailItem = `<div class="menu-item px-3"><a href="#" class="menu-link px-3 btn-item-detail" data-id="${data}">Detail Item</a></div>`;
                    const mutItem = `<div class="menu-item px-3"><a href="${mutationIndexUrl}?item_id=${encodeURIComponent(data)}&warehouse_id=all" class="menu-link px-3">Mutasi</a></div>`;
                    const safetyItem = row.item_type === 'bundle' ? '' : `<div class="menu-item px-3"><a href="#" class="menu-link px-3 btn-safety" data-id="${data}" data-sku="${safeSku}" data-name="${safeName}" data-safety-main="${row.safety_main_raw ?? ''}" data-safety-display="${row.safety_display_raw ?? ''}" data-safety-base="${row.safety_base ?? 0}" data-monitor-main="${row.monitor_main ? '1' : '0'}" data-monitor-display="${row.monitor_display ? '1' : '0'}">Set Safety & Monitoring</a></div>`;
                    const actions = `${detailItem}${mutItem}${safetyItem}`;
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
                }},
            ]
        });
        const currentPagePhysicalIds = () => dt.rows({ page: 'current' }).data().toArray()
            .filter((row) => row.item_type !== 'bundle')
            .map((row) => String(row.id));
        const syncBulkSelectionUi = () => {
            const count = selectedItemIds.size;
            if (bulkSafetyMainBtn) {
                bulkSafetyMainBtn.disabled = count === 0;
                bulkSafetyMainBtn.textContent = count > 0 ? `Ubah Safety Gudang Besar (${count})` : 'Ubah Safety Gudang Besar';
            }
            if (bulkSafetyDisplayBtn) {
                bulkSafetyDisplayBtn.disabled = count === 0;
                bulkSafetyDisplayBtn.textContent = count > 0 ? `Ubah Safety Display (${count})` : 'Ubah Safety Display';
            }

            if (selectAllCheckbox) {
                const ids = currentPagePhysicalIds();
                const checkedCount = ids.filter((id) => selectedItemIds.has(id)).length;
                selectAllCheckbox.checked = ids.length > 0 && checkedCount === ids.length;
                selectAllCheckbox.indeterminate = checkedCount > 0 && checkedCount < ids.length;
                selectAllCheckbox.disabled = ids.length === 0;
            }

            tableEl.find('.item-stock-row-check').each(function () {
                this.checked = selectedItemIds.has(String(this.value));
            });
        };
        const refreshMenus = () => {
            if (window.KTMenu) KTMenu.createInstances();
            syncBulkSelectionUi();
        };
        refreshMenus();
        dt.on('draw', refreshMenus);

        const reloadTable = () => dt.ajax.reload();
        searchInput?.addEventListener('input', reloadTable);
        searchModeSelect?.addEventListener('change', reloadTable);
        statusSelect?.addEventListener('change', reloadTable);
        safetySelect?.addEventListener('change', reloadTable);
        limitSelect?.addEventListener('change', () => {
            const val = Number(limitSelect.value || 10);
            dt.page.len(val).draw();
        });
        exportBtn?.addEventListener('click', () => {
            const q = searchInput?.value?.trim() || '';
            const mode = searchModeSelect?.value || 'contains';
            const params = new URLSearchParams();
            if (q) params.set('q', q);
            params.set('search_mode', mode);
            params.set('status', statusSelect?.value || 'active');
            params.set('safety_filter', safetySelect?.value || 'all');
            const query = params.toString();
            window.location.href = query ? `${exportUrl}?${query}` : exportUrl;
        });

        tableEl.on('click', '.btn-item-detail', function(e) {
            e.preventDefault();
            showItemDetail(findTableRow(this));
        });

        tableEl.on('click', '.btn-edit-stock', function(e) {
            e.preventDefault();
            openEditStockModal(this);
        });

        tableEl.on('click', '.btn-safety', function(e) {
            e.preventDefault();
            safetyBulkMode = false;
            safetyBulkTarget = null;
            const id = this.getAttribute('data-id');
            const sku = this.getAttribute('data-sku') || '';
            const name = this.getAttribute('data-name') || '';
            const mainRaw = this.getAttribute('data-safety-main');
            const displayRaw = this.getAttribute('data-safety-display');
            const base = this.getAttribute('data-safety-base') || 0;
            const monitorMainValue = this.getAttribute('data-monitor-main') !== '0';
            const monitorDisplayValue = this.getAttribute('data-monitor-display') !== '0';

            if (safetyModalTitle) safetyModalTitle.textContent = 'Safety Stock per Gudang';
            if (safetyMainWrap) safetyMainWrap.style.display = '';
            if (safetyDisplayWrap) safetyDisplayWrap.style.display = '';
            if (safetyMainHint) safetyMainHint.textContent = 'Kosongkan untuk gunakan safety default item.';
            if (safetyDisplayHint) safetyDisplayHint.textContent = 'Kosongkan untuk gunakan safety default item.';
            if (safetyItemId) safetyItemId.value = id || '';
            if (safetyItemLabel) safetyItemLabel.textContent = `${sku} - ${name}`.trim();
            if (safetyMain) safetyMain.value = mainRaw !== null && mainRaw !== '' ? mainRaw : '';
            if (safetyDisplay) safetyDisplay.value = displayRaw !== null && displayRaw !== '' ? displayRaw : '';
            if (safetyMain) safetyMain.placeholder = `Default: ${base}`;
            if (safetyDisplay) safetyDisplay.placeholder = `Default: ${base}`;
            if (monitorMain) monitorMain.checked = monitorMainValue;
            if (monitorDisplay) monitorDisplay.checked = monitorDisplayValue;
            safetyModal?.show();
        });

        tableEl.on('change', '.item-stock-row-check', function() {
            const id = String(this.value || '');
            if (!id) return;
            if (this.checked) {
                selectedItemIds.add(id);
            } else {
                selectedItemIds.delete(id);
            }
            syncBulkSelectionUi();
        });

        selectAllCheckbox?.addEventListener('change', function() {
            const ids = currentPagePhysicalIds();
            ids.forEach((id) => {
                if (this.checked) {
                    selectedItemIds.add(id);
                } else {
                    selectedItemIds.delete(id);
                }
            });
            syncBulkSelectionUi();
        });

        const openBulkSafetyModal = (target) => {
            if (selectedItemIds.size === 0) return;
            safetyBulkMode = true;
            safetyBulkTarget = target;
            const isMainTarget = target === 'main';
            const title = isMainTarget ? 'Ubah Safety Gudang Besar' : 'Ubah Safety Gudang Display';
            const placeholder = isMainTarget ? 'Isi nilai safety gudang besar' : 'Isi nilai safety display';
            if (safetyModalTitle) safetyModalTitle.textContent = title;
            if (safetyMainWrap) safetyMainWrap.style.display = isMainTarget ? '' : 'none';
            if (safetyDisplayWrap) safetyDisplayWrap.style.display = isMainTarget ? 'none' : '';
            if (safetyItemId) safetyItemId.value = '';
            if (safetyItemLabel) safetyItemLabel.textContent = `${selectedItemIds.size} item dipilih`;
            if (safetyMain) {
                safetyMain.value = '';
                safetyMain.placeholder = placeholder;
            }
            if (safetyDisplay) {
                safetyDisplay.value = '';
                safetyDisplay.placeholder = placeholder;
            }
            if (monitorMain) monitorMain.checked = true;
            if (monitorDisplay) monitorDisplay.checked = true;
            if (safetyMainHint) safetyMainHint.textContent = 'Nilai ini akan diterapkan ke semua item yang dicentang.';
            if (safetyDisplayHint) safetyDisplayHint.textContent = 'Nilai ini akan diterapkan ke semua item yang dicentang.';
            safetyModal?.show();
            setTimeout(() => (isMainTarget ? safetyMain : safetyDisplay)?.focus(), 150);
        };

        bulkSafetyMainBtn?.addEventListener('click', () => openBulkSafetyModal('main'));
        bulkSafetyDisplayBtn?.addEventListener('click', () => openBulkSafetyModal('display'));

        // ── MODAL MUTASI ─────────────────────────────────────────────
        document.getElementById('edit_stock_target_pcs')?.addEventListener('input', syncEditStockPreview);
        document.getElementById('edit_stock_target_koli')?.addEventListener('input', syncEditStockPreview);

        editStockForm?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const payload = calculateEditStockPayload();
            if (payload.error) {
                if (typeof Swal !== 'undefined') Swal.fire('Error', payload.error, 'error');
                return;
            }

            if (typeof Swal !== 'undefined') {
                const sign = payload.direction === 'in' ? '+' : '-';
                const koliText = payload.koli ? ` (${payload.koli} koli)` : '';
                const confirmation = await Swal.fire({
                    title: 'Simpan penyesuaian stok?',
                    text: `Stok akan disesuaikan ${sign}${formatStockNumber(payload.qty)} pcs${koliText} dan langsung disetujui otomatis.`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, simpan',
                    cancelButtonText: 'Batal',
                    buttonsStyling: false,
                    customClass: {
                        confirmButton: 'btn btn-primary',
                        cancelButton: 'btn btn-light',
                    },
                });

                if (!confirmation.isConfirmed) {
                    return;
                }
            } else if (!window.confirm('Simpan penyesuaian stok dan approve otomatis?')) {
                return;
            }

            const formData = new FormData();
            formData.append('auto_approve', '1');
            formData.append('warehouse_id', payload.warehouseId);
            formData.append('transacted_at', getJakartaNow());
            formData.append('note', payload.note || 'Edit stok dari halaman Item Stocks.');
            formData.append('items[0][item_id]', payload.itemId);
            formData.append('items[0][direction]', payload.direction);
            formData.append('items[0][qty]', payload.qty);
            if (payload.koli) {
                formData.append('items[0][koli]', payload.koli);
            }
            formData.append('items[0][note]', `Set stok akhir ${formatStockNumber(payload.targetStock)} pcs`);

            try {
                const res = await fetch(stockAdjustmentStoreUrl, {
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
                    const firstError = json?.errors ? Object.values(json.errors).flat()[0] : null;
                    if (typeof Swal !== 'undefined') Swal.fire('Error', firstError || json.message || 'Gagal menyimpan penyesuaian stok', 'error');
                    return;
                }
                if (typeof Swal !== 'undefined') Swal.fire('Berhasil', json.message || 'Penyesuaian stok berhasil disetujui otomatis.', 'success');
                editStockModal?.hide();
                reloadTable();
            } catch (err) {
                if (typeof Swal !== 'undefined') Swal.fire('Error', 'Gagal menyimpan penyesuaian stok', 'error');
            }
        });

        const mutationsModalEl = null;
        const mutationsModal = null;
        const mutWarehouse = null;
        const mutDateFrom = null;
        const mutDateTo = null;
        const mutFpFrom = null;
        const mutFpTo = null;
        let mutDt = null;
        let currentItemId = null;
        const initMutDt = () => {};

        tableEl.on('click', '.btn-mutations-disabled', function (e) {
            e.preventDefault();
            const id   = this.getAttribute('data-id');
            const sku  = this.getAttribute('data-sku') || '';
            const name = this.getAttribute('data-name') || '';
            currentItemId = id;
            const label = document.getElementById('mutations_item_label');
            if (label) label.textContent = [sku, name].filter(Boolean).join(' – ');
            if (mutWarehouse) mutWarehouse.value = 'all';
            if (mutFpFrom) mutFpFrom.clear(); else if (mutDateFrom) mutDateFrom.value = '';
            if (mutFpTo)   mutFpTo.clear();   else if (mutDateTo)   mutDateTo.value   = '';
            initMutDt();
            mutationsModal?.show();
        });

        mutationsModalEl?.addEventListener('hidden.bs.modal', () => {
            if (mutDt) { mutDt.destroy(); mutDt = null; }
            currentItemId = null;
        });

        document.getElementById('mut_filter_apply')?.addEventListener('click', () => mutDt?.ajax.reload());
        document.getElementById('mut_filter_reset')?.addEventListener('click', () => {
            if (mutWarehouse) mutWarehouse.value = 'all';
            if (mutFpFrom) mutFpFrom.clear(); else if (mutDateFrom) mutDateFrom.value = '';
            if (mutFpTo)   mutFpTo.clear();   else if (mutDateTo)   mutDateTo.value   = '';
            mutDt?.ajax.reload();
        });

        // ─────────────────────────────────────────────────────────────

        safetyForm?.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (!updateSafetyUrl) return;
            const formData = new FormData();
            if (safetyBulkMode) {
                selectedItemIds.forEach((id) => formData.append('item_ids[]', id));
                if (safetyBulkTarget === 'main') {
                    formData.append('safety_main', safetyMain?.value ?? '');
                    formData.append('monitor_main', monitorMain?.checked ? '1' : '0');
                } else {
                    formData.append('safety_display', safetyDisplay?.value ?? '');
                    formData.append('monitor_display', monitorDisplay?.checked ? '1' : '0');
                }
            } else {
                formData.append('item_id', safetyItemId?.value ?? '');
                formData.append('safety_main', safetyMain?.value ?? '');
                formData.append('safety_display', safetyDisplay?.value ?? '');
                formData.append('monitor_main', monitorMain?.checked ? '1' : '0');
                formData.append('monitor_display', monitorDisplay?.checked ? '1' : '0');
            }
            try {
                const res = await fetch(updateSafetyUrl, {
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
                    const msg = json?.message || 'Gagal menyimpan';
                    if (typeof Swal !== 'undefined') Swal.fire('Error', msg, 'error');
                    return;
                }
                if (typeof Swal !== 'undefined') Swal.fire('Berhasil', json.message || 'Berhasil', 'success');
                if (safetyBulkMode) {
                    selectedItemIds.clear();
                    syncBulkSelectionUi();
                }
                safetyModal?.hide();
                reloadTable();
            } catch (err) {
                if (typeof Swal !== 'undefined') Swal.fire('Error', 'Gagal menyimpan', 'error');
            }
        });
    });
</script>
@endpush
