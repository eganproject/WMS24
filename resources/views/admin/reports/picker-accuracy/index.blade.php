@extends('layouts.admin')

@section('title', 'Laporan Akurasi Picker')
@section('page_title', 'Laporan Akurasi Picker')

@section('content')
<div class="card mb-6">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div>
                <div class="fs-3 fw-bold text-gray-900">Akurasi Picking per Picker</div>
                <div class="text-muted fs-7">Berdasarkan picker yang dipilih saat QC resi. Periode mengikuti waktu mulai QC.</div>
            </div>
        </div>
        <div class="card-toolbar">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <input type="text" class="form-control form-control-solid w-150px" id="filter_date_from" placeholder="Dari" value="{{ $monthStart }}">
                <input type="text" class="form-control form-control-solid w-150px" id="filter_date_to" placeholder="Sampai" value="{{ $today }}">
                <select class="form-select form-select-solid w-200px" id="filter_picker">
                    <option value="">Semua Picker</option>
                    @foreach($pickers as $picker)
                        <option value="{{ $picker->id }}">{{ $picker->employee_code ? $picker->employee_code.' - ' : '' }}{{ $picker->name }}</option>
                    @endforeach
                </select>
                <input type="text" class="form-control form-control-solid w-225px" id="filter_search" placeholder="Resi / pesanan / picker">
                <button type="button" class="btn btn-primary" id="filter_apply"><i class="fas fa-filter me-1"></i>Terapkan</button>
                <button type="button" class="btn btn-light" id="filter_reset">Reset</button>
                <button type="button" class="btn btn-success" id="export_excel"><i class="fas fa-file-excel me-1"></i>Export Excel</button>
            </div>
        </div>
    </div>
    <div class="card-body pt-0">
        <div class="notice bg-light-primary rounded border border-primary border-dashed p-4 fs-7 text-gray-700">
            <strong>Cara baca:</strong> resi dihitung <em>error</em> bila QC menemukan salah ambil SKU, qty berlebih, atau hold/reset dengan kategori kesalahan picker,
            atau bila resi kembali sebagai retur dengan root cause <em>salah barang</em> / <em>barang kurang</em>.
            Barcode tidak dikenal dan substitusi ditampilkan terpisah dan tidak mengurangi akurasi.
        </div>
    </div>
</div>

<div id="report_alert" class="alert alert-danger d-none mb-6"></div>

<div class="row g-4 mb-6">
    <div class="col-sm-6 col-xl-3">
        <div class="card card-flush h-100 border border-gray-200">
            <div class="card-body">
                <div class="text-muted fw-semibold">Akurasi Picking</div>
                <div class="fs-2hx fw-bold text-success"><span id="summary_accuracy">0</span><span class="fs-3">%</span></div>
                <div class="fs-7 text-muted"><span id="summary_accurate_resi">0</span> dari <span id="summary_total_resi">0</span> resi tanpa error</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card card-flush h-100 border border-gray-200">
            <div class="card-body">
                <div class="text-muted fw-semibold">First Pass Rate QC</div>
                <div class="fs-2hx fw-bold text-primary"><span id="summary_first_pass">0</span><span class="fs-3">%</span></div>
                <div class="fs-7 text-muted">Lolos QC tanpa salah scan, hold, atau reset</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card card-flush h-100 border border-gray-200">
            <div class="card-body">
                <div class="text-muted fw-semibold">Error Tertangkap QC</div>
                <div class="fs-2hx fw-bold text-warning" id="summary_caught">0</div>
                <div class="fs-7 text-muted">Resi dengan kesalahan picking yang dicegah QC</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card card-flush h-100 border border-gray-200">
            <div class="card-body">
                <div class="text-muted fw-semibold">Error Lolos ke Pembeli</div>
                <div class="fs-2hx fw-bold text-danger" id="summary_escaped">0</div>
                <div class="fs-7 text-muted">Resi yang kembali sebagai retur salah barang / kurang</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-6">
    @foreach([
        ['id' => 'summary_wrong_sku', 'label' => 'Salah Ambil SKU', 'icon' => 'fa-exchange-alt', 'class' => 'danger'],
        ['id' => 'summary_over_qty', 'label' => 'Qty Berlebih', 'icon' => 'fa-plus-circle', 'class' => 'warning'],
        ['id' => 'summary_picker_fault', 'label' => 'Hold/Reset Picker', 'icon' => 'fa-pause-circle', 'class' => 'warning'],
        ['id' => 'summary_substitution', 'label' => 'Substitusi SKU', 'icon' => 'fa-random', 'class' => 'info'],
        ['id' => 'summary_unknown_barcode', 'label' => 'Barcode Tak Dikenal', 'icon' => 'fa-barcode', 'class' => 'primary'],
        ['id' => 'summary_unattributed', 'label' => 'Resi Tanpa Picker', 'icon' => 'fa-user-slash', 'class' => 'dark'],
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
    <div class="col-xl-5">
        <div class="card card-flush h-100">
            <div class="card-header"><h3 class="card-title">Akurasi per Picker</h3></div>
            <div class="card-body pt-0"><div id="chart_picker" style="min-height:320px"></div></div>
        </div>
    </div>
    <div class="col-xl-7">
        <div class="card card-flush h-100">
            <div class="card-header"><h3 class="card-title">Tren Harian</h3></div>
            <div class="card-body pt-0"><div id="chart_daily" style="min-height:320px"></div></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <ul class="nav nav-tabs nav-line-tabs nav-line-tabs-2x fs-6 fw-bold border-0">
                <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab_picker">Per Picker</a></li>
                <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab_sku_pair">SKU Tertukar</a></li>
                <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab_reason">Kategori Alasan</a></li>
                <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab_event">Detail Kejadian</a></li>
            </ul>
        </div>
    </div>
    <div class="card-body pt-3">
        <div class="tab-content">
            <div class="tab-pane fade show active" id="tab_picker">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-4" id="picker_table">
                        <thead><tr class="text-gray-500 fw-bold fs-7 text-uppercase"><th>Picker</th><th class="text-end">Resi</th><th class="text-end">Qty</th><th class="text-end">Akurasi</th><th class="text-end">First Pass</th><th class="text-end">Error QC</th><th class="text-end">Error Lolos</th><th class="text-end">Salah SKU</th><th class="text-end">Qty Lebih</th><th class="text-end">Hold/Reset Picker</th><th>Aktivitas Terakhir</th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            <div class="tab-pane fade" id="tab_sku_pair">
                <div class="text-muted fs-7 mb-3">Pasangan SKU yang seharusnya diambil vs yang terambil. Pasangan yang sering muncul biasanya karena kemasan mirip atau lokasi berdekatan.</div>
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-4" id="sku_pair_table">
                        <thead><tr class="text-gray-500 fw-bold fs-7 text-uppercase"><th>SKU Seharusnya</th><th>Lokasi</th><th>SKU Terambil</th><th>Lokasi</th><th class="text-end">Kejadian</th><th class="text-end">Picker</th><th>Terakhir</th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            <div class="tab-pane fade" id="tab_reason">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-4" id="reason_table">
                        <thead><tr class="text-gray-500 fw-bold fs-7 text-uppercase"><th>Aksi</th><th>Kategori</th><th>Kesalahan Picker</th><th class="text-end">Kejadian</th><th class="text-end">Picker</th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            <div class="tab-pane fade" id="tab_event">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-4" id="event_table">
                        <thead><tr class="text-gray-500 fw-bold fs-7 text-uppercase"><th>Waktu</th><th>Jenis</th><th>Picker</th><th>No. Resi</th><th>SKU Discan</th><th>SKU Seharusnya</th><th class="text-end">Qty</th><th>Alasan</th><th>Operator QC</th></tr></thead>
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
    const monthStart = @json($monthStart);
    const number = new Intl.NumberFormat('id-ID');
    const decimal = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });
    const els = {
        from: document.getElementById('filter_date_from'),
        to: document.getElementById('filter_date_to'),
        picker: document.getElementById('filter_picker'),
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
    const textRenderer = (data, type) => type === 'display' ? escapeHtml(data || '-') : (data ?? '');
    const percentRenderer = (data, type) => {
        const value = Number(data) || 0;
        if (type !== 'display') return value;
        const tone = value >= 99 ? 'success' : (value >= 97 ? 'warning' : 'danger');
        return `<span class="badge badge-light-${tone} fs-7">${decimal.format(value)}%</span>`;
    };
    const countRenderer = (tone) => (data, type) => {
        const value = Number(data) || 0;
        if (type !== 'display') return value;
        return value > 0 ? `<span class="fw-bold text-${tone}">${number.format(value)}</span>` : '<span class="text-muted">0</span>';
    };
    const skuRenderer = (skuKey, nameKey) => (data, type, row) => {
        if (type !== 'display') return row[skuKey] || '';
        const name = row[nameKey] ? `<div class="text-muted fs-8">${escapeHtml(row[nameKey])}</div>` : '';
        return `<div class="fw-bold">${escapeHtml(row[skuKey] || '-')}</div>${name}`;
    };
    const faultRenderer = (data, type) => type === 'display'
        ? (data ? '<span class="badge badge-light-danger">Ya</span>' : '<span class="badge badge-light-secondary">Tidak</span>')
        : (data ? 1 : 0);

    const pickerTable = $('#picker_table').DataTable({
        data: [], searching: false, pageLength: 25, order: [[3, 'asc'], [1, 'desc']],
        columns: [
            { data: 'picker', render: (data, type, row) => type === 'display'
                ? `<div class="fw-bold">${escapeHtml(row.picker)}</div><div class="text-muted fs-8">${escapeHtml(row.picker_code || '-')}</div>`
                : row.picker },
            { data: 'total_resi', className: 'text-end' }, { data: 'total_qty', className: 'text-end' },
            { data: 'accuracy_rate', className: 'text-end', render: percentRenderer },
            { data: 'first_pass_rate', className: 'text-end', render: percentRenderer },
            { data: 'caught_error_resi', className: 'text-end', render: countRenderer('warning') },
            { data: 'escaped_error_resi', className: 'text-end', render: countRenderer('danger') },
            { data: 'wrong_sku', className: 'text-end', render: countRenderer('danger') },
            { data: 'over_qty', className: 'text-end', render: countRenderer('warning') },
            { data: 'picker_fault', className: 'text-end', render: countRenderer('warning') },
            { data: 'last_activity', render: textRenderer },
        ],
        language: { emptyTable: 'Tidak ada resi dengan atribusi picker pada filter ini.' }
    });
    const skuPairTable = $('#sku_pair_table').DataTable({
        data: [], searching: false, pageLength: 25, order: [[4, 'desc']],
        columns: [
            { data: 'expected_sku', render: skuRenderer('expected_sku', 'expected_sku_name') },
            { data: 'expected_sku_location', render: textRenderer },
            { data: 'picked_sku', render: skuRenderer('picked_sku', 'picked_sku_name') },
            { data: 'picked_sku_location', render: textRenderer },
            { data: 'occurrences', className: 'text-end', render: countRenderer('danger') },
            { data: 'pickers', className: 'text-end' },
            { data: 'last_occurred_at', render: textRenderer },
        ],
        language: { emptyTable: 'Tidak ada kejadian salah ambil SKU pada filter ini.' }
    });
    const reasonTable = $('#reason_table').DataTable({
        data: [], searching: false, pageLength: 25, order: [[3, 'desc']],
        columns: [
            { data: 'event_label', render: textRenderer }, { data: 'reason_label', render: textRenderer },
            { data: 'is_picker_fault', render: faultRenderer },
            { data: 'occurrences', className: 'text-end' }, { data: 'pickers', className: 'text-end' },
        ],
        language: { emptyTable: 'Tidak ada hold, reset, atau substitusi pada filter ini.' }
    });
    const eventTable = $('#event_table').DataTable({
        data: [], searching: false, pageLength: 25, order: [[0, 'desc']],
        columns: [
            { data: 'occurred_at', render: textRenderer },
            { data: 'event_label', render: (data, type, row) => type === 'display'
                ? `<span class="badge badge-light-${row.is_picker_fault ? 'danger' : 'secondary'}">${escapeHtml(row.event_label)}</span>`
                : row.event_label },
            { data: 'picker', render: textRenderer }, { data: 'no_resi', render: textRenderer },
            { data: 'sku', render: (data, type, row) => type === 'display'
                ? (row.sku ? skuRenderer('sku', 'sku_name')(data, type, row) : `<span class="text-muted">${escapeHtml(row.scan_code || '-')}</span>`)
                : (row.sku || row.scan_code) },
            { data: 'expected_sku', render: skuRenderer('expected_sku', 'expected_sku_name') },
            { data: 'qty', className: 'text-end', render: (data) => data === null ? '-' : number.format(data) },
            { data: 'reason', render: textRenderer }, { data: 'qc_operator', render: textRenderer },
        ],
        language: { emptyTable: 'Tidak ada kejadian pada filter ini.' }
    });

    const filters = () => ({
        date_from: els.from.value || '', date_to: els.to.value || '', picker_id: els.picker.value || '', q: els.search.value.trim(),
    });
    const setText = (id, value) => { const el = document.getElementById(id); if (el) el.textContent = value; };

    const renderSummary = (summary = {}) => {
        setText('summary_accuracy', decimal.format(summary.accuracy_rate || 0));
        setText('summary_accurate_resi', number.format(summary.accurate_resi || 0));
        setText('summary_total_resi', number.format(summary.total_resi || 0));
        setText('summary_first_pass', decimal.format(summary.first_pass_rate || 0));
        setText('summary_caught', number.format(summary.caught_error_resi || 0));
        setText('summary_escaped', number.format(summary.escaped_error_resi || 0));
        setText('summary_wrong_sku', number.format(summary.wrong_sku_events || 0));
        setText('summary_over_qty', number.format(summary.over_qty_events || 0));
        setText('summary_picker_fault', number.format(summary.picker_fault_events || 0));
        setText('summary_substitution', number.format(summary.substitution_events || 0));
        setText('summary_unknown_barcode', number.format(summary.unknown_barcode_events || 0));
        setText('summary_unattributed', number.format(summary.unattributed_resi || 0));
    };

    const emptyChart = (id, message = 'Tidak ada data pada filter ini.') => {
        document.getElementById(id).innerHTML = `<div class="d-flex align-items-center justify-content-center text-muted h-300px">${escapeHtml(message)}</div>`;
    };
    const renderCharts = (data = {}) => {
        charts.forEach(chart => chart.destroy());
        charts = [];
        if (typeof ApexCharts === 'undefined') {
            emptyChart('chart_picker', 'Library grafik tidak tersedia.');
            emptyChart('chart_daily', 'Library grafik tidak tersedia.');
            return;
        }
        const pickers = Array.isArray(data.pickers) ? data.pickers : [];
        const daily = Array.isArray(data.daily) ? data.daily : [];
        if (pickers.length) {
            const chart = new ApexCharts(document.getElementById('chart_picker'), {
                series: [{ name: 'Akurasi (%)', data: pickers.map(row => row.accuracy_rate) }],
                chart: { type: 'bar', height: Math.max(320, pickers.length * 34), toolbar: { show: false } },
                colors: [({ value }) => value >= 99 ? '#50CD89' : (value >= 97 ? '#FFC700' : '#F1416C')],
                plotOptions: { bar: { horizontal: true, borderRadius: 4 } },
                dataLabels: { enabled: true, formatter: (value) => `${decimal.format(value)}%` },
                xaxis: { categories: pickers.map(row => row.picker), max: 100, labels: { formatter: (value) => `${value}%` } },
                tooltip: { y: { formatter: (value, { dataPointIndex }) => `${decimal.format(value)}% (${pickers[dataPointIndex].error_resi} resi error)` } },
                grid: { borderColor: '#E4E6EF', strokeDashArray: 4 },
            });
            chart.render(); charts.push(chart);
        } else emptyChart('chart_picker');
        if (daily.length) {
            const chart = new ApexCharts(document.getElementById('chart_daily'), {
                series: [
                    { name: 'Resi error', type: 'column', data: daily.map(row => row.error_resi) },
                    { name: 'Akurasi (%)', type: 'line', data: daily.map(row => row.accuracy_rate) },
                ],
                chart: { height: 320, type: 'line', toolbar: { show: false } },
                colors: ['#F1416C', '#1B84FF'], stroke: { width: [0, 3] }, dataLabels: { enabled: false },
                xaxis: { categories: daily.map(row => row.date) },
                yaxis: [
                    { title: { text: 'Resi error' }, min: 0, forceNiceScale: true },
                    { opposite: true, title: { text: 'Akurasi (%)' }, max: 100, labels: { formatter: (value) => `${Math.round(value)}%` } },
                ],
                grid: { borderColor: '#E4E6EF', strokeDashArray: 4 },
            });
            chart.render(); charts.push(chart);
        } else emptyChart('chart_daily');
    };

    const load = async () => {
        els.alert.classList.add('d-none');
        const apply = document.getElementById('filter_apply');
        apply.disabled = true;
        apply.setAttribute('data-kt-indicator', 'on');
        try {
            const response = await fetch(`${dataUrl}?${new URLSearchParams(filters())}`, { headers: { Accept: 'application/json' } });
            const json = await response.json();
            if (!response.ok) throw new Error(json?.message || 'Gagal memuat laporan akurasi picker.');
            renderSummary(json.summary);
            renderCharts(json.charts);
            pickerTable.clear().rows.add(json.pickers || []).draw();
            skuPairTable.clear().rows.add(json.sku_pairs || []).draw();
            reasonTable.clear().rows.add(json.reasons || []).draw();
            eventTable.clear().rows.add(json.events || []).draw();
        } catch (error) {
            els.alert.textContent = error.message || 'Gagal memuat laporan akurasi picker.';
            els.alert.classList.remove('d-none');
        } finally {
            apply.disabled = false;
            apply.removeAttribute('data-kt-indicator');
        }
    };

    document.getElementById('filter_apply').addEventListener('click', load);
    document.getElementById('filter_reset').addEventListener('click', () => {
        if (fromPicker) fromPicker.setDate(monthStart); else els.from.value = monthStart;
        if (toPicker) toPicker.setDate(today); else els.to.value = today;
        els.picker.value = ''; els.search.value = ''; load();
    });
    document.getElementById('export_excel').addEventListener('click', () => {
        window.location.href = `${exportUrl}?${new URLSearchParams(filters())}`;
    });
    els.picker.addEventListener('change', load);
    els.search.addEventListener('keyup', event => { if (event.key === 'Enter') load(); });
    load();
});
</script>
@endpush
