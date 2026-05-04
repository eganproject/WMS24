@extends('layouts.admin')

@section('title', 'History Surat Jalan')
@section('page_title', 'History Surat Jalan')

@section('content')
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div class="table-search-toolbar">
                <span class="svg-icon svg-icon-1 position-absolute ms-6">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <rect opacity="0.5" x="17.0365" y="15.1223" width="8.15546" height="2" rx="1" transform="rotate(45 17.0365 15.1223)" fill="black" />
                        <path d="M11 19C6.55556 19 3 15.4444 3 11C3 6.55556 3 3 11 3C15.4444 3 19 6.55556 19 11C19 15.4444 15.4444 19 11 19ZM11 5C7.53333 5 5 7.53333 5 11C5 14.4667 7.53333 17 11 17C14.4667 17 17 14.4667 17 11C17 7.53333 14.4667 5 11 5Z" fill="black" />
                    </svg>
                </span>
                <input type="text" class="form-control form-control-solid table-search-input ps-14" placeholder="Cari no SJ, outbound, supplier, gudang, SKU" id="delivery_note_search" />
                <div class="table-search-mode-wrap">
                    <button type="button" class="btn btn-sm btn-light-primary active" data-search-mode="contains">Contains</button>
                    <button type="button" class="btn btn-sm btn-light" data-search-mode="exact">Exact</button>
                </div>
            </div>
        </div>
        <div class="card-toolbar">
            <div class="d-flex align-items-end gap-3 flex-wrap">
                <div class="min-w-160px">
                    <label class="text-muted fs-7 mb-1">Jenis</label>
                    <select id="filter_type" class="form-select form-select-solid w-160px">
                        <option value="">Semua</option>
                        <option value="manual">Outbound Manual</option>
                        <option value="return">Retur Outbound</option>
                    </select>
                </div>
                <div class="min-w-160px">
                    <label class="text-muted fs-7 mb-1">Status</label>
                    <select id="filter_status" class="form-select form-select-solid w-160px">
                        <option value="">Semua</option>
                        <option value="pending">Pending</option>
                        <option value="pending_qc">Pending QC</option>
                        <option value="qc_scanning">QC Scanning</option>
                        <option value="approved">Approved</option>
                    </select>
                </div>
                <div class="min-w-140px">
                    <label class="text-muted fs-7 mb-1">Dari Tgl SJ</label>
                    <input type="text" id="filter_date_from" class="form-control form-control-solid" placeholder="YYYY-MM-DD" />
                </div>
                <div class="min-w-140px">
                    <label class="text-muted fs-7 mb-1">Sampai</label>
                    <input type="text" id="filter_date_to" class="form-control form-control-solid" placeholder="YYYY-MM-DD" />
                </div>
                <button type="button" class="btn btn-light" id="filter_reset">Reset</button>
            </div>
        </div>
    </div>
    <div class="card-body py-6">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5" id="delivery_notes_table">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                        <th width="5%">No</th>
                        <th>Surat Jalan</th>
                        <th>Dokumen</th>
                        <th>Tujuan/Supplier</th>
                        <th>Item</th>
                        <th class="text-end">Qty</th>
                        <th>Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const dataUrl = '{{ $dataUrl }}';
    const showUrlTpl = '{{ $showUrlTpl }}';
    const printUrlTpl = '{{ $printUrlTpl }}';

    document.addEventListener('DOMContentLoaded', () => {
        const tableEl = $('#delivery_notes_table');
        const searchInput = document.getElementById('delivery_note_search');
        const typeFilter = document.getElementById('filter_type');
        const statusFilter = document.getElementById('filter_status');
        const dateFromEl = document.getElementById('filter_date_from');
        const dateToEl = document.getElementById('filter_date_to');
        const resetBtn = document.getElementById('filter_reset');
        let searchMode = 'contains';
        let fpFrom = null;
        let fpTo = null;

        if (typeof flatpickr !== 'undefined') {
            fpFrom = flatpickr(dateFromEl, { dateFormat: 'Y-m-d', allowInput: true });
            fpTo = flatpickr(dateToEl, { dateFormat: 'Y-m-d', allowInput: true });
        }

        const esc = (value) => $('<div>').text(value ?? '').html();
        const statusBadge = (status) => {
            const map = {
                approved: ['Disetujui', 'badge-light-success'],
                pending_qc: ['Pending QC', 'badge-light-primary'],
                qc_scanning: ['QC Scanning', 'badge-light-info'],
                pending: ['Pending', 'badge-light-warning'],
            };
            const [label, cls] = map[status] || [status || '-', 'badge-light-secondary'];
            return `<span class="badge ${cls}">${esc(label)}</span>`;
        };

        const dt = tableEl.DataTable({
            processing: true,
            serverSide: true,
            dom: 'rtip',
            order: [[0, 'desc']],
            ajax: {
                url: dataUrl,
                dataSrc: 'data',
                data(params) {
                    params.q = searchInput?.value || '';
                    params.search_mode = searchMode;
                    params.type = typeFilter?.value || '';
                    params.status = statusFilter?.value || '';
                    params.date_from = dateFromEl?.value || '';
                    params.date_to = dateToEl?.value || '';
                },
            },
            columns: [
                { data: 'id', render: (data, type, row, meta) => meta.row + meta.settings._iDisplayStart + 1 },
                { data: 'surat_jalan_no', render: (data, type, row) => `<div class="fw-bolder text-gray-900">${esc(data)}</div><div class="text-muted fs-8">${esc(row.surat_jalan_at || '-')}</div>` },
                { data: 'code', render: (data, type, row) => {
                    const linked = row.linked_allocation ? `<div class="text-muted fs-8">Alokasi: ${esc(row.linked_allocation)}</div>` : '';
                    return `<div>${esc(row.type_label)}</div><div class="fw-semibold">${esc(data)}</div><div class="text-muted fs-8">Ref: ${esc(row.ref_no)}</div>${linked}`;
                }},
                { data: 'supplier', render: (data, type, row) => `<div>${esc(data)}</div><div class="text-muted fs-8">${esc(row.warehouse)}</div>` },
                { data: 'items', orderable: false, searchable: false },
                { data: 'qty', className: 'text-end fw-bold' },
                { data: 'status', orderable: false, searchable: false, render: statusBadge },
                { data: 'id', orderable: false, searchable: false, className: 'text-end', render: (data) => `
                    <a href="${showUrlTpl.replace(':id', data)}" class="btn btn-sm btn-light-primary me-2">Detail</a>
                    <a href="${printUrlTpl.replace(':id', data)}" target="_blank" rel="noopener" class="btn btn-sm btn-primary">Cetak</a>
                ` },
            ],
        });

        const reload = () => dt.ajax.reload();
        searchInput?.addEventListener('keyup', reload);
        typeFilter?.addEventListener('change', reload);
        statusFilter?.addEventListener('change', reload);
        dateFromEl?.addEventListener('change', reload);
        dateToEl?.addEventListener('change', reload);
        resetBtn?.addEventListener('click', () => {
            if (searchInput) searchInput.value = '';
            if (typeFilter) typeFilter.value = '';
            if (statusFilter) statusFilter.value = '';
            if (fpFrom) fpFrom.clear(); else if (dateFromEl) dateFromEl.value = '';
            if (fpTo) fpTo.clear(); else if (dateToEl) dateToEl.value = '';
            reload();
        });

        document.querySelectorAll('[data-search-mode]').forEach((btn) => {
            btn.addEventListener('click', () => {
                searchMode = btn.dataset.searchMode || 'contains';
                document.querySelectorAll('[data-search-mode]').forEach((el) => {
                    el.classList.toggle('active', el === btn);
                    el.classList.toggle('btn-light-primary', el === btn);
                    el.classList.toggle('btn-light', el !== btn);
                });
                reload();
            });
        });
    });
</script>
@endpush
