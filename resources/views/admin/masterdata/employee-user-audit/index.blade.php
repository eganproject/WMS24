@extends('layouts.admin')

@section('title', 'Audit User Karyawan')

@section('content')
<div class="row g-6 mb-6">
    <div class="col-md-6 col-xl-2">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted fw-semibold mb-2">User tanpa karyawan</div>
                <div class="fs-2 fw-bolder text-danger">{{ number_format($summary['users_without_employee']) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-2">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted fw-semibold mb-2">Karyawan aktif tanpa user</div>
                <div class="fs-2 fw-bolder text-danger">{{ number_format($summary['active_employees_without_user']) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-2">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted fw-semibold mb-2">Karyawan tanpa area</div>
                <div class="fs-2 fw-bolder text-warning">{{ number_format($summary['employees_without_area']) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-2">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted fw-semibold mb-2">Karyawan tanpa jabatan</div>
                <div class="fs-2 fw-bolder text-warning">{{ number_format($summary['employees_without_position']) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-2">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted fw-semibold mb-2">Nonaktif masih punya user</div>
                <div class="fs-2 fw-bolder text-warning">{{ number_format($summary['inactive_employees_with_user']) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-2">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted fw-semibold mb-2">Area tidak sama</div>
                <div class="fs-2 fw-bolder text-primary">{{ number_format($summary['area_mismatches']) }}</div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div class="d-flex align-items-center position-relative my-1">
                <span class="svg-icon svg-icon-1 position-absolute ms-6">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <rect opacity="0.5" x="17.0365" y="15.1223" width="8.15546" height="2" rx="1" transform="rotate(45 17.0365 15.1223)" fill="black" />
                        <path d="M11 19C6.55556 19 3 15.4444 3 11C3 6.55556 3 3 11 3C15.4444 3 19 6.55556 19 11C19 15.4444 15.4444 19 11 19ZM11 5C7.53333 5 5 7.53333 5 11C5 14.4667 7.53333 17 11 17C14.4667 17 17 14.4667 17 11C17 7.53333 14.4667 5 11 5Z" fill="black" />
                    </svg>
                </span>
                <input type="text" class="form-control form-control-solid w-250px ps-14" placeholder="Cari user/karyawan" data-kt-filter="search" />
            </div>
        </div>
        <div class="card-toolbar">
            <select id="filter_audit_type" class="form-select form-select-solid w-275px">
                <option value="">Semua temuan</option>
                <option value="user_without_employee">User belum terhubung ke karyawan</option>
                <option value="employee_without_user">Karyawan aktif belum punya user login</option>
                <option value="employee_without_area">Karyawan aktif belum punya area</option>
                <option value="employee_without_position">Karyawan aktif belum punya jabatan</option>
                <option value="inactive_employee_with_user">Karyawan nonaktif masih terhubung ke user</option>
                <option value="area_mismatch">Area user berbeda dengan area karyawan</option>
            </select>
        </div>
    </div>
    <div class="card-body py-6">
        <div class="alert alert-info">
            Halaman ini hanya memonitor gap relasi user dan karyawan. Perbaikan data tetap dilakukan dari Master User atau Master Karyawan agar perubahan tetap terkontrol.
        </div>
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5" id="employee_user_audit_table">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                        <th>Temuan</th>
                        <th>Prioritas</th>
                        <th>User</th>
                        <th>Karyawan</th>
                        <th>Jabatan</th>
                        <th>Area</th>
                        <th>Saran</th>
                        <th class="text-end">Aksi</th>
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
    document.addEventListener('DOMContentLoaded', function() {
        const dataUrl = '{{ route('admin.masterdata.employee-user-audit.data') }}';
        const tableEl = $('#employee_user_audit_table');
        const searchInput = document.querySelector('[data-kt-filter="search"]');
        const typeSelect = document.getElementById('filter_audit_type');

        const severityBadge = (value) => {
            const map = {
                high: 'badge-light-danger',
                medium: 'badge-light-warning',
                low: 'badge-light-primary',
            };
            const label = {
                high: 'Tinggi',
                medium: 'Sedang',
                low: 'Rendah',
            };

            return `<span class="badge ${map[value] || 'badge-light'}">${label[value] || value || '-'}</span>`;
        };

        const escapeHtml = (value) => $('<div>').text(value ?? '').html();

        if (!tableEl.length || !$.fn.DataTable) {
            console.error('DataTables is not available or #employee_user_audit_table missing');
            return;
        }

        const table = tableEl.DataTable({
            processing: true,
            serverSide: false,
            dom: 'rtip',
            order: [[1, 'asc']],
            ajax: {
                url: dataUrl,
                dataSrc: 'data',
                data: function(params) {
                    params.q = searchInput?.value || '';
                    params.type = typeSelect?.value || '';
                }
            },
            columns: [
                { data: 'issue' },
                { data: 'severity', render: severityBadge },
                {
                    data: null,
                    render: function(row) {
                        return `
                            <div class="fw-bold">${escapeHtml(row.user_name)}</div>
                            <div class="text-muted">${escapeHtml(row.user_email)}</div>
                            <div class="text-muted small">${escapeHtml(row.roles)}</div>
                        `;
                    }
                },
                {
                    data: null,
                    render: function(row) {
                        return `
                            <div class="fw-bold">${escapeHtml(row.employee_name)}</div>
                            <div class="text-muted">${escapeHtml(row.employee_code)}</div>
                        `;
                    }
                },
                { data: 'position' },
                { data: 'area' },
                { data: 'recommendation' },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: 'text-end',
                    render: function(row) {
                        if (!row.action_url) return '-';

                        return `<a href="${escapeHtml(row.action_url)}" class="btn btn-sm btn-light-primary">${escapeHtml(row.action_label || 'Buka')}</a>`;
                    }
                },
            ],
        });

        let searchTimer = null;
        searchInput?.addEventListener('input', function() {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => table.ajax.reload(), 250);
        });
        typeSelect?.addEventListener('change', () => table.ajax.reload());
    });
</script>
@endpush
