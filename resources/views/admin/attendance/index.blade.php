@extends('layouts.admin')

@section('title', 'Absensi')
@section('page_title', 'Absensi Karyawan')

@push('styles')
<link href="{{ asset('metronic/plugins/custom/fullcalendar/fullcalendar.bundle.css') }}" rel="stylesheet" type="text/css" />
<style>
    /* ===== Section Navigation ===== */
    .att-nav {
        display: flex;
        flex-wrap: nowrap;
        gap: .5rem;
        overflow-x: auto;
        padding: .5rem .25rem;
        margin: 0 -.25rem;
        scrollbar-width: thin;
    }
    .att-nav::-webkit-scrollbar { height: 6px; }
    .att-nav::-webkit-scrollbar-thumb { background: #e4e6ef; border-radius: 4px; }

    .att-nav-item {
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
        transition: all .15s ease;
    }
    .att-nav-item:hover {
        background: #eef3f7;
        color: #1b84ff;
    }
    .att-nav-item i {
        font-size: .9rem;
        opacity: .8;
    }
    .att-nav-item.active {
        background: #1b84ff;
        color: #fff;
        box-shadow: 0 6px 14px rgba(27, 132, 255, .25);
    }
    .att-nav-item.active:hover { color: #fff; }
    .att-nav-item.active i { opacity: 1; }

    /* ===== Page Hero ===== */
    .att-hero {
        background: linear-gradient(135deg, #f8faff 0%, #fff 60%);
        border: 1px solid #eef0f8;
        border-radius: 1rem;
        padding: 1.25rem 1.5rem;
        margin-bottom: 1.5rem;
    }
    .att-hero-eyebrow {
        font-size: .72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: #1b84ff;
        margin-bottom: .35rem;
    }
    .att-hero-title { font-size: 1.5rem; font-weight: 800; color: #1e1e2d; margin: 0; }
    .att-hero-desc  { color: #7e8299; font-size: .875rem; margin-top: .25rem; }

    /* ===== Form Card ===== */
    .att-form-card {
        background: #fff;
        border: 1px solid #eef0f8;
        border-radius: .85rem;
        padding: 1.25rem;
        margin-bottom: 1.5rem;
    }
    .att-form-head {
        display: flex;
        align-items: center;
        gap: .75rem;
        padding-bottom: .85rem;
        margin-bottom: 1rem;
        border-bottom: 1px dashed #e4e6ef;
    }
    .att-form-head .icon {
        width: 38px;
        height: 38px;
        background: #f0f7ff;
        color: #1b84ff;
        border-radius: .55rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: .95rem;
        flex: 0 0 38px;
    }
    .att-form-head h3 { font-size: 1rem; margin: 0; font-weight: 700; color: #1e1e2d; }
    .att-form-head p  { margin: .15rem 0 0; color: #7e8299; font-size: .8rem; }

    /* ===== Sub-section inside form (e.g., attendance & overtime) ===== */
    .attendance-form-section {
        border: 1px solid #e4e6ef;
        border-radius: 0.75rem;
        padding: 1rem 1.1rem 1.1rem;
        background: #f9fafc;
    }
    .attendance-form-section + .attendance-form-section { margin-top: 1rem; }
    .attendance-form-section-title {
        color: #3f4254;
        font-size: 0.78rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        margin-bottom: 0.85rem;
        display: flex;
        align-items: center;
        gap: .5rem;
    }
    .attendance-form-section-title::before {
        content: "";
        width: 4px;
        height: 14px;
        background: #1b84ff;
        border-radius: 2px;
    }

    /* ===== Calendar ===== */
    #attendance_schedule_calendar { min-height: 680px; }
    #attendance_schedule_calendar .fc-event {
        border-radius: 0.475rem;
        padding: 0.125rem 0.25rem;
    }
    .att-legend {
        display: flex;
        flex-wrap: wrap;
        gap: .9rem;
        font-size: .78rem;
        color: #5e6278;
    }
    .att-legend .dot {
        display: inline-block;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        margin-right: .35rem;
        vertical-align: middle;
    }

    .attendance-calendar-detail {
        max-height: 420px;
        overflow-y: auto;
        text-align: left;
    }
    .attendance-calendar-detail ol {
        padding-left: 1.25rem;
        margin-bottom: 0;
    }

    /* ===== Toolbar ===== */
    .att-toolbar {
        display: flex;
        flex-wrap: wrap;
        gap: .75rem;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1.25rem;
    }
    .att-toolbar .search-wrap {
        position: relative;
        flex: 1 1 280px;
        max-width: 360px;
    }
    .att-toolbar .search-wrap i {
        position: absolute;
        top: 50%;
        left: .9rem;
        transform: translateY(-50%);
        color: #a1a5b7;
    }
    .att-toolbar .search-wrap input { padding-left: 2.4rem; }

    /* ===== Form helpers ===== */
    .form-label.fw-bold { color: #3f4254; font-size: .8rem; }
    .att-checkbox-row {
        display: flex;
        align-items: center;
        gap: 1.25rem;
        flex-wrap: wrap;
        padding: .65rem .9rem;
        background: #f9fafc;
        border: 1px solid #e4e6ef;
        border-radius: .55rem;
    }

    /* ===== Responsive tweaks ===== */
    @media (max-width: 768px) {
        .att-hero { padding: 1rem; }
        .att-hero-title { font-size: 1.2rem; }
        .att-form-card { padding: 1rem; }
        .att-toolbar { flex-direction: column; align-items: stretch; }
        .att-toolbar .search-wrap { max-width: 100%; }
    }
</style>
@endpush

@section('content')
@php
    $activeSection = $activeSection ?? 'employees';
    $sectionLinks = $sectionLinks ?? [];
    $activeSectionLabel = $sectionLinks[$activeSection]['label'] ?? 'Absensi';
    $activeSectionIcon  = $sectionLinks[$activeSection]['icon']  ?? 'fas fa-user-clock';

    $sectionDescriptions = [
        'employees'    => 'Kelola data karyawan, jabatan, area kerja, dan akun login yang terhubung.',
        'devices'      => 'Daftar mesin absensi (fingerprint/face) yang terhubung ke sistem.',
        'fingerprints' => 'Pemetaan ID karyawan ke User ID di mesin absensi.',
        'shifts'       => 'Definisi shift kerja, jam istirahat, toleransi, dan aturan lembur.',
        'schedules'    => 'Atur jadwal harian per karyawan dan lihat kalender keseluruhan.',
        'holidays'     => 'Daftar hari libur perusahaan dan nasional.',
        'templates'    => 'Pola jadwal mingguan yang dapat di-assign ke banyak karyawan sekaligus.',
        'leaves'       => 'Pengajuan & approval cuti, sakit, dan izin karyawan.',
        'raw_logs'     => 'Log mentah scan dari mesin absensi sebelum diolah jadi rekap.',
        'attendances'  => 'Rekap final absensi harian, termasuk approval lembur.',
    ];
    $activeDescription = $sectionDescriptions[$activeSection] ?? 'Modul absensi terintegrasi.';
@endphp

{{-- ===== Hero ===== --}}
<div class="att-hero">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
            <div class="att-hero-eyebrow"><i class="fas fa-user-clock me-1"></i>Modul Absensi</div>
            <h1 class="att-hero-title">
                <i class="{{ $activeSectionIcon }} me-2 text-primary"></i>{{ $activeSectionLabel }}
            </h1>
            <div class="att-hero-desc">{{ $activeDescription }}</div>
        </div>
        <button type="button" class="btn btn-light-primary btn-sm" id="attendance_refresh_tab" data-active-section="{{ $activeSection }}">
            <i class="fas fa-sync-alt me-1"></i>Refresh Halaman
        </button>
    </div>
</div>

{{-- ===== Section Navigation ===== --}}
<div class="card mb-6 shadow-sm">
    <div class="card-body py-3">
        <nav class="att-nav">
            @foreach($sectionLinks as $sectionKey => $section)
                <a href="{{ route($section['route']) }}" class="att-nav-item {{ $activeSection === $sectionKey ? 'active' : '' }}">
                    <i class="{{ $section['icon'] }}"></i>
                    <span>{{ $section['label'] }}</span>
                </a>
            @endforeach
        </nav>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body py-6">

        {{-- ===== Toolbar (search) ===== --}}
        <div class="att-toolbar">
            <div class="search-wrap">
                <i class="fas fa-search"></i>
                <input type="text" class="form-control form-control-solid" placeholder="Cari data pada tab aktif..." id="attendance_search" />
            </div>
            <div class="text-muted fs-7">
                <i class="fas fa-info-circle me-1 text-primary"></i>
                Pencarian berlaku untuk data tabel pada tab yang sedang aktif.
            </div>
        </div>

        <div class="tab-content">
            {{-- ===== EMPLOYEES ===== --}}
            <div class="tab-pane fade {{ $activeSection === 'employees' ? 'show active' : '' }}" id="tab_employees">
                <div class="att-form-card">
                    <div class="att-form-head">
                        <span class="icon"><i class="fas fa-user-plus"></i></span>
                        <div>
                            <h3>Tambah Karyawan</h3>
                            <p>Isi data karyawan baru. Klik <em>Edit</em> di tabel untuk mengubah data.</p>
                        </div>
                    </div>
                    <form class="row g-3 ajax-form" data-table="employees_table" action="{{ route('admin.attendance.employees.store') }}">
                        @csrf
                        <div class="col-12 col-md-6 col-lg-3"><label class="form-label fw-bold">Kode Karyawan</label><input name="employee_code" class="form-control form-control-solid" placeholder="EMP001" required></div>
                        <div class="col-12 col-md-6 col-lg-4"><label class="form-label fw-bold">Nama</label><input name="name" class="form-control form-control-solid" placeholder="Nama lengkap karyawan" required></div>
                        <div class="col-12 col-md-6 col-lg-3"><label class="form-label fw-bold">Telepon</label><input name="phone" class="form-control form-control-solid" placeholder="08xxxxxxxxxx"></div>
                        <div class="col-12 col-md-6 col-lg-2">
                            <label class="form-label fw-bold">Status Kerja</label>
                            <select name="employment_status" class="form-select form-select-solid">
                                <option value="active">Aktif</option>
                                <option value="inactive">Nonaktif</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label fw-bold">Jabatan</label>
                            <select name="position_id" id="employee_position_id" class="form-select form-select-solid">
                                <option value="">Tanpa jabatan</option>
                                @foreach($positions as $position)
                                    <option value="{{ $position->id }}">{{ $position->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-6 col-lg-3"><label class="form-label fw-bold">Tanggal Masuk</label><input type="text" name="join_date" class="form-control form-control-solid js-date" placeholder="YYYY-MM-DD"></div>
                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label fw-bold">Area</label>
                            <select name="area_id" class="form-select form-select-solid">
                                <option value="">Area kosong</option>
                                @foreach($areas as $area)
                                    <option value="{{ $area->id }}">{{ $area->code }} - {{ $area->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label fw-bold">User Login</label>
                            <select name="user_id" class="form-select form-select-solid">
                                <option value="">Tidak terhubung user login</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }} - {{ $user->email }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 d-flex flex-wrap gap-2 justify-content-end pt-2">
                            <button type="button" class="btn btn-light-primary" data-bs-toggle="modal" data-bs-target="#modal_positions">
                                <i class="fas fa-briefcase me-1"></i>Kelola Jabatan
                            </button>
                            <button class="btn btn-primary"><i class="fas fa-plus me-1"></i>Tambah Karyawan</button>
                        </div>
                    </form>
                </div>
                <x-attendance-table id="employees_table" :headers="['Kode','Nama','Area','User','Telepon','Jabatan','Status','Aksi']" />
            </div>

            {{-- ===== DEVICES ===== --}}
            <div class="tab-pane fade {{ $activeSection === 'devices' ? 'show active' : '' }}" id="tab_devices">
                <div class="att-form-card">
                    <div class="att-form-head">
                        <span class="icon"><i class="fas fa-fingerprint"></i></span>
                        <div>
                            <h3>Tambah Device</h3>
                            <p>Daftarkan mesin absensi (fingerprint/face) yang terhubung ke sistem.</p>
                        </div>
                    </div>
                    <form class="row g-3 ajax-form" data-table="devices_table" action="{{ route('admin.attendance.devices.store') }}">
                        @csrf
                        <div class="col-12 col-md-6 col-lg-4"><label class="form-label fw-bold">Nama Device</label><input name="name" class="form-control form-control-solid" placeholder="Fingerprint Gudang" required></div>
                        <div class="col-12 col-md-6 col-lg-4"><label class="form-label fw-bold">Serial Number</label><input name="serial_number" class="form-control form-control-solid" placeholder="SN001"></div>
                        <div class="col-12 col-md-6 col-lg-4"><label class="form-label fw-bold">Tipe Device</label><input name="device_type" class="form-control form-control-solid" placeholder="ZKTeco / Solution X100C"></div>
                        <div class="col-12 col-md-6 col-lg-4"><label class="form-label fw-bold">IP Address</label><input name="ip_address" class="form-control form-control-solid" placeholder="192.168.1.201"></div>
                        <div class="col-12 col-md-6 col-lg-2"><label class="form-label fw-bold">Port</label><input type="number" name="port" value="4370" class="form-control form-control-solid" placeholder="4370" required></div>
                        <div class="col-12 col-md-6 col-lg-6"><label class="form-label fw-bold">Lokasi</label><input name="location" class="form-control form-control-solid" placeholder="Pintu masuk / Gudang utama"></div>
                        <div class="col-12 d-flex flex-wrap align-items-center justify-content-between gap-2 pt-2">
                            <div class="att-checkbox-row">
                                <input type="hidden" name="is_active" value="0">
                                <label class="form-check form-check-custom form-check-solid mb-0">
                                    <input type="checkbox" name="is_active" value="1" class="form-check-input" checked>
                                    <span class="form-check-label fw-semibold">Device aktif</span>
                                </label>
                            </div>
                            <button class="btn btn-primary"><i class="fas fa-plus me-1"></i>Tambah Device</button>
                        </div>
                    </form>
                </div>
                <x-attendance-table id="devices_table" :headers="['Nama','Serial','IP','Port','Lokasi','Tipe','Aktif','Sync Terakhir','Aksi']" />
            </div>

            {{-- ===== FINGERPRINTS ===== --}}
            <div class="tab-pane fade {{ $activeSection === 'fingerprints' ? 'show active' : '' }}" id="tab_fingerprints">
                <div class="att-form-card">
                    <div class="att-form-head">
                        <span class="icon"><i class="fas fa-id-badge"></i></span>
                        <div>
                            <h3>Daftarkan Fingerprint</h3>
                            <p>Hubungkan ID karyawan dengan User ID yang ada di mesin absensi.</p>
                        </div>
                    </div>
                    <form class="row g-3 ajax-form" data-table="fingerprints_table" action="{{ route('admin.attendance.fingerprints.store') }}">
                        @csrf
                        <div class="col-12 col-md-6 col-lg-4"><label class="form-label fw-bold">Karyawan</label><select name="employee_id" class="form-select form-select-solid" required>@foreach($employees as $employee)<option value="{{ $employee->id }}">{{ $employee->employee_code }} - {{ $employee->name }}</option>@endforeach</select></div>
                        <div class="col-12 col-md-6 col-lg-4"><label class="form-label fw-bold">Device</label><select name="attendance_device_id" class="form-select form-select-solid"><option value="">Semua device</option>@foreach($devices as $device)<option value="{{ $device->id }}">{{ $device->name }}</option>@endforeach</select></div>
                        <div class="col-12 col-md-6 col-lg-2"><label class="form-label fw-bold">User ID Mesin</label><input name="device_user_id" class="form-control form-control-solid" placeholder="1001" required></div>
                        <div class="col-12 col-md-6 col-lg-2"><label class="form-label fw-bold">UID</label><input name="fingerprint_uid" class="form-control form-control-solid" placeholder="Opsional"></div>
                        <div class="col-12 d-flex flex-wrap align-items-center justify-content-between gap-2 pt-2">
                            <div class="att-checkbox-row">
                                <input type="hidden" name="is_active" value="0">
                                <label class="form-check form-check-custom form-check-solid mb-0">
                                    <input type="checkbox" name="is_active" value="1" class="form-check-input" checked>
                                    <span class="form-check-label fw-semibold">Fingerprint aktif</span>
                                </label>
                            </div>
                            <button class="btn btn-primary"><i class="fas fa-plus me-1"></i>Tambah</button>
                        </div>
                    </form>
                </div>
                <x-attendance-table id="fingerprints_table" :headers="['Karyawan','Device','Device User ID','UID','Aktif','Enrolled','Aksi']" />
            </div>

            {{-- ===== SHIFTS ===== --}}
            <div class="tab-pane fade {{ $activeSection === 'shifts' ? 'show active' : '' }}" id="tab_shifts">
                <div class="att-form-card">
                    <div class="att-form-head">
                        <span class="icon"><i class="fas fa-clock"></i></span>
                        <div>
                            <h3>Tambah Shift Kerja</h3>
                            <p>Atur jam kerja, istirahat, toleransi keterlambatan, dan aturan lembur.</p>
                        </div>
                    </div>
                    <form class="ajax-form" data-table="shifts_table" action="{{ route('admin.attendance.shifts.store') }}">
                        @csrf
                        <div class="attendance-form-section">
                            <div class="attendance-form-section-title">Identitas & Jam Kerja</div>
                            <div class="row g-3">
                                <div class="col-12 col-md-6 col-lg-4"><label class="form-label fw-bold">Nama Shift</label><input name="name" class="form-control form-control-solid" placeholder="Shift Pagi" required></div>
                                <div class="col-6 col-md-3 col-lg-2"><label class="form-label fw-bold">Jam Masuk</label><input type="text" name="start_time" class="form-control form-control-solid js-time" placeholder="08:00" required></div>
                                <div class="col-6 col-md-3 col-lg-2"><label class="form-label fw-bold">Jam Pulang</label><input type="text" name="end_time" class="form-control form-control-solid js-time" placeholder="17:00" required></div>
                                <div class="col-6 col-md-3 col-lg-2"><label class="form-label fw-bold">Istirahat Mulai</label><input type="text" name="break_start_time" class="form-control form-control-solid js-time" placeholder="12:00"></div>
                                <div class="col-6 col-md-3 col-lg-2"><label class="form-label fw-bold">Istirahat Selesai</label><input type="text" name="break_end_time" class="form-control form-control-solid js-time" placeholder="13:00"></div>
                            </div>
                        </div>
                        <div class="attendance-form-section">
                            <div class="attendance-form-section-title">Toleransi & Lembur (Menit)</div>
                            <div class="row g-3">
                                <div class="col-6 col-md-6 col-lg-3"><label class="form-label fw-bold">Toleransi Telat</label><input type="number" name="late_tolerance_minutes" value="0" min="0" class="form-control form-control-solid" placeholder="0"></div>
                                <div class="col-6 col-md-6 col-lg-3"><label class="form-label fw-bold">Toleransi Pulang Cepat</label><input type="number" name="checkout_tolerance_minutes" value="0" min="0" class="form-control form-control-solid" placeholder="0"></div>
                                <div class="col-6 col-md-6 col-lg-3"><label class="form-label fw-bold">Lembur Mulai Setelah</label><input type="number" name="overtime_start_after_minutes" value="0" min="0" class="form-control form-control-solid" placeholder="Menit setelah pulang"></div>
                                <div class="col-6 col-md-6 col-lg-3"><label class="form-label fw-bold">Minimal Lembur</label><input type="number" name="minimum_overtime_minutes" value="0" min="0" class="form-control form-control-solid" placeholder="Menit"></div>
                            </div>
                        </div>
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mt-4">
                            <div class="att-checkbox-row">
                                <label class="form-check form-check-custom form-check-solid mb-0">
                                    <input type="checkbox" name="crosses_midnight" value="1" class="form-check-input">
                                    <span class="form-check-label fw-semibold">Shift malam (lewat tengah malam)</span>
                                </label>
                                <input type="hidden" name="is_active" value="0">
                                <label class="form-check form-check-custom form-check-solid mb-0">
                                    <input type="checkbox" name="is_active" value="1" class="form-check-input" checked>
                                    <span class="form-check-label fw-semibold">Aktif</span>
                                </label>
                            </div>
                            <button class="btn btn-primary"><i class="fas fa-plus me-1"></i>Tambah Shift</button>
                        </div>
                    </form>
                </div>
                <x-attendance-table id="shifts_table" :headers="['Nama','Masuk','Pulang','Istirahat','Telat','Pulang Cepat','Lembur Setelah','Minimal Lembur','Malam','Aktif','Aksi']" />
            </div>

            {{-- ===== SCHEDULES ===== --}}
            <div class="tab-pane fade {{ $activeSection === 'schedules' ? 'show active' : '' }}" id="tab_schedules">
                <div class="att-form-card">
                    <div class="att-form-head flex-wrap" style="gap:.75rem;">
                        <span class="icon"><i class="fas fa-calendar-plus"></i></span>
                        <div class="flex-grow-1">
                            <h3>Tambah Jadwal Karyawan</h3>
                            <p>Atur jadwal harian masuk, libur, atau cuti per karyawan.</p>
                        </div>
                        <a href="{{ route('admin.attendance.employee-schedule.index') }}" class="btn btn-sm btn-light-info">
                            <i class="fas fa-user-clock me-1"></i>Lihat Jadwal Per Karyawan
                        </a>
                    </div>
                    <form class="row g-3 ajax-form" data-table="schedules_table" action="{{ route('admin.attendance.schedules.store') }}">
                        @csrf
                        <div class="col-12 col-md-6 col-lg-4"><label class="form-label fw-bold">Karyawan</label><select name="employee_id" class="form-select form-select-solid" required>@foreach($employees as $employee)<option value="{{ $employee->id }}">{{ $employee->employee_code }} - {{ $employee->name }}</option>@endforeach</select></div>
                        <div class="col-6 col-md-6 col-lg-2"><label class="form-label fw-bold">Tanggal Jadwal</label><input type="text" name="schedule_date" class="form-control form-control-solid js-date" placeholder="YYYY-MM-DD" required></div>
                        <div class="col-6 col-md-6 col-lg-2"><label class="form-label fw-bold">Tipe</label><select name="schedule_type" class="form-select form-select-solid"><option value="work">Masuk</option><option value="day_off">Libur</option><option value="holiday">Libur Perusahaan</option><option value="leave">Cuti/Izin</option></select></div>
                        <div class="col-12 col-md-6 col-lg-4"><label class="form-label fw-bold">Shift</label><select name="work_shift_id" class="form-select form-select-solid"><option value="">Tanpa shift</option>@foreach($shifts as $shift)<option value="{{ $shift->id }}">{{ $shift->name }} ({{ substr($shift->start_time,0,5) }}-{{ substr($shift->end_time,0,5) }})</option>@endforeach</select></div>
                        <div class="col-12 col-md-9"><label class="form-label fw-bold">Catatan</label><input name="note" class="form-control form-control-solid" placeholder="Opsional"></div>
                        <div class="col-12 col-md-3 d-flex align-items-end">
                            <button class="btn btn-primary w-100"><i class="fas fa-save me-1"></i>Simpan Jadwal</button>
                        </div>
                    </form>
                </div>

                <div class="att-form-card">
                    <div class="att-form-head flex-wrap" style="gap:.75rem;">
                        <span class="icon"><i class="fas fa-calendar-alt"></i></span>
                        <div class="flex-grow-1">
                            <h3>Kalender Jadwal</h3>
                            <p>Klik event untuk melihat detail. Filter karyawan untuk fokus pada satu orang.</p>
                        </div>
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <select id="calendar_employee_filter" class="form-select form-select-solid form-select-sm" style="min-width:230px;">
                                <option value="">Semua karyawan</option>
                                @foreach($employees as $employee)
                                    <option value="{{ $employee->id }}">{{ $employee->employee_code }} - {{ $employee->name }}</option>
                                @endforeach
                            </select>
                            <button type="button" class="btn btn-sm btn-light-primary" id="calendar_refresh">
                                <i class="fas fa-sync-alt me-1"></i>Refresh
                            </button>
                        </div>
                    </div>
                    <div class="att-legend mb-4">
                        <span><span class="dot" style="background:#009ef7"></span>Jadwal masuk</span>
                        <span><span class="dot" style="background:#50cd89"></span>Hadir</span>
                        <span><span class="dot" style="background:#ffc700"></span>Telat / Cuti</span>
                        <span><span class="dot" style="background:#f1416c"></span>Libur / Absen</span>
                        <span><span class="dot" style="background:#7239ea"></span>Tidak lengkap</span>
                    </div>
                    <div id="attendance_schedule_calendar"></div>
                </div>

                <x-attendance-table id="schedules_table" :headers="['Karyawan','Tanggal','Tipe','Shift','Catatan','Aksi']" />
            </div>

            {{-- ===== HOLIDAYS ===== --}}
            <div class="tab-pane fade {{ $activeSection === 'holidays' ? 'show active' : '' }}" id="tab_holidays">
                <div class="att-form-card">
                    <div class="att-form-head">
                        <span class="icon"><i class="fas fa-calendar-day"></i></span>
                        <div>
                            <h3>Tambah Hari Libur</h3>
                            <p>Tetapkan hari libur perusahaan dan nasional yang diakui sistem.</p>
                        </div>
                    </div>
                    <form class="row g-3 ajax-form" data-table="holidays_table" action="{{ route('admin.attendance.holidays.store') }}">
                        @csrf
                        <div class="col-12 col-md-4"><label class="form-label fw-bold">Tanggal Libur</label><input type="text" name="holiday_date" class="form-control form-control-solid js-date" placeholder="YYYY-MM-DD" required></div>
                        <div class="col-12 col-md-8"><label class="form-label fw-bold">Nama Hari Libur</label><input name="name" class="form-control form-control-solid" placeholder="Contoh: Hari Kemerdekaan" required></div>
                        <div class="col-12 col-md-4"><label class="form-label fw-bold">Tipe</label><select name="type" class="form-select form-select-solid"><option value="company">Perusahaan</option><option value="national">Nasional</option></select></div>
                        <div class="col-12 col-md-4 d-flex align-items-end">
                            <div class="att-checkbox-row w-100">
                                <label class="form-check form-check-custom form-check-solid mb-0">
                                    <input type="checkbox" name="is_paid" value="1" class="form-check-input" checked>
                                    <span class="form-check-label fw-semibold">Hari libur dibayar</span>
                                </label>
                            </div>
                        </div>
                        <div class="col-12 col-md-4 d-flex align-items-end">
                            <button class="btn btn-primary w-100"><i class="fas fa-plus me-1"></i>Tambah Libur</button>
                        </div>
                    </form>
                </div>
                <x-attendance-table id="holidays_table" :headers="['Tanggal','Nama','Tipe','Dibayar','Aksi']" />
            </div>

            {{-- ===== TEMPLATES ===== --}}
            <div class="tab-pane fade {{ $activeSection === 'templates' ? 'show active' : '' }}" id="tab_templates">
                <div class="att-form-card">
                    <div class="att-form-head">
                        <span class="icon"><i class="fas fa-calendar-week"></i></span>
                        <div>
                            <h3>Buat Template Mingguan</h3>
                            <p>Tentukan pola masuk/libur per hari, lalu assign ke banyak karyawan sekaligus.</p>
                        </div>
                    </div>
                    <form class="ajax-form template-days-form" data-table="templates_table" action="{{ route('admin.attendance.templates.store') }}">
                        @csrf
                        <div class="row g-3 mb-4">
                            <div class="col-12 col-md-6 col-lg-5">
                                <label class="form-label fw-bold">Nama Template</label>
                                <input name="name" class="form-control form-control-solid" placeholder="Contoh: Pola Kerja 6 Hari" required>
                                <div class="form-text">Atur tipe Masuk/Libur per hari sesuai pola kerja.</div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4 d-flex align-items-end">
                                <div class="att-checkbox-row w-100">
                                    <input type="hidden" name="is_active" value="0">
                                    <label class="form-check form-check-custom form-check-solid mb-0">
                                        <input type="checkbox" name="is_active" value="1" class="form-check-input" checked>
                                        <span class="form-check-label fw-semibold">Template aktif</span>
                                    </label>
                                </div>
                            </div>
                            <div class="col-12 col-lg-3 d-flex align-items-end">
                                <button class="btn btn-primary w-100"><i class="fas fa-plus me-1"></i>Tambah Template</button>
                            </div>
                        </div>
                        <div class="attendance-form-section">
                            <div class="attendance-form-section-title">Pola Hari</div>
                            <div class="table-responsive">
                                <table class="table align-middle table-row-dashed fs-6 gy-3 mb-0">
                                    <thead>
                                        <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                            <th class="min-w-110px">Hari</th>
                                            <th class="min-w-150px">Tipe Jadwal</th>
                                            <th class="min-w-250px">Shift</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach([1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'] as $dayNumber => $dayName)
                                            <tr class="template-day-row" data-day="{{ $dayNumber }}">
                                                <td class="fw-bold">{{ $dayName }}</td>
                                                <td>
                                                    <select class="form-select form-select-solid template-day-type">
                                                        <option value="work" selected>Masuk</option>
                                                        <option value="day_off">Libur</option>
                                                    </select>
                                                </td>
                                                <td>
                                                    <select class="form-select form-select-solid template-day-shift">
                                                        <option value="">Tanpa shift</option>
                                                        @foreach($shifts as $shift)
                                                            <option value="{{ $shift->id }}">{{ $shift->name }} ({{ substr($shift->start_time,0,5) }}-{{ substr($shift->end_time,0,5) }})</option>
                                                        @endforeach
                                                    </select>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="att-form-card">
                    <div class="att-form-head">
                        <span class="icon"><i class="fas fa-link"></i></span>
                        <div>
                            <h3>Assign Template ke Karyawan</h3>
                            <p>Tetapkan template mingguan ke karyawan tertentu untuk periode tertentu.</p>
                        </div>
                    </div>
                    <form class="row g-3 ajax-form" data-table="templates_table" action="{{ route('admin.attendance.templates.assign') }}">
                        @csrf
                        <div class="col-12 col-md-6 col-lg-4"><label class="form-label fw-bold">Karyawan</label><select name="employee_id" class="form-select form-select-solid" required>@foreach($employees as $employee)<option value="{{ $employee->id }}">{{ $employee->employee_code }} - {{ $employee->name }}</option>@endforeach</select></div>
                        <div class="col-12 col-md-6 col-lg-4"><label class="form-label fw-bold">Template</label><select name="weekly_schedule_template_id" class="form-select form-select-solid" required>@foreach($templates as $template)<option value="{{ $template->id }}">{{ $template->name }}</option>@endforeach</select></div>
                        <div class="col-6 col-md-6 col-lg-2"><label class="form-label fw-bold">Berlaku Dari</label><input type="text" name="effective_from" class="form-control form-control-solid js-date" placeholder="YYYY-MM-DD" required></div>
                        <div class="col-6 col-md-6 col-lg-2"><label class="form-label fw-bold">Berlaku Sampai</label><input type="text" name="effective_until" class="form-control form-control-solid js-date" placeholder="Opsional"></div>
                        <div class="col-12 d-flex justify-content-end pt-2">
                            <button class="btn btn-light-primary"><i class="fas fa-link me-1"></i>Assign Template</button>
                        </div>
                    </form>
                </div>

                <x-attendance-table id="templates_table" :headers="['Nama','Aktif','Isi Hari','Aksi']" />
            </div>

            {{-- ===== LEAVES ===== --}}
            <div class="tab-pane fade {{ $activeSection === 'leaves' ? 'show active' : '' }}" id="tab_leaves">
                <div class="att-form-card">
                    <div class="att-form-head">
                        <span class="icon"><i class="fas fa-plane-departure"></i></span>
                        <div>
                            <h3>Pengajuan Cuti / Izin</h3>
                            <p>Catat pengajuan cuti, sakit, atau izin karyawan beserta status approval-nya.</p>
                        </div>
                    </div>
                    <form class="row g-3 ajax-form" data-table="leaves_table" action="{{ route('admin.attendance.leaves.store') }}">
                        @csrf
                        <div class="col-12 col-md-6 col-lg-4"><label class="form-label fw-bold">Karyawan</label><select name="employee_id" class="form-select form-select-solid" required>@foreach($employees as $employee)<option value="{{ $employee->id }}">{{ $employee->employee_code }} - {{ $employee->name }}</option>@endforeach</select></div>
                        <div class="col-6 col-md-6 col-lg-2"><label class="form-label fw-bold">Tipe</label><select name="leave_type" class="form-select form-select-solid"><option value="annual">Cuti tahunan</option><option value="sick">Sakit</option><option value="permission">Izin</option><option value="unpaid">Unpaid</option></select></div>
                        <div class="col-6 col-md-6 col-lg-2"><label class="form-label fw-bold">Status</label><select name="status" class="form-select form-select-solid"><option value="pending">Pending</option><option value="approved">Approved</option><option value="rejected">Rejected</option></select></div>
                        <div class="col-6 col-md-6 col-lg-2"><label class="form-label fw-bold">Tanggal Mulai</label><input type="text" name="start_date" class="form-control form-control-solid js-date" placeholder="YYYY-MM-DD" required></div>
                        <div class="col-6 col-md-6 col-lg-2"><label class="form-label fw-bold">Tanggal Selesai</label><input type="text" name="end_date" class="form-control form-control-solid js-date" placeholder="YYYY-MM-DD" required></div>
                        <div class="col-12"><label class="form-label fw-bold">Alasan</label><input name="reason" class="form-control form-control-solid" placeholder="Alasan cuti / izin"></div>
                        <div class="col-12 d-flex justify-content-end pt-2">
                            <button class="btn btn-primary"><i class="fas fa-plus me-1"></i>Tambah Pengajuan</button>
                        </div>
                    </form>
                </div>
                <x-attendance-table id="leaves_table" :headers="['Karyawan','Tipe','Mulai','Selesai','Status','Alasan','Aksi']" />
            </div>

            {{-- ===== RAW LOGS ===== --}}
            <div class="tab-pane fade {{ $activeSection === 'raw_logs' ? 'show active' : '' }}" id="tab_raw_logs">
                <div class="att-form-card">
                    <div class="att-form-head">
                        <span class="icon"><i class="fas fa-list"></i></span>
                        <div>
                            <h3>Input Manual Raw Log</h3>
                            <p>Tambahkan log scan manual jika ada data yang tidak terkirim dari mesin.</p>
                        </div>
                    </div>
                    <form class="row g-3 ajax-form" data-table="raw_logs_table" action="{{ route('admin.attendance.raw-logs.store') }}">
                        @csrf
                        <div class="col-12 col-md-6 col-lg-4"><label class="form-label fw-bold">Device</label><select name="attendance_device_id" class="form-select form-select-solid" required>@foreach($devices as $device)<option value="{{ $device->id }}">{{ $device->name }}</option>@endforeach</select></div>
                        <div class="col-6 col-md-6 col-lg-2"><label class="form-label fw-bold">User ID Mesin</label><input name="device_user_id" class="form-control form-control-solid" placeholder="1001" required></div>
                        <div class="col-12 col-md-6 col-lg-3"><label class="form-label fw-bold">Waktu Scan</label><input type="text" name="scan_at" class="form-control form-control-solid js-datetime" placeholder="YYYY-MM-DD HH:mm" required></div>
                        <div class="col-6 col-md-6 col-lg-2"><label class="form-label fw-bold">Verify Type</label><input name="verify_type" class="form-control form-control-solid" placeholder="fingerprint"></div>
                        <div class="col-6 col-md-6 col-lg-1"><label class="form-label fw-bold">State</label><input name="state" class="form-control form-control-solid" placeholder="0"></div>
                        <div class="col-12 d-flex justify-content-end pt-2">
                            <button class="btn btn-primary"><i class="fas fa-plus me-1"></i>Tambah Scan</button>
                        </div>
                    </form>
                </div>
                <x-attendance-table id="raw_logs_table" :headers="['Device','Karyawan','Device User ID','Waktu Scan','Verify','State','Aksi']" />
            </div>

            {{-- ===== ATTENDANCES (Rekap) ===== --}}
            <div class="tab-pane fade {{ $activeSection === 'attendances' ? 'show active' : '' }}" id="tab_attendances">
                <div class="att-form-card">
                    <div class="att-form-head">
                        <span class="icon"><i class="fas fa-clipboard-check"></i></span>
                        <div>
                            <h3>Update Rekap Absensi</h3>
                            <p>Pilih baris di tabel lalu klik <em>Edit</em> untuk mengubah data rekap & approval lembur.</p>
                        </div>
                    </div>
                    <form class="ajax-form" data-table="attendances_table" action="#">
                        @csrf
                        <div class="attendance-form-section">
                            <div class="attendance-form-section-title">Data Kehadiran</div>
                            <div class="row g-3">
                                <div class="col-12 col-md-6 col-lg-4"><label class="form-label fw-bold">Karyawan</label><select name="employee_id" class="form-select form-select-solid" required>@foreach($employees as $employee)<option value="{{ $employee->id }}">{{ $employee->employee_code }} - {{ $employee->name }}</option>@endforeach</select></div>
                                <div class="col-6 col-md-6 col-lg-2"><label class="form-label fw-bold">Tanggal</label><input type="text" name="attendance_date" class="form-control form-control-solid js-date" placeholder="YYYY-MM-DD" required></div>
                                <div class="col-6 col-md-6 col-lg-3"><label class="form-label fw-bold">Shift</label><select name="work_shift_id" class="form-select form-select-solid"><option value="">Tanpa shift</option>@foreach($shifts as $shift)<option value="{{ $shift->id }}">{{ $shift->name }}</option>@endforeach</select></div>
                                <div class="col-6 col-md-6 col-lg-3"><label class="form-label fw-bold">Status Absensi</label><select name="status" class="form-select form-select-solid"><option value="present">Hadir</option><option value="late">Terlambat</option><option value="absent">Alpha</option><option value="incomplete">Belum Lengkap</option><option value="leave">Cuti/Izin</option><option value="holiday">Libur Perusahaan</option><option value="day_off">Libur</option></select></div>
                                <div class="col-12 col-md-6 col-lg-3"><label class="form-label fw-bold">Waktu Masuk</label><input type="text" name="check_in_at" class="form-control form-control-solid js-datetime" placeholder="YYYY-MM-DD HH:mm"></div>
                                <div class="col-12 col-md-6 col-lg-3"><label class="form-label fw-bold">Waktu Pulang</label><input type="text" name="check_out_at" class="form-control form-control-solid js-datetime" placeholder="YYYY-MM-DD HH:mm"></div>
                                <div class="col-4 col-md-3 col-lg-2"><label class="form-label fw-bold">Telat (Menit)</label><input type="number" name="late_minutes" min="0" value="0" class="form-control form-control-solid"></div>
                                <div class="col-4 col-md-3 col-lg-2"><label class="form-label fw-bold">Pulang Cepat</label><input type="number" name="early_leave_minutes" min="0" value="0" class="form-control form-control-solid"></div>
                                <div class="col-4 col-md-3 col-lg-2"><label class="form-label fw-bold">Menit Kerja</label><input type="number" name="work_minutes" min="0" value="0" class="form-control form-control-solid"></div>
                                <div class="col-12 col-md-6 col-lg-4"><label class="form-label fw-bold">Source</label><input name="source" class="form-control form-control-solid" value="manual"></div>
                                <div class="col-12 col-md-6 col-lg-8"><label class="form-label fw-bold">Catatan Absensi</label><input name="note" class="form-control form-control-solid" placeholder="Opsional"></div>
                            </div>
                        </div>
                        <div class="attendance-form-section">
                            <div class="attendance-form-section-title">Approval Lembur</div>
                            <div class="row g-3">
                                <div class="col-6 col-md-4 col-lg-3"><label class="form-label fw-bold">Lembur Terhitung</label><input type="number" name="calculated_overtime_minutes" min="0" value="0" class="form-control form-control-solid"></div>
                                <div class="col-6 col-md-4 col-lg-3"><label class="form-label fw-bold">Lembur Disetujui</label><input type="number" name="approved_overtime_minutes" min="0" class="form-control form-control-solid" placeholder="Menit final"></div>
                                <div class="col-12 col-md-4 col-lg-3"><label class="form-label fw-bold">Status Lembur</label><select name="overtime_status" class="form-select form-select-solid"><option value="none">Tidak Ada</option><option value="pending">Pending</option><option value="approved">Approved</option><option value="rejected">Rejected</option></select></div>
                                <div class="col-12"><label class="form-label fw-bold">Catatan Lembur</label><input name="overtime_note" class="form-control form-control-solid" placeholder="Alasan approve / reject / koreksi"></div>
                            </div>
                            <div class="d-flex justify-content-end mt-3">
                                <button class="btn btn-primary"><i class="fas fa-save me-1"></i>Update Rekap</button>
                            </div>
                        </div>
                    </form>
                </div>
                <x-attendance-table id="attendances_table" :headers="['Karyawan','Tanggal','Shift','Masuk','Pulang','Telat','Pulang Cepat','Menit Kerja','Lembur Hitung','Lembur Approved','Status Lembur','Status','Source','Catatan','Aksi']" />
            </div>
        </div>
    </div>
</div>

{{-- ===== Modal Jabatan ===== --}}
<div class="modal fade" id="modal_positions" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 class="fw-bolder mb-1"><i class="fas fa-briefcase text-primary me-2"></i>Kelola Jabatan</h2>
                    <div class="text-muted fs-7">Tambah, edit, atau nonaktifkan daftar jabatan karyawan.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="position_form" class="row g-3 mb-6" action="{{ route('admin.attendance.positions.store') }}" data-update-template="{{ route('admin.attendance.positions.update', ':id') }}">
                    @csrf
                    <input type="hidden" id="position_id" name="position_id">
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-bold">Nama Jabatan</label>
                        <input name="name" id="position_name" class="form-control form-control-solid" placeholder="Contoh: Picker" required>
                    </div>
                    <div class="col-12 col-md-5">
                        <label class="form-label fw-bold">Deskripsi</label>
                        <input name="description" id="position_description" class="form-control form-control-solid" placeholder="Opsional">
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label fw-bold">Status</label>
                        <select name="is_active" id="position_is_active" class="form-select form-select-solid">
                            <option value="1">Aktif</option>
                            <option value="0">Nonaktif</option>
                        </select>
                    </div>
                    <div class="col-12 d-flex flex-wrap gap-2 justify-content-end">
                        <button type="button" class="btn btn-light" id="position_reset"><i class="fas fa-undo me-1"></i>Reset</button>
                        <button class="btn btn-primary" id="position_submit"><i class="fas fa-plus me-1"></i>Tambah</button>
                    </div>
                </form>
                <x-attendance-table id="positions_table" :headers="['Nama','Deskripsi','Aktif','Aksi']" />
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('metronic/plugins/custom/fullcalendar/fullcalendar.bundle.js') }}"></script>
<script>
const csrfToken = '{{ csrf_token() }}';
const searchInput = document.getElementById('attendance_search');
const calendarEventsUrl = '{{ route('admin.attendance.schedules.calendar-events') }}';
const assignTemplateUrl = '{{ route('admin.attendance.templates.assign') }}';
const positionStoreUrl = '{{ route('admin.attendance.positions.store') }}';
const positionUpdateTpl = '{{ route('admin.attendance.positions.update', ':id') }}';
const positionDeleteTpl = '{{ route('admin.attendance.positions.destroy', ':id') }}';
const crudUrls = {
    employees_table: { update: '{{ route('admin.attendance.employees.update', ':id') }}', destroy: '{{ route('admin.attendance.employees.destroy', ':id') }}' },
    devices_table: { update: '{{ route('admin.attendance.devices.update', ':id') }}', destroy: '{{ route('admin.attendance.devices.destroy', ':id') }}' },
    fingerprints_table: { update: '{{ route('admin.attendance.fingerprints.update', ':id') }}', destroy: '{{ route('admin.attendance.fingerprints.destroy', ':id') }}' },
    shifts_table: { update: '{{ route('admin.attendance.shifts.update', ':id') }}', destroy: '{{ route('admin.attendance.shifts.destroy', ':id') }}' },
    schedules_table: { update: '{{ route('admin.attendance.schedules.update', ':id') }}', destroy: '{{ route('admin.attendance.schedules.destroy', ':id') }}' },
    holidays_table: { update: '{{ route('admin.attendance.holidays.update', ':id') }}', destroy: '{{ route('admin.attendance.holidays.destroy', ':id') }}' },
    templates_table: { update: '{{ route('admin.attendance.templates.update', ':id') }}', destroy: '{{ route('admin.attendance.templates.destroy', ':id') }}' },
    leaves_table: { update: '{{ route('admin.attendance.leaves.update', ':id') }}', destroy: '{{ route('admin.attendance.leaves.destroy', ':id') }}' },
    raw_logs_table: { update: '{{ route('admin.attendance.raw-logs.update', ':id') }}', destroy: '{{ route('admin.attendance.raw-logs.destroy', ':id') }}' },
    attendances_table: { update: '{{ route('admin.attendance.attendances.update', ':id') }}', destroy: '{{ route('admin.attendance.attendances.destroy', ':id') }}' },
};
function renderAttendanceStatusBadge(value) {
    const labels = {
        present: 'Hadir',
        late: 'Terlambat',
        absent: 'Absen',
        leave: 'Cuti/Izin',
        holiday: 'Libur',
        day_off: 'Libur',
        incomplete: 'Belum Lengkap',
    };
    const classes = {
        present: 'badge-light-success',
        late: 'badge-light-warning',
        absent: 'badge-light-danger',
        leave: 'badge-light-info',
        holiday: 'badge-light-danger',
        day_off: 'badge-light-secondary',
        incomplete: 'badge-light-primary',
    };

    return `<span class="badge ${classes[value] || 'badge-light'}">${labels[value] || value || '-'}</span>`;
}
function renderOvertimeStatusBadge(value) {
    const labels = {
        none: 'Tidak Ada',
        pending: 'Pending',
        approved: 'Approved',
        rejected: 'Rejected',
    };
    const classes = {
        none: 'badge-light-secondary',
        pending: 'badge-light-warning',
        approved: 'badge-light-success',
        rejected: 'badge-light-danger',
    };

    return `<span class="badge ${classes[value] || 'badge-light'}">${labels[value] || value || '-'}</span>`;
}
const tableConfigs = {
    employees_table: { url: '{{ route('admin.attendance.employees.data') }}', columns: ['employee_code','name','area','user','phone','position','employment_status','__actions'] },
    positions_table: { url: '{{ route('admin.attendance.positions.data') }}', columns: [
        'name',
        'description',
        'is_active',
        { data: 'id', render: (value, row) => `
            <button class="btn btn-sm btn-light-primary btn-position-edit me-2" data-id="${value}" data-name="${escapeAttr(row.name)}" data-description="${escapeAttr(row.description || '')}" data-is-active="${row.is_active ? 1 : 0}">Edit</button>
            <button class="btn btn-sm btn-light-danger btn-position-delete" data-id="${value}">Hapus</button>
        ` },
    ] },
    devices_table: { url: '{{ route('admin.attendance.devices.data') }}', columns: ['name','serial_number','ip_address','port','location','device_type','is_active','last_synced_at','__actions'] },
    fingerprints_table: { url: '{{ route('admin.attendance.fingerprints.data') }}', columns: ['employee','device','device_user_id','fingerprint_uid','is_active','enrolled_at','__actions'] },
    shifts_table: { url: '{{ route('admin.attendance.shifts.data') }}', columns: ['name','start_time','end_time','break_start_time','late_tolerance_minutes','checkout_tolerance_minutes','overtime_start_after_minutes','minimum_overtime_minutes','crosses_midnight','is_active','__actions'] },
    schedules_table: { url: '{{ route('admin.attendance.schedules.data') }}', columns: ['employee','schedule_date','schedule_type','shift','note','__actions'] },
    holidays_table: { url: '{{ route('admin.attendance.holidays.data') }}', columns: ['holiday_date','name','type','is_paid','__actions'] },
    templates_table: { url: '{{ route('admin.attendance.templates.data') }}', columns: ['name','is_active','days','__actions'] },
    leaves_table: { url: '{{ route('admin.attendance.leaves.data') }}', columns: ['employee','leave_type','start_date','end_date','status','reason','__actions'] },
    raw_logs_table: { url: '{{ route('admin.attendance.raw-logs.data') }}', columns: ['device','employee','device_user_id','scan_at','verify_type','state','__actions'] },
    attendances_table: { url: '{{ route('admin.attendance.attendances.data') }}', columns: ['employee','attendance_date','shift','check_in_at','check_out_at','late_minutes','early_leave_minutes','work_minutes','calculated_overtime_minutes','approved_overtime_minutes',{ data: 'overtime_status', render: renderOvertimeStatusBadge },{ data: 'status', render: renderAttendanceStatusBadge },'source','note','__actions'] },
};
const tables = {};
const tabTableMap = {
    tab_employees: ['employees_table'],
    tab_devices: ['devices_table'],
    tab_fingerprints: ['fingerprints_table'],
    tab_shifts: ['shifts_table'],
    tab_schedules: ['schedules_table'],
    tab_holidays: ['holidays_table'],
    tab_templates: ['templates_table'],
    tab_leaves: ['leaves_table'],
    tab_raw_logs: ['raw_logs_table'],
    tab_attendances: ['attendances_table'],
};

const escapeAttr = (value) => String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('"', '&quot;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;');

const crudUrl = (tableId, action, id) => crudUrls[tableId]?.[action]?.replace(':id', id);
const renderCrudActions = (tableId, row) => {
    if (!row?.id || !crudUrls[tableId]) return '-';
    const payload = escapeAttr(encodeURIComponent(JSON.stringify(row)));

    return `
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-light-primary btn-crud-edit" data-table="${tableId}" data-row="${payload}"><i class="fas fa-pen me-1"></i>Edit</button>
            <button type="button" class="btn btn-sm btn-light-danger btn-crud-delete" data-table="${tableId}" data-id="${row.id}"><i class="fas fa-trash me-1"></i>Hapus</button>
        </div>
    `;
};

const renderValue = (value) => {
    if (Array.isArray(value)) {
        return value.map((day) => `${day.day_of_week}: ${day.schedule_type}${day.shift ? ' - ' + day.shift : ''}`).join('<br>');
    }
    if (value === true || value === 1) return '<span class="badge badge-light-success">Ya</span>';
    if (value === false || value === 0) return '<span class="badge badge-light-secondary">Tidak</span>';
    return value ?? '-';
};

document.addEventListener('DOMContentLoaded', () => {
    if (typeof $ !== 'undefined' && $.fn.select2) {
        document.querySelectorAll('#tab_employees select, #tab_fingerprints select, #tab_schedules select, #tab_holidays select, #tab_templates select, #tab_leaves select, #tab_raw_logs select, #tab_attendances select, #modal_positions select').forEach((select) => {
            const allowClear = select.querySelector('option[value=""]') !== null;
            const parentModal = select.closest('.modal');
            $(select).select2({
                width: '100%',
                allowClear,
                placeholder: select.querySelector('option[value=""]')?.textContent || 'Pilih',
                minimumResultsForSearch: 0,
                dropdownParent: parentModal ? $(parentModal) : $(document.body),
            });
        });
    }

    if (typeof flatpickr !== 'undefined') {
        document.querySelectorAll('.js-date').forEach((input) => {
            flatpickr(input, {
                dateFormat: 'Y-m-d',
                allowInput: true,
            });
        });
        document.querySelectorAll('.js-time').forEach((input) => {
            flatpickr(input, {
                enableTime: true,
                noCalendar: true,
                dateFormat: 'H:i',
                time_24hr: true,
                allowInput: true,
            });
        });
        document.querySelectorAll('.js-datetime').forEach((input) => {
            flatpickr(input, {
                enableTime: true,
                dateFormat: 'Y-m-d H:i',
                time_24hr: true,
                allowInput: true,
            });
        });
    }

    const initAttendanceTable = (id) => {
        if (tables[id]) return tables[id];

        const config = tableConfigs[id];
        if (!config) return null;

        const tableEl = $('#' + id);
        if (!tableEl.length || !$.fn.DataTable) return null;

        tables[id] = tableEl.DataTable({
            processing: true,
            serverSide: true,
            dom: 'rtip',
            language: {
                processing: '<div class="text-muted py-4"><span class="spinner-border spinner-border-sm me-2"></span>Memuat data...</div>',
                emptyTable: '<div class="text-center py-8 text-muted"><i class="fas fa-inbox fs-2 d-block mb-2"></i>Belum ada data</div>',
                zeroRecords: '<div class="text-center py-8 text-muted"><i class="fas fa-search fs-2 d-block mb-2"></i>Tidak ada data yang cocok</div>',
                info: 'Menampilkan _START_–_END_ dari _TOTAL_ data',
                infoEmpty: '0 data',
                infoFiltered: '(difilter dari _MAX_ data)',
                paginate: { first: '«', last: '»', next: '›', previous: '‹' },
            },
            ajax: {
                url: config.url,
                dataSrc: 'data',
                data: (params) => {
                    params.q = searchInput?.value || '';
                },
            },
            columns: config.columns.map((column) => {
                if (typeof column === 'object') {
                    return {
                        data: column.data,
                        render: (value, type, row) => column.render(value, row),
                        orderable: false,
                    };
                }
                if (column === '__actions') {
                    return {
                        data: null,
                        render: (value, type, row) => renderCrudActions(id, row),
                        orderable: false,
                    };
                }

                return {
                    data: column,
                    render: renderValue,
                    orderable: false,
                };
            }),
        });

        return tables[id];
    };

    const initTablesForTab = (tabId) => {
        (tabTableMap[tabId] || []).forEach(initAttendanceTable);
    };

    const activeTabId = document.querySelector('.tab-pane.active')?.id;
    if (activeTabId) {
        initTablesForTab(activeTabId);
    }

    searchInput?.addEventListener('keyup', () => {
        const activeTabId = document.querySelector('.tab-pane.active')?.id;
        (tabTableMap[activeTabId] || []).forEach((tableId) => {
            initAttendanceTable(tableId)?.ajax.reload();
        });
    });

    const resetFormsInTab = (tabId) => {
        const tab = document.getElementById(tabId);
        if (!tab) return;

        tab.querySelectorAll('form.ajax-form').forEach((form) => {
            form.reset();
            clearEditState(form);
            if (typeof $ !== 'undefined') {
                $(form).find('select').trigger('change.select2');
            }
        });
        updateTemplateShiftState(tab);
    };

    const refreshAttendanceTab = (tabId, options = {}) => {
        const shouldResetForms = options.resetForms ?? false;

        if (shouldResetForms) {
            resetFormsInTab(tabId);
        }

        (tabTableMap[tabId] || []).forEach((tableId) => {
            initAttendanceTable(tableId)?.ajax.reload(null, false);
        });

        if (tabId === 'tab_schedules') {
            initScheduleCalendar();
            scheduleCalendar?.refetchEvents();
            scheduleCalendar?.updateSize();
        }
    };

    let scheduleCalendar = null;
    const calendarEl = document.getElementById('attendance_schedule_calendar');
    const calendarEmployeeFilter = document.getElementById('calendar_employee_filter');
    const initScheduleCalendar = () => {
        if (!calendarEl || scheduleCalendar || typeof FullCalendar === 'undefined') return;

        scheduleCalendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            height: 680,
            locale: 'id',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,listMonth',
            },
            buttonText: {
                today: 'Hari ini',
                month: 'Bulan',
                week: 'Minggu',
                list: 'List',
            },
            dayMaxEvents: 4,
            eventSources: [{
                events: (info, success, failure) => {
                    const params = new URLSearchParams({
                        start: info.startStr,
                        end: info.endStr,
                    });
                    if (calendarEmployeeFilter?.value) {
                        params.set('employee_id', calendarEmployeeFilter.value);
                    }

                    fetch(`${calendarEventsUrl}?${params.toString()}`, {
                        headers: { 'Accept': 'application/json' },
                    })
                        .then((response) => response.ok ? response.json() : Promise.reject(response))
                        .then(success)
                        .catch(failure);
                },
            }],
            eventClick: (info) => {
                const props = info.event.extendedProps || {};
                const details = Array.isArray(props.details) ? props.details : [];
                const escapeHtml = (value) => String(value || '').replace(/[&<>"']/g, (char) => ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;',
                }[char]));
                const html = details.length
                    ? `<div class="attendance-calendar-detail"><ol>${details.map((row) => `<li>${escapeHtml(row)}</li>`).join('')}</ol></div>`
                    : `<div class="attendance-calendar-detail">${escapeHtml(info.event.title)}</div>`;

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: escapeHtml(info.event.title),
                        html,
                        icon: 'info',
                        width: 720,
                    });
                }
            },
        });
        scheduleCalendar.render();
    };

    document.querySelector('a[href="#tab_schedules"]')?.addEventListener('shown.bs.tab', () => {
        initScheduleCalendar();
        scheduleCalendar?.updateSize();
    });
    document.querySelectorAll('a[data-bs-toggle="tab"]').forEach((tabLink) => {
        tabLink.addEventListener('shown.bs.tab', (event) => {
            const tabId = event.target.getAttribute('href')?.replace('#', '');
            if (tabId) {
                initTablesForTab(tabId);
                refreshAttendanceTab(tabId);
            }
        });
    });
    document.getElementById('modal_positions')?.addEventListener('shown.bs.modal', () => {
        initAttendanceTable('positions_table')?.ajax.reload(null, false);
    });
    if (document.getElementById('tab_schedules')?.classList.contains('show')) {
        initScheduleCalendar();
    }
    calendarEmployeeFilter?.addEventListener('change', () => scheduleCalendar?.refetchEvents());
    document.getElementById('calendar_refresh')?.addEventListener('click', () => scheduleCalendar?.refetchEvents());
    document.getElementById('attendance_refresh_tab')?.addEventListener('click', () => {
        const activeTabId = document.querySelector('.tab-pane.active')?.id;
        if (!activeTabId) return;

        refreshAttendanceTab(activeTabId, { resetForms: true });
        Swal?.fire('Berhasil', 'Data tab aktif dimuat ulang.', 'success');
    });

    const formSelectorByTable = {
        templates_table: '.template-days-form',
    };
    const formForTable = (tableId) => document.querySelector(formSelectorByTable[tableId] || `form.ajax-form[data-table="${tableId}"]`);
    const setFieldValue = (form, name, value) => {
        const field = form.querySelector(`[name="${name}"][type="checkbox"]`) || form.querySelector(`[name="${name}"]`);
        if (!field) return;

        if (field.type === 'checkbox') {
            field.checked = value === true || value === 1 || value === '1';
        } else {
            field.value = value ?? '';
        }

        if (typeof $ !== 'undefined' && $(field).data('select2')) {
            $(field).trigger('change.select2');
        }
    };
    const clearEditState = (form) => {
        delete form.dataset.editUrl;
        delete form.dataset.editingId;
        const submit = form.querySelector('button[type="submit"], button:not([type])');
        if (submit && submit.dataset.createText) {
            submit.innerHTML = submit.dataset.createText;
        }
    };
    const setEditState = (form, tableId, row) => {
        form.dataset.editUrl = crudUrl(tableId, 'update', row.id);
        form.dataset.editingId = row.id;
        const submit = form.querySelector('button[type="submit"], button:not([type])');
        if (submit) {
            submit.dataset.createText ||= submit.innerHTML;
            submit.innerHTML = '<i class="fas fa-save me-1"></i>Update';
        }
    };
    const fillTemplateForm = (form, row) => {
        setFieldValue(form, 'name', row.name);
        setFieldValue(form, 'is_active', row.is_active ? 1 : 0);
        const days = Array.isArray(row.days) ? row.days : [];
        form.querySelectorAll('.template-day-row').forEach((dayRow) => {
            const day = days.find((item) => String(item.day_of_week) === String(dayRow.dataset.day));
            const type = dayRow.querySelector('.template-day-type');
            const shift = dayRow.querySelector('.template-day-shift');
            type.value = day?.schedule_type || 'day_off';
            shift.value = day?.work_shift_id || '';
            if (typeof $ !== 'undefined' && $(type).data('select2')) {
                $(type).trigger('change.select2');
            }
        });
        updateTemplateShiftState(form);
    };
    const fillCrudForm = (tableId, row) => {
        const form = formForTable(tableId);
        if (!form || !crudUrls[tableId]?.update) return;

        form.reset();
        if (tableId === 'templates_table') {
            fillTemplateForm(form, row);
        } else {
            Object.entries(row).forEach(([key, value]) => setFieldValue(form, key, value));
        }
        setEditState(form, tableId, row);
        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    document.querySelectorAll('.ajax-form').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const formData = new FormData(form);
            const isEditing = Boolean(form.dataset.editUrl);
            if (isEditing) {
                formData.append('_method', 'PUT');
            } else if (form.getAttribute('action') === '#') {
                Swal?.fire('Info', 'Pilih data dari tabel lalu klik Edit terlebih dahulu.', 'info');
                return;
            }
            if (form.classList.contains('template-days-form')) {
                let hasWorkDayWithoutShift = false;
                form.querySelectorAll('.template-day-row').forEach((row, index) => {
                    const type = row.querySelector('.template-day-type')?.value || 'day_off';
                    const shiftId = row.querySelector('.template-day-shift')?.value || '';
                    if (type === 'work' && !shiftId) {
                        hasWorkDayWithoutShift = true;
                    }
                    formData.append(`days[${index}][day_of_week]`, row.dataset.day || String(index + 1));
                    formData.append(`days[${index}][schedule_type]`, type);
                    if (shiftId) {
                        formData.append(`days[${index}][work_shift_id]`, shiftId);
                    }
                });

                if (hasWorkDayWithoutShift) {
                    Swal?.fire('Error', 'Hari dengan tipe Masuk wajib memilih shift.', 'error');
                    return;
                }
            }

            try {
                const response = await fetch(isEditing ? form.dataset.editUrl : form.action, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    body: formData,
                });
                const json = await response.json().catch(() => ({}));
                if (!response.ok) {
                    const firstError = json?.errors ? Object.values(json.errors)[0]?.[0] : null;
                    Swal?.fire('Error', firstError || json?.message || 'Gagal menyimpan data', 'error');
                    return;
                }
                if (json?.notification?.late_check_in) {
                    Swal?.fire('Absensi Terlambat', json.notification.message || 'Karyawan terlambat.', 'warning');
                } else {
                    Swal?.fire('Berhasil', json?.message || 'Data tersimpan', 'success');
                }
                form.reset();
                clearEditState(form);
                $(form).find('select').trigger('change.select2');
                updateTemplateShiftState(form);
                const tableId = form.getAttribute('data-table');
                tables[tableId]?.ajax.reload();
                if (form.action === assignTemplateUrl) {
                    tables.schedules_table?.ajax.reload();
                }
                scheduleCalendar?.refetchEvents();
            } catch (error) {
                Swal?.fire('Error', 'Gagal mengirim request', 'error');
            }
        });
    });

    document.addEventListener('click', async (event) => {
        const editButton = event.target.closest('.btn-crud-edit');
        if (editButton) {
            const row = JSON.parse(decodeURIComponent(editButton.dataset.row || '{}'));
            fillCrudForm(editButton.dataset.table, row);
            return;
        }

        const deleteButton = event.target.closest('.btn-crud-delete');
        if (!deleteButton) return;

        const tableId = deleteButton.dataset.table;
        const deleteUrl = crudUrl(tableId, 'destroy', deleteButton.dataset.id);
        if (!deleteUrl) return;

        const confirmation = typeof Swal !== 'undefined'
            ? await Swal.fire({
                title: 'Hapus data?',
                text: 'Data yang dihapus tidak bisa dikembalikan.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Hapus',
                cancelButtonText: 'Batal',
            })
            : { isConfirmed: confirm('Hapus data ini?') };

        if (!confirmation.isConfirmed) return;

        const response = await fetch(deleteUrl, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
        });
        const json = await response.json().catch(() => ({}));
        if (!response.ok) {
            Swal?.fire('Error', json?.message || 'Gagal menghapus data', 'error');
            return;
        }

        Swal?.fire('Berhasil', json?.message || 'Data berhasil dihapus', 'success');
        tables[tableId]?.ajax.reload(null, false);
        scheduleCalendar?.refetchEvents();
    });

    const updateTemplateShiftState = (scope = document) => {
        scope.querySelectorAll('.template-day-row').forEach((row) => {
            const type = row.querySelector('.template-day-type');
            const shift = row.querySelector('.template-day-shift');
            if (!type || !shift) return;
            const disabled = type.value !== 'work';
            shift.disabled = disabled;
            if (disabled) {
                shift.value = '';
            }
            if (typeof $ !== 'undefined' && $(shift).data('select2')) {
                $(shift).trigger('change.select2');
            }
        });
    };

    document.querySelectorAll('.template-day-type').forEach((select) => {
        select.addEventListener('change', () => updateTemplateShiftState(select.closest('form') || document));
    });
    updateTemplateShiftState(document);

    const positionForm = document.getElementById('position_form');
    const positionId = document.getElementById('position_id');
    const positionName = document.getElementById('position_name');
    const positionDescription = document.getElementById('position_description');
    const positionActive = document.getElementById('position_is_active');
    const positionSubmit = document.getElementById('position_submit');
    const employeePositionSelect = document.getElementById('employee_position_id');

    const resetPositionForm = () => {
        positionForm?.reset();
        if (positionId) positionId.value = '';
        if (positionSubmit) positionSubmit.innerHTML = '<i class="fas fa-plus me-1"></i>Tambah';
        if (positionActive && typeof $ !== 'undefined' && $(positionActive).data('select2')) {
            $(positionActive).val('1').trigger('change.select2');
        }
    };

    const upsertPositionOption = (position) => {
        if (!position?.is_active || !employeePositionSelect) return;
        const value = String(position.id);
        let option = employeePositionSelect.querySelector(`option[value="${value}"]`);
        if (!option) {
            option = new Option(position.name, value, false, false);
            employeePositionSelect.add(option);
        } else {
            option.textContent = position.name;
        }
        if (typeof $ !== 'undefined' && $(employeePositionSelect).data('select2')) {
            $(employeePositionSelect).trigger('change.select2');
        }
    };

    positionForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const id = positionId?.value || '';
        const formData = new FormData(positionForm);
        if (id) formData.append('_method', 'PUT');

        try {
            const response = await fetch(id ? positionUpdateTpl.replace(':id', id) : positionStoreUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: formData,
            });
            const json = await response.json().catch(() => ({}));
            if (!response.ok) {
                const firstError = json?.errors ? Object.values(json.errors)[0]?.[0] : null;
                Swal?.fire('Error', firstError || json?.message || 'Gagal menyimpan jabatan', 'error');
                return;
            }
            Swal?.fire('Berhasil', json?.message || 'Jabatan tersimpan', 'success');
            upsertPositionOption(json.position);
            resetPositionForm();
            tables.positions_table?.ajax.reload();
        } catch (error) {
            Swal?.fire('Error', 'Gagal menyimpan jabatan', 'error');
        }
    });

    document.getElementById('position_reset')?.addEventListener('click', resetPositionForm);
    document.getElementById('modal_positions')?.addEventListener('shown.bs.modal', () => {
        tables.positions_table?.columns.adjust();
    });

    $('#positions_table').on('click', '.btn-position-edit', function () {
        if (positionId) positionId.value = this.dataset.id || '';
        if (positionName) positionName.value = this.dataset.name || '';
        if (positionDescription) positionDescription.value = this.dataset.description || '';
        if (positionActive) {
            positionActive.value = this.dataset.isActive || '1';
            if (typeof $ !== 'undefined' && $(positionActive).data('select2')) {
                $(positionActive).trigger('change.select2');
            }
        }
        if (positionSubmit) positionSubmit.innerHTML = '<i class="fas fa-save me-1"></i>Update';
        positionName?.focus();
    });

    $('#positions_table').on('click', '.btn-position-delete', async function () {
        const id = this.dataset.id;
        if (!id) return;
        const confirm = typeof Swal === 'undefined'
            ? { isConfirmed: window.confirm('Hapus jabatan ini?') }
            : await Swal.fire({ title: 'Hapus jabatan?', text: 'Data jabatan yang sudah dipakai karyawan tidak bisa dihapus.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Hapus', cancelButtonText: 'Batal' });
        if (!confirm.isConfirmed) return;

        try {
            const response = await fetch(positionDeleteTpl.replace(':id', id), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: new URLSearchParams({ _method: 'DELETE' }),
            });
            const json = await response.json().catch(() => ({}));
            if (!response.ok) {
                Swal?.fire('Error', json?.message || 'Gagal menghapus jabatan', 'error');
                return;
            }
            Swal?.fire('Berhasil', json?.message || 'Jabatan terhapus', 'success');
            employeePositionSelect?.querySelector(`option[value="${id}"]`)?.remove();
            if (typeof $ !== 'undefined' && $(employeePositionSelect).data('select2')) {
                $(employeePositionSelect).trigger('change.select2');
            }
            tables.positions_table?.ajax.reload();
        } catch (error) {
            Swal?.fire('Error', 'Gagal menghapus jabatan', 'error');
        }
    });
});
</script>
@endpush
