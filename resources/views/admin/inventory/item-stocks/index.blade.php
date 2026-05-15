@extends('layouts.admin')

@section('title', 'Item Stocks')
@section('page_title', 'Item Stocks')

@php
    use App\Support\Permission as Perm;
    $canCreateStockAdjustment = Perm::can(auth()->user(), 'admin.inventory.stock-adjustments.index', 'create');
@endphp

@section('content')
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div class="d-flex align-items-center position-relative my-1">
                <span class="svg-icon svg-icon-1 position-absolute ms-6">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <rect opacity="0.5" x="17.0365" y="15.1223" width="8.15546" height="2" rx="1" transform="rotate(45 17.0365 15.1223)" fill="black" />
                        <path d="M11 19C6.55556 19 3 15.4444 3 11C3 6.55556 6.55556 3 11 3C15.4444 3 19 6.55556 19 11C19 15.4444 15.4444 19 11 19ZM11 5C7.53333 5 5 7.53333 5 11C5 14.4667 7.53333 17 11 17C14.4667 17 17 14.4667 17 11C17 7.53333 14.4667 5 11 5Z" fill="black" />
                    </svg>
                </span>
                <input type="text" class="form-control form-control-solid w-250px ps-14" placeholder="Search items" data-kt-filter="search" />
            </div>
        </div>
        <div class="card-toolbar">
            <button type="button" class="btn btn-light-primary" id="btn_export_item_stocks">Export Excel</button>
        </div>
    </div>
    <div class="card-body py-6">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5" id="item_stocks_table">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
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
                <h2 class="fw-bolder">Safety Stock per Gudang</h2>
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
                    <div class="fv-row mb-6">
                        <label class="fs-6 fw-bold form-label mb-2">Safety {{ $defaultWarehouseLabel ?? 'Gudang Besar' }}</label>
                        <input type="number" min="0" class="form-control form-control-solid" name="safety_main" id="safety_main" />
                        <div class="form-text text-muted">Kosongkan untuk gunakan safety default item.</div>
                    </div>
                    <div class="fv-row mb-6">
                        <label class="fs-6 fw-bold form-label mb-2">Safety {{ $displayWarehouseLabel ?? 'Gudang Display' }}</label>
                        <input type="number" min="0" class="form-control form-control-solid" name="safety_display" id="safety_display" />
                        <div class="form-text text-muted">Kosongkan untuk gunakan safety default item.</div>
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
{{-- Modal: Mutasi Barang --}}
<div class="modal fade" id="modal_item_mutations" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 class="fw-bolder">Mutasi Barang</h2>
                    <div class="text-muted fs-7" id="mutations_item_label">-</div>
                </div>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <span class="svg-icon svg-icon-1">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black"/>
                            <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black"/>
                        </svg>
                    </span>
                </div>
            </div>
            <div class="modal-body py-6">
                <div class="d-flex align-items-center gap-2 mb-6 flex-wrap">
                    <select class="form-select form-select-solid w-200px" id="mut_filter_warehouse">
                        <option value="all">Semua Gudang</option>
                        @foreach($warehouses ?? [] as $wh)
                            <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                        @endforeach
                    </select>
                    <input type="text" class="form-control form-control-solid w-150px" id="mut_date_from" placeholder="Dari" />
                    <input type="text" class="form-control form-control-solid w-150px" id="mut_date_to" placeholder="Sampai" />
                    <button type="button" class="btn btn-light" id="mut_filter_apply">Filter</button>
                    <button type="button" class="btn btn-light" id="mut_filter_reset">Reset</button>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-5" id="modal_mutations_table">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                <th>Tanggal</th>
                                <th>Gudang</th>
                                <th>Arah</th>
                                <th>Qty</th>
                                <th>Sumber</th>
                                <th>Kode</th>
                                <th>Catatan</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal: Detail Mutasi --}}
<div class="modal fade" id="modal_mutation_detail" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-900px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bolder">Detail Mutasi Stok</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <span class="svg-icon svg-icon-1">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black"/>
                            <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black"/>
                        </svg>
                    </span>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <div class="row mb-6">
                    <div class="col-md-3"><div class="fw-bold text-gray-600">ID</div><div id="md_id">-</div></div>
                    <div class="col-md-3"><div class="fw-bold text-gray-600">Tanggal</div><div id="md_date">-</div></div>
                    <div class="col-md-3"><div class="fw-bold text-gray-600">User</div><div id="md_user">-</div></div>
                    <div class="col-md-3"><div class="fw-bold text-gray-600">Gudang</div><div id="md_warehouse">-</div></div>
                </div>
                <div class="row mb-6">
                    <div class="col-md-6"><div class="fw-bold text-gray-600">Item</div><div id="md_item">-</div></div>
                    <div class="col-md-2"><div class="fw-bold text-gray-600">Arah</div><div id="md_direction">-</div></div>
                    <div class="col-md-2"><div class="fw-bold text-gray-600">Qty</div><div id="md_qty">-</div></div>
                    <div class="col-md-2"><div class="fw-bold text-gray-600">Sumber</div><div id="md_source">-</div></div>
                </div>
                <div class="row mb-6">
                    <div class="col-md-4"><div class="fw-bold text-gray-600">Kode Sumber</div><div id="md_source_code">-</div></div>
                    <div class="col-md-8"><div class="fw-bold text-gray-600">Catatan</div><div id="md_note">-</div></div>
                </div>
                <hr class="my-6" />
                <div class="fw-bolder fs-5 mb-4">Sumber Data</div>
                <div id="md_source_empty" class="text-muted">Data sumber tidak ditemukan.</div>
                <div id="md_source_section" style="display:none;">
                    <div class="row mb-6">
                        <div class="col-md-4"><div class="fw-bold text-gray-600">Jenis</div><div id="md_src_label">-</div></div>
                        <div class="col-md-4"><div class="fw-bold text-gray-600">Kode</div><div id="md_src_code">-</div></div>
                        <div class="col-md-4"><div class="fw-bold text-gray-600">Ref</div><div id="md_src_ref">-</div></div>
                    </div>
                    <div class="row mb-6">
                        <div class="col-md-4"><div class="fw-bold text-gray-600">Tanggal</div><div id="md_src_date">-</div></div>
                        <div class="col-md-8"><div class="fw-bold text-gray-600">Catatan</div><div id="md_src_note">-</div></div>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle table-row-dashed fs-6 gy-5">
                            <thead>
                                <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                    <th>Item</th><th>Qty</th><th>Catatan</th>
                                </tr>
                            </thead>
                            <tbody id="md_src_items"></tbody>
                        </table>
                    </div>
                </div>
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
    const mutationsDataUrl = '{{ route('admin.inventory.stock-mutations.data') }}';
    const mutationDetailUrlTpl = '{{ route('admin.inventory.stock-mutations.show', ':id') }}';
    const defaultWarehouseId = {{ !empty($defaultWarehouseId) ? (int) $defaultWarehouseId : 'null' }};
    const displayWarehouseId = {{ !empty($displayWarehouseId) ? (int) $displayWarehouseId : 'null' }};
    const damagedWarehouseId = {{ !empty($damagedWarehouseId) ? (int) $damagedWarehouseId : 'null' }};
    const csrfToken = '{{ csrf_token() }}';

    document.addEventListener('DOMContentLoaded', () => {
        const tableEl = $('#item_stocks_table');
        const searchInput = document.querySelector('[data-kt-filter="search"]');
        const exportBtn = document.getElementById('btn_export_item_stocks');
        const safetyModalEl = document.getElementById('modal_safety_stock');
        const safetyModal = safetyModalEl ? new bootstrap.Modal(safetyModalEl) : null;
        const safetyForm = document.getElementById('safety_stock_form');
        const safetyItemId = document.getElementById('safety_item_id');
        const safetyItemLabel = document.getElementById('safety_item_label');
        const safetyMain = document.getElementById('safety_main');
        const safetyDisplay = document.getElementById('safety_display');
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
            order: [[0, 'desc']],
            ajax: {
                url: dataUrl,
                dataSrc: 'data',
                data: function(params) {
                    params.q = searchInput?.value || '';
                }
            },
            columns: [
                { data: 'id' },
                { data: 'sku' },
                { data: 'name' },
                { data: 'item_type', render: (data) => renderItemTypeBadge(data) },
                { data: 'stock_main', className: 'text-end', render: (data, type, row) => renderWarehouseStock(data, type, row, 'virtual_main', 'is_main_below_safety', { showKoli: true, warehouseId: defaultWarehouseId, warehouseLabel: @json($defaultWarehouseLabel ?? 'Gudang Besar'), stockKey: 'stock_main', mode: 'koli' }) },
                { data: 'safety_main', className: 'text-end', render: (data, type, row) => row.item_type === 'bundle' ? '-' : (data ?? 0) },
                { data: 'stock_display', className: 'text-end', render: (data, type, row) => renderWarehouseStock(data, type, row, 'virtual_display', 'is_display_below_safety', { warehouseId: displayWarehouseId, warehouseLabel: @json($displayWarehouseLabel ?? 'Gudang Display'), stockKey: 'stock_display', mode: 'pcs' }) },
                { data: 'safety_display', className: 'text-end', render: (data, type, row) => row.item_type === 'bundle' ? '-' : (data ?? 0) },
                { data: 'stock_damaged', className: 'text-end', render: (data, type, row) => row.item_type === 'bundle' ? '-' : (data ?? 0) },
                { data: 'stock_good_total', className: 'text-end', render: (data, type, row) => row.item_type === 'bundle' ? `<span class="fw-bold text-primary">${row.virtual_total ?? 0}</span><div class="text-muted fs-8">virtual total</div>` : (data ?? 0) },
                { data: 'stock_total', className: 'text-end', render: (data, type, row) => row.item_type === 'bundle' ? '-' : (data ?? 0) },
                { data: 'id', orderable:false, searchable:false, className: 'text-end', render: (data, type, row) => {
                    const safeSku = escapeHtml(row.sku || '');
                    const safeName = escapeHtml(row.name || '');
                    const detailItem = `<div class="menu-item px-3"><a href="#" class="menu-link px-3 btn-item-detail" data-id="${data}">Detail Item</a></div>`;
                    const mutItem = `<div class="menu-item px-3"><a href="#" class="menu-link px-3 btn-mutations" data-id="${data}" data-sku="${safeSku}" data-name="${safeName}">Mutasi</a></div>`;
                    const safetyItem = row.item_type === 'bundle' ? '' : `<div class="menu-item px-3"><a href="#" class="menu-link px-3 btn-safety" data-id="${data}" data-sku="${safeSku}" data-name="${safeName}" data-safety-main="${row.safety_main_raw ?? ''}" data-safety-display="${row.safety_display_raw ?? ''}" data-safety-base="${row.safety_base ?? 0}">Set Safety</a></div>`;
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
        const refreshMenus = () => { if (window.KTMenu) KTMenu.createInstances(); };
        refreshMenus();
        dt.on('draw', refreshMenus);

        const reloadTable = () => dt.ajax.reload();
        searchInput?.addEventListener('keyup', reloadTable);
        exportBtn?.addEventListener('click', () => {
            const q = searchInput?.value?.trim() || '';
            const url = q ? `${exportUrl}?q=${encodeURIComponent(q)}` : exportUrl;
            window.location.href = url;
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
            const id = this.getAttribute('data-id');
            const sku = this.getAttribute('data-sku') || '';
            const name = this.getAttribute('data-name') || '';
            const mainRaw = this.getAttribute('data-safety-main');
            const displayRaw = this.getAttribute('data-safety-display');
            const base = this.getAttribute('data-safety-base') || 0;

            if (safetyItemId) safetyItemId.value = id || '';
            if (safetyItemLabel) safetyItemLabel.textContent = `${sku} - ${name}`.trim();
            if (safetyMain) safetyMain.value = mainRaw !== null && mainRaw !== '' ? mainRaw : '';
            if (safetyDisplay) safetyDisplay.value = displayRaw !== null && displayRaw !== '' ? displayRaw : '';
            if (safetyMain) safetyMain.placeholder = `Default: ${base}`;
            if (safetyDisplay) safetyDisplay.placeholder = `Default: ${base}`;
            safetyModal?.show();
        });

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

        const mutationsModalEl = document.getElementById('modal_item_mutations');
        const mutationsModal = mutationsModalEl ? new bootstrap.Modal(mutationsModalEl) : null;
        const detailModalEl = document.getElementById('modal_mutation_detail');
        const detailModal = detailModalEl ? new bootstrap.Modal(detailModalEl) : null;
        const mutDateFrom = document.getElementById('mut_date_from');
        const mutDateTo = document.getElementById('mut_date_to');
        const mutWarehouse = document.getElementById('mut_filter_warehouse');
        let mutDt = null;
        let mutFpFrom = null;
        let mutFpTo = null;
        let currentItemId = null;

        if (typeof flatpickr !== 'undefined') {
            if (mutDateFrom) mutFpFrom = flatpickr(mutDateFrom, { dateFormat: 'Y-m-d', allowInput: true });
            if (mutDateTo)   mutFpTo   = flatpickr(mutDateTo,   { dateFormat: 'Y-m-d', allowInput: true });
        }

        const warehouseBadgeClass = (wId) => {
            const id = Number(wId || 0);
            if (displayWarehouseId && id === Number(displayWarehouseId)) return 'badge-light-success';
            if (defaultWarehouseId && id === Number(defaultWarehouseId)) return 'badge-light-primary';
            if (damagedWarehouseId && id === Number(damagedWarehouseId)) return 'badge-light-danger';
            return 'badge-light-secondary';
        };
        const renderWhBadge = (label, wId) => `<span class="badge ${warehouseBadgeClass(wId)}">${label || '-'}</span>`;

        const initMutDt = () => {
            if (mutDt) { mutDt.destroy(); mutDt = null; }
            mutDt = $('#modal_mutations_table').DataTable({
                processing: true,
                serverSide: true,
                dom: 'rtip',
                order: [[0, 'desc']],
                ajax: {
                    url: mutationsDataUrl,
                    dataSrc: 'data',
                    data: (params) => {
                        params.item_id = currentItemId;
                        params.warehouse_id = mutWarehouse?.value || 'all';
                        if (mutDateFrom?.value) params.date_from = mutDateFrom.value;
                        if (mutDateTo?.value)   params.date_to   = mutDateTo.value;
                    }
                },
                columns: [
                    { data: 'occurred_at' },
                    { data: 'warehouse', render: (d, t, row) => renderWhBadge(d, row?.warehouse_id) },
                    { data: 'direction', render: (d) => d === 'IN'
                        ? '<span class="badge badge-light-success">IN</span>'
                        : '<span class="badge badge-light-danger">OUT</span>' },
                    { data: 'qty' },
                    { data: 'source' },
                    { data: 'source_code' },
                    { data: 'note' },
                    { data: 'id', orderable: false, searchable: false, className: 'text-end',
                        render: (d) => `<button type="button" class="btn btn-sm btn-light btn-mut-detail" data-id="${d}">Detail</button>` },
                ]
            });
        };

        tableEl.on('click', '.btn-mutations', function (e) {
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

        // Detail mutasi
        const setText = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val ?? '-'; };
        const setHtml = (id, val) => { const el = document.getElementById(id); if (el) el.innerHTML = val ?? '-'; };

        $(document).on('click', '.btn-mut-detail', async function (e) {
            e.preventDefault();
            const id = this.getAttribute('data-id');
            if (!id) return;
            try {
                const res  = await fetch(mutationDetailUrlTpl.replace(':id', id), { headers: { 'Accept': 'application/json' } });
                const json = await res.json();
                if (!res.ok) { if (typeof Swal !== 'undefined') Swal.fire('Error', json.message || 'Gagal memuat data', 'error'); return; }
                const m = json.mutation || {};
                setText('md_id', m.id);
                setText('md_date', m.occurred_at);
                setText('md_item', m.item);
                setHtml('md_warehouse', renderWhBadge(m.warehouse, m.warehouse_id));
                setText('md_direction', m.direction);
                setText('md_qty', m.qty);
                setText('md_source', m.source);
                setText('md_source_code', m.source_code);
                setText('md_note', m.note);
                setText('md_user', m.user);
                const src = json.source || null;
                const srcEmpty = document.getElementById('md_source_empty');
                const srcSection = document.getElementById('md_source_section');
                const srcItems = document.getElementById('md_src_items');
                if (src && srcSection && srcEmpty) {
                    srcSection.style.display = '';
                    srcEmpty.style.display = 'none';
                    setText('md_src_label', src.label);
                    setText('md_src_code', src.code);
                    setText('md_src_ref', src.ref);
                    setText('md_src_date', src.date);
                    setText('md_src_note', src.note);
                    if (srcItems) {
                        srcItems.innerHTML = (src.items || []).map(r => {
                            const meta = r.meta ? `<div class="text-muted fs-8">${r.meta}</div>` : '';
                            return `<tr><td>${r.label || '-'}${meta}</td><td>${r.qty ?? '-'}</td><td>${r.note ?? '-'}</td></tr>`;
                        }).join('') || '<tr><td colspan="3" class="text-muted">Tidak ada item.</td></tr>';
                    }
                } else if (srcEmpty && srcSection) {
                    srcSection.style.display = 'none';
                    srcEmpty.style.display = '';
                }
                detailModal?.show();
            } catch (err) {
                console.error(err);
                if (typeof Swal !== 'undefined') Swal.fire('Error', 'Gagal memuat data', 'error');
            }
        });
        // ─────────────────────────────────────────────────────────────

        safetyForm?.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (!updateSafetyUrl) return;
            const formData = new FormData(safetyForm);
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
                safetyModal?.hide();
                reloadTable();
            } catch (err) {
                if (typeof Swal !== 'undefined') Swal.fire('Error', 'Gagal menyimpan', 'error');
            }
        });
    });
</script>
@endpush
