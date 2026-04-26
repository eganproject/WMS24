@extends('layouts.admin')

@section('title', 'Item Stocks')
@section('page_title', 'Item Stocks')

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

        if (!tableEl.length || !$.fn.DataTable) {
            console.error('DataTables unavailable');
            return;
        }

        const renderWarehouseStock = (value, type, row, virtualKey, lowFlagKey) => {
            if (row.item_type === 'bundle') {
                const virtualValue = Number.isFinite(Number(row[virtualKey])) ? Number(row[virtualKey]) : 0;
                if (type !== 'display') return virtualValue;
                return `<span class="fw-bold text-primary">${virtualValue}</span><div class="text-muted fs-8">virtual</div>`;
            }

            const stockValue = Number.isFinite(Number(value)) ? Number(value) : 0;
            if (type !== 'display') return stockValue;

            if (row[lowFlagKey]) {
                return `<span class="fw-bold text-danger">${stockValue}</span>`;
            }

            return stockValue;
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
                { data: 'item_type', render: (data) => data === 'bundle' ? '<span class="badge badge-light-primary">Bundle</span>' : '<span class="badge badge-light-success">Single</span>' },
                { data: 'stock_main', className: 'text-end', render: (data, type, row) => renderWarehouseStock(data, type, row, 'virtual_main', 'is_main_below_safety') },
                { data: 'safety_main', className: 'text-end', render: (data, type, row) => row.item_type === 'bundle' ? '-' : (data ?? 0) },
                { data: 'stock_display', className: 'text-end', render: (data, type, row) => renderWarehouseStock(data, type, row, 'virtual_display', 'is_display_below_safety') },
                { data: 'safety_display', className: 'text-end', render: (data, type, row) => row.item_type === 'bundle' ? '-' : (data ?? 0) },
                { data: 'stock_damaged', className: 'text-end', render: (data, type, row) => row.item_type === 'bundle' ? '-' : (data ?? 0) },
                { data: 'stock_good_total', className: 'text-end', render: (data, type, row) => row.item_type === 'bundle' ? `<span class="fw-bold text-primary">${row.virtual_total ?? 0}</span><div class="text-muted fs-8">virtual total</div>` : (data ?? 0) },
                { data: 'stock_total', className: 'text-end', render: (data, type, row) => row.item_type === 'bundle' ? '-' : (data ?? 0) },
                { data: 'id', orderable:false, searchable:false, className: 'text-end', render: (data, type, row) => {
                    const mutBtn = `<button type="button" class="btn btn-light-info btn-sm btn-mutations me-1" data-id="${data}" data-sku="${row.sku || ''}" data-name="${row.name || ''}">Mutasi</button>`;
                    if (row.item_type === 'bundle') {
                        return mutBtn;
                    }
                    const safetyBtn = `<button type="button" class="btn btn-light-primary btn-sm btn-safety" data-id="${data}" data-sku="${row.sku}" data-name="${row.name}" data-safety-main="${row.safety_main_raw ?? ''}" data-safety-display="${row.safety_display_raw ?? ''}" data-safety-base="${row.safety_base ?? 0}">Set Safety</button>`;
                    return mutBtn + safetyBtn;
                }},
            ]
        });

        const reloadTable = () => dt.ajax.reload();
        searchInput?.addEventListener('keyup', reloadTable);
        exportBtn?.addEventListener('click', () => {
            const q = searchInput?.value?.trim() || '';
            const url = q ? `${exportUrl}?q=${encodeURIComponent(q)}` : exportUrl;
            window.location.href = url;
        });

        tableEl.on('click', '.btn-safety', function() {
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

        tableEl.on('click', '.btn-mutations', function () {
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

        $(document).on('click', '.btn-mut-detail', async function () {
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
