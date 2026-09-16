@extends('layouts.admin')

@section('title', 'Laporan QC Scan')
@section('page_title', 'Laporan QC Scan')

@section('content')
<div class="card mb-6">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div>
                <div class="fs-3 fw-bold text-gray-900">Produktivitas QC per Jam</div>
                <div class="text-muted fs-7">Perhitungan berdasarkan waktu resi dinyatakan lolos QC.</div>
            </div>
        </div>
        <div class="card-toolbar">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <input type="text" class="form-control form-control-solid w-150px" id="filter_date_from" placeholder="Dari" value="{{ $today }}">
                <input type="text" class="form-control form-control-solid w-150px" id="filter_date_to" placeholder="Sampai" value="{{ $today }}">
                <select class="form-select form-select-solid w-200px" id="filter_operator">
                    <option value="">Semua Petugas</option>
                    @foreach($operators as $operator)
                        <option value="{{ $operator->id }}">{{ $operator->name }}</option>
                    @endforeach
                </select>
                <input type="text" class="form-control form-control-solid w-225px" id="filter_search" placeholder="Resi / pesanan / ekspedisi">
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
        <div class="card card-flush h-100 border border-gray-200">
            <div class="card-body">
                <div class="text-muted fw-semibold">Resi Lolos QC</div>
                <div class="fs-2hx fw-bold text-primary" id="summary_total_resi">0</div>
                <div class="fs-7 text-muted"><span id="summary_total_operator">0</span> petugas aktif</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card card-flush h-100 border border-gray-200">
            <div class="card-body">
                <div class="text-muted fw-semibold">Rata-rata Resi / Jam</div>
                <div class="fs-2hx fw-bold text-success" id="summary_avg_hour">0</div>
                <div class="fs-7 text-muted"><span id="summary_active_hours">0</span> jam-petugas aktif</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card card-flush h-100 border border-gray-200">
            <div class="card-body">
                <div class="text-muted fw-semibold">Rata-rata Durasi QC</div>
                <div class="fs-2hx fw-bold text-info"><span id="summary_duration">0</span><span class="fs-5 ms-1">menit</span></div>
                <div class="fs-7 text-muted">Dari mulai hingga selesai QC</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card card-flush h-100 border border-gray-200">
            <div class="card-body">
                <div class="text-muted fw-semibold">Jam Puncak</div>
                <div class="fs-2 fw-bold text-warning" id="summary_peak_hour">-</div>
                <div class="fs-7 text-muted"><span id="summary_peak_total">0</span> resi pada kelompok tertinggi</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-6">
    @foreach([
        ['id' => 'summary_total_qty', 'label' => 'Qty Discan', 'icon' => 'fa-boxes', 'class' => 'primary'],
        ['id' => 'summary_reset', 'label' => 'Reset QC', 'icon' => 'fa-redo', 'class' => 'warning'],
        ['id' => 'summary_substitution', 'label' => 'Substitusi SKU', 'icon' => 'fa-random', 'class' => 'info'],
        ['id' => 'summary_duplicate', 'label' => 'Double Scan', 'icon' => 'fa-copy', 'class' => 'danger'],
        ['id' => 'summary_hold', 'label' => 'Aktivitas Hold', 'icon' => 'fa-pause-circle', 'class' => 'danger'],
    ] as $metric)
        <div class="col-6 col-md">
            <div class="bg-light-{{ $metric['class'] }} rounded-3 p-4 h-100">
                <div class="d-flex align-items-center gap-3">
                    <i class="fas {{ $metric['icon'] }} text-{{ $metric['class'] }} fs-2"></i>
                    <div>
                        <div class="text-muted fs-7">{{ $metric['label'] }}</div>
                        <div class="fs-3 fw-bold" id="{{ $metric['id'] }}">0</div>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="row g-6 mb-6">
    <div class="col-xl-7">
        <div class="card card-flush h-100">
            <div class="card-header"><h3 class="card-title">Sebaran Resi Selesai per Jam</h3></div>
            <div class="card-body pt-0"><div id="chart_hourly" style="min-height:320px"></div></div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="card card-flush h-100">
            <div class="card-header"><h3 class="card-title">Kontribusi per Petugas</h3></div>
            <div class="card-body pt-0"><div id="chart_operator" style="min-height:320px"></div></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <ul class="nav nav-tabs nav-line-tabs nav-line-tabs-2x fs-6 fw-bold border-0">
                <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab_hourly">Per Jam</a></li>
                <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab_operator">Per Petugas</a></li>
                <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab_detail">Detail Resi</a></li>
            </ul>
        </div>
    </div>
    <div class="card-body pt-3">
        <div class="tab-content">
            <div class="tab-pane fade show active" id="tab_hourly">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-4" id="hourly_table">
                        <thead><tr class="text-gray-500 fw-bold fs-7 text-uppercase"><th>Tanggal</th><th>Jam</th><th>Petugas</th><th class="text-end">Resi</th><th class="text-end">SKU</th><th class="text-end">Qty</th><th class="text-end">Durasi</th><th class="text-end">Reset</th><th class="text-end">Substitusi</th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            <div class="tab-pane fade" id="tab_operator">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-4" id="operator_table">
                        <thead><tr class="text-gray-500 fw-bold fs-7 text-uppercase"><th>Petugas</th><th class="text-end">Resi QC</th><th class="text-end">Jam Aktif</th><th class="text-end">Resi/Jam</th><th class="text-end">Qty</th><th class="text-end">Durasi</th><th class="text-end">Reset</th><th class="text-end">Substitusi</th><th>Aktivitas Terakhir</th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            <div class="tab-pane fade" id="tab_detail">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-4" id="detail_table">
                        <thead><tr class="text-gray-500 fw-bold fs-7 text-uppercase"><th>Selesai</th><th>Petugas</th><th>ID Pesanan</th><th>No. Resi</th><th>Ekspedisi</th><th class="text-end">SKU</th><th class="text-end">Qty</th><th class="text-end">Durasi</th><th class="text-end">Reset</th><th class="text-end">Substitusi</th><th class="text-end">Double Scan</th></tr></thead>
                        <tbody></tbody>
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
    const today = @json($today);
    const number = new Intl.NumberFormat('id-ID');
    const decimal = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });
    const els = {
        from: document.getElementById('filter_date_from'),
        to: document.getElementById('filter_date_to'),
        operator: document.getElementById('filter_operator'),
        search: document.getElementById('filter_search'),
        alert: document.getElementById('report_alert'),
    };
    let charts = [];
    let fromPicker = null;
    let toPicker = null;

    if (typeof flatpickr !== 'undefined') {
        fromPicker = flatpickr(els.from, { dateFormat: 'Y-m-d', allowInput: true });
        toPicker = flatpickr(els.to, { dateFormat: 'Y-m-d', allowInput: true });
    }

    const escapeHtml = (value) => $('<div>').text(value ?? '-').html();
    const textRenderer = (data, type) => type === 'display' ? escapeHtml(data) : (data ?? '');
    const durationRenderer = (data, type) => type === 'display' ? `${decimal.format(Number(data) || 0)} mnt` : (Number(data) || 0);

    const hourlyTable = $('#hourly_table').DataTable({
        data: [], searching: false, pageLength: 25, order: [[0, 'desc'], [1, 'desc']],
        columns: [
            { data: 'date', render: textRenderer }, { data: 'hour_label', render: textRenderer }, { data: 'operator', render: textRenderer },
            { data: 'total_resi', className: 'text-end' }, { data: 'total_sku', className: 'text-end' }, { data: 'total_qty', className: 'text-end' },
            { data: 'avg_duration_minutes', className: 'text-end', render: durationRenderer }, { data: 'reset_count', className: 'text-end' },
            { data: 'substitution_count', className: 'text-end' },
        ],
        language: { emptyTable: 'Tidak ada QC selesai pada filter ini.' }
    });
    const operatorTable = $('#operator_table').DataTable({
        data: [], searching: false, pageLength: 25, order: [[1, 'desc']],
        columns: [
            { data: 'operator', render: textRenderer }, { data: 'total_resi', className: 'text-end' }, { data: 'active_hours', className: 'text-end' },
            { data: 'avg_resi_per_hour', className: 'text-end' }, { data: 'total_qty', className: 'text-end' },
            { data: 'avg_duration_minutes', className: 'text-end', render: durationRenderer }, { data: 'reset_count', className: 'text-end' },
            { data: 'substitution_count', className: 'text-end' }, { data: 'last_completion', render: textRenderer },
        ],
        language: { emptyTable: 'Tidak ada data petugas pada filter ini.' }
    });
    const detailTable = $('#detail_table').DataTable({
        data: [], searching: false, pageLength: 25, order: [[0, 'desc']],
        columns: [
            { data: 'completed_at', render: textRenderer }, { data: 'operator', render: textRenderer }, { data: 'id_pesanan', render: textRenderer },
            { data: 'no_resi', render: textRenderer }, { data: 'expedition', render: textRenderer }, { data: 'total_sku', className: 'text-end' },
            { data: 'total_qty', className: 'text-end' }, { data: 'duration_minutes', className: 'text-end', render: durationRenderer },
            { data: 'reset_count', className: 'text-end' }, { data: 'substitution_count', className: 'text-end' },
            { data: 'duplicate_attempts', className: 'text-end' },
        ],
        language: { emptyTable: 'Tidak ada detail QC pada filter ini.' }
    });

    const filters = () => ({
        date_from: els.from.value || '', date_to: els.to.value || '', operator_id: els.operator.value || '', q: els.search.value.trim(),
    });
    const setText = (id, value) => { const el = document.getElementById(id); if (el) el.textContent = value; };

    const renderSummary = (summary = {}) => {
        setText('summary_total_resi', number.format(summary.total_resi || 0));
        setText('summary_total_operator', number.format(summary.total_operators || 0));
        setText('summary_avg_hour', decimal.format(summary.avg_resi_per_hour || 0));
        setText('summary_active_hours', number.format(summary.active_operator_hours || 0));
        setText('summary_duration', decimal.format(summary.avg_duration_minutes || 0));
        setText('summary_peak_hour', summary.peak_hour || '-');
        setText('summary_peak_total', number.format(summary.peak_hour_resi || 0));
        setText('summary_total_qty', number.format(summary.total_qty || 0));
        setText('summary_reset', number.format(summary.reset_count || 0));
        setText('summary_substitution', number.format(summary.substitution_count || 0));
        setText('summary_duplicate', number.format(summary.duplicate_attempts || 0));
        setText('summary_hold', number.format(summary.hold_events || 0));
    };

    const emptyChart = (id, message = 'Tidak ada data pada filter ini.') => {
        document.getElementById(id).innerHTML = `<div class="d-flex align-items-center justify-content-center text-muted h-300px">${escapeHtml(message)}</div>`;
    };
    const renderCharts = (data = {}) => {
        charts.forEach(chart => chart.destroy());
        charts = [];
        if (typeof ApexCharts === 'undefined') {
            emptyChart('chart_hourly', 'Library grafik tidak tersedia.');
            emptyChart('chart_operator', 'Library grafik tidak tersedia.');
            return;
        }
        const hours = Array.isArray(data.hours) ? data.hours : [];
        const operators = Array.isArray(data.operators) ? data.operators : [];
        if (hours.some(row => Number(row.total) > 0)) {
            const chart = new ApexCharts(document.getElementById('chart_hourly'), {
                series: [{ name: 'Resi QC', data: hours.map(row => row.total) }], chart: { type: 'bar', height: 320, toolbar: { show: false } },
                colors: ['#1B84FF'], plotOptions: { bar: { borderRadius: 4, columnWidth: '55%' } }, dataLabels: { enabled: false },
                xaxis: { categories: hours.map(row => row.label) }, yaxis: { min: 0, forceNiceScale: true }, grid: { borderColor: '#E4E6EF', strokeDashArray: 4 },
            });
            chart.render(); charts.push(chart);
        } else emptyChart('chart_hourly');
        if (operators.length) {
            const chart = new ApexCharts(document.getElementById('chart_operator'), {
                series: [{ name: 'Resi QC', data: operators.map(row => row.total) }], chart: { type: 'bar', height: 320, toolbar: { show: false } },
                colors: ['#50CD89'], plotOptions: { bar: { horizontal: true, borderRadius: 4 } }, dataLabels: { enabled: true },
                xaxis: { categories: operators.map(row => row.operator) }, grid: { borderColor: '#E4E6EF', strokeDashArray: 4 },
            });
            chart.render(); charts.push(chart);
        } else emptyChart('chart_operator');
    };

    const load = async () => {
        els.alert.classList.add('d-none');
        const apply = document.getElementById('filter_apply');
        apply.disabled = true;
        apply.setAttribute('data-kt-indicator', 'on');
        try {
            const response = await fetch(`${dataUrl}?${new URLSearchParams(filters())}`, { headers: { Accept: 'application/json' } });
            const json = await response.json();
            if (!response.ok) throw new Error(json?.message || 'Gagal memuat laporan QC.');
            renderSummary(json.summary);
            renderCharts(json.charts);
            hourlyTable.clear().rows.add(json.hourly || []).draw();
            operatorTable.clear().rows.add(json.operators || []).draw();
            detailTable.clear().rows.add(json.details || []).draw();
        } catch (error) {
            els.alert.textContent = error.message || 'Gagal memuat laporan QC.';
            els.alert.classList.remove('d-none');
        } finally {
            apply.disabled = false;
            apply.removeAttribute('data-kt-indicator');
        }
    };

    document.getElementById('filter_apply').addEventListener('click', load);
    document.getElementById('filter_reset').addEventListener('click', () => {
        if (fromPicker) fromPicker.setDate(today); else els.from.value = today;
        if (toPicker) toPicker.setDate(today); else els.to.value = today;
        els.operator.value = ''; els.search.value = ''; load();
    });
    document.getElementById('export_excel').addEventListener('click', () => {
        window.location.href = `${exportUrl}?${new URLSearchParams(filters())}`;
    });
    els.operator.addEventListener('change', load);
    els.search.addEventListener('keyup', event => { if (event.key === 'Enter') load(); });
    load();
});
</script>
@endpush
