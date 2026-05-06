@extends('layouts.admin')

@section('title', 'Log Mesin Absensi')
@section('page_title', 'Log Mesin Absensi')

@push('styles')
<style>
    /* ===== Hero ===== */
    .ml-hero {
        background: linear-gradient(135deg, #f8faff 0%, #fff 60%);
        border: 1px solid #eef0f8;
        border-radius: 1rem;
        padding: 1.25rem 1.5rem;
        margin-bottom: 1.5rem;
    }
    .ml-hero-eyebrow {
        font-size: .72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: #1b84ff;
        margin-bottom: .35rem;
    }
    .ml-hero h1 { font-size: 1.5rem; font-weight: 800; color: #1e1e2d; margin: 0; }
    .ml-hero p  { color: #7e8299; font-size: .875rem; margin-top: .25rem; }

    /* ===== Section Nav ===== */
    .ml-nav {
        display: flex;
        flex-wrap: nowrap;
        gap: .5rem;
        overflow-x: auto;
        padding: .5rem .25rem;
        margin: 0 -.25rem;
        scrollbar-width: thin;
    }
    .ml-nav::-webkit-scrollbar { height: 6px; }
    .ml-nav::-webkit-scrollbar-thumb { background: #e4e6ef; border-radius: 4px; }
    .ml-nav-item {
        flex: 0 0 auto;
        display: inline-flex;
        align-items: center;
        gap: .5rem;
        padding: .65rem 1rem;
        border-radius: .65rem;
        background: #f5f8fa;
        color: #5e6278;
        font-weight: 600;
        font-size: .875rem;
        text-decoration: none;
        white-space: nowrap;
        border: 1px solid transparent;
        transition: all .15s ease;
    }
    .ml-nav-item:hover { background: #eef3f7; color: #1b84ff; }
    .ml-nav-item.active {
        background: #1b84ff;
        color: #fff;
        box-shadow: 0 6px 14px rgba(27, 132, 255, .25);
    }
    .ml-nav-item.active:hover { color: #fff; }

    /* ===== Summary cards ===== */
    .ml-stat-card {
        position: relative;
        background: #fff;
        border-radius: 1rem;
        padding: 1.15rem 1.25rem;
        height: 100%;
        border: 1px solid #eef0f8;
        overflow: hidden;
        transition: transform .15s ease, box-shadow .15s ease;
    }
    .ml-stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 22px rgba(31, 41, 55, .06);
    }
    .ml-stat-card::before {
        content: "";
        position: absolute;
        top: 0; left: 0;
        width: 6px;
        height: 100%;
        border-radius: 1rem 0 0 1rem;
    }
    .ml-stat-card.total::before     { background: #1b84ff; }
    .ml-stat-card.success::before   { background: #50cd89; }
    .ml-stat-card.failed::before    { background: #f1416c; }
    .ml-stat-card.heartbeat::before { background: #7239ea; }

    .ml-stat-icon {
        width: 38px;
        height: 38px;
        border-radius: .55rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: .95rem;
    }
    .ml-stat-card.total .ml-stat-icon     { background: #eaf3ff; color: #1b84ff; }
    .ml-stat-card.success .ml-stat-icon   { background: #e8fbf1; color: #1aae6f; }
    .ml-stat-card.failed .ml-stat-icon    { background: #fde8ef; color: #d33269; }
    .ml-stat-card.heartbeat .ml-stat-icon { background: #f0eafc; color: #6f37df; }

    .ml-stat-label {
        color: #7e8299;
        font-size: .72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .06em;
        margin-bottom: .35rem;
    }
    .ml-stat-value {
        font-size: 1.85rem;
        line-height: 1.1;
        font-weight: 800;
        color: #1e1e2d;
    }
    .ml-stat-card.success .ml-stat-value   { color: #1aae6f; }
    .ml-stat-card.failed .ml-stat-value    { color: #d33269; }
    .ml-stat-card.heartbeat .ml-stat-value { color: #6f37df; }
    .ml-stat-meta {
        color: #a1a5b7;
        font-size: .75rem;
        margin-top: .35rem;
    }

    /* ===== Filter bar ===== */
    .ml-filter {
        background: #fff;
        border: 1px solid #eef0f8;
        border-radius: .85rem;
        padding: 1.1rem;
        margin-bottom: 1.25rem;
    }
    .ml-filter-head {
        display: flex;
        align-items: center;
        gap: .65rem;
        margin-bottom: .85rem;
        font-weight: 700;
        color: #3f4254;
    }
    .ml-filter-head i { color: #1b84ff; }

    /* ===== Status badge ===== */
    .status-badge {
        font-size: 0.72rem;
        font-weight: 700;
        padding: 0.3rem 0.65rem;
        border-radius: 999px;
        display: inline-block;
    }

    /* ===== Modal ===== */
    .payload-cell {
        max-width: 280px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        cursor: pointer;
    }
    pre.payload-pre {
        background: #f8f9fc;
        border: 1px solid #eef0f8;
        border-radius: 0.5rem;
        padding: 1rem;
        font-size: 0.8rem;
        max-height: 400px;
        overflow-y: auto;
        white-space: pre-wrap;
        word-break: break-all;
        color: #3f4254;
    }
    .ml-section-title {
        font-weight: 700;
        font-size: .72rem;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: #7e8299;
        margin-bottom: .65rem;
        display: flex;
        align-items: center;
        gap: .4rem;
    }
    .ml-section-title::before {
        content: "";
        width: 4px;
        height: 12px;
        background: #1b84ff;
        border-radius: 2px;
    }

    /* ===== Table polish ===== */
    #machine_logs_table thead th {
        font-size: .72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: #7e8299;
        background: #f9fafc;
        border: none;
    }
    #machine_logs_table tbody td {
        vertical-align: middle;
    }
    .ml-empty {
        text-align: center;
        padding: 3rem 1rem;
        color: #a1a5b7;
    }
    .ml-empty i { font-size: 2.5rem; display: block; margin-bottom: .5rem; opacity: .55; }

    /* ===== Responsive ===== */
    @media (max-width: 768px) {
        .ml-hero { padding: 1rem; }
        .ml-hero h1 { font-size: 1.2rem; }
        .ml-stat-card { padding: 1rem; }
        .ml-stat-value { font-size: 1.4rem; }
    }
</style>
@endpush

@section('content')
@php $sectionLinks = $sectionLinks ?? []; @endphp

{{-- ===== Hero ===== --}}
<div class="ml-hero">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
            <div class="ml-hero-eyebrow"><i class="fas fa-satellite-dish me-1"></i>Modul Absensi</div>
            <h1><i class="fas fa-network-wired me-2 text-primary"></i>Log Mesin Absensi</h1>
            <p class="mb-0">Mencatat setiap request dari mesin absensi (ADMS / webhook) lengkap dengan payload, response, dan status.</p>
        </div>
        <button type="button" class="btn btn-light-primary btn-sm" id="btn_refresh_machine_logs">
            <i class="fas fa-sync-alt me-1"></i>Refresh
        </button>
    </div>
</div>

{{-- ===== Section Navigation ===== --}}
<div class="card mb-6 shadow-sm">
    <div class="card-body py-3">
        <nav class="ml-nav">
            @foreach($sectionLinks as $sectionKey => $section)
                <a href="{{ route($section['route']) }}" class="ml-nav-item">
                    <i class="{{ $section['icon'] }}"></i>
                    <span>{{ $section['label'] }}</span>
                </a>
            @endforeach
            <a href="{{ route('admin.attendance.machine-logs.index') }}" class="ml-nav-item active">
                <i class="fas fa-satellite-dish"></i>
                <span>Machine Log</span>
            </a>
        </nav>
    </div>
</div>

{{-- ===== Info config ===== --}}
<div class="alert alert-info d-flex align-items-start gap-3 mb-6 border-0" style="background:#eff8ff;">
    <i class="fas fa-info-circle fs-3 mt-1 text-primary"></i>
    <div class="flex-grow-1">
        <div class="fw-bold mb-2">Konfigurasi Mesin Solution X100C</div>
        <div class="fs-7 text-muted mb-2">
            Arahkan server / ADMS mesin ke domain aplikasi ini dengan path <code>/iclock/cdata</code>.
            Nomor serial mesin harus sama dengan kolom <strong>serial_number</strong> di menu Device Absensi.
        </div>
        <div class="fs-8 text-muted">
            <span class="me-3"><i class="fas fa-circle text-success me-1" style="font-size:6px;"></i>Endpoint ADMS: <code>{{ url('/iclock/cdata') }}</code></span>
            <span class="me-3"><i class="fas fa-circle text-success me-1" style="font-size:6px;"></i><code>{{ url('/iclock/getrequest') }}</code></span>
            <span class="me-3"><i class="fas fa-circle text-success me-1" style="font-size:6px;"></i><code>{{ url('/iclock/devicecmd') }}</code></span>
            <span><i class="fas fa-circle text-primary me-1" style="font-size:6px;"></i>Webhook JSON: <code>{{ url('/attendance/fingerprint/webhook') }}</code></span>
        </div>
    </div>
</div>

{{-- ===== Summary cards ===== --}}
<div class="row g-4 mb-6">
    <div class="col-6 col-xl-3">
        <div class="ml-stat-card total">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <span class="ml-stat-icon"><i class="fas fa-bolt"></i></span>
            </div>
            <div class="ml-stat-label">Total Scan Hari Ini</div>
            <div class="ml-stat-value" id="summary_total">-</div>
            <div class="ml-stat-meta">Semua request masuk</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="ml-stat-card success">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <span class="ml-stat-icon"><i class="fas fa-check-circle"></i></span>
            </div>
            <div class="ml-stat-label">Berhasil</div>
            <div class="ml-stat-value" id="summary_success">-</div>
            <div class="ml-stat-meta">Berhasil tercatat sebagai scan</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="ml-stat-card failed">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <span class="ml-stat-icon"><i class="fas fa-exclamation-triangle"></i></span>
            </div>
            <div class="ml-stat-label">Gagal / Error</div>
            <div class="ml-stat-value" id="summary_failed">-</div>
            <div class="ml-stat-meta">Validasi / unauthorized / error</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="ml-stat-card heartbeat">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <span class="ml-stat-icon"><i class="fas fa-heartbeat"></i></span>
            </div>
            <div class="ml-stat-label">Koneksi Mesin</div>
            <div class="ml-stat-value" id="summary_heartbeat">-</div>
            <div class="ml-stat-meta">Heartbeat / polling mesin</div>
        </div>
    </div>
</div>

{{-- ===== Filter ===== --}}
<div class="ml-filter">
    <div class="ml-filter-head"><i class="fas fa-filter"></i> Filter Log</div>
    <div class="row g-3 align-items-end">
        <div class="col-6 col-md-3 col-lg-2">
            <label class="form-label fw-bold fs-8">Dari Tanggal</label>
            <input type="text" id="filter_date_from" class="form-control form-control-solid js-date"
                placeholder="YYYY-MM-DD" value="{{ date('Y-m-d') }}">
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <label class="form-label fw-bold fs-8">Sampai Tanggal</label>
            <input type="text" id="filter_date_to" class="form-control form-control-solid js-date"
                placeholder="YYYY-MM-DD" value="{{ date('Y-m-d') }}">
        </div>
        <div class="col-12 col-md-6 col-lg-4">
            <label class="form-label fw-bold fs-8">Device</label>
            <select id="filter_device" class="form-select form-select-solid">
                <option value="">Semua Device</option>
                @foreach($devices as $device)
                    <option value="{{ $device->id }}">{{ $device->name }}{{ $device->serial_number ? ' (SN: '.$device->serial_number.')' : '' }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-6 col-lg-3">
            <label class="form-label fw-bold fs-8">Status</label>
            <select id="filter_status" class="form-select form-select-solid">
                <option value="">Semua Status</option>
                @foreach($statusOptions as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-lg-1">
            <button type="button" class="btn btn-primary w-100" id="btn_apply_filter">
                <i class="fas fa-search me-1"></i>Cari
            </button>
        </div>
    </div>
</div>

{{-- ===== Table ===== --}}
<div class="card shadow-sm">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
            <div>
                <h3 class="fw-bolder mb-1" style="font-size:1.05rem;">Riwayat Request Mesin</h3>
                <div class="text-muted fs-8">Klik tombol <i class="fas fa-search"></i> di kolom Detail untuk melihat payload request & response.</div>
            </div>
            <div class="text-muted fs-8"><i class="fas fa-clock me-1"></i>Auto-refresh setiap 30 detik (saat di halaman 1)</div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-row-bordered align-middle" id="machine_logs_table">
                <thead>
                    <tr>
                        <th class="text-nowrap">Waktu</th>
                        <th class="text-nowrap">Sumber</th>
                        <th class="text-nowrap">IP Address</th>
                        <th class="text-nowrap">Device</th>
                        <th class="text-nowrap">User ID Mesin</th>
                        <th class="text-nowrap">HTTP</th>
                        <th class="text-nowrap">Status</th>
                        <th class="text-nowrap">Raw Log</th>
                        <th class="text-nowrap text-center">Detail</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
        <div class="d-flex justify-content-between align-items-center mt-4 flex-wrap gap-2">
            <div class="text-muted fs-7" id="table_info_text"></div>
            <div id="table_pagination" class="d-flex flex-wrap gap-1"></div>
        </div>
    </div>
</div>

{{-- ===== Modal: Detail Payload ===== --}}
<div class="modal fade" id="modal_payload" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title fw-bold mb-1"><i class="fas fa-search-plus text-primary me-2"></i>Detail Request Mesin</h5>
                    <div class="text-muted fs-8">Payload mentah dari mesin & response yang dikirim server.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-4">
                    <div class="ml-section-title">Info</div>
                    <table class="table table-sm table-borderless mb-0" id="modal_info_table">
                        <tr><td class="text-muted fw-bold" style="width:160px">Waktu</td><td id="modal_created_at">-</td></tr>
                        <tr><td class="text-muted fw-bold">IP Address</td><td><code id="modal_ip">-</code></td></tr>
                        <tr><td class="text-muted fw-bold">Device</td><td id="modal_device">-</td></tr>
                        <tr><td class="text-muted fw-bold">User ID Mesin</td><td><code id="modal_device_user_id">-</code></td></tr>
                        <tr><td class="text-muted fw-bold">HTTP Status</td><td id="modal_http_status">-</td></tr>
                        <tr><td class="text-muted fw-bold">Status</td><td id="modal_status">-</td></tr>
                        <tr><td class="text-muted fw-bold">Raw Log ID</td><td id="modal_raw_log_id">-</td></tr>
                    </table>
                </div>
                <div class="mb-4">
                    <div class="ml-section-title">Request Payload (Data dari Mesin)</div>
                    <pre class="payload-pre" id="modal_request_payload">-</pre>
                </div>
                <div>
                    <div class="ml-section-title">Response Payload (Jawaban Server)</div>
                    <pre class="payload-pre" id="modal_response_payload">-</pre>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    const DATA_URL = '{{ route('admin.attendance.machine-logs.data') }}';

    let currentPage = 1;
    let perPage = 30;
    let totalCount = 0;
    let isLoading = false;

    const statusConfig = {
        success:           { label: 'Berhasil',              cls: 'bg-success text-white' },
        heartbeat:         { label: 'Koneksi Mesin',         cls: 'bg-info text-white' },
        command_poll:      { label: 'Mesin Polling',         cls: 'bg-info text-white' },
        device_command:    { label: 'Hasil Command',         cls: 'bg-info text-white' },
        empty_payload:     { label: 'Payload Kosong',        cls: 'bg-light text-dark' },
        unsupported_table: { label: 'Tabel ADMS Lain',       cls: 'bg-light text-dark' },
        unauthorized:      { label: 'Unauthorized',          cls: 'bg-danger text-white' },
        device_not_found:  { label: 'Device Tdk Ditemukan',  cls: 'bg-warning text-dark' },
        validation_error:  { label: 'Validasi Gagal',        cls: 'bg-warning text-dark' },
        error:             { label: 'Error Server',          cls: 'bg-danger text-white' },
    };

    function getFilters() {
        return {
            date_from: document.getElementById('filter_date_from').value,
            date_to:   document.getElementById('filter_date_to').value,
            device_id: document.getElementById('filter_device').value,
            status:    document.getElementById('filter_status').value,
        };
    }

    function buildStatusBadge(status) {
        const cfg = statusConfig[status] || { label: status, cls: 'bg-secondary text-white' };
        return `<span class="status-badge ${cfg.cls}">${cfg.label}</span>`;
    }

    function buildHttpBadge(code) {
        if (!code) return '-';
        const cls = code < 300 ? 'text-success fw-bold' : (code < 500 ? 'text-warning fw-bold' : 'text-danger fw-bold');
        return `<span class="${cls}">${code}</span>`;
    }

    function loadTable() {
        if (isLoading) return;
        isLoading = true;

        const tbody = document.querySelector('#machine_logs_table tbody');
        tbody.innerHTML = '<tr><td colspan="9"><div class="ml-empty"><div class="spinner-border spinner-border-sm text-primary"></div><div class="mt-2">Memuat data...</div></div></td></tr>';

        const params = new URLSearchParams({
            page: currentPage,
            per_page: perPage,
            ...getFilters(),
        });

        fetch(`${DATA_URL}?${params}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(res => {
                isLoading = false;
                const rows = res.data ?? [];
                totalCount = res.total ?? 0;

                renderTable(rows);
                renderPagination(res);
                loadSummary();
            })
            .catch(() => {
                isLoading = false;
                tbody.innerHTML = '<tr><td colspan="9"><div class="ml-empty text-danger"><i class="fas fa-exclamation-triangle"></i><div>Gagal memuat data.</div></div></td></tr>';
            });
    }

    function renderTable(rows) {
        const tbody = document.querySelector('#machine_logs_table tbody');
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="9"><div class="ml-empty"><i class="fas fa-inbox"></i><div>Tidak ada log untuk filter ini.</div></div></td></tr>';
            return;
        }

        tbody.innerHTML = rows.map(row => {
            const source = row.request_payload?.source === 'adms'
                ? '<span class="badge bg-primary">ADMS</span>'
                : '<span class="badge bg-secondary">Webhook</span>';
            return `
            <tr>
                <td class="text-nowrap fw-semibold">${row.created_at ?? '-'}</td>
                <td>${source}</td>
                <td class="text-nowrap"><code>${row.ip_address ?? '-'}</code></td>
                <td class="text-nowrap">${escHtml(row.device ?? '-')}</td>
                <td class="text-nowrap"><code>${escHtml(row.device_user_id ?? '-')}</code></td>
                <td>${buildHttpBadge(row.http_status)}</td>
                <td>${buildStatusBadge(row.status)}</td>
                <td>${row.raw_log_id ? `<span class="badge badge-light-success">#${row.raw_log_id}</span>` : '<span class="text-muted">-</span>'}</td>
                <td class="text-center">
                    <button class="btn btn-sm btn-light-primary btn-detail" data-row='${escAttr(JSON.stringify(row))}' title="Lihat detail">
                        <i class="fas fa-search"></i>
                    </button>
                </td>
            </tr>`;
        }).join('');

        document.querySelectorAll('.btn-detail').forEach(btn => {
            btn.addEventListener('click', () => openModal(JSON.parse(btn.dataset.row)));
        });
    }

    function renderPagination(res) {
        const lastPage = res.last_page ?? 1;
        const from = res.from ?? 0;
        const to = res.to ?? 0;
        const total = res.total ?? 0;

        document.getElementById('table_info_text').textContent =
            total ? `Menampilkan ${from}–${to} dari ${total} data` : '';

        const container = document.getElementById('table_pagination');
        let html = '';
        if (lastPage > 1) {
            html += `<button class="btn btn-sm btn-light" ${currentPage <= 1 ? 'disabled' : ''} data-page="${currentPage - 1}"><i class="fas fa-chevron-left"></i></button>`;
            for (let p = Math.max(1, currentPage - 2); p <= Math.min(lastPage, currentPage + 2); p++) {
                html += `<button class="btn btn-sm ${p === currentPage ? 'btn-primary' : 'btn-light'}" data-page="${p}">${p}</button>`;
            }
            html += `<button class="btn btn-sm btn-light" ${currentPage >= lastPage ? 'disabled' : ''} data-page="${currentPage + 1}"><i class="fas fa-chevron-right"></i></button>`;
        }
        container.innerHTML = html;
        container.querySelectorAll('button[data-page]').forEach(btn => {
            btn.addEventListener('click', () => { currentPage = parseInt(btn.dataset.page); loadTable(); });
        });
    }

    const SUMMARY_URL = '{{ route('admin.attendance.machine-logs.summary') }}';

    function loadSummary() {
        fetch(SUMMARY_URL, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(res => {
                document.getElementById('summary_total').textContent     = res.total     ?? 0;
                document.getElementById('summary_success').textContent   = res.success   ?? 0;
                document.getElementById('summary_failed').textContent    = res.failed    ?? 0;
                document.getElementById('summary_heartbeat').textContent = res.heartbeat ?? 0;
            })
            .catch(() => {});
    }

    function openModal(row) {
        document.getElementById('modal_created_at').textContent = row.created_at ?? '-';
        document.getElementById('modal_ip').textContent = row.ip_address ?? '-';
        document.getElementById('modal_device').textContent = row.device ?? '-';
        document.getElementById('modal_device_user_id').textContent = row.device_user_id ?? '-';
        document.getElementById('modal_http_status').textContent = row.http_status ?? '-';
        document.getElementById('modal_status').innerHTML = buildStatusBadge(row.status);
        document.getElementById('modal_raw_log_id').textContent = row.raw_log_id ? '#' + row.raw_log_id : '-';
        document.getElementById('modal_request_payload').textContent =
            row.request_payload ? JSON.stringify(row.request_payload, null, 2) : '-';
        document.getElementById('modal_response_payload').textContent =
            row.response_payload ? JSON.stringify(row.response_payload, null, 2) : '-';

        const modal = new bootstrap.Modal(document.getElementById('modal_payload'));
        modal.show();
    }

    function escHtml(str) {
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    function escAttr(str) {
        return String(str).replace(/'/g,'&#39;').replace(/"/g,'&quot;');
    }

    document.getElementById('btn_apply_filter').addEventListener('click', () => { currentPage = 1; loadTable(); });
    document.getElementById('btn_refresh_machine_logs').addEventListener('click', () => { currentPage = 1; loadTable(); });

    // flatpickr untuk filter tanggal
    if (typeof flatpickr !== 'undefined') {
        document.querySelectorAll('.js-date').forEach((input) => {
            flatpickr(input, { dateFormat: 'Y-m-d', allowInput: true });
        });
    }

    // select2
    if (typeof $ !== 'undefined' && $.fn.select2) {
        ['filter_device','filter_status'].forEach((id) => {
            const el = document.getElementById(id);
            if (!el) return;
            $(el).select2({ width: '100%', allowClear: true, placeholder: el.querySelector('option[value=""]')?.textContent || 'Pilih' });
        });
    }

    // Auto-refresh setiap 30 detik
    setInterval(() => { if (currentPage === 1) loadTable(); }, 30000);

    loadTable();
})();
</script>
@endpush
