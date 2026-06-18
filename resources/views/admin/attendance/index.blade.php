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
    .att-form-card.in-modal {
        border: 0;
        border-radius: 0;
        padding: 0;
        margin-bottom: 0;
        box-shadow: none;
    }
    .att-form-card.in-modal + .att-form-card.in-modal {
        border-top: 1px dashed #e4e6ef;
        padding-top: 1.25rem;
        margin-top: 1.25rem;
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
    .att-toolbar-actions {
        display: flex;
        flex-wrap: wrap;
        gap: .5rem;
        justify-content: flex-end;
    }

    .attendance-table-wrap {
        border: 1px solid #eef0f8;
        border-radius: .85rem;
        background: #fff;
    }
    .attendance-table-wrap table {
        margin-bottom: 0;
        min-width: 980px;
    }
    .attendance-table-wrap thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #f9fafc;
        border-bottom: 1px solid #eef0f8;
        font-size: .72rem;
        letter-spacing: .04em;
    }
    .attendance-table-wrap tbody td {
        vertical-align: middle;
    }
    .attendance-row-actions {
        display: flex;
        gap: .5rem;
        flex-wrap: nowrap;
    }
    .attendance-form-bank {
        display: none;
    }
    .attendance-form-empty {
        border: 1px dashed #e4e6ef;
        border-radius: .85rem;
        padding: 1rem;
        color: #7e8299;
        background: #f9fafc;
    }
    .schedule-list-panel {
        border: 1px solid #eef0f8;
        border-radius: .9rem;
        background: #fff;
        padding: 1rem;
    }
    .schedule-list-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: .75rem;
        margin-bottom: 1rem;
    }
    .schedule-list-heading {
        min-width: 0;
        flex: 1 1 260px;
    }
    .schedule-list-heading .title {
        color: #181c32;
        font-weight: 800;
        font-size: .95rem;
    }
    .schedule-list-heading .meta {
        color: #7e8299;
        font-size: .76rem;
        margin-top: .15rem;
    }
    .schedule-filter-trigger {
        position: relative;
    }
    .schedule-filter-count {
        min-width: 20px;
        height: 20px;
        border-radius: 999px;
        background: #1b84ff;
        color: #fff;
        font-size: .68rem;
        font-weight: 800;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0 .35rem;
        margin-left: .35rem;
    }
    .schedule-active-filters {
        display: flex;
        flex-wrap: wrap;
        gap: .4rem;
        margin-bottom: 1rem;
    }
    .schedule-filter-chip {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        border-radius: 999px;
        background: #f1f7ff;
        color: #1b84ff;
        font-size: .72rem;
        font-weight: 700;
        padding: .38rem .65rem;
    }
    .schedule-filter-modal-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1rem;
    }
    .modal .flatpickr-calendar {
        z-index: 1070 !important;
    }
    .modal .flatpickr-wrapper {
        display: block;
        width: 100%;
    }
    .schedule-view-switch {
        display: inline-flex;
        padding: .25rem;
        border: 1px solid #e4e6ef;
        border-radius: .65rem;
        background: #f5f8fa;
        gap: .2rem;
    }
    .schedule-list-actions {
        display: flex;
        align-items: center;
        gap: .5rem;
        flex-wrap: wrap;
    }
    .schedule-view-button {
        border: 0;
        border-radius: .45rem;
        background: transparent;
        color: #7e8299;
        font-weight: 700;
        font-size: .78rem;
        padding: .55rem .8rem;
        white-space: nowrap;
    }
    .schedule-view-button.active {
        background: #fff;
        color: #1b84ff;
        box-shadow: 0 3px 10px rgba(31, 41, 55, .08);
    }
    .schedule-card-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 1rem;
    }
    .schedule-card {
        position: relative;
        display: flex;
        flex-direction: column;
        min-height: 245px;
        border: 1px solid #eef0f8;
        border-radius: .9rem;
        background: #fff;
        padding: 1rem;
        overflow: hidden;
        transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease;
    }
    .schedule-card::before {
        content: "";
        position: absolute;
        inset: 0 auto 0 0;
        width: 4px;
        background: #009ef7;
    }
    .schedule-card.day_off::before { background: #a1a5b7; }
    .schedule-card.holiday::before { background: #f1416c; }
    .schedule-card.leave::before { background: #ffc700; }
    .schedule-card:hover {
        transform: translateY(-2px);
        border-color: #d9e9ff;
        box-shadow: 0 12px 26px rgba(31, 41, 55, .08);
    }
    .schedule-card-head {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: .75rem;
    }
    .schedule-card-date {
        color: #1e1e2d;
        font-size: 1rem;
        font-weight: 800;
    }
    .schedule-card-day {
        color: #7e8299;
        font-size: .76rem;
        margin-top: .12rem;
    }
    .schedule-card-employee {
        display: flex;
        align-items: center;
        gap: .65rem;
        padding: .9rem 0;
        margin: .85rem 0;
        border-top: 1px dashed #e4e6ef;
        border-bottom: 1px dashed #e4e6ef;
    }
    .schedule-card-avatar {
        width: 40px;
        height: 40px;
        border-radius: .65rem;
        background: #eef6ff;
        color: #1b84ff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        flex: 0 0 40px;
    }
    .schedule-card-employee .name {
        color: #181c32;
        font-weight: 700;
    }
    .schedule-card-employee .code {
        color: #7e8299;
        font-size: .74rem;
        margin-top: .1rem;
    }
    .schedule-card-details {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .6rem;
    }
    .schedule-card-detail {
        border-radius: .6rem;
        background: #f9fafc;
        padding: .65rem;
        min-width: 0;
    }
    .schedule-card-detail .label {
        color: #a1a5b7;
        font-size: .65rem;
        font-weight: 700;
        text-transform: uppercase;
    }
    .schedule-card-detail .value {
        color: #3f4254;
        font-size: .8rem;
        font-weight: 700;
        margin-top: .2rem;
        overflow-wrap: anywhere;
    }
    .schedule-card-note {
        color: #7e8299;
        font-size: .76rem;
        line-height: 1.45;
        margin-top: .75rem;
        min-height: 34px;
    }
    .schedule-card-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        margin-top: auto;
        padding-top: .85rem;
    }
    .schedule-card-footer .attendance-row-actions {
        flex-wrap: wrap;
        justify-content: flex-end;
    }
    .schedule-card-pagination {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: .75rem;
        padding-top: 1rem;
        margin-top: 1rem;
        border-top: 1px solid #eef0f8;
    }
    .schedule-calendar-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: .75rem;
        padding-bottom: 1rem;
        margin-bottom: 1rem;
        border-bottom: 1px dashed #e4e6ef;
    }
    .schedule-card-empty {
        grid-column: 1 / -1;
        border: 1px dashed #d9e2ec;
        border-radius: .9rem;
        padding: 3rem 1rem;
        text-align: center;
        color: #7e8299;
        background: #fbfcfe;
    }
    .recap-summary-grid {
        display: grid;
        grid-template-columns: repeat(7, minmax(0, 1fr));
        gap: .75rem;
        margin-bottom: 1rem;
    }
    .recap-summary-card {
        border: 1px solid #eef0f8;
        border-radius: .75rem;
        background: #fff;
        padding: .85rem 1rem;
        min-width: 0;
    }
    .recap-summary-card .label {
        color: #7e8299;
        font-size: .7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .04em;
    }
    .recap-summary-card .value {
        color: #1e1e2d;
        font-size: 1.4rem;
        line-height: 1.2;
        font-weight: 800;
        margin-top: .2rem;
    }
    .recap-summary-card.primary { border-top: 3px solid #1b84ff; }
    .recap-summary-card.success { border-top: 3px solid #50cd89; }
    .recap-summary-card.warning { border-top: 3px solid #ffc700; }
    .recap-summary-card.info { border-top: 3px solid #7239ea; }
    .recap-summary-card.danger { border-top: 3px solid #f1416c; }
    .recap-summary-card.off { border-top: 3px solid #7e8299; }
    .recap-summary-card.overtime { border-top: 3px solid #009ef7; }
    .recap-action-trigger {
        position: relative;
    }
    .recap-overtime-alert-dot {
        position: absolute;
        top: -4px;
        right: -4px;
        width: 10px;
        height: 10px;
        border: 2px solid #fff;
        border-radius: 50%;
        background: #f1416c;
        box-shadow: 0 0 0 0 rgba(241, 65, 108, .65);
        animation: recapOvertimePulse 1.4s ease-out infinite;
    }
    @keyframes recapOvertimePulse {
        0% { box-shadow: 0 0 0 0 rgba(241, 65, 108, .65); }
        70% { box-shadow: 0 0 0 8px rgba(241, 65, 108, 0); }
        100% { box-shadow: 0 0 0 0 rgba(241, 65, 108, 0); }
    }
    @media (prefers-reduced-motion: reduce) {
        .recap-overtime-alert-dot {
            animation: none;
        }
    }
    .recap-employee {
        min-width: 170px;
    }
    .recap-employee .name {
        color: #181c32;
        font-weight: 700;
    }
    .recap-employee .code,
    .recap-cell-meta {
        color: #7e8299;
        font-size: .75rem;
        margin-top: .15rem;
    }
    .recap-time-pair {
        display: grid;
        grid-template-columns: repeat(2, minmax(58px, 1fr));
        gap: .4rem;
        min-width: 135px;
    }
    .recap-time {
        border: 1px solid #eef0f8;
        border-radius: .5rem;
        background: #f9fafc;
        padding: .4rem .5rem;
        text-align: center;
    }
    .recap-time .label {
        color: #a1a5b7;
        font-size: .62rem;
        font-weight: 700;
        text-transform: uppercase;
    }
    .recap-time .value {
        color: #3f4254;
        font-size: .8rem;
        font-weight: 700;
        margin-top: .1rem;
    }
    .recap-metric {
        display: inline-flex;
        align-items: center;
        gap: .3rem;
        border-radius: .45rem;
        padding: .25rem .45rem;
        background: #f5f8fa;
        color: #5e6278;
        font-size: .74rem;
        white-space: nowrap;
        margin: .1rem .15rem .1rem 0;
    }
    .recap-note {
        max-width: 220px;
        white-space: normal;
        color: #5e6278;
        font-size: .78rem;
        line-height: 1.45;
    }
    #attendances_table tbody tr:hover {
        background: #f8fbff;
    }
    @media (max-width: 1199px) {
        .schedule-card-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .recap-summary-grid {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }
    }

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

    /* ===== Template assignment workspace ===== */
    .att-assignment-panel {
        display: grid;
        grid-template-columns: minmax(0, 1.05fr) minmax(320px, .95fr);
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    .att-assignment-card {
        background: #fff;
        border: 1px solid #eef0f8;
        border-radius: .85rem;
        padding: 1.15rem;
        min-width: 0;
    }
    .att-assignment-title {
        display: flex;
        align-items: flex-start;
        gap: .75rem;
        padding-bottom: .85rem;
        margin-bottom: 1rem;
        border-bottom: 1px dashed #e4e6ef;
    }
    .att-assignment-title .icon {
        width: 38px;
        height: 38px;
        border-radius: .55rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 38px;
        background: #ecfdf3;
        color: #17c653;
    }
    .att-assignment-title h3 { font-size: 1rem; font-weight: 800; color: #1e1e2d; margin: 0; }
    .att-assignment-title p { color: #7e8299; font-size: .8rem; margin: .15rem 0 0; }
    .att-template-preview {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .55rem;
    }
    .att-template-day {
        border: 1px solid #eef0f8;
        border-radius: .65rem;
        padding: .7rem;
        background: #f9fafc;
        min-width: 0;
    }
    .att-template-day .day {
        font-size: .72rem;
        font-weight: 800;
        color: #3f4254;
        text-transform: uppercase;
    }
    .att-template-day .meta {
        font-size: .78rem;
        color: #7e8299;
        margin-top: .25rem;
        overflow-wrap: anywhere;
    }
    .att-template-day.work {
        background: #f1faff;
        border-color: #d7efff;
    }
    .att-assignment-result {
        border: 1px solid #d7f5e5;
        background: #f3fff8;
        border-radius: .75rem;
        padding: 1rem;
        margin-top: 1rem;
    }
    .att-assignment-result.d-none { display: none !important; }
    .att-result-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .75rem;
        margin-top: .75rem;
    }
    .att-result-item {
        background: #fff;
        border: 1px solid #d7f5e5;
        border-radius: .6rem;
        padding: .7rem;
    }
    .att-result-item .label {
        color: #7e8299;
        font-size: .72rem;
        font-weight: 700;
        text-transform: uppercase;
    }
    .att-result-item .value {
        color: #1e1e2d;
        font-size: .9rem;
        font-weight: 700;
        margin-top: .2rem;
        overflow-wrap: anywhere;
    }

    /* ===== Responsive tweaks ===== */
    @media (max-width: 768px) {
        .att-hero { padding: 1rem; }
        .att-hero-title { font-size: 1.2rem; }
        .att-form-card { padding: 1rem; }
        .att-toolbar { flex-direction: column; align-items: stretch; }
        .att-toolbar .search-wrap { max-width: 100%; }
        .att-toolbar-actions,
        .att-toolbar-actions .btn,
        #attendance_refresh_tab {
            width: 100%;
        }
        .att-toolbar-actions .btn,
        #attendance_refresh_tab {
            justify-content: center;
        }
        .attendance-form-section {
            padding: .85rem;
        }
        .att-assignment-panel {
            grid-template-columns: 1fr;
        }
        .att-template-preview,
        .att-result-grid {
            grid-template-columns: 1fr;
        }
        .recap-summary-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .schedule-card-grid,
        .schedule-filter-modal-grid {
            grid-template-columns: 1fr;
        }
        .schedule-view-switch {
            width: 100%;
        }
        .schedule-list-actions {
            width: 100%;
        }
        .schedule-list-actions .btn,
        .schedule-filter-trigger {
            flex: 1 1 auto;
        }
        .schedule-calendar-toolbar > div,
        .schedule-calendar-toolbar select,
        .schedule-calendar-toolbar .btn {
            width: 100%;
        }
        .schedule-view-button {
            flex: 1;
        }
        #attendance_schedule_calendar { min-height: 560px; }
        #attendance_schedule_calendar .fc-toolbar {
            flex-direction: column;
            gap: .75rem;
        }
        .modal-dialog {
            margin: .75rem;
        }
        .modal-body {
            padding: 1rem;
        }
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
            <div class="att-toolbar-actions">
                <button type="button" class="btn btn-light-primary d-none" id="attendance_export_employees">
                    <i class="fas fa-file-excel me-1"></i>Export Karyawan
                </button>
                <button type="button" class="btn btn-light-primary d-none" id="attendance_import_employees">
                    <i class="fas fa-file-import me-1"></i>Import Karyawan
                </button>
                <button type="button" class="btn btn-light-primary d-none" id="attendance_export_templates">
                    <i class="fas fa-file-excel me-1"></i>Export Template
                </button>
                <button type="button" class="btn btn-light-primary d-none" id="attendance_import_templates">
                    <i class="fas fa-file-import me-1"></i>Import Template
                </button>
                <button type="button" class="btn btn-light-primary d-none" id="attendance_export_shifts">
                    <i class="fas fa-file-excel me-1"></i>Export Shift
                </button>
                <button type="button" class="btn btn-light-primary d-none" id="attendance_export_attendances">
                    <i class="fas fa-file-excel me-1"></i>Export Rekap
                </button>
                <button type="button" class="btn btn-light-primary d-none" id="attendance_import_shifts">
                    <i class="fas fa-file-import me-1"></i>Import Shift
                </button>
                <button type="button" class="btn btn-primary" id="attendance_open_form" data-active-section="{{ $activeSection }}">
                    <i class="fas fa-plus me-1"></i><span>Tambah {{ $activeSectionLabel }}</span>
                </button>
                <button type="button" class="btn btn-light" id="attendance_clear_search">
                    <i class="fas fa-times me-1"></i>Reset Cari
                </button>
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
                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label fw-bold">Kode Karyawan</label>
                            <input name="employee_code" class="form-control form-control-solid" value="{{ $nextEmployeeCode ?? 'K0001' }}" readonly>
                            <div class="form-text">Dibuat otomatis oleh sistem dan tidak bisa diedit.</div>
                        </div>
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
                        <div class="col-6 col-md-6 col-lg-2"><label class="form-label fw-bold">Tanggal Jadwal</label><input type="text" name="schedule_date" class="form-control form-control-solid js-date js-schedule-date" min="{{ today()->toDateString() }}" placeholder="YYYY-MM-DD" required></div>
                        <div class="col-6 col-md-6 col-lg-2"><label class="form-label fw-bold">Tipe</label><select name="schedule_type" class="form-select form-select-solid"><option value="work">Masuk</option><option value="day_off">Libur</option><option value="holiday">Libur Perusahaan</option><option value="leave">Cuti/Izin</option></select></div>
                        <div class="col-12 col-md-6 col-lg-4"><label class="form-label fw-bold">Shift</label><select name="work_shift_id" class="form-select form-select-solid"><option value="">Tanpa shift</option>@foreach($shifts as $shift)<option value="{{ $shift->id }}">{{ $shift->name }} ({{ substr($shift->start_time,0,5) }}-{{ substr($shift->end_time,0,5) }})</option>@endforeach</select></div>
                        <div class="col-12 col-md-9"><label class="form-label fw-bold">Catatan</label><input name="note" class="form-control form-control-solid" placeholder="Opsional"></div>
                        <div class="col-12 col-md-3 d-flex align-items-end">
                            <button class="btn btn-primary w-100"><i class="fas fa-save me-1"></i>Simpan Jadwal</button>
                        </div>
                    </form>
                </div>

                <div class="schedule-list-panel">
                    <div class="schedule-list-toolbar">
                        <div class="schedule-list-heading">
                            <div class="title">Daftar Jadwal Karyawan</div>
                            <div class="meta" id="schedule_filter_summary">Menampilkan seluruh jadwal</div>
                        </div>
                        <div class="schedule-list-actions">
                            <button type="button" class="btn btn-light-primary btn-sm schedule-filter-trigger" data-bs-toggle="modal" data-bs-target="#schedule_filter_modal">
                                <i class="fas fa-sliders-h me-1"></i>Filter
                                <span class="schedule-filter-count d-none" id="schedule_filter_count">0</span>
                            </button>
                            <button type="button" class="btn btn-light btn-sm d-none" id="schedule_list_reset">
                                <i class="fas fa-times me-1"></i>Hapus Filter
                            </button>
                            <div class="schedule-view-switch" role="group" aria-label="Pilih tampilan jadwal">
                                <button type="button" class="schedule-view-button active" data-schedule-view="table">
                                    <i class="fas fa-table me-1"></i>Tabel
                                </button>
                                <button type="button" class="schedule-view-button" data-schedule-view="card">
                                    <i class="fas fa-th-large me-1"></i>Card
                                </button>
                                <button type="button" class="schedule-view-button" data-schedule-view="calendar">
                                    <i class="fas fa-calendar-alt me-1"></i>Kalender
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="schedule-active-filters d-none" id="schedule_active_filters"></div>

                    <div id="schedule_table_view">
                        <x-attendance-table id="schedules_table" :headers="['Karyawan','Tanggal','Tipe','Shift','Catatan','Aksi']" />
                    </div>
                    <div id="schedule_card_view" class="d-none">
                        <div class="schedule-card-grid" id="schedule_card_grid"></div>
                        <div class="schedule-card-pagination">
                            <div class="text-muted fs-8" id="schedule_card_info">0 jadwal</div>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-light" id="schedule_card_prev">
                                    <i class="fas fa-chevron-left me-1"></i>Sebelumnya
                                </button>
                                <button type="button" class="btn btn-sm btn-light" id="schedule_card_next">
                                    Berikutnya<i class="fas fa-chevron-right ms-1"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div id="schedule_calendar_view" class="d-none">
                        <div class="schedule-calendar-toolbar">
                            <div>
                                <div class="fw-bold text-gray-900">Kalender Jadwal</div>
                                <div class="text-muted fs-8">Klik event untuk melihat rincian jadwal karyawan.</div>
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
                            <span><span class="dot" style="background:#7e8299"></span>Libur</span>
                            <span><span class="dot" style="background:#f1416c"></span>Libur perusahaan</span>
                            <span><span class="dot" style="background:#ffc700"></span>Cuti / izin</span>
                        </div>
                        <div id="attendance_schedule_calendar"></div>
                    </div>
                </div>
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

                <div class="att-assignment-panel" id="template_assignment_panel">
                    <div class="att-assignment-card">
                        <div class="att-assignment-title">
                            <span class="icon"><i class="fas fa-link"></i></span>
                            <div>
                                <h3>Tetapkan Template ke Karyawan</h3>
                                <p>Pilih karyawan, template, dan periode. Jadwal harian akan dibuat otomatis.</p>
                            </div>
                        </div>
                        <form class="row g-3 ajax-form" id="template_assignment_form" data-table="templates_table" action="{{ route('admin.attendance.templates.assign') }}">
                            @csrf
                            <div class="col-12">
                                <label class="form-label fw-bold">Karyawan</label>
                                <select name="employee_id" class="form-select form-select-solid" required>
                                    @foreach($employees as $employee)
                                        <option value="{{ $employee->id }}">{{ $employee->employee_code }} - {{ $employee->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold">Template Jadwal</label>
                                <select name="weekly_schedule_template_id" class="form-select form-select-solid" required id="template_assignment_template">
                                    @foreach($templates as $template)
                                        <option value="{{ $template->id }}">{{ $template->name }}{{ $template->is_active ? '' : ' (Nonaktif)' }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label fw-bold">Berlaku Dari</label>
                                <input type="text" name="effective_from" class="form-control form-control-solid js-date js-schedule-date" min="{{ today()->toDateString() }}" placeholder="YYYY-MM-DD" required>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label fw-bold">Berlaku Sampai</label>
                                <input type="text" name="effective_until" class="form-control form-control-solid js-date" placeholder="Akhir bulan otomatis">
                            </div>
                            <div class="col-12 d-grid d-md-flex justify-content-md-end gap-2 pt-2">
                                <a href="{{ route('admin.attendance.schedules.index') }}" class="btn btn-light">
                                    <i class="fas fa-calendar-alt me-1"></i>Lihat Jadwal
                                </a>
                                <button class="btn btn-success">
                                    <i class="fas fa-check me-1"></i>Tetapkan Jadwal
                                </button>
                            </div>
                        </form>

                        <div class="att-assignment-result d-none" id="template_assignment_result">
                            <div class="d-flex align-items-start gap-3">
                                <span class="badge badge-light-success mt-1">Berhasil</span>
                                <div>
                                    <div class="fw-bold text-gray-900">Jadwal sudah dibuat dan siap dicek.</div>
                                    <div class="text-muted fs-8">Gunakan tombol Lihat Jadwal untuk memeriksa hasil penetapan.</div>
                                </div>
                            </div>
                            <div class="att-result-grid">
                                <div class="att-result-item">
                                    <div class="label">Karyawan</div>
                                    <div class="value" data-result="employee">-</div>
                                </div>
                                <div class="att-result-item">
                                    <div class="label">Template</div>
                                    <div class="value" data-result="template">-</div>
                                </div>
                                <div class="att-result-item">
                                    <div class="label">Periode</div>
                                    <div class="value" data-result="period">-</div>
                                </div>
                                <div class="att-result-item">
                                    <div class="label">Jumlah Jadwal</div>
                                    <div class="value" data-result="count">-</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="att-assignment-card">
                        <div class="att-assignment-title">
                            <span class="icon"><i class="fas fa-eye"></i></span>
                            <div>
                                <h3>Preview Pola Template</h3>
                                <p>Cek pola mingguan sebelum ditempelkan ke karyawan.</p>
                            </div>
                        </div>
                        <div class="att-template-preview" id="template_assignment_preview"></div>
                    </div>
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
                        <div class="col-6 col-md-6 col-lg-2"><label class="form-label fw-bold">Tanggal Mulai</label><input type="text" name="start_date" class="form-control form-control-solid js-date" placeholder="YYYY-MM-DD" required></div>
                        <div class="col-6 col-md-6 col-lg-2"><label class="form-label fw-bold">Tanggal Selesai</label><input type="text" name="end_date" class="form-control form-control-solid js-date" placeholder="YYYY-MM-DD" required></div>
                        <div class="col-12"><label class="form-label fw-bold">Alasan</label><input name="reason" class="form-control form-control-solid" placeholder="Alasan cuti / izin"></div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Gambar Bukti</label>
                            <input type="file" name="proof_image" class="form-control form-control-solid" accept="image/jpeg,image/png,image/webp">
                            <div class="form-text">Format JPG, PNG, atau WEBP. Maksimal 2 MB.</div>
                            <div class="mt-2 leave-proof-preview" style="display:none;">
                                <a href="#" target="_blank" rel="noopener" class="btn btn-sm btn-light-primary leave-proof-link">
                                    <i class="fas fa-image me-1"></i>Lihat gambar saat ini
                                </a>
                                <label class="form-check form-check-sm form-check-custom form-check-solid mt-3">
                                    <input class="form-check-input" type="checkbox" name="remove_proof_image" value="1">
                                    <span class="form-check-label">Hapus gambar saat ini</span>
                                </label>
                            </div>
                        </div>
                        <div class="col-12 d-flex justify-content-end pt-2">
                            <button class="btn btn-primary"><i class="fas fa-plus me-1"></i>Tambah Pengajuan</button>
                        </div>
                    </form>
                </div>
                <x-attendance-table id="leaves_table" :headers="['Karyawan','Tipe','Mulai','Selesai','Status','Diproses Oleh','Alasan','Bukti','Aksi']" />
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
                            <p>Pilih baris di tabel lalu klik <em>Edit</em> untuk koreksi data rekap. Approval lembur diproses dari tombol action pada tabel.</p>
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
                                <div class="col-6 col-md-6 col-lg-3"><label class="form-label fw-bold">Status Absensi</label><select name="status" class="form-select form-select-solid"><option value="present">Hadir</option><option value="late">Terlambat</option><option value="absent">Alfa</option><option value="incomplete">Belum Check-out</option><option value="leave">Cuti/Izin</option><option value="holiday">Libur Perusahaan</option><option value="day_off">Libur</option></select></div>
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
                            <div class="attendance-form-section-title">Data Lembur Terhitung</div>
                            <div class="row g-3">
                                <div class="col-6 col-md-4 col-lg-3"><label class="form-label fw-bold">Lembur Terhitung</label><input type="number" name="calculated_overtime_minutes" min="0" value="0" class="form-control form-control-solid"></div>
                                <div class="col-12 col-md-8 col-lg-9"><label class="form-label fw-bold">Catatan Lembur / Koreksi</label><input name="overtime_note" class="form-control form-control-solid" placeholder="Catatan koreksi atau konteks lembur"></div>
                            </div>
                            <div class="d-flex justify-content-end mt-3">
                                <button class="btn btn-primary"><i class="fas fa-save me-1"></i>Update Rekap</button>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="attendance-form-section mb-4" id="attendance_recap_filters">
                    <div class="attendance-form-section-title">Filter Rekap</div>
                    <div class="row g-3 align-items-end">
                        <div class="col-12 col-md-6 col-lg-2">
                            <label class="form-label fw-bold">Dari Tanggal</label>
                            <input type="text" class="form-control form-control-solid js-date" id="recap_filter_date_from" value="{{ today()->toDateString() }}" placeholder="YYYY-MM-DD">
                        </div>
                        <div class="col-12 col-md-6 col-lg-2">
                            <label class="form-label fw-bold">Sampai Tanggal</label>
                            <input type="text" class="form-control form-control-solid js-date" id="recap_filter_date_to" value="{{ today()->toDateString() }}" placeholder="YYYY-MM-DD">
                        </div>
                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label fw-bold">Karyawan</label>
                            <select class="form-select form-select-solid" id="recap_filter_employee">
                                <option value="">Semua karyawan</option>
                                @foreach($recapEmployees as $employee)
                                    <option value="{{ $employee->id }}">
                                        {{ $employee->employee_code }} - {{ $employee->name }}{{ $employee->employment_status === \App\Models\Employee::STATUS_INACTIVE ? ' (Nonaktif)' : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-6 col-lg-2">
                            <label class="form-label fw-bold">Status Karyawan</label>
                            <select class="form-select form-select-solid" id="recap_filter_employment_status">
                                <option value="active">Aktif</option>
                                <option value="inactive">Nonaktif</option>
                                <option value="all">Semua</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-6 col-lg-2">
                            <label class="form-label fw-bold">Status Absensi</label>
                            <select class="form-select form-select-solid" id="recap_filter_status">
                                <option value="">Semua status</option>
                                <option value="present">Hadir</option>
                                <option value="late">Terlambat</option>
                                <option value="absent">Alfa</option>
                                <option value="incomplete">Belum Check-out</option>
                                <option value="leave">Cuti/Izin</option>
                                <option value="holiday">Libur Perusahaan</option>
                                <option value="day_off">Libur</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-6 col-lg-2">
                            <label class="form-label fw-bold">Status Lembur</label>
                            <select class="form-select form-select-solid" id="recap_filter_overtime_status">
                                <option value="">Semua lembur</option>
                                <option value="none">Tidak Ada</option>
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-6 col-lg-1 d-grid gap-2">
                            <button type="button" class="btn btn-light-primary" id="recap_apply_filters">
                                <i class="fas fa-filter"></i>
                            </button>
                            <button type="button" class="btn btn-light" id="recap_reset_filters">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="recap-summary-grid" id="attendance_recap_summary">
                    <div class="recap-summary-card primary"><div class="label">Total Rekap</div><div class="value" data-summary="total">0</div></div>
                    <div class="recap-summary-card success"><div class="label">Sudah Check-in</div><div class="value" data-summary="present">0</div></div>
                    <div class="recap-summary-card warning"><div class="label">Terlambat</div><div class="value" data-summary="late">0</div></div>
                    <div class="recap-summary-card info"><div class="label">Belum Check-out</div><div class="value" data-summary="incomplete">0</div></div>
                    <div class="recap-summary-card danger"><div class="label">Alfa</div><div class="value" data-summary="absent">0</div></div>
                    <div class="recap-summary-card off"><div class="label">Karyawan Libur</div><div class="value" data-summary="day_off">0</div></div>
                    <div class="recap-summary-card overtime"><div class="label">Lembur Pending</div><div class="value" data-summary="overtime_pending">0</div></div>
                </div>
                <x-attendance-table id="attendances_table" :headers="['Karyawan','Tanggal & Shift','Jam Absensi','Kedisiplinan','Durasi Kerja','Lembur','Status','Catatan','Aksi']" />
            </div>
        </div>
    </div>
</div>

<div id="attendance_form_bank" class="attendance-form-bank"></div>

{{-- ===== Modal Filter Jadwal ===== --}}
<div class="modal fade" id="schedule_filter_modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h2 class="fw-bolder mb-1"><i class="fas fa-sliders-h text-primary me-2"></i>Filter Jadwal</h2>
                    <div class="text-muted fs-7">Pilih filter yang diperlukan. Kosongkan untuk menampilkan seluruh jadwal.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-5">
                <div class="schedule-filter-modal-grid">
                    <div>
                        <label class="form-label fw-bold">Karyawan</label>
                        <select class="form-select form-select-solid" id="schedule_list_employee">
                            <option value="">Semua karyawan</option>
                            @foreach($employees as $employee)
                                <option value="{{ $employee->id }}">{{ $employee->employee_code }} - {{ $employee->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label fw-bold">Tipe Jadwal</label>
                        <select class="form-select form-select-solid" id="schedule_list_type">
                            <option value="">Semua tipe</option>
                            <option value="work">Masuk</option>
                            <option value="day_off">Libur</option>
                            <option value="holiday">Libur Perusahaan</option>
                            <option value="leave">Cuti/Izin</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label fw-bold">Dari Tanggal</label>
                        <input type="text" class="form-control form-control-solid js-date" id="schedule_list_date_from" placeholder="YYYY-MM-DD">
                    </div>
                    <div>
                        <label class="form-label fw-bold">Sampai Tanggal</label>
                        <input type="text" class="form-control form-control-solid js-date" id="schedule_list_date_to" placeholder="YYYY-MM-DD">
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light me-auto" id="schedule_filter_modal_reset">
                    <i class="fas fa-undo me-1"></i>Reset
                </button>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary" id="schedule_filter_apply">
                    <i class="fas fa-check me-1"></i>Terapkan Filter
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ===== Modal Import Karyawan ===== --}}
<div class="modal fade" id="attendance_import_employees_modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 class="fw-bolder mb-1"><i class="fas fa-file-import text-primary me-2"></i>Import Karyawan</h2>
                    <div class="text-muted fs-7">Upload Excel untuk menambahkan atau memperbarui data karyawan absensi.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info border-0 mb-5" style="background:#eff8ff;">
                    <div class="fw-bold mb-2">Format kolom Excel</div>
                    <div class="fs-7 text-muted">
                        Wajib: <code>name</code>.
                        Opsional: <code>phone</code>, <code>employment_status</code>, <code>position</code>,
                        <code>position_id</code>, <code>area</code>, <code>area_id</code>, <code>user_email</code>,
                        <code>user_id</code>, <code>create_user</code>, <code>user_password</code>, <code>user_roles</code>,
                        <code>join_date</code>, <code>employee_code</code>.
                    </div>
                    <div class="fs-8 text-muted mt-2">Kosongkan <code>employee_code</code> agar sistem membuat kode pendek otomatis. Status kerja bisa diisi <code>active</code>/<code>aktif</code> atau <code>inactive</code>/<code>nonaktif</code>.</div>
                    <div class="fs-8 text-muted mt-2">Jika ingin sekaligus membuat user login, isi <code>create_user</code> dengan <code>yes</code>, lalu wajib isi <code>user_email</code>, <code>user_password</code>, dan <code>user_roles</code>. Role bisa berupa slug/nama/id dan bisa dipisah koma.</div>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-bold">Mode Import</label>
                    <select id="employee_import_mode" class="form-select form-select-solid">
                        <option value="create_only">Tambah data baru saja</option>
                        <option value="upsert">Tambah dan update berdasarkan kode karyawan</option>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label fw-bold">File Excel</label>
                    <input type="file" id="employee_import_file" class="form-control form-control-solid" accept=".xlsx,.xls">
                    <div class="invalid-feedback d-block" id="employee_import_error"></div>
                </div>
            </div>
            <div class="modal-footer">
                <a href="{{ route('admin.attendance.employees.import-template') }}" class="btn btn-light-primary me-auto" id="employee_import_template">
                    <i class="fas fa-download me-1"></i>Download Template
                </a>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary" id="employee_import_submit">
                    <i class="fas fa-file-import me-1"></i>Import
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ===== Modal Import Shift Kerja ===== --}}
<div class="modal fade" id="attendance_import_shifts_modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 class="fw-bolder mb-1"><i class="fas fa-file-import text-primary me-2"></i>Import Shift Kerja</h2>
                    <div class="text-muted fs-7">Upload Excel untuk membuat atau memperbarui data shift kerja.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info border-0 mb-5" style="background:#eff8ff;">
                    <div class="fw-bold mb-2">Format kolom Excel</div>
                    <div class="fs-7 text-muted">
                        Gunakan format yang sama dengan export:
                        <code>name</code>, <code>start_time</code>, <code>end_time</code>,
                        <code>break_start_time</code>, <code>break_end_time</code>,
                        <code>late_tolerance_minutes</code>, <code>checkout_tolerance_minutes</code>,
                        <code>overtime_start_after_minutes</code>, <code>minimum_overtime_minutes</code>,
                        <code>crosses_midnight</code>, <code>is_active</code>.
                    </div>
                    <div class="fs-8 text-muted mt-2">Format jam memakai <code>HH:MM</code>, contoh <code>08:00</code>. Import akan update shift yang namanya sudah ada.</div>
                </div>
                <div class="mb-2">
                    <label class="form-label fw-bold">File Excel</label>
                    <input type="file" id="shift_import_file" class="form-control form-control-solid" accept=".xlsx,.xls">
                    <div class="invalid-feedback d-block" id="shift_import_error"></div>
                </div>
            </div>
            <div class="modal-footer">
                <a href="{{ route('admin.attendance.shifts.export') }}" class="btn btn-light-primary me-auto" id="shift_import_export">
                    <i class="fas fa-download me-1"></i>Download Format
                </a>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary" id="shift_import_submit">
                    <i class="fas fa-file-import me-1"></i>Import
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ===== Modal Import Template Jadwal ===== --}}
<div class="modal fade" id="attendance_import_templates_modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 class="fw-bolder mb-1"><i class="fas fa-file-import text-primary me-2"></i>Import Template Jadwal</h2>
                    <div class="text-muted fs-7">Upload Excel untuk membuat atau memperbarui pola template mingguan.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info border-0 mb-5" style="background:#eff8ff;">
                    <div class="fw-bold mb-2">Format kolom Excel</div>
                    <div class="fs-7 text-muted">
                        Gunakan format yang sama dengan export:
                        <code>template_name</code>, <code>is_active</code>, <code>day_of_week</code>,
                        <code>day_name</code>, <code>schedule_type</code>, <code>shift</code>, <code>work_shift_id</code>.
                    </div>
                    <div class="fs-8 text-muted mt-2">
                        Satu baris mewakili satu hari. <code>day_of_week</code>: 1=Senin sampai 7=Minggu.
                        <code>schedule_type</code> bisa <code>work</code>/<code>masuk</code> atau <code>day_off</code>/<code>libur</code>.
                    </div>
                    <div class="fs-8 text-muted mt-2">Untuk hari <code>work</code>, isi salah satu: <code>work_shift_id</code> atau nama shift pada kolom <code>shift</code>.</div>
                </div>
                <div class="mb-2">
                    <label class="form-label fw-bold">File Excel</label>
                    <input type="file" id="template_import_file" class="form-control form-control-solid" accept=".xlsx,.xls">
                    <div class="invalid-feedback d-block" id="template_import_error"></div>
                </div>
            </div>
            <div class="modal-footer">
                <a href="{{ route('admin.attendance.templates.export') }}" class="btn btn-light-primary me-auto" id="template_import_export">
                    <i class="fas fa-download me-1"></i>Download Format
                </a>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary" id="template_import_submit">
                    <i class="fas fa-file-import me-1"></i>Import
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ===== Modal Create/Edit ===== --}}
<div class="modal fade" id="attendance_form_modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 class="fw-bolder mb-1" id="attendance_form_modal_title">
                        <i class="{{ $activeSectionIcon }} text-primary me-2"></i>{{ $activeSectionLabel }}
                    </h2>
                    <div class="text-muted fs-7" id="attendance_form_modal_subtitle">Isi form lalu simpan. Data tabel akan dimuat ulang otomatis.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="attendance_form_modal_body"></div>
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
const attendanceToday = @json(today()->toDateString());
const searchInput = document.getElementById('attendance_search');
const calendarEventsUrl = '{{ route('admin.attendance.schedules.calendar-events') }}';
const assignTemplateUrl = '{{ route('admin.attendance.templates.assign') }}';
const employeeImportUrl = '{{ route('admin.attendance.employees.import') }}';
const employeeImportTemplateUrl = '{{ route('admin.attendance.employees.import-template') }}';
const employeeExportUrl = '{{ route('admin.attendance.employees.export') }}';
const templateImportUrl = '{{ route('admin.attendance.templates.import') }}';
const templateExportUrl = '{{ route('admin.attendance.templates.export') }}';
const shiftImportUrl = '{{ route('admin.attendance.shifts.import') }}';
const shiftExportUrl = '{{ route('admin.attendance.shifts.export') }}';
const attendanceRecapExportUrl = '{{ route('admin.attendance.attendances.export') }}';
const nextEmployeeCode = @json($nextEmployeeCode ?? 'K0001');
const weeklyTemplateOptions = @json($templateOptions ?? []);
const positionStoreUrl = '{{ route('admin.attendance.positions.store') }}';
const positionUpdateTpl = '{{ route('admin.attendance.positions.update', ':id') }}';
const positionDeleteTpl = '{{ route('admin.attendance.positions.destroy', ':id') }}';
const activeSectionKey = @json($activeSection);
const sectionLinks = @json($sectionLinks);
const crudUrls = {
    employees_table: { update: '{{ route('admin.attendance.employees.update', ':id') }}', destroy: '{{ route('admin.attendance.employees.destroy', ':id') }}' },
    devices_table: { update: '{{ route('admin.attendance.devices.update', ':id') }}', destroy: '{{ route('admin.attendance.devices.destroy', ':id') }}' },
    fingerprints_table: { update: '{{ route('admin.attendance.fingerprints.update', ':id') }}', destroy: '{{ route('admin.attendance.fingerprints.destroy', ':id') }}' },
    shifts_table: { update: '{{ route('admin.attendance.shifts.update', ':id') }}', destroy: '{{ route('admin.attendance.shifts.destroy', ':id') }}' },
    schedules_table: { update: '{{ route('admin.attendance.schedules.update', ':id') }}', destroy: '{{ route('admin.attendance.schedules.destroy', ':id') }}' },
    holidays_table: { update: '{{ route('admin.attendance.holidays.update', ':id') }}', destroy: '{{ route('admin.attendance.holidays.destroy', ':id') }}' },
    templates_table: { update: '{{ route('admin.attendance.templates.update', ':id') }}', destroy: '{{ route('admin.attendance.templates.destroy', ':id') }}' },
    leaves_table: {
        update: '{{ route('admin.attendance.leaves.update', ':id') }}',
        destroy: '{{ route('admin.attendance.leaves.destroy', ':id') }}',
        approve: '{{ route('admin.attendance.leaves.approve', ':id') }}',
        reject: '{{ route('admin.attendance.leaves.reject', ':id') }}',
    },
    raw_logs_table: { update: '{{ route('admin.attendance.raw-logs.update', ':id') }}', destroy: '{{ route('admin.attendance.raw-logs.destroy', ':id') }}' },
    attendances_table: {
        update: '{{ route('admin.attendance.attendances.update', ':id') }}',
        destroy: '{{ route('admin.attendance.attendances.destroy', ':id') }}',
        approveOvertime: '{{ route('admin.attendance.attendances.overtime.approve', ':id') }}',
        rejectOvertime: '{{ route('admin.attendance.attendances.overtime.reject', ':id') }}',
    },
};
function renderAttendanceStatusBadge(value) {
    const labels = {
        present: 'Hadir',
        late: 'Terlambat',
        absent: 'Alfa',
        leave: 'Cuti/Izin',
        holiday: 'Libur',
        day_off: 'Libur',
        incomplete: 'Belum Check-out',
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
function renderLeaveStatusBadge(value) {
    const labels = {
        pending: 'Menunggu Approval',
        approved: 'Approved',
        rejected: 'Rejected',
    };
    const classes = {
        pending: 'badge-light-warning',
        approved: 'badge-light-success',
        rejected: 'badge-light-danger',
    };

    return `<span class="badge ${classes[value] || 'badge-light'}">${labels[value] || value || '-'}</span>`;
}
function renderEmployeeStatusBadge(value) {
    const labels = {
        active: 'Aktif',
        inactive: 'Nonaktif',
    };
    const classes = {
        active: 'badge-light-success',
        inactive: 'badge-light-secondary',
    };

    return `<span class="badge ${classes[value] || 'badge-light'}">${labels[value] || value || '-'}</span>`;
}
function formatRecapDate(value) {
    if (!value) return '-';
    const date = new Date(`${value}T00:00:00`);
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleDateString('id-ID', {
        weekday: 'short',
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}
function formatRecapTime(value) {
    if (!value) return '-';
    const match = String(value).match(/(\d{2}):(\d{2})/);
    return match ? `${match[1]}:${match[2]}` : '-';
}
function formatMinutes(value) {
    const minutes = Number(value || 0);
    if (minutes <= 0) return '0 menit';
    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;
    if (!hours) return `${rest} menit`;
    return rest ? `${hours}j ${rest}m` : `${hours} jam`;
}
function renderRecapEmployee(value) {
    const parts = String(value || '-').split(' - ');
    const code = parts.shift() || '-';
    const name = parts.join(' - ') || code;
    return `<div class="recap-employee"><div class="name">${escapeAttr(name)}</div><div class="code">${escapeAttr(code)}</div></div>`;
}
function renderRecapSchedule(value, row) {
    return `<div class="fw-bold text-gray-800">${escapeAttr(formatRecapDate(row.attendance_date))}</div>
        <div class="recap-cell-meta"><i class="fas fa-clock me-1"></i>${escapeAttr(row.shift || 'Tanpa shift')}</div>`;
}
function renderRecapTimes(value, row) {
    return `<div class="recap-time-pair">
        <div class="recap-time"><div class="label">Masuk</div><div class="value">${escapeAttr(formatRecapTime(row.check_in_at))}</div></div>
        <div class="recap-time"><div class="label">Pulang</div><div class="value">${escapeAttr(formatRecapTime(row.check_out_at))}</div></div>
    </div>`;
}
function renderRecapDiscipline(value, row) {
    const late = Number(row.late_minutes || 0);
    const early = Number(row.early_leave_minutes || 0);
    if (!late && !early) return '<span class="badge badge-light-success">Tepat waktu</span>';
    return `${late ? `<span class="recap-metric text-warning"><i class="fas fa-hourglass-start"></i>Telat ${late}m</span>` : ''}
        ${early ? `<span class="recap-metric text-danger"><i class="fas fa-running"></i>Pulang cepat ${early}m</span>` : ''}`;
}
function renderRecapWorkDuration(value, row) {
    return `<div class="fw-bold text-gray-800">${escapeAttr(formatMinutes(row.work_minutes))}</div>
        <div class="recap-cell-meta">${Number(row.work_minutes || 0)} menit tercatat</div>`;
}
function renderRecapOvertime(value, row) {
    const calculated = Number(row.calculated_overtime_minutes || 0);
    const approved = row.approved_overtime_minutes;
    return `<div>${renderOvertimeStatusBadge(row.overtime_status)}</div>
        <div class="recap-cell-meta mt-1">Hitung: ${escapeAttr(formatMinutes(calculated))}</div>
        ${approved !== null && approved !== undefined ? `<div class="recap-cell-meta">Approved: ${escapeAttr(formatMinutes(approved))}</div>` : ''}`;
}
function renderRecapStatus(value, row) {
    const sourceLabels = { fingerprint: 'Mesin', manual: 'Manual', system: 'Sistem' };
    return `<div>${renderAttendanceStatusBadge(row.status)}</div>
        <div class="recap-cell-meta mt-1">Sumber: ${escapeAttr(sourceLabels[row.source] || row.source || '-')}</div>`;
}
function renderRecapNote(value, row) {
    const notes = [row.note, row.overtime_note].filter(Boolean);
    return notes.length
        ? `<div class="recap-note">${notes.map(escapeAttr).join('<br>')}</div>`
        : '<span class="text-muted">-</span>';
}
function updateRecapSummary(summary = {}) {
    document.querySelectorAll('#attendance_recap_summary [data-summary]').forEach((element) => {
        element.textContent = Number(summary[element.dataset.summary] || 0).toLocaleString('id-ID');
    });
}
const tableConfigs = {
    employees_table: { url: '{{ route('admin.attendance.employees.data') }}', columns: ['employee_code','name','area','user','phone','position',{ data: 'employment_status', render: renderEmployeeStatusBadge },'__actions'] },
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
    leaves_table: { url: '{{ route('admin.attendance.leaves.data') }}', columns: ['employee','leave_type','start_date','end_date',{ data: 'status', render: renderLeaveStatusBadge },{ data: 'approved_by', render: (value, row) => value ? `${value}<div class="text-muted fs-8">${row.approved_at || ''}</div>` : '-' },'reason',{ data: 'proof_image_url', render: (value) => value ? `<a href="${escapeAttr(value)}" target="_blank" rel="noopener" class="badge badge-light-primary">Lihat Gambar</a>` : '-' },'__actions'] },
    raw_logs_table: { url: '{{ route('admin.attendance.raw-logs.data') }}', columns: ['device','employee','device_user_id','scan_at','verify_type','state','__actions'] },
    attendances_table: { url: '{{ route('admin.attendance.attendances.data') }}', columns: [
        { data: 'employee', render: renderRecapEmployee },
        { data: 'attendance_date', render: renderRecapSchedule },
        { data: 'check_in_at', render: renderRecapTimes },
        { data: 'late_minutes', render: renderRecapDiscipline },
        { data: 'work_minutes', render: renderRecapWorkDuration },
        { data: 'overtime_status', render: renderRecapOvertime },
        { data: 'status', render: renderRecapStatus },
        { data: 'note', render: renderRecapNote },
        '__actions',
    ] },
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
const tableSectionMap = {
    employees_table: 'employees',
    devices_table: 'devices',
    fingerprints_table: 'fingerprints',
    shifts_table: 'shifts',
    schedules_table: 'schedules',
    holidays_table: 'holidays',
    templates_table: 'templates',
    leaves_table: 'leaves',
    raw_logs_table: 'raw_logs',
    attendances_table: 'attendances',
};

const escapeAttr = (value) => String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('"', '&quot;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;');

const crudUrl = (tableId, action, id) => crudUrls[tableId]?.[action]?.replace(':id', id);
const renderLeaveActions = (row, payload) => {
    const canEdit = row.status === 'pending';
    const approveButton = row.status !== 'approved'
        ? `<button type="button" class="btn btn-sm btn-light-success btn-leave-approve" data-id="${row.id}"><i class="fas fa-check me-1"></i>Approve</button>`
        : '';
    const rejectButton = row.status !== 'rejected'
        ? `<button type="button" class="btn btn-sm btn-light-warning btn-leave-reject" data-id="${row.id}"><i class="fas fa-times me-1"></i>${row.status === 'approved' ? 'Batalkan' : 'Reject'}</button>`
        : '';
    const editButton = canEdit
        ? `<button type="button" class="btn btn-sm btn-light-primary btn-crud-edit" data-table="leaves_table" data-row="${payload}"><i class="fas fa-pen me-1"></i>Edit</button>`
        : '';

    return `
        <div class="attendance-row-actions">
            ${approveButton}
            ${rejectButton}
            ${editButton}
            <button type="button" class="btn btn-sm btn-light-danger btn-crud-delete" data-table="leaves_table" data-id="${row.id}"><i class="fas fa-trash me-1"></i>Hapus</button>
        </div>
    `;
};
const renderAttendanceActions = (row, payload) => {
    const calculated = Number(row.calculated_overtime_minutes || 0);
    const overtimeStatus = row.overtime_status || 'none';
    const showApprove = calculated > 0 && overtimeStatus !== 'approved';
    const showReject = calculated > 0 && overtimeStatus !== 'rejected' && overtimeStatus !== 'none';
    const overtimeIndicator = calculated > 0
        ? '<span class="recap-overtime-alert-dot" title="Terdapat lembur"></span>'
        : '';

    return `
        <div class="text-end">
            <a href="#" class="btn btn-sm btn-light btn-active-light-primary recap-action-trigger" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                ${overtimeIndicator}
                Aksi
                <span class="svg-icon svg-icon-5 m-0">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <path d="M11.4343 12.7344L7.25 8.55005C6.83579 8.13583 6.16421 8.13584 5.75 8.55005C5.33579 8.96426 5.33579 9.63583 5.75 10.05L11.2929 15.5929C11.6834 15.9835 12.3166 15.9835 12.7071 15.5929L18.25 10.05C18.6642 9.63584 18.6642 8.96426 18.25 8.55005C17.8358 8.13584 17.1642 8.13584 16.75 8.55005L12.5657 12.7344C12.2533 13.0468 11.7467 13.0468 11.4343 12.7344Z" fill="black"></path>
                    </svg>
                </span>
            </a>
            <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-600 menu-state-bg-light-primary fw-bold fs-7 w-175px py-3" data-kt-menu="true">
                ${showApprove ? `<div class="menu-item px-3"><a href="#" class="menu-link px-3 text-success btn-overtime-approve" data-id="${row.id}" data-minutes="${calculated}" data-note="${escapeAttr(row.overtime_note || '')}">Approve Lembur</a></div>` : ''}
                ${showReject ? `<div class="menu-item px-3"><a href="#" class="menu-link px-3 text-warning btn-overtime-reject" data-id="${row.id}" data-note="${escapeAttr(row.overtime_note || '')}">Reject Lembur</a></div>` : ''}
                <div class="menu-item px-3"><a href="#" class="menu-link px-3 btn-crud-edit" data-table="attendances_table" data-row="${payload}">Edit Rekap</a></div>
                <div class="menu-item px-3"><a href="#" class="menu-link px-3 text-danger btn-crud-delete" data-table="attendances_table" data-id="${row.id}">Hapus</a></div>
            </div>
        </div>
    `;
};
const renderCrudActions = (tableId, row) => {
    if (!row?.id || !crudUrls[tableId]) return '-';
    const payload = escapeAttr(encodeURIComponent(JSON.stringify(row)));

    if (tableId === 'leaves_table') {
        return renderLeaveActions(row, payload);
    }
    if (tableId === 'attendances_table') {
        return renderAttendanceActions(row, payload);
    }
    if (tableId === 'schedules_table' && !row.is_editable) {
        return '<span class="badge badge-light-secondary">Terkunci</span>';
    }

    return `
        <div class="attendance-row-actions">
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
    const formBank = document.getElementById('attendance_form_bank');
    const formModalEl = document.getElementById('attendance_form_modal');
    const formModalBody = document.getElementById('attendance_form_modal_body');
    const formModalTitle = document.getElementById('attendance_form_modal_title');
    const formModalSubtitle = document.getElementById('attendance_form_modal_subtitle');
    const openFormButton = document.getElementById('attendance_open_form');
    const exportEmployeesButton = document.getElementById('attendance_export_employees');
    const importEmployeesButton = document.getElementById('attendance_import_employees');
    const exportTemplatesButton = document.getElementById('attendance_export_templates');
    const importTemplatesButton = document.getElementById('attendance_import_templates');
    const exportShiftsButton = document.getElementById('attendance_export_shifts');
    const importShiftsButton = document.getElementById('attendance_import_shifts');
    const importEmployeesModalEl = document.getElementById('attendance_import_employees_modal');
    const importEmployeesModal = importEmployeesModalEl && typeof bootstrap !== 'undefined'
        ? bootstrap.Modal.getOrCreateInstance(importEmployeesModalEl)
        : null;
    const importShiftsModalEl = document.getElementById('attendance_import_shifts_modal');
    const importShiftsModal = importShiftsModalEl && typeof bootstrap !== 'undefined'
        ? bootstrap.Modal.getOrCreateInstance(importShiftsModalEl)
        : null;
    const importTemplatesModalEl = document.getElementById('attendance_import_templates_modal');
    const importTemplatesModal = importTemplatesModalEl && typeof bootstrap !== 'undefined'
        ? bootstrap.Modal.getOrCreateInstance(importTemplatesModalEl)
        : null;
    const employeeImportFile = document.getElementById('employee_import_file');
    const employeeImportMode = document.getElementById('employee_import_mode');
    const employeeImportError = document.getElementById('employee_import_error');
    const employeeImportSubmit = document.getElementById('employee_import_submit');
    const templateImportFile = document.getElementById('template_import_file');
    const templateImportError = document.getElementById('template_import_error');
    const templateImportSubmit = document.getElementById('template_import_submit');
    const shiftImportFile = document.getElementById('shift_import_file');
    const shiftImportError = document.getElementById('shift_import_error');
    const shiftImportSubmit = document.getElementById('shift_import_submit');
    const recapFilterDateFrom = document.getElementById('recap_filter_date_from');
    const recapFilterDateTo = document.getElementById('recap_filter_date_to');
    const recapFilterEmployee = document.getElementById('recap_filter_employee');
    const recapFilterEmploymentStatus = document.getElementById('recap_filter_employment_status');
    const recapFilterStatus = document.getElementById('recap_filter_status');
    const recapFilterOvertimeStatus = document.getElementById('recap_filter_overtime_status');
    const exportAttendancesButton = document.getElementById('attendance_export_attendances');
    const scheduleListEmployee = document.getElementById('schedule_list_employee');
    const scheduleListDateFrom = document.getElementById('schedule_list_date_from');
    const scheduleListDateTo = document.getElementById('schedule_list_date_to');
    const scheduleListType = document.getElementById('schedule_list_type');
    const scheduleTableView = document.getElementById('schedule_table_view');
    const scheduleCardView = document.getElementById('schedule_card_view');
    const scheduleCalendarView = document.getElementById('schedule_calendar_view');
    const scheduleCardGrid = document.getElementById('schedule_card_grid');
    const scheduleCardInfo = document.getElementById('schedule_card_info');
    const scheduleCardPrev = document.getElementById('schedule_card_prev');
    const scheduleCardNext = document.getElementById('schedule_card_next');
    const scheduleListReset = document.getElementById('schedule_list_reset');
    const scheduleFilterApply = document.getElementById('schedule_filter_apply');
    const scheduleFilterModalReset = document.getElementById('schedule_filter_modal_reset');
    const scheduleFilterCount = document.getElementById('schedule_filter_count');
    const scheduleFilterSummary = document.getElementById('schedule_filter_summary');
    const scheduleActiveFilters = document.getElementById('schedule_active_filters');
    const scheduleFilterModalEl = document.getElementById('schedule_filter_modal');
    const scheduleFilterModal = scheduleFilterModalEl && typeof bootstrap !== 'undefined'
        ? bootstrap.Modal.getOrCreateInstance(scheduleFilterModalEl)
        : null;
    let appliedScheduleFilters = {
        employee_id: '',
        date_from: '',
        date_to: '',
        schedule_type: '',
    };
    const scheduleViewButtons = document.querySelectorAll('[data-schedule-view]');
    const formCardsBySection = {};
    const formModal = formModalEl && typeof bootstrap !== 'undefined'
        ? bootstrap.Modal.getOrCreateInstance(formModalEl)
        : null;
    const templateAssignmentForm = document.getElementById('template_assignment_form');
    const templateAssignmentTemplate = document.getElementById('template_assignment_template');
    const templateAssignmentPreview = document.getElementById('template_assignment_preview');
    const templateAssignmentResult = document.getElementById('template_assignment_result');
    const dayLabels = {
        1: 'Senin',
        2: 'Selasa',
        3: 'Rabu',
        4: 'Kamis',
        5: 'Jumat',
        6: 'Sabtu',
        7: 'Minggu',
    };

    document.querySelectorAll('.tab-pane').forEach((tabPane) => {
        const section = tabPane.id.replace('tab_', '');
        formCardsBySection[section] = [];
        tabPane.querySelectorAll(':scope > .att-form-card').forEach((card) => {
            if (!card.querySelector('form.ajax-form')) return;
            card.classList.add('in-modal');
            card.dataset.section = section;
            formCardsBySection[section].push(card);
            formBank?.appendChild(card);
        });
    });
    const shouldMaskAutocompleteField = (field) => {
        if (field.tagName !== 'INPUT' || field.readOnly) return false;
        return !['hidden', 'checkbox', 'radio', 'file'].includes(field.type);
    };
    const maskAutocompleteFields = (scope = document) => {
        const forms = scope.matches?.('form')
            ? [scope]
            : [...scope.querySelectorAll('#attendance_form_bank form, #attendance_form_modal form, #modal_positions form, #template_assignment_panel form')];
        forms.forEach((form, formIndex) => {
            form.setAttribute('autocomplete', 'off');
            form.setAttribute('data-form-type', 'other');
            form.querySelectorAll('input, select, textarea').forEach((field, fieldIndex) => {
                const originalName = field.dataset.originalName || field.getAttribute('name') || '';
                if (originalName) field.dataset.originalName = originalName;

                const randomToken = `no_history_${Date.now()}_${formIndex}_${fieldIndex}`;
                field.setAttribute('autocomplete', randomToken);
                field.setAttribute('autocorrect', 'off');
                field.setAttribute('autocapitalize', 'off');
                field.setAttribute('spellcheck', 'false');
                field.setAttribute('data-lpignore', 'true');
                field.setAttribute('data-1p-ignore', 'true');
                field.setAttribute('data-form-type', 'other');

                if (shouldMaskAutocompleteField(field) && originalName) {
                    field.setAttribute('name', `${randomToken}_${originalName}`);
                }
            });
        });
    };
    const restoreOriginalNames = (scope) => {
        scope.querySelectorAll('[data-original-name]').forEach((field) => {
            field.setAttribute('name', field.dataset.originalName);
        });
    };

    maskAutocompleteFields(document);

    const activeSection = () => document.querySelector('.tab-pane.active')?.id.replace('tab_', '') || activeSectionKey;
    const sectionLabel = (section) => sectionLinks?.[section]?.label || 'Absensi';
    const sectionIcon = (section) => sectionLinks?.[section]?.icon || 'fas fa-user-clock';
    const updateOpenFormButton = () => {
        if (!openFormButton) return;

        const section = activeSection();
        const cards = formCardsBySection[section] || [];
        const hasCreateForm = cards.some((card) => {
            const form = card.querySelector('form.ajax-form');
            return form && form.getAttribute('action') !== '#';
        });

        openFormButton.classList.toggle('d-none', !hasCreateForm);
        exportEmployeesButton?.classList.toggle('d-none', section !== 'employees');
        importEmployeesButton?.classList.toggle('d-none', section !== 'employees');
        exportTemplatesButton?.classList.toggle('d-none', section !== 'templates');
        importTemplatesButton?.classList.toggle('d-none', section !== 'templates');
        exportShiftsButton?.classList.toggle('d-none', section !== 'shifts');
        importShiftsButton?.classList.toggle('d-none', section !== 'shifts');
        exportAttendancesButton?.classList.toggle('d-none', section !== 'attendances');
        openFormButton.dataset.activeSection = section;
        openFormButton.querySelector('span').textContent = section === 'raw_logs'
            ? 'Input Manual Raw Log'
            : section === 'templates'
                ? 'Buat Template'
                : `Tambah ${sectionLabel(section)}`;
    };

    const moveCardsToBank = () => {
        if (!formBank || !formModalBody) return;
        formModalBody.querySelectorAll('.att-form-card.in-modal').forEach((card) => {
            formBank.appendChild(card);
        });
    };

    const openAttendanceFormModal = (section, options = {}) => {
        if (!formModalBody || !formModal) return;

        moveCardsToBank();
        formModalBody.innerHTML = '';

        const cards = [...(formCardsBySection[section] || [])];
        const editFormSelector = options.tableId === 'templates_table'
            ? '.template-days-form'
            : `form.ajax-form[data-table="${options.tableId}"]`;
        const visibleCards = options.tableId
            ? cards.filter((card) => card.querySelector(editFormSelector))
            : cards.filter((card) => {
                const form = card.querySelector('form.ajax-form');
                return form && form.getAttribute('action') !== '#';
            });

        visibleCards.forEach((card) => formModalBody.appendChild(card));

        if (!visibleCards.length) {
            formModalBody.innerHTML = '<div class="attendance-form-empty">Form hanya tersedia dari tombol Edit pada tabel.</div>';
        }

        const icon = sectionIcon(section);
        const label = sectionLabel(section);
        formModalTitle.innerHTML = `<i class="${icon} text-primary me-2"></i>${options.mode === 'edit' ? 'Edit' : 'Form'} ${label}`;
        formModalSubtitle.textContent = options.mode === 'edit'
            ? 'Perbarui data dengan teliti lalu simpan perubahan.'
            : 'Isi data baru pada form ini. Halaman list tetap bersih dan tabel akan refresh otomatis.';

        if (options.mode !== 'edit') {
            visibleCards.forEach((card) => {
                card.querySelectorAll('form.ajax-form').forEach((form) => {
                    form.reset();
                    clearEditState(form);
                    if (form.getAttribute('data-table') === 'employees_table') {
                        setFieldValue(form, 'employee_code', nextEmployeeCode);
                    }
                    if (typeof $ !== 'undefined') {
                        $(form).find('select').trigger('change.select2');
                    }
                    updateTemplateShiftState(form);
                });
            });
        }

        formModal.show();
        maskAutocompleteFields(formModalBody);
        setTimeout(() => visibleCards[0]?.querySelector('input, select, textarea')?.focus(), 250);
    };

    openFormButton?.addEventListener('click', () => {
        openAttendanceFormModal(openFormButton.dataset.activeSection || activeSection(), { mode: 'create' });
    });
    formModalEl?.addEventListener('hidden.bs.modal', () => {
        moveCardsToBank();
        formBank?.querySelectorAll('form.ajax-form').forEach((form) => clearEditState(form));
    });

    if (typeof $ !== 'undefined' && $.fn.select2) {
        document.querySelectorAll('#attendance_form_bank select, #attendance_form_modal select, #modal_positions select, #template_assignment_panel select, #attendance_recap_filters select, #schedule_filter_modal select').forEach((select) => {
            const allowClear = select.querySelector('option[value=""]') !== null;
            const parentModal = select.closest('.modal') || (select.closest('#attendance_form_bank') ? formModalEl : null);
            $(select).select2({
                width: '100%',
                allowClear,
                placeholder: select.querySelector('option[value=""]')?.textContent || 'Pilih',
                minimumResultsForSearch: 0,
                dropdownParent: parentModal ? $(parentModal) : $(document.body),
            });
        });
    }

    const currentAssignmentTemplate = () => weeklyTemplateOptions.find((template) => String(template.id) === String(templateAssignmentTemplate?.value));
    const renderTemplateAssignmentPreview = () => {
        if (!templateAssignmentPreview) return;

        const template = currentAssignmentTemplate();
        if (!template) {
            templateAssignmentPreview.innerHTML = '<div class="attendance-form-empty">Pilih template untuk melihat pola mingguan.</div>';
            return;
        }

        const days = Array.isArray(template.days) ? template.days : [];
        templateAssignmentPreview.innerHTML = Object.entries(dayLabels).map(([day, label]) => {
            const item = days.find((row) => String(row.day_of_week) === String(day));
            const isWork = item?.schedule_type === 'work';
            const meta = isWork ? (item?.shift || 'Shift belum dipilih') : 'Tidak membuat jadwal kerja';

            return `
                <div class="att-template-day ${isWork ? 'work' : ''}">
                    <div class="day">${label}</div>
                    <div class="mt-2">${isWork ? '<span class="badge badge-light-primary">Masuk</span>' : '<span class="badge badge-light-secondary">Libur</span>'}</div>
                    <div class="meta">${escapeAttr(meta)}</div>
                </div>
            `;
        }).join('');
    };

    const setAssignmentResult = (json) => {
        if (!templateAssignmentResult) return;

        const assignment = json?.assignment || {};
        const summary = json?.assignment_summary || {};
        const employee = summary.employee || (assignment.employee
            ? `${assignment.employee.employee_code} - ${assignment.employee.name}`
            : templateAssignmentForm?.querySelector('[name="employee_id"] option:checked')?.textContent?.trim() || '-');
        const template = summary.template || assignment.template?.name
            || templateAssignmentForm?.querySelector('[name="weekly_schedule_template_id"] option:checked')?.textContent?.trim()
            || '-';
        const from = summary.effective_from || assignment.effective_from || templateAssignmentForm?.querySelector('[name="effective_from"]')?.value || '-';
        const until = json?.generated_until || summary.effective_until || assignment.effective_until || templateAssignmentForm?.querySelector('[name="effective_until"]')?.value || 'akhir bulan';

        templateAssignmentResult.querySelector('[data-result="employee"]').textContent = employee;
        templateAssignmentResult.querySelector('[data-result="template"]').textContent = template;
        templateAssignmentResult.querySelector('[data-result="period"]').textContent = `${from} s/d ${until}`;
        const skipped = Number(json?.skipped_count || 0);
        templateAssignmentResult.querySelector('[data-result="count"]').textContent = skipped > 0
            ? `${json?.generated_count ?? 0} dibuat, ${skipped} dilewati`
            : `${json?.generated_count ?? 0} jadwal`;
        templateAssignmentResult.classList.remove('d-none');
    };

    templateAssignmentTemplate?.addEventListener('change', renderTemplateAssignmentPreview);
    renderTemplateAssignmentPreview();

    if (typeof flatpickr !== 'undefined') {
        const modalFlatpickrOptions = (input) => {
            return input.closest('.modal') ? {
                static: true,
            } : {};
        };

        document.querySelectorAll('.js-date').forEach((input) => {
            flatpickr(input, {
                minDate: input.classList.contains('js-schedule-date') ? attendanceToday : null,
                dateFormat: 'Y-m-d',
                allowInput: true,
                ...modalFlatpickrOptions(input),
            });
        });
        document.querySelectorAll('.js-time').forEach((input) => {
            flatpickr(input, {
                enableTime: true,
                noCalendar: true,
                dateFormat: 'H:i',
                time_24hr: true,
                allowInput: true,
                ...modalFlatpickrOptions(input),
            });
        });
        document.querySelectorAll('.js-datetime').forEach((input) => {
            flatpickr(input, {
                enableTime: true,
                dateFormat: 'Y-m-d H:i',
                time_24hr: true,
                allowInput: true,
                ...modalFlatpickrOptions(input),
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
                dataSrc: (json) => {
                    if (id === 'attendances_table') {
                        updateRecapSummary(json.summary || {});
                    }
                    return json.data || [];
                },
                data: (params) => {
                    params.q = searchInput?.value || '';
                    if (id === 'attendances_table') {
                        params.date_from = recapFilterDateFrom?.value || '';
                        params.date_to = recapFilterDateTo?.value || '';
                        params.employee_id = recapFilterEmployee?.value || '';
                        params.employment_status = recapFilterEmploymentStatus?.value || 'active';
                        params.status = recapFilterStatus?.value || '';
                        params.overtime_status = recapFilterOvertimeStatus?.value || '';
                    }
                    if (id === 'schedules_table') {
                        params.employee_id = appliedScheduleFilters.employee_id;
                        params.date_from = appliedScheduleFilters.date_from;
                        params.date_to = appliedScheduleFilters.date_to;
                        params.schedule_type = appliedScheduleFilters.schedule_type;
                    }
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

        const refreshTableMenus = () => {
            if (window.KTMenu) {
                KTMenu.createInstances();
            }
        };
        refreshTableMenus();
        tables[id].on('draw', refreshTableMenus);

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
        if (activeTabId === 'tab_schedules') {
            loadScheduleCards(true);
        }
    });
    const reloadRecapTable = () => initAttendanceTable('attendances_table')?.ajax.reload();
    document.getElementById('recap_apply_filters')?.addEventListener('click', reloadRecapTable);
    document.getElementById('recap_reset_filters')?.addEventListener('click', () => {
        [recapFilterDateFrom, recapFilterDateTo].forEach((field) => {
            if (!field) return;
            if (field._flatpickr) {
                field._flatpickr.setDate(attendanceToday, false);
            } else {
                field.value = attendanceToday;
            }
        });
        [recapFilterEmployee, recapFilterStatus, recapFilterOvertimeStatus].forEach((field) => {
            if (field) field.value = '';
        });
        if (recapFilterEmploymentStatus) recapFilterEmploymentStatus.value = 'active';
        if (typeof $ !== 'undefined') {
            $('#attendance_recap_filters select').val('').trigger('change.select2');
            $('#recap_filter_employment_status').val('active').trigger('change.select2');
        }
        reloadRecapTable();
    });
    [recapFilterDateFrom, recapFilterDateTo].forEach((field) => {
        field?.addEventListener('change', reloadRecapTable);
    });
    [recapFilterEmployee, recapFilterEmploymentStatus, recapFilterStatus, recapFilterOvertimeStatus].forEach((field) => {
        field?.addEventListener('change', reloadRecapTable);
    });

    let scheduleCardPage = 1;
    let scheduleCardTotal = 0;
    const scheduleCardPageSize = 9;
    const storedScheduleView = localStorage.getItem('attendance_schedule_view');
    let activeScheduleView = ['table', 'card', 'calendar'].includes(storedScheduleView)
        ? storedScheduleView
        : 'table';

    const scheduleTypeMeta = {
        work: { label: 'Masuk', badge: 'badge-light-primary', icon: 'fas fa-briefcase' },
        day_off: { label: 'Libur', badge: 'badge-light-secondary', icon: 'fas fa-mug-hot' },
        holiday: { label: 'Libur Perusahaan', badge: 'badge-light-danger', icon: 'fas fa-calendar-day' },
        leave: { label: 'Cuti/Izin', badge: 'badge-light-warning', icon: 'fas fa-plane-departure' },
    };
    const scheduleEmployeeParts = (value) => {
        const parts = String(value || '-').split(' - ');
        return {
            code: parts.shift() || '-',
            name: parts.join(' - ') || '-',
        };
    };
    const scheduleInitials = (name) => String(name || '-')
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('') || '-';
    const renderScheduleCard = (row) => {
        const meta = scheduleTypeMeta[row.schedule_type] || {
            label: row.schedule_type || '-',
            badge: 'badge-light',
            icon: 'fas fa-calendar',
        };
        const employee = scheduleEmployeeParts(row.employee);
        const date = new Date(`${row.schedule_date}T00:00:00`);
        const dateLabel = Number.isNaN(date.getTime())
            ? row.schedule_date
            : date.toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' });
        const dayLabel = Number.isNaN(date.getTime())
            ? '-'
            : date.toLocaleDateString('id-ID', { weekday: 'long' });
        const shiftTime = row.shift_start_time && row.shift_end_time
            ? `${row.shift_start_time} - ${row.shift_end_time}${row.crosses_midnight ? ' (+1 hari)' : ''}`
            : 'Tidak ada jam kerja';
        const payload = escapeAttr(encodeURIComponent(JSON.stringify(row)));
        const actions = row.is_editable
            ? `<div class="attendance-row-actions">
                <button type="button" class="btn btn-sm btn-light-primary btn-crud-edit" data-table="schedules_table" data-row="${payload}">
                    <i class="fas fa-pen me-1"></i>Edit
                </button>
                <button type="button" class="btn btn-sm btn-light-danger btn-crud-delete" data-table="schedules_table" data-id="${row.id}">
                    <i class="fas fa-trash me-1"></i>Hapus
                </button>
            </div>`
            : '<span class="badge badge-light-secondary"><i class="fas fa-lock me-1"></i>Terkunci</span>';

        return `<article class="schedule-card ${escapeAttr(row.schedule_type || '')}">
            <div class="schedule-card-head">
                <div>
                    <div class="schedule-card-date">${escapeAttr(dateLabel)}</div>
                    <div class="schedule-card-day">${escapeAttr(dayLabel)}</div>
                </div>
                <span class="badge ${meta.badge}"><i class="${meta.icon} me-1"></i>${escapeAttr(meta.label)}</span>
            </div>
            <div class="schedule-card-employee">
                <div class="schedule-card-avatar">${escapeAttr(scheduleInitials(employee.name))}</div>
                <div class="min-w-0">
                    <div class="name">${escapeAttr(employee.name)}</div>
                    <div class="code">${escapeAttr(employee.code)}</div>
                </div>
            </div>
            <div class="schedule-card-details">
                <div class="schedule-card-detail">
                    <div class="label">Shift</div>
                    <div class="value">${escapeAttr(row.shift || 'Tanpa shift')}</div>
                </div>
                <div class="schedule-card-detail">
                    <div class="label">Jam Kerja</div>
                    <div class="value">${escapeAttr(shiftTime)}</div>
                </div>
            </div>
            <div class="schedule-card-note"><i class="fas fa-sticky-note me-1"></i>${escapeAttr(row.note || 'Tidak ada catatan')}</div>
            <div class="schedule-card-footer">
                <span class="text-muted fs-9">${row.employee_schedule_assignment_id ? 'Dari template' : 'Jadwal manual'}</span>
                ${actions}
            </div>
        </article>`;
    };
    const loadScheduleCards = async (resetPage = false) => {
        if (!scheduleCardGrid) return;
        if (resetPage) scheduleCardPage = 1;

        scheduleCardGrid.innerHTML = '<div class="schedule-card-empty"><span class="spinner-border spinner-border-sm me-2"></span>Memuat jadwal...</div>';
        const params = new URLSearchParams({
            draw: '1',
            start: String((scheduleCardPage - 1) * scheduleCardPageSize),
            length: String(scheduleCardPageSize),
            q: searchInput?.value || '',
            employee_id: appliedScheduleFilters.employee_id,
            date_from: appliedScheduleFilters.date_from,
            date_to: appliedScheduleFilters.date_to,
            schedule_type: appliedScheduleFilters.schedule_type,
        });

        try {
            const response = await fetch(`${tableConfigs.schedules_table.url}?${params.toString()}`, {
                headers: { Accept: 'application/json' },
            });
            const json = await response.json();
            if (!response.ok) throw new Error(json?.message || 'Gagal memuat jadwal');

            const rows = Array.isArray(json.data) ? json.data : [];
            scheduleCardTotal = Number(json.recordsFiltered || 0);
            scheduleCardGrid.innerHTML = rows.length
                ? rows.map(renderScheduleCard).join('')
                : '<div class="schedule-card-empty"><i class="fas fa-calendar-times fs-2 d-block mb-3"></i><div class="fw-bold text-gray-800 mb-1">Jadwal tidak ditemukan</div><div>Ubah filter atau kata pencarian untuk melihat data lain.</div></div>';

            const from = scheduleCardTotal ? ((scheduleCardPage - 1) * scheduleCardPageSize) + 1 : 0;
            const to = Math.min(scheduleCardPage * scheduleCardPageSize, scheduleCardTotal);
            if (scheduleCardInfo) scheduleCardInfo.textContent = `Menampilkan ${from}-${to} dari ${scheduleCardTotal} jadwal`;
            if (scheduleCardPrev) scheduleCardPrev.disabled = scheduleCardPage <= 1;
            if (scheduleCardNext) scheduleCardNext.disabled = to >= scheduleCardTotal;
        } catch (error) {
            scheduleCardGrid.innerHTML = `<div class="schedule-card-empty text-danger"><i class="fas fa-exclamation-circle fs-2 d-block mb-3"></i>${escapeAttr(error.message || 'Gagal memuat jadwal')}</div>`;
        }
    };
    const reloadScheduleViews = (resetPage = false) => {
        initAttendanceTable('schedules_table')?.ajax.reload(null, false);
        loadScheduleCards(resetPage);
    };
    const clearScheduleFilterFields = () => {
        [scheduleListDateFrom, scheduleListDateTo].forEach((field) => {
            if (!field) return;
            if (field._flatpickr) {
                field._flatpickr.clear(false);
            } else {
                field.value = '';
            }
        });
        [scheduleListEmployee, scheduleListType].forEach((field) => {
            if (field) field.value = '';
        });
        if (typeof $ !== 'undefined') {
            $('#schedule_list_employee, #schedule_list_type').val('').trigger('change.select2');
        }
    };
    const updateScheduleFilterDisplay = () => {
        const filters = [];
        const employeeText = [...(scheduleListEmployee?.options || [])]
            .find((option) => option.value === appliedScheduleFilters.employee_id)
            ?.textContent?.trim() || '';
        const typeText = [...(scheduleListType?.options || [])]
            .find((option) => option.value === appliedScheduleFilters.schedule_type)
            ?.textContent?.trim() || '';

        if (employeeText) filters.push({ icon: 'fas fa-user', label: employeeText });
        if (typeText) filters.push({ icon: 'fas fa-tag', label: typeText });
        if (appliedScheduleFilters.date_from) filters.push({ icon: 'fas fa-calendar-alt', label: `Dari ${appliedScheduleFilters.date_from}` });
        if (appliedScheduleFilters.date_to) filters.push({ icon: 'fas fa-calendar-check', label: `Sampai ${appliedScheduleFilters.date_to}` });

        if (scheduleFilterCount) {
            scheduleFilterCount.textContent = filters.length;
            scheduleFilterCount.classList.toggle('d-none', filters.length === 0);
        }
        scheduleListReset?.classList.toggle('d-none', filters.length === 0);
        if (scheduleFilterSummary) {
            scheduleFilterSummary.textContent = filters.length
                ? `${filters.length} filter aktif`
                : 'Menampilkan seluruh jadwal';
        }
        if (scheduleActiveFilters) {
            scheduleActiveFilters.classList.toggle('d-none', filters.length === 0);
            scheduleActiveFilters.innerHTML = filters.map((filter) => `
                <span class="schedule-filter-chip"><i class="${filter.icon}"></i>${escapeAttr(filter.label)}</span>
            `).join('');
        }
    };
    const applyScheduleView = (view) => {
        activeScheduleView = ['table', 'card', 'calendar'].includes(view) ? view : 'table';
        localStorage.setItem('attendance_schedule_view', activeScheduleView);
        scheduleTableView?.classList.toggle('d-none', activeScheduleView !== 'table');
        scheduleCardView?.classList.toggle('d-none', activeScheduleView !== 'card');
        scheduleCalendarView?.classList.toggle('d-none', activeScheduleView !== 'calendar');
        scheduleViewButtons.forEach((button) => {
            button.classList.toggle('active', button.dataset.scheduleView === activeScheduleView);
        });
        const scheduleTabActive = document.getElementById('tab_schedules')?.classList.contains('active');
        if (!scheduleTabActive) return;

        if (activeScheduleView === 'card') {
            loadScheduleCards();
        } else if (activeScheduleView === 'calendar') {
            initScheduleCalendar();
            scheduleCalendar?.refetchEvents();
            setTimeout(() => scheduleCalendar?.updateSize(), 0);
        } else {
            initAttendanceTable('schedules_table')?.columns.adjust();
        }
    };
    scheduleViewButtons.forEach((button) => {
        button.addEventListener('click', () => applyScheduleView(button.dataset.scheduleView));
    });
    scheduleListReset?.addEventListener('click', () => {
        clearScheduleFilterFields();
        appliedScheduleFilters = { employee_id: '', date_from: '', date_to: '', schedule_type: '' };
        updateScheduleFilterDisplay();
        reloadScheduleViews(true);
    });
    scheduleFilterModalReset?.addEventListener('click', clearScheduleFilterFields);
    scheduleFilterApply?.addEventListener('click', () => {
        if (
            scheduleListDateFrom?.value
            && scheduleListDateTo?.value
            && scheduleListDateFrom.value > scheduleListDateTo.value
        ) {
            Swal?.fire('Tanggal tidak valid', 'Tanggal mulai tidak boleh setelah tanggal selesai.', 'warning');
            return;
        }

        appliedScheduleFilters = {
            employee_id: scheduleListEmployee?.value || '',
            date_from: scheduleListDateFrom?.value || '',
            date_to: scheduleListDateTo?.value || '',
            schedule_type: scheduleListType?.value || '',
        };
        updateScheduleFilterDisplay();
        reloadScheduleViews(true);
        scheduleFilterModal?.hide();
    });
    scheduleFilterModalEl?.addEventListener('show.bs.modal', () => {
        if (scheduleListEmployee) scheduleListEmployee.value = appliedScheduleFilters.employee_id;
        if (scheduleListType) scheduleListType.value = appliedScheduleFilters.schedule_type;
        if (scheduleListDateFrom?._flatpickr) {
            scheduleListDateFrom._flatpickr.setDate(appliedScheduleFilters.date_from || null, false);
        } else if (scheduleListDateFrom) {
            scheduleListDateFrom.value = appliedScheduleFilters.date_from;
        }
        if (scheduleListDateTo?._flatpickr) {
            scheduleListDateTo._flatpickr.setDate(appliedScheduleFilters.date_to || null, false);
        } else if (scheduleListDateTo) {
            scheduleListDateTo.value = appliedScheduleFilters.date_to;
        }
        if (typeof $ !== 'undefined') {
            $('#schedule_list_employee, #schedule_list_type').trigger('change.select2');
        }
    });
    updateScheduleFilterDisplay();
    scheduleCardPrev?.addEventListener('click', () => {
        if (scheduleCardPage <= 1) return;
        scheduleCardPage--;
        loadScheduleCards();
    });
    scheduleCardNext?.addEventListener('click', () => {
        if (scheduleCardPage * scheduleCardPageSize >= scheduleCardTotal) return;
        scheduleCardPage++;
        loadScheduleCards();
    });

    const resetFormsInTab = (tabId) => {
        const section = tabId.replace('tab_', '');
        const cards = formCardsBySection[section] || [];

        cards.forEach((card) => card.querySelectorAll('form.ajax-form').forEach((form) => {
            form.reset();
            clearEditState(form);
            if (typeof $ !== 'undefined') {
                $(form).find('select').trigger('change.select2');
            }
            updateTemplateShiftState(form);
        }));
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
            loadScheduleCards();
            if (activeScheduleView === 'calendar') {
                initScheduleCalendar();
                scheduleCalendar?.refetchEvents();
                scheduleCalendar?.updateSize();
            }
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

    applyScheduleView(activeScheduleView);

    document.querySelector('a[href="#tab_schedules"]')?.addEventListener('shown.bs.tab', () => {
        applyScheduleView(activeScheduleView);
    });
    document.querySelectorAll('a[data-bs-toggle="tab"]').forEach((tabLink) => {
        tabLink.addEventListener('shown.bs.tab', (event) => {
            const tabId = event.target.getAttribute('href')?.replace('#', '');
            if (tabId) {
                initTablesForTab(tabId);
                refreshAttendanceTab(tabId);
                updateOpenFormButton();
            }
        });
    });
    updateOpenFormButton();
    document.getElementById('attendance_clear_search')?.addEventListener('click', () => {
        if (searchInput) searchInput.value = '';
        const activeTabId = document.querySelector('.tab-pane.active')?.id;
        (tabTableMap[activeTabId] || []).forEach((tableId) => {
            initAttendanceTable(tableId)?.ajax.reload();
        });
        if (activeTabId === 'tab_schedules') {
            loadScheduleCards(true);
        }
    });
    importEmployeesButton?.addEventListener('click', () => {
        if (employeeImportFile) employeeImportFile.value = '';
        if (employeeImportError) employeeImportError.textContent = '';
        if (employeeImportMode) employeeImportMode.value = 'create_only';
        importEmployeesModal?.show();
    });
    exportEmployeesButton?.addEventListener('click', () => {
        const q = searchInput?.value?.trim() || '';
        window.location.href = q ? `${employeeExportUrl}?q=${encodeURIComponent(q)}` : employeeExportUrl;
    });
    importShiftsButton?.addEventListener('click', () => {
        if (shiftImportFile) shiftImportFile.value = '';
        if (shiftImportError) shiftImportError.textContent = '';
        importShiftsModal?.show();
    });
    exportShiftsButton?.addEventListener('click', () => {
        window.location.href = shiftExportUrl;
    });
    importTemplatesButton?.addEventListener('click', () => {
        if (templateImportFile) templateImportFile.value = '';
        if (templateImportError) templateImportError.textContent = '';
        importTemplatesModal?.show();
    });
    exportTemplatesButton?.addEventListener('click', () => {
        window.location.href = templateExportUrl;
    });
    exportAttendancesButton?.addEventListener('click', () => {
        const params = new URLSearchParams();
        params.set('q', searchInput?.value || '');
        params.set('date_from', recapFilterDateFrom?.value || '');
        params.set('date_to', recapFilterDateTo?.value || '');
        params.set('employee_id', recapFilterEmployee?.value || '');
        params.set('employment_status', recapFilterEmploymentStatus?.value || 'active');
        params.set('status', recapFilterStatus?.value || '');
        params.set('overtime_status', recapFilterOvertimeStatus?.value || '');
        [...params.keys()].forEach((key) => {
            if (!params.get(key)) params.delete(key);
        });
        const query = params.toString();
        window.location.href = query ? `${attendanceRecapExportUrl}?${query}` : attendanceRecapExportUrl;
    });
    employeeImportSubmit?.addEventListener('click', async () => {
        if (!employeeImportUrl) return;
        if (employeeImportError) employeeImportError.textContent = '';

        const file = employeeImportFile?.files?.[0];
        if (!file) {
            if (employeeImportError) employeeImportError.textContent = 'Pilih file Excel terlebih dahulu.';
            return;
        }

        const formData = new FormData();
        formData.append('file', file);
        formData.append('mode', employeeImportMode?.value || 'create_only');

        employeeImportSubmit.disabled = true;
        employeeImportSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Mengimport...';

        try {
            const response = await fetch(employeeImportUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: formData,
            });
            const json = await response.json().catch(() => ({}));
            if (!response.ok) {
                const message = json?.errors?.file?.[0] || json?.message || 'Gagal import karyawan';
                if (employeeImportError) employeeImportError.textContent = message;
                Swal?.fire('Error', message, 'error');
                return;
            }

            const detail = `Created: ${json.created ?? 0}, Updated: ${json.updated ?? 0}, User dibuat: ${json.users_created ?? 0}`;
            Swal?.fire('Berhasil', `${json.message || 'Import karyawan berhasil'} (${detail})`, 'success');
            if (employeeImportFile) employeeImportFile.value = '';
            importEmployeesModal?.hide();
            initAttendanceTable('employees_table')?.ajax.reload(null, false);
        } catch (error) {
            if (employeeImportError) employeeImportError.textContent = 'Gagal import karyawan';
            Swal?.fire('Error', 'Gagal import karyawan', 'error');
        } finally {
            employeeImportSubmit.disabled = false;
            employeeImportSubmit.innerHTML = '<i class="fas fa-file-import me-1"></i>Import';
        }
    });
    shiftImportSubmit?.addEventListener('click', async () => {
        if (!shiftImportUrl) return;
        if (shiftImportError) shiftImportError.textContent = '';

        const file = shiftImportFile?.files?.[0];
        if (!file) {
            if (shiftImportError) shiftImportError.textContent = 'Pilih file Excel terlebih dahulu.';
            return;
        }

        const formData = new FormData();
        formData.append('file', file);

        shiftImportSubmit.disabled = true;
        shiftImportSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Mengimport...';

        try {
            const response = await fetch(shiftImportUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: formData,
            });
            const json = await response.json().catch(() => ({}));
            if (!response.ok) {
                const message = json?.errors?.file?.[0] || json?.message || 'Gagal import shift';
                if (shiftImportError) shiftImportError.textContent = message;
                Swal?.fire('Error', message, 'error');
                return;
            }

            const detail = `Created: ${json.created ?? 0}, Updated: ${json.updated ?? 0}`;
            Swal?.fire('Berhasil', `${json.message || 'Import shift berhasil'} (${detail})`, 'success');
            if (shiftImportFile) shiftImportFile.value = '';
            importShiftsModal?.hide();
            initAttendanceTable('shifts_table')?.ajax.reload(null, false);
        } catch (error) {
            if (shiftImportError) shiftImportError.textContent = 'Gagal import shift';
            Swal?.fire('Error', 'Gagal import shift', 'error');
        } finally {
            shiftImportSubmit.disabled = false;
            shiftImportSubmit.innerHTML = '<i class="fas fa-file-import me-1"></i>Import';
        }
    });
    templateImportSubmit?.addEventListener('click', async () => {
        if (!templateImportUrl) return;
        if (templateImportError) templateImportError.textContent = '';

        const file = templateImportFile?.files?.[0];
        if (!file) {
            if (templateImportError) templateImportError.textContent = 'Pilih file Excel terlebih dahulu.';
            return;
        }

        const formData = new FormData();
        formData.append('file', file);

        templateImportSubmit.disabled = true;
        templateImportSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Mengimport...';

        try {
            const response = await fetch(templateImportUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: formData,
            });
            const json = await response.json().catch(() => ({}));
            if (!response.ok) {
                const message = json?.errors?.file?.[0] || json?.message || 'Gagal import template jadwal';
                if (templateImportError) templateImportError.textContent = message;
                Swal?.fire('Error', message, 'error');
                return;
            }

            const detail = `Created: ${json.created ?? 0}, Updated: ${json.updated ?? 0}`;
            Swal?.fire('Berhasil', `${json.message || 'Import template jadwal berhasil'} (${detail})`, 'success');
            if (templateImportFile) templateImportFile.value = '';
            importTemplatesModal?.hide();
            initAttendanceTable('templates_table')?.ajax.reload(null, false);
        } catch (error) {
            if (templateImportError) templateImportError.textContent = 'Gagal import template jadwal';
            Swal?.fire('Error', 'Gagal import template jadwal', 'error');
        } finally {
            templateImportSubmit.disabled = false;
            templateImportSubmit.innerHTML = '<i class="fas fa-file-import me-1"></i>Import';
        }
    });
    document.getElementById('modal_positions')?.addEventListener('shown.bs.modal', () => {
        initAttendanceTable('positions_table')?.ajax.reload(null, false);
    });
    if (document.getElementById('tab_schedules')?.classList.contains('show') && activeScheduleView === 'calendar') {
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
        const field = form.querySelector(`[data-original-name="${name}"][type="checkbox"]`)
            || form.querySelector(`[name="${name}"][type="checkbox"]`)
            || form.querySelector(`[data-original-name="${name}"]`)
            || form.querySelector(`[name="${name}"]`);
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
    const resetLeaveProofPreview = (form) => {
        if (form?.getAttribute('data-table') !== 'leaves_table') return;
        const preview = form.querySelector('.leave-proof-preview');
        const link = form.querySelector('.leave-proof-link');
        const remove = form.querySelector('[name="remove_proof_image"]');
        const file = form.querySelector('[name="proof_image"]');
        if (preview) preview.style.display = 'none';
        if (link) link.href = '#';
        if (remove) remove.checked = false;
        if (file) file.value = '';
    };
    const setLeaveProofPreview = (form, row) => {
        resetLeaveProofPreview(form);
        const url = row?.proof_image_url || '';
        if (!url) return;
        const preview = form.querySelector('.leave-proof-preview');
        const link = form.querySelector('.leave-proof-link');
        if (link) link.href = url;
        if (preview) preview.style.display = '';
    };
    const clearEditState = (form) => {
        delete form.dataset.editUrl;
        delete form.dataset.editingId;
        const submit = form.querySelector('button[type="submit"], button:not([type])');
        if (submit && submit.dataset.createText) {
            submit.innerHTML = submit.dataset.createText;
        }
        resetLeaveProofPreview(form);
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
        const section = tableSectionMap[tableId] || activeSection();
        openAttendanceFormModal(section, { mode: 'edit', tableId });
        const form = formForTable(tableId);
        if (!form || !crudUrls[tableId]?.update) return;

        form.reset();
        if (tableId === 'templates_table') {
            fillTemplateForm(form, row);
        } else {
            Object.entries(row).forEach(([key, value]) => setFieldValue(form, key, value));
        }
        if (tableId === 'leaves_table') {
            setLeaveProofPreview(form, row);
        }
        setEditState(form, tableId, row);
        setTimeout(() => form.querySelector('input, select, textarea')?.focus(), 250);
    };

    const finishSuccessfulFormSubmit = (form, json) => {
        if (json?.notification?.late_check_in) {
            Swal?.fire('Absensi Terlambat', json.notification.message || 'Karyawan terlambat.', 'warning');
        } else {
            Swal?.fire('Berhasil', json?.message || 'Data tersimpan', 'success');
        }
        if (form.action === assignTemplateUrl) {
            setAssignmentResult(json);
        }
        form.reset();
        resetLeaveProofPreview(form);
        clearEditState(form);
        $(form).find('select').trigger('change.select2');
        updateTemplateShiftState(form);
        if (form.id === 'template_assignment_form') {
            renderTemplateAssignmentPreview();
        }
        const tableId = form.getAttribute('data-table');
        tables[tableId]?.ajax.reload();
        if (tableId === 'schedules_table') {
            loadScheduleCards(true);
        }
        if (form.action === assignTemplateUrl) {
            tables.schedules_table?.ajax.reload();
            loadScheduleCards(true);
        }
        scheduleCalendar?.refetchEvents();
        formModal?.hide();
    };

    const confirmTemplateAssignmentConflict = async (form, conflictJson, isEditing) => {
        const conflicts = conflictJson?.conflicts || {};
        const dateSamples = Array.isArray(conflicts.date_samples) && conflicts.date_samples.length
            ? `<div class="mt-2 fs-8 text-muted">Contoh tanggal: ${conflicts.date_samples.join(', ')}</div>`
            : '';
        const html = `
            <div class="text-start">
                <div>Periode ini sudah memiliki <strong>${conflicts.schedule_count || 0}</strong> jadwal dan <strong>${conflicts.assignment_count || 0}</strong> assignment template yang overlap.</div>
                ${dateSamples}
                <div class="mt-3">Pilih cara sistem menerapkan template baru.</div>
            </div>
        `;
        const decision = typeof Swal !== 'undefined'
            ? await Swal.fire({
                title: 'Jadwal sudah ada',
                html,
                icon: 'warning',
                showDenyButton: true,
                showCancelButton: true,
                confirmButtonText: 'Timpa jadwal lama',
                denyButtonText: 'Lewati yang sudah ada',
                cancelButtonText: 'Batalkan',
                reverseButtons: true,
                width: 680,
            })
            : { isDismissed: true };

        const strategy = decision.isConfirmed
            ? 'overwrite'
            : decision.isDenied
                ? 'skip_existing'
                : null;
        if (!strategy) return;

        restoreOriginalNames(form);
        const retryFormData = new FormData(form);
        retryFormData.append('conflict_strategy', strategy);
        if (isEditing) {
            retryFormData.append('_method', 'PUT');
        }
        maskAutocompleteFields(form);

        try {
            const response = await fetch(isEditing ? form.dataset.editUrl : form.action, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: retryFormData,
            });
            const json = await response.json().catch(() => ({}));
            if (!response.ok) {
                const firstError = json?.errors ? Object.values(json.errors)[0]?.[0] : null;
                Swal?.fire('Error', firstError || json?.message || 'Gagal menyimpan data', 'error');
                return;
            }
            finishSuccessfulFormSubmit(form, json);
        } catch (error) {
            Swal?.fire('Error', 'Gagal mengirim request', 'error');
        }
    };

    document.querySelectorAll('.ajax-form').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            restoreOriginalNames(form);
            const formData = new FormData(form);
            maskAutocompleteFields(form);
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
                    if (response.status === 409 && form.action === assignTemplateUrl && json?.requires_decision) {
                        await confirmTemplateAssignmentConflict(form, json, isEditing);
                        return;
                    }
                    const firstError = json?.errors ? Object.values(json.errors)[0]?.[0] : null;
                    Swal?.fire('Error', firstError || json?.message || 'Gagal menyimpan data', 'error');
                    return;
                }
                finishSuccessfulFormSubmit(form, json);
            } catch (error) {
                Swal?.fire('Error', 'Gagal mengirim request', 'error');
            }
        });
    });

    const postAction = async (url, data = {}) => {
        const formData = new FormData();
        Object.entries(data).forEach(([key, value]) => {
            if (value !== undefined && value !== null) {
                formData.append(key, value);
            }
        });

        const response = await fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: formData,
        });
        const json = await response.json().catch(() => ({}));
        if (!response.ok) {
            const firstError = json?.errors ? Object.values(json.errors)[0]?.[0] : null;
            throw new Error(firstError || json?.message || 'Request gagal');
        }
        return json;
    };

    document.addEventListener('click', async (event) => {
        const editButton = event.target.closest('.btn-crud-edit');
        if (editButton) {
            event.preventDefault();
            const row = JSON.parse(decodeURIComponent(editButton.dataset.row || '{}'));
            fillCrudForm(editButton.dataset.table, row);
            return;
        }

        const leaveApproveButton = event.target.closest('.btn-leave-approve');
        if (leaveApproveButton) {
            const confirmation = typeof Swal !== 'undefined'
                ? await Swal.fire({
                    title: 'Approve cuti/izin?',
                    text: 'Rekap absensi pada rentang tanggal cuti akan diperbarui menjadi Cuti/Izin.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Approve',
                    cancelButtonText: 'Batal',
                })
                : { isConfirmed: confirm('Approve cuti/izin ini?') };
            if (!confirmation.isConfirmed) return;

            try {
                const json = await postAction(crudUrl('leaves_table', 'approve', leaveApproveButton.dataset.id));
                Swal?.fire('Berhasil', json?.message || 'Cuti/izin berhasil di-approve.', 'success');
                tables.leaves_table?.ajax.reload(null, false);
                tables.attendances_table?.ajax.reload(null, false);
                scheduleCalendar?.refetchEvents();
            } catch (error) {
                Swal?.fire('Error', error.message || 'Gagal approve cuti/izin', 'error');
            }
            return;
        }

        const leaveRejectButton = event.target.closest('.btn-leave-reject');
        if (leaveRejectButton) {
            const confirmation = typeof Swal !== 'undefined'
                ? await Swal.fire({
                    title: leaveRejectButton.textContent.includes('Batalkan') ? 'Batalkan approval cuti/izin?' : 'Reject cuti/izin?',
                    text: 'Rekap absensi pada rentang tanggal cuti akan dihitung ulang dari jadwal dan scan fingerprint.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: leaveRejectButton.textContent.includes('Batalkan') ? 'Batalkan Approval' : 'Reject',
                    cancelButtonText: 'Batal',
                })
                : { isConfirmed: confirm('Reject cuti/izin ini?') };
            if (!confirmation.isConfirmed) return;

            try {
                const json = await postAction(crudUrl('leaves_table', 'reject', leaveRejectButton.dataset.id));
                Swal?.fire('Berhasil', json?.message || 'Cuti/izin berhasil di-reject.', 'success');
                tables.leaves_table?.ajax.reload(null, false);
                tables.attendances_table?.ajax.reload(null, false);
                scheduleCalendar?.refetchEvents();
            } catch (error) {
                Swal?.fire('Error', error.message || 'Gagal reject cuti/izin', 'error');
            }
            return;
        }

        const overtimeApproveButton = event.target.closest('.btn-overtime-approve');
        if (overtimeApproveButton) {
            event.preventDefault();
            const minutes = Number(overtimeApproveButton.dataset.minutes || 0);
            const result = typeof Swal !== 'undefined'
                ? await Swal.fire({
                    title: 'Approve lembur?',
                    html: '<div class="text-start"><label class="form-label fw-bold">Menit disetujui</label><input id="swal_overtime_minutes" type="number" min="1" class="swal2-input" value="'+minutes+'"><label class="form-label fw-bold mt-3">Catatan</label><input id="swal_overtime_note" class="swal2-input" value="'+escapeAttr(overtimeApproveButton.dataset.note || '')+'"></div>',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Approve',
                    cancelButtonText: 'Batal',
                    preConfirm: () => {
                        const approved = parseInt(document.getElementById('swal_overtime_minutes')?.value || '0', 10);
                        if (!approved || approved <= 0) {
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
                const json = await postAction(crudUrl('attendances_table', 'approveOvertime', overtimeApproveButton.dataset.id), result.value);
                Swal?.fire('Berhasil', json?.message || 'Lembur berhasil di-approve.', 'success');
                tables.attendances_table?.ajax.reload(null, false);
            } catch (error) {
                Swal?.fire('Error', error.message || 'Gagal approve lembur', 'error');
            }
            return;
        }

        const overtimeRejectButton = event.target.closest('.btn-overtime-reject');
        if (overtimeRejectButton) {
            event.preventDefault();
            const result = typeof Swal !== 'undefined'
                ? await Swal.fire({
                    title: 'Reject lembur?',
                    input: 'text',
                    inputValue: overtimeRejectButton.dataset.note || '',
                    inputPlaceholder: 'Catatan reject lembur',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Reject',
                    cancelButtonText: 'Batal',
                })
                : { isConfirmed: confirm('Reject lembur ini?'), value: '' };
            if (!result.isConfirmed) return;

            try {
                const json = await postAction(crudUrl('attendances_table', 'rejectOvertime', overtimeRejectButton.dataset.id), {
                    overtime_note: result.value || '',
                });
                Swal?.fire('Berhasil', json?.message || 'Lembur berhasil di-reject.', 'success');
                tables.attendances_table?.ajax.reload(null, false);
            } catch (error) {
                Swal?.fire('Error', error.message || 'Gagal reject lembur', 'error');
            }
            return;
        }

        const deleteButton = event.target.closest('.btn-crud-delete');
        if (!deleteButton) return;
        event.preventDefault();

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
        if (tableId === 'schedules_table') {
            loadScheduleCards();
        }
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
        restoreOriginalNames(positionForm);
        const formData = new FormData(positionForm);
        maskAutocompleteFields(positionForm);
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
