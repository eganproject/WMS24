@extends('layouts.admin')

@section('title', 'Transit QC')
@section('page_title', 'Transit QC')

@section('content')

{{-- Stat Cards --}}
<div class="row g-5 mb-6">
    <div class="col-6 col-md-4">
        <div class="card h-100">
            <div class="card-body d-flex flex-column align-items-center justify-content-center py-7">
                <span class="fs-2x fw-bolder text-warning" id="stat_siap">-</span>
                <span class="fs-7 text-muted mt-1">Siap Scan Out</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="card h-100">
            <div class="card-body d-flex flex-column align-items-center justify-content-center py-7">
                <span class="fs-2x fw-bolder text-success" id="stat_selesai">-</span>
                <span class="fs-7 text-muted mt-1">Sudah Scan Out</span>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card h-100">
            <div class="card-body d-flex flex-column align-items-center justify-content-center py-7">
                <span class="fs-2x fw-bolder text-primary" id="stat_qty">-</span>
                <span class="fs-7 text-muted mt-1">Total Item Menunggu</span>
            </div>
        </div>
    </div>
</div>

{{-- Main Table Card --}}
<div class="card">
    <div class="card-header border-0 pt-6 flex-column flex-md-row gap-3">
        {{-- Search --}}
        <div class="card-title">
            <div class="d-flex align-items-center position-relative my-1">
                <span class="svg-icon svg-icon-1 position-absolute ms-6">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <rect opacity="0.5" x="17.0365" y="15.1223" width="8.15546" height="2" rx="1" transform="rotate(45 17.0365 15.1223)" fill="black" />
                        <path d="M11 19C6.55556 19 3 15.4444 3 11C3 6.55556 6.55556 3 11 3C15.4444 3 19 6.55556 19 11C19 15.4444 15.4444 19 11 19ZM11 5C7.53333 5 5 7.53333 5 11C5 14.4667 7.53333 17 11 17C14.4667 17 17 14.4667 17 11C17 7.53333 14.4667 5 11 5Z" fill="black" />
                    </svg>
                </span>
                <input type="text" class="form-control form-control-solid w-250px ps-14"
                       placeholder="Cari ID pesanan, no resi, SKU, kurir..." data-kt-filter="search" />
            </div>
        </div>
        {{-- Filters --}}
        <div class="card-toolbar flex-wrap gap-2">
            <input type="text" class="form-control form-control-solid w-150px" id="filter_date_from"
                   placeholder="Dari tanggal" value="{{ $today ?? '' }}" />
            <input type="text" class="form-control form-control-solid w-150px" id="filter_date_to"
                   placeholder="Sampai tanggal" value="{{ $today ?? '' }}" />
            <button type="button" class="btn btn-light" id="btn_filter">Terapkan</button>
            <button type="button" class="btn btn-light-danger" id="btn_reset">Reset</button>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="card-body pt-4 pb-0">
        <ul class="nav nav-tabs nav-line-tabs nav-line-tabs-2x fs-6 border-0 mb-0" id="transit_tabs">
            <li class="nav-item">
                <a class="nav-link active fw-bold px-4" data-tab="siap_scan_out" href="#">
                    Siap Scan Out
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link fw-bold px-4" data-tab="scan_out_selesai" href="#">
                    Sudah Scan Out
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link fw-bold px-4" data-tab="semua" href="#">
                    Semua
                </a>
            </li>
        </ul>
    </div>

    <div class="card-body py-5">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-4 cursor-pointer" id="transit_table">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                        <th>QC Selesai</th>
                        <th>ID Pesanan</th>
                        <th>No Resi</th>
                        <th>Kurir</th>
                        <th>Di-QC Oleh</th>
                        <th class="text-center">Jml SKU</th>
                        <th class="text-center">Total Qty</th>
                        <th>Lama Tunggu</th>
                        <th>Status</th>
                        <th>Info Scan Out</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

{{-- Detail Modal --}}
<div class="modal fade" id="modal_detail" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Detail SKU — <span id="modal_id_pesanan"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-5">
                <div class="row mb-4 fs-7 text-muted fw-semibold" id="modal_meta"></div>
                <table class="table table-bordered align-middle fs-6">
                    <thead class="text-gray-400 fw-bolder fs-7 text-uppercase">
                        <tr>
                            <th>SKU</th>
                            <th class="text-center">Expected Qty</th>
                            <th class="text-center">Scanned Qty</th>
                        </tr>
                    </thead>
                    <tbody id="modal_items"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
const dataUrl = '{{ $dataUrl }}';
const todayStr = '{{ $today ?? '' }}';
let activeTab = 'siap_scan_out';

const esc = (v) => String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));

const lamaBadge = (menit) => {
    if (menit === null || menit === undefined) return '<span class="text-muted">-</span>';
    let cls = 'badge-light-success';
    if (menit >= 60) cls = 'badge-light-danger';
    else if (menit >= 30) cls = 'badge-light-warning';
    const jam  = Math.floor(menit / 60);
    const sisa = menit % 60;
    const label = jam > 0 ? `${jam}j ${sisa}m` : `${sisa}m`;
    return `<span class="badge ${cls}">${esc(label)}</span>`;
};

const statusBadge = (status) => {
    if (status === 'siap_scan_out')
        return '<span class="badge badge-light-warning">Siap Scan Out</span>';
    return '<span class="badge badge-light-success">Scan Out Selesai</span>';
};

document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.querySelector('[data-kt-filter="search"]');
    const dateFromEl  = document.getElementById('filter_date_from');
    const dateToEl    = document.getElementById('filter_date_to');
    const btnFilter   = document.getElementById('btn_filter');
    const btnReset    = document.getElementById('btn_reset');
    const statSiap    = document.getElementById('stat_siap');
    const statSelesai = document.getElementById('stat_selesai');
    const statQty     = document.getElementById('stat_qty');

    let fpFrom = null, fpTo = null;
    if (typeof flatpickr !== 'undefined') {
        fpFrom = flatpickr(dateFromEl, { dateFormat: 'Y-m-d', allowInput: true });
        fpTo   = flatpickr(dateToEl,   { dateFormat: 'Y-m-d', allowInput: true });
        if (todayStr) { fpFrom?.setDate(todayStr, true); fpTo?.setDate(todayStr, true); }
    }

    const dt = $('#transit_table').DataTable({
        processing: true,
        serverSide: true,
        dom: 'rtip',
        pageLength: 25,
        order: [[0, 'desc']],
        ajax: {
            url: dataUrl,
            dataSrc: 'data',
            data(params) {
                params.tab       = activeTab;
                params.q         = searchInput?.value || '';
                params.date_from = dateFromEl?.value || '';
                params.date_to   = dateToEl?.value   || '';
            },
        },
        columns: [
            { data: 'completed_at' },
            { data: 'id_pesanan' },
            { data: 'no_resi' },
            { data: 'kurir' },
            { data: 'completed_by' },
            { data: 'total_sku',  className: 'text-center' },
            { data: 'total_qty',  className: 'text-center' },
            { data: 'lama_menit', orderable: false, render: (d) => lamaBadge(d) },
            { data: 'scan_out_status', orderable: false, render: (d) => statusBadge(d) },
            { data: null, orderable: false, render: (d, t, row) => {
                if (row.scan_out_status === 'siap_scan_out') return '<span class="text-muted">-</span>';
                return `<div><span class="fw-bold">${esc(row.scan_out_at ?? '-')}</span>`
                     + `<div class="text-muted fs-7">${esc(row.scan_out_by ?? '-')}</div></div>`;
            }},
        ],
    });

    // Update stat cards dari response
    $('#transit_table').on('xhr.dt', function () {
        const json = dt?.ajax?.json?.();
        if (json?.summary) {
            if (statSiap)    statSiap.textContent    = json.summary.siap_scan_out      ?? '-';
            if (statSelesai) statSelesai.textContent = json.summary.scan_out_selesai   ?? '-';
            if (statQty)     statQty.textContent     = json.summary.total_qty_menunggu ?? '-';
        }
    });

    // Klik baris → detail modal
    $('#transit_table tbody').on('click', 'tr', function () {
        const row = dt.row(this).data();
        if (!row) return;

        document.getElementById('modal_id_pesanan').textContent = row.id_pesanan || '-';
        document.getElementById('modal_meta').innerHTML =
            `<div class="col-6"><span class="text-muted">No Resi:</span> <strong>${esc(row.no_resi)}</strong></div>`
          + `<div class="col-6"><span class="text-muted">Kurir:</span> <strong>${esc(row.kurir)}</strong></div>`
          + `<div class="col-6 mt-2"><span class="text-muted">QC Selesai:</span> <strong>${esc(row.completed_at)}</strong></div>`
          + `<div class="col-6 mt-2"><span class="text-muted">Di-QC Oleh:</span> <strong>${esc(row.completed_by)}</strong></div>`;

        const items = row.items || [];
        const tbody = document.getElementById('modal_items');
        if (!items.length) {
            tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">Tidak ada item</td></tr>';
        } else {
            tbody.innerHTML = items.map(it =>
                `<tr>
                    <td>${esc(it.sku)}</td>
                    <td class="text-center">${it.expected_qty}</td>
                    <td class="text-center">${it.scanned_qty}</td>
                </tr>`
            ).join('');
        }

        const modal = new bootstrap.Modal(document.getElementById('modal_detail'));
        modal.show();
    });

    // Tab switching
    document.querySelectorAll('#transit_tabs .nav-link').forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            document.querySelectorAll('#transit_tabs .nav-link').forEach(l => l.classList.remove('active'));
            link.classList.add('active');
            activeTab = link.dataset.tab;
            dt.ajax.reload();
        });
    });

    // Filter buttons
    btnFilter?.addEventListener('click', () => dt.ajax.reload());
    btnReset?.addEventListener('click', () => {
        if (searchInput) searchInput.value = '';
        if (fpFrom && todayStr) fpFrom.setDate(todayStr, true);
        else if (dateFromEl) dateFromEl.value = todayStr;
        if (fpTo && todayStr) fpTo.setDate(todayStr, true);
        else if (dateToEl) dateToEl.value = todayStr;
        dt.ajax.reload();
    });

    searchInput?.addEventListener('keyup', () => dt.ajax.reload());
});
</script>
@endpush
