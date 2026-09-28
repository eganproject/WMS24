@extends('layouts.admin')

@section('title', 'Lead Time Operasional')
@section('page_title', 'Lead Time Operasional')

@push('styles')
<style>
    .lead-time-help { cursor: help; }
    .lead-time-kpi { transition: transform .15s ease, box-shadow .15s ease; }
    .lead-time-kpi:hover { transform: translateY(-2px); box-shadow: 0 .5rem 1.5rem rgba(0, 0, 0, .06); }
    .lead-time-definition { border: 1px solid #e4e6ef; background: #f9f9f9; }
    .lead-time-definition .symbol { flex: 0 0 auto; }
    .lead-time-table-tabs .nav-link { color: #7e8299; border-radius: .6rem; }
    .lead-time-table-tabs .nav-link.active { color: #009ef7; background: #eef6ff; }
    .lead-time-tooltip { color: #a1a5b7; margin-left: .35rem; }
    .tooltip-inner { max-width: 320px; text-align: left; }
    #report_content[aria-busy="true"] { opacity: .65; pointer-events: none; }
    .report-loading-indicator {
        position: fixed;
        inset: 0;
        z-index: 1090;
        background: rgba(24, 28, 50, .18);
        backdrop-filter: blur(1px);
    }
    .report-loading-indicator .loading-card {
        min-width: 250px;
        box-shadow: 0 1rem 3rem rgba(24, 28, 50, .18);
    }
</style>
@endpush

@section('content')
<div class="card mb-6">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div>
                <div class="fs-3 fw-bold text-gray-900">Analisis Lead Time per Role dan Jabatan</div>
                <div class="text-muted fs-7">Bandingkan kecepatan proses, backlog, dan konsistensi performa dalam satu laporan.</div>
            </div>
        </div>
        <div class="card-toolbar">
            <div class="d-flex align-items-end gap-2 flex-wrap">
                <div><label class="form-label fs-8 text-muted mb-1">Mulai proses</label><input type="text" class="form-control form-control-solid w-145px" id="filter_date_from" value="{{ $dateFrom }}" placeholder="Dari"></div>
                <div><label class="form-label fs-8 text-muted mb-1">Sampai</label><input type="text" class="form-control form-control-solid w-145px" id="filter_date_to" value="{{ $dateTo }}" placeholder="Sampai"></div>
                <div><label class="form-label fs-8 text-muted mb-1">Role</label><select class="form-select form-select-solid w-180px" id="filter_role">
                    <option value="">Semua Role</option><option value="picker">Picker</option><option value="packer">Packer</option><option value="inbound">Inbound</option><option value="customer_return">Retur Customer</option>
                </select></div>
                <div><label class="form-label fs-8 text-muted mb-1">Status</label><select class="form-select form-select-solid w-170px" id="filter_status">
                    <option value="">Semua Status</option><option value="completed">Selesai</option><option value="open">Belum Selesai</option>
                </select></div>
                <div><label class="form-label fs-8 text-muted mb-1">Pencarian</label><input type="text" class="form-control form-control-solid w-225px" id="filter_search" placeholder="Dokumen / PIC / jabatan"></div>
                <button type="button" class="btn btn-primary" id="filter_apply"><i class="fas fa-filter me-1"></i>Terapkan</button>
                <button type="button" class="btn btn-light" id="filter_reset">Reset</button>
                <button type="button" class="btn btn-success" id="export_excel"><i class="fas fa-file-excel me-1"></i>Export Excel</button>
            </div>
        </div>
    </div>
</div>

<div class="card card-flush mb-6">
    <div class="card-header min-h-60px"><div class="card-title">
        <i class="fas fa-route text-primary me-3"></i><div><div class="fw-bold">Batas waktu setiap role</div><div class="text-muted fs-8">Setiap proses memakai titik mulai yang berbeda sesuai alur operasional.</div></div>
    </div></div>
    <div class="card-body pt-2"><div class="row g-3">
        @foreach([
            ['Picker', 'Input resi', 'QC scan selesai', 'primary'],
            ['Packer', 'QC selesai', 'Scan out selesai', 'info'],
            ['Inbound', 'Input receipt', 'Completion', 'success'],
            ['Retur Customer', 'Input retur', 'Finalisasi', 'warning'],
        ] as [$role, $start, $end, $color])
            <div class="col-md-6 col-xl-3"><div class="lead-time-definition rounded p-3 h-100 d-flex align-items-center">
                <div class="symbol symbol-35px me-3"><span class="symbol-label bg-light-{{ $color }} text-{{ $color }}"><i class="fas fa-stopwatch text-{{ $color }}"></i></span></div>
                <div><div class="fw-bold text-gray-800">{{ $role }}</div><div class="text-muted fs-8">{{ $start }} <i class="fas fa-arrow-right mx-1"></i> {{ $end }}</div></div>
            </div></div>
        @endforeach
    </div></div>
</div>

<div id="report_alert" class="alert alert-danger d-none mb-6"></div>
<div id="report_loading" class="report-loading-indicator d-none align-items-center justify-content-center" role="status" aria-live="polite" aria-label="Sedang mengambil data laporan">
    <div class="loading-card bg-white rounded-3 p-6 d-flex align-items-center gap-4">
        <span class="spinner-border text-primary" aria-hidden="true"></span>
        <div><div class="fw-bold text-gray-900">Mengambil data laporan…</div><div class="text-muted fs-8">Mohon tunggu sebentar.</div></div>
    </div>
</div>
<div id="report_context" class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div class="text-muted fs-7"><i class="fas fa-calendar-alt me-2"></i><span id="active_filter_label">Memuat periode laporan...</span></div>
    <div class="d-flex gap-2"><span class="badge badge-light-success">Selesai</span><span class="badge badge-light-warning">Belum selesai</span></div>
</div>

<div id="report_content" aria-busy="false">
<div class="row g-4 mb-6">
    <div class="col-sm-6 col-xl-3"><div class="card card-flush h-100 border border-gray-200 lead-time-kpi"><div class="card-body">
        <div class="d-flex align-items-center text-muted fw-semibold">Total Proses <i class="fas fa-info-circle lead-time-tooltip lead-time-help" data-bs-toggle="tooltip" data-bs-html="true" title="Jumlah seluruh proses pada filter. Satu resi dapat dihitung sebagai proses Picker dan Packer karena keduanya memiliki rentang waktu berbeda."></i></div>
        <div class="fs-2hx fw-bold text-primary" id="summary_total">0</div><div class="fs-7 text-muted"><span id="summary_completed">0</span> selesai · <span id="summary_open">0</span> terbuka</div>
    </div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card card-flush h-100 border border-gray-200 lead-time-kpi"><div class="card-body">
        <div class="d-flex align-items-center text-muted fw-semibold">Completion Rate <i class="fas fa-info-circle lead-time-tooltip lead-time-help" data-bs-toggle="tooltip" data-bs-html="true" title="Rumus: proses selesai ÷ total proses × 100%.<br>Menunjukkan proporsi pekerjaan yang sudah mencapai milestone akhir."></i></div>
        <div class="fs-2hx fw-bold text-success"><span id="summary_rate">0</span><span class="fs-5 ms-1">%</span></div><div class="fs-7 text-muted">Tingkat penyelesaian proses</div>
    </div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card card-flush h-100 border border-gray-200 lead-time-kpi"><div class="card-body">
        <div class="d-flex align-items-center text-muted fw-semibold">Rata-rata Lead Time <i class="fas fa-info-circle lead-time-tooltip lead-time-help" data-bs-toggle="tooltip" data-bs-html="true" title="Rumus: total durasi proses selesai ÷ jumlah proses selesai.<br>Proses yang masih terbuka tidak masuk perhitungan."></i></div>
        <div class="fs-2hx fw-bold text-info" id="summary_avg">0 menit</div><div class="fs-7 text-muted">Median <span id="summary_median">0 menit</span> · P90 <span id="summary_p90">0 menit</span> <i class="fas fa-info-circle lead-time-tooltip lead-time-help" data-bs-toggle="tooltip" title="Median adalah nilai tengah. P90 berarti 90% proses selesai dalam durasi ini atau lebih cepat."></i></div>
    </div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card card-flush h-100 border border-gray-200 lead-time-kpi"><div class="card-body">
        <div class="d-flex align-items-center text-muted fw-semibold">Backlog Terlama <i class="fas fa-info-circle lead-time-tooltip lead-time-help" data-bs-toggle="tooltip" data-bs-html="true" title="Aging terbesar dari proses yang belum selesai.<br>Rumus: waktu sekarang − waktu mulai proses."></i></div>
        <div class="fs-2hx fw-bold text-warning" id="summary_oldest">0 menit</div><div class="fs-7 text-muted"><span id="summary_missing_position">0</span> proses belum terhubung ke jabatan <i class="fas fa-info-circle lead-time-tooltip lead-time-help" data-bs-toggle="tooltip" title="Jabatan diambil dari data karyawan yang terhubung dengan PIC proses."></i></div>
    </div></div></div>
</div>

<div class="row g-4 mb-6" id="role_cards"></div>

<div class="row g-6 mb-6">
    <div class="col-xl-8"><div class="card card-flush h-100">
        <div class="card-header"><div class="card-title"><div><div class="fw-bold">Perbandingan Lead Time per Role <i class="fas fa-info-circle lead-time-tooltip lead-time-help" data-bs-toggle="tooltip" title="Rata-rata menunjukkan kecenderungan umum, sedangkan P90 membantu melihat kasus lambat tanpa hanya terpaku pada nilai maksimum."></i></div><div class="text-muted fs-8">Membandingkan rata-rata dan batas P90 dalam jam.</div></div></div></div>
        <div class="card-body pt-0"><div id="chart_roles" style="min-height:330px"></div></div>
    </div></div>
    <div class="col-xl-4"><div class="card card-flush h-100">
        <div class="card-header"><div class="card-title"><div><div class="fw-bold">Status Proses</div><div class="text-muted fs-8">Komposisi selesai dan backlog aktif.</div></div></div></div>
        <div class="card-body pt-0"><div id="chart_status" style="min-height:330px"></div></div>
    </div></div>
</div>

<div class="card">
    <div class="card-header border-0 pt-5 align-items-end">
        <div class="card-title"><div><div class="fs-3 fw-bold">Analisis Detail</div><div class="text-muted fs-8">Pilih sudut pandang yang ingin dianalisis.</div></div></div>
        <div class="card-toolbar"><ul class="nav nav-pills lead-time-table-tabs gap-2" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab_position" type="button"><i class="fas fa-briefcase me-2"></i>Jabatan</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab_operator" type="button"><i class="fas fa-user me-2"></i>Operator</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab_detail" type="button"><i class="fas fa-list me-2"></i>Timeline <span class="badge badge-light-primary ms-1" id="detail_count">0</span></button></li>
        </ul></div>
    </div>
    <div class="card-body pt-4"><div class="tab-content">
        <div class="tab-pane fade show active" id="tab_position" role="tabpanel">
            <div class="mb-4"><div class="fw-bold">Performa per Jabatan</div><div class="text-muted fs-8">Gunakan tabel ini untuk membandingkan kapasitas dan konsistensi antarjabatan.</div></div>
            <div class="table-responsive"><table class="table align-middle table-row-dashed fs-6 gy-4 w-100" id="position_table">
                <thead><tr class="text-gray-500 fw-semibold fs-7"><th>Role</th><th>Jabatan</th><th class="text-end">Proses</th><th class="text-end">Selesai</th><th class="text-end">Terbuka</th>
                    <th class="text-end lead-time-help" data-bs-toggle="tooltip" title="Proses selesai ÷ total proses × 100%">Completion <i class="fas fa-info-circle"></i></th>
                    <th class="text-end lead-time-help" data-bs-toggle="tooltip" title="Total durasi proses selesai ÷ jumlah proses selesai">Rata-rata <i class="fas fa-info-circle"></i></th>
                    <th class="text-end lead-time-help" data-bs-toggle="tooltip" title="Nilai tengah durasi proses selesai">Median <i class="fas fa-info-circle"></i></th>
                    <th class="text-end lead-time-help" data-bs-toggle="tooltip" title="90% proses selesai dalam durasi ini atau lebih cepat">P90 <i class="fas fa-info-circle"></i></th>
                    <th class="text-end lead-time-help" data-bs-toggle="tooltip" title="Aging terbesar dari proses yang belum selesai">Backlog Terlama <i class="fas fa-info-circle"></i></th>
                </tr></thead><tbody></tbody>
            </table></div>
        </div>
        <div class="tab-pane fade" id="tab_operator" role="tabpanel">
            <div class="mb-4"><div class="fw-bold">Performa per Operator</div><div class="text-muted fs-8">PIC mengikuti operator yang tercatat pada milestone akhir atau operator terakhir yang tersedia.</div></div>
            <div class="table-responsive"><table class="table align-middle table-row-dashed fs-6 gy-4 w-100" id="operator_table">
                <thead><tr class="text-gray-500 fw-semibold fs-7"><th>Role</th><th class="lead-time-help" data-bs-toggle="tooltip" title="Operator yang menyelesaikan milestone; bila belum selesai menggunakan operator terakhir yang tersedia.">PIC <i class="fas fa-info-circle"></i></th><th>Jabatan</th><th class="text-end">Proses</th><th class="text-end">Selesai</th><th class="text-end">Terbuka</th>
                    <th class="text-end lead-time-help" data-bs-toggle="tooltip" title="Proses selesai ÷ total proses × 100%">Completion <i class="fas fa-info-circle"></i></th>
                    <th class="text-end lead-time-help" data-bs-toggle="tooltip" title="Total durasi proses selesai ÷ jumlah proses selesai">Rata-rata <i class="fas fa-info-circle"></i></th>
                    <th class="text-end lead-time-help" data-bs-toggle="tooltip" title="Nilai tengah durasi proses selesai">Median <i class="fas fa-info-circle"></i></th>
                    <th class="text-end lead-time-help" data-bs-toggle="tooltip" title="90% proses selesai dalam durasi ini atau lebih cepat">P90 <i class="fas fa-info-circle"></i></th>
                </tr></thead><tbody></tbody>
            </table></div>
        </div>
        <div class="tab-pane fade" id="tab_detail" role="tabpanel">
            <div class="mb-4"><div class="fw-bold">Detail Timeline Operasional</div><div class="text-muted fs-8">Urutan kejadian per dokumen, dipaginasi 25 baris per halaman.</div></div>
            <div class="table-responsive"><table class="table align-middle table-row-dashed fs-6 gy-4 w-100" id="detail_table">
                <thead><tr class="text-gray-500 fw-semibold fs-7"><th>Role</th><th>Dokumen</th><th>Status</th><th class="lead-time-help" data-bs-toggle="tooltip" title="Waktu awal sesuai definisi role di bagian atas laporan.">Mulai <i class="fas fa-info-circle"></i></th><th>Selesai</th>
                    <th class="text-end lead-time-help" data-bs-toggle="tooltip" title="Waktu selesai − waktu mulai. Hanya tersedia untuk proses selesai.">Lead Time <i class="fas fa-info-circle"></i></th>
                    <th class="text-end lead-time-help" data-bs-toggle="tooltip" title="Waktu sekarang − waktu mulai untuk proses yang belum selesai.">Aging <i class="fas fa-info-circle"></i></th>
                    <th class="lead-time-help" data-bs-toggle="tooltip" title="PIC dan jabatan karyawan yang terhubung ke proses.">PIC / Jabatan <i class="fas fa-info-circle"></i></th><th>Pelaku Awal / Akhir</th><th class="text-end">SKU</th><th class="text-end">Qty</th>
                </tr></thead><tbody></tbody>
            </table></div>
        </div>
    </div></div>
</div>
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
    const dateTimeFormat = new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    const els = {
        from: document.getElementById('filter_date_from'), to: document.getElementById('filter_date_to'), role: document.getElementById('filter_role'),
        status: document.getElementById('filter_status'), search: document.getElementById('filter_search'), alert: document.getElementById('report_alert'),
        content: document.getElementById('report_content'), loading: document.getElementById('report_loading'), filterLabel: document.getElementById('active_filter_label'),
    };
    const tableLanguage = { lengthMenu: 'Tampilkan _MENU_', info: 'Menampilkan _START_–_END_ dari _TOTAL_', infoEmpty: 'Tidak ada data', paginate: { previous: 'Sebelumnya', next: 'Berikutnya' } };
    let charts = [];
    let fromPicker = null;
    let toPicker = null;
    if (typeof flatpickr !== 'undefined') {
        fromPicker = flatpickr(els.from, { dateFormat: 'Y-m-d', allowInput: true });
        toPicker = flatpickr(els.to, { dateFormat: 'Y-m-d', allowInput: true });
    }

    const initTooltips = root => {
        if (!window.bootstrap?.Tooltip) return;
        root.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(element => bootstrap.Tooltip.getOrCreateInstance(element, { container: 'body' }));
    };
    const escapeHtml = value => $('<div>').text(value ?? '-').html();
    const duration = value => {
        const minutes = Number(value) || 0;
        if (minutes < 60) return `${decimal.format(minutes)} mnt`;
        if (minutes < 1440) return `${decimal.format(minutes / 60)} jam`;
        return `${decimal.format(minutes / 1440)} hari`;
    };
    const dateTime = value => {
        if (!value) return '-';
        const parsed = new Date(String(value).replace(' ', 'T'));
        return Number.isNaN(parsed.getTime()) ? value : dateTimeFormat.format(parsed).replace(' pukul ', ' · ');
    };
    const durationCell = (data, type) => type === 'display' ? (data === null ? '-' : duration(data)) : (Number(data) || 0);
    const dateCell = (data, type) => type === 'display' ? dateTime(data) : (data || '');
    const roleBadge = (data, type, row) => {
        if (type !== 'display') return data;
        const colors = { picker: 'primary', packer: 'info', inbound: 'success', customer_return: 'warning' };
        return `<span class="badge badge-light-${colors[row.role] || 'secondary'}">${escapeHtml(data)}</span>`;
    };
    const statusBadge = (data, type, row) => type === 'display'
        ? `<div><span class="badge badge-light-${row.status === 'completed' ? 'success' : 'warning'}">${escapeHtml(data)}</span><div class="text-muted fs-8 mt-1">${escapeHtml(row.stage_label)}</div></div>` : data;
    const rateCell = (data, type) => type === 'display' ? `${decimal.format(data || 0)}%` : Number(data || 0);

    const positionTable = $('#position_table').DataTable({
        data: [], searching: false, pageLength: 10, lengthMenu: [10, 25, 50], order: [[0, 'asc'], [6, 'asc']], scrollX: true, autoWidth: false,
        columns: [
            { data: 'role_label', render: roleBadge }, { data: 'position' }, { data: 'total', className: 'text-end' }, { data: 'completed', className: 'text-end' }, { data: 'open', className: 'text-end' },
            { data: 'completion_rate', className: 'text-end', render: rateCell }, { data: 'avg_lead_minutes', className: 'text-end', render: durationCell },
            { data: 'median_lead_minutes', className: 'text-end', render: durationCell }, { data: 'p90_lead_minutes', className: 'text-end fw-semibold', render: durationCell },
            { data: 'oldest_open_minutes', className: 'text-end', render: durationCell },
        ], language: { ...tableLanguage, emptyTable: 'Belum ada data jabatan pada filter ini.' }
    });
    const operatorTable = $('#operator_table').DataTable({
        data: [], searching: false, pageLength: 10, lengthMenu: [10, 25, 50], order: [[0, 'asc'], [7, 'asc']], scrollX: true, autoWidth: false,
        columns: [
            { data: 'role_label', render: roleBadge }, { data: 'pic' }, { data: 'position' }, { data: 'total', className: 'text-end' }, { data: 'completed', className: 'text-end' }, { data: 'open', className: 'text-end' },
            { data: 'completion_rate', className: 'text-end', render: rateCell }, { data: 'avg_lead_minutes', className: 'text-end', render: durationCell },
            { data: 'median_lead_minutes', className: 'text-end', render: durationCell }, { data: 'p90_lead_minutes', className: 'text-end fw-semibold', render: durationCell },
        ], language: { ...tableLanguage, emptyTable: 'Belum ada operator yang dapat dianalisis pada filter ini.' }
    });
    const detailTable = $('#detail_table').DataTable({
        data: [], searching: false, pageLength: 25, lengthMenu: [25, 50, 100], order: [[3, 'desc']], scrollX: true, autoWidth: false,
        columns: [
            { data: 'role_label', render: roleBadge },
            { data: 'code', render: (data, type, row) => type === 'display' ? `<div class="fw-bold text-gray-900">${escapeHtml(data)}</div><div class="text-muted fs-8">${escapeHtml(row.reference)}</div>` : data },
            { data: 'status_label', render: statusBadge }, { data: 'started_at', render: dateCell }, { data: 'completed_at', render: dateCell },
            { data: 'lead_minutes', className: 'text-end fw-semibold', render: durationCell }, { data: 'aging_minutes', className: 'text-end', render: durationCell },
            { data: 'pic', render: (data, type, row) => type === 'display' ? `<div class="${row.pic_missing ? 'text-warning' : 'fw-semibold'}">${escapeHtml(data)}</div><div class="text-muted fs-8">${escapeHtml(row.position)}</div>` : `${data} ${row.position}` },
            { data: 'start_actor', render: (data, type, row) => type === 'display' ? `<div><span class="text-muted">Awal:</span> ${escapeHtml(data)}</div><div><span class="text-muted">Akhir:</span> ${escapeHtml(row.end_actor)}</div>` : `${data} ${row.end_actor}` },
            { data: 'total_sku', className: 'text-end', render: data => number.format(data || 0) }, { data: 'total_qty', className: 'text-end', render: data => number.format(data || 0) },
        ], language: { ...tableLanguage, emptyTable: 'Tidak ada proses operasional pada filter ini.' }
    });

    const filters = () => ({ date_from: els.from.value || '', date_to: els.to.value || '', role: els.role.value || '', status: els.status.value || '', q: els.search.value.trim() });
    const setText = (id, value) => { const target = document.getElementById(id); if (target) target.textContent = value; };
    const renderFilterLabel = () => {
        const role = els.role.options[els.role.selectedIndex]?.text || 'Semua Role';
        const status = els.status.options[els.status.selectedIndex]?.text || 'Semua Status';
        els.filterLabel.textContent = `${els.from.value || 'Semua tanggal'} s.d. ${els.to.value || 'Semua tanggal'} · ${role} · ${status}`;
    };
    const renderSummary = (summary = {}) => {
        setText('summary_total', number.format(summary.total_documents || 0)); setText('summary_completed', number.format(summary.completed_documents || 0));
        setText('summary_open', number.format(summary.open_documents || 0)); setText('summary_rate', decimal.format(summary.completion_rate || 0));
        setText('summary_avg', duration(summary.avg_lead_minutes || 0)); setText('summary_median', duration(summary.median_lead_minutes || 0));
        setText('summary_p90', duration(summary.p90_lead_minutes || 0)); setText('summary_oldest', duration(summary.oldest_open_minutes || 0));
        setText('summary_missing_position', number.format(summary.missing_position_documents || 0));
    };
    const renderRoles = (roles = []) => {
        const colors = { picker: 'primary', packer: 'info', inbound: 'success', customer_return: 'warning' };
        const container = document.getElementById('role_cards');
        container.innerHTML = roles.map(role => `<div class="col-md-6 col-xl-3"><div class="card card-flush h-100 border-top border-3 border-${colors[role.key] || 'secondary'} lead-time-kpi"><div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-3"><div><div class="fs-4 fw-bold">${escapeHtml(role.label)}</div><div class="text-muted fs-8">${escapeHtml(role.definition)}</div></div><span class="badge badge-light-${colors[role.key] || 'secondary'} lead-time-help" data-bs-toggle="tooltip" title="Completion rate: ${number.format(role.completed)} selesai dari ${number.format(role.total)} proses">${decimal.format(role.completion_rate || 0)}%</span></div>
            <div class="fs-2 fw-bold mb-1">${duration(role.avg_lead_minutes)}</div><div class="text-muted fs-8 mb-3">Rata-rata · Median ${duration(role.median_lead_minutes)} · P90 ${duration(role.p90_lead_minutes)}</div>
            <div class="d-flex justify-content-between fs-7"><span><i class="fas fa-check-circle text-success me-1"></i>${number.format(role.completed)} selesai</span><span class="text-warning"><i class="fas fa-clock me-1"></i>${number.format(role.open)} terbuka</span></div>
        </div></div></div>`).join('');
        initTooltips(container);
    };
    const emptyChart = (id, message = 'Tidak ada data pada filter ini.') => { document.getElementById(id).innerHTML = `<div class="d-flex align-items-center justify-content-center text-muted h-300px">${escapeHtml(message)}</div>`; };
    const renderCharts = (roles = [], status = []) => {
        charts.forEach(chart => chart.destroy()); charts = [];
        if (typeof ApexCharts === 'undefined') { emptyChart('chart_roles', 'Library grafik tidak tersedia.'); emptyChart('chart_status', 'Library grafik tidak tersedia.'); return; }
        if (roles.some(role => role.total > 0)) {
            const roleChart = new ApexCharts(document.getElementById('chart_roles'), {
                series: [{ name: 'Rata-rata (jam)', data: roles.map(role => Number((role.avg_lead_minutes / 60).toFixed(2))) }, { name: 'P90 (jam)', data: roles.map(role => Number((role.p90_lead_minutes / 60).toFixed(2))) }],
                chart: { type: 'bar', height: 330, toolbar: { show: false } }, colors: ['#1B84FF', '#F1416C'], plotOptions: { bar: { borderRadius: 4, columnWidth: '48%' } },
                dataLabels: { enabled: false }, xaxis: { categories: roles.map(role => role.label) }, yaxis: { title: { text: 'Jam' }, min: 0 },
                tooltip: { y: { formatter: value => `${decimal.format(value)} jam` } }, grid: { borderColor: '#E4E6EF', strokeDashArray: 4 },
            }); roleChart.render(); charts.push(roleChart);
        } else emptyChart('chart_roles');
        const statusTotal = status.reduce((sum, row) => sum + Number(row.total || 0), 0);
        if (statusTotal) {
            const statusChart = new ApexCharts(document.getElementById('chart_status'), {
                series: status.map(row => row.total), labels: status.map(row => row.label), chart: { type: 'donut', height: 330 }, colors: ['#50CD89', '#FFC700'], legend: { position: 'bottom' }, dataLabels: { enabled: true },
                plotOptions: { pie: { donut: { size: '60%', labels: { show: true, total: { show: true, label: 'Total', formatter: () => number.format(statusTotal) } } } } },
            }); statusChart.render(); charts.push(statusChart);
        } else emptyChart('chart_status');
    };

    const load = async () => {
        els.alert.classList.add('d-none'); els.content.setAttribute('aria-busy', 'true');
        els.loading.classList.remove('d-none'); els.loading.classList.add('d-flex'); renderFilterLabel();
        const button = document.getElementById('filter_apply'); button.disabled = true; button.setAttribute('data-kt-indicator', 'on');
        try {
            const response = await fetch(`${dataUrl}?${new URLSearchParams(filters())}`, { headers: { Accept: 'application/json' } });
            const json = await response.json();
            if (!response.ok) throw new Error(json?.message || 'Gagal memuat laporan lead time operasional.');
            renderSummary(json.summary); renderRoles(json.roles || []); renderCharts(json.roles || [], json.charts?.status || []);
            positionTable.clear().rows.add(json.positions || []).draw(); operatorTable.clear().rows.add(json.operators || []).draw(); detailTable.clear().rows.add(json.details || []).draw();
            setText('detail_count', number.format((json.details || []).length));
        } catch (error) {
            els.alert.textContent = error.message || 'Gagal memuat laporan lead time operasional.'; els.alert.classList.remove('d-none');
        } finally {
            button.disabled = false; button.removeAttribute('data-kt-indicator'); els.content.setAttribute('aria-busy', 'false');
            els.loading.classList.add('d-none'); els.loading.classList.remove('d-flex');
        }
    };

    document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => tab.addEventListener('shown.bs.tab', () => { positionTable.columns.adjust(); operatorTable.columns.adjust(); detailTable.columns.adjust(); }));
    document.getElementById('filter_apply').addEventListener('click', load);
    document.getElementById('filter_reset').addEventListener('click', () => {
        if (fromPicker) fromPicker.setDate(initialFrom); else els.from.value = initialFrom;
        if (toPicker) toPicker.setDate(initialTo); else els.to.value = initialTo;
        els.role.value = ''; els.status.value = ''; els.search.value = ''; load();
    });
    document.getElementById('export_excel').addEventListener('click', () => { window.location.href = `${exportUrl}?${new URLSearchParams(filters())}`; });
    els.role.addEventListener('change', load); els.status.addEventListener('change', load); els.search.addEventListener('keyup', event => { if (event.key === 'Enter') load(); });
    initTooltips(document); load();
});
</script>
@endpush
