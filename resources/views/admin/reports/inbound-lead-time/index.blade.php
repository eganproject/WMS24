@extends('layouts.admin')

@section('title', 'Lead Time Operasional')
@section('page_title', 'Lead Time Operasional')

@section('content')
<div class="card mb-6">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div>
                <div class="fs-3 fw-bold text-gray-900">Analisis Lead Time per Role dan Jabatan</div>
                <div class="text-muted fs-7">Periode mengikuti waktu mulai tiap proses. Lead time hanya dihitung untuk proses yang sudah mencapai milestone akhir.</div>
            </div>
        </div>
        <div class="card-toolbar">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <input type="text" class="form-control form-control-solid w-145px" id="filter_date_from" value="{{ $dateFrom }}" placeholder="Dari">
                <input type="text" class="form-control form-control-solid w-145px" id="filter_date_to" value="{{ $dateTo }}" placeholder="Sampai">
                <select class="form-select form-select-solid w-180px" id="filter_role">
                    <option value="">Semua Role</option>
                    <option value="picker">Picker</option>
                    <option value="packer">Packer</option>
                    <option value="inbound">Inbound</option>
                    <option value="customer_return">Retur Customer</option>
                </select>
                <select class="form-select form-select-solid w-170px" id="filter_status">
                    <option value="">Semua Status</option>
                    <option value="completed">Selesai</option>
                    <option value="open">Belum Selesai</option>
                </select>
                <input type="text" class="form-control form-control-solid w-225px" id="filter_search" placeholder="Dokumen / PIC / jabatan">
                <button type="button" class="btn btn-primary" id="filter_apply"><i class="fas fa-filter me-1"></i>Terapkan</button>
                <button type="button" class="btn btn-light" id="filter_reset">Reset</button>
                <button type="button" class="btn btn-success" id="export_excel"><i class="fas fa-file-excel me-1"></i>Export Excel</button>
            </div>
        </div>
    </div>
</div>

<div class="alert alert-primary d-flex align-items-start mb-6">
    <i class="fas fa-info-circle fs-2 me-3 mt-1"></i>
    <div class="row g-2 flex-grow-1 fs-7">
        <div class="col-md-6 col-xl-3"><span class="fw-bold">Picker:</span> input resi → QC selesai</div>
        <div class="col-md-6 col-xl-3"><span class="fw-bold">Packer:</span> QC selesai → scan out selesai</div>
        <div class="col-md-6 col-xl-3"><span class="fw-bold">Inbound:</span> input receipt → completion</div>
        <div class="col-md-6 col-xl-3"><span class="fw-bold">Retur Customer:</span> input retur → finalisasi</div>
    </div>
</div>
<div id="report_alert" class="alert alert-danger d-none mb-6"></div>

<div class="row g-4 mb-6">
    <div class="col-sm-6 col-xl-3"><div class="card card-flush h-100 border border-gray-200"><div class="card-body">
        <div class="text-muted fw-semibold">Total Proses</div><div class="fs-2hx fw-bold text-primary" id="summary_total">0</div>
        <div class="fs-7 text-muted"><span id="summary_completed">0</span> selesai · <span id="summary_open">0</span> terbuka</div>
    </div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card card-flush h-100 border border-gray-200"><div class="card-body">
        <div class="text-muted fw-semibold">Completion Rate</div><div class="fs-2hx fw-bold text-success"><span id="summary_rate">0</span><span class="fs-5 ms-1">%</span></div>
        <div class="fs-7 text-muted">Proporsi proses yang mencapai milestone akhir</div>
    </div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card card-flush h-100 border border-gray-200"><div class="card-body">
        <div class="text-muted fw-semibold">Rata-rata Lead Time</div><div class="fs-2hx fw-bold text-info" id="summary_avg">0 menit</div>
        <div class="fs-7 text-muted">Median <span id="summary_median">0 menit</span> · P90 <span id="summary_p90">0 menit</span></div>
    </div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card card-flush h-100 border border-gray-200"><div class="card-body">
        <div class="text-muted fw-semibold">Backlog Terlama</div><div class="fs-2hx fw-bold text-warning" id="summary_oldest">0 menit</div>
        <div class="fs-7 text-muted"><span id="summary_missing_position">0</span> proses belum terhubung ke jabatan</div>
    </div></div></div>
</div>

<div class="row g-4 mb-6" id="role_cards"></div>

<div class="row g-6 mb-6">
    <div class="col-xl-8"><div class="card card-flush h-100">
        <div class="card-header"><h3 class="card-title">Perbandingan Lead Time per Role</h3></div>
        <div class="card-body pt-0"><div id="chart_roles" style="min-height:330px"></div></div>
    </div></div>
    <div class="col-xl-4"><div class="card card-flush h-100">
        <div class="card-header"><h3 class="card-title">Status Proses</h3></div>
        <div class="card-body pt-0"><div id="chart_status" style="min-height:330px"></div></div>
    </div></div>
</div>

<div class="card mb-6">
    <div class="card-header border-0 pt-6"><div class="card-title"><h3 class="fw-bold m-0">Performa per Jabatan</h3></div></div>
    <div class="card-body pt-3"><div class="table-responsive">
        <table class="table align-middle table-row-dashed fs-6 gy-4 w-100" id="position_table">
            <thead><tr class="text-gray-500 fw-bold fs-7 text-uppercase">
                <th>Role</th><th>Jabatan</th><th class="text-end">Proses</th><th class="text-end">Selesai</th><th class="text-end">Terbuka</th>
                <th class="text-end">Completion</th><th class="text-end">Rata-rata</th><th class="text-end">Median</th><th class="text-end">P90</th><th class="text-end">Backlog Terlama</th>
            </tr></thead><tbody></tbody>
        </table>
    </div></div>
</div>

<div class="card mb-6">
    <div class="card-header border-0 pt-6"><div class="card-title"><h3 class="fw-bold m-0">Performa per Operator</h3></div></div>
    <div class="card-body pt-3"><div class="table-responsive">
        <table class="table align-middle table-row-dashed fs-6 gy-4 w-100" id="operator_table">
            <thead><tr class="text-gray-500 fw-bold fs-7 text-uppercase">
                <th>Role</th><th>PIC</th><th>Jabatan</th><th class="text-end">Proses</th><th class="text-end">Selesai</th><th class="text-end">Terbuka</th>
                <th class="text-end">Completion</th><th class="text-end">Rata-rata</th><th class="text-end">Median</th><th class="text-end">P90</th>
            </tr></thead><tbody></tbody>
        </table>
    </div></div>
</div>

<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title"><h3 class="fw-bold m-0">Detail Timeline Operasional</h3></div>
        <div class="card-toolbar text-muted fs-7">Tabel dipaginasi 25 baris per halaman</div>
    </div>
    <div class="card-body pt-3"><div class="table-responsive">
        <table class="table align-middle table-row-dashed fs-6 gy-4 w-100" id="detail_table">
            <thead><tr class="text-gray-500 fw-bold fs-7 text-uppercase">
                <th>Role</th><th>Dokumen</th><th>Status</th><th>Mulai</th><th>Selesai</th><th class="text-end">Lead Time</th>
                <th class="text-end">Aging</th><th>PIC / Jabatan</th><th>Pelaku Awal / Akhir</th><th class="text-end">SKU</th><th class="text-end">Qty</th>
            </tr></thead><tbody></tbody>
        </table>
    </div></div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const dataUrl = @json($dataUrl);
    const exportUrl = @json($exportUrl);
    const initialFrom = @json($dateFrom);
    const initialTo = @json($dateTo);
    const number = new Intl.NumberFormat('id-ID');
    const decimal = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });
    const els = {
        from: document.getElementById('filter_date_from'), to: document.getElementById('filter_date_to'),
        role: document.getElementById('filter_role'), status: document.getElementById('filter_status'),
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
    const durationCell = (data, type) => type === 'display' ? (data === null ? '-' : duration(data)) : (Number(data) || 0);
    const roleBadge = (data, type, row) => {
        if (type !== 'display') return data;
        const colors = { picker: 'primary', packer: 'info', inbound: 'success', customer_return: 'warning' };
        return `<span class="badge badge-light-${colors[row.role] || 'secondary'}">${escapeHtml(data)}</span>`;
    };
    const statusBadge = (data, type, row) => type === 'display'
        ? `<div><span class="badge badge-light-${row.status === 'completed' ? 'success' : 'warning'}">${escapeHtml(data)}</span><div class="text-muted fs-8 mt-1">${escapeHtml(row.stage_label)}</div></div>`
        : data;
    const rateCell = (data, type) => type === 'display' ? `${decimal.format(data || 0)}%` : Number(data || 0);

    const positionTable = $('#position_table').DataTable({
        data: [], searching: false, pageLength: 10, order: [[0, 'asc'], [6, 'asc']], scrollX: true,
        columns: [
            { data: 'role_label', render: roleBadge }, { data: 'position' },
            { data: 'total', className: 'text-end' }, { data: 'completed', className: 'text-end' }, { data: 'open', className: 'text-end' },
            { data: 'completion_rate', className: 'text-end', render: rateCell },
            { data: 'avg_lead_minutes', className: 'text-end', render: durationCell },
            { data: 'median_lead_minutes', className: 'text-end', render: durationCell },
            { data: 'p90_lead_minutes', className: 'text-end fw-semibold', render: durationCell },
            { data: 'oldest_open_minutes', className: 'text-end', render: durationCell },
        ], language: { emptyTable: 'Belum ada data jabatan pada filter ini.' }
    });
    const operatorTable = $('#operator_table').DataTable({
        data: [], searching: false, pageLength: 10, order: [[0, 'asc'], [7, 'asc']], scrollX: true,
        columns: [
            { data: 'role_label', render: roleBadge }, { data: 'pic' }, { data: 'position' },
            { data: 'total', className: 'text-end' }, { data: 'completed', className: 'text-end' }, { data: 'open', className: 'text-end' },
            { data: 'completion_rate', className: 'text-end', render: rateCell },
            { data: 'avg_lead_minutes', className: 'text-end', render: durationCell },
            { data: 'median_lead_minutes', className: 'text-end', render: durationCell },
            { data: 'p90_lead_minutes', className: 'text-end fw-semibold', render: durationCell },
        ], language: { emptyTable: 'Belum ada operator yang dapat dianalisis pada filter ini.' }
    });
    const detailTable = $('#detail_table').DataTable({
        data: [], searching: false, pageLength: 25, order: [[3, 'desc']], scrollX: true,
        columns: [
            { data: 'role_label', render: roleBadge },
            { data: 'code', render: (data, type, row) => type === 'display' ? `<div class="fw-bold text-gray-900">${escapeHtml(data)}</div><div class="text-muted fs-8">${escapeHtml(row.reference)}</div>` : data },
            { data: 'status_label', render: statusBadge }, { data: 'started_at' }, { data: 'completed_at', defaultContent: '-' },
            { data: 'lead_minutes', className: 'text-end fw-semibold', render: durationCell },
            { data: 'aging_minutes', className: 'text-end', render: durationCell },
            { data: 'pic', render: (data, type, row) => type === 'display' ? `<div class="${row.pic_missing ? 'text-warning' : 'fw-semibold'}">${escapeHtml(data)}</div><div class="text-muted fs-8">${escapeHtml(row.position)}</div>` : `${data} ${row.position}` },
            { data: 'start_actor', render: (data, type, row) => type === 'display' ? `<div><span class="text-muted">Awal:</span> ${escapeHtml(data)}</div><div><span class="text-muted">Akhir:</span> ${escapeHtml(row.end_actor)}</div>` : `${data} ${row.end_actor}` },
            { data: 'total_sku', className: 'text-end', render: data => number.format(data || 0) },
            { data: 'total_qty', className: 'text-end', render: data => number.format(data || 0) },
        ], language: { emptyTable: 'Tidak ada proses operasional pada filter ini.' }
    });

    const filters = () => ({
        date_from: els.from.value || '', date_to: els.to.value || '', role: els.role.value || '',
        status: els.status.value || '', q: els.search.value.trim(),
    });
    const setText = (id, value) => { const target = document.getElementById(id); if (target) target.textContent = value; };
    const renderSummary = (summary = {}) => {
        setText('summary_total', number.format(summary.total_documents || 0));
        setText('summary_completed', number.format(summary.completed_documents || 0));
        setText('summary_open', number.format(summary.open_documents || 0));
        setText('summary_rate', decimal.format(summary.completion_rate || 0));
        setText('summary_avg', duration(summary.avg_lead_minutes || 0));
        setText('summary_median', duration(summary.median_lead_minutes || 0));
        setText('summary_p90', duration(summary.p90_lead_minutes || 0));
        setText('summary_oldest', duration(summary.oldest_open_minutes || 0));
        setText('summary_missing_position', number.format(summary.missing_position_documents || 0));
    };
    const renderRoles = (roles = []) => {
        const colors = { picker: 'primary', packer: 'info', inbound: 'success', customer_return: 'warning' };
        document.getElementById('role_cards').innerHTML = roles.map(role => `<div class="col-md-6 col-xl-3"><div class="card card-flush h-100 border-top border-3 border-${colors[role.key] || 'secondary'}"><div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-3"><div><div class="fs-4 fw-bold">${escapeHtml(role.label)}</div><div class="text-muted fs-8">${escapeHtml(role.definition)}</div></div><span class="badge badge-light-${colors[role.key] || 'secondary'}">${decimal.format(role.completion_rate || 0)}%</span></div>
            <div class="fs-2 fw-bold mb-1">${duration(role.avg_lead_minutes)}</div><div class="text-muted fs-8 mb-3">Rata-rata lead time · P90 ${duration(role.p90_lead_minutes)}</div>
            <div class="d-flex justify-content-between fs-7"><span>${number.format(role.completed)} selesai</span><span class="text-warning">${number.format(role.open)} terbuka</span></div>
        </div></div></div>`).join('');
    };
    const emptyChart = (id, message = 'Tidak ada data pada filter ini.') => {
        document.getElementById(id).innerHTML = `<div class="d-flex align-items-center justify-content-center text-muted h-300px">${escapeHtml(message)}</div>`;
    };
    const renderCharts = (roles = [], status = []) => {
        charts.forEach(chart => chart.destroy()); charts = [];
        if (typeof ApexCharts === 'undefined') {
            emptyChart('chart_roles', 'Library grafik tidak tersedia.'); emptyChart('chart_status', 'Library grafik tidak tersedia.'); return;
        }
        if (roles.some(role => role.total > 0)) {
            const roleChart = new ApexCharts(document.getElementById('chart_roles'), {
                series: [
                    { name: 'Rata-rata (jam)', data: roles.map(role => Number((role.avg_lead_minutes / 60).toFixed(2))) },
                    { name: 'P90 (jam)', data: roles.map(role => Number((role.p90_lead_minutes / 60).toFixed(2))) },
                ], chart: { type: 'bar', height: 330, toolbar: { show: false } }, colors: ['#1B84FF', '#F1416C'],
                plotOptions: { bar: { borderRadius: 4, columnWidth: '48%' } }, dataLabels: { enabled: false },
                xaxis: { categories: roles.map(role => role.label) }, yaxis: { title: { text: 'Jam' }, min: 0 },
                tooltip: { y: { formatter: value => `${decimal.format(value)} jam` } }, grid: { borderColor: '#E4E6EF', strokeDashArray: 4 },
            }); roleChart.render(); charts.push(roleChart);
        } else emptyChart('chart_roles');
        const statusTotal = status.reduce((sum, row) => sum + Number(row.total || 0), 0);
        if (statusTotal) {
            const statusChart = new ApexCharts(document.getElementById('chart_status'), {
                series: status.map(row => row.total), labels: status.map(row => row.label), chart: { type: 'donut', height: 330 },
                colors: ['#50CD89', '#FFC700'], legend: { position: 'bottom' }, dataLabels: { enabled: true },
                plotOptions: { pie: { donut: { size: '60%', labels: { show: true, total: { show: true, label: 'Total', formatter: () => number.format(statusTotal) } } } } },
            }); statusChart.render(); charts.push(statusChart);
        } else emptyChart('chart_status');
    };

    const load = async () => {
        els.alert.classList.add('d-none');
        const button = document.getElementById('filter_apply'); button.disabled = true; button.setAttribute('data-kt-indicator', 'on');
        try {
            const response = await fetch(`${dataUrl}?${new URLSearchParams(filters())}`, { headers: { Accept: 'application/json' } });
            const json = await response.json();
            if (!response.ok) throw new Error(json?.message || 'Gagal memuat laporan lead time operasional.');
            renderSummary(json.summary); renderRoles(json.roles || []); renderCharts(json.roles || [], json.charts?.status || []);
            positionTable.clear().rows.add(json.positions || []).draw();
            operatorTable.clear().rows.add(json.operators || []).draw();
            detailTable.clear().rows.add(json.details || []).draw();
        } catch (error) {
            els.alert.textContent = error.message || 'Gagal memuat laporan lead time operasional.'; els.alert.classList.remove('d-none');
        } finally {
            button.disabled = false; button.removeAttribute('data-kt-indicator');
        }
    };

    document.getElementById('filter_apply').addEventListener('click', load);
    document.getElementById('filter_reset').addEventListener('click', () => {
        if (fromPicker) fromPicker.setDate(initialFrom); else els.from.value = initialFrom;
        if (toPicker) toPicker.setDate(initialTo); else els.to.value = initialTo;
        els.role.value = ''; els.status.value = ''; els.search.value = ''; load();
    });
    document.getElementById('export_excel').addEventListener('click', () => { window.location.href = `${exportUrl}?${new URLSearchParams(filters())}`; });
    els.role.addEventListener('change', load); els.status.addEventListener('change', load);
    els.search.addEventListener('keyup', event => { if (event.key === 'Enter') load(); });
    load();
});
</script>
@endpush
