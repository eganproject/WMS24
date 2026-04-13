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
                <input type="text" class="form-control form-control-solid w-250px ps-14" placeholder="Search" data-kt-filter="search" />
            </div>
        </div>
        <div class="card-toolbar">
            <div class="d-flex align-items-center gap-2 me-4">
                @if(!empty($warehouses ?? []))
                    <select class="form-select form-select-solid w-200px" id="filter_warehouse">
                        <option value="all">Semua Gudang</option>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                        @endforeach
                    </select>
                @endif
                <input type="text" class="form-control form-control-solid w-150px" id="filter_date_from" placeholder="Dari" />
                <input type="text" class="form-control form-control-solid w-150px" id="filter_date_to" placeholder="Sampai" />
                <button type="button" class="btn btn-light" id="filter_apply">Filter</button>
                <button type="button" class="btn btn-light" id="filter_reset">Reset</button>
            </div>
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
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5" id="stock_flow_table">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                        <th>ID</th>
                        <th>Kode</th>
                        <th>Jenis</th>
                        <th>Status</th>
                        <th>Tanggal</th>
                        <th>Submit By</th>
                        <th>Gudang</th>
                        <th>Item</th>
                        <th>Qty</th>
                        <th>Catatan</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modal_stock_flow" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-900px">
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
                    <div id="flow_items_container"></div>
                    <div class="mb-7">
                        <button type="button" class="btn btn-light" id="btn_add_flow_item">Tambah Item</button>
                    </div>
                    @if(!empty($warehouseOptions ?? []))
                        <div class="fv-row mb-7" id="flow_warehouse_row" style="display:none;">
                            <label class="fs-6 fw-bold form-label mb-2">Gudang</label>
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
                    @if(!empty($showDeliveryNoteFields ?? false))
                        <div class="fv-row mb-7">
                            <label class="fs-6 fw-bold form-label mb-2">No Surat Jalan</label>
                            <input type="text" class="form-control form-control-solid" name="surat_jalan_no" id="flow_surat_jalan_no" />
                            <div class="invalid-feedback" id="error_surat_jalan_no"></div>
                        </div>
                        <div class="fv-row mb-7">
                            <label class="fs-6 fw-bold form-label mb-2">Tanggal Surat Jalan</label>
                            <input type="text" class="form-control form-control-solid" name="surat_jalan_at" id="flow_surat_jalan_at" placeholder="YYYY-MM-DD" />
                            <div class="invalid-feedback" id="error_surat_jalan_at"></div>
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
                            @if(!empty($enableKoli ?? false))
                                Header minimal: <strong>sku</strong>, <strong>qty</strong> atau <strong>koli</strong>.<br>
                            @else
                                Header minimal: <strong>sku</strong>, <strong>qty</strong>.<br>
                            @endif
                            Opsional: <strong>ref_no</strong>
                            @if(!empty($showDeliveryNoteFields ?? false))
                                , <strong>surat_jalan_no</strong>, <strong>surat_jalan_at</strong>
                            @endif
                            , <strong>note</strong>, <strong>item_note</strong>, <strong>transacted_at</strong>
                            @if(!empty($enableWarehouseSelect ?? false))
                                , <strong>warehouse</strong>/<strong>gudang</strong> (isi kode/nama gudang)
                            @endif
                            .
                        </div>
                        @if(!empty($templateUrl ?? null))
                            <div class="mt-3">
                                @if(!empty($templateNote ?? null))
                                    <div class="text-muted fs-7 mb-2">{{ $templateNote }}</div>
                                @endif
                                <a href="{{ $templateUrl }}" class="btn btn-light-primary btn-sm">
                                    {{ $templateLabel ?? 'Download Template' }}
                                </a>
                            </div>
                        @endif
                    </div>
                    <div class="fv-row mb-6">
                        <label class="required fs-6 fw-bold form-label mb-2">File Excel</label>
                        <input type="file" class="form-control form-control-solid" id="import_flow_file" accept=".xlsx,.xls" />
                        <div class="invalid-feedback d-block" id="error_import_flow_file"></div>
                    </div>
                    <div class="text-end">
                        <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Batal</button>
                        <button type="button" class="btn btn-primary" id="btn_import_flow_submit">Import</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
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
    const routeMap = @json($routeMap ?? []);
    const typeLabelMap = @json($typeOptions ?? []);
    const csrfToken = '{{ csrf_token() }}';
    const itemOptionsHtml = `@foreach($items as $item)<option value="{{ $item->id }}" data-koli-qty="{{ (int) ($item->koli_qty ?? 0) }}">{{ $item->sku }} - {{ $item->name }}</option>@endforeach`;
    const defaultTypeFilter = '{{ $typeDefault ?? '' }}';
    const permMap = @json($permMap ?? []);
    const canCreateDefault = {{ $canCreateDefault ? 'true' : 'false' }};
    const enableKoli = {{ !empty($enableKoli ?? false) ? 'true' : 'false' }};
    const enableWarehouseSelect = {{ !empty($enableWarehouseSelect ?? false) ? 'true' : 'false' }};
    const displayWarehouseId = {{ isset($displayWarehouseId) ? (int) $displayWarehouseId : 'null' }};
    const defaultWarehouseId = {{ isset($defaultWarehouseId) ? (int) $defaultWarehouseId : 'null' }};
    const statusLabels = @json($statusLabels ?? []);
    const lockedStatuses = @json($lockedStatuses ?? ['approved']);
    const showApproveAction = {{ isset($showApproveAction) ? ($showApproveAction ? 'true' : 'false') : 'true' }};
    const deleteWarningText = @json($deleteWarningText ?? 'Data akan dihapus dan stok akan dikembalikan');

    document.addEventListener('DOMContentLoaded', () => {
        const tableEl = $('#stock_flow_table');
        const searchInput = document.querySelector('[data-kt-filter="search"]');
        const form = document.getElementById('stock_flow_form');
        const modalEl = document.getElementById('modal_stock_flow');
        const modal = modalEl ? new bootstrap.Modal(modalEl) : null;
        const itemsContainer = document.getElementById('flow_items_container');
        const addItemBtn = document.getElementById('btn_add_flow_item');
        const openCreateBtn = document.getElementById('btn_open_create_flow');
        const modalTitle = document.getElementById('flow_modal_title');
        const dateFromEl = document.getElementById('filter_date_from');
        const dateToEl = document.getElementById('filter_date_to');
        const transactedAtEl = document.getElementById('flow_transacted_at');
        const warehouseFilter = document.getElementById('filter_warehouse');
        const filterApplyBtn = document.getElementById('filter_apply');
        const filterResetBtn = document.getElementById('filter_reset');
        const importBtn = document.getElementById('btn_import_flow');
        const importModalEl = document.getElementById('modal_import_flow');
        const importModal = importModalEl ? new bootstrap.Modal(importModalEl) : null;
        const importInput = document.getElementById('import_flow_file');
        const importError = document.getElementById('error_import_flow_file');
        const importSubmit = document.getElementById('btn_import_flow_submit');
        const warehouseRow = document.getElementById('flow_warehouse_row');
        const warehouseSelect = document.getElementById('flow_warehouse_id');
        const suratJalanNoEl = document.getElementById('flow_surat_jalan_no');
        const suratJalanAtEl = document.getElementById('flow_surat_jalan_at');
        let fpFrom = null;
        let fpTo = null;
        let fpTransacted = null;
        let fpSuratJalan = null;

        const formatDateTime = (date) => {
            const pad = (n) => String(n).padStart(2, '0');
            return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`;
        };

        const getJakartaNow = () => {
            const jkt = new Date(new Date().toLocaleString('en-US', { timeZone: 'Asia/Jakarta' }));
            return formatDateTime(jkt);
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
                    : (status === 'scanning' ? 'badge-light-primary' : 'badge-light-warning');
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
            return `<span class="badge ${warehouseBadgeClass(warehouseId)}">${text}</span>`;
        };

        const clearErrors = () => {
            ['error_transacted_at','error_ref_no','error_surat_jalan_no','error_surat_jalan_at','error_note','error_warehouse_id'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.textContent = '';
            });
            itemsContainer?.querySelectorAll('[data-error-for]')?.forEach(el => { el.textContent = ''; });
            itemsContainer?.querySelectorAll('.flow-item-select.is-invalid')?.forEach(el => { el.classList.remove('is-invalid'); });
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

        const updateKoliInfo = (row) => {
            if (!enableKoli || !row) return;
            const infoEl = row.querySelector('[data-koli-info]');
            if (!infoEl) return;
            const selectEl = row.querySelector('.flow-item-select');
            const koliQty = getSelectedKoliQty(selectEl);
            infoEl.textContent = koliQty > 0 ? `Isi/Koli: ${koliQty} pcs` : 'Isi/Koli: belum diset';
        };

        const syncQtyFromKoli = (row) => {
            if (!enableKoli || !row) return;
            const koliEl = row.querySelector('input[data-name="koli"]');
            const qtyEl = row.querySelector('input[data-name="qty"]');
            const selectEl = row.querySelector('.flow-item-select');
            if (!koliEl || !qtyEl || !selectEl) return;
            const koliVal = parseInt(koliEl.value || '', 10);
            if (!koliVal || koliVal <= 0) return;
            const koliQty = getSelectedKoliQty(selectEl);
            if (koliQty <= 0) return;
            qtyEl.value = String(koliVal * koliQty);
        };

        const initSelect2 = (selectEl) => {
            if (selectEl && typeof $ !== 'undefined' && $.fn.select2) {
                $(selectEl).select2({
                    placeholder: 'Pilih item',
                    allowClear: true,
                    width: '100%',
                    dropdownParent: modalEl,
                    minimumResultsForSearch: 0,
                })
                    .on('select2:opening select2:closing select2:close', function(e){ e.stopPropagation(); });
            }
        };

        const applyWarehouseVisibility = (flowType) => {
            if (!warehouseRow || !warehouseSelect) return;
            const shouldShow = enableWarehouseSelect && flowType === 'manual';
            warehouseRow.style.display = shouldShow ? '' : 'none';
            warehouseSelect.required = shouldShow;
            if (shouldShow) {
                if (!warehouseSelect.value) {
                    const fallbackId = displayWarehouseId || defaultWarehouseId || '';
                    if (fallbackId) warehouseSelect.value = String(fallbackId);
                }
            } else {
                warehouseSelect.value = '';
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
            const itemColSize = enableKoli ? 'col-md-5' : 'col-md-6';
            const noteColSize = enableKoli ? 'col-md-2' : 'col-md-3';
            const koliCol = enableKoli ? `
                <div class="col-md-2">
                    <label class="fs-6 fw-bold form-label mb-2">Koli</label>
                    <input type="number" min="1" class="form-control form-control-solid" data-name="koli" />
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
                ${koliCol}
                <div class="col-md-2">
                    <label class="required fs-6 fw-bold form-label mb-2">Qty</label>
                    <input type="number" min="1" class="form-control form-control-solid" data-name="qty" required />
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

            initSelect2(selectEl);
            updateKoliInfo(row);
            syncQtyFromKoli(row);
            renumberRows();
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
            if (fpSuratJalan) {
                fpSuratJalan.clear();
            } else if (suratJalanAtEl) {
                suratJalanAtEl.value = '';
            }
            itemsContainer.innerHTML = '';
            createItemRow();
            clearErrors();
            validateUniqueItems();
        };

        addItemBtn?.addEventListener('click', () => createItemRow());
        if (!canCreateDefault && openCreateBtn) {
            openCreateBtn.remove();
        } else {
            openCreateBtn?.addEventListener('click', resetForm);
        }

        itemsContainer?.addEventListener('change', (e) => {
            if (e.target.matches('.flow-item-select')) {
                validateUniqueItems();
                const row = e.target.closest('.flow-item-row');
                updateKoliInfo(row);
                syncQtyFromKoli(row);
            }
        });

        itemsContainer?.addEventListener('input', (e) => {
            if (!enableKoli) return;
            if (e.target.matches('input[data-name="koli"]')) {
                const row = e.target.closest('.flow-item-row');
                syncQtyFromKoli(row);
            }
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
                    if (warehouseFilter?.value) params.warehouse_id = warehouseFilter.value;
                    if (dateFromEl?.value) params.date_from = dateFromEl.value;
                    if (dateToEl?.value) params.date_to = dateToEl.value;
                }
            },
            columns: [
                { data: 'id' },
                { data: 'code' },
                { data: 'type', render: (data) => typeLabelMap?.[data] || data || '-' },
                { data: 'status', orderable:false, searchable:false, render: (data) => statusLabel(data) },
                { data: 'transacted_at' },
                { data: 'submit_by' },
                { data: 'warehouse', render: (data, type, row) => renderWarehouseBadge(data, row?.warehouse_id) },
                { data: 'item' },
                { data: 'qty' },
                { data: 'note' },
                { data: 'id', orderable:false, searchable:false, className:'text-end', render: (data, type, row)=>{
                    const rowType = row?.type || defaultTypeFilter;
                    const perms = permMap?.[rowType] || {};
                    const isLocked = Array.isArray(lockedStatuses)
                        ? lockedStatuses.includes(row?.status)
                        : row?.status === 'approved';
                    const detailItem = `<div class="menu-item px-3"><a href="${resolveRoute(rowType, 'detail').replace(':id', data)}" class="menu-link px-3">Detail</a></div>`;
                    const approveItem = (showApproveAction && !isLocked && perms.update)
                        ? `<div class="menu-item px-3"><a href="#" class="menu-link px-3 text-success btn-approve" data-id="${data}" data-type="${rowType}">Approve</a></div>`
                        : '';
                    const editItem = (!isLocked && perms.update)
                        ? `<div class="menu-item px-3"><a href="#" class="menu-link px-3 btn-edit" data-id="${data}" data-type="${rowType}">Edit</a></div>`
                        : '';
                    const delItem = (!isLocked && perms.delete)
                        ? `<div class="menu-item px-3"><a href="#" class="menu-link px-3 text-danger btn-delete" data-id="${data}" data-type="${rowType}">Hapus</a></div>`
                        : '';
                    const actions = `${detailItem}${approveItem}${editItem}${delItem}`;
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

        const reloadTable = () => dt.ajax.reload();
        searchInput?.addEventListener('keyup', reloadTable);
        warehouseFilter?.addEventListener('change', reloadTable);
        filterApplyBtn?.addEventListener('click', reloadTable);
        filterResetBtn?.addEventListener('click', () => {
            if (warehouseFilter) {
                warehouseFilter.value = 'all';
                if (typeof $ !== 'undefined' && $(warehouseFilter).data('select2')) {
                    $(warehouseFilter).val('all').trigger('change.select2');
                }
            }
            if (fpFrom) fpFrom.clear(); else if (dateFromEl) dateFromEl.value = '';
            if (fpTo) fpTo.clear(); else if (dateToEl) dateToEl.value = '';
            reloadTable();
        });

        importBtn?.addEventListener('click', () => {
            if (importInput) importInput.value = '';
            if (importError) importError.textContent = '';
        });

        importSubmit?.addEventListener('click', async () => {
            if (!importUrl) return;
            if (importError) importError.textContent = '';
            const file = importInput?.files?.[0];
            if (!file) {
                if (importError) importError.textContent = 'Pilih file Excel terlebih dahulu.';
                return;
            }
            const formData = new FormData();
            formData.append('file', file);
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
                    const msg = json?.errors?.file?.[0] || json?.message;
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
                if (suratJalanNoEl) suratJalanNoEl.value = json.surat_jalan_no || '';
                document.getElementById('flow_note').value = json.note || '';
                applyWarehouseVisibility(rowType);
                if (warehouseSelect) {
                    const fallbackId = displayWarehouseId || defaultWarehouseId || '';
                    warehouseSelect.value = json.warehouse_id ? String(json.warehouse_id) : (fallbackId ? String(fallbackId) : '');
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
                                if (errEl) errEl.textContent = msgs.join(', ');
                                else unhandled.push(msgs.join(', '));
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
