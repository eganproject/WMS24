@extends('layouts.admin')

@section('title', 'Riwayat Substitusi SKU')
@section('page_title', 'Riwayat Substitusi SKU')

@section('content')
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div class="d-flex align-items-center position-relative my-1">
                <span class="svg-icon svg-icon-1 position-absolute ms-6"><i class="fas fa-search"></i></span>
                <input type="text" class="form-control form-control-solid w-300px ps-14" id="filter_search" placeholder="SKU, order, resi, alasan, atau user" />
            </div>
        </div>
        <div class="card-toolbar">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <input type="text" class="form-control form-control-solid w-175px" id="filter_created_by" placeholder="Dilakukan oleh" />
                <input type="text" class="form-control form-control-solid w-150px" id="filter_date_from" placeholder="Dari" value="{{ $today ?? '' }}" />
                <input type="text" class="form-control form-control-solid w-150px" id="filter_date_to" placeholder="Sampai" value="{{ $today ?? '' }}" />
                <button type="button" class="btn btn-light" id="filter_apply">Filter</button>
                <button type="button" class="btn btn-light" id="filter_reset">Reset</button>
            </div>
        </div>
    </div>
    <div class="card-body py-6">
        <div class="d-flex flex-wrap align-items-center gap-6 mb-5">
            <div class="fw-bold">Total Substitusi: <span id="summary_substitutions">0</span></div>
            <div class="fw-bold">Total Qty: <span id="summary_qty">0</span></div>
            <div class="fw-bold">SKU Asal: <span id="summary_original_skus">0</span></div>
            <div class="fw-bold">SKU Pengganti: <span id="summary_replacement_skus">0</span></div>
        </div>
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5" id="qc_substitutions_table">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                        <th>Waktu</th><th>SKU Asal</th><th>SKU Pengganti</th><th>Qty</th><th>Alasan</th><th>ID Pesanan</th><th>No Resi</th><th>Status QC</th><th>Dilakukan Oleh</th><th>Catatan Pembeli</th>
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
document.addEventListener('DOMContentLoaded', () => {
    const table = $('#qc_substitutions_table');
    const dataUrl = @json($dataUrl);
    const today = @json($today ?? '');
    const search = document.getElementById('filter_search');
    const createdBy = document.getElementById('filter_created_by');
    const dateFrom = document.getElementById('filter_date_from');
    const dateTo = document.getElementById('filter_date_to');
    let fpFrom, fpTo;

    if (typeof flatpickr !== 'undefined') {
        fpFrom = flatpickr(dateFrom, { dateFormat: 'Y-m-d', allowInput: true });
        fpTo = flatpickr(dateTo, { dateFormat: 'Y-m-d', allowInput: true });
    }

    const escapeHtml = (value) => String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    const dt = table.DataTable({
        processing: true, serverSide: true, dom: 'rtip', order: [[0, 'desc']],
        ajax: { url: dataUrl, dataSrc: 'data', data: (params) => {
            params.q = search.value;
            params.created_by = createdBy.value;
            params.date_from = dateFrom.value;
            params.date_to = dateTo.value;
        }},
        columns: [
            { data: 'created_at' },
            { data: 'original_sku', render: (value) => `<strong>${escapeHtml(value)}</strong>` },
            { data: 'replacement_sku', render: (value) => `<strong>${escapeHtml(value)}</strong>` },
            { data: 'qty' }, { data: 'reason', render: escapeHtml }, { data: 'id_pesanan' }, { data: 'no_resi' },
            { data: 'qc_status_label', render: (value) => `<span class="badge badge-light-primary">${escapeHtml(value)}</span>` },
            { data: 'created_by' }, { data: 'buyer_note', render: (value) => value ? escapeHtml(value) : '<span class="text-muted">-</span>' },
        ],
    });

    table.on('xhr.dt', () => {
        const summary = dt.ajax.json()?.summary;
        if (!summary) return;
        document.getElementById('summary_substitutions').textContent = summary.substitutions ?? 0;
        document.getElementById('summary_qty').textContent = summary.qty ?? 0;
        document.getElementById('summary_original_skus').textContent = summary.original_skus ?? 0;
        document.getElementById('summary_replacement_skus').textContent = summary.replacement_skus ?? 0;
    });

    const reload = () => dt.ajax.reload();
    document.getElementById('filter_apply').addEventListener('click', reload);
    document.getElementById('filter_reset').addEventListener('click', () => {
        search.value = ''; createdBy.value = '';
        if (fpFrom) fpFrom.setDate(today, true); else dateFrom.value = today;
        if (fpTo) fpTo.setDate(today, true); else dateTo.value = today;
        reload();
    });
    let timeout;
    [search, createdBy].forEach((input) => input.addEventListener('input', () => { clearTimeout(timeout); timeout = setTimeout(reload, 300); }));
});
</script>
@endpush
