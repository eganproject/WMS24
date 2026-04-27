@extends('layouts.admin')

@section('title', 'Jadwal Per Karyawan')
@section('page_title', 'Jadwal Per Karyawan')

@push('styles')
<link href="{{ asset('metronic/plugins/custom/fullcalendar/fullcalendar.bundle.css') }}" rel="stylesheet" type="text/css" />
<style>
    #emp_calendar { min-height: 650px; }
</style>
@endpush

@section('content')

{{-- Filter --}}
<div class="card mb-6">
    <div class="card-body py-5">
        <div class="row g-3 align-items-end">
            <div class="col-md-5">
                <label class="form-label fw-bold">Karyawan</label>
                <select id="emp_select" class="form-select form-select-solid">
                    <option value="">-- Pilih Karyawan --</option>
                    @foreach($employees as $emp)
                        <option value="{{ $emp->id }}">{{ $emp->employee_code }} - {{ $emp->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 col-lg-2">
                <label class="form-label fw-bold">Bulan</label>
                <input type="month" id="month_input" class="form-control form-control-solid" value="{{ now()->format('Y-m') }}" />
            </div>
            <div class="col-md-4 col-lg-5 d-flex align-items-end gap-2">
                <button type="button" class="btn btn-primary" id="btn_load">Tampilkan</button>
                <a href="{{ route('admin.attendance.index') }}" class="btn btn-light">&#8592; Kembali ke Absensi</a>
            </div>
        </div>
    </div>
</div>

{{-- Empty state --}}
<div id="empty_state" class="card">
    <div class="card-body text-center py-20">
        <div class="text-gray-300 mb-4" style="font-size:4rem">&#128197;</div>
        <div class="text-gray-500 fs-5 fw-semibold">Pilih karyawan untuk melihat jadwal</div>
        <div class="text-gray-400 fs-7 mt-2">Gunakan dropdown di atas untuk memilih karyawan</div>
    </div>
</div>

{{-- Main content (hidden until employee selected) --}}
<div id="schedule_content" class="d-none">

    {{-- Stats --}}
    <div class="row g-5 mb-6">
        <div class="col-6 col-md-3">
            <div class="card h-100">
                <div class="card-body d-flex flex-column align-items-center justify-content-center py-7">
                    <span class="fs-2x fw-bolder text-primary" id="stat_total">-</span>
                    <span class="fs-7 text-muted mt-1">Total Terjadwal</span>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card h-100">
                <div class="card-body d-flex flex-column align-items-center justify-content-center py-7">
                    <span class="fs-2x fw-bolder text-success" id="stat_work">-</span>
                    <span class="fs-7 text-muted mt-1">Hari Masuk</span>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card h-100">
                <div class="card-body d-flex flex-column align-items-center justify-content-center py-7">
                    <span class="fs-2x fw-bolder text-secondary" id="stat_off">-</span>
                    <span class="fs-7 text-muted mt-1">Hari Libur</span>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card h-100">
                <div class="card-body d-flex flex-column align-items-center justify-content-center py-7">
                    <span class="fs-2x fw-bolder text-warning" id="stat_leave">-</span>
                    <span class="fs-7 text-muted mt-1">Cuti / Izin</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Calendar --}}
    <div class="card bg-light mb-6">
        <div class="card-body bg-white rounded">
            <div class="d-flex flex-wrap gap-3 mb-5 fs-7">
                <span><span class="badge me-1" style="background:#009ef7">&nbsp;</span> Jadwal masuk</span>
                <span><span class="badge me-1" style="background:#50cd89">&nbsp;</span> Hadir</span>
                <span><span class="badge me-1" style="background:#ffc700">&nbsp;</span> Telat / Cuti</span>
                <span><span class="badge me-1" style="background:#f1416c">&nbsp;</span> Libur / Absen</span>
                <span><span class="badge me-1" style="background:#7239ea">&nbsp;</span> Tidak lengkap</span>
            </div>
            <div id="emp_calendar"></div>
        </div>
    </div>

    {{-- Schedule table --}}
    <div class="card">
        <div class="card-header border-0 pt-5">
            <h3 class="card-title fw-bold mb-0" id="table_title">Detail Jadwal</h3>
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
                        <tr><td colspan="5" class="text-center text-muted py-8">Memuat data...</td></tr>
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
    // Stats
    document.getElementById('stat_total').textContent = rows.length;
    document.getElementById('stat_work').textContent  = rows.filter(r => r.schedule_type === 'work').length;
    document.getElementById('stat_off').textContent   = rows.filter(r => ['day_off','holiday'].includes(r.schedule_type)).length;
    document.getElementById('stat_leave').textContent = rows.filter(r => r.schedule_type === 'leave').length;

    // Table title
    if (activeDateFrom) {
        const d = new Date(activeDateFrom + 'T00:00:00');
        document.getElementById('table_title').textContent =
            'Jadwal ' + d.toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
    }

    // Table rows
    const tbody = document.getElementById('schedule_tbody');
    if (!rows.length) {
        tbody.innerHTML = `<tr><td colspan="5" class="text-center text-muted py-10">Tidak ada jadwal untuk bulan ini</td></tr>`;
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
    // datesSet fires and calls refreshTable automatically
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
