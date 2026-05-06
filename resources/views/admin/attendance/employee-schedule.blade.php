@extends('layouts.admin')

@section('title', 'Jadwal Per Karyawan')
@section('page_title', 'Jadwal Per Karyawan')

@push('styles')
<link href="{{ asset('metronic/plugins/custom/fullcalendar/fullcalendar.bundle.css') }}" rel="stylesheet" type="text/css" />
<style>
    /* ===== Hero ===== */
    .es-hero {
        background: linear-gradient(135deg, #f8faff 0%, #fff 60%);
        border: 1px solid #eef0f8;
        border-radius: 1rem;
        padding: 1.25rem 1.5rem;
        margin-bottom: 1.5rem;
    }
    .es-hero-eyebrow {
        font-size: .72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: #1b84ff;
        margin-bottom: .35rem;
    }
    .es-hero h1 { font-size: 1.5rem; font-weight: 800; color: #1e1e2d; margin: 0; }
    .es-hero p  { color: #7e8299; font-size: .875rem; margin-top: .25rem; }

    /* ===== Filter Card ===== */
    .es-filter-head {
        display: flex;
        align-items: center;
        gap: .65rem;
        margin-bottom: .85rem;
        font-weight: 700;
        color: #3f4254;
    }
    .es-filter-head i { color: #1b84ff; }

    /* ===== Stats ===== */
    .es-stat {
        position: relative;
        background: #fff;
        border: 1px solid #eef0f8;
        border-radius: 1rem;
        padding: 1.25rem;
        height: 100%;
        text-align: center;
        overflow: hidden;
        transition: transform .15s ease, box-shadow .15s ease;
    }
    .es-stat:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 22px rgba(31, 41, 55, .06);
    }
    .es-stat::before {
        content: "";
        position: absolute;
        top: 0; left: 0;
        height: 4px;
        width: 100%;
    }
    .es-stat.total::before { background: #1b84ff; }
    .es-stat.work::before  { background: #50cd89; }
    .es-stat.off::before   { background: #a1a5b7; }
    .es-stat.leave::before { background: #ffc700; }

    .es-stat-icon {
        width: 44px;
        height: 44px;
        border-radius: .65rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.05rem;
        margin-bottom: .65rem;
    }
    .es-stat.total .es-stat-icon { background: #eaf3ff; color: #1b84ff; }
    .es-stat.work .es-stat-icon  { background: #e8fbf1; color: #1aae6f; }
    .es-stat.off .es-stat-icon   { background: #f5f8fa; color: #5e6278; }
    .es-stat.leave .es-stat-icon { background: #fff8d6; color: #b58a00; }

    .es-stat-value {
        font-size: 1.95rem;
        line-height: 1;
        font-weight: 800;
        color: #1e1e2d;
    }
    .es-stat.total .es-stat-value { color: #1b84ff; }
    .es-stat.work .es-stat-value  { color: #1aae6f; }
    .es-stat.leave .es-stat-value { color: #b58a00; }
    .es-stat-label {
        color: #7e8299;
        font-size: .78rem;
        margin-top: .35rem;
        font-weight: 600;
    }

    /* ===== Calendar ===== */
    #emp_calendar { min-height: 650px; }
    #emp_calendar .fc-event {
        border-radius: 0.475rem;
        padding: 0.125rem 0.25rem;
    }
    .es-legend {
        display: flex;
        flex-wrap: wrap;
        gap: .9rem;
        font-size: .78rem;
        color: #5e6278;
    }
    .es-legend .dot {
        display: inline-block;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        margin-right: .35rem;
        vertical-align: middle;
    }

    /* ===== Empty state ===== */
    .es-empty {
        text-align: center;
        padding: 4rem 1rem 4.5rem;
    }
    .es-empty-icon {
        width: 92px;
        height: 92px;
        border-radius: 50%;
        background: #f0f7ff;
        color: #1b84ff;
        font-size: 2.4rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 1.25rem;
    }
    .es-empty h3 { color: #3f4254; font-weight: 700; }
    .es-empty p  { color: #a1a5b7; }

    /* ===== Responsive ===== */
    @media (max-width: 768px) {
        .es-hero { padding: 1rem; }
        .es-hero h1 { font-size: 1.2rem; }
        .es-stat-value { font-size: 1.5rem; }
        .es-empty { padding: 2.5rem 1rem; }
    }
</style>
@endpush

@section('content')

{{-- ===== Hero ===== --}}
<div class="es-hero">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
            <div class="es-hero-eyebrow"><i class="fas fa-calendar-check me-1"></i>Modul Absensi</div>
            <h1><i class="fas fa-user-clock me-2 text-primary"></i>Jadwal Per Karyawan</h1>
            <p class="mb-0">Lihat jadwal kerja, libur, dan cuti per karyawan dalam tampilan kalender.</p>
        </div>
        <a href="{{ route('admin.attendance.index') }}" class="btn btn-light btn-sm">
            <i class="fas fa-arrow-left me-1"></i>Kembali ke Absensi
        </a>
    </div>
</div>

{{-- ===== Filter ===== --}}
<div class="card mb-6 shadow-sm">
    <div class="card-body py-5">
        <div class="es-filter-head"><i class="fas fa-filter"></i> Pilih Karyawan & Bulan</div>
        <div class="row g-3 align-items-end">
            <div class="col-12 col-md-6 col-lg-6">
                <label class="form-label fw-bold">Karyawan</label>
                <select id="emp_select" class="form-select form-select-solid">
                    <option value="">-- Pilih Karyawan --</option>
                    @foreach($employees as $emp)
                        <option value="{{ $emp->id }}">{{ $emp->employee_code }} - {{ $emp->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-6 col-lg-3">
                <label class="form-label fw-bold">Bulan</label>
                <input type="month" id="month_input" class="form-control form-control-solid" value="{{ now()->format('Y-m') }}" />
            </div>
            <div class="col-12 col-lg-3">
                <button type="button" class="btn btn-primary w-100" id="btn_load">
                    <i class="fas fa-eye me-1"></i>Tampilkan Jadwal
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ===== Empty state ===== --}}
<div id="empty_state" class="card shadow-sm">
    <div class="card-body es-empty">
        <div class="es-empty-icon"><i class="fas fa-calendar-alt"></i></div>
        <h3 class="fs-3 mb-2">Belum ada karyawan dipilih</h3>
        <p class="mb-0">Pilih karyawan dari dropdown di atas untuk melihat jadwal & rekap kehadirannya.</p>
    </div>
</div>

{{-- ===== Main content (hidden until employee selected) ===== --}}
<div id="schedule_content" class="d-none">

    {{-- Stats --}}
    <div class="row g-4 mb-6">
        <div class="col-6 col-md-3">
            <div class="es-stat total">
                <div class="es-stat-icon"><i class="fas fa-calendar"></i></div>
                <div class="es-stat-value" id="stat_total">-</div>
                <div class="es-stat-label">Total Terjadwal</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="es-stat work">
                <div class="es-stat-icon"><i class="fas fa-briefcase"></i></div>
                <div class="es-stat-value" id="stat_work">-</div>
                <div class="es-stat-label">Hari Masuk</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="es-stat off">
                <div class="es-stat-icon"><i class="fas fa-mug-hot"></i></div>
                <div class="es-stat-value" id="stat_off">-</div>
                <div class="es-stat-label">Hari Libur</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="es-stat leave">
                <div class="es-stat-icon"><i class="fas fa-plane-departure"></i></div>
                <div class="es-stat-value" id="stat_leave">-</div>
                <div class="es-stat-label">Cuti / Izin</div>
            </div>
        </div>
    </div>

    {{-- Calendar --}}
    <div class="card mb-6 shadow-sm">
        <div class="card-header border-0 pt-5 pb-2 flex-wrap gap-2">
            <div class="card-title">
                <h3 class="fw-bold mb-0"><i class="fas fa-calendar-alt text-primary me-2"></i>Kalender</h3>
            </div>
            <div class="card-toolbar">
                <div class="es-legend">
                    <span><span class="dot" style="background:#009ef7"></span>Jadwal masuk</span>
                    <span><span class="dot" style="background:#50cd89"></span>Hadir</span>
                    <span><span class="dot" style="background:#ffc700"></span>Telat / Cuti</span>
                    <span><span class="dot" style="background:#f1416c"></span>Libur / Absen</span>
                    <span><span class="dot" style="background:#7239ea"></span>Tidak lengkap</span>
                </div>
            </div>
        </div>
        <div class="card-body pt-3">
            <div id="emp_calendar"></div>
        </div>
    </div>

    {{-- Schedule table --}}
    <div class="card shadow-sm">
        <div class="card-header border-0 pt-5">
            <h3 class="card-title fw-bold mb-0" id="table_title"><i class="fas fa-list text-primary me-2"></i>Detail Jadwal</h3>
        </div>
        <div class="card-body py-3">
            <div class="table-responsive">
                <table class="table align-middle table-row-dashed fs-6 gy-4">
                    <thead>
                        <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                            <th class="min-w-110px">Tanggal</th>
                            <th class="min-w-80px">Hari</th>
                            <th class="min-w-110px">Tipe</th>
                            <th class="min-w-150px">Shift</th>
                            <th>Catatan</th>
                        </tr>
                    </thead>
                    <tbody id="schedule_tbody">
                        <tr><td colspan="5" class="text-center text-muted py-8"><div class="spinner-border spinner-border-sm me-2"></div>Memuat data...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

@endsection

@push('scripts')
<script src="{{ asset('metronic/plugins/custom/fullcalendar/fullcalendar.bundle.js') }}"></script>
<script>
const calendarEventsUrl = '{{ route('admin.attendance.schedules.calendar-events') }}';
const schedulesDataUrl  = '{{ route('admin.attendance.schedules.data') }}';

const empSelect      = document.getElementById('emp_select');
const monthInput     = document.getElementById('month_input');
const scheduleContent = document.getElementById('schedule_content');
const emptyState     = document.getElementById('empty_state');

let calendar       = null;
let activeDateFrom = null;
let activeDateTo   = null;

const DAY_NAMES  = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
const TYPE_LABEL = { work:'Masuk', day_off:'Libur', holiday:'Libur Perusahaan', leave:'Cuti/Izin' };
const TYPE_BADGE = { work:'badge-light-primary', day_off:'badge-light-secondary', holiday:'badge-light-danger', leave:'badge-light-warning' };

const escHtml = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));

function computeMonthRange(currentStart) {
    const d = new Date(currentStart);
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const lastDay = new Date(y, d.getMonth() + 1, 0).getDate();
    return {
        dateFrom: `${y}-${m}-01`,
        dateTo:   `${y}-${m}-${String(lastDay).padStart(2, '0')}`,
        label:    `${y}-${m}`,
    };
}

function refreshTable() {
    const empId = empSelect.value;
    if (!empId || !activeDateFrom || !activeDateTo) return;

    const url = `${schedulesDataUrl}?employee_id=${encodeURIComponent(empId)}`
              + `&date_from=${encodeURIComponent(activeDateFrom)}`
              + `&date_to=${encodeURIComponent(activeDateTo)}`
              + `&length=-1&q=&draw=1`;

    fetch(url, { headers: { Accept: 'application/json' } })
        .then(r => r.json())
        .then(json => renderResults(json.data || []))
        .catch(() => {});
}

function renderResults(rows) {
    document.getElementById('stat_total').textContent = rows.length;
    document.getElementById('stat_work').textContent  = rows.filter(r => r.schedule_type === 'work').length;
    document.getElementById('stat_off').textContent   = rows.filter(r => ['day_off','holiday'].includes(r.schedule_type)).length;
    document.getElementById('stat_leave').textContent = rows.filter(r => r.schedule_type === 'leave').length;

    if (activeDateFrom) {
        const d = new Date(activeDateFrom + 'T00:00:00');
        document.getElementById('table_title').innerHTML =
            '<i class="fas fa-list text-primary me-2"></i>Detail Jadwal ' + d.toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
    }

    const tbody = document.getElementById('schedule_tbody');
    if (!rows.length) {
        tbody.innerHTML = `<tr><td colspan="5" class="text-center text-muted py-10"><i class="fas fa-inbox fs-2 d-block mb-2 opacity-50"></i>Tidak ada jadwal untuk bulan ini</td></tr>`;
        return;
    }

    rows.sort((a, b) => a.schedule_date.localeCompare(b.schedule_date));
    tbody.innerHTML = rows.map(row => {
        const dayIdx  = new Date(row.schedule_date + 'T00:00:00').getDay();
        const dayName = DAY_NAMES[dayIdx];
        const label   = TYPE_LABEL[row.schedule_type] || row.schedule_type;
        const badge   = TYPE_BADGE[row.schedule_type] || 'badge-light';
        const isWeekend = dayIdx === 0 || dayIdx === 6;
        return `<tr class="${isWeekend ? 'text-muted' : ''}">
            <td class="fw-bold">${escHtml(row.schedule_date)}</td>
            <td>${escHtml(dayName)}</td>
            <td><span class="badge ${badge}">${escHtml(label)}</span></td>
            <td>${escHtml(row.shift ?? '-')}</td>
            <td class="text-muted">${escHtml(row.note ?? '-')}</td>
        </tr>`;
    }).join('');
}

function initCalendar() {
    if (calendar) return;
    const el = document.getElementById('emp_calendar');
    if (!el || typeof FullCalendar === 'undefined') return;

    calendar = new FullCalendar.Calendar(el, {
        initialView: 'dayGridMonth',
        height: 650,
        locale: 'id',
        headerToolbar: {
            left:   'prev,next today',
            center: 'title',
            right:  'dayGridMonth,listMonth',
        },
        buttonText: { today: 'Hari ini', month: 'Bulan', list: 'List' },
        dayMaxEvents: 5,
        eventSources: [{
            events(info, success, failure) {
                const empId = empSelect.value;
                if (!empId) { success([]); return; }
                const params = new URLSearchParams({
                    start: info.startStr,
                    end:   info.endStr,
                    employee_id: empId,
                });
                fetch(`${calendarEventsUrl}?${params}`, { headers: { Accept: 'application/json' } })
                    .then(r => r.json())
                    .then(success)
                    .catch(failure);
            },
        }],
        datesSet(info) {
            const range = computeMonthRange(info.view.currentStart);
            activeDateFrom  = range.dateFrom;
            activeDateTo    = range.dateTo;
            monthInput.value = range.label;
            if (empSelect.value) refreshTable();
        },
        eventClick(info) {
            const details = Array.isArray(info.event.extendedProps?.details) ? info.event.extendedProps.details : [];
            const html = details.length
                ? `<ul class="text-start ps-4">${details.map(d => `<li>${escHtml(d)}</li>`).join('')}</ul>`
                : escHtml(info.event.title);
            Swal?.fire({ title: escHtml(info.event.title), html, icon: 'info', width: 640 });
        },
    });
    calendar.render();
}

function showContent() {
    emptyState.classList.add('d-none');
    scheduleContent.classList.remove('d-none');
}

function hideContent() {
    scheduleContent.classList.add('d-none');
    emptyState.classList.remove('d-none');
}

function handleLoad() {
    if (!empSelect.value) { hideContent(); return; }
    showContent();
    initCalendar();
    calendar.refetchEvents();
    if (activeDateFrom && activeDateTo) refreshTable();
}

document.getElementById('btn_load').addEventListener('click', handleLoad);

empSelect.addEventListener('change', handleLoad);

monthInput.addEventListener('change', () => {
    if (!monthInput.value || !calendar) return;
    const [y, m] = monthInput.value.split('-').map(Number);
    calendar.gotoDate(`${y}-${String(m).padStart(2, '0')}-01`);
});

document.addEventListener('DOMContentLoaded', () => {
    if (typeof $ !== 'undefined' && $.fn.select2) {
        $(empSelect).select2({
            width: '100%',
            allowClear: true,
            placeholder: '-- Pilih Karyawan --',
        });
    }
});
</script>
@endpush
