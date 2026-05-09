@extends('layouts.admin')

@section('title', 'Orang Absen')
@section('page_title', 'Orang Absen')

@push('styles')
<style>
    .abs-hero {
        background: linear-gradient(135deg, #fff8f8 0%, #fff 64%);
        border: 1px solid #f1e8e8;
        border-radius: 1rem;
        padding: 1.25rem 1.5rem;
        margin-bottom: 1.5rem;
    }
    .abs-hero-eyebrow {
        font-size: .72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: #f1416c;
        margin-bottom: .35rem;
    }
    .abs-hero h1 { font-size: 1.5rem; font-weight: 800; color: #1e1e2d; margin: 0; }
    .abs-hero p { color: #7e8299; font-size: .875rem; margin-top: .25rem; }
    .abs-nav {
        display: flex;
        flex-wrap: nowrap;
        gap: .5rem;
        overflow-x: auto;
        padding: .5rem .25rem;
        margin: 0 -.25rem;
        scrollbar-width: thin;
    }
    .abs-nav-item {
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
    }
    .abs-nav-item:hover { background: #eef3f7; color: #1b84ff; }
    .abs-nav-item.active {
        background: #f1416c;
        color: #fff;
        box-shadow: 0 6px 14px rgba(241, 65, 108, .22);
    }
    .abs-nav-item.active:hover { color: #fff; }
    .abs-filter-card,
    .abs-table-card {
        background: #fff;
        border: 1px solid #eef0f8;
        border-radius: .85rem;
        padding: 1.15rem;
        margin-bottom: 1.25rem;
    }
    .abs-stat {
        background: #fff5f8;
        border: 1px solid #ffe0e8;
        border-radius: .75rem;
        padding: 1rem;
        min-width: 180px;
    }
    .abs-stat .label {
        color: #7e8299;
        font-size: .72rem;
        font-weight: 700;
        text-transform: uppercase;
    }
    .abs-stat .value {
        color: #f1416c;
        font-size: 1.75rem;
        font-weight: 800;
        line-height: 1.1;
        margin-top: .25rem;
    }
    .abs-table-wrap {
        border: 1px solid #eef0f8;
        border-radius: .75rem;
        overflow: auto;
    }
    .abs-table {
        min-width: 920px;
        margin-bottom: 0;
    }
    .abs-table thead th {
        background: #f9fafc;
        color: #7e8299;
        font-size: .72rem;
        text-transform: uppercase;
        letter-spacing: .04em;
        border-bottom: 1px solid #eef0f8;
        white-space: nowrap;
    }
    .abs-empty {
        padding: 2rem;
        color: #7e8299;
        text-align: center;
    }
    .abs-pagination {
        display: flex;
        flex-wrap: wrap;
        gap: .5rem;
        align-items: center;
        justify-content: space-between;
        margin-top: 1rem;
    }
    @media (max-width: 768px) {
        .abs-hero { padding: 1rem; }
        .abs-hero h1 { font-size: 1.2rem; }
        .abs-filter-card,
        .abs-table-card { padding: 1rem; }
        .abs-stat { width: 100%; }
        .abs-pagination { align-items: stretch; }
        .abs-pagination .btn { flex: 1 1 auto; }
    }
</style>
@endpush

@section('content')
@php $sectionLinks = $sectionLinks ?? []; @endphp

<div class="abs-hero">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
            <div class="abs-hero-eyebrow"><i class="fas fa-user-times me-1"></i>Modul Absensi</div>
            <h1><i class="fas fa-user-times me-2 text-danger"></i>Orang Absen</h1>
            <p class="mb-0">Pantau karyawan dengan status absen berdasarkan rekap absensi yang sudah diproses sistem.</p>
        </div>
        <button type="button" class="btn btn-light-danger btn-sm" id="btn_refresh_absences">
            <i class="fas fa-sync-alt me-1"></i>Refresh
        </button>
    </div>
</div>

<div class="card mb-6 shadow-sm">
    <div class="card-body py-3">
        <nav class="abs-nav">
            @foreach($sectionLinks as $sectionKey => $section)
                <a href="{{ route($section['route']) }}" class="abs-nav-item {{ $sectionKey === 'absences' ? 'active' : '' }}">
                    <i class="{{ $section['icon'] }}"></i>
                    <span>{{ $section['label'] }}</span>
                </a>
            @endforeach
        </nav>
    </div>
</div>

<div class="abs-filter-card">
    <div class="row g-3 align-items-end">
        <div class="col-12 col-md-3">
            <label class="form-label fw-bold">Dari Tanggal</label>
            <input type="text" class="form-control form-control-solid js-date" id="filter_date_from" value="{{ $today }}" placeholder="YYYY-MM-DD">
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label fw-bold">Sampai Tanggal</label>
            <input type="text" class="form-control form-control-solid js-date" id="filter_date_to" value="{{ $today }}" placeholder="YYYY-MM-DD">
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label fw-bold">Karyawan</label>
            <select class="form-select form-select-solid" id="filter_employee">
                <option value="">Semua karyawan</option>
                @foreach($employees as $employee)
                    <option value="{{ $employee->id }}">{{ $employee->employee_code }} - {{ $employee->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label fw-bold">Cari</label>
            <input type="text" class="form-control form-control-solid" id="filter_search" placeholder="Nama / kode / telepon">
        </div>
        <div class="col-12 d-flex flex-wrap gap-2 justify-content-between align-items-center pt-2">
            <div class="abs-stat">
                <div class="label">Total Absen</div>
                <div class="value" id="summary_absent_count">0</div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-light" id="btn_reset_absences">
                    <i class="fas fa-times me-1"></i>Reset
                </button>
                <button type="button" class="btn btn-light-primary" id="btn_filter_absences">
                    <i class="fas fa-filter me-1"></i>Terapkan Filter
                </button>
                <a href="#" class="btn btn-success" id="btn_export_absences">
                    <i class="fas fa-file-excel me-1"></i>Export Excel
                </a>
            </div>
        </div>
    </div>
</div>

<div class="abs-table-card">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <div class="fw-bold text-gray-900">Daftar Karyawan Absen</div>
            <div class="text-muted fs-8" id="absence_range_label">-</div>
        </div>
        <div class="text-muted fs-8" id="absence_info">Memuat data...</div>
    </div>
    <div class="abs-table-wrap">
        <table class="table align-middle abs-table">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Kode</th>
                    <th>Nama</th>
                    <th>Jabatan</th>
                    <th>Area</th>
                    <th>Shift</th>
                    <th>Catatan</th>
                </tr>
            </thead>
            <tbody id="absence_rows">
                <tr><td colspan="7"><div class="abs-empty">Memuat data...</div></td></tr>
            </tbody>
        </table>
    </div>
    <div class="abs-pagination">
        <div class="text-muted fs-8" id="absence_page_info">-</div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-light" id="btn_prev_absences">Sebelumnya</button>
            <button type="button" class="btn btn-sm btn-light" id="btn_next_absences">Berikutnya</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const dataUrl = '{{ route('admin.attendance.absences.data') }}';
    const exportUrl = '{{ route('admin.attendance.absences.export') }}';
    const today = '{{ $today }}';
    const rowsEl = document.getElementById('absence_rows');
    const infoEl = document.getElementById('absence_info');
    const pageInfoEl = document.getElementById('absence_page_info');
    const rangeLabelEl = document.getElementById('absence_range_label');
    const summaryEl = document.getElementById('summary_absent_count');
    const dateFromEl = document.getElementById('filter_date_from');
    const dateToEl = document.getElementById('filter_date_to');
    const employeeEl = document.getElementById('filter_employee');
    const searchEl = document.getElementById('filter_search');
    const exportEl = document.getElementById('btn_export_absences');
    const prevEl = document.getElementById('btn_prev_absences');
    const nextEl = document.getElementById('btn_next_absences');
    let currentPage = 1;
    let lastPage = 1;

    const escapeHtml = (value) => String(value ?? '-').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    }[char]));

    const params = (page = currentPage) => {
        const query = new URLSearchParams({
            page,
            per_page: 30,
            date_from: dateFromEl.value || today,
            date_to: dateToEl.value || dateFromEl.value || today,
        });
        if (employeeEl.value) query.set('employee_id', employeeEl.value);
        if (searchEl.value.trim()) query.set('q', searchEl.value.trim());
        return query;
    };

    const updateExportLink = () => {
        exportEl.href = `${exportUrl}?${params(1).toString()}`;
    };

    const renderRows = (rows) => {
        if (!rows.length) {
            rowsEl.innerHTML = '<tr><td colspan="7"><div class="abs-empty"><i class="fas fa-check-circle fs-2 d-block mb-2 text-success"></i>Tidak ada karyawan absen pada filter ini.</div></td></tr>';
            return;
        }

        rowsEl.innerHTML = rows.map((row) => `
            <tr>
                <td class="fw-semibold">${escapeHtml(row.attendance_date)}</td>
                <td><span class="badge badge-light-danger">${escapeHtml(row.employee_code)}</span></td>
                <td class="fw-bold text-gray-900">${escapeHtml(row.employee_name)}</td>
                <td>${escapeHtml(row.position)}</td>
                <td>${escapeHtml(row.area)}</td>
                <td>${escapeHtml(row.shift)}</td>
                <td>${escapeHtml(row.note)}</td>
            </tr>
        `).join('');
    };

    const loadAbsences = async (page = 1) => {
        currentPage = page;
        rowsEl.innerHTML = '<tr><td colspan="7"><div class="abs-empty"><span class="spinner-border spinner-border-sm me-2"></span>Memuat data...</div></td></tr>';
        updateExportLink();

        try {
            const response = await fetch(`${dataUrl}?${params(page).toString()}`, {
                headers: { 'Accept': 'application/json' },
            });
            const json = await response.json();
            if (!response.ok) throw new Error(json?.message || 'Gagal memuat data');

            lastPage = json.last_page || 1;
            renderRows(json.data || []);
            summaryEl.textContent = json.summary?.absent_count ?? json.total ?? 0;
            rangeLabelEl.textContent = `${json.summary?.date_from || dateFromEl.value} s/d ${json.summary?.date_to || dateToEl.value}`;
            infoEl.textContent = `${json.total || 0} data ditemukan`;
            pageInfoEl.textContent = `Halaman ${json.current_page || 1} dari ${json.last_page || 1}`;
            prevEl.disabled = currentPage <= 1;
            nextEl.disabled = currentPage >= lastPage;
        } catch (error) {
            rowsEl.innerHTML = '<tr><td colspan="7"><div class="abs-empty text-danger">Gagal memuat data absen.</div></td></tr>';
            infoEl.textContent = 'Gagal memuat data';
        }
    };

    if (typeof flatpickr !== 'undefined') {
        document.querySelectorAll('.js-date').forEach((input) => flatpickr(input, {
            dateFormat: 'Y-m-d',
            allowInput: true,
        }));
    }
    if (typeof $ !== 'undefined' && $.fn.select2) {
        $('#filter_employee').select2({
            width: '100%',
            allowClear: true,
            placeholder: 'Semua karyawan',
        });
    }

    document.getElementById('btn_filter_absences')?.addEventListener('click', () => loadAbsences(1));
    document.getElementById('btn_refresh_absences')?.addEventListener('click', () => loadAbsences(currentPage));
    document.getElementById('btn_reset_absences')?.addEventListener('click', () => {
        dateFromEl.value = today;
        dateToEl.value = today;
        employeeEl.value = '';
        searchEl.value = '';
        if (typeof $ !== 'undefined' && $.fn.select2) {
            $('#filter_employee').val('').trigger('change.select2');
        }
        loadAbsences(1);
    });
    searchEl?.addEventListener('keyup', (event) => {
        if (event.key === 'Enter') loadAbsences(1);
    });
    prevEl?.addEventListener('click', () => {
        if (currentPage > 1) loadAbsences(currentPage - 1);
    });
    nextEl?.addEventListener('click', () => {
        if (currentPage < lastPage) loadAbsences(currentPage + 1);
    });

    updateExportLink();
    loadAbsences(1);
});
</script>
@endpush
