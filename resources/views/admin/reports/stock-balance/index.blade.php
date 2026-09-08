@extends('layouts.admin')

@section('title', 'Laporan Saldo Stok')
@section('page_title', 'Laporan Saldo Stok')

@push('styles')
<style>
    .stock-report-filter-grid {
        display: grid;
        grid-template-columns: minmax(240px, 1.4fr) repeat(3, minmax(170px, .7fr)) auto;
        gap: .85rem;
        align-items: end;
    }
    .stock-report-tabs { gap: .5rem; }
    .stock-report-tabs .nav-link {
        border: 0;
        border-radius: .75rem;
        color: #7e8299;
        font-weight: 700;
        padding: .8rem 1.1rem;
    }
    .stock-report-tabs .nav-link.active { background: #eef6ff; color: #009ef7; }
    .stock-summary-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1rem; }
    .movement-summary-grid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: .85rem; }
    .stock-summary-card, .movement-summary-card {
        background: #f8f9fc;
        border: 1px solid #eef2f7;
        border-radius: .9rem;
        min-height: 112px;
        overflow: hidden;
        padding: 1rem 1.1rem;
        position: relative;
    }
    .movement-summary-card { cursor: pointer; min-height: 104px; transition: border-color .15s, transform .15s; }
    .movement-summary-card:hover, .movement-summary-card.active { border-color: var(--category-color); transform: translateY(-1px); }
    .stock-summary-card::after, .movement-summary-card::after {
        background: var(--category-color);
        border-radius: 999px;
        content: '';
        height: 68px;
        opacity: .09;
        position: absolute;
        right: -18px;
        top: -18px;
        width: 68px;
    }
    .summary-opening { --category-color: #7239ea; }
    .summary-incoming { --category-color: #50cd89; }
    .summary-outgoing { --category-color: #f1416c; }
    .summary-ending { --category-color: #009ef7; }
    .movement-fast { --category-color: #50cd89; }
    .movement-medium { --category-color: #009ef7; }
    .movement-slow { --category-color: #ffc700; }
    .movement-dead { --category-color: #f1416c; }
    .movement-empty { --category-color: #7e8299; }
    .stock-summary-label { color: #7e8299; font-size: .76rem; font-weight: 700; letter-spacing: .03em; text-transform: uppercase; }
    .stock-summary-value { color: #181c32; font-size: 1.7rem; font-weight: 800; line-height: 1.15; margin-top: .35rem; }
    .stock-report-qty { font-variant-numeric: tabular-nums; font-weight: 700; white-space: nowrap; }
    .movement-definition-grid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: .65rem; }
    .movement-definition { background: #f8f9fc; border-left: 3px solid var(--category-color); border-radius: .55rem; padding: .75rem .85rem; }
    .movement-table-toolbar { align-items: end; display: flex; flex-wrap: wrap; gap: 1rem; justify-content: space-between; }
    @media (max-width: 1199.98px) {
        .stock-report-filter-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .movement-summary-grid, .movement-definition-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    }
    @media (max-width: 767.98px) {
        .stock-report-filter-grid, .stock-summary-grid, .movement-summary-grid, .movement-definition-grid { grid-template-columns: 1fr; }
        .stock-report-filter-grid .btn { width: 100%; }
        .stock-report-tabs .nav-item { flex: 1; }
        .stock-report-tabs .nav-link { text-align: center; width: 100%; }
    }
</style>
@endpush

@section('content')
<div class="card mb-6">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div>
                <h3 class="fw-bolder mb-1">Posisi dan Pergerakan Stok</h3>
                <div class="text-muted fs-7">Pantau saldo sekaligus identifikasi kecepatan pergerakan setiap SKU.</div>
            </div>
        </div>
        <div class="card-toolbar">
            <button type="button" class="btn btn-light-success" id="btn_export_stock_balance"><i class="fas fa-file-excel me-1"></i> Export Saldo</button>
        </div>
    </div>
    <div class="card-body pt-3">
        <ul class="nav stock-report-tabs bg-light rounded p-2 mb-6" id="stock_report_tabs" role="tablist">
            <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#stock_balance_tab" type="button" role="tab"><i class="fas fa-balance-scale me-2"></i>Saldo Stok</button></li>
            <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#stock_movement_tab" type="button" role="tab"><i class="fas fa-chart-line me-2"></i>Analisis Pergerakan</button></li>
        </ul>

        <div class="stock-report-filter-grid mb-6">
            <div>
                <label class="text-muted fs-7 mb-1">Cari Barang</label>
                <input type="text" class="form-control form-control-solid" id="report_search" placeholder="SKU atau nama barang" autocomplete="off" />
            </div>
            <div>
                <label class="text-muted fs-7 mb-1">Gudang (bisa pilih beberapa)</label>
                <select class="form-select form-select-solid" id="filter_warehouse" multiple>
                    <option value="all" selected>Seluruh Gudang</option>
                    @foreach($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}">{{ $warehouse->name }}{{ $warehouse->code ? ' ('.$warehouse->code.')' : '' }}</option>
                    @endforeach
                </select>
                <div class="text-muted fs-8 mt-1">Pilih satu atau beberapa gudang.</div>
            </div>
            <div><label class="text-muted fs-7 mb-1">Tanggal Awal</label><input type="text" class="form-control form-control-solid" id="filter_date_from" value="{{ $defaultDateFrom }}" placeholder="YYYY-MM-DD" /></div>
            <div><label class="text-muted fs-7 mb-1">Tanggal Akhir</label><input type="text" class="form-control form-control-solid" id="filter_date_to" value="{{ $defaultDateTo }}" placeholder="YYYY-MM-DD" /></div>
            <div class="d-flex gap-2"><button type="button" class="btn btn-primary" id="filter_apply">Terapkan</button><button type="button" class="btn btn-light" id="filter_reset">Reset</button></div>
        </div>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="stock_balance_tab" role="tabpanel">
                <div class="alert alert-light-primary d-flex align-items-start mb-6">
                    <i class="fas fa-info-circle text-primary mt-1 me-3"></i>
                    <div><div class="fw-semibold">Saldo akhir = stok awal + stok masuk − stok keluar.</div><div class="text-muted fs-8">Tanggal mencakup transaksi pukul 00:00–23:59. Mutasi yang dibatalkan tidak dihitung.</div></div>
                </div>
                <div class="stock-summary-grid mb-8">
                    <div class="stock-summary-card summary-opening"><div class="stock-summary-label">Total Stok Awal</div><div class="stock-summary-value" id="summary_opening">0</div><div class="text-muted fs-8 mt-1" id="summary_scope">0 item</div></div>
                    <div class="stock-summary-card summary-incoming"><div class="stock-summary-label">Total Masuk</div><div class="stock-summary-value text-success" id="summary_in">0</div><div class="text-muted fs-8 mt-1">Selama periode terpilih</div></div>
                    <div class="stock-summary-card summary-outgoing"><div class="stock-summary-label">Total Keluar</div><div class="stock-summary-value text-danger" id="summary_out">0</div><div class="text-muted fs-8 mt-1">Selama periode terpilih</div></div>
                    <div class="stock-summary-card summary-ending"><div class="stock-summary-label">Total Saldo Akhir</div><div class="stock-summary-value text-primary" id="summary_ending">0</div><div class="text-muted fs-8 mt-1" id="summary_period">-</div></div>
                </div>
                <h3 class="fw-bolder mb-4">Rincian Saldo per SKU dan Gudang</h3>
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-5" id="stock_balance_table">
                        <thead><tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                            <th class="w-50px">No</th><th>SKU</th><th class="min-w-250px">Nama Barang</th><th>Gudang</th><th class="text-end">Stok Awal</th><th class="text-end">Masuk</th><th class="text-end">Keluar</th><th class="text-end">Saldo Akhir</th><th class="text-end">Aksi</th>
                        </tr></thead><tbody></tbody>
                    </table>
                </div>
            </div>

            <div class="tab-pane fade" id="stock_movement_tab" role="tabpanel">
                <div class="alert alert-light-primary d-flex align-items-start mb-5">
                    <i class="fas fa-info-circle text-primary mt-1 me-3"></i>
                    <div><div class="fw-semibold">Klasifikasi memakai permintaan keluar dari pengiriman marketplace dan outbound manual.</div><div class="text-muted fs-8">Transfer antar-gudang, opname, penyesuaian, barang rusak, dan mutasi yang dibatalkan tidak dihitung sebagai permintaan.</div></div>
                </div>
                <div class="movement-definition-grid mb-6">
                    <div class="movement-definition movement-fast"><div class="fw-bold text-success">Fast Moving</div><div class="text-muted fs-8">Rata-rata ≥ 1 unit/hari.</div></div>
                    <div class="movement-definition movement-medium"><div class="fw-bold text-primary">Medium Moving</div><div class="text-muted fs-8">≥ 1 unit/minggu, tetapi &lt; 1/hari.</div></div>
                    <div class="movement-definition movement-slow"><div class="fw-bold text-warning">Slow Moving</div><div class="text-muted fs-8">Masih keluar, tetapi &lt; 1 unit/minggu.</div></div>
                    <div class="movement-definition movement-dead"><div class="fw-bold text-danger">Dead Stock</div><div class="text-muted fs-8">Tidak keluar dan saldo akhir masih ada.</div></div>
                    <div class="movement-definition movement-empty"><div class="fw-bold text-gray-700">Tanpa Stok</div><div class="text-muted fs-8">Tidak keluar dan saldo akhir ≤ 0.</div></div>
                </div>
                <div class="movement-summary-grid mb-7">
                    <div class="movement-summary-card movement-fast" data-movement-filter="fast"><div class="stock-summary-label">Fast Moving</div><div class="stock-summary-value text-success" id="movement_fast_count">0</div><div class="text-muted fs-8 mt-1">SKU berputar cepat</div></div>
                    <div class="movement-summary-card movement-medium" data-movement-filter="medium"><div class="stock-summary-label">Medium Moving</div><div class="stock-summary-value text-primary" id="movement_medium_count">0</div><div class="text-muted fs-8 mt-1">SKU berputar stabil</div></div>
                    <div class="movement-summary-card movement-slow" data-movement-filter="slow"><div class="stock-summary-label">Slow Moving</div><div class="stock-summary-value text-warning" id="movement_slow_count">0</div><div class="text-muted fs-8 mt-1">SKU perlu dipantau</div></div>
                    <div class="movement-summary-card movement-dead" data-movement-filter="dead_stock"><div class="stock-summary-label">Dead Stock</div><div class="stock-summary-value text-danger" id="movement_dead_count">0</div><div class="text-muted fs-8 mt-1">SKU menahan stok</div></div>
                    <div class="movement-summary-card movement-empty" data-movement-filter="no_stock"><div class="stock-summary-label">Tanpa Stok</div><div class="stock-summary-value text-gray-700" id="movement_empty_count">0</div><div class="text-muted fs-8 mt-1">SKU tanpa saldo</div></div>
                </div>
                <div class="movement-table-toolbar mb-4">
                    <div><h3 class="fw-bolder mb-1">Analisis Pergerakan per SKU</h3><div class="text-muted fs-8" id="movement_scope">0 SKU dianalisis</div></div>
                    <div class="d-flex align-items-end gap-3 flex-wrap">
                        <div class="w-225px">
                            <label class="text-muted fs-7 mb-1">Kategori Pergerakan</label>
                            <select class="form-select form-select-solid" id="filter_movement_category">
                                <option value="">Semua Kategori</option><option value="fast">Fast Moving</option><option value="medium">Medium Moving</option><option value="slow">Slow Moving</option><option value="dead_stock">Dead Stock</option><option value="no_stock">Tanpa Stok</option>
                            </select>
                        </div>
                        <button type="button" class="btn btn-light-success" id="btn_export_stock_movement">
                            <i class="fas fa-file-excel me-1"></i> Export Analisis
                        </button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-5" id="stock_movement_table">
                        <thead><tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                            <th class="w-50px">No</th><th>SKU</th><th class="min-w-220px">Nama Barang</th><th>Kategori</th><th class="text-end">Keluar Aktual</th><th class="text-end">Rata-rata/Hari</th><th class="text-end">Saldo Akhir</th><th class="text-end">Ketahanan Stok</th><th>Terakhir Keluar</th><th class="text-end">Aksi</th>
                        </tr></thead><tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const dataUrl = @json($dataUrl);
    const exportUrl = @json($exportUrl);
    const defaults = { warehouseValues: ['all'], dateFrom: @json($defaultDateFrom), dateTo: @json($defaultDateTo) };
    const fields = {
        search: document.getElementById('report_search'), warehouse: document.getElementById('filter_warehouse'), dateFrom: document.getElementById('filter_date_from'),
        dateTo: document.getElementById('filter_date_to'), category: document.getElementById('filter_movement_category'), export: document.getElementById('btn_export_stock_balance'),
        movementExport: document.getElementById('btn_export_stock_movement'),
    };
    const numberFormat = new Intl.NumberFormat('id-ID');
    const decimalFormat = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const formatQty = (value) => numberFormat.format(Number(value || 0));
    const formatDecimal = (value) => decimalFormat.format(Number(value || 0));
    const escapeHtml = (value) => String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    const selectedWarehouseIds = () => Array.from(fields.warehouse.selectedOptions).map((option) => option.value).filter((value) => value && value !== 'all');
    const requestParams = (params) => {
        params.date_from = fields.dateFrom.value; params.date_to = fields.dateTo.value; params.warehouse_ids = selectedWarehouseIds(); params.q = fields.search.value.trim();
    };
    const validatePeriod = () => {
        if (!fields.dateFrom.value || !fields.dateTo.value) {
            if (typeof Swal !== 'undefined') Swal.fire('Filter belum lengkap', 'Tanggal awal dan tanggal akhir wajib diisi.', 'warning');
            return false;
        }
        if (fields.dateFrom.value > fields.dateTo.value) {
            if (typeof Swal !== 'undefined') Swal.fire('Rentang tanggal tidak valid', 'Tanggal akhir tidak boleh sebelum tanggal awal.', 'warning');
            return false;
        }
        return true;
    };
    if (typeof flatpickr !== 'undefined') {
        flatpickr(fields.dateFrom, { dateFormat: 'Y-m-d', allowInput: true }); flatpickr(fields.dateTo, { dateFormat: 'Y-m-d', allowInput: true });
    }
    if (typeof $ !== 'undefined' && $.fn.select2) {
        $(fields.warehouse).select2({ width: '100%', placeholder: 'Pilih gudang', closeOnSelect: false });
        $(fields.category).select2({ width: '100%', minimumResultsForSearch: Infinity });
    }
    let previousWarehouseValues = ['all'];
    const normalizeWarehouseSelection = () => {
        let values = Array.from(fields.warehouse.selectedOptions).map((option) => option.value);
        const allWasSelected = previousWarehouseValues.includes('all'); const allIsSelected = values.includes('all');
        if (allIsSelected && !allWasSelected) values = ['all'];
        else if (allIsSelected && values.length > 1) values = values.filter((value) => value !== 'all');
        else if (!values.length) values = ['all'];
        Array.from(fields.warehouse.options).forEach((option) => { option.selected = values.includes(option.value); });
        if (typeof $ !== 'undefined' && $.fn.select2) $(fields.warehouse).trigger('change.select2');
        previousWarehouseValues = values;
    };
    const updateBalanceSummary = (summary, period) => {
        document.getElementById('summary_opening').textContent = formatQty(summary.opening_stock);
        document.getElementById('summary_in').textContent = formatQty(summary.stock_in);
        document.getElementById('summary_out').textContent = formatQty(summary.stock_out);
        document.getElementById('summary_ending').textContent = formatQty(summary.ending_stock);
        document.getElementById('summary_scope').textContent = `${formatQty(summary.total_items)} item · ${formatQty(summary.total_warehouses)} gudang`;
        document.getElementById('summary_period').textContent = period?.date_from && period?.date_to ? `${period.date_from} s.d. ${period.date_to}` : '-';
    };
    const balanceTable = $('#stock_balance_table').DataTable({
        processing: true, serverSide: true, searchDelay: 400, pageLength: 25, lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]], dom: 'rt<"d-flex flex-stack flex-wrap pt-5"lip>', order: [[2, 'asc']],
        ajax: { url: dataUrl, data: requestParams, dataSrc: (json) => { updateBalanceSummary(json.summary || {}, json.period || {}); return json.data || []; }, error: (xhr) => { if (typeof toastr !== 'undefined') toastr.error(xhr.responseJSON?.message || 'Data laporan gagal dimuat.'); } },
        columns: [
            { data: null, orderable: false, searchable: false, render: (data, type, row, meta) => meta.row + meta.settings._iDisplayStart + 1 },
            { data: 'sku', render: (value, type, row) => `<div class="fw-bolder text-gray-900">${escapeHtml(value || '-')}</div>${row.item_status === 'inactive' ? '<span class="badge badge-light-secondary mt-1">Nonaktif</span>' : ''}` },
            { data: 'item_name', render: (value) => `<span class="fw-semibold text-gray-800">${escapeHtml(value || '-')}</span>` },
            { data: 'warehouse_name', render: (value, type, row) => `<div>${escapeHtml(value || '-')}</div><div class="text-muted fs-8">${escapeHtml(row.warehouse_code || '')}</div>` },
            { data: 'opening_stock', className: 'text-end stock-report-qty', render: formatQty },
            { data: 'stock_in', className: 'text-end stock-report-qty text-success', render: (value) => value > 0 ? `+${formatQty(value)}` : '0' },
            { data: 'stock_out', className: 'text-end stock-report-qty text-danger', render: (value) => value > 0 ? `−${formatQty(value)}` : '0' },
            { data: 'ending_stock', className: 'text-end stock-report-qty', render: (value) => `<span class="badge ${Number(value) < 0 ? 'badge-light-danger' : 'badge-light-primary'} fs-7">${formatQty(value)}</span>` },
            { data: null, orderable: false, searchable: false, className: 'text-end', render: (data, type, row) => row.mutation_url ? `<a href="${escapeHtml(row.mutation_url)}" class="btn btn-sm btn-light-primary"><i class="fas fa-list-ul"></i> Mutasi</a>` : '<span class="text-muted">-</span>' },
        ],
        language: { emptyTable: 'Tidak ada data stok yang sesuai dengan filter.', info: 'Menampilkan _START_–_END_ dari _TOTAL_ baris', infoEmpty: 'Tidak ada data', lengthMenu: 'Tampilkan _MENU_', loadingRecords: 'Memuat...', processing: 'Menghitung saldo stok...', paginate: { previous: 'Sebelumnya', next: 'Berikutnya' } },
    });
    const categoryMeta = {
        fast: ['Fast Moving', 'badge-light-success'], medium: ['Medium Moving', 'badge-light-primary'], slow: ['Slow Moving', 'badge-light-warning'],
        dead_stock: ['Dead Stock', 'badge-light-danger'], no_stock: ['Tanpa Stok', 'badge-light-secondary'],
    };
    const categoryBadge = (value) => { const meta = categoryMeta[value] || [value || '-', 'badge-light-secondary']; return `<span class="badge ${meta[1]} fs-7">${escapeHtml(meta[0])}</span>`; };
    const formatDateTime = (value) => {
        if (!value) return '<span class="text-muted">Belum pernah</span>';
        const parsed = new Date(String(value).replace(' ', 'T')); if (Number.isNaN(parsed.getTime())) return escapeHtml(value);
        return new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' }).format(parsed);
    };
    const updateMovementSummary = (summary, period) => {
        document.getElementById('movement_fast_count').textContent = formatQty(summary.fast_items);
        document.getElementById('movement_medium_count').textContent = formatQty(summary.medium_items);
        document.getElementById('movement_slow_count').textContent = formatQty(summary.slow_items);
        document.getElementById('movement_dead_count').textContent = formatQty(summary.dead_stock_items);
        document.getElementById('movement_empty_count').textContent = formatQty(summary.no_stock_items);
        document.getElementById('movement_scope').textContent = `${formatQty(summary.total_items)} SKU · ${formatQty(summary.demand_out)} unit keluar aktual · ${formatQty(period.days)} hari observasi`;
        document.querySelectorAll('[data-movement-filter]').forEach((card) => card.classList.toggle('active', card.dataset.movementFilter === fields.category.value));
    };
    let movementTable = null;
    const createMovementTable = () => {
        if (movementTable) return movementTable;
        movementTable = $('#stock_movement_table').DataTable({
            processing: true, serverSide: true, pageLength: 25, lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]], dom: 'rt<"d-flex flex-stack flex-wrap pt-5"lip>', order: [[5, 'desc']],
            ajax: { url: dataUrl, data: (params) => { requestParams(params); params.analysis = 'movement'; params.movement_category = fields.category.value; }, dataSrc: (json) => { updateMovementSummary(json.summary || {}, json.period || {}); return json.data || []; }, error: (xhr) => { if (typeof toastr !== 'undefined') toastr.error(xhr.responseJSON?.message || 'Analisis pergerakan gagal dimuat.'); } },
            columns: [
                { data: null, orderable: false, searchable: false, render: (data, type, row, meta) => meta.row + meta.settings._iDisplayStart + 1 },
                { data: 'sku', render: (value, type, row) => `<div class="fw-bolder text-gray-900">${escapeHtml(value || '-')}</div>${row.item_status === 'inactive' ? '<span class="badge badge-light-secondary mt-1">Nonaktif</span>' : ''}` },
                { data: 'item_name', render: (value) => `<span class="fw-semibold text-gray-800">${escapeHtml(value || '-')}</span>` },
                { data: 'movement_category', render: categoryBadge },
                { data: 'demand_out', className: 'text-end stock-report-qty', render: (value, type, row) => `<div>${formatQty(value)}</div><div class="text-muted fs-8 fw-normal">${formatQty(row.demand_documents)} dokumen</div>` },
                { data: 'average_daily_out', className: 'text-end stock-report-qty text-primary', render: formatDecimal },
                { data: 'ending_stock', className: 'text-end stock-report-qty', render: (value) => `<span class="${Number(value) <= 0 ? 'text-danger' : ''}">${formatQty(value)}</span>` },
                { data: 'stock_coverage_days', className: 'text-end stock-report-qty', render: (value) => value === null ? '<span class="text-muted">-</span>' : `${formatQty(Math.ceil(Number(value)))} hari` },
                { data: 'last_out_at', render: formatDateTime },
                { data: null, orderable: false, searchable: false, className: 'text-end', render: (data, type, row) => row.mutation_url ? `<a href="${escapeHtml(row.mutation_url)}" class="btn btn-sm btn-light-primary"><i class="fas fa-list-ul"></i> Mutasi</a>` : '<span class="text-muted">-</span>' },
            ],
            language: { emptyTable: 'Tidak ada SKU pada kategori pergerakan ini.', info: 'Menampilkan _START_–_END_ dari _TOTAL_ SKU', infoEmpty: 'Tidak ada data', lengthMenu: 'Tampilkan _MENU_', loadingRecords: 'Memuat...', processing: 'Menganalisis pergerakan stok...', paginate: { previous: 'Sebelumnya', next: 'Berikutnya' } },
        });
        return movementTable;
    };
    const reload = () => { if (!validatePeriod()) return; balanceTable.ajax.reload(); if (movementTable) movementTable.ajax.reload(); };
    document.getElementById('filter_apply').addEventListener('click', reload);
    fields.warehouse.addEventListener('change', () => { normalizeWarehouseSelection(); reload(); });
    let searchTimer = null; fields.search.addEventListener('input', () => { clearTimeout(searchTimer); searchTimer = setTimeout(reload, 450); });
    [fields.dateFrom, fields.dateTo].forEach((input) => input.addEventListener('change', reload));
    fields.category.addEventListener('change', () => { if (movementTable && validatePeriod()) movementTable.ajax.reload(); });
    document.querySelectorAll('[data-movement-filter]').forEach((card) => card.addEventListener('click', () => {
        fields.category.value = fields.category.value === card.dataset.movementFilter ? '' : card.dataset.movementFilter;
        if (typeof $ !== 'undefined' && $.fn.select2) $(fields.category).trigger('change.select2');
        if (movementTable && validatePeriod()) movementTable.ajax.reload();
    }));
    document.getElementById('filter_reset').addEventListener('click', () => {
        fields.search.value = ''; Array.from(fields.warehouse.options).forEach((option) => { option.selected = defaults.warehouseValues.includes(option.value); });
        previousWarehouseValues = [...defaults.warehouseValues]; fields.dateFrom.value = defaults.dateFrom; fields.dateTo.value = defaults.dateTo; fields.category.value = '';
        if (typeof $ !== 'undefined' && $.fn.select2) { $(fields.warehouse).trigger('change.select2'); $(fields.category).trigger('change.select2'); }
        reload();
    });
    document.querySelectorAll('#stock_report_tabs [data-bs-toggle="tab"]').forEach((tab) => tab.addEventListener('shown.bs.tab', (event) => {
        const movementActive = event.target.getAttribute('data-bs-target') === '#stock_movement_tab'; fields.export.classList.toggle('d-none', movementActive);
        if (movementActive) createMovementTable().columns.adjust(); else balanceTable.columns.adjust();
    }));
    fields.export.addEventListener('click', () => {
        if (!validatePeriod()) return;
        const params = new URLSearchParams(); params.set('date_from', fields.dateFrom.value); params.set('date_to', fields.dateTo.value);
        selectedWarehouseIds().forEach((value) => params.append('warehouse_ids[]', value)); if (fields.search.value.trim()) params.set('q', fields.search.value.trim());
        window.location.href = `${exportUrl}?${params.toString()}`;
    });
    fields.movementExport.addEventListener('click', () => {
        if (!validatePeriod()) return;
        const params = new URLSearchParams(); params.set('analysis', 'movement'); params.set('date_from', fields.dateFrom.value); params.set('date_to', fields.dateTo.value);
        selectedWarehouseIds().forEach((value) => params.append('warehouse_ids[]', value));
        if (fields.search.value.trim()) params.set('q', fields.search.value.trim());
        if (fields.category.value) params.set('movement_category', fields.category.value);
        window.location.href = `${exportUrl}?${params.toString()}`;
    });
});
</script>
@endpush
