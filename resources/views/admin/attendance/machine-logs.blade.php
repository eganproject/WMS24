@extends('layouts.admin')

@section('title', 'Log Mesin Absensi')
@section('page_title', 'Log Mesin Absensi')

@push('styles')
<style>
    .payload-cell {
        max-width: 280px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        cursor: pointer;
    }
    .status-badge {
        font-size: 0.75rem;
        font-weight: 600;
        padding: 0.3rem 0.65rem;
        border-radius: 0.375rem;
    }
    pre.payload-pre {
        background: #f1f3f7;
        border-radius: 0.5rem;
        padding: 1rem;
        font-size: 0.8rem;
        max-height: 400px;
        overflow-y: auto;
        white-space: pre-wrap;
        word-break: break-all;
    }
    .summary-card {
        border-left: 4px solid;
        border-radius: 0.5rem;
    }
    .summary-card.success { border-color: #50cd89; }
    .summary-card.failed  { border-color: #f1416c; }
    .summary-card.total   { border-color: #009ef7; }
</style>
@endpush

@section('content')
@php $sectionLinks = $sectionLinks ?? []; @endphp

<div class="card mb-6">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <h2 class="fw-bolder mb-0">Log Webhook Mesin Absensi</h2>
        </div>
        <div class="card-toolbar">
            <button type="button" class="btn btn-light-primary" id="btn_refresh_machine_logs">
                <i class="fas fa-sync-alt me-1"></i> Refresh
            </button>
        </div>
    </div>
    <div class="card-body pt-2 pb-4">
        <div class="text-muted fs-7 mb-4">
            Halaman ini mencatat request dari mesin absensi Solution X100C ke endpoint ADMS
            <code>{{ url('/iclock/cdata') }}</code>, <code>{{ url('/iclock/getrequest') }}</code>,
            <code>{{ url('/iclock/devicecmd') }}</code>, serta endpoint JSON
            <code>{{ url('/attendance/fingerprint/webhook') }}</code>.
            Buka detail untuk melihat query, raw body, baris ATTLOG, dan response server.
        </div>

        <div class="alert alert-info d-flex align-items-start gap-3 mb-6">
            <i class="fas fa-network-wired fs-2 mt-1"></i>
            <div>
                <div class="fw-bold mb-1">Konfigurasi mesin Solution X100C</div>
                <div class="fs-7">
                    Arahkan server/ADMS mesin ke domain aplikasi ini dengan path <code>/iclock/cdata</code>.
                    Nomor serial mesin harus sama dengan kolom <strong>serial_number</strong> di menu Device Absensi.
                </div>
            </div>
        </div>

        {{-- Section navigation --}}
        <div class="d-flex flex-wrap gap-2 mb-6">
            @foreach($sectionLinks as $sectionKey => $section)
                <a href="{{ route($section['route']) }}" class="btn btn-sm btn-light">
                    <i class="{{ $section['icon'] }} me-1"></i>{{ $section['label'] }}
                </a>
            @endforeach
            <a href="{{ route('admin.attendance.machine-logs.index') }}" class="btn btn-sm btn-primary">
                <i class="fas fa-satellite-dish me-1"></i>Machine Log
            </a>
        </div>

        {{-- Summary cards --}}
        <div class="row g-4 mb-6">
            <div class="col-6 col-md-3">
                <div class="card summary-card total p-4">
                    <div class="text-muted fs-8 fw-bold text-uppercase mb-1">Total Scan Hari Ini</div>
                    <div class="fs-2 fw-bolder" id="summary_total">-</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card summary-card success p-4">
                    <div class="text-muted fs-8 fw-bold text-uppercase mb-1">Berhasil</div>
                    <div class="fs-2 fw-bolder text-success" id="summary_success">-</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card summary-card failed p-4">
                    <div class="text-muted fs-8 fw-bold text-uppercase mb-1">Gagal</div>
                    <div class="fs-2 fw-bolder text-danger" id="summary_failed">-</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card p-4" style="border-left:4px solid #009ef7;border-radius:.5rem">
                    <div class="text-muted fs-8 fw-bold text-uppercase mb-1">Koneksi Mesin</div>
                    <div class="fs-2 fw-bolder text-info" id="summary_heartbeat">-</div>
                </div>
            </div>
        </div>

        {{-- Filter --}}
        <div class="row g-3 mb-5 align-items-end">
            <div class="col-md-2">
                <label class="form-label fw-bold fs-7">Dari Tanggal</label>
                <input type="text" id="filter_date_from" class="form-control form-control-solid js-date"
                    placeholder="YYYY-MM-DD" value="{{ date('Y-m-d') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold fs-7">Sampai Tanggal</label>
                <input type="text" id="filter_date_to" class="form-control form-control-solid js-date"
                    placeholder="YYYY-MM-DD" value="{{ date('Y-m-d') }}">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold fs-7">Device</label>
                <select id="filter_device" class="form-select form-select-solid">
                    <option value="">Semua Device</option>
                    @foreach($devices as $device)
                        <option value="{{ $device->id }}">{{ $device->name }}{{ $device->serial_number ? ' (SN: '.$device->serial_number.')' : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold fs-7">Status</label>
                <select id="filter_status" class="form-select form-select-solid">
                    <option value="">Semua Status</option>
                    @foreach($statusOptions as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-1">
                <button type="button" class="btn btn-primary w-100" id="btn_apply_filter">Cari</button>
            </div>
        </div>

        {{-- Table --}}
        <div class="table-responsive">
            <table class="table table-hover table-row-bordered align-middle" id="machine_logs_table">
                <thead class="table-light">
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
            <div id="table_pagination" class="d-flex gap-1"></div>
        </div>
    </div>
</div>

{{-- Modal: Detail Payload --}}
<div class="modal fade" id="modal_payload" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Detail Request Mesin</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-4">
                    <div class="fw-bold fs-7 text-uppercase text-muted mb-2">Info</div>
                    <table class="table table-sm table-borderless" id="modal_info_table">
                        <tr><td class="text-muted fw-bold" style="width:160px">Waktu</td><td id="modal_created_at">-</td></tr>
                        <tr><td class="text-muted fw-bold">IP Address</td><td id="modal_ip">-</td></tr>
                        <tr><td class="text-muted fw-bold">Device</td><td id="modal_device">-</td></tr>
                        <tr><td class="text-muted fw-bold">User ID Mesin</td><td id="modal_device_user_id">-</td></tr>
                        <tr><td class="text-muted fw-bold">HTTP Status</td><td id="modal_http_status">-</td></tr>
                        <tr><td class="text-muted fw-bold">Status</td><td id="modal_status">-</td></tr>
                        <tr><td class="text-muted fw-bold">Raw Log ID</td><td id="modal_raw_log_id">-</td></tr>
                    </table>
                </div>
                <div class="mb-4">
                    <div class="fw-bold fs-7 text-uppercase text-muted mb-2">Request Payload (Data dari Mesin)</div>
                    <pre class="payload-pre" id="modal_request_payload">-</pre>
                </div>
                <div>
                    <div class="fw-bold fs-7 text-uppercase text-muted mb-2">Response Payload (Jawaban Server)</div>
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
        success:          { label: 'Berhasil',              cls: 'bg-success text-white' },
        heartbeat:        { label: 'Koneksi Mesin',         cls: 'bg-info text-white' },
        command_poll:      { label: 'Mesin Polling',         cls: 'bg-info text-white' },
        device_command:    { label: 'Hasil Command',         cls: 'bg-info text-white' },
        empty_payload:     { label: 'Payload Kosong',        cls: 'bg-light text-dark' },
        unsupported_table: { label: 'Tabel ADMS Lain',       cls: 'bg-light text-dark' },
        unauthorized:     { label: 'Unauthorized',          cls: 'bg-danger text-white' },
        device_not_found: { label: 'Device Tdk Ditemukan',  cls: 'bg-warning text-dark' },
        validation_error: { label: 'Validasi Gagal',        cls: 'bg-warning text-dark' },
        error:            { label: 'Error Server',          cls: 'bg-danger text-white' },
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
        tbody.innerHTML = '<tr><td colspan="9" class="text-center py-6 text-muted">Memuat data...</td></tr>';

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
                tbody.innerHTML = '<tr><td colspan="9" class="text-center py-6 text-danger">Gagal memuat data.</td></tr>';
            });
    }

    function renderTable(rows) {
        const tbody = document.querySelector('#machine_logs_table tbody');
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="9" class="text-center py-6 text-muted">Tidak ada data.</td></tr>';
            return;
        }

        tbody.innerHTML = rows.map(row => {
            const source = row.request_payload?.source === 'adms'
                ? '<span class="badge bg-primary">ADMS</span>'
                : '<span class="badge bg-secondary">Webhook</span>';
            return `
            <tr>
                <td class="text-nowrap">${row.created_at ?? '-'}</td>
                <td>${source}</td>
                <td class="text-nowrap"><code>${row.ip_address ?? '-'}</code></td>
                <td class="text-nowrap">${escHtml(row.device ?? '-')}</td>
                <td class="text-nowrap"><code>${escHtml(row.device_user_id ?? '-')}</code></td>
                <td>${buildHttpBadge(row.http_status)}</td>
                <td>${buildStatusBadge(row.status)}</td>
                <td>${row.raw_log_id ? `<span class="badge bg-light-success text-success">#${row.raw_log_id}</span>` : '<span class="text-muted">-</span>'}</td>
                <td class="text-center">
                    <button class="btn btn-sm btn-light-primary btn-detail" data-row='${escAttr(JSON.stringify(row))}'>
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
            html += `<button class="btn btn-sm btn-light" ${currentPage <= 1 ? 'disabled' : ''} data-page="${currentPage - 1}">&laquo;</button>`;
            for (let p = Math.max(1, currentPage - 2); p <= Math.min(lastPage, currentPage + 2); p++) {
                html += `<button class="btn btn-sm ${p === currentPage ? 'btn-primary' : 'btn-light'}" data-page="${p}">${p}</button>`;
            }
            html += `<button class="btn btn-sm btn-light" ${currentPage >= lastPage ? 'disabled' : ''} data-page="${currentPage + 1}">&raquo;</button>`;
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

    // Auto-refresh setiap 30 detik
    setInterval(() => { if (currentPage === 1) loadTable(); }, 30000);

    loadTable();
})();
</script>
@endpush
