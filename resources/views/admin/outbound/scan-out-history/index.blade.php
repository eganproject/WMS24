@extends('layouts.admin')

@section('title', 'Riwayat Scan Out')
@section('page_title', 'Riwayat Scan Out')

@section('content')
<ul class="nav nav-tabs nav-line-tabs mb-6 fs-6" role="tablist">
    <li class="nav-item">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#scan_out_success_tab" type="button" role="tab">Scan Berhasil</button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#scan_out_failed_tab" type="button" role="tab">Scan Ditolak</button>
    </li>
</ul>

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
                <input type="text" class="form-control form-control-solid w-250px ps-14" placeholder="Cari operator / resi" data-kt-filter="search" />
            </div>
        </div>
        <div class="card-toolbar">
            <div class="d-flex align-items-center gap-2 me-4">
                <input type="text" class="form-control form-control-solid w-150px" id="filter_date_from" placeholder="Dari" value="{{ $today ?? '' }}" />
                <input type="text" class="form-control form-control-solid w-150px" id="filter_date_to" placeholder="Sampai" value="{{ $today ?? '' }}" />
                <button type="button" class="btn btn-light" id="filter_date_apply">Filter</button>
                <button type="button" class="btn btn-light" id="filter_date_reset">Reset</button>
            </div>
        </div>
    </div>
    <div class="card-body py-6">
        <div class="tab-content">
            <div class="tab-pane fade show active" id="scan_out_success_tab" role="tabpanel">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5" id="scan_out_history_table">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                        <th>No</th>
                        <th>Tanggal</th>
                        <th>Jam Scan</th>
                        <th>Operator</th>
                        <th>Packed By</th>
                        <th>Packed At</th>
                        <th>Jenis Scan</th>
                        <th>Kode Scan</th>
                        <th>ID Pesanan</th>
                        <th>No Resi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
            </div>
            <div class="tab-pane fade" id="scan_out_failed_tab" role="tabpanel">
                <div class="row g-4 mb-5" id="failed_attempt_summary">
                    <div class="col-6 col-md-3">
                        <div class="border rounded p-4">
                            <div class="text-muted fs-8 text-uppercase">Total Ditolak</div>
                            <div class="fs-3 fw-bolder" data-failed-summary="total">0</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="border rounded p-4">
                            <div class="text-muted fs-8 text-uppercase">Duplikat</div>
                            <div class="fs-3 fw-bolder text-warning" data-failed-summary="duplicate">0</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="border rounded p-4">
                            <div class="text-muted fs-8 text-uppercase">QC Belum Siap</div>
                            <div class="fs-3 fw-bolder text-danger" data-failed-summary="qc_not_ready">0</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="border rounded p-4">
                            <div class="text-muted fs-8 text-uppercase">Invalid</div>
                            <div class="fs-3 fw-bolder text-muted" data-failed-summary="invalid">0</div>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-5" id="scan_out_failed_table">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                <th>No</th>
                                <th>Waktu</th>
                                <th>Operator</th>
                                <th>Alasan</th>
                                <th>Pesan</th>
                                <th>Jenis Scan</th>
                                <th>Kode Scan</th>
                                <th>ID Pesanan</th>
                                <th>No Resi</th>
                                <th>Status Resi</th>
                                <th>Status QC</th>
                                <th>Scan Out Existing</th>
                            </tr>
                        </thead>
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
    const dataUrl = '{{ $dataUrl }}';
    const failedDataUrl = '{{ $failedDataUrl }}';
    const todayStr = '{{ $today ?? '' }}';

    document.addEventListener('DOMContentLoaded', () => {
        const tableEl = $('#scan_out_history_table');
        const failedTableEl = $('#scan_out_failed_table');
        const searchInput = document.querySelector('[data-kt-filter="search"]');
        const dateFromEl = document.getElementById('filter_date_from');
        const dateToEl = document.getElementById('filter_date_to');
        const dateApplyBtn = document.getElementById('filter_date_apply');
        const dateResetBtn = document.getElementById('filter_date_reset');
        let fpFrom = null;
        let fpTo = null;

        if ((!tableEl.length && !failedTableEl.length) || !$.fn.DataTable) {
            console.error('DataTables unavailable');
            return;
        }

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
            order: [[2, 'desc']],
            ajax: {
                url: dataUrl,
                dataSrc: 'data',
                data: function(params) {
                    params.q = searchInput?.value || '';
                    if (dateFromEl?.value) params.date_from = dateFromEl.value;
                    if (dateToEl?.value) params.date_to = dateToEl.value;
                }
            },
            columns: [
                { data: null, orderable: false, searchable: false, render: (data, type, row, meta) => meta.row + meta.settings._iDisplayStart + 1 },
                { data: 'scan_date' },
                { data: 'scanned_at' },
                { data: 'scanner' },
                { data: 'packed_by' },
                { data: 'packed_at' },
                { data: 'scan_type', render: (data) => {
                    if (data === 'id_pesanan') return 'ID Pesanan';
                    if (data === 'no_resi') return 'No Resi';
                    return data || '-';
                }},
                { data: 'scan_code' },
                { data: 'id_pesanan' },
                { data: 'no_resi' },
            ]
        });

        const failedDt = failedTableEl.DataTable({
            processing: true,
            serverSide: true,
            dom: 'rtip',
            order: [[1, 'desc']],
            ajax: {
                url: failedDataUrl,
                dataSrc: function(json) {
                    const summary = json.summary || {};
                    document.querySelectorAll('[data-failed-summary]').forEach((el) => {
                        const key = el.getAttribute('data-failed-summary');
                        el.textContent = Number(summary[key] || 0).toLocaleString('id-ID');
                    });
                    return json.data || [];
                },
                data: function(params) {
                    params.q = searchInput?.value || '';
                    if (dateFromEl?.value) params.date_from = dateFromEl.value;
                    if (dateToEl?.value) params.date_to = dateToEl.value;
                }
            },
            columns: [
                { data: null, orderable: false, searchable: false, render: (data, type, row, meta) => meta.row + meta.settings._iDisplayStart + 1 },
                { data: 'attempted_at' },
                { data: 'operator' },
                { data: 'reason_label', render: (data, type, row) => `<span class="badge badge-light-danger">${data || row.reason_code || '-'}</span>` },
                { data: 'message' },
                { data: 'scan_type', render: (data) => {
                    if (data === 'id_pesanan') return 'ID Pesanan';
                    if (data === 'no_resi') return 'No Resi';
                    return data || '-';
                }},
                { data: 'scan_code' },
                { data: 'id_pesanan' },
                { data: 'no_resi' },
                { data: 'resi_status' },
                { data: 'qc_status' },
                { data: 'existing_scanned_at' },
            ]
        });

        const reloadTable = () => {
            dt.ajax.reload();
            failedDt.ajax.reload();
        };
        searchInput?.addEventListener('keyup', reloadTable);
        dateApplyBtn?.addEventListener('click', reloadTable);
        dateResetBtn?.addEventListener('click', () => {
            if (fpFrom && todayStr) fpFrom.setDate(todayStr, true);
            if (fpTo && todayStr) fpTo.setDate(todayStr, true);
            if (!fpFrom && dateFromEl) dateFromEl.value = todayStr;
            if (!fpTo && dateToEl) dateToEl.value = todayStr;
            reloadTable();
        });

        document.querySelectorAll('[data-bs-toggle="tab"]').forEach((tabEl) => {
            tabEl.addEventListener('shown.bs.tab', () => {
                setTimeout(() => {
                    dt.columns.adjust();
                    failedDt.columns.adjust();
                }, 50);
            });
        });
    });
</script>
@endpush
