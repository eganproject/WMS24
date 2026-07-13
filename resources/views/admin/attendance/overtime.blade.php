@extends('layouts.admin')

@section('title', 'Monitor Lembur')
@section('page_title', 'Monitor Lembur')

@push('styles')
<style>
    .ot-hero {
        background: #fff;
        border: 1px solid #eef0f8;
        border-radius: .85rem;
        padding: 1.25rem 1.5rem;
        margin-bottom: 1.25rem;
    }
    .ot-hero h1 {
        font-size: 1.45rem;
        font-weight: 800;
        color: #181c32;
        margin: 0;
    }
    .ot-hero p {
        color: #7e8299;
        font-size: .875rem;
        margin: .25rem 0 0;
    }
    .ot-nav {
        display: flex;
        gap: .5rem;
        overflow-x: auto;
        padding: .5rem .25rem;
        margin: 0 -.25rem 1rem;
        scrollbar-width: thin;
    }
    .ot-nav-item {
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
    }
    .ot-nav-item:hover {
        background: #eef3f7;
        color: #1b84ff;
    }
    .ot-nav-item.active {
        background: #1b84ff;
        color: #fff;
    }
    .ot-card {
        background: #fff;
        border: 1px solid #eef0f8;
        border-radius: .85rem;
        padding: 1.15rem;
        margin-bottom: 1.25rem;
    }
    .ot-stat {
        border: 1px solid #eef0f8;
        border-radius: .75rem;
        padding: 1rem;
        min-height: 96px;
        background: #f9fafc;
    }
    .ot-stat .label {
        color: #7e8299;
        font-size: .72rem;
        font-weight: 700;
        text-transform: uppercase;
    }
    .ot-stat .value {
        color: #181c32;
        font-size: 1.55rem;
        font-weight: 800;
        margin-top: .25rem;
    }
    .ot-table {
        min-width: 1120px;
    }
    .ot-table thead th {
        background: #f9fafc;
        color: #7e8299;
        font-size: .72rem;
        font-weight: 700;
        text-transform: uppercase;
        white-space: nowrap;
    }
    .ot-time-meta {
        color: #7e8299;
        font-size: .75rem;
    }
    .ot-bulk-bar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        padding: .8rem 1rem;
        margin-bottom: 1rem;
        border: 1px solid #e4e6ef;
        border-radius: .65rem;
        background: #f9fafc;
    }
    .ot-detail-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: .5rem .75rem;
        padding: .7rem .8rem;
        border-radius: .65rem;
        background: #f5f8fa;
    }
    .ot-detail-grid .label { color: #7e8299; font-size: .72rem; }
    .ot-detail-grid .value { color: #181c32; font-weight: 600; }
    .ot-approval-form {
        display: grid;
        grid-template-columns: minmax(180px, .7fr) minmax(280px, 1.3fr);
        gap: .75rem;
        align-items: start;
    }
    .ot-approval-modal {
        padding: 1.15rem 1.35rem !important;
    }
    .ot-approval-modal .swal2-title {
        padding: 0 0 .7rem !important;
        font-size: 1.35rem !important;
    }
    .ot-approval-modal .swal2-html-container {
        margin: 0 !important;
        overflow: visible !important;
    }
    .ot-approval-modal .swal2-actions {
        margin-top: .85rem !important;
    }
    @media (max-width: 767.98px) {
        .ot-detail-grid,
        .ot-approval-form { grid-template-columns: 1fr 1fr; }
    }
    @media (max-width: 479.98px) {
        .ot-detail-grid,
        .ot-approval-form { grid-template-columns: 1fr; }
    }
</style>
@endpush

@section('content')
<div class="ot-hero">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <h1>Monitor Lembur</h1>
            <p>Review lembur terhitung, approve menit lembur yang disetujui, atau reject dengan catatan.</p>
        </div>
        <a href="{{ $recapUrl }}" class="btn btn-light-primary">
            <i class="fas fa-clipboard-check me-1"></i>Rekap Absensi
        </a>
    </div>
</div>

<div class="ot-nav">
    @foreach($sectionLinks as $key => $link)
        <a href="{{ route($link['route']) }}" class="ot-nav-item {{ $key === 'overtime' ? 'active' : '' }}">
            <i class="{{ $link['icon'] }}"></i>{{ $link['label'] }}
        </a>
    @endforeach
</div>

<div class="row g-4 mb-5" id="overtime_summary">
    <div class="col-6 col-md-4 col-xl-2"><div class="ot-stat"><div class="label">Total</div><div class="value" data-summary="total">0</div></div></div>
    <div class="col-6 col-md-4 col-xl-2"><div class="ot-stat"><div class="label">Pending</div><div class="value text-warning" data-summary="pending">0</div></div></div>
    <div class="col-6 col-md-4 col-xl-2"><div class="ot-stat"><div class="label">Approved</div><div class="value text-success" data-summary="approved">0</div></div></div>
    <div class="col-6 col-md-4 col-xl-2"><div class="ot-stat"><div class="label">Rejected</div><div class="value text-danger" data-summary="rejected">0</div></div></div>
    <div class="col-6 col-md-4 col-xl-2"><div class="ot-stat"><div class="label">Terhitung</div><div class="value" data-summary-hours="calculated_minutes">0j</div></div></div>
    <div class="col-6 col-md-4 col-xl-2"><div class="ot-stat"><div class="label">Disetujui</div><div class="value text-primary" data-summary-hours="approved_minutes">0j</div></div></div>
</div>

<div class="ot-card">
    <div class="row g-4 align-items-end">
        <div class="col-md-2">
            <label class="form-label fw-bold">Dari</label>
            <input type="text" class="form-control form-control-solid" id="filter_date_from" value="{{ $defaultDateFrom }}" placeholder="YYYY-MM-DD">
        </div>
        <div class="col-md-2">
            <label class="form-label fw-bold">Sampai</label>
            <input type="text" class="form-control form-control-solid" id="filter_date_to" value="{{ $defaultDateTo }}" placeholder="YYYY-MM-DD">
        </div>
        <div class="col-md-3">
            <label class="form-label fw-bold">Karyawan</label>
            <select class="form-select form-select-solid" id="filter_employee_id" data-control="select2" data-placeholder="Semua Karyawan">
                <option value="">Semua Karyawan</option>
                @foreach($employees as $employee)
                    <option value="{{ $employee->id }}">{{ $employee->employee_code }} - {{ $employee->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label fw-bold">Status</label>
            <select class="form-select form-select-solid" id="filter_overtime_status" data-control="select2">
                <option value="">Semua Status</option>
                <option value="pending" selected>Pending</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label fw-bold">Cari</label>
            <div class="d-flex gap-2">
                <input type="text" class="form-control form-control-solid" id="filter_search" placeholder="Nama / kode">
                <button class="btn btn-primary" type="button" id="btn_apply_filter"><i class="fas fa-search"></i></button>
                <button class="btn btn-light" type="button" id="btn_reset_filter"><i class="fas fa-undo"></i></button>
            </div>
        </div>
    </div>
</div>

<div class="ot-card">
    <div class="ot-bulk-bar">
        <div>
            <span class="fw-bold"><span id="bulk_selected_count">0</span> data dipilih</span>
            <span class="text-muted fs-8 ms-2">Hanya lembur berstatus pending yang dapat dipilih.</span>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-light-success" id="btn_bulk_approve" disabled>
                <i class="fas fa-check-double me-1"></i>Bulk Approve
            </button>
            <button type="button" class="btn btn-sm btn-light-danger" id="btn_bulk_reject" disabled>
                <i class="fas fa-times-circle me-1"></i>Bulk Reject
            </button>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle table-row-dashed fs-6 gy-4 ot-table" id="overtime_table">
            <thead>
                <tr>
                    <th class="text-center" style="width: 36px"><input type="checkbox" class="form-check-input" id="select_all_overtime" title="Pilih semua pending di halaman ini"></th>
                    <th>Karyawan</th>
                    <th>Tanggal</th>
                    <th>Shift</th>
                    <th>Jam Masuk/Keluar</th>
                    <th class="text-end">Kerja</th>
                    <th class="text-end">Lembur Terhitung</th>
                    <th class="text-end">Lembur Disetujui</th>
                    <th>Status</th>
                    <th>Approval</th>
                    <th>Catatan</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const urls = {
        data: @json($dataUrl),
        approve: @json($approveUrlTpl),
        reject: @json($rejectUrlTpl),
        bulkApprove: @json($bulkApproveUrl),
        bulkReject: @json($bulkRejectUrl),
    };
    const csrfToken = @json(csrf_token());
    const initialDateFrom = @json($defaultDateFrom);
    const initialDateTo = @json($defaultDateTo);
    const selectedRows = new Map();

    const route = (tpl, id) => tpl.replace(':id', id);
    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    const number = (value) => Number(value || 0).toLocaleString('id-ID');
    const hours = (minutes) => {
        const value = Number(minutes || 0);
        const h = Math.floor(value / 60);
        const m = value % 60;
        return `${h}j ${m}m`;
    };
    const badge = (status) => {
        if (status === 'approved') return '<span class="badge badge-light-success">Approved</span>';
        if (status === 'rejected') return '<span class="badge badge-light-danger">Rejected</span>';
        if (status === 'pending') return '<span class="badge badge-light-warning">Pending</span>';
        return '<span class="badge badge-light">None</span>';
    };
    const updateSummary = (summary = {}) => {
        document.querySelectorAll('[data-summary]').forEach((el) => {
            el.textContent = number(summary[el.dataset.summary] || 0);
        });
        document.querySelectorAll('[data-summary-hours]').forEach((el) => {
            el.textContent = hours(summary[el.dataset.summaryHours] || 0);
        });
    };
    const request = async (url, payload) => {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify(payload),
        });
        const json = await response.json().catch(() => ({}));
        if (!response.ok) {
            const errors = json.errors ? Object.values(json.errors).flat().join('\n') : (json.message || 'Terjadi kesalahan.');
            throw new Error(errors);
        }
        return json;
    };
    const notify = (message, icon = 'success') => {
        if (window.Swal) {
            Swal.fire({ text: message, icon, buttonsStyling: false, confirmButtonText: 'OK', customClass: { confirmButton: 'btn btn-primary' } });
        } else {
            alert(message);
        }
    };
    const updateBulkState = () => {
        const count = selectedRows.size;
        document.getElementById('bulk_selected_count').textContent = number(count);
        document.getElementById('btn_bulk_approve').disabled = count === 0;
        document.getElementById('btn_bulk_reject').disabled = count === 0;
        const visible = Array.from(document.querySelectorAll('.overtime-select:not(:disabled)'));
        const selectedVisible = visible.filter((checkbox) => checkbox.checked).length;
        const selectAll = document.getElementById('select_all_overtime');
        selectAll.checked = visible.length > 0 && selectedVisible === visible.length;
        selectAll.indeterminate = selectedVisible > 0 && selectedVisible < visible.length;
    };
    const clearSelection = () => {
        selectedRows.clear();
        updateBulkState();
    };
    const rowDetails = (row) => `
        <div class="ot-detail-grid text-start mb-4">
            <div><div class="label">Karyawan</div><div class="value">${escapeHtml(row.employee)}</div></div>
            <div><div class="label">Posisi</div><div class="value">${escapeHtml(row.position || '-')}</div></div>
            <div><div class="label">Tanggal / Shift</div><div class="value">${escapeHtml(row.attendance_date || '-')} / ${escapeHtml(row.shift || '-')} (${escapeHtml(row.shift_start_time || '-')}–${escapeHtml(row.shift_end_time || '-')})</div></div>
            <div><div class="label">Jam Masuk</div><div class="value">${escapeHtml(row.check_in_at || '-')}</div></div>
            <div><div class="label">Jam Keluar</div><div class="value">${escapeHtml(row.check_out_at || '-')}</div></div>
            <div><div class="label">Durasi Kerja</div><div class="value">${hours(row.work_minutes)}</div></div>
            <div><div class="label">Lembur Terhitung</div><div class="value">${hours(row.calculated_overtime_minutes)}</div></div>
            <div><div class="label">Sumber</div><div class="value">${escapeHtml(row.source || '-')}</div></div>
        </div>`;

    if (typeof $ !== 'undefined' && $.fn.select2) {
        $('[data-control="select2"]').each(function () {
            $(this).select2({ width: '100%', allowClear: this.querySelector('option[value=""]') !== null, placeholder: $(this).data('placeholder') || 'Pilih' });
        });
    }

    const table = $('#overtime_table').DataTable({
        processing: true,
        serverSide: true,
        dom: 'rtip',
        language: {
            processing: '<div class="text-muted py-4"><span class="spinner-border spinner-border-sm me-2"></span>Memuat data...</div>',
            emptyTable: '<div class="text-center py-8 text-muted">Tidak ada data lembur.</div>',
            zeroRecords: '<div class="text-center py-8 text-muted">Tidak ada data yang cocok.</div>',
            info: 'Menampilkan _START_–_END_ dari _TOTAL_ data',
            infoEmpty: '0 data',
            paginate: { first: '«', last: '»', next: '›', previous: '‹' },
        },
        ajax: {
            url: urls.data,
            data: (params) => {
                params.date_from = document.getElementById('filter_date_from').value;
                params.date_to = document.getElementById('filter_date_to').value;
                params.employee_id = document.getElementById('filter_employee_id').value;
                params.overtime_status = document.getElementById('filter_overtime_status').value;
                params.q = document.getElementById('filter_search').value;
            },
            dataSrc: (json) => {
                updateSummary(json.summary || {});
                return json.data || [];
            },
        },
        columns: [
            { data: null, orderable: false, searchable: false, className: 'text-center', render: (row) => {
                const selectable = row.overtime_status === 'pending' && Number(row.calculated_overtime_minutes || 0) > 0;
                return `<input type="checkbox" class="form-check-input overtime-select" data-id="${row.id}" ${selectedRows.has(String(row.id)) ? 'checked' : ''} ${selectable ? '' : 'disabled'} aria-label="Pilih lembur ${escapeHtml(row.employee)}">`;
            } },
            { data: null, render: (row) => `<div class="fw-bold">${escapeHtml(row.employee)}</div><div class="text-muted fs-8">${escapeHtml(row.position)}</div>` },
            { data: 'attendance_date', render: escapeHtml },
            { data: 'shift', render: escapeHtml },
            { data: null, render: (row) => `<div>${escapeHtml(row.check_in_at || '-')}</div><div class="ot-time-meta">${escapeHtml(row.check_out_at || '-')}</div>` },
            { data: 'work_minutes', className: 'text-end', render: hours },
            { data: 'calculated_overtime_minutes', className: 'text-end fw-bold', render: hours },
            { data: 'approved_overtime_minutes', className: 'text-end', render: (value) => value === null ? '<span class="text-muted">-</span>' : hours(value) },
            { data: 'overtime_status', render: badge },
            { data: null, render: (row) => row.approved_by ? `<div>${escapeHtml(row.approved_by)}</div><div class="text-muted fs-8">${escapeHtml(row.approved_at || '')}</div>` : '<span class="text-muted">-</span>' },
            { data: null, render: (row) => {
                const notes = [row.overtime_note, row.note].filter(Boolean);
                return notes.length ? escapeHtml(notes.join(' | ')) : '<span class="text-muted">-</span>';
            } },
            { data: null, orderable: false, className: 'text-end', render: (row) => {
                const canApprove = Number(row.calculated_overtime_minutes || 0) > 0 && row.overtime_status !== 'approved';
                const canReject = Number(row.calculated_overtime_minutes || 0) > 0 && row.overtime_status !== 'rejected';
                return `
                    ${canApprove ? `<button type="button" class="btn btn-sm btn-light-success btn-approve-overtime" data-id="${row.id}" data-minutes="${row.calculated_overtime_minutes}" data-note="${escapeHtml(row.overtime_note || '')}">Approve</button>` : ''}
                    ${canReject ? `<button type="button" class="btn btn-sm btn-light-danger btn-reject-overtime" data-id="${row.id}" data-note="${escapeHtml(row.overtime_note || '')}">Reject</button>` : ''}
                ` || '-';
            } },
        ],
        order: [],
        drawCallback: updateBulkState,
    });

    document.getElementById('btn_apply_filter').addEventListener('click', () => {
        clearSelection();
        table.ajax.reload();
    });
    document.getElementById('filter_search').addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            clearSelection();
            table.ajax.reload();
        }
    });
    document.getElementById('btn_reset_filter').addEventListener('click', () => {
        document.getElementById('filter_date_from').value = initialDateFrom;
        document.getElementById('filter_date_to').value = initialDateTo;
        document.getElementById('filter_search').value = '';
        document.getElementById('filter_employee_id').value = '';
        document.getElementById('filter_overtime_status').value = 'pending';
        if (typeof $ !== 'undefined' && $.fn.select2) {
            $('#filter_employee_id, #filter_overtime_status').trigger('change.select2');
        }
        clearSelection();
        table.ajax.reload();
    });
    $('#filter_employee_id, #filter_overtime_status').on('change', () => {
        clearSelection();
        table.ajax.reload();
    });

    $('#overtime_table').on('change', '.overtime-select', function () {
        const row = table.row($(this).closest('tr')).data();
        if (!row) return;
        if (this.checked) selectedRows.set(String(row.id), row);
        else selectedRows.delete(String(row.id));
        updateBulkState();
    });

    document.getElementById('select_all_overtime').addEventListener('change', function () {
        document.querySelectorAll('.overtime-select:not(:disabled)').forEach((checkbox) => {
            checkbox.checked = this.checked;
            const row = table.row($(checkbox).closest('tr')).data();
            if (!row) return;
            if (this.checked) selectedRows.set(String(row.id), row);
            else selectedRows.delete(String(row.id));
        });
        updateBulkState();
    });

    document.getElementById('btn_bulk_approve').addEventListener('click', async () => {
        const rows = Array.from(selectedRows.values());
        if (!rows.length) return;
        if (!window.Swal) {
            notify('Bulk approve membutuhkan komponen modal yang belum termuat.', 'error');
            return;
        }

        const items = [];
        for (let index = 0; index < rows.length; index += 1) {
            const row = rows[index];
            const maximum = Number(row.calculated_overtime_minutes || 0);
            const result = await Swal.fire({
                title: `Konfirmasi Approval ${index + 1} dari ${rows.length}`,
                html: `${rowDetails(row)}<div class="ot-approval-form text-start"><div><label class="form-label fw-bold mb-1">Menit disetujui</label><input id="swal_overtime_minutes" type="number" min="1" max="${maximum}" class="form-control" value="${maximum}"><div class="text-muted fs-8 mt-1">Maksimal ${maximum} menit.</div></div><div><label class="form-label fw-bold mb-1">Catatan</label><textarea id="swal_overtime_note" class="form-control" rows="1">${escapeHtml(row.overtime_note || '')}</textarea></div></div>`,
                width: 'min(920px, calc(100vw - 2rem))',
                customClass: { popup: 'ot-approval-modal' },
                showCancelButton: true,
                confirmButtonText: index === rows.length - 1 ? 'Simpan Semua Approval' : 'Lanjut',
                cancelButtonText: 'Batalkan Proses',
                allowOutsideClick: false,
                preConfirm: () => {
                    const approved = parseInt(document.getElementById('swal_overtime_minutes')?.value || '0', 10);
                    if (!approved || approved < 1) {
                        Swal.showValidationMessage('Menit disetujui wajib lebih dari 0.');
                        return false;
                    }
                    if (approved > maximum) {
                        Swal.showValidationMessage('Menit disetujui tidak boleh melebihi lembur terhitung.');
                        return false;
                    }
                    return { id: row.id, approved_overtime_minutes: approved, overtime_note: document.getElementById('swal_overtime_note')?.value || '' };
                },
            });
            if (!result.isConfirmed) return;
            items.push(result.value);
        }

        try {
            const json = await request(urls.bulkApprove, { items });
            clearSelection();
            notify(json.message || 'Bulk approval berhasil.');
            table.ajax.reload(null, false);
        } catch (error) {
            clearSelection();
            notify(error.message, 'error');
            table.ajax.reload(null, false);
        }
    });

    document.getElementById('btn_bulk_reject').addEventListener('click', async () => {
        const rows = Array.from(selectedRows.values());
        if (!rows.length) return;
        const names = rows.slice(0, 5).map((row) => `<li>${escapeHtml(row.employee)} — ${escapeHtml(row.attendance_date)}</li>`).join('');
        const more = rows.length > 5 ? `<div class="text-muted mt-2">dan ${rows.length - 5} data lainnya</div>` : '';
        const result = window.Swal ? await Swal.fire({
            title: `Reject ${rows.length} data lembur?`,
            html: `<div class="text-start"><ul>${names}</ul>${more}<label class="form-label fw-bold mt-3">Alasan rejection</label><textarea id="swal_reject_note" class="form-control" rows="3" placeholder="Alasan wajib diisi"></textarea></div>`,
            showCancelButton: true,
            confirmButtonText: 'Reject Semua',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#f1416c',
            preConfirm: () => {
                const note = document.getElementById('swal_reject_note')?.value.trim() || '';
                if (!note) {
                    Swal.showValidationMessage('Alasan rejection wajib diisi.');
                    return false;
                }
                return note;
            },
        }) : { isConfirmed: false };
        if (!result.isConfirmed) return;

        try {
            const json = await request(urls.bulkReject, { ids: rows.map((row) => row.id), overtime_note: result.value });
            clearSelection();
            notify(json.message || 'Bulk rejection berhasil.');
            table.ajax.reload(null, false);
        } catch (error) {
            clearSelection();
            notify(error.message, 'error');
            table.ajax.reload(null, false);
        }
    });

    $('#overtime_table').on('click', '.btn-approve-overtime', async function () {
        const id = this.dataset.id;
        const minutes = Number(this.dataset.minutes || 0);
        const result = window.Swal
            ? await Swal.fire({
                title: 'Approve lembur?',
                html: `<div class="text-start"><label class="form-label fw-bold">Menit disetujui</label><input id="swal_overtime_minutes" type="number" min="1" max="${minutes}" class="swal2-input" value="${minutes}"><div class="text-muted fs-8">Maksimal ${minutes} menit sesuai lembur terhitung.</div><label class="form-label fw-bold mt-3">Catatan</label><input id="swal_overtime_note" class="swal2-input" value="${escapeHtml(this.dataset.note || '')}"></div>`,
                showCancelButton: true,
                confirmButtonText: 'Approve',
                cancelButtonText: 'Batal',
                preConfirm: () => {
                    const approved = parseInt(document.getElementById('swal_overtime_minutes')?.value || '0', 10);
                    if (!approved || approved < 1) {
                        Swal.showValidationMessage('Menit disetujui wajib lebih dari 0.');
                        return false;
                    }
                    if (approved > minutes) {
                        Swal.showValidationMessage('Menit disetujui tidak boleh lebih besar dari lembur terhitung.');
                        return false;
                    }
                    return {
                        approved_overtime_minutes: approved,
                        overtime_note: document.getElementById('swal_overtime_note')?.value || '',
                    };
                },
            })
            : { isConfirmed: true, value: { approved_overtime_minutes: minutes, overtime_note: '' } };
        if (!result.isConfirmed) return;

        try {
            const json = await request(route(urls.approve, id), result.value);
            selectedRows.delete(String(id));
            updateBulkState();
            notify(json.message || 'Lembur berhasil di-approve.');
            table.ajax.reload(null, false);
        } catch (error) {
            notify(error.message, 'error');
        }
    });

    $('#overtime_table').on('click', '.btn-reject-overtime', async function () {
        const id = this.dataset.id;
        const result = window.Swal
            ? await Swal.fire({
                title: 'Reject lembur?',
                input: 'text',
                inputLabel: 'Catatan',
                inputValue: this.dataset.note || '',
                inputPlaceholder: 'Catatan reject lembur',
                showCancelButton: true,
                confirmButtonText: 'Reject',
                cancelButtonText: 'Batal',
                inputValidator: (value) => {
                    if (!String(value || '').trim()) {
                        return 'Catatan reject lembur wajib diisi.';
                    }
                },
            })
            : (() => {
                if (!confirm('Reject lembur ini?')) return { isConfirmed: false };
                return { isConfirmed: true, value: prompt('Catatan reject lembur') || '' };
            })();
        if (!result.isConfirmed) return;
        if (!String(result.value || '').trim()) {
            notify('Catatan reject lembur wajib diisi.', 'error');
            return;
        }

        try {
            const json = await request(route(urls.reject, id), { overtime_note: result.value || '' });
            selectedRows.delete(String(id));
            updateBulkState();
            notify(json.message || 'Lembur berhasil di-reject.');
            table.ajax.reload(null, false);
        } catch (error) {
            notify(error.message, 'error');
        }
    });
});
</script>
@endpush
