@extends('layouts.admin')

@section('title', 'Analisis Retur')
@section('page_title', 'Analisis Retur')

@push('styles')
<style>
    .return-report-hero {
        background: linear-gradient(135deg, #172554 0%, #1d4ed8 58%, #0ea5e9 100%);
        border-radius: 1rem;
        color: #fff;
        overflow: hidden;
    }
    .return-report-tabs {
        display: flex;
        gap: .6rem;
        flex-wrap: wrap;
    }
    .return-report-tabs .nav-link {
        border: 1px solid rgba(255,255,255,.28);
        border-radius: .7rem;
        color: rgba(255,255,255,.78);
        font-weight: 700;
        padding: .75rem 1rem;
    }
    .return-report-tabs .nav-link.active {
        background: #fff;
        border-color: #fff;
        color: #1d4ed8;
    }
    .return-filter-grid {
        display: grid;
        grid-template-columns: minmax(220px, 1.5fr) repeat(5, minmax(135px, 1fr));
        gap: .75rem;
        align-items: end;
    }
    .analytics-headline, .analytics-secondary {
        display: grid;
        gap: 1rem;
    }
    .analytics-headline { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    .analytics-secondary { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    .metric-card {
        position: relative;
        border: 1px solid #e8edf5;
        border-radius: .9rem;
        background: #fff;
        min-height: 132px;
        padding: 1.15rem;
        overflow: hidden;
    }
    .metric-card::before {
        content: '';
        position: absolute;
        inset: 0 auto 0 0;
        width: 4px;
        background: var(--metric-color, #1b84ff);
    }
    .metric-card[data-tone="success"] { --metric-color: #50cd89; }
    .metric-card[data-tone="danger"] { --metric-color: #f1416c; }
    .metric-card[data-tone="warning"] { --metric-color: #ffc700; }
    .metric-card[data-tone="info"] { --metric-color: #7239ea; }
    .metric-label {
        color: #7e8299;
        font-size: .76rem;
        font-weight: 700;
        letter-spacing: .035em;
        text-transform: uppercase;
    }
    .metric-value {
        color: #181c32;
        font-size: 2rem;
        font-weight: 800;
        line-height: 1.15;
        margin-top: .4rem;
    }
    .metric-help {
        color: #7e8299;
        font-size: .75rem;
        margin-top: .5rem;
    }
    .secondary-metric {
        border-radius: .8rem;
        background: #f7f9fc;
        padding: .9rem 1rem;
    }
    .secondary-metric-value { font-size: 1.35rem; font-weight: 800; color: #181c32; }
    .report-chart { min-height: 315px; }
    .report-item-list { display: grid; gap: .4rem; min-width: 260px; }
    .report-item-chip {
        background: #f7f9fc;
        border: 1px solid #e8edf5;
        border-radius: .65rem;
        padding: .55rem .65rem;
        font-size: .78rem;
    }
    .qty-stack { display: flex; flex-wrap: wrap; gap: .35rem; min-width: 190px; }
    .timeline-stack { min-width: 145px; }
    .report-table td { vertical-align: top; }
    @media (max-width: 1199.98px) {
        .return-filter-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .analytics-headline, .analytics-secondary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 767.98px) {
        .return-filter-grid, .analytics-headline, .analytics-secondary { grid-template-columns: 1fr; }
        .return-report-tabs .nav-link { width: 100%; text-align: left; }
    }
</style>
@endpush

@section('content')
<div class="return-report-hero p-6 mb-6">
    <div class="d-flex justify-content-between align-items-start gap-4 flex-wrap mb-6">
        <div>
            <div class="fs-2 fw-bolder">Control Tower Retur</div>
            <div class="opacity-75 mt-1">Pantau kualitas, kecepatan proses, backlog, variance, dan performa PIC sesuai alur setiap modul.</div>
        </div>
        <button type="button" class="btn btn-light-success" id="btn_export_return_report">
            <i class="fas fa-file-excel me-1"></i> Export Modul Aktif
        </button>
    </div>
    <ul class="nav return-report-tabs border-0" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-return-source="customer" data-bs-target="#tab_return_customer" type="button">Retur Customer</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-return-source="inbound" data-bs-target="#tab_return_inbound" type="button">Retur Inbound</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-return-source="outbound" data-bs-target="#tab_return_outbound" type="button">Retur Outbound</button></li>
    </ul>
</div>

<div class="card mb-6">
    <div class="card-header border-0 pt-6">
        <div class="card-title"><h3 class="fw-bolder mb-0">Filter Analisis</h3></div>
    </div>
    <div class="card-body pt-2">
        <div class="return-filter-grid">
            <div>
                <label class="text-muted fs-7 mb-1">Pencarian</label>
                <input type="text" class="form-control form-control-solid" placeholder="Kode, resi, supplier, gudang, SKU, atau catatan" id="report_search">
            </div>
            <div>
                <label class="text-muted fs-7 mb-1">Status</label>
                <select id="filter_status" class="form-select form-select-solid"></select>
            </div>
            <div id="match_state_wrap">
                <label class="text-muted fs-7 mb-1">Kecocokan Resi</label>
                <select id="filter_match_state" class="form-select form-select-solid">
                    <option value="">Semua Resi</option>
                    <option value="matched">Resi Ditemukan</option>
                    <option value="unmatched">Input Manual</option>
                </select>
            </div>
            <div id="resi_source_wrap">
                <label class="text-muted fs-7 mb-1">Jenis Resi</label>
                <select id="filter_resi_source" class="form-select form-select-solid">
                    <option value="">Semua Jenis</option>
                    <option value="cod">COD</option>
                    <option value="non_cod">Non COD</option>
                </select>
            </div>
            <div>
                <label class="text-muted fs-7 mb-1">Dari Tanggal</label>
                <input type="text" id="filter_date_from" class="form-control form-control-solid" value="{{ $defaultDateFrom }}" placeholder="YYYY-MM-DD">
            </div>
            <div>
                <label class="text-muted fs-7 mb-1">Sampai Tanggal</label>
                <input type="text" id="filter_date_to" class="form-control form-control-solid" value="{{ $defaultDateTo }}" placeholder="YYYY-MM-DD">
            </div>
        </div>
        <div class="d-flex gap-2 justify-content-end mt-4">
            <button type="button" class="btn btn-primary" id="filter_apply"><i class="fas fa-filter me-1"></i>Terapkan</button>
            <button type="button" class="btn btn-light" id="filter_reset">Reset</button>
        </div>
    </div>
</div>

<div id="report_alert" class="alert alert-danger d-none mb-6"></div>

<div class="mb-5">
    <h2 class="fw-bolder mb-1" id="analytics_title">Analisis Retur Customer</h2>
    <div class="text-muted" id="analytics_description">Memuat analisis...</div>
</div>
<div class="analytics-headline mb-5" id="analytics_headline"></div>
<div class="analytics-secondary mb-6" id="analytics_secondary"></div>

<div class="row g-5 mb-6">
    <div class="col-xl-6">
        <div class="card card-flush h-100">
            <div class="card-header"><h3 class="card-title">Tren Harian Dokumen & Qty</h3></div>
            <div class="card-body pt-0"><div id="chart_return_daily" class="report-chart"></div></div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card card-flush h-100">
            <div class="card-header"><h3 class="card-title">Komposisi Status</h3></div>
            <div class="card-body pt-0"><div id="chart_return_status" class="report-chart"></div></div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card card-flush h-100">
            <div class="card-header"><h3 class="card-title" id="breakdown_title">Breakdown</h3></div>
            <div class="card-body pt-0"><div id="chart_return_breakdown" class="report-chart"></div></div>
        </div>
    </div>
</div>

<div class="card mb-6">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div>
                <h3 class="fw-bolder mb-1">Analisis Qty per SKU</h3>
                <div class="text-muted fs-7">Identifikasi SKU dominan, penyumbang exception, serta perbandingan target dan aktual. Maksimal 50 SKU berdasarkan volume.</div>
            </div>
        </div>
    </div>
    <div class="card-body pt-2">
        <div class="d-flex flex-wrap gap-2 mb-5" id="sku_summary"></div>
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-4">
                <thead><tr class="text-gray-500 fw-bold fs-7 text-uppercase">
                    <th>SKU / Item</th>
                    <th class="text-end">Dokumen</th>
                    <th class="text-end" id="sku_target_label">Target</th>
                    <th class="text-end" id="sku_actual_label">Aktual</th>
                    <th class="text-end">Selisih</th>
                    <th class="text-end" id="sku_exception_label">Exception</th>
                    <th>Kontribusi Volume</th>
                </tr></thead>
                <tbody id="sku_analytics_rows"></tbody>
            </table>
        </div>
    </div>
</div>

<div class="card mb-6">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div>
                <h3 class="fw-bolder mb-1">Performa PIC</h3>
                <div class="text-muted fs-7">Perbandingan volume kerja, keberhasilan proses, lead time, dan exception.</div>
            </div>
        </div>
    </div>
    <div class="card-body pt-2">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-4">
                <thead><tr class="text-gray-500 fw-bold fs-7 text-uppercase">
                    <th id="performance_role_label">PIC</th>
                    <th class="text-end">Dokumen</th>
                    <th class="text-end">Selesai</th>
                    <th class="text-end">Success Rate</th>
                    <th class="text-end" id="performance_qty_label">Qty</th>
                    <th class="text-end">Rata-rata Lead</th>
                    <th class="text-end" id="performance_exception_label">Exception</th>
                    <th>Aktivitas Terakhir</th>
                </tr></thead>
                <tbody id="performance_rows"></tbody>
            </table>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title"><h3 class="fw-bolder mb-0" id="detail_title">Detail Retur Customer</h3></div>
    </div>
    <div class="card-body pt-3">
        <div class="tab-content">
            <div class="tab-pane fade show active" id="tab_return_customer">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-4 report-table w-100" id="customer_returns_report_table">
                        <thead><tr class="text-gray-500 fw-bold fs-7 text-uppercase">
                            <th>Tanggal</th><th>Dokumen & Resi</th><th>Item</th><th>Kualitas</th><th>Analisis</th><th>Timeline</th><th>PIC</th><th class="text-end">Aksi</th>
                        </tr></thead><tbody></tbody>
                    </table>
                </div>
            </div>
            <div class="tab-pane fade" id="tab_return_inbound">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-4 report-table w-100" id="inbound_returns_report_table">
                        <thead><tr class="text-gray-500 fw-bold fs-7 text-uppercase">
                            <th>Tanggal</th><th>Dokumen & Gudang</th><th>Item</th><th>Progress Scan</th><th>Variance</th><th>Timeline</th><th>PIC</th><th class="text-end">Aksi</th>
                        </tr></thead><tbody></tbody>
                    </table>
                </div>
            </div>
            <div class="tab-pane fade" id="tab_return_outbound">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-4 report-table w-100" id="outbound_returns_report_table">
                        <thead><tr class="text-gray-500 fw-bold fs-7 text-uppercase">
                            <th>Tanggal</th><th>Dokumen & Supplier</th><th>Item</th><th class="text-end">Qty</th><th>Approval</th><th>Timeline</th><th>PIC</th><th class="text-end">Aksi</th>
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
    const defaultDateFrom = @json($defaultDateFrom);
    const defaultDateTo = @json($defaultDateTo);
    const number = new Intl.NumberFormat('id-ID');
    const decimal = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });
    const els = {
        search: document.getElementById('report_search'),
        status: document.getElementById('filter_status'),
        match: document.getElementById('filter_match_state'),
        matchWrap: document.getElementById('match_state_wrap'),
        resiSource: document.getElementById('filter_resi_source'),
        resiSourceWrap: document.getElementById('resi_source_wrap'),
        from: document.getElementById('filter_date_from'),
        to: document.getElementById('filter_date_to'),
        alert: document.getElementById('report_alert'),
    };
    const statusOptions = {
        customer: [
            ['', 'Semua Status'], ['inspected', 'Belum Finalisasi'], ['completed', 'Selesai'], ['no_received', 'Tidak Diterima'],
        ],
        inbound: [
            ['', 'Semua Status'], ['pending_scan', 'Menunggu Scan'], ['scanning', 'Sedang Scan'], ['completed', 'Selesai'],
        ],
        outbound: [
            ['', 'Semua Status'], ['pending', 'Menunggu Approval'], ['approved', 'Disetujui'],
        ],
    };
    const moduleTitles = { customer: 'Retur Customer', inbound: 'Retur Inbound', outbound: 'Retur Outbound' };
    const tables = {};
    let activeSource = 'customer';
    let charts = [];
    let fromPicker = null;
    let toPicker = null;

    const escapeHtml = value => String(value ?? '')
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    const duration = value => {
        const minutes = Number(value) || 0;
        if (minutes < 60) return `${decimal.format(minutes)} mnt`;
        if (minutes < 1440) return `${decimal.format(minutes / 60)} jam`;
        return `${decimal.format(minutes / 1440)} hari`;
    };
    const metricValue = metric => {
        const value = Number(metric?.value || 0);
        if (metric?.format === 'percent') return `${decimal.format(value)}%`;
        if (metric?.format === 'duration') return duration(value);
        if (metric?.format === 'signed') return `${value > 0 ? '+' : ''}${number.format(value)}`;
        if (metric?.format === 'decimal') return decimal.format(value);
        return number.format(value);
    };
    const setStatusOptions = () => {
        els.status.innerHTML = (statusOptions[activeSource] || []).map(([value, label]) =>
            `<option value="${escapeHtml(value)}">${escapeHtml(label)}</option>`
        ).join('');
        const customerOnly = activeSource === 'customer';
        els.matchWrap.classList.toggle('d-none', !customerOnly);
        els.resiSourceWrap.classList.toggle('d-none', !customerOnly);
    };
    const filters = () => {
        const result = {
            source: activeSource,
            q: els.search.value.trim(),
            status: els.status.value || '',
            date_from: els.from.value || '',
            date_to: els.to.value || '',
        };
        if (activeSource === 'customer') {
            result.match_state = els.match.value || '';
            result.resi_source = els.resiSource.value || '';
        }
        return result;
    };
    const renderDocument = row => `
        <div class="d-flex flex-column gap-1">
            <div class="fw-bolder text-gray-900">${escapeHtml(row.code || '-')}</div>
            <div class="text-muted fs-8">${escapeHtml(row.ref_primary_label)}: <span class="fw-semibold">${escapeHtml(row.ref_primary_value)}</span></div>
            <div class="text-muted fs-8">${escapeHtml(row.ref_secondary_label)}: <span class="fw-semibold">${escapeHtml(row.ref_secondary_value)}</span></div>
            <div class="text-muted fs-8">${escapeHtml(row.counterparty_label)}: <span class="fw-semibold">${escapeHtml(row.counterparty_value)}</span></div>
            ${row.extra_reference ? `<span class="badge badge-light-danger align-self-start">${escapeHtml(row.extra_reference_label)}: ${escapeHtml(row.extra_reference)}</span>` : ''}
            ${row.note ? `<div class="text-muted fs-8">${escapeHtml(row.note)}</div>` : ''}
        </div>`;
    const renderItems = row => {
        const parts = String(row.item_summary || '').split('||').filter(Boolean);
        if (!parts.length) return '<span class="text-muted">Tidak ada item.</span>';
        const visible = parts.slice(0, 4).map(part => `<div class="report-item-chip">${escapeHtml(part)}</div>`).join('');
        const more = parts.length > 4 ? `<div class="text-muted fs-8">+${parts.length - 4} item lainnya</div>` : '';
        const skuCount = Number(row.sku_count ?? parts.length);
        return `<div class="report-item-list"><div><span class="badge badge-light-info">${number.format(skuCount)} SKU</span></div>${visible}${more}</div>`;
    };
    const statusBadge = row => `<span class="badge ${escapeHtml(row.status_badge || 'badge-light-secondary')}">${escapeHtml(row.status_label || '-')}</span>`;
    const renderPic = row => `
        <div class="d-flex flex-column gap-1">
            <div class="text-muted fs-8">Input: <span class="fw-semibold">${escapeHtml(row.submit_by)}</span></div>
            ${row.secondary_by_label ? `<div class="text-muted fs-8">${escapeHtml(row.secondary_by_label)}: <span class="fw-semibold">${escapeHtml(row.secondary_by)}</span></div>` : ''}
            ${row.tertiary_by_label ? `<div class="text-muted fs-8">${escapeHtml(row.tertiary_by_label)}: <span class="fw-semibold">${escapeHtml(row.tertiary_by)}</span></div>` : ''}
        </div>`;
    const detailAction = row => row.detail_url
        ? `<a href="${escapeHtml(row.detail_url)}" class="btn btn-sm btn-light-primary">Detail</a>`
        : '-';
    const timeline = (row, label = 'Lead') => `
        <div class="timeline-stack">
            <div class="text-muted fs-8">${label}</div>
            <div class="fw-bold">${row.lead_minutes === null ? '-' : duration(row.lead_minutes)}</div>
            ${row.aging_minutes !== null && row.aging_minutes !== undefined ? `<div class="text-danger fs-8">Aging ${duration(row.aging_minutes)}</div>` : ''}
        </div>`;

    const customerColumns = [
        { data: 'transacted_at' },
        { data: null, orderable: false, render: (d,t,row) => renderDocument(row) },
        { data: null, orderable: false, render: (d,t,row) => renderItems(row) },
        { data: null, orderable: false, render: (d,t,row) => `<div class="qty-stack">
            <span class="badge badge-light-primary">Terima ${number.format(row.qty_received || 0)}</span>
            <span class="badge badge-light-success">Bagus ${number.format(row.qty_good || 0)}</span>
            <span class="badge badge-light-warning">Kemasan ${number.format(row.qty_packaging_damaged || 0)}</span>
            <span class="badge badge-light-danger">Rusak ${number.format(row.qty_damaged || 0)}</span>
            <span class="badge badge-light-secondary">Hilang ${number.format(row.qty_lost || 0)}</span>
        </div>` },
        { data: null, orderable: false, render: (d,t,row) => `<div>${statusBadge(row)}<div class="mt-2 fw-semibold">Recovery ${decimal.format(row.quality_rate || 0)}%</div><div class="text-muted fs-8">${row.matched ? 'Resi ditemukan' : 'Input manual'}</div></div>` },
        { data: null, orderable: false, render: (d,t,row) => timeline(row, 'Finalisasi') },
        { data: null, orderable: false, render: (d,t,row) => renderPic(row) },
        { data: null, orderable: false, className: 'text-end', render: (d,t,row) => detailAction(row) },
    ];
    const inboundColumns = [
        { data: 'transacted_at' },
        { data: null, orderable: false, render: (d,t,row) => renderDocument(row) },
        { data: null, orderable: false, render: (d,t,row) => renderItems(row) },
        { data: null, orderable: false, render: (d,t,row) => `<div class="qty-stack"><span class="badge badge-light-primary">Qty ${number.format(row.qty_received || 0)} / ${number.format(row.qty_expected || 0)}</span><span class="badge badge-light-info">Unit ${number.format(row.unit_scanned || 0)} / ${number.format(row.unit_expected || 0)}</span></div><div class="mt-2 fw-semibold">${decimal.format(row.progress_percent || 0)}%</div>` },
        { data: null, orderable: false, render: (d,t,row) => `<div class="${Number(row.qty_variance) === 0 ? 'text-success' : 'text-danger'} fw-bold">${Number(row.qty_variance) > 0 ? '+' : ''}${number.format(row.qty_variance || 0)} qty</div><div class="text-muted fs-8">Reset ${number.format(row.reset_count || 0)}x</div>` },
        { data: null, orderable: false, render: (d,t,row) => `<div class="timeline-stack"><div class="text-muted fs-8">Tunggu: ${row.waiting_minutes === null ? '-' : duration(row.waiting_minutes)}</div><div class="text-muted fs-8">Scan: ${row.scan_minutes === null ? '-' : duration(row.scan_minutes)}</div><div class="fw-bold">Total: ${row.lead_minutes === null ? '-' : duration(row.lead_minutes)}</div>${row.aging_minutes !== null ? `<div class="text-danger fs-8">Aging ${duration(row.aging_minutes)}</div>` : ''}</div>` },
        { data: null, orderable: false, render: (d,t,row) => `<div class="mb-2">${statusBadge(row)}</div>${renderPic(row)}` },
        { data: null, orderable: false, className: 'text-end', render: (d,t,row) => detailAction(row) },
    ];
    const outboundColumns = [
        { data: 'transacted_at' },
        { data: null, orderable: false, render: (d,t,row) => renderDocument(row) },
        { data: null, orderable: false, render: (d,t,row) => renderItems(row) },
        { data: 'qty_total', className: 'text-end fw-bold', render: data => number.format(data || 0) },
        { data: null, orderable: false, render: (d,t,row) => statusBadge(row) },
        { data: null, orderable: false, render: (d,t,row) => timeline(row, 'Approval') },
        { data: null, orderable: false, render: (d,t,row) => renderPic(row) },
        { data: null, orderable: false, className: 'text-end', render: (d,t,row) => detailAction(row) },
    ];
    const tableConfig = {
        customer: ['#customer_returns_report_table', customerColumns],
        inbound: ['#inbound_returns_report_table', inboundColumns],
        outbound: ['#outbound_returns_report_table', outboundColumns],
    };

    const renderMetrics = (analytics = {}) => {
        document.getElementById('analytics_title').textContent = analytics.title || moduleTitles[activeSource];
        document.getElementById('analytics_description').textContent = analytics.description || '';
        document.getElementById('analytics_headline').innerHTML = (analytics.headline || []).map(metric => `
            <div class="metric-card" data-tone="${escapeHtml(metric.tone || 'primary')}">
                <div class="metric-label">${escapeHtml(metric.label)}</div>
                <div class="metric-value">${escapeHtml(metricValue(metric))}</div>
                <div class="metric-help">${escapeHtml(metric.help || 'Sesuai periode dan filter aktif')}</div>
            </div>`).join('');
        document.getElementById('analytics_secondary').innerHTML = (analytics.secondary || []).map(metric => `
            <div class="secondary-metric">
                <div class="text-muted fs-8 fw-semibold">${escapeHtml(metric.label)}</div>
                <div class="secondary-metric-value text-${escapeHtml(metric.tone || 'primary')}">${escapeHtml(metricValue(metric))}</div>
            </div>`).join('');
    };
    const emptyChart = (id, text = 'Tidak ada data pada filter ini.') => {
        document.getElementById(id).innerHTML = `<div class="d-flex align-items-center justify-content-center text-muted h-300px">${escapeHtml(text)}</div>`;
    };
    const renderCharts = (analytics = {}) => {
        charts.forEach(chart => chart.destroy());
        charts = [];
        const chartData = analytics.charts || {};
        document.getElementById('breakdown_title').textContent = chartData.breakdown_title || 'Breakdown';
        if (typeof ApexCharts === 'undefined') {
            ['chart_return_daily', 'chart_return_status', 'chart_return_breakdown'].forEach(id => emptyChart(id, 'Library grafik tidak tersedia.'));
            return;
        }
        const daily = chartData.daily || [];
        if (daily.length) {
            const dailyChart = new ApexCharts(document.getElementById('chart_return_daily'), {
                series: [
                    { name: 'Dokumen', type: 'column', data: daily.map(row => row.documents) },
                    { name: 'Qty', type: 'line', data: daily.map(row => row.qty) },
                    { name: 'Exception', type: 'line', data: daily.map(row => row.exceptions) },
                ],
                chart: { height: 315, type: 'line', toolbar: { show: false } },
                colors: ['#1b84ff', '#50cd89', '#f1416c'],
                stroke: { width: [0, 3, 2], curve: 'smooth' },
                dataLabels: { enabled: false },
                plotOptions: { bar: { borderRadius: 4, columnWidth: '50%' } },
                xaxis: { categories: daily.map(row => row.date) },
                yaxis: [{ title: { text: 'Dokumen' }, min: 0 }, { opposite: true, title: { text: 'Qty' }, min: 0 }],
                grid: { borderColor: '#e4e6ef', strokeDashArray: 4 },
                legend: { position: 'top' },
            });
            dailyChart.render(); charts.push(dailyChart);
        } else emptyChart('chart_return_daily');
        const statuses = chartData.status || [];
        if (statuses.length) {
            const statusChart = new ApexCharts(document.getElementById('chart_return_status'), {
                series: statuses.map(row => row.total),
                labels: statuses.map(row => row.label),
                chart: { type: 'donut', height: 315 },
                colors: ['#50cd89', '#ffc700', '#1b84ff', '#7e8299'],
                legend: { position: 'bottom' },
                dataLabels: { enabled: true },
            });
            statusChart.render(); charts.push(statusChart);
        } else emptyChart('chart_return_status');
        const breakdown = chartData.breakdown || [];
        if (breakdown.length) {
            const breakdownChart = new ApexCharts(document.getElementById('chart_return_breakdown'), {
                series: [{ name: 'Qty', data: breakdown.map(row => row.total) }],
                chart: { type: 'bar', height: 315, toolbar: { show: false } },
                colors: ['#7239ea'],
                plotOptions: { bar: { horizontal: true, borderRadius: 4 } },
                dataLabels: { enabled: true },
                xaxis: { categories: breakdown.map(row => row.label), min: 0 },
                grid: { borderColor: '#e4e6ef', strokeDashArray: 4 },
            });
            breakdownChart.render(); charts.push(breakdownChart);
        } else emptyChart('chart_return_breakdown');
    };
    const renderSkuAnalytics = (analytics = {}) => {
        const skuAnalytics = analytics.sku_analytics || {};
        const labels = skuAnalytics.labels || {};
        document.getElementById('sku_target_label').textContent = labels.target || 'Target';
        document.getElementById('sku_actual_label').textContent = labels.actual || 'Aktual';
        document.getElementById('sku_exception_label').textContent = labels.exception || 'Exception';
        document.getElementById('sku_summary').innerHTML = [
            ['SKU Unik', skuAnalytics.total_unique || 0, 'primary'],
            ['Baris SKU', skuAnalytics.total_lines || 0, 'info'],
            ['Rata-rata SKU/Dokumen', decimal.format(Number(skuAnalytics.avg_per_document || 0)), 'success'],
            [labels.target || 'Target', skuAnalytics.total_target_qty || 0, 'primary'],
            [labels.actual || 'Aktual', skuAnalytics.total_actual_qty || 0, 'success'],
            [labels.exception || 'Exception', skuAnalytics.total_exception_qty || 0, 'danger'],
        ].map(([label, value, tone]) => `<span class="badge badge-light-${tone} fs-7 px-3 py-2">${escapeHtml(label)}: ${typeof value === 'string' ? escapeHtml(value) : number.format(value)}</span>`).join('');

        const rows = skuAnalytics.rows || [];
        document.getElementById('sku_analytics_rows').innerHTML = rows.length ? rows.map(row => {
            const variance = Number(row.variance_qty || 0);
            const contribution = Math.max(0, Math.min(100, Number(row.contribution_rate || 0)));
            const exceptionRate = Number(row.exception_rate || 0);
            return `<tr>
                <td><div class="fw-bolder text-gray-900">${escapeHtml(row.sku)}</div><div class="text-muted fs-8">${escapeHtml(row.name || '-')}</div></td>
                <td class="text-end">${number.format(row.documents || 0)}</td>
                <td class="text-end">${number.format(row.target_qty || 0)}</td>
                <td class="text-end fw-bold">${number.format(row.actual_qty || 0)}</td>
                <td class="text-end ${variance === 0 ? 'text-success' : 'text-danger fw-bold'}">${variance > 0 ? '+' : ''}${number.format(variance)}</td>
                <td class="text-end ${Number(row.exception_qty) > 0 ? 'text-danger fw-bold' : ''}">${number.format(row.exception_qty || 0)} <span class="text-muted fs-8">(${decimal.format(exceptionRate)}%)</span></td>
                <td><div class="d-flex justify-content-between gap-3 fs-8"><span>${number.format(row.volume_qty || 0)} qty</span><span>${decimal.format(contribution)}%</span></div><div class="progress h-4px mt-1"><div class="progress-bar bg-info" style="width:${contribution}%"></div></div></td>
            </tr>`;
        }).join('') : '<tr><td colspan="7" class="text-center text-muted py-8">Belum ada data SKU pada filter ini.</td></tr>';
    };

    const renderPerformance = (analytics = {}) => {
        const labels = analytics.performance_labels || {};
        document.getElementById('performance_role_label').textContent = labels.role || 'PIC';
        document.getElementById('performance_qty_label').textContent = labels.qty || 'Qty';
        document.getElementById('performance_exception_label').textContent = labels.exceptions || 'Exception';
        const rows = analytics.performance || [];
        document.getElementById('performance_rows').innerHTML = rows.length ? rows.map(row => `
            <tr>
                <td class="fw-bold">${escapeHtml(row.operator)}</td>
                <td class="text-end">${number.format(row.documents || 0)}</td>
                <td class="text-end">${number.format(row.completed || 0)}</td>
                <td class="text-end"><span class="badge ${Number(row.success_rate) >= 90 ? 'badge-light-success' : 'badge-light-warning'}">${decimal.format(row.success_rate || 0)}%</span></td>
                <td class="text-end fw-semibold">${number.format(row.qty || 0)}</td>
                <td class="text-end">${duration(row.avg_lead_minutes || 0)}</td>
                <td class="text-end ${Number(row.exceptions) > 0 ? 'text-danger fw-bold' : ''}">${number.format(row.exceptions || 0)}</td>
                <td>${escapeHtml(row.last_activity || '-')}</td>
            </tr>`).join('') : '<tr><td colspan="8" class="text-center text-muted py-8">Belum ada aktivitas PIC pada filter ini.</td></tr>';
    };
    const renderAnalytics = analytics => {
        renderMetrics(analytics);
        renderCharts(analytics);
        renderSkuAnalytics(analytics);
        renderPerformance(analytics);
    };
    const makeTable = source => {
        const [selector, columns] = tableConfig[source];
        tables[source] = $(selector).DataTable({
            processing: true,
            serverSide: true,
            searchDelay: 400,
            pageLength: 25,
            order: [],
            ajax: {
                url: dataUrl,
                data: request => Object.assign(request, filters(), { source }),
                dataSrc: json => {
                    if (activeSource === source) renderAnalytics(json.analytics || {});
                    return json.data || [];
                },
                error: xhr => {
                    let message = 'Gagal memuat laporan retur.';
                    try { message = JSON.parse(xhr.responseText)?.message || message; } catch (error) {}
                    els.alert.textContent = message;
                    els.alert.classList.remove('d-none');
                },
            },
            columns,
            language: { emptyTable: 'Tidak ada data retur yang cocok dengan filter.', processing: 'Memuat analisis...' },
        });
    };
    const reloadActive = () => {
        els.alert.classList.add('d-none');
        if (!tables[activeSource]) makeTable(activeSource);
        else tables[activeSource].ajax.reload();
        document.getElementById('detail_title').textContent = `Detail ${moduleTitles[activeSource]}`;
    };

    if (typeof flatpickr !== 'undefined') {
        fromPicker = flatpickr(els.from, { dateFormat: 'Y-m-d', allowInput: true });
        toPicker = flatpickr(els.to, { dateFormat: 'Y-m-d', allowInput: true });
    }
    setStatusOptions();
    reloadActive();

    document.querySelectorAll('[data-return-source]').forEach(tab => {
        tab.addEventListener('shown.bs.tab', () => {
            activeSource = tab.dataset.returnSource || 'customer';
            setStatusOptions();
            reloadActive();
            setTimeout(() => tables[activeSource]?.columns.adjust(), 100);
        });
    });
    document.getElementById('filter_apply').addEventListener('click', reloadActive);
    els.search.addEventListener('keyup', event => { if (event.key === 'Enter') reloadActive(); });
    document.getElementById('filter_reset').addEventListener('click', () => {
        els.search.value = '';
        els.match.value = '';
        els.resiSource.value = '';
        setStatusOptions();
        if (fromPicker) fromPicker.setDate(defaultDateFrom); else els.from.value = defaultDateFrom;
        if (toPicker) toPicker.setDate(defaultDateTo); else els.to.value = defaultDateTo;
        reloadActive();
    });
    document.getElementById('btn_export_return_report').addEventListener('click', () => {
        window.location.href = `${exportUrl}?${new URLSearchParams(filters())}`;
    });
});
</script>
@endpush
