@extends('layouts.admin')

@section('title', 'History QC')
@section('page_title', 'History QC')

@section('content')
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div class="d-flex align-items-center position-relative my-1">
                <span class="svg-icon svg-icon-1 position-absolute ms-6">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <rect opacity="0.5" x="17.0365" y="15.1223" width="8.15546" height="2" rx="1" transform="rotate(45 17.0365 15.1223)" fill="black" />
                        <path d="M11 19C6.55556 19 3 15.4444 3 11C3 6.55556 6.55556 3 11 3C15.4444 3 19 6.55556 19 11C19 15.4444 15.4444 19 11 19ZM11 5C7.53333 5 5 7.53333 5 11C5 14.4667 7.53333 17 11 17C14.4667 17 17 14.4667 17 11C17 7.53333 14.4667 5 11 5Z" fill="black" />
                    </svg>
                </span>
                <input type="text" class="form-control form-control-solid w-250px ps-14" placeholder="Search" data-kt-filter="search" />
            </div>
        </div>
        <div class="card-toolbar">
            <div class="d-flex align-items-center gap-2 me-4">
                <select class="form-select form-select-solid w-160px" id="filter_status">
                    <option value="">Semua Status</option>
                    @foreach(($statusOptions ?? []) as $option)
                        <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                    @endforeach
                </select>
                <input type="text" class="form-control form-control-solid w-175px" id="filter_completed_by" placeholder="Selesai oleh" />
                <input type="text" class="form-control form-control-solid w-175px" id="filter_reset_by" placeholder="Reset oleh" />
                <input type="text" class="form-control form-control-solid w-150px" id="filter_date_from" placeholder="Dari" value="{{ $today ?? '' }}" />
                <input type="text" class="form-control form-control-solid w-150px" id="filter_date_to" placeholder="Sampai" value="{{ $today ?? '' }}" />
                <button type="button" class="btn btn-light" id="filter_date_apply">Filter</button>
                <button type="button" class="btn btn-light" id="filter_date_reset">Reset</button>
            </div>
        </div>
    </div>
    <div class="card-body py-6">
        <div class="d-flex flex-wrap align-items-center gap-6 mb-4">
            <div class="fw-bold">QC Berjalan: <span id="summary_draft">0</span></div>
            <div class="fw-bold">Lolos QC: <span id="summary_passed">0</span></div>
        </div>
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5" id="qc_history_table">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                        <th>ID</th>
                        <th>Mulai</th>
                        <th>Selesai</th>
                        <th>Mulai Oleh</th>
                        <th>Status</th>
                        <th>Audit</th>
                        <th>Jenis Scan</th>
                        <th>Kode Scan</th>
                        <th>ID Pesanan</th>
                        <th>No Resi</th>
                        <th>SKU</th>
                        <th>Total SKU</th>
                        <th>Expected Qty</th>
                        <th>Scanned Qty</th>
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
    const dataUrl = '{{ $dataUrl }}';
    const todayStr = '{{ $today ?? '' }}';

    document.addEventListener('DOMContentLoaded', () => {
        const tableEl = $('#qc_history_table');
        const searchInput = document.querySelector('[data-kt-filter="search"]');
        const statusEl = document.getElementById('filter_status');
        const completedByEl = document.getElementById('filter_completed_by');
        const resetByEl = document.getElementById('filter_reset_by');
        const dateFromEl = document.getElementById('filter_date_from');
        const dateToEl = document.getElementById('filter_date_to');
        const dateApplyBtn = document.getElementById('filter_date_apply');
        const dateResetBtn = document.getElementById('filter_date_reset');
        const summaryDraftEl = document.getElementById('summary_draft');
        const summaryPassedEl = document.getElementById('summary_passed');
        let fpFrom = null;
        let fpTo = null;

        if (!tableEl.length || !$.fn.DataTable) {
            console.error('DataTables unavailable');
            return;
        }

        const escapeHtml = (value) => {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        };

        if (typeof flatpickr !== 'undefined') {
            if (dateFromEl) {
                fpFrom = flatpickr(dateFromEl, { dateFormat: 'Y-m-d', allowInput: true });
                if (todayStr && !dateFromEl.value) fpFrom.setDate(todayStr, true);
            }
            if (dateToEl) {
                fpTo = flatpickr(dateToEl, { dateFormat: 'Y-m-d', allowInput: true });
                if (todayStr && !dateToEl.value) fpTo.setDate(todayStr, true);
            }
        }

        const dt = tableEl.DataTable({
            processing: true,
            serverSide: true,
            dom: 'rtip',
            order: [[1, 'desc']],
            ajax: {
                url: dataUrl,
                dataSrc: 'data',
                data: function(params) {
                    params.q = searchInput?.value || '';
                    params.status = statusEl?.value || '';
                    params.completed_by = completedByEl?.value || '';
                    params.reset_by = resetByEl?.value || '';
                    if (dateFromEl?.value) params.date_from = dateFromEl.value;
                    if (dateToEl?.value) params.date_to = dateToEl.value;
                }
            },
            columns: [
                { data: 'id' },
                { data: 'started_at' },
                { data: 'completed_at' },
                { data: 'scanner' },
                { data: 'status', render: (data, type, row) => {
                    const label = row.status_label || '-';
                    const badge = row.status_badge || 'badge-light';
                    return `<span class="badge ${badge}">${escapeHtml(label)}</span>`;
                }},
                { data: null, orderable: false, searchable: false, render: (data, type, row) => {
                    const lines = [];
                    if ((row.completed_by || '-') !== '-') {
                        lines.push(`<div>Selesai: <strong>${escapeHtml(row.completed_by)}</strong></div>`);
                    }
                    if ((row.last_scanned_by || '-') !== '-' && (row.last_scanned_at || '-') !== '-') {
                        lines.push(`<div>Scan terakhir: <strong>${escapeHtml(row.last_scanned_by)}</strong><div class="text-muted">${escapeHtml(row.last_scanned_at)}</div></div>`);
                    }
                    if ((row.reset_count || 0) > 0) {
                        const reason = row.reset_reason ? ` - ${escapeHtml(row.reset_reason)}` : '';
                        const resetBy = (row.reset_by || '-') !== '-' ? escapeHtml(row.reset_by) : 'unknown';
                        const resetAt = (row.reset_at || '-') !== '-' ? escapeHtml(row.reset_at) : '-';
                        lines.push(`<div>Reset: <strong>${escapeHtml(row.reset_count)}x</strong><div class="text-muted">${resetBy} @ ${resetAt}${reason}</div></div>`);
                    }

                    return lines.length ? lines.join('') : '<span class="text-muted">-</span>';
                }},
                { data: 'scan_type', render: (data) => {
                    if (data === 'id_pesanan') return 'ID Pesanan';
                    if (data === 'no_resi') return 'No Resi';
                    return data || '-';
                }},
                { data: 'scan_code' },
                { data: 'id_pesanan' },
                { data: 'no_resi' },
                { data: 'sku_list' },
                { data: 'total_sku' },
                { data: 'total_expected_qty' },
                { data: 'total_scanned_qty' },
            ]
        });

        tableEl.on('xhr.dt', function () {
            const json = dt?.ajax?.json?.();
            if (json?.summary) {
                if (summaryDraftEl) summaryDraftEl.textContent = json.summary.draft ?? '0';
                if (summaryPassedEl) summaryPassedEl.textContent = json.summary.passed ?? '0';
            }
        });

        const reloadTable = () => dt.ajax.reload();
        searchInput?.addEventListener('keyup', reloadTable);
        completedByEl?.addEventListener('keyup', (e) => {
            if (e.key === 'Enter') reloadTable();
        });
        resetByEl?.addEventListener('keyup', (e) => {
            if (e.key === 'Enter') reloadTable();
        });
        statusEl?.addEventListener('change', reloadTable);
        dateApplyBtn?.addEventListener('click', reloadTable);
        dateResetBtn?.addEventListener('click', () => {
            if (statusEl) statusEl.value = '';
            if (completedByEl) completedByEl.value = '';
            if (resetByEl) resetByEl.value = '';
            if (fpFrom && todayStr) fpFrom.setDate(todayStr, true);
            if (fpTo && todayStr) fpTo.setDate(todayStr, true);
            if (!fpFrom && dateFromEl) dateFromEl.value = todayStr;
            if (!fpTo && dateToEl) dateToEl.value = todayStr;
            reloadTable();
        });
    });
</script>
@endpush
