@extends('layouts.admin')

@section('title', 'Laporan KPI Score')
@section('page_title', 'Laporan KPI Score')

@section('content')
<div class="card mb-6">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <h3 class="fw-bolder mb-0">Filter Laporan</h3>
        </div>
        <div class="card-toolbar">
            <button type="button" class="btn btn-light-primary" id="btn_export_kpi_score">
                <i class="fas fa-file-excel"></i> Export Excel
            </button>
        </div>
    </div>
    <div class="card-body py-6">
        <div class="row g-5 align-items-end">
            <div class="col-md-2">
                <label class="form-label">Dari</label>
                <input type="text" id="filter_date_from" class="form-control form-control-solid" value="{{ $defaultDateFrom }}" placeholder="YYYY-MM-DD">
            </div>
            <div class="col-md-2">
                <label class="form-label">Sampai</label>
                <input type="text" id="filter_date_to" class="form-control form-control-solid" value="{{ $defaultDateTo }}" placeholder="YYYY-MM-DD">
            </div>
            <div class="col-md-3">
                <label class="form-label">Snapshot</label>
                <select id="filter_snapshot_id" class="form-select form-select-solid" data-control="select2" data-placeholder="Semua Snapshot">
                    <option value="">Semua Snapshot</option>
                    @foreach($snapshots as $snapshot)
                        <option value="{{ $snapshot->id }}">{{ $snapshot->code }} - {{ $snapshot->period_start?->format('Y-m-d') }} s/d {{ $snapshot->period_end?->format('Y-m-d') }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Karyawan</label>
                <select id="filter_employee_id" class="form-select form-select-solid" data-control="select2" data-placeholder="Semua Karyawan">
                    <option value="">Semua Karyawan</option>
                    @foreach($employees as $employee)
                        <option value="{{ $employee->id }}">{{ $employee->employee_code }} - {{ $employee->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Role/Jabatan</label>
                <select id="filter_role_name" class="form-select form-select-solid" data-control="select2" data-placeholder="Semua Role">
                    <option value="">Semua Role</option>
                    @foreach($roles as $role)
                        <option value="{{ $role }}">{{ $role }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Status Snapshot</label>
                <select id="filter_status" class="form-select form-select-solid">
                    <option value="">Semua</option>
                    <option value="draft">Draft</option>
                    <option value="locked">Locked</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Sumber</label>
                <select id="filter_source_type" class="form-select form-select-solid">
                    <option value="">Semua</option>
                    <option value="auto">Auto</option>
                    <option value="manual">Manual</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Actual</label>
                <select id="filter_actual_status" class="form-select form-select-solid">
                    <option value="">Semua</option>
                    <option value="filled">Sudah Ada</option>
                    <option value="pending">Belum Ada</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Cari</label>
                <input type="text" id="filter_search" class="form-control form-control-solid" placeholder="Snapshot, KPI, role, karyawan">
            </div>
            <div class="col-md-3 d-flex gap-3">
                <button type="button" class="btn btn-primary" id="btn_apply_filter">Terapkan</button>
                <button type="button" class="btn btn-light" id="btn_reset_filter">Reset</button>
            </div>
        </div>
    </div>
</div>

<div class="row g-5 mb-6">
    <div class="col-md-2">
        <div class="card h-100">
            <div class="card-body py-5">
                <div class="text-muted fs-7 text-uppercase fw-bold">Total Item</div>
                <div class="fs-2 fw-bolder" id="summary_total_items">0</div>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card h-100">
            <div class="card-body py-5">
                <div class="text-muted fs-7 text-uppercase fw-bold">Actual Terisi</div>
                <div class="fs-2 fw-bolder text-success" id="summary_actual_filled">0</div>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card h-100">
            <div class="card-body py-5">
                <div class="text-muted fs-7 text-uppercase fw-bold">Belum Actual</div>
                <div class="fs-2 fw-bolder text-warning" id="summary_actual_pending">0</div>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card h-100">
            <div class="card-body py-5">
                <div class="text-muted fs-7 text-uppercase fw-bold">Avg Score</div>
                <div class="fs-2 fw-bolder" id="summary_avg_score">0</div>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card h-100">
            <div class="card-body py-5">
                <div class="text-muted fs-7 text-uppercase fw-bold">Avg Weighted</div>
                <div class="fs-2 fw-bolder" id="summary_avg_weighted_score">0</div>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card h-100">
            <div class="card-body py-5">
                <div class="text-muted fs-7 text-uppercase fw-bold">Completion</div>
                <div class="fs-2 fw-bolder" id="summary_completed_rate">0%</div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <h3 class="fw-bolder mb-0">Detail KPI Score</h3>
        </div>
    </div>
    <div class="card-body py-6">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5" id="kpi_score_report_table">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                        <th>Snapshot</th>
                        <th>Periode</th>
                        <th>Status</th>
                        <th>Karyawan</th>
                        <th>Role</th>
                        <th>KPI</th>
                        <th>Target</th>
                        <th class="text-end">Actual</th>
                        <th class="text-end">Achievement</th>
                        <th class="text-end">Score</th>
                        <th class="text-end">Bobot</th>
                        <th class="text-end">Weighted</th>
                        <th>Sumber</th>
                        <th>Calculated</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const kpiScoreReportUrls = {
        data: @json($dataUrl),
        summary: @json($summaryUrl),
        export: @json($exportUrl),
    };

    document.addEventListener('DOMContentLoaded', () => {
        const escapeHtml = (value) => String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');

        const number = (value, digits = 2) => {
            const parsed = Number(value ?? 0);
            return Number.isFinite(parsed) ? parsed.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: digits }) : '0';
        };

        const filters = () => ({
            date_from: document.getElementById('filter_date_from').value,
            date_to: document.getElementById('filter_date_to').value,
            snapshot_id: document.getElementById('filter_snapshot_id').value,
            employee_id: document.getElementById('filter_employee_id').value,
            role_name: document.getElementById('filter_role_name').value,
            status: document.getElementById('filter_status').value,
            source_type: document.getElementById('filter_source_type').value,
            actual_status: document.getElementById('filter_actual_status').value,
            q: document.getElementById('filter_search').value,
        });

        const buildQuery = () => {
            const params = new URLSearchParams();
            Object.entries(filters()).forEach(([key, value]) => {
                if (value !== null && value !== undefined && value !== '') params.append(key, value);
            });
            return params.toString();
        };

        const loadSummary = () => {
            fetch(`${kpiScoreReportUrls.summary}?${buildQuery()}`, { headers: { Accept: 'application/json' } })
                .then((response) => response.json())
                .then((summary) => {
                    document.getElementById('summary_total_items').textContent = number(summary.total_items, 0);
                    document.getElementById('summary_actual_filled').textContent = number(summary.actual_filled, 0);
                    document.getElementById('summary_actual_pending').textContent = number(summary.actual_pending, 0);
                    document.getElementById('summary_avg_score').textContent = number(summary.avg_score, 2);
                    document.getElementById('summary_avg_weighted_score').textContent = number(summary.avg_weighted_score, 2);
                    document.getElementById('summary_completed_rate').textContent = `${number(summary.completed_rate, 2)}%`;
                });
        };

        $('[data-control="select2"]').each(function () {
            if ($.fn.select2) {
                $(this).select2({ width: '100%', allowClear: true, placeholder: $(this).data('placeholder') || 'Pilih' });
            }
        });

        const table = $('#kpi_score_report_table').DataTable({
            processing: true,
            serverSide: true,
            pageLength: 25,
            dom: 'rtip',
            ajax: {
                url: kpiScoreReportUrls.data,
                data: (data) => Object.assign(data, filters()),
            },
            columns: [
                { data: 'snapshot_code', render: escapeHtml },
                { data: 'period', render: escapeHtml },
                { data: 'snapshot_status', render: (data) => data === 'locked' ? '<span class="badge badge-light-primary">Locked</span>' : '<span class="badge badge-light-warning">Draft</span>' },
                { data: 'employee', render: escapeHtml },
                { data: 'role_name', render: escapeHtml },
                { data: 'metric_name', render: (data, type, row) => `<div class="fw-bold">${escapeHtml(data)}</div><div class="text-muted fs-7">${escapeHtml(row.formula_key || '-')}</div>` },
                { data: 'target', render: escapeHtml },
                { data: 'actual_value', className: 'text-end', render: (data) => data === null ? '<span class="text-muted">-</span>' : number(data, 4) },
                { data: 'achievement_percent', className: 'text-end', render: (data) => `${number(data, 2)}%` },
                { data: 'score', className: 'text-end', render: (data) => number(data, 2) },
                { data: 'weight', className: 'text-end', render: (data) => `${number(data, 2)}%` },
                { data: 'weighted_score', className: 'text-end', render: (data) => number(data, 2) },
                { data: 'source_type', render: escapeHtml },
                { data: 'calculated_at', render: escapeHtml },
            ],
        });

        const reload = () => {
            table.ajax.reload();
            loadSummary();
        };

        document.getElementById('btn_apply_filter').addEventListener('click', reload);
        document.getElementById('filter_search').addEventListener('keyup', (event) => {
            if (event.key === 'Enter') reload();
        });
        document.getElementById('btn_reset_filter').addEventListener('click', () => {
            ['filter_snapshot_id', 'filter_employee_id', 'filter_role_name', 'filter_status', 'filter_source_type', 'filter_actual_status'].forEach((id) => {
                const el = document.getElementById(id);
                el.value = '';
                if ($.fn.select2) $(el).trigger('change');
            });
            document.getElementById('filter_search').value = '';
            reload();
        });
        document.getElementById('btn_export_kpi_score').addEventListener('click', () => {
            window.location.href = `${kpiScoreReportUrls.export}?${buildQuery()}`;
        });

        loadSummary();
    });
</script>
@endpush
