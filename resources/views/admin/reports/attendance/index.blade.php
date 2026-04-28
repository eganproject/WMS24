@extends('layouts.admin')

@section('title', 'Laporan Absensi')
@section('page_title', 'Laporan Absensi')

@push('styles')
<style>
    .attendance-report-filter {
        display: grid;
        grid-template-columns: minmax(220px, 1.2fr) repeat(6, minmax(150px, 1fr)) auto;
        gap: 12px;
        align-items: end;
    }
    .attendance-report-kpi {
        border: 1px solid #e4e6ef;
        border-radius: 14px;
        background: #fff;
        padding: 18px 20px;
        height: 100%;
    }
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
        font-size: 28px;
        line-height: 1;
        font-weight: 800;
    }
    .attendance-report-kpi-note {
        margin-top: 8px;
        color: #7e8299;
        font-size: 12px;
    }
    .attendance-report-note {
        border-radius: 12px;
        background: #f1faff;
        color: #3f4254;
        padding: 14px 16px;
        font-size: 13px;
        line-height: 1.6;
    }
    .attendance-detail-table {
        max-height: 520px;
        overflow: auto;
    }
    @media (max-width: 1400px) {
        .attendance-report-filter {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }
    @media (max-width: 767.98px) {
        .attendance-report-filter {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush

@section('content')
<div class="card mb-6">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <h3 class="fw-bolder mb-0">Filter Laporan</h3>
        </div>
    </div>
    <div class="card-body pt-0">
        <div class="attendance-report-filter">
            <div>
                <label class="form-label text-muted fs-7">Cari</label>
                <input type="text" id="filter_search" class="form-control form-control-solid" placeholder="Kode/nama karyawan, area, jabatan">
            </div>
            <div>
                <label class="form-label text-muted fs-7">Dari</label>
                <input type="text" id="filter_date_from" class="form-control form-control-solid" placeholder="YYYY-MM-DD">
            </div>
            <div>
                <label class="form-label text-muted fs-7">Sampai</label>
                <input type="text" id="filter_date_to" class="form-control form-control-solid" placeholder="YYYY-MM-DD">
            </div>
            <div>
                <label class="form-label text-muted fs-7">Karyawan</label>
                <select id="filter_employee" class="form-select form-select-solid">
                    <option value="">Semua karyawan</option>
                    @foreach($employees as $employee)
                        <option value="{{ $employee->id }}">{{ $employee->employee_code }} - {{ $employee->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label text-muted fs-7">Area</label>
                <select id="filter_area" class="form-select form-select-solid">
                    <option value="">Semua area</option>
                    @foreach($areas as $area)
                        <option value="{{ $area->id }}">{{ $area->code }} - {{ $area->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label text-muted fs-7">Jabatan</label>
                <select id="filter_position" class="form-select form-select-solid">
                    <option value="">Semua jabatan</option>
                    @foreach($positions as $position)
                        <option value="{{ $position->id }}">{{ $position->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label text-muted fs-7">Status Laporan</label>
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
                <button type="button" class="btn btn-primary" id="filter_apply">Terapkan</button>
                <button type="button" class="btn btn-light" id="filter_reset">Reset</button>
            </div>
        </div>
        <div class="attendance-report-note mt-5">
            Persentase hadir dihitung dari <strong>hari kerja terjadwal</strong>. Status hadir mencakup Hadir dan Terlambat. Alpha dan scan tidak lengkap ditampilkan terpisah agar evaluasi HR tidak bercampur dengan hari libur/cuti.
        </div>
    </div>
</div>

<div class="row g-4 mb-6">
    <div class="col-md-6 col-xl-3">
        <div class="attendance-report-kpi">
            <div class="attendance-report-kpi-label">Karyawan</div>
            <div class="attendance-report-kpi-value" id="summary_employees">0</div>
            <div class="attendance-report-kpi-note" id="summary_period">Periode -</div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="attendance-report-kpi">
            <div class="attendance-report-kpi-label">Persentase Hadir</div>
            <div class="attendance-report-kpi-value text-success" id="summary_attendance_rate">0%</div>
            <div class="attendance-report-kpi-note" id="summary_scheduled">0 hari kerja terjadwal</div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="attendance-report-kpi">
            <div class="attendance-report-kpi-label">Masalah Absensi</div>
            <div class="attendance-report-kpi-value text-danger" id="summary_problem_days">0</div>
            <div class="attendance-report-kpi-note" id="summary_problem_meta">Alpha 0 | Tidak lengkap 0</div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="attendance-report-kpi">
            <div class="attendance-report-kpi-label">Lembur</div>
            <div class="attendance-report-kpi-value text-primary" id="summary_overtime">0 jam</div>
            <div class="attendance-report-kpi-note" id="summary_overtime_meta">Pending 0 jam</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <h3 class="card-label fw-bolder">Ringkasan Per Karyawan</h3>
        </div>
    </div>
    <div class="card-body py-6">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-4" id="attendance_report_table">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
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

<div class="modal fade" id="attendance_detail_modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 class="fw-bolder mb-1" id="attendance_detail_title">Detail Absensi</h2>
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
                render: (row) => `<button type="button" class="btn btn-sm btn-light-primary btn-detail-attendance" data-id="${row.employee_id}">Detail</button>`,
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

        document.getElementById('attendance_detail_title').textContent = row.employee_label;
        document.getElementById('attendance_detail_subtitle').textContent = `${row.area} | ${row.position}`;
        const detailRows = Array.isArray(row.detail_rows) && row.detail_rows.length
            ? row.detail_rows.map((detail) => `
                <tr>
                    <td>${escapeHtml(detail.date)}</td>
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
            : '<tr><td colspan="12" class="text-center text-muted py-8">Tidak ada jadwal atau rekap absensi pada periode ini.</td></tr>';
        document.getElementById('attendance_detail_rows').innerHTML = detailRows;
        detailModal?.show();
    });
});
</script>
@endpush
