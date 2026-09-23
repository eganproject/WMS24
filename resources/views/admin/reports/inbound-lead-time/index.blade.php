@extends('layouts.admin')

@section('title', 'Lead Time Inbound')
@section('page_title', 'Lead Time Inbound')

@section('content')
<div class="card mb-6">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div>
                <div class="fs-3 fw-bold text-gray-900">Tracking Dokumen sampai Completed</div>
                <div class="text-muted fs-7">Periode berdasarkan tanggal dokumen dibuat. Durasi berjalan tetap tampil untuk dokumen yang belum selesai.</div>
            </div>
        </div>
        <div class="card-toolbar">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <input type="text" class="form-control form-control-solid w-150px" id="filter_date_from" placeholder="Dari" value="{{ $today }}">
                <input type="text" class="form-control form-control-solid w-150px" id="filter_date_to" placeholder="Sampai" value="{{ $today }}">
                <select class="form-select form-select-solid w-180px" id="filter_type">
                    <option value="">Semua Jenis</option>
                    <option value="receipt">Penerimaan Barang</option>
                    <option value="return">Retur</option>
                    <option value="manual">Manual</option>
                    <option value="opening">Saldo Awal</option>
                </select>
                <select class="form-select form-select-solid w-170px" id="filter_status">
                    <option value="">Semua Status</option>
                    <option value="pending_scan">Menunggu Scan</option>
                    <option value="scanning">Sedang Scan</option>
                    <option value="completed">Selesai</option>
                </select>
                <input type="text" class="form-control form-control-solid w-225px" id="filter_search" placeholder="Kode / referensi / petugas">
                <button type="button" class="btn btn-primary" id="filter_apply"><i class="fas fa-filter me-1"></i>Terapkan</button>
                <button type="button" class="btn btn-light" id="filter_reset">Reset</button>
                <button type="button" class="btn btn-success" id="export_excel"><i class="fas fa-file-excel me-1"></i>Export Excel</button>
            </div>
        </div>
    </div>
</div>

<div id="report_alert" class="alert alert-danger d-none mb-6"></div>

<div class="row g-4 mb-6">
    <div class="col-sm-6 col-xl-3">
        <div class="card card-flush h-100 border border-gray-200"><div class="card-body">
            <div class="text-muted fw-semibold">Dokumen Dibuat</div>
            <div class="fs-2hx fw-bold text-primary" id="summary_total">0</div>
            <div class="fs-7 text-muted"><span id="summary_completed">0</span> sudah completed</div>
        </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card card-flush h-100 border border-gray-200"><div class="card-body">
            <div class="text-muted fw-semibold">Completion Rate</div>
            <div class="fs-2hx fw-bold text-success"><span id="summary_rate">0</span><span class="fs-5 ms-1">%</span></div>
            <div class="fs-7 text-muted"><span id="summary_pending">0</span> menunggu · <span id="summary_scanning">0</span> sedang scan</div>
        </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card card-flush h-100 border border-gray-200"><div class="card-body">
            <div class="text-muted fw-semibold">Rata-rata Total Lead Time</div>
            <div class="fs-2hx fw-bold text-info" id="summary_lead">0 menit</div>
            <div class="fs-7 text-muted">Dibuat sampai completed</div>
        </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card card-flush h-100 border border-gray-200"><div class="card-body">
            <div class="text-muted fw-semibold">Dokumen Terbuka Terlama</div>
            <div class="fs-2hx fw-bold text-warning" id="summary_oldest">0 menit</div>
            <div class="fs-7 text-muted">Aging sejak dokumen dibuat</div>
        </div></div>
    </div>
</div>

<div class="row g-4 mb-6">
    @foreach([
        ['id' => 'summary_waiting', 'label' => 'Rata-rata Menunggu Scan', 'icon' => 'fa-hourglass-half', 'class' => 'warning'],
        ['id' => 'summary_scan', 'label' => 'Rata-rata Proses Scan', 'icon' => 'fa-barcode', 'class' => 'primary'],
        ['id' => 'summary_max_lead', 'label' => 'Lead Time Terlama', 'icon' => 'fa-stopwatch', 'class' => 'danger'],
        ['id' => 'summary_variance', 'label' => 'Dokumen Berselisih', 'icon' => 'fa-exclamation-triangle', 'class' => 'danger'],
        ['id' => 'summary_reset', 'label' => 'Total Reset Scan', 'icon' => 'fa-redo', 'class' => 'info'],
    ] as $metric)
        <div class="col-6 col-md">
            <div class="bg-light-{{ $metric['class'] }} rounded-3 p-4 h-100">
                <div class="d-flex align-items-center gap-3">
                    <i class="fas {{ $metric['icon'] }} text-{{ $metric['class'] }} fs-2"></i>
                    <div><div class="text-muted fs-7">{{ $metric['label'] }}</div><div class="fs-3 fw-bold" id="{{ $metric['id'] }}">0</div></div>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="row g-6 mb-6">
    <div class="col-xl-8">
        <div class="card card-flush h-100">
            <div class="card-header"><h3 class="card-title">Dokumen per Tanggal Dibuat</h3></div>
            <div class="card-body pt-0"><div id="chart_daily" style="min-height:320px"></div></div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card card-flush h-100">
            <div class="card-header"><h3 class="card-title">Komposisi Status</h3></div>
            <div class="card-body pt-0"><div id="chart_status" style="min-height:320px"></div></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title"><h3 class="fw-bold m-0">Detail Timeline Dokumen</h3></div>
    </div>
    <div class="card-body pt-3">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-4 w-100" id="detail_table">
                <thead><tr class="text-gray-500 fw-bold fs-7 text-uppercase">
                    <th>Kode</th><th>Jenis</th><th>Status</th><th>Dibuat</th><th>Mulai Scan</th><th>Completed</th>
                    <th class="text-end">Menunggu</th><th class="text-end">Proses Scan</th><th class="text-end">Total Lead</th>
                    <th class="text-end">Aging</th><th>Petugas</th><th class="text-end">Qty</th><th class="text-end">Selisih</th><th class="text-end">Progress</th>
                </tr></thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const dataUrl = @json($dataUrl);
    const exportUrl = @json($exportUrl);
    const today = @json($today);
    const number = new Intl.NumberFormat('id-ID');
    const decimal = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });
    const els = {
        from: document.getElementById('filter_date_from'), to: document.getElementById('filter_date_to'),
        type: document.getElementById('filter_type'), status: document.getElementById('filter_status'),
        search: document.getElementById('filter_search'), alert: document.getElementById('report_alert'),
    };
    let charts = [];
    let fromPicker = null;
    let toPicker = null;

    if (typeof flatpickr !== 'undefined') {
        fromPicker = flatpickr(els.from, { dateFormat: 'Y-m-d', allowInput: true });
        toPicker = flatpickr(els.to, { dateFormat: 'Y-m-d', allowInput: true });
    }

    const escapeHtml = value => $('<div>').text(value ?? '-').html();
    const duration = value => {
        const minutes = Number(value) || 0;
        if (minutes < 60) return `${decimal.format(minutes)} mnt`;
        if (minutes < 1440) return `${decimal.format(minutes / 60)} jam`;
        return `${decimal.format(minutes / 1440)} hari`;
    };
    const durationRenderer = (data, type) => type === 'display' ? (data === null ? '-' : duration(data)) : (Number(data) || 0);
    const statusRenderer = (data, type, row) => {
        if (type !== 'display') return data;
        const color = { completed: 'success', scanning: 'primary', pending_scan: 'warning' }[row.status] || 'secondary';
        return `<span class="badge badge-light-${color}">${escapeHtml(data)}</span>`;
    };
    const operatorRenderer = (data, type, row) => {
        if (type !== 'display') return `${row.creator} ${row.starter} ${row.completer}`;
        return `<div class="text-nowrap"><div><span class="text-muted">Buat:</span> ${escapeHtml(row.creator)}</div><div><span class="text-muted">Mulai:</span> ${escapeHtml(row.starter)}</div><div><span class="text-muted">Selesai:</span> ${escapeHtml(row.completer)}</div></div>`;
    };

    const detailTable = $('#detail_table').DataTable({
        data: [], searching: false, pageLength: 25, order: [[3, 'desc']], scrollX: true,
        columns: [
            { data: 'code', render: (data, type, row) => type === 'display' ? `<div class="fw-bold text-gray-900">${escapeHtml(data)}</div><div class="text-muted fs-8">${escapeHtml(row.ref_no)} · ${escapeHtml(row.warehouse)}</div>` : data },
            { data: 'type_label' }, { data: 'status_label', render: statusRenderer }, { data: 'created_at' },
            { data: 'started_at', defaultContent: '-' }, { data: 'completed_at', defaultContent: '-' },
            { data: 'waiting_minutes', className: 'text-end', render: durationRenderer },
            { data: 'scan_minutes', className: 'text-end', render: durationRenderer },
            { data: 'lead_minutes', className: 'text-end fw-semibold', render: durationRenderer },
            { data: 'aging_minutes', className: 'text-end', render: durationRenderer },
            { data: 'creator', render: operatorRenderer },
            { data: 'scanned_qty', className: 'text-end', render: (data, type, row) => type === 'display' ? `${number.format(data)} / ${number.format(row.expected_qty)}` : data },
            { data: 'qty_variance', className: 'text-end', render: (data, type) => type === 'display' ? `<span class="${Number(data) === 0 ? 'text-success' : 'text-danger fw-bold'}">${Number(data) > 0 ? '+' : ''}${number.format(data)}</span>` : data },
            { data: 'progress_percent', className: 'text-end', render: (data, type) => type === 'display' ? `${decimal.format(data)}%` : data },
        ],
        language: { emptyTable: 'Tidak ada dokumen inbound pada filter ini.' }
    });

    const filters = () => ({
        date_from: els.from.value || '', date_to: els.to.value || '', type: els.type.value || '',
        status: els.status.value || '', q: els.search.value.trim(),
    });
    const setText = (id, value) => { const element = document.getElementById(id); if (element) element.textContent = value; };
    const renderSummary = (summary = {}) => {
        setText('summary_total', number.format(summary.total_documents || 0));
        setText('summary_completed', number.format(summary.completed_documents || 0));
        setText('summary_rate', decimal.format(summary.completion_rate || 0));
        setText('summary_pending', number.format(summary.pending_documents || 0));
        setText('summary_scanning', number.format(summary.scanning_documents || 0));
        setText('summary_lead', duration(summary.avg_lead_minutes || 0));
        setText('summary_oldest', duration(summary.oldest_open_minutes || 0));
        setText('summary_waiting', duration(summary.avg_waiting_minutes || 0));
        setText('summary_scan', duration(summary.avg_scan_minutes || 0));
        setText('summary_max_lead', duration(summary.max_lead_minutes || 0));
        setText('summary_variance', number.format(summary.variance_documents || 0));
        setText('summary_reset', number.format(summary.reset_count || 0));
    };
    const emptyChart = (id, message = 'Tidak ada data pada filter ini.') => {
        document.getElementById(id).innerHTML = `<div class="d-flex align-items-center justify-content-center text-muted h-300px">${escapeHtml(message)}</div>`;
    };
    const renderCharts = (data = {}) => {
        charts.forEach(chart => chart.destroy()); charts = [];
        if (typeof ApexCharts === 'undefined') {
            emptyChart('chart_daily', 'Library grafik tidak tersedia.'); emptyChart('chart_status', 'Library grafik tidak tersedia.'); return;
        }
        const daily = Array.isArray(data.daily) ? data.daily : [];
        const statuses = Array.isArray(data.status) ? data.status : [];
        if (daily.length) {
            const chart = new ApexCharts(document.getElementById('chart_daily'), {
                series: [
                    { name: 'Dibuat', type: 'column', data: daily.map(row => row.created) },
                    { name: 'Completed', type: 'column', data: daily.map(row => row.completed) },
                    { name: 'Rata-rata Lead (jam)', type: 'line', data: daily.map(row => Number((row.avg_lead_minutes / 60).toFixed(2))) },
                ],
                chart: { height: 320, type: 'line', stacked: false, toolbar: { show: false } }, colors: ['#FFC700', '#50CD89', '#1B84FF'],
                stroke: { width: [0, 0, 3] }, plotOptions: { bar: { borderRadius: 3, columnWidth: '55%' } }, dataLabels: { enabled: false },
                xaxis: { categories: daily.map(row => row.date) }, yaxis: [{ title: { text: 'Dokumen' }, min: 0 }, { opposite: true, title: { text: 'Jam' }, min: 0 }],
                grid: { borderColor: '#E4E6EF', strokeDashArray: 4 },
            }); chart.render(); charts.push(chart);
        } else emptyChart('chart_daily');
        if (statuses.length) {
            const chart = new ApexCharts(document.getElementById('chart_status'), {
                series: statuses.map(row => row.total), labels: statuses.map(row => row.label), chart: { type: 'donut', height: 320 },
                colors: ['#50CD89', '#1B84FF', '#FFC700', '#7239EA'], legend: { position: 'bottom' }, dataLabels: { enabled: true },
                plotOptions: { pie: { donut: { size: '58%' } } },
            }); chart.render(); charts.push(chart);
        } else emptyChart('chart_status');
    };

    const load = async () => {
        els.alert.classList.add('d-none');
        const button = document.getElementById('filter_apply'); button.disabled = true; button.setAttribute('data-kt-indicator', 'on');
        try {
            const response = await fetch(`${dataUrl}?${new URLSearchParams(filters())}`, { headers: { Accept: 'application/json' } });
            const json = await response.json();
            if (!response.ok) throw new Error(json?.message || 'Gagal memuat laporan lead time inbound.');
            renderSummary(json.summary); renderCharts(json.charts); detailTable.clear().rows.add(json.details || []).draw();
        } catch (error) {
            els.alert.textContent = error.message || 'Gagal memuat laporan lead time inbound.'; els.alert.classList.remove('d-none');
        } finally {
            button.disabled = false; button.removeAttribute('data-kt-indicator');
        }
    };

    document.getElementById('filter_apply').addEventListener('click', load);
    document.getElementById('filter_reset').addEventListener('click', () => {
        if (fromPicker) fromPicker.setDate(today); else els.from.value = today;
        if (toPicker) toPicker.setDate(today); else els.to.value = today;
        els.type.value = ''; els.status.value = ''; els.search.value = ''; load();
    });
    document.getElementById('export_excel').addEventListener('click', () => { window.location.href = `${exportUrl}?${new URLSearchParams(filters())}`; });
    els.type.addEventListener('change', load); els.status.addEventListener('change', load);
    els.search.addEventListener('keyup', event => { if (event.key === 'Enter') load(); });
    load();
});
</script>
@endpush
