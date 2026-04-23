@extends('layouts.admin')

@section('title', 'Barang Rusak')
@section('page_title', 'Barang Rusak')

@php
    use App\Support\Permission as Perm;
    $canCreate = Perm::can(auth()->user(), 'admin.inventory.damaged-goods.index', 'create');
    $canUpdate = Perm::can(auth()->user(), 'admin.inventory.damaged-goods.index', 'update');
    $canDelete = Perm::can(auth()->user(), 'admin.inventory.damaged-goods.index', 'delete');
@endphp

@section('content')
<div class="row g-5 g-xl-8 mb-6" id="damage_aging_cards">
    <div class="col-6 col-md-3">
        <div class="card h-100 border border-gray-200">
            <div class="card-body d-flex flex-column justify-content-between">
                <div class="text-gray-500 fw-semibold fs-7 text-uppercase">0-7 Hari</div>
                <div class="mt-4">
                    <div class="fs-2hx fw-bolder text-gray-900" data-aging-qty="0_7">0</div>
                    <div class="fs-7 text-muted mt-1" data-aging-lines="0_7">0 line aktif</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card h-100 border border-gray-200">
            <div class="card-body d-flex flex-column justify-content-between">
                <div class="text-gray-500 fw-semibold fs-7 text-uppercase">8-30 Hari</div>
                <div class="mt-4">
                    <div class="fs-2hx fw-bolder text-warning" data-aging-qty="8_30">0</div>
                    <div class="fs-7 text-muted mt-1" data-aging-lines="8_30">0 line aktif</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card h-100 border border-gray-200">
            <div class="card-body d-flex flex-column justify-content-between">
                <div class="text-gray-500 fw-semibold fs-7 text-uppercase">31-60 Hari</div>
                <div class="mt-4">
                    <div class="fs-2hx fw-bolder text-primary" data-aging-qty="31_60">0</div>
                    <div class="fs-7 text-muted mt-1" data-aging-lines="31_60">0 line aktif</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card h-100 border border-gray-200">
            <div class="card-body d-flex flex-column justify-content-between">
                <div class="text-gray-500 fw-semibold fs-7 text-uppercase">&gt;60 Hari</div>
                <div class="mt-4">
                    <div class="fs-2hx fw-bolder text-danger" data-aging-qty="61_plus">0</div>
                    <div class="fs-7 text-muted mt-1" data-aging-lines="61_plus">0 line aktif</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-6">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div>
                <div class="fs-4 fw-bolder text-gray-900">Saldo Rusak per SKU</div>
                <div class="fs-7 text-muted" id="damage_summary_overview">Gabungan semua intake barang rusak approved yang masih memiliki sisa untuk dialokasikan.</div>
            </div>
        </div>
    </div>
    <div class="card-body py-6">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5" id="damaged_goods_summary_table">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                        <th>SKU</th>
                        <th>Nama Item</th>
                        <th class="text-end">Dokumen Aktif</th>
                        <th>Alasan Rusak</th>
                        <th class="text-end">Qty Intake</th>
                        <th class="text-end">Dialokasikan</th>
                        <th class="text-end">Sisa</th>
                        <th>Aging Tertua</th>
                        <th>Intake Tertua</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header border-0 pt-6 table-search-card-header">
        <div class="card-title">
            <div class="d-flex align-items-center position-relative my-1">
                <span class="svg-icon svg-icon-1 position-absolute ms-6">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <rect opacity="0.5" x="17.0365" y="15.1223" width="8.15546" height="2" rx="1" transform="rotate(45 17.0365 15.1223)" fill="black" />
                        <path d="M11 19C6.55556 19 3 15.4444 3 11C3 6.55556 6.55556 3 11 3C15.4444 3 19 6.55556 19 11C19 15.4444 15.4444 19 11 19ZM11 5C7.53333 5 5 7.53333 5 11C5 14.4667 7.53333 17 11 17C14.4667 17 17 14.4667 17 11C17 7.53333 14.4667 5 11 5Z" fill="black" />
                    </svg>
                </span>
                <input type="text" class="form-control form-control-solid w-250px ps-14" placeholder="Cari kode intake, SKU, nama item, gudang, atau alasan rusak" data-kt-filter="search" />
            </div>
        </div>
        <div class="card-toolbar">
            <div class="d-flex flex-wrap align-items-center gap-3 justify-content-md-end">
                <div class="min-w-200px">
                    <select class="form-select form-select-solid" id="damage_reason_filter" data-control="select2" data-hide-search="true" data-placeholder="Semua alasan rusak">
                        <option value="">Semua alasan rusak</option>
                        @foreach(($reasonOptions ?? []) as $code => $label)
                            <option value="{{ $code }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                @if(!empty($damagedWarehouseLabel ?? null))
                    <span class="badge badge-light-danger">Gudang Rusak: {{ $damagedWarehouseLabel }}</span>
                @endif
                @if($canCreate)
                    <button type="button" class="btn btn-primary" id="btn_open_damage" data-bs-toggle="modal" data-bs-target="#modal_damaged_goods">Tambah</button>
                @endif
            </div>
        </div>
    </div>
    <div class="card-body py-6">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5" id="damaged_goods_table">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                        <th>ID</th>
                        <th>Kode</th>
                        <th>Sumber Intake</th>
                        <th>Status</th>
                        <th>Tanggal</th>
                        <th>Gudang Asal</th>
                        <th>Item</th>
                        <th>Alasan Rusak</th>
                        <th class="text-end">Qty Intake</th>
                        <th class="text-end">Dialokasikan</th>
                        <th class="text-end">Sisa</th>
                        <th>Submit By</th>
                        <th>Catatan</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modal_damaged_goods" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable mw-950px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bolder" id="damage_modal_title">Tambah Barang Rusak</h2>
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
                <form class="form" id="damaged_goods_form">
                    @csrf
                    <div class="row g-3 mb-6">
                        <div class="col-md-6">
                            <label class="required fs-6 fw-bold form-label mb-2">Sumber Intake</label>
                            <select class="form-select form-select-solid" name="source_type" id="damage_source_type" required>
                                <option value="">Pilih sumber intake</option>
                                @foreach(($sourceTypeOptions ?? []) as $code => $label)
                                    <option value="{{ $code }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback" id="error_source_type"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="required fs-6 fw-bold form-label mb-2">Gudang Asal</label>
                            <select class="form-select form-select-solid" name="source_warehouse_id" id="damage_source_warehouse_id" required>
                                <option value="">Pilih gudang asal</option>
                                @foreach(($sourceWarehouses ?? []) as $warehouse)
                                    <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback" id="error_source_warehouse_id"></div>
                            <div class="form-text text-muted">Saat approve, stok akan dipindahkan ke {{ $damagedWarehouseLabel ?? 'Gudang Rusak' }}.</div>
                        </div>
                    </div>

                    <div class="row g-3 mb-6">
                        <div class="col-md-6">
                            <label class="fs-6 fw-bold form-label mb-2">Ref Sumber</label>
                            <input type="text" class="form-control form-control-solid" name="source_ref" id="damage_source_ref" placeholder="Contoh: kode retur, BA, atau referensi internal" />
                            <div class="invalid-feedback" id="error_source_ref"></div>
                        </div>
                    </div>

                    <div class="d-flex flex-column gap-4 mb-7" id="damage_items_container"></div>
                    <div class="mb-7">
                        <button type="button" class="btn btn-light" id="btn_add_damage_item">Tambah Item</button>
                    </div>

                    <div class="fv-row mb-7">
                        <label class="required fs-6 fw-bold form-label mb-2">Tanggal</label>
                        <input type="text" class="form-control form-control-solid" name="transacted_at" id="damage_transacted_at" placeholder="YYYY-MM-DD HH:mm" required />
                        <div class="invalid-feedback" id="error_transacted_at"></div>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fs-6 fw-bold form-label mb-2">Catatan</label>
                        <textarea class="form-control form-control-solid" name="note" id="damage_note" rows="3"></textarea>
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
@endsection

@push('scripts')
<script>
    const dataUrl = '{{ $dataUrl }}';
    const summaryUrl = '{{ $summaryUrl }}';
    const agingUrl = '{{ $agingUrl }}';
    const storeUrl = '{{ $storeUrl }}';
    const showUrlTpl = '{{ route('admin.inventory.damaged-goods.show', ':id') }}';
    const updateUrlTpl = '{{ route('admin.inventory.damaged-goods.update', ':id') }}';
    const deleteUrlTpl = '{{ route('admin.inventory.damaged-goods.destroy', ':id') }}';
    const approveUrlTpl = '{{ route('admin.inventory.damaged-goods.approve', ':id') }}';
    const csrfToken = '{{ csrf_token() }}';
    const canUpdate = {{ $canUpdate ? 'true' : 'false' }};
    const canDelete = {{ $canDelete ? 'true' : 'false' }};
    const itemOptionsHtml = `@foreach($items as $item)<option value="{{ $item->id }}">{{ $item->sku }} - {{ $item->name }}</option>@endforeach`;
    const reasonOptionsHtml = `@foreach(($reasonOptions ?? []) as $code => $label)<option value="{{ $code }}">{{ $label }}</option>@endforeach`;
    const defaultSourceWarehouseId = {{ isset($defaultSourceWarehouseId) ? (int) $defaultSourceWarehouseId : 'null' }};

    document.addEventListener('DOMContentLoaded', () => {
        const tableEl = $('#damaged_goods_table');
        const summaryTableEl = $('#damaged_goods_summary_table');
        const searchInput = document.querySelector('[data-kt-filter="search"]');
        const reasonFilterEl = document.getElementById('damage_reason_filter');
        const summaryOverviewEl = document.getElementById('damage_summary_overview');
        const form = document.getElementById('damaged_goods_form');
        const modalEl = document.getElementById('modal_damaged_goods');
        const modal = modalEl ? new bootstrap.Modal(modalEl) : null;
        const modalContentEl = modalEl?.querySelector('.modal-content') || modalEl;
        const itemsContainer = document.getElementById('damage_items_container');
        const addItemBtn = document.getElementById('btn_add_damage_item');
        const openBtn = document.getElementById('btn_open_damage');
        const modalTitle = document.getElementById('damage_modal_title');
        const transactedAtEl = document.getElementById('damage_transacted_at');
        const sourceWarehouseEl = document.getElementById('damage_source_warehouse_id');
        const sourceTypeEl = document.getElementById('damage_source_type');
        let fpTransacted = null;
        let searchTimer = null;

        const escapeHtml = (value) => String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');

        const formatDateTime = (date) => {
            const pad = (n) => String(n).padStart(2, '0');
            return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`;
        };

        const getJakartaNow = () => {
            const jkt = new Date(new Date().toLocaleString('en-US', { timeZone: 'Asia/Jakarta' }));
            return formatDateTime(jkt);
        };

        const statusLabel = (status) => {
            if (status === 'approved') return '<span class="badge badge-light-success">Disetujui</span>';
            return '<span class="badge badge-light-warning">Menunggu</span>';
        };

        const clearErrors = () => {
            form?.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
            ['error_source_type','error_source_warehouse_id','error_source_ref','error_transacted_at','error_note'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.textContent = '';
            });
            itemsContainer?.querySelectorAll('[data-error-for]')?.forEach(el => { el.textContent = ''; });
        };

        const validateUniqueItems = () => {
            if (!itemsContainer) return true;
            const rows = Array.from(itemsContainer.querySelectorAll('.damage-item-row'));
            const counts = {};
            rows.forEach((row) => {
                const selectEl = row.querySelector('.damage-item-select');
                const val = selectEl?.value;
                if (val) counts[val] = (counts[val] || 0) + 1;
            });

            let hasDuplicate = false;
            rows.forEach((row) => {
                const selectEl = row.querySelector('.damage-item-select');
                const errEl = row.querySelector('[data-error-for="item_id"]');
                const val = selectEl?.value;
                if (selectEl && val && counts[val] > 1) {
                    hasDuplicate = true;
                    selectEl.classList.add('is-invalid');
                    if (errEl) errEl.textContent = 'Item tidak boleh duplikat';
                } else {
                    selectEl?.classList.remove('is-invalid');
                    if (errEl && errEl.textContent === 'Item tidak boleh duplikat') errEl.textContent = '';
                }
            });

            return !hasDuplicate;
        };

        const initSelect2 = (selectEl) => {
            if (selectEl && typeof $ !== 'undefined' && $.fn.select2) {
                $(selectEl).select2({
                    placeholder: 'Pilih item',
                    allowClear: true,
                    width: '100%',
                    dropdownParent: modalContentEl,
                    minimumResultsForSearch: 0,
                }).on('select2:opening select2:closing select2:close', function(e) {
                    e.stopPropagation();
                });
            }
        };

        const renumberRows = () => {
            const rows = itemsContainer.querySelectorAll('.damage-item-row');
            rows.forEach((row, idx) => {
                row.querySelectorAll('[data-name]')?.forEach((el) => {
                    const key = el.getAttribute('data-name');
                    el.name = `items[${idx}][${key}]`;
                });
            });
        };

        const createItemRow = (data = {}) => {
            const row = document.createElement('div');
            row.className = 'card border border-dashed border-gray-300 damage-item-row';
            row.innerHTML = `
                <div class="card-body py-5">
                    <div class="row g-3 align-items-end">
                        <div class="col-lg-4">
                            <label class="required fs-6 fw-bold form-label mb-2">Item</label>
                            <select class="form-select form-select-solid damage-item-select" data-name="item_id" required>
                                <option value=""></option>
                                ${itemOptionsHtml}
                            </select>
                            <div class="invalid-feedback d-block" data-error-for="item_id"></div>
                        </div>
                        <div class="col-lg-3">
                            <label class="required fs-6 fw-bold form-label mb-2">Alasan Rusak</label>
                            <select class="form-select form-select-solid" data-name="reason_code" required>
                                <option value="">Pilih alasan rusak</option>
                                ${reasonOptionsHtml}
                            </select>
                            <div class="invalid-feedback d-block" data-error-for="reason_code"></div>
                        </div>
                        <div class="col-lg-2">
                            <label class="required fs-6 fw-bold form-label mb-2">Qty</label>
                            <input type="number" min="1" class="form-control form-control-solid" data-name="qty" required />
                            <div class="invalid-feedback d-block" data-error-for="qty"></div>
                        </div>
                        <div class="col-lg-2">
                            <label class="fs-6 fw-bold form-label mb-2">Catatan Item</label>
                            <input type="text" class="form-control form-control-solid" data-name="note" />
                            <div class="invalid-feedback d-block" data-error-for="note"></div>
                        </div>
                        <div class="col-lg-1 text-lg-end">
                            <button type="button" class="btn btn-light btn-sm btn-remove-item">Hapus</button>
                        </div>
                    </div>
                </div>
            `;
            itemsContainer.appendChild(row);

            const itemSelectEl = row.querySelector('.damage-item-select');
            const reasonSelectEl = row.querySelector('select[data-name="reason_code"]');
            const qtyEl = row.querySelector('input[data-name="qty"]');
            const noteEl = row.querySelector('input[data-name="note"]');

            if (data.item_id) itemSelectEl.value = String(data.item_id);
            if (reasonSelectEl) reasonSelectEl.value = data.reason_code || '';
            if (qtyEl) qtyEl.value = data.qty ?? '';
            if (noteEl) noteEl.value = data.note ?? '';

            initSelect2(itemSelectEl);
            renumberRows();
            validateUniqueItems();
        };

        const resetForm = () => {
            form?.reset();
            form.dataset.editId = '';
            clearErrors();
            if (modalTitle) modalTitle.textContent = 'Tambah Barang Rusak';
            if (sourceTypeEl) sourceTypeEl.value = 'warehouse';
            if (sourceWarehouseEl) {
                sourceWarehouseEl.value = defaultSourceWarehouseId ? String(defaultSourceWarehouseId) : '';
                if (typeof $ !== 'undefined' && $(sourceWarehouseEl).data('select2')) {
                    $(sourceWarehouseEl).val(sourceWarehouseEl.value).trigger('change.select2');
                }
            }

            const nowJkt = getJakartaNow();
            if (fpTransacted) {
                fpTransacted.setDate(nowJkt, true, 'Y-m-d H:i');
            } else if (transactedAtEl) {
                transactedAtEl.value = nowJkt;
            }

            itemsContainer.innerHTML = '';
            createItemRow();
        };

        const markFieldInvalid = (fieldEl, message, errorEl) => {
            if (fieldEl) fieldEl.classList.add('is-invalid');
            if (errorEl) errorEl.textContent = message;
        };

        const renderAgingSummary = (payload) => {
            const bucketMap = new Map((payload?.buckets || []).map((bucket) => [bucket.code, bucket]));
            ['0_7', '8_30', '31_60', '61_plus'].forEach((code) => {
                const bucket = bucketMap.get(code) || { qty: 0, lines: 0 };
                const qtyEl = document.querySelector(`[data-aging-qty="${code}"]`);
                const linesEl = document.querySelector(`[data-aging-lines="${code}"]`);
                if (qtyEl) qtyEl.textContent = Number(bucket.qty || 0).toLocaleString('id-ID');
                if (linesEl) linesEl.textContent = `${Number(bucket.lines || 0).toLocaleString('id-ID')} line aktif`;
            });
            if (summaryOverviewEl) {
                summaryOverviewEl.textContent = `Total sisa rusak ${Number(payload?.total_remaining_qty || 0).toLocaleString('id-ID')} pcs pada ${Number(payload?.total_skus || 0).toLocaleString('id-ID')} SKU aktif.`;
            }
        };

        const fetchAgingSummary = async () => {
            try {
                const params = new URLSearchParams({
                    q: searchInput?.value || '',
                    reason_code: reasonFilterEl?.value || '',
                    search_mode: window.resolveTableSearchMode ? window.resolveTableSearchMode(summaryTableEl[0] || null) : 'contains',
                });
                const res = await fetch(`${agingUrl}?${params.toString()}`, { headers: { 'Accept': 'application/json' } });
                const json = await res.json();
                if (!res.ok) return;
                renderAgingSummary(json);
            } catch (err) {
                console.error(err);
            }
        };

        itemsContainer?.addEventListener('change', (e) => {
            if (e.target.matches('.damage-item-select')) {
                validateUniqueItems();
            }
        });

        itemsContainer?.addEventListener('click', (e) => {
            const btn = e.target.closest('.btn-remove-item');
            if (!btn) return;
            const row = btn.closest('.damage-item-row');
            if (row) row.remove();
            if (itemsContainer.querySelectorAll('.damage-item-row').length === 0) {
                createItemRow();
            } else {
                renumberRows();
            }
            validateUniqueItems();
        });

        addItemBtn?.addEventListener('click', () => createItemRow());
        openBtn?.addEventListener('click', resetForm);
        modalEl?.addEventListener('hidden.bs.modal', resetForm);

        if (typeof flatpickr !== 'undefined' && transactedAtEl) {
            fpTransacted = flatpickr(transactedAtEl, { enableTime: true, dateFormat: 'Y-m-d H:i', allowInput: true });
        }

        if (sourceWarehouseEl && typeof $ !== 'undefined' && $.fn.select2) {
            $(sourceWarehouseEl).select2({
                placeholder: 'Pilih gudang asal',
                allowClear: true,
                width: '100%',
                dropdownParent: modalContentEl,
                minimumResultsForSearch: 0,
            });
        }

        if (reasonFilterEl && typeof $ !== 'undefined' && $.fn.select2) {
            $(reasonFilterEl).select2({
                minimumResultsForSearch: Infinity,
                width: '100%',
            }).on('change', () => reloadAll());
        }

        if ((!tableEl.length && !summaryTableEl.length) || !$.fn.DataTable) {
            console.error('DataTables unavailable');
            return;
        }

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
                    params.reason_code = reasonFilterEl?.value || '';
                }
            },
            columns: [
                { data: 'id' },
                { data: 'code' },
                { data: 'source' },
                { data: 'status', orderable: false, searchable: false, render: (data) => statusLabel(data) },
                { data: 'transacted_at' },
                { data: 'source_warehouse' },
                { data: 'item', render: (data) => `<div class="text-gray-800 fw-semibold">${escapeHtml(data || '-')}</div>` },
                { data: 'reason_summary', orderable: false, searchable: false, render: (data) => `<span class="badge badge-light-danger">${escapeHtml(data || '-')}</span>` },
                { data: 'qty', className: 'text-end' },
                { data: 'allocated_qty', className: 'text-end' },
                { data: 'remaining_qty', className: 'text-end fw-bolder text-danger' },
                { data: 'submit_by' },
                { data: 'note', render: (data) => `<div class="text-muted text-wrap">${escapeHtml(data || '-')}</div>` },
                { data: 'id', orderable: false, searchable: false, className: 'text-end', render: (data, type, row) => {
                    const isApproved = row?.status === 'approved';
                    const approveItem = (!isApproved && canUpdate)
                        ? `<div class="menu-item px-3"><a href="#" class="menu-link px-3 text-success btn-approve" data-id="${data}">Approve</a></div>`
                        : '';
                    const editItem = (!isApproved && canUpdate)
                        ? `<div class="menu-item px-3"><a href="#" class="menu-link px-3 btn-edit" data-id="${data}">Edit</a></div>`
                        : '';
                    const delItem = (!isApproved && canDelete)
                        ? `<div class="menu-item px-3"><a href="#" class="menu-link px-3 text-danger btn-delete" data-id="${data}">Hapus</a></div>`
                        : '';
                    const actions = `${approveItem}${editItem}${delItem}`;
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
                }},
            ]
        });

        const summaryDt = summaryTableEl.DataTable({
            processing: true,
            serverSide: true,
            dom: 'rtip',
            order: [[6, 'desc']],
            ajax: {
                url: summaryUrl,
                dataSrc: 'data',
                data: function(params) {
                    params.q = searchInput?.value || '';
                    params.reason_code = reasonFilterEl?.value || '';
                }
            },
            columns: [
                { data: 'sku', render: (data) => `<span class="fw-bolder text-gray-900">${escapeHtml(data || '-')}</span>` },
                { data: 'item_name', render: (data) => `<div class="text-gray-700">${escapeHtml(data || '-')}</div>` },
                { data: 'doc_count', className: 'text-end' },
                { data: 'reason_summary', render: (data) => `<span class="badge badge-light-danger">${escapeHtml(data || '-')}</span>` },
                { data: 'intake_qty', className: 'text-end' },
                { data: 'allocated_qty', className: 'text-end' },
                { data: 'remaining_qty', className: 'text-end fw-bolder text-danger' },
                { data: null, orderable: false, searchable: false, render: (_, __, row) => `<span class="badge badge-light-warning">${escapeHtml(row.age_bucket || '-')}</span><div class="fs-8 text-muted mt-1">${escapeHtml(row.age_days || 0)} hari</div>` },
                { data: 'oldest_transacted_at' },
            ]
        });

        const refreshMenus = () => { if (window.KTMenu) KTMenu.createInstances(); };
        refreshMenus();
        dt.on('draw', refreshMenus);

        const reloadAll = () => {
            dt.ajax.reload();
            summaryDt.ajax.reload();
            fetchAgingSummary();
        };

        searchInput?.addEventListener('keyup', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(reloadAll, 250);
        });

        fetchAgingSummary();

        tableEl.on('click', '.btn-edit', async function(e) {
            e.preventDefault();
            const id = this.getAttribute('data-id');
            if (!id) return;

            try {
                const res = await fetch(showUrlTpl.replace(':id', id), { headers: { 'Accept': 'application/json' } });
                const json = await res.json();
                if (!res.ok) {
                    if (typeof Swal !== 'undefined') Swal.fire('Error', json.message || 'Gagal memuat data', 'error');
                    return;
                }

                form.dataset.editId = id;
                if (modalTitle) modalTitle.textContent = `Edit ${json.code || ''}`.trim();
                document.getElementById('damage_source_type').value = json.source_type || '';
                if (sourceWarehouseEl) {
                    sourceWarehouseEl.value = json.source_warehouse_id ? String(json.source_warehouse_id) : '';
                    if (typeof $ !== 'undefined' && $(sourceWarehouseEl).data('select2')) {
                        $(sourceWarehouseEl).val(sourceWarehouseEl.value).trigger('change.select2');
                    }
                }
                document.getElementById('damage_source_ref').value = json.source_ref || '';
                document.getElementById('damage_note').value = json.note || '';
                if (fpTransacted) {
                    fpTransacted.setDate(json.transacted_at || null, true, 'Y-m-d H:i');
                } else {
                    document.getElementById('damage_transacted_at').value = json.transacted_at || '';
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
            if (!id) return;

            let confirmed = true;
            if (typeof Swal !== 'undefined') {
                const res = await Swal.fire({
                    title: 'Apakah Anda yakin?',
                    text: 'Data barang rusak akan dihapus',
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
                const res = await fetch(deleteUrlTpl.replace(':id', id), {
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
                reloadAll();
            } catch (err) {
                console.error(err);
                if (typeof Swal !== 'undefined') Swal.fire('Error', 'Gagal menghapus', 'error');
            }
        });

        tableEl.on('click', '.btn-approve', async function(e) {
            e.preventDefault();
            const id = this.getAttribute('data-id');
            if (!id) return;

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
                const res = await fetch(approveUrlTpl.replace(':id', id), {
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
                reloadAll();
            } catch (err) {
                console.error(err);
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
            const url = isEdit ? updateUrlTpl.replace(':id', form.dataset.editId) : storeUrl;
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
                const json = await res.json();
                if (!res.ok) {
                    if (json?.errors) {
                        const unhandled = [];
                        Object.entries(json.errors).forEach(([key, msgs]) => {
                            const message = msgs.join(', ');
                            if (key.startsWith('items.')) {
                                const parts = key.split('.');
                                const idx = parseInt(parts[1], 10);
                                const field = parts[2];
                                const row = itemsContainer.querySelectorAll('.damage-item-row')[idx];
                                const fieldEl = row?.querySelector(`[data-name="${field}"]`) || null;
                                const errEl = row?.querySelector(`[data-error-for="${field}"]`) || null;
                                if (fieldEl || errEl) {
                                    markFieldInvalid(fieldEl, message, errEl);
                                } else {
                                    unhandled.push(message);
                                }
                            } else {
                                const fieldEl = form.querySelector(`[name="${key}"]`);
                                const errEl = document.getElementById(`error_${key}`);
                                if (fieldEl || errEl) {
                                    markFieldInvalid(fieldEl, message, errEl);
                                } else {
                                    unhandled.push(message);
                                }
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
                reloadAll();
            } catch (err) {
                console.error(err);
                if (typeof Swal !== 'undefined') Swal.fire('Error', 'Gagal menyimpan', 'error');
            }
        });

        resetForm();
    });
</script>
@endpush

@include('layouts.partials.form-submit-confirmation')
