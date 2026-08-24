@extends('layouts.admin')

@section('title', 'Forecast Ketahanan Stok')
@section('page_title', 'Forecast Ketahanan Stok')

@section('content')
<style>
    .runout-card { border: 1px solid #eef0f4; box-shadow: 0 10px 24px rgba(15, 23, 42, .04); }
    .runout-number { font-variant-numeric: tabular-nums; }
</style>

<div class="card runout-card mb-6">
    <div class="card-body p-6 p-lg-8">
        <div class="d-flex flex-column flex-xl-row justify-content-between gap-6">
            <div>
                <div class="text-muted fw-semibold mb-2">Perkiraan berdasarkan rata-rata barang keluar dari mutasi outbound.</div>
                <h1 class="fs-2hx fw-bolder text-dark mb-3">Forecast Ketahanan Stok</h1>
                <div class="text-muted">Laporan ini hanya membaca stok dan histori mutasi. Transfer gudang, adjustment, dan barang rusak tidak dihitung sebagai penjualan/pemakaian.</div>
            </div>
            <div class="d-flex align-items-end gap-3 flex-wrap">
                <div>
                    <label class="text-muted fs-7 mb-1">Gudang</label>
                    <select id="filter_warehouse" class="form-select form-select-solid w-200px">
                        @foreach($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" @selected((int) $warehouse->id === (int) $defaultWarehouseId)>{{ $warehouse->name }}{{ $warehouse->code ? ' ('.$warehouse->code.')' : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-muted fs-7 mb-1">Histori rata-rata (hari)</label>
                    <input type="number" id="filter_history_days" class="form-control form-control-solid w-150px" min="1" max="365" value="30">
                </div>
                <div>
                    <label class="text-muted fs-7 mb-1">Forecast ke depan (hari)</label>
                    <input type="number" id="filter_forecast_days" class="form-control form-control-solid w-150px" min="1" max="365" value="14">
                </div>
                <div>
                    <label class="text-muted fs-7 mb-1">Kategori</label>
                    <select id="filter_category" class="form-select form-select-solid w-180px"><option value="">Semua Kategori</option><option value="0">Tanpa Kategori</option>@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select>
                </div>
                <button type="button" class="btn btn-primary" id="filter_apply">Generate</button>
            </div>
        </div>
    </div>
</div>

<div class="alert alert-light-primary d-flex align-items-center mb-6 py-4">
    <i class="fas fa-info-circle fs-2 me-4"></i>
    <div id="period_info">Masukkan jumlah hari histori dan jangka forecast, lalu klik Generate.</div>
</div>

<div class="row g-5 mb-6">
    <div class="col-md-6 col-xl-3"><div class="card runout-card"><div class="card-body"><div class="text-muted fw-semibold mb-2">Total Item</div><div class="fs-2 fw-bolder runout-number" id="summary_total">0</div></div></div></div>
    <div class="col-md-6 col-xl-3"><div class="card runout-card bg-light-danger"><div class="card-body"><div class="text-muted fw-semibold mb-2">Akan Habis</div><div class="fs-2 fw-bolder text-danger runout-number" id="summary_runout">0</div></div></div></div>
    <div class="col-md-6 col-xl-3"><div class="card runout-card bg-light-warning"><div class="card-body"><div class="text-muted fw-semibold mb-2">Di Bawah Safety</div><div class="fs-2 fw-bolder text-warning runout-number" id="summary_critical">0</div></div></div></div>
    <div class="col-md-6 col-xl-3"><div class="card runout-card bg-light-success"><div class="card-body"><div class="text-muted fw-semibold mb-2">Aman</div><div class="fs-2 fw-bolder text-success runout-number" id="summary_safe">0</div></div></div></div>
</div>

<div class="card runout-card">
    <div class="card-header border-0 pt-6">
        <div class="card-title"><div class="d-flex align-items-center position-relative my-1"><i class="fas fa-search position-absolute ms-5 text-muted"></i><input type="text" class="form-control form-control-solid w-300px ps-13" placeholder="Cari SKU / nama barang" id="forecast_search"></div></div>
        <div class="card-toolbar"><select id="filter_status" class="form-select form-select-solid w-190px"><option value="all">Semua kondisi</option><option value="runout">Akan Habis</option><option value="critical">Di Bawah Safety</option><option value="safe">Aman</option><option value="no_demand">Belum Ada Keluar</option></select></div>
    </div>
    <div class="card-body py-6"><div class="table-responsive"><table class="table align-middle table-row-dashed fs-6 gy-5" id="stock_runout_forecast_table"><thead><tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0"><th>Barang</th><th class="text-end">Stok Saat Ini</th><th class="text-end">Safety</th><th class="text-end">Keluar Histori</th><th class="text-end">Rata-rata / Hari</th><th class="text-end">Forecast Stok</th><th class="text-end">Kebutuhan Isi</th><th class="text-end">Estimasi Habis</th><th>Tanggal Habis</th><th>Status</th></tr></thead><tbody></tbody></table></div></div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const dataUrl = @json($dataUrl);
    const $table = $('#stock_runout_forecast_table');
    const fields = {
        warehouse: document.getElementById('filter_warehouse'), history: document.getElementById('filter_history_days'), forecast: document.getElementById('filter_forecast_days'),
        category: document.getElementById('filter_category'), status: document.getElementById('filter_status'), search: document.getElementById('forecast_search'),
    };
    const number = (value, digits = 0) => Number(value || 0).toLocaleString('id-ID', { maximumFractionDigits: digits, minimumFractionDigits: digits });
    const escapeHtml = (value) => $('<div>').text(value ?? '').html();
    const date = (value) => value ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' }).format(new Date(value + 'T00:00:00')) : '-';
    const badge = (row) => `<span class="badge badge-light-${row.status_class || 'secondary'}">${escapeHtml(row.status_label)}</span>`;

    if (typeof $.fn.select2 !== 'undefined') {
        $(fields.warehouse).select2({ width: '100%' }); $(fields.category).select2({ placeholder: 'Semua Kategori', allowClear: true, width: '100%' }); $(fields.status).select2({ minimumResultsForSearch: Infinity, width: '100%' });
    }

    const table = $table.DataTable({
        processing: true, serverSide: false, dom: 'rtip', pageLength: 25, order: [[7, 'asc']],
        ajax: { url: dataUrl, data: (params) => { params.warehouse_id = fields.warehouse.value; params.history_days = fields.history.value; params.forecast_days = fields.forecast.value; params.category_id = fields.category.value; params.status = fields.status.value; params.q = fields.search.value; }, dataSrc: (json) => {
            const summary = json.summary || {}; $('#summary_total').text(number(summary.total_items)); $('#summary_runout').text(number(summary.runout)); $('#summary_critical').text(number(summary.critical)); $('#summary_safe').text(number(summary.safe));
            const period = json.period || {}; $('#period_info').text(`Rata-rata dihitung dari ${period.start || '-'} s.d. ${period.end || '-'} (${number(period.history_days)} hari), gudang ${period.warehouse || '-'}. Forecast stok untuk ${number(period.forecast_days)} hari ke depan.`);
            return json.data || [];
        }},
        columns: [
            { data: null, render: (row) => `<div class="fw-bold">${escapeHtml(row.sku)}</div><div class="text-muted">${escapeHtml(row.name)}</div><div class="text-muted small">${escapeHtml(row.category)}</div>` },
            { data: 'stock', className: 'text-end runout-number', render: number },
            { data: 'safety_stock', className: 'text-end runout-number', render: number },
            { data: 'total_outbound', className: 'text-end runout-number', render: number },
            { data: 'daily_average', className: 'text-end runout-number fw-bold', render: (value) => number(value, 2) },
            { data: 'forecast_stock', className: 'text-end runout-number fw-bolder', render: (value) => `<span class="${Number(value) <= 0 ? 'text-danger' : ''}">${number(value, 2)}</span>` },
            { data: 'replenishment_need', className: 'text-end runout-number', render: number },
            { data: 'days_until_runout', className: 'text-end runout-number', render: (value, type) => type === 'sort' ? (value === null ? 999999 : value) : (value === null ? '-' : `${number(value, 1)} hari`) },
            { data: 'runout_date', render: date }, { data: null, render: badge },
        ],
    });
    let timer; fields.search.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(() => table.ajax.reload(), 250); });
    document.getElementById('filter_apply').addEventListener('click', () => table.ajax.reload());
    [fields.warehouse, fields.category, fields.status].forEach((field) => field.addEventListener('change', () => table.ajax.reload()));
});
</script>
@endpush
