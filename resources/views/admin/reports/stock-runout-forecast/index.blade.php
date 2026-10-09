@extends('layouts.admin')

@section('title', 'Forecast Ketahanan Stok')
@section('page_title', 'Forecast Ketahanan Stok')

@push('styles')
<style>
    .runout-filter-grid {
        display: grid;
        grid-template-columns: minmax(220px, 1.4fr) minmax(170px, 1fr) repeat(2, minmax(140px, .7fr)) minmax(190px, 1fr) minmax(150px, .8fr);
        gap: .85rem;
        align-items: end;
    }
    .runout-filter-grid label { color: #7e8299; display: block; font-size: .8rem; font-weight: 600; margin-bottom: .35rem; }
    .runout-summary-grid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 1rem; }
    .runout-summary-card {
        background: #f8f9fc;
        border: 1px solid #eef2f7;
        border-radius: .9rem;
        min-height: 112px;
        overflow: hidden;
        padding: 1rem 1.1rem;
        position: relative;
    }
    .runout-summary-card::after {
        background: var(--runout-color);
        border-radius: 999px;
        content: '';
        height: 68px;
        opacity: .09;
        position: absolute;
        right: -18px;
        top: -18px;
        width: 68px;
    }
    .runout-summary-card.is-clickable { cursor: pointer; transition: border-color .15s, transform .15s; }
    .runout-summary-card.is-clickable:hover { border-color: var(--runout-color); transform: translateY(-1px); }
    .summary-items { --runout-color: #009ef7; }
    .summary-units { --runout-color: #f1416c; }
    .summary-empty { --runout-color: #d9214e; }
    .summary-critical { --runout-color: #ffc700; }
    .summary-nearest { --runout-color: #7239ea; }
    .runout-summary-label { color: #7e8299; font-size: .76rem; font-weight: 700; letter-spacing: .03em; text-transform: uppercase; }
    .runout-summary-value { color: #181c32; font-size: 1.7rem; font-weight: 800; line-height: 1.15; margin-top: .35rem; }
    .runout-number { font-variant-numeric: tabular-nums; white-space: nowrap; }
    .runout-status-tabs { display: flex; flex-wrap: wrap; gap: .5rem; }
    .runout-status-tabs .btn { border-radius: 999px; font-weight: 700; padding: .5rem 1rem; }
    .runout-status-tabs .btn .badge { margin-left: .4rem; }
    .runout-table-shell { border: 1px solid #eef2f7; border-radius: .85rem; overflow-x: auto; }
    #stock_runout_forecast_table { margin-bottom: 0 !important; }
    #stock_runout_forecast_table thead th { background: #f8f9fc; color: #5e6278; padding-bottom: 1rem; padding-top: 1rem; white-space: nowrap; }
    #stock_runout_forecast_table tbody td { padding-bottom: .9rem; padding-top: .9rem; vertical-align: middle; }
    #stock_runout_forecast_table tbody tr:hover { background: #fafcff; }
    .runout-item-name { color: #7e8299; font-size: .8rem; line-height: 1.35; margin-top: .15rem; max-width: 300px; white-space: normal; }
    .runout-note { color: #a1a5b7; font-size: .72rem; font-weight: 500; margin-top: .15rem; white-space: nowrap; }
    .runout-coverage { background: #eef2f7; border-radius: 999px; height: 5px; margin-top: .4rem; overflow: hidden; width: 120px; }
    .runout-coverage > span { border-radius: 999px; display: block; height: 100%; }
    .runout-restock { color: #d9214e; font-size: 1.1rem; font-weight: 800; }
    .runout-legend { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .65rem; }
    .runout-legend > div { background: #f8f9fc; border-left: 3px solid var(--runout-color); border-radius: .55rem; padding: .7rem .85rem; }
    @media (max-width: 1399.98px) {
        .runout-filter-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    }
    @media (max-width: 1199.98px) {
        .runout-summary-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    }
    @media (max-width: 767.98px) {
        .runout-filter-grid, .runout-summary-grid, .runout-legend { grid-template-columns: 1fr; }
    }
</style>
@endpush

@section('content')
<div class="card mb-6">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div>
                <h3 class="fw-bolder mb-1">Forecast Ketahanan Stok</h3>
                <div class="text-muted fs-7">Daftar SKU yang diproyeksikan habis dalam periode forecast, beserta jumlah unit yang perlu di-restock.</div>
            </div>
        </div>
        <div class="card-toolbar gap-2">
            <button type="button" class="btn btn-light" id="filter_reset"><i class="fas fa-undo me-1"></i> Reset</button>
            <button type="button" class="btn btn-light-success" id="btn_export"><i class="fas fa-file-excel me-1"></i> Export Excel</button>
        </div>
    </div>
    <div class="card-body pt-3">
        <div class="runout-filter-grid mb-5">
            <div>
                <label for="forecast_search">Cari Barang</label>
                <div class="position-relative">
                    <i class="fas fa-search position-absolute top-50 translate-middle-y ms-4 text-muted"></i>
                    <input type="text" class="form-control form-control-solid ps-11" placeholder="SKU atau nama barang" id="forecast_search" autocomplete="off">
                </div>
            </div>
            <div>
                <label for="filter_category">Kategori</label>
                <select id="filter_category" class="form-select form-select-solid">
                    <option value="">Semua Kategori</option>
                    <option value="0">Tanpa Kategori</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="filter_history_days" title="Jumlah hari ke belakang yang dipakai untuk menghitung rata-rata penjualan">Histori Penjualan <i class="fas fa-question-circle text-gray-400"></i></label>
                <div class="input-group input-group-solid">
                    <input type="number" id="filter_history_days" class="form-control form-control-solid" min="1" max="365" value="30">
                    <span class="input-group-text">hari</span>
                </div>
            </div>
            <div>
                <label for="filter_forecast_days" title="Stok harus cukup untuk berapa hari ke depan">Forecast ke Depan <i class="fas fa-question-circle text-gray-400"></i></label>
                <div class="input-group input-group-solid">
                    <input type="number" id="filter_forecast_days" class="form-control form-control-solid" min="1" max="365" value="14">
                    <span class="input-group-text">hari</span>
                </div>
            </div>
            <div>
                <label for="filter_sort">Urutkan Berdasarkan</label>
                <select id="filter_sort" class="form-select form-select-solid">
                    @foreach($sortOptions as $value => $label)
                        <option value="{{ $value }}" @selected($value === 'runout')>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="filter_sort_dir">Urutan</label>
                <select id="filter_sort_dir" class="form-select form-select-solid">
                    <option value="asc" selected>Kecil ke Besar</option>
                    <option value="desc">Besar ke Kecil</option>
                </select>
            </div>
        </div>

        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 bg-light-primary rounded px-5 py-4">
            <div class="d-flex align-items-center gap-3">
                <i class="fas fa-calendar-alt text-primary fs-3"></i>
                <div id="period_info" class="fw-semibold text-gray-800">Memuat data...</div>
            </div>
            <a class="fw-bold fs-7" data-bs-toggle="collapse" href="#runout_help" role="button" aria-expanded="false" aria-controls="runout_help"><i class="fas fa-info-circle me-1"></i>Cara perhitungan</a>
        </div>
        <div class="collapse" id="runout_help">
            <div class="border border-dashed rounded px-5 py-4 mt-3 fs-7 text-gray-700">
                <div class="mb-2"><b>Penjualan</b> dihitung dari barang keluar untuk pesanan: QC scan resi marketplace + outbound manual. Transfer, penyesuaian, dan barang rusak tidak dihitung.</div>
                <div class="mb-2"><b>Stok gabungan</b> = stok gudang besar + gudang kecil saat ini (gudang barang rusak tidak dihitung).</div>
                <div class="mb-2"><b>Rata-rata / hari</b> = penjualan histori ÷ hari histori. <b>Kebutuhan periode</b> = rata-rata / hari × hari forecast.</div>
                <div class="mb-2"><b>Perlu restock</b> = kebutuhan periode − stok gabungan (dibulatkan ke atas). <b>Estimasi habis</b> = stok gabungan ÷ rata-rata / hari.</div>
                <div>Hanya SKU aktif yang terjual dan stoknya tidak cukup sampai akhir periode forecast yang ditampilkan.</div>
            </div>
        </div>
    </div>
</div>

<div class="runout-summary-grid mb-6">
    <div class="runout-summary-card summary-items"><div class="runout-summary-label">SKU Perlu Restock</div><div class="runout-summary-value runout-number" id="summary_total">0</div><div class="text-muted fs-8 mt-1" id="summary_total_note">Habis dalam periode forecast</div></div>
    <div class="runout-summary-card summary-units"><div class="runout-summary-label">Total Unit Restock</div><div class="runout-summary-value text-danger runout-number" id="summary_restock_need">0</div><div class="text-muted fs-8 mt-1" id="summary_units_note">Unit yang perlu dipesan</div></div>
    <div class="runout-summary-card summary-empty is-clickable" data-status-shortcut="empty"><div class="runout-summary-label">Stok Sudah Habis</div><div class="runout-summary-value text-danger runout-number" id="summary_empty">0</div><div class="text-muted fs-8 mt-1">Stok 0 / minus, restock segera</div></div>
    <div class="runout-summary-card summary-critical is-clickable" data-status-shortcut="critical"><div class="runout-summary-label">Kritis (≤ {{ $criticalDays }} hari)</div><div class="runout-summary-value text-warning runout-number" id="summary_critical">0</div><div class="text-muted fs-8 mt-1">Prioritaskan pemesanan</div></div>
    <div class="runout-summary-card summary-nearest"><div class="runout-summary-label">Habis Paling Cepat</div><div class="runout-summary-value runout-number" id="summary_nearest_runout">-</div><div class="text-muted fs-8 mt-1" id="summary_nearest_date">-</div></div>
</div>

<div class="card">
    <div class="card-header border-0 pt-6 align-items-center">
        <div class="card-title flex-column align-items-start">
            <h3 class="fw-bolder mb-1">Daftar SKU Perlu Restock</h3>
            <div class="text-muted fs-7">Klik judul kolom atau gunakan pilihan "Urutkan" untuk mengubah urutan.</div>
        </div>
        <div class="card-toolbar">
            <div class="runout-status-tabs" id="status_tabs">
                <button type="button" class="btn btn-sm btn-primary" data-status="">Semua<span class="badge badge-circle badge-white text-primary" data-count="all">0</span></button>
                <button type="button" class="btn btn-sm btn-light" data-status="empty">Stok Habis<span class="badge badge-light-danger" data-count="empty">0</span></button>
                <button type="button" class="btn btn-sm btn-light" data-status="critical">Kritis<span class="badge badge-light-warning" data-count="critical">0</span></button>
                <button type="button" class="btn btn-sm btn-light" data-status="warning">Perlu Restock<span class="badge badge-light-primary" data-count="warning">0</span></button>
            </div>
        </div>
    </div>
    <div class="card-body pt-4">
        <div class="runout-legend mb-5 fs-8 text-gray-700">
            <div style="--runout-color:#d9214e"><b class="text-danger">Stok Habis</b> — stok gabungan sudah 0 atau minus.</div>
            <div style="--runout-color:#ffc700"><b class="text-warning">Kritis</b> — diperkirakan habis dalam {{ $criticalDays }} hari atau kurang.</div>
            <div style="--runout-color:#009ef7"><b class="text-primary">Perlu Restock</b> — habis sebelum periode forecast berakhir.</div>
        </div>
        <div class="runout-table-shell">
            <table class="table align-middle table-row-dashed fs-6 gy-4 gs-5" id="stock_runout_forecast_table">
                <thead>
                    <tr class="text-start fw-bolder fs-7 text-uppercase">
                        <th class="w-40px">No</th>
                        <th class="min-w-250px">Barang</th>
                        <th>Status</th>
                        <th class="text-end">Stok Gabungan</th>
                        <th class="text-end">Rata-rata / Hari</th>
                        <th>Estimasi Habis</th>
                        <th class="text-end" id="col_forecast_demand">Kebutuhan Periode</th>
                        <th class="text-end">Sisa Proyeksi</th>
                        <th class="text-end">Perlu Restock</th>
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
document.addEventListener('DOMContentLoaded', function () {
    const dataUrl = @json($dataUrl);
    const exportUrl = @json($exportUrl);
    const defaults = { history: '30', forecast: '14', sort: 'runout', dir: 'asc' };
    const $table = $('#stock_runout_forecast_table');
    const fields = {
        history: document.getElementById('filter_history_days'), forecast: document.getElementById('filter_forecast_days'),
        category: document.getElementById('filter_category'), search: document.getElementById('forecast_search'),
        sort: document.getElementById('filter_sort'), sortDir: document.getElementById('filter_sort_dir'),
    };
    const statusMeta = {
        empty: { label: 'Stok Habis', badge: 'badge-light-danger', bar: '#f1416c' },
        critical: { label: 'Kritis', badge: 'badge-light-warning', bar: '#ffc700' },
        warning: { label: 'Perlu Restock', badge: 'badge-light-primary', bar: '#009ef7' },
    };
    let currentStatus = '';
    let lastPeriod = {};

    const number = (value, digits = 0) => Number(value || 0).toLocaleString('id-ID', { maximumFractionDigits: digits, minimumFractionDigits: digits });
    const escapeHtml = (value) => $('<div>').text(value ?? '').html();
    const date = (value) => value ? new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(value + 'T00:00:00')) : '-';
    const clampDays = (field, fallback) => {
        const value = Math.round(Number(field.value));
        field.value = Number.isFinite(value) && value >= 1 ? Math.min(365, value) : fallback;
    };

    if (typeof $.fn.select2 !== 'undefined') {
        $(fields.category).select2({ placeholder: 'Semua Kategori', allowClear: true, width: '100%' });
    }

    // Nama kolom = kunci urutan di server; kolom tanpa nama tidak bisa diurutkan.
    const table = $table.DataTable({
        processing: true, serverSide: true, ordering: true, order: [[5, 'asc']], dom: 'rtip', pageLength: 25,
        language: {
            info: 'Menampilkan _START_–_END_ dari _TOTAL_ SKU', infoEmpty: 'Tidak ada data', processing: 'Memuat...',
            paginate: { previous: 'Sebelumnya', next: 'Berikutnya' },
            emptyTable: '<div class="text-center py-10 text-muted"><i class="fas fa-check-circle fs-2x text-success d-block mb-3"></i>Tidak ada SKU yang perlu restock pada filter ini.</div>',
        },
        ajax: {
            url: dataUrl,
            data: (params) => {
                const order = (params.order || [])[0];
                params.sort = order ? params.columns[order.column].name : defaults.sort;
                params.dir = order ? order.dir : defaults.dir;
                params.history_days = fields.history.value; params.forecast_days = fields.forecast.value;
                params.category_id = fields.category.value; params.q = fields.search.value; params.status = currentStatus;
                // Kolom dan pencarian bawaan DataTables tidak dipakai server, jadi tidak perlu dikirim.
                delete params.columns; delete params.search; delete params.order;
            },
            dataSrc: (json) => { renderSummary(json); return json.data || []; },
        },
        columns: [
            { data: null, orderable: false, className: 'text-muted', render: (data, type, row, meta) => meta.row + meta.settings._iDisplayStart + 1 },
            { data: null, name: 'sku', render: (row) => `<div class="fw-bolder text-gray-900">${escapeHtml(row.sku)}</div><div class="runout-item-name">${escapeHtml(row.name)}</div><div class="runout-note">${escapeHtml(row.category)}</div>` },
            { data: 'status', orderable: false, render: (value) => `<span class="badge ${statusMeta[value]?.badge || 'badge-light'}">${statusMeta[value]?.label || '-'}</span>` },
            { data: 'stock', name: 'stock', className: 'text-end runout-number fw-bold', render: (value) => `<span class="${Number(value) <= 0 ? 'text-danger' : ''}">${number(value)}</span>` },
            { data: 'daily_average', name: 'daily_average', orderSequence: ['desc', 'asc'], className: 'text-end runout-number', render: (value, type, row) => `<div class="fw-bold">${number(value, 2)}</div><div class="runout-note">${number(row.total_outbound)} terjual / ${number(lastPeriod.history_days)} hr</div>` },
            { data: 'days_until_runout', name: 'runout', render: (value, type, row) => {
                const coverage = Math.max(0, Math.min(100, (Number(value) / Math.max(1, lastPeriod.forecast_days || 1)) * 100));
                const label = Number(row.stock) <= 0 ? '<span class="text-danger fw-bolder">Sudah habis</span>' : `<span class="fw-bolder">${number(value, 1)} hari</span>`;
                return `<div class="runout-number">${label}</div><div class="runout-note">${Number(row.stock) <= 0 ? 'Restock segera' : 'sekitar ' + date(row.runout_date)}</div><div class="runout-coverage" title="Ketahanan stok ${Math.round(coverage)}% dari periode forecast"><span style="width:${coverage}%;background:${statusMeta[row.status]?.bar || '#009ef7'}"></span></div>`;
            } },
            { data: 'forecast_demand', name: 'forecast_demand', orderSequence: ['desc', 'asc'], className: 'text-end runout-number', render: (value) => number(value, 2) },
            { data: 'forecast_stock', name: 'forecast_stock', className: 'text-end runout-number text-danger fw-bold', render: (value) => number(value, 2) },
            { data: 'restock_need', name: 'restock_need', orderSequence: ['desc', 'asc'], className: 'text-end runout-number', render: (value) => `<span class="runout-restock">${number(value)}</span><div class="runout-note">unit</div>` },
        ],
    });

    function renderSummary(json) {
        const summary = json.summary || {};
        const period = lastPeriod = json.period || {};
        const counts = json.status_counts || {};
        const forecastEnd = new Date(); forecastEnd.setDate(forecastEnd.getDate() + Number(period.forecast_days || 0));

        $('#summary_total').text(number(summary.total_items));
        $('#summary_total_note').text(currentStatus ? `Status: ${statusMeta[currentStatus].label}` : `Habis dalam ${number(period.forecast_days)} hari ke depan`);
        $('#summary_restock_need').text(number(summary.total_restock_need));
        $('#summary_units_note').text(`Agar stok cukup ${number(period.forecast_days)} hari`);
        $('#summary_empty').text(number(counts.empty?.items));
        $('#summary_critical').text(number(counts.critical?.items));
        $('#summary_nearest_runout').text(summary.nearest_runout_days === null || summary.nearest_runout_days === undefined ? '-' : (Number(summary.nearest_runout_days) <= 0 ? 'Habis' : `${number(summary.nearest_runout_days, 1)} hari`));
        $('#summary_nearest_date').text(summary.nearest_runout_date ? `Sekitar ${date(summary.nearest_runout_date)}` : 'Tidak ada data');
        $('#col_forecast_demand').text(`Kebutuhan ${number(period.forecast_days)} Hari`);
        $('#period_info').html(`Penjualan <b>${date(period.start)} – ${date(period.end)}</b> (${number(period.history_days)} hari) &nbsp;•&nbsp; Forecast stok sampai <b>${date(forecastEnd.toISOString().slice(0, 10))}</b> (${number(period.forecast_days)} hari ke depan)`);

        const all = Object.values(counts).reduce((total, item) => total + Number(item.items || 0), 0);
        $('#status_tabs [data-count="all"]').text(number(all));
        Object.keys(statusMeta).forEach((status) => $(`#status_tabs [data-count="${status}"]`).text(number(counts[status]?.items)));
    }

    const reload = () => table.ajax.reload(null, true);
    const setStatus = (status) => {
        currentStatus = status;
        $('#status_tabs .btn').each(function () {
            const active = this.dataset.status === status;
            this.classList.toggle('btn-primary', active);
            this.classList.toggle('btn-light', !active);
        });
        reload();
    };

    let timer;
    fields.search.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(reload, 300); });
    [fields.history, fields.forecast].forEach((field) => field.addEventListener('change', () => {
        clampDays(field, field === fields.history ? defaults.history : defaults.forecast);
        reload();
    }));
    $(fields.category).on('change', reload);
    document.querySelectorAll('#status_tabs .btn').forEach((button) => button.addEventListener('click', () => setStatus(button.dataset.status)));
    document.querySelectorAll('[data-status-shortcut]').forEach((card) => card.addEventListener('click', () => setStatus(card.dataset.statusShortcut)));

    // Pilihan "Urutkan" dan klik judul kolom saling disinkronkan.
    const columnIndex = (name) => table.column(`${name}:name`).index();
    const applySort = () => table.order([columnIndex(fields.sort.value), fields.sortDir.value]).draw();
    [fields.sort, fields.sortDir].forEach((field) => field.addEventListener('change', applySort));
    $table.on('order.dt', () => {
        const [column, dir] = table.order()[0] || [];
        const name = table.settings()[0].aoColumns[column]?.sName;
        if (name) { fields.sort.value = name; fields.sortDir.value = dir; }
    });

    document.getElementById('filter_reset').addEventListener('click', () => {
        fields.search.value = ''; fields.history.value = defaults.history; fields.forecast.value = defaults.forecast;
        $(fields.category).val('').trigger('change.select2');
        fields.category.value = '';
        currentStatus = '';
        fields.sort.value = defaults.sort; fields.sortDir.value = defaults.dir;
        $('#status_tabs .btn').each(function () {
            this.classList.toggle('btn-primary', this.dataset.status === '');
            this.classList.toggle('btn-light', this.dataset.status !== '');
        });
        table.order([columnIndex(defaults.sort), defaults.dir]);
        reload();
    });

    document.getElementById('btn_export').addEventListener('click', () => {
        const [column, dir] = table.order()[0] || [];
        const params = new URLSearchParams({
            history_days: fields.history.value, forecast_days: fields.forecast.value, category_id: fields.category.value,
            q: fields.search.value, status: currentStatus,
            sort: table.settings()[0].aoColumns[column]?.sName || defaults.sort, dir: dir || defaults.dir,
        });
        window.location.href = `${exportUrl}?${params.toString()}`;
    });
});
</script>
@endpush
