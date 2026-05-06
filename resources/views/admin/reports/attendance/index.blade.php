@extends('layouts.admin')

@section('title', 'Laporan Absensi')
@section('page_title', 'Laporan Absensi')

@push('styles')
<style>
    /* ===== Hero ===== */
    .ar-hero {
        background: linear-gradient(135deg, #f8faff 0%, #fff 60%);
        border: 1px solid #eef0f8;
        border-radius: 1rem;
        padding: 1.25rem 1.5rem;
        margin-bottom: 1.5rem;
    }
    .ar-hero-eyebrow {
        font-size: .72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: #1b84ff;
        margin-bottom: .35rem;
    }
    .ar-hero h1 { font-size: 1.5rem; font-weight: 800; color: #1e1e2d; margin: 0; }
    .ar-hero p  { color: #7e8299; font-size: .875rem; margin-top: .25rem; }

    /* ===== Filter ===== */
    .ar-filter-head {
        display: flex;
        align-items: center;
        gap: .65rem;
        margin-bottom: .85rem;
        font-weight: 700;
        color: #3f4254;
    }
    .ar-filter-head i { color: #1b84ff; }

    .attendance-report-filter {
        display: grid;
        grid-template-columns: minmax(220px, 1.2fr) repeat(6, minmax(150px, 1fr)) auto;
        gap: 12px;
        align-items: end;
    }

    .attendance-report-note {
        border-radius: 12px;
        background: linear-gradient(135deg, #eff8ff, #f8fbff);
        color: #3f4254;
        padding: 12px 16px;
        font-size: 13px;
        line-height: 1.6;
        border: 1px solid #d8eaff;
    }
    .attendance-report-note i { color: #1b84ff; }

    /* ===== KPI cards ===== */
    .attendance-report-kpi {
        position: relative;
        border: 1px solid #eef0f8;
        border-radius: 1rem;
        background: #fff;
        padding: 1.25rem;
        height: 100%;
        overflow: hidden;
        transition: transform .15s ease, box-shadow .15s ease;
    }
    .attendance-report-kpi:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 22px rgba(31, 41, 55, .06);
    }
    .attendance-report-kpi::before {
        content: "";
        position: absolute;
        top: 0; left: 0;
        height: 4px;
        width: 100%;
    }
    .attendance-report-kpi.kpi-employees::before { background: #1b84ff; }
    .attendance-report-kpi.kpi-rate::before      { background: #50cd89; }
    .attendance-report-kpi.kpi-problem::before   { background: #f1416c; }
    .attendance-report-kpi.kpi-overtime::before  { background: #7239ea; }

    .attendance-report-kpi-icon {
        width: 42px;
        height: 42px;
        border-radius: .65rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        margin-bottom: .65rem;
    }
    .kpi-employees .attendance-report-kpi-icon { background: #eaf3ff; color: #1b84ff; }
    .kpi-rate .attendance-report-kpi-icon      { background: #e8fbf1; color: #1aae6f; }
    .kpi-problem .attendance-report-kpi-icon   { background: #fde8ef; color: #d33269; }
    .kpi-overtime .attendance-report-kpi-icon  { background: #f0eafc; color: #6f37df; }

    .attendance-report-kpi-label {
        color: #7e8299;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .04em;
        margin-bottom: 8px;
    }
    .attendance-report-kpi-value {
        color: #181c32;
        font-size: 1.85rem;
        line-height: 1;
        font-weight: 800;
    }
    .attendance-report-kpi-note {
        margin-top: 8px;
        color: #a1a5b7;
        font-size: 12px;
    }

    /* ===== Detail modal table ===== */
    .attendance-detail-table {
        max-height: 520px;
        overflow: auto;
    }

    /* ===== Table polish ===== */
    #attendance_report_table thead th {
        font-size: .72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: #7e8299;
        background: #f9fafc;
    }

    /* ===== Responsive ===== */
    @media (max-width: 1400px) {
        .attendance-report-filter {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }
    @media (max-width: 991.98px) {
        .attendance-report-filter {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
    @media (max-width: 575.98px) {
        .attendance-report-filter {
            grid-template-columns: 1fr;
        }
        .ar-hero { padding: 1rem; }
        .ar-hero h1 { font-size: 1.2rem; }
        .attendance-report-kpi-value { font-size: 1.5rem; }
    }
</style>
@endpush

@section('content')

{{-- ===== Hero ===== --}}
<div class="ar-hero">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
            <div class="ar-hero-eyebrow"><i class="fas fa-chart-line me-1"></i>Laporan</div>
            <h1><i class="fas fa-clipboard-list me-2 text-primary"></i>Laporan Absensi</h1>
            <p class="mb-0">Rekap kehadiran, keterlambatan, alpha, dan jam lembur per karyawan dalam satu periode.</p>
        </div>
    </div>
</div>

{{-- ===== Filter ===== --}}
<div class="card mb-6 shadow-sm">
    <div class="card-body pt-5 pb-5">
        <div class="ar-filter-head"><i class="fas fa-filter"></i> Filter Laporan</div>
        <div class="attendance-report-filter">
            <div>
                <label class="form-label fw-bold fs-8">Cari</label>
                <input type="text" id="filter_search" class="form-control form-control-solid" placeholder="Kode/nama karyawan, area, jabatan">
            </div>
            <div>
                <label class="form-label fw-bold fs-8">Dari</label>
                <input type="text" id="filter_date_from" class="form-control form-control-solid" placeholder="YYYY-MM-DD">
            </div>
            <div>
                <label class="form-label fw-bold fs-8">Sampai</label>
                <input type="text" id="filter_date_to" class="form-control form-control-solid" placeholder="YYYY-MM-DD">
            </div>
            <div>
                <label class="form-label fw-bold fs-8">Karyawan</label>
                <select id="filter_employee" class="form-select form-select-solid">
                    <option value="">Semua karyawan</option>
                    @foreach($employees as $employee)
                        <option value="{{ $employee->id }}">{{ $employee->employee_code }} - {{ $employee->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label fw-bold fs-8">Area</label>
                <select id="filter_area" class="form-select form-select-solid">
                    <option value="">Semua area</option>
                    @foreach($areas as $area)
                        <option value="{{ $area->id }}">{{ $area->code }} - {{ $area->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label fw-bold fs-8">Jabatan</label>
                <select id="filter_position" class="form-select form-select-solid">
                    <option value="">Semua jabatan</option>
                    @foreach($positions as $position)
                        <option value="{{ $position->id }}">{{ $position->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label fw-bold fs-8">Status Laporan</label>
                <select id="filter_report_status" class="form-select form-select-solid">
                    <option value="">Semua</option>
                    <option value="has_absent">Ada Alpha</option>
                    <option value="has_late">Ada Terlambat</option>
                    <option value="has_incomplete">Scan Tidak Lengkap</option>
                    <option value="has_overtime_pending">Lembur Pending</option>
                    <option value="good_attendance">Kehadiran Baik</option>
                </select>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-primary" id="filter_apply">
                    <i class="fas fa-search me-1"></i>Terapkan
                </button>
                <button type="button" class="btn btn-light" id="filter_reset" title="Reset filter">
                    <i class="fas fa-undo"></i>
                </button>
            </div>
        </div>
        <div class="attendance-report-note mt-5">
            <i class="fas fa-info-circle me-1"></i>
            Persentase hadir dihitung dari <strong>hari kerja terjadwal</strong>. Status hadir mencakup Hadir dan Terlambat. Alpha dan scan tidak lengkap ditampilkan terpisah agar evaluasi HR tidak bercampur dengan hari libur/cuti.
        </div>
    </div>
</div>

{{-- ===== KPI Cards ===== --}}
<div class="row g-4 mb-6">
    <div class="col-12 col-md-6 col-xl-3">
        <div class="attendance-report-kpi kpi-employees">
            <span class="attendance-report-kpi-icon"><i class="fas fa-users"></i></span>
            <div class="attendance-report-kpi-label">Karyawan</div>
            <div class="attendance-report-kpi-value" id="summary_employees">0</div>
            <div class="attendance-report-kpi-note" id="summary_period">Periode -</div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
        <div class="attendance-report-kpi kpi-rate">
            <span class="attendance-report-kpi-icon"><i class="fas fa-percentage"></i></span>
            <div class="attendance-report-kpi-label">Persentase Hadir</div>
            <div class="attendance-report-kpi-value text-success" id="summary_attendance_rate">0%</div>
            <div class="attendance-report-kpi-note" id="summary_scheduled">0 hari kerja terjadwal</div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
        <div class="attendance-report-kpi kpi-problem">
            <span class="attendance-report-kpi-icon"><i class="fas fa-exclamation-triangle"></i></span>
            <div class="attendance-report-kpi-label">Masalah Absensi</div>
            <div class="attendance-report-kpi-value text-danger" id="summary_problem_days">0</div>
            <div class="attendance-report-kpi-note" id="summary_problem_meta">Alpha 0 | Tidak lengkap 0</div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
        <div class="attendance-report-kpi kpi-overtime">
            <span class="attendance-report-kpi-icon"><i class="fas fa-business-time"></i></span>
            <div class="attendance-report-kpi-label">Lembur</div>
            <div class="attendance-report-kpi-value" id="summary_overtime" style="color:#6f37df">0 jam</div>
            <div class="attendance-report-kpi-note" id="summary_overtime_meta">Pending 0 jam</div>
        </div>
    </div>
</div>

{{-- ===== Table ===== --}}
<div class="card shadow-sm">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <h3 class="card-label fw-bolder mb-0"><i class="fas fa-table text-primary me-2"></i>Ringkasan Per Karyawan</h3>
        </div>
    </div>
    <div class="card-body py-6">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-4" id="attendance_report_table">
                <thead>
                    <tr>
                        <th>Karyawan</th>
                        <th>Area / Jabatan</th>
                        <th class="text-end">Hari Kerja</th>
                        <th class="text-end">Hadir</th>
                        <th class="text-end">Telat</th>
                        <th class="text-end">Alpha</th>
                        <th class="text-end">Tidak Lengkap</th>
                        <th class="text-end">Cuti</th>
                        <th class="text-end">Rate</th>
                        <th class="text-end">Jam Kerja</th>
                        <th class="text-end">Lembur</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

{{-- ===== Detail Modal ===== --}}
<div class="modal fade" id="attendance_detail_modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 class="fw-bolder mb-1" id="attendance_detail_title"><i class="fas fa-user-clock text-primary me-2"></i>Detail Absensi</h2>
                    <div class="text-muted" id="attendance_detail_subtitle">-</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="attendance-detail-table table-responsive">
                    <table class="table table-row-dashed align-middle fs-7">
                        <thead>
                            <tr class="text-gray-400 fw-bolder text-uppercase">
                                <th>Tanggal</th>
                                <th>Jadwal</th>
                                <th>Shift</th>
                                <th>Masuk</th>
                                <th>Pulang</th>
                                <th>Status</th>
                                <th class="text-end">Telat</th>
                                <th class="text-end">Pulang Cepat</th>
                                <th class="text-end">Kerja</th>
                                <th class="text-end">Lembur Hitung</th>
                                <th class="text-end">Lembur Approved</th>
                                <th>Catatan</th>
                            </tr>
                        </thead>
                        <tbody id="attendance_detail_rows"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const dataUrl = '{{ $dataUrl }}';

document.addEventListener('DOMContentLoaded', () => {
    const tableEl = $('#attendance_report_table');
    const searchEl = document.getElementById('filter_search');
    const dateFromEl = document.getElementById('filter_date_from');
    const dateToEl = document.getElementById('filter_date_to');
    const employeeEl = document.getElementById('filter_employee');
    const areaEl = document.getElementById('filter_area');
    const positionEl = document.getElementById('filter_position');
    const reportStatusEl = document.getElementById('filter_report_status');
    const applyBtn = document.getElementById('filter_apply');
    const resetBtn = document.getElementById('filter_reset');
    const detailModalEl = document.getElementById('attendance_detail_modal');
    const detailModal = detailModalEl ? new bootstrap.Modal(detailModalEl) : null;
    let currentRows = [];

    if (typeof flatpickr !== 'undefined') {
        flatpickr(dateFromEl, { dateFormat: 'Y-m-d', allowInput: true });
        flatpickr(dateToEl, { dateFormat: 'Y-m-d', allowInput: true });
    }

    if (typeof $ !== 'undefined' && $.fn.select2) {
        [employeeEl, areaEl, positionEl, reportStatusEl].forEach((select) => {
            $(select).select2({ width: '100%', allowClear: true, placeholder: select.querySelector('option[value=""]')?.textContent || 'Pilih' });
        });
    }

    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const hours = (minutes) => `${(Number(minutes || 0) / 60).toFixed(2)} jam`;
    const pct = (value) => `${Number(value || 0).toFixed(2)}%`;

    const statusLabel = (value) => ({
        present: 'Hadir',
        late: 'Terlambat',
        absent: 'Alpha',
        incomplete: 'Tidak Lengkap',
        leave: 'Cuti/Izin',
        holiday: 'Libur',
        day_off: 'Libur',
        none: 'Tidak Ada',
        pending: 'Pending',
        approved: 'Approved',
        rejected: 'Rejected',
        work: 'Masuk',
    }[value] || value || '-');

    const statusBadge = (value) => {
        const cls = {
            present: 'badge-light-success',
            late: 'badge-light-warning',
            absent: 'badge-light-danger',
            incomplete: 'badge-light-primary',
            leave: 'badge-light-info',
            holiday: 'badge-light-danger',
            day_off: 'badge-light-secondary',
            pending: 'badge-light-warning',
            approved: 'badge-light-success',
            rejected: 'badge-light-danger',
            none: 'badge-light-secondary',
        }[value] || 'badge-light';

        return `<span class="badge ${cls}">${escapeHtml(statusLabel(value))}</span>`;
    };

    const updateSummary = (summary = {}, period = {}) => {
        document.getElementById('summary_employees').textContent = Number(summary.employees || 0).toLocaleString('id-ID');
        document.getElementById('summary_period').textContent = `Periode ${period.from || '-'} sampai ${period.to || '-'}`;
        document.getElementById('summary_attendance_rate').textContent = pct(summary.attendance_rate);
        document.getElementById('summary_scheduled').textContent = `${Number(summary.scheduled_work_days || 0).toLocaleString('id-ID')} hari kerja terjadwal`;
        document.getElementById('summary_problem_days').textContent = Number((summary.absent_days || 0) + (summary.incomplete_days || 0)).toLocaleString('id-ID');
        document.getElementById('summary_problem_meta').textContent = `Alpha ${Number(summary.absent_days || 0).toLocaleString('id-ID')} | Tidak lengkap ${Number(summary.incomplete_days || 0).toLocaleString('id-ID')}`;
        document.getElementById('summary_overtime').textContent = hours(summary.approved_overtime_minutes);
        document.getElementById('summary_overtime_meta').textContent = `Pending ${hours(summary.pending_overtime_minutes)}`;
    };

    const table = tableEl.DataTable({
        processing: true,
        serverSide: true,
        dom: 'rtip',
        language: {
            processing: '<div class="text-muted py-4"><span class="spinner-border spinner-border-sm me-2"></span>Memuat data...</div>',
            emptyTable: '<div class="text-center py-8 text-muted"><i class="fas fa-inbox fs-2 d-block mb-2"></i>Belum ada data</div>',
            zeroRecords: '<div class="text-center py-8 text-muted"><i class="fas fa-search fs-2 d-block mb-2"></i>Tidak ada data yang cocok</div>',
            info: 'Menampilkan _START_–_END_ dari _TOTAL_ data',
            infoEmpty: '0 data',
            infoFiltered: '(difilter dari _MAX_ data)',
            paginate: { first: '«', last: '»', next: '›', previous: '‹' },
        },
        ajax: {
            url: dataUrl,
            dataSrc: (json) => {
                currentRows = json.data || [];
                updateSummary(json.summary || {}, json.period || {});
                return currentRows;
            },
            data: (params) => {
                params.q = searchEl.value || '';
                params.date_from = dateFromEl.value || '';
                params.date_to = dateToEl.value || '';
                params.employee_id = employeeEl.value || '';
                params.area_id = areaEl.value || '';
                params.position_id = positionEl.value || '';
                params.report_status = reportStatusEl.value || '';
                params.employment_status = 'active';
            },
        },
        columns: [
            {
                data: null,
                render: (row) => `<div class="fw-bolder text-gray-900">${escapeHtml(row.employee_label)}</div><div class="text-muted fs-8">${escapeHtml(row.employment_status)}</div>`,
            },
            {
                data: null,
                render: (row) => `<div>${escapeHtml(row.area)}</div><div class="text-muted fs-8">${escapeHtml(row.position)}</div>`,
            },
            { data: 'scheduled_work_days', className: 'text-end' },
            { data: 'present_days', className: 'text-end' },
            { data: 'late_days', className: 'text-end' },
            { data: 'absent_days', className: 'text-end' },
            { data: 'incomplete_days', className: 'text-end' },
            { data: 'leave_days', className: 'text-end' },
            { data: 'attendance_rate', className: 'text-end', render: pct },
            { data: 'work_minutes', className: 'text-end', render: hours },
            {
                data: null,
                className: 'text-end',
                render: (row) => `<div>${hours(row.approved_overtime_minutes)}</div><div class="text-muted fs-8">Pending ${hours(row.pending_overtime_minutes)}</div>`,
            },
            {
                data: null,
                className: 'text-end',
                orderable: false,
                render: (row) => `<button type="button" class="btn btn-sm btn-light-primary btn-detail-attendance" data-id="${row.employee_id}"><i class="fas fa-eye me-1"></i>Detail</button>`,
            },
        ],
        order: [],
    });

    const reload = () => table.ajax.reload();
    applyBtn.addEventListener('click', reload);
    [employeeEl, areaEl, positionEl, reportStatusEl].forEach((el) => el.addEventListener('change', reload));
    searchEl.addEventListener('keyup', () => reload());
    resetBtn.addEventListener('click', () => {
        searchEl.value = '';
        dateFromEl.value = '';
        dateToEl.value = '';
        [employeeEl, areaEl, positionEl, reportStatusEl].forEach((select) => {
            select.value = '';
            if (typeof $ !== 'undefined' && $(select).data('select2')) {
                $(select).trigger('change.select2');
            }
        });
        reload();
    });

    tableEl.on('click', '.btn-detail-attendance', function () {
        const row = currentRows.find((item) => String(item.employee_id) === String(this.dataset.id));
        if (!row) return;

        document.getElementById('attendance_detail_title').innerHTML =
            `<i class="fas fa-user-clock text-primary me-2"></i>${escapeHtml(row.employee_label)}`;
        document.getElementById('attendance_detail_subtitle').textContent = `${row.area} | ${row.position}`;
        const detailRows = Array.isArray(row.detail_rows) && row.detail_rows.length
            ? row.detail_rows.map((detail) => `
                <tr>
                    <td class="fw-semibold">${escapeHtml(detail.date)}</td>
                    <td>${escapeHtml(statusLabel(detail.schedule_type))}</td>
                    <td>${escapeHtml(detail.shift)}</td>
                    <td>${escapeHtml(detail.check_in_at)}</td>
                    <td>${escapeHtml(detail.check_out_at)}</td>
                    <td>${statusBadge(detail.status)}</td>
                    <td class="text-end">${Number(detail.late_minutes || 0)}</td>
                    <td class="text-end">${Number(detail.early_leave_minutes || 0)}</td>
                    <td class="text-end">${hours(detail.work_minutes)}</td>
                    <td class="text-end">${hours(detail.calculated_overtime_minutes)}</td>
                    <td class="text-end">${hours(detail.approved_overtime_minutes)}<div>${statusBadge(detail.overtime_status)}</div></td>
                    <td>${escapeHtml(detail.note || '-')}</td>
                </tr>
            `).join('')
            : '<tr><td colspan="12" class="text-center text-muted py-8"><i class="fas fa-inbox fs-2 d-block mb-2 opacity-50"></i>Tidak ada jadwal atau rekap absensi pada periode ini.</td></tr>';
        document.getElementById('attendance_detail_rows').innerHTML = detailRows;
        detailModal?.show();
    });
});
</script>
@endpush
