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
    <div class="table-responsive">
        <table class="table align-middle table-row-dashed fs-6 gy-4 ot-table" id="overtime_table">
            <thead>
                <tr>
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
    };
    const csrfToken = @json(csrf_token());
    const initialDateFrom = @json($defaultDateFrom);
    const initialDateTo = @json($defaultDateTo);

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
    });

    document.getElementById('btn_apply_filter').addEventListener('click', () => table.ajax.reload());
    document.getElementById('filter_search').addEventListener('keydown', (event) => {
        if (event.key === 'Enter') table.ajax.reload();
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
        table.ajax.reload();
    });
    $('#filter_employee_id, #filter_overtime_status').on('change', () => table.ajax.reload());

    $('#overtime_table').on('click', '.btn-approve-overtime', async function () {
        const id = this.dataset.id;
        const minutes = Number(this.dataset.minutes || 0);
        const result = window.Swal
            ? await Swal.fire({
                title: 'Approve lembur?',
                html: `<div class="text-start"><label class="form-label fw-bold">Menit disetujui</label><input id="swal_overtime_minutes" type="number" min="1" class="swal2-input" value="${minutes}"><label class="form-label fw-bold mt-3">Catatan</label><input id="swal_overtime_note" class="swal2-input" value="${escapeHtml(this.dataset.note || '')}"></div>`,
                showCancelButton: true,
                confirmButtonText: 'Approve',
                cancelButtonText: 'Batal',
                preConfirm: () => {
                    const approved = parseInt(document.getElementById('swal_overtime_minutes')?.value || '0', 10);
                    if (!approved || approved < 1) {
                        Swal.showValidationMessage('Menit disetujui wajib lebih dari 0.');
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
            })
            : { isConfirmed: confirm('Reject lembur ini?'), value: '' };
        if (!result.isConfirmed) return;

        try {
            const json = await request(route(urls.reject, id), { overtime_note: result.value || '' });
            notify(json.message || 'Lembur berhasil di-reject.');
            table.ajax.reload(null, false);
        } catch (error) {
            notify(error.message, 'error');
        }
    });
});
</script>
@endpush
