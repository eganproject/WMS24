@extends('layouts.admin')

@section('title', 'Dashboard')

@push('styles')
<style>
    /* ── Dashboard variables ───────────────────────────────────────────── */
    :root {
        --dash-radius: 16px;
        --dash-shadow: 0 2px 12px rgba(15,23,42,.06);
        --dash-shadow-hover: 0 8px 24px rgba(15,23,42,.10);
        --dash-border: #e9ecef;
    }

    /* ── KPI cards ─────────────────────────────────────────────────────── */
    .kpi-card {
        border: 1px solid var(--dash-border);
        border-radius: var(--dash-radius);
        padding: 22px 24px;
        background: #fff;
        box-shadow: var(--dash-shadow);
        transition: box-shadow .2s ease, transform .2s ease;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .kpi-card:hover {
        box-shadow: var(--dash-shadow-hover);
        transform: translateY(-2px);
    }
    .kpi-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        margin-bottom: 12px;
    }
    .kpi-label {
        font-size: 11.5px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: #6b7280;
    }
    .kpi-value {
        font-size: 32px;
        font-weight: 800;
        line-height: 1.1;
        margin: 4px 0 2px;
        letter-spacing: -.02em;
    }
    .kpi-meta {
        font-size: 11.5px;
        color: #9ca3af;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    /* ── Section heading ────────────────────────────────────────────────── */
    .dash-section-title {
        font-size: 15px;
        font-weight: 700;
        color: #111827;
    }
    .dash-section-sub {
        font-size: 12.5px;
        color: #9ca3af;
    }

    /* ── Filter strip ───────────────────────────────────────────────────── */
    .filter-strip {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }
    .date-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 12px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 700;
        background: #eff6ff;
        color: #2563eb;
        border: 1px solid #bfdbfe;
    }

    /* ── Kurir grid ─────────────────────────────────────────────────────── */
    .kurir-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 16px;
    }
    @media (max-width: 991px) { .kurir-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 640px)  { .kurir-grid { grid-template-columns: 1fr; } }

    /* ── Kurir card ─────────────────────────────────────────────────────── */
    .kurir-card {
        border: 1px solid var(--dash-border);
        border-radius: var(--dash-radius);
        padding: 18px 20px;
        background: #fff;
        box-shadow: var(--dash-shadow);
        transition: box-shadow .2s ease, transform .2s ease;
        display: flex;
        flex-direction: column;
        gap: 0;
    }
    .kurir-card:hover { box-shadow: var(--dash-shadow-hover); transform: translateY(-2px); }

    .kurir-card-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        margin-bottom: 14px;
    }
    .kurir-name {
        font-size: 15px;
        font-weight: 700;
        color: #111827;
    }
    .kurir-updated {
        font-size: 11px;
        color: #9ca3af;
        white-space: nowrap;
        display: flex;
        align-items: center;
        gap: 3px;
    }

    /* row of stats inside courier card */
    .kurir-stats {
        display: flex;
        gap: 6px;
        margin-bottom: 14px;
        flex-wrap: wrap;
    }
    .kurir-stat-chip {
        flex: 1 1 0;
        min-width: 68px;
        border-radius: 10px;
        padding: 8px 10px;
        text-align: center;
    }
    .kurir-stat-chip .chip-val {
        font-size: 19px;
        font-weight: 800;
        line-height: 1.15;
    }
    .kurir-stat-chip .chip-lbl {
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .04em;
        margin-top: 2px;
    }

    /* color tokens */
    .chip-blue  { background: #eff6ff; } .chip-blue  .chip-val { color: #1d4ed8; } .chip-blue  .chip-lbl { color: #3b82f6; }
    .chip-green { background: #f0fdf4; } .chip-green .chip-val { color: #15803d; } .chip-green .chip-lbl { color: #22c55e; }
    .chip-amber { background: #fffbeb; } .chip-amber .chip-val { color: #b45309; } .chip-amber .chip-lbl { color: #f59e0b; }
    .chip-red   { background: #fef2f2; } .chip-red   .chip-val { color: #b91c1c; } .chip-red   .chip-lbl { color: #ef4444; }

    /* progress bar */
    .kurir-progress-wrap { margin-bottom: 14px; }
    .kurir-progress-label {
        display: flex;
        justify-content: space-between;
        font-size: 11px;
        font-weight: 600;
        color: #6b7280;
        margin-bottom: 5px;
    }
    .kurir-progress-track {
        height: 6px;
        border-radius: 999px;
        background: #f3f4f6;
        overflow: hidden;
    }
    .kurir-progress-fill {
        height: 100%;
        border-radius: 999px;
        background: linear-gradient(90deg, #3b82f6, #06b6d4);
        transition: width .4s ease;
    }
    .kurir-progress-fill.is-complete { background: linear-gradient(90deg, #10b981, #34d399); }

    /* ── Modal summary chips ────────────────────────────────────────────── */
    .modal-kpi-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 10px;
        margin-bottom: 20px;
    }
    @media (max-width: 575px) { .modal-kpi-grid { grid-template-columns: repeat(2, 1fr); } }

    .modal-kpi-chip {
        border: 1px solid transparent;
        border-radius: 12px;
        padding: 12px 14px;
        display: flex;
        flex-direction: column;
        gap: 2px;
        text-align: left;
        transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease;
        width: 100%;
    }
    button.modal-kpi-chip {
        cursor: pointer;
    }
    button.modal-kpi-chip:hover {
        box-shadow: 0 10px 22px rgba(15, 23, 42, .09);
        transform: translateY(-1px);
    }
    .modal-kpi-chip.is-active {
        border-color: currentColor;
        box-shadow: 0 10px 22px rgba(15, 23, 42, .12);
    }
    .modal-kpi-chip .chip-label {
        font-size: 10.5px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .05em;
    }
    .modal-kpi-chip .chip-value {
        font-size: 22px;
        font-weight: 800;
        line-height: 1.2;
    }

    /* ── Empty state ────────────────────────────────────────────────────── */
    .dash-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 52px 24px;
        color: #9ca3af;
        gap: 10px;
    }
    .dash-empty i { font-size: 36px; opacity: .4; }
    .dash-empty p { font-size: 14px; font-weight: 500; margin: 0; }

    /* ── Utility ────────────────────────────────────────────────────────── */
    .kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
        gap: 16px;
    }
    .dashboard-action-panel {
        border: 1px solid #fcd34d;
        border-radius: 12px;
        background: #fffbeb;
        padding: 14px 16px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
    }
    .dashboard-action-title {
        font-size: 13px;
        font-weight: 800;
        color: #92400e;
    }
    .dashboard-action-sub {
        font-size: 12px;
        color: #b45309;
    }
    .dashboard-tabs {
        border-bottom: 1px solid var(--dash-border);
        gap: 8px;
    }
    .dashboard-tabs .nav-link {
        border: 0;
        border-bottom: 3px solid transparent;
        border-radius: 0;
        color: #6b7280;
        font-weight: 700;
        padding: 10px 4px;
        margin-right: 18px;
    }
    .dashboard-tabs .nav-link.active {
        color: #1d4ed8;
        border-bottom-color: #3b82f6;
        background: transparent;
    }
    .dash-mini-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 14px;
    }
    .dash-mini-card {
        border: 1px solid var(--dash-border);
        border-radius: 12px;
        padding: 16px;
        background: #fff;
        box-shadow: var(--dash-shadow);
    }
    .dash-mini-label {
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .05em;
        color: #6b7280;
    }
    .dash-mini-value {
        font-size: 28px;
        font-weight: 800;
        line-height: 1.15;
    }
    .approval-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        padding: 14px 0;
        border-bottom: 1px dashed #e5e7eb;
    }
    .approval-row:last-child { border-bottom: 0; }
    .warehouse-progress {
        height: 7px;
        border-radius: 999px;
        overflow: hidden;
        background: #f3f4f6;
    }
    .warehouse-progress > span {
        display: block;
        height: 100%;
        border-radius: inherit;
        background: linear-gradient(90deg, #ef4444, #f59e0b);
    }
</style>
@endpush

@section('content')
@php
    $isToday = ($today ?? '') === ($currentDate ?? '');
    $totalActive = ($totalResi ?? 0);
    $totalCanceledVal = ($totalCanceled ?? 0);
    $totalScanVal = ($totalScanOut ?? 0);
    $overallPct = $totalActive > 0 ? min(100, round($totalScanVal / $totalActive * 100)) : 0;
    $scanDiff = (int) ($scanOutDifference ?? (($totalScanOut ?? 0) - ($totalResi ?? 0)));
    $duplicateResiGroupCountVal = (int) ($duplicateResiGroupCount ?? 0);
    $duplicateResiTotalVal = (int) ($duplicateResiTotal ?? 0);
    $emptyStockTotal = (int) ($emptyStockSummary['total_empty'] ?? 0);
    $emptyWarehouseTotal = (int) ($emptyStockSummary['warehouse_total'] ?? 0);
    $pendingApprovalTotal = (int) ($pendingApprovalSummary['total'] ?? 0);
@endphp

{{-- ──────────────────────────────────────────────────────────────────── --}}
{{--  Page header + filter                                                  --}}
{{-- ──────────────────────────────────────────────────────────────────── --}}
<div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-6">
    <div>
        <h2 class="fw-bolder fs-3 mb-1">Dashboard</h2>
        <div class="text-muted fs-7">
            <i class="fas fa-calendar-alt me-1"></i>
            Menampilkan data tanggal <strong>{{ $today ?? '-' }}</strong>
        </div>
    </div>

    <div class="filter-strip">
        <span class="date-badge">
            <i class="fas fa-circle fs-9"></i>
            {{ $isToday ? 'Hari Ini' : 'Tanggal Dipilih' }}
        </span>
        <input type="text"
               class="form-control form-control-solid form-control-sm"
               id="filter_date"
               placeholder="Pilih tanggal"
               value="{{ $today ?? '' }}"
               style="width: 140px;" />
        <button type="button" class="btn btn-primary btn-sm" id="filter_date_apply">
            <i class="fas fa-filter me-1"></i>Filter
        </button>
        <button type="button" class="btn btn-light btn-sm" id="filter_date_reset">
            <i class="fas fa-undo me-1"></i>Reset
        </button>
    </div>
</div>

<ul class="nav nav-tabs dashboard-tabs mb-6" id="dashboard_tabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="tab-operational-tab" data-bs-toggle="tab" data-bs-target="#tab_operational" type="button" role="tab" aria-controls="tab_operational" aria-selected="true">
            <i class="fas fa-chart-line me-2"></i>Operasional
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-empty-stock-tab" data-bs-toggle="tab" data-bs-target="#tab_empty_stock" type="button" role="tab" aria-controls="tab_empty_stock" aria-selected="false">
            <i class="fas fa-box-open me-2"></i>Stock Kosong
            @if($emptyStockTotal > 0)
                <span class="badge badge-light-danger ms-2">{{ number_format($emptyStockTotal) }}</span>
            @endif
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-pending-approval-tab" data-bs-toggle="tab" data-bs-target="#tab_pending_approval" type="button" role="tab" aria-controls="tab_pending_approval" aria-selected="false">
            <i class="fas fa-user-clock me-2"></i>Pending Approval
            @if($pendingApprovalTotal > 0)
                <span class="badge badge-light-warning ms-2">{{ number_format($pendingApprovalTotal) }}</span>
            @endif
        </button>
    </li>
</ul>

<div class="tab-content" id="dashboard_tab_content">
<div class="tab-pane fade show active" id="tab_operational" role="tabpanel" aria-labelledby="tab-operational-tab">

{{-- ──────────────────────────────────────────────────────────────────── --}}
{{--  KPI summary cards                                                     --}}
{{-- ──────────────────────────────────────────────────────────────────── --}}
<div class="kpi-grid mb-6">
    {{-- Total Resi Aktif --}}
    <div class="kpi-card">
        <div class="kpi-icon" style="background:#eff6ff;">
            <i class="fas fa-box" style="color:#3b82f6;"></i>
        </div>
        <div class="kpi-label">Total Resi (Aktif)</div>
        <div class="kpi-value" style="color:#1d4ed8;">{{ number_format($totalResi ?? 0) }}</div>
        <div class="kpi-meta">
            <i class="fas fa-clock" style="font-size:10px;"></i>
            Update {{ $totalResiUpdated ?? '-' }}
        </div>
    </div>

    {{-- Scan Out Selesai --}}
    <div class="kpi-card">
        <div class="kpi-icon" style="background:#f0fdf4;">
            <i class="fas fa-check-circle" style="color:#22c55e;"></i>
        </div>
        <div class="kpi-label">Scan Out Selesai</div>
        <div class="kpi-value" style="color:#15803d;">{{ number_format($totalScanOut ?? 0) }}</div>
        <div class="kpi-meta">
            <i class="fas fa-clock" style="font-size:10px;"></i>
            Update {{ $totalScanUpdated ?? '-' }}
        </div>
    </div>

    {{-- QC Scan Selesai --}}
    <div class="kpi-card">
        <div class="kpi-icon" style="background:#eef2ff;">
            <i class="fas fa-clipboard-check" style="color:#6366f1;"></i>
        </div>
        <div class="kpi-label">QC Scan Selesai</div>
        <div class="kpi-value" style="color:#4f46e5;">{{ number_format($totalQcScan ?? 0) }}</div>
        <div class="kpi-meta">
            <i class="fas fa-clock" style="font-size:10px;"></i>
            Update {{ $totalQcUpdated ?? '-' }}
        </div>
    </div>

    {{-- Sisa / Belum Scan --}}
    <div class="kpi-card">
        <div class="kpi-icon" style="background:#fffbeb;">
            <i class="fas fa-hourglass-half" style="color:#f59e0b;"></i>
        </div>
        <div class="kpi-label">Sisa Belum Scan</div>
        @php $remaining = max(0, ($totalResi ?? 0) - ($totalScanOut ?? 0)); @endphp
        <div class="kpi-value" style="color:#b45309;">{{ number_format($remaining) }}</div>
        <div class="kpi-meta">
            <i class="fas fa-percent" style="font-size:10px;"></i>
            {{ $overallPct }}% selesai
        </div>
    </div>

    {{-- Total Canceled --}}
    <div class="kpi-card">
        <div class="kpi-icon" style="background:#fef2f2;">
            <i class="fas fa-times-circle" style="color:#ef4444;"></i>
        </div>
        <div class="kpi-label">Total Canceled</div>
        <div class="kpi-value text-danger">{{ number_format($totalCanceled ?? 0) }}</div>
        <div class="kpi-meta">
            <i class="fas fa-clock" style="font-size:10px;"></i>
            Update {{ $totalCanceledUpdated ?? '-' }}
        </div>
    </div>
</div>

{{-- Overall progress bar --}}
<div class="card mb-6">
    <div class="card-body py-4">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <span class="fw-bold fs-7 text-gray-700">Progress Scan Out Keseluruhan</span>
            <span class="fw-bolder fs-6" style="color: {{ $overallPct >= 100 ? '#15803d' : ($overallPct >= 60 ? '#1d4ed8' : '#b45309') }}">{{ $overallPct }}%</span>
        </div>
        <div class="kurir-progress-track" style="height:10px;">
            <div class="kurir-progress-fill {{ $overallPct >= 100 ? 'is-complete' : '' }}"
                 style="width: {{ $overallPct }}%;"></div>
        </div>
        <div class="d-flex justify-content-between mt-2">
            <span class="text-muted fs-8">{{ number_format($totalScanOut ?? 0) }} sudah scan</span>
            <span class="text-muted fs-8">{{ number_format($totalResi ?? 0) }} total aktif</span>
        </div>
    </div>
</div>

{{-- ──────────────────────────────────────────────────────────────────── --}}
{{--  Per-kurir grid                                                        --}}
{{-- ──────────────────────────────────────────────────────────────────── --}}
<div class="dashboard-action-panel mb-6">
    <div>
        <div class="dashboard-action-title">Audit Selisih Scan Out</div>
        <div class="dashboard-action-sub">
            Selisih scan out vs total resi aktif: {{ number_format(abs($scanDiff)) }}
            {{ $scanDiff > 0 ? 'lebih scan out' : ($scanDiff < 0 ? 'kurang scan out' : 'sesuai') }}.
            Lebih: {{ number_format($scanOutOverCount ?? 0) }}, Kurang: {{ number_format($scanOutUnderCount ?? 0) }}.
        </div>
    </div>
    <button type="button" class="btn btn-warning btn-sm" id="btn_scan_discrepancy">
        <i class="fas fa-search me-1"></i>Lihat Selisih
    </button>
</div>

@if($duplicateResiGroupCountVal > 0)
    <div class="dashboard-action-panel mb-6">
        <div>
            <div class="dashboard-action-title">Audit Double Resi</div>
            <div class="dashboard-action-sub">
                Ditemukan {{ number_format($duplicateResiGroupCountVal) }} no resi ganda
                dengan total {{ number_format($duplicateResiTotalVal) }} baris pada tanggal {{ $today ?? '-' }}.
            </div>
            <div class="d-flex flex-wrap gap-2 mt-3">
                @foreach(($duplicateResiRows ?? collect()) as $duplicateResi)
                    <a href="{{ $duplicateResi['url'] }}" class="btn btn-light-warning btn-sm">
                        <i class="fas fa-search me-1"></i>{{ $duplicateResi['no_resi'] }}
                        <span class="badge badge-warning ms-1">{{ number_format($duplicateResi['total']) }}</span>
                    </a>
                @endforeach
            </div>
        </div>
        <a href="{{ ($duplicateResiRows ?? collect())->first()['url'] ?? '#' }}" class="btn btn-warning btn-sm">
            <i class="fas fa-list me-1"></i>Lihat Resi
        </a>
    </div>
@endif

<div class="card">
    <div class="card-header border-0 pt-6 pb-2">
        <div class="card-title flex-column">
            <span class="dash-section-title">Per Kurir</span>
            <span class="dash-section-sub mt-1">Resi aktif & scan out per kurir — {{ $today ?? '-' }}</span>
        </div>
    </div>
    <div class="card-body pt-2">
        @if(isset($kurirs) && $kurirs->count())
            <div class="kurir-grid">
                @foreach($kurirs as $kurir)
                    @php
                        $kPct = $kurir['resi_total'] > 0
                            ? min(100, round($kurir['scan_total'] / $kurir['resi_total'] * 100))
                            : 0;
                    @endphp
                    <div class="kurir-card">
                        {{-- head --}}
                        <div class="kurir-card-head">
                            <div>
                                <div class="kurir-name">{{ $kurir['name'] }}</div>
                            </div>
                            <div class="kurir-updated">
                                <i class="fas fa-clock" style="font-size:10px;"></i>
                                {{ $kurir['last_update'] }}
                            </div>
                        </div>

                        {{-- stat chips --}}
                        <div class="kurir-stats">
                            <div class="kurir-stat-chip chip-blue">
                                <div class="chip-val">{{ number_format($kurir['resi_total']) }}</div>
                                <div class="chip-lbl">Aktif</div>
                            </div>
                            <div class="kurir-stat-chip chip-green">
                                <div class="chip-val">{{ number_format($kurir['scan_total']) }}</div>
                                <div class="chip-lbl">Scan</div>
                            </div>
                            <div class="kurir-stat-chip chip-amber">
                                <div class="chip-val">{{ number_format($kurir['remaining']) }}</div>
                                <div class="chip-lbl">Sisa</div>
                            </div>
                            <div class="kurir-stat-chip chip-red">
                                <div class="chip-val">{{ number_format($kurir['canceled_total'] ?? 0) }}</div>
                                <div class="chip-lbl">Cancel</div>
                            </div>
                        </div>

                        {{-- progress --}}
                        <div class="kurir-progress-wrap">
                            <div class="kurir-progress-label">
                                <span>Progress scan out</span>
                                <span>{{ $kPct }}%</span>
                            </div>
                            <div class="kurir-progress-track">
                                <div class="kurir-progress-fill {{ $kPct >= 100 ? 'is-complete' : '' }}"
                                     style="width: {{ $kPct }}%;"></div>
                            </div>
                        </div>

                        {{-- action --}}
                        <button
                            type="button"
                            class="btn btn-sm btn-light-primary btn-kurir-detail w-100"
                            data-kurir-id="{{ $kurir['id'] }}"
                            data-kurir-name="{{ $kurir['name'] }}"
                            data-date="{{ $today ?? '' }}"
                        >
                            <i class="fas fa-list-ul me-1"></i> Lihat Detail Resi
                        </button>
                    </div>
                @endforeach
            </div>
        @else
            <div class="dash-empty">
                <i class="fas fa-truck"></i>
                <p>Belum ada data kurir untuk tanggal ini.</p>
            </div>
        @endif
    </div>
</div>

</div>

<div class="tab-pane fade" id="tab_empty_stock" role="tabpanel" aria-labelledby="tab-empty-stock-tab">
    <div class="dash-mini-grid mb-6">
        <div class="dash-mini-card">
            <div class="dash-mini-label">Total SKU Stock Kosong</div>
            <div class="dash-mini-value text-danger">{{ number_format($emptyStockTotal) }}</div>
            <div class="text-muted fs-8">Akumulasi seluruh gudang aktif.</div>
        </div>
        <div class="dash-mini-card">
            <div class="dash-mini-label">Gudang Terdampak</div>
            <div class="dash-mini-value text-warning">{{ number_format($emptyWarehouseTotal) }}</div>
            <div class="text-muted fs-8">Gudang non-rusak dengan stock kosong.</div>
        </div>
        <div class="dash-mini-card">
            <div class="dash-mini-label">Referensi Laporan</div>
            <div class="dash-mini-value text-primary">Low Stock</div>
            <a href="{{ route('admin.reports.low-stock.index', ['status' => 'out', 'warehouse_id' => 'all']) }}" class="btn btn-sm btn-light-primary mt-2">
                Buka Report Stock
            </a>
        </div>
    </div>

    <div class="row g-6">
        <div class="col-xl-4">
            <div class="card h-100">
                <div class="card-header border-0 pt-6 pb-2">
                    <div class="card-title flex-column">
                        <span class="dash-section-title">Ringkasan per Gudang</span>
                        <span class="dash-section-sub mt-1">Jumlah SKU dengan stock 0 atau minus.</span>
                    </div>
                </div>
                <div class="card-body pt-2">
                    @forelse(($emptyStockSummary['warehouses'] ?? collect()) as $warehouse)
                        <div class="mb-5">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <div class="fw-bold text-gray-800">{{ $warehouse['name'] }}</div>
                                    <div class="text-muted fs-8">{{ $warehouse['code'] }} · Update {{ $warehouse['latest_update'] }}</div>
                                </div>
                                <span class="badge badge-light-danger">{{ number_format($warehouse['total_empty']) }}</span>
                            </div>
                            <div class="warehouse-progress">
                                <span style="width: {{ min(100, $warehouse['percent']) }}%;"></span>
                            </div>
                        </div>
                    @empty
                        <div class="dash-empty py-8">
                            <i class="fas fa-check-circle text-success"></i>
                            <p>Tidak ada stock kosong.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-xl-8">
            <div class="card h-100">
                <div class="card-header border-0 pt-6 pb-2">
                    <div class="card-title flex-column">
                        <span class="dash-section-title">Detail Stock Kosong</span>
                        <span class="dash-section-sub mt-1">Maksimal 50 SKU pertama, urut gudang dan SKU.</span>
                    </div>
                </div>
                <div class="card-body pt-2">
                    <div class="table-responsive">
                        <table class="table table-row-dashed align-middle">
                            <thead>
                                <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                    <th>SKU</th>
                                    <th>Nama Barang</th>
                                    <th>Gudang</th>
                                    <th>Kategori</th>
                                    <th class="text-end">Stock</th>
                                    <th class="text-end">Safety</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse(($emptyStockRows ?? collect()) as $row)
                                    <tr>
                                        <td class="fw-bold">{{ $row['sku'] }}</td>
                                        <td>
                                            <div>{{ $row['name'] }}</div>
                                            <div class="text-muted fs-8">{{ $row['address'] }}</div>
                                        </td>
                                        <td>
                                            <span class="badge badge-light-secondary">{{ $row['warehouse'] }}</span>
                                        </td>
                                        <td>{{ $row['category'] }}</td>
                                        <td class="text-end text-danger fw-bolder">{{ number_format($row['stock']) }}</td>
                                        <td class="text-end">{{ number_format($row['safety_stock']) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-8">Tidak ada stock kosong.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="tab-pane fade" id="tab_pending_approval" role="tabpanel" aria-labelledby="tab-pending-approval-tab">
    <div class="dash-mini-grid mb-6">
        <div class="dash-mini-card">
            <div class="dash-mini-label">Total Pending Approval</div>
            <div class="dash-mini-value text-warning">{{ number_format($pendingApprovalTotal) }}</div>
            <div class="text-muted fs-8">Gabungan dokumen menunggu tindakan.</div>
        </div>
        <div class="dash-mini-card">
            <div class="dash-mini-label">Menu dengan Pending</div>
            <div class="dash-mini-value text-primary">{{ number_format(collect($pendingApprovalSummary['items'] ?? [])->where('count', '>', 0)->count()) }}</div>
            <div class="text-muted fs-8">Dari semua modul approval utama.</div>
        </div>
    </div>

    <div class="card">
        <div class="card-header border-0 pt-6 pb-2">
            <div class="card-title flex-column">
                <span class="dash-section-title">Pending Approval per Menu</span>
                <span class="dash-section-sub mt-1">Klik menu untuk membuka daftar terkait.</span>
            </div>
        </div>
        <div class="card-body pt-2">
            @forelse(($pendingApprovalSummary['items'] ?? collect()) as $item)
                <div class="approval-row">
                    <div>
                        <div class="fw-bold text-gray-800">{{ $item['label'] }}</div>
                        <div class="text-muted fs-8">{{ $item['group'] }}</div>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <span class="badge {{ $item['count'] > 0 ? 'badge-light-warning' : 'badge-light-success' }}">
                            {{ number_format($item['count']) }} pending
                        </span>
                        <a href="{{ $item['url'] }}" class="btn btn-sm btn-light-primary">Buka</a>
                    </div>
                </div>
            @empty
                <div class="dash-empty">
                    <i class="fas fa-check-circle text-success"></i>
                    <p>Tidak ada pending approval.</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
</div>

{{-- ──────────────────────────────────────────────────────────────────── --}}
{{--  Kurir detail modal                                                    --}}
{{-- ──────────────────────────────────────────────────────────────────── --}}
<div class="modal fade" id="modal_kurir_detail" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="modal-title fw-bolder mb-1">
                        <i class="fas fa-truck me-2 text-primary"></i>
                        Detail Resi Kurir
                    </h5>
                    <div class="text-muted fs-7" id="kurir_detail_subtitle">-</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body pt-4">
                {{-- summary chips --}}
                <div class="modal-kpi-grid">
                    <button type="button" class="modal-kpi-chip js-kurir-detail-type" data-type="total" style="background:#eff6ff; color:#1d4ed8;">
                        <div class="chip-label" style="color:#3b82f6;">Total Resi</div>
                        <div class="chip-value" style="color:#1d4ed8;" id="kurir_detail_total">0</div>
                    </button>
                    <button type="button" class="modal-kpi-chip js-kurir-detail-type" data-type="scanned" style="background:#f0fdf4; color:#15803d;">
                        <div class="chip-label" style="color:#22c55e;">Scan Out</div>
                        <div class="chip-value" style="color:#15803d;" id="kurir_detail_scanned">0</div>
                    </button>
                    <button type="button" class="modal-kpi-chip js-kurir-detail-type" data-type="remaining" style="background:#fffbeb; color:#b45309;">
                        <div class="chip-label" style="color:#f59e0b;">Siap Scan</div>
                        <div class="chip-value" style="color:#b45309;" id="kurir_detail_remaining">0</div>
                    </button>
                    <button type="button" class="modal-kpi-chip js-kurir-detail-type" data-type="canceled" style="background:#fef2f2; color:#b91c1c;">
                        <div class="chip-label" style="color:#ef4444;">Canceled</div>
                        <div class="chip-value" style="color:#b91c1c;" id="kurir_detail_canceled">0</div>
                    </button>
                </div>

                <form id="kurir_detail_search_form" class="mb-5" novalidate>
                    <label for="kurir_detail_search" class="form-label fw-semibold mb-2">Cari nomor resi atau ID pesanan</label>
                    <div class="d-flex gap-2 align-items-start">
                        <textarea
                            id="kurir_detail_search"
                            class="form-control form-control-solid"
                            rows="2"
                            placeholder="Masukkan beberapa nomor resi atau ID pesanan, pisahkan dengan baris baru, koma, atau titik koma"
                        ></textarea>
                        <div class="d-flex flex-column gap-2">
                            <button type="submit" class="btn btn-primary text-nowrap">Cari</button>
                            <button type="button" class="btn btn-light text-nowrap" id="kurir_detail_search_reset">Reset</button>
                        </div>
                    </div>
                    <div class="form-text">Pencarian bersifat presisi dan mengikuti kategori status yang sedang dipilih.</div>
                    <div class="text-muted fs-8 mt-2 d-none" id="kurir_detail_search_summary"></div>
                </form>

                {{-- table --}}
                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                <th width="20%">ID Pesanan</th>
                                <th width="22%">No Resi</th>
                                <th width="28%">SKU</th>
                                <th width="15%">Status</th>
                                <th width="15%">Tanggal Upload</th>
                            </tr>
                        </thead>
                        <tbody id="kurir_detail_body">
                            <tr>
                                <td colspan="5" class="text-center text-muted py-6">Belum ada data.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal_scan_discrepancy" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="modal-title fw-bolder mb-1">
                        <i class="fas fa-clipboard-check me-2 text-warning"></i>
                        Detail Selisih Scan Out
                    </h5>
                    <div class="text-muted fs-7" id="scan_discrepancy_subtitle">Belum dimuat.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body pt-4">
                <div class="modal-kpi-grid">
                    <button type="button" class="modal-kpi-chip js-scan-discrepancy-type" data-type="over" style="background:#fef2f2; color:#b91c1c;">
                        <div class="chip-label" style="color:#ef4444;">Lebih Scan Out</div>
                        <div class="chip-value" style="color:#b91c1c;" id="scan_discrepancy_over">0</div>
                    </button>
                    <button type="button" class="modal-kpi-chip js-scan-discrepancy-type" data-type="under" style="background:#fffbeb; color:#b45309;">
                        <div class="chip-label" style="color:#f59e0b;">Kurang Scan Out</div>
                        <div class="chip-value" style="color:#b45309;" id="scan_discrepancy_under">0</div>
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                <th width="14%">Kategori</th>
                                <th width="15%">ID Pesanan</th>
                                <th width="17%">No Resi</th>
                                <th width="12%">Kurir</th>
                                <th width="22%">SKU</th>
                                <th width="10%">Upload</th>
                                <th width="10%">Scan Out</th>
                            </tr>
                        </thead>
                        <tbody id="scan_discrepancy_body">
                            <tr>
                                <td colspan="7" class="text-center text-muted py-6">Belum ada data.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const kurirDetailUrl  = '{{ route('admin.dashboard.kurir-detail') }}';
    const scanDiscrepancyUrl = '{{ route('admin.dashboard.scan-out-discrepancy') }}';
    const selectedDateStr = '{{ $today ?? '' }}';
    const currentDateStr  = '{{ $currentDate ?? '' }}';

    document.addEventListener('DOMContentLoaded', () => {
        // ── Date filter ──────────────────────────────────────────────────
        const filterDateEl   = document.getElementById('filter_date');
        const filterApplyBtn = document.getElementById('filter_date_apply');
        const filterResetBtn = document.getElementById('filter_date_reset');
        let fpFilterDate = null;

        if (typeof flatpickr !== 'undefined' && filterDateEl) {
            fpFilterDate = flatpickr(filterDateEl, { dateFormat: 'Y-m-d', allowInput: true });
            if (selectedDateStr && !filterDateEl.value) {
                fpFilterDate.setDate(selectedDateStr, true);
            }
        }

        const applyDateFilter = (dateValue) => {
            const url = new URL(window.location.href);
            if (dateValue) { url.searchParams.set('date', dateValue); }
            else            { url.searchParams.delete('date'); }
            window.location.href = url.toString();
        };

        filterApplyBtn?.addEventListener('click', () => {
            applyDateFilter(filterDateEl?.value || '');
        });

        filterResetBtn?.addEventListener('click', () => {
            const resetDate = currentDateStr || '';
            if (fpFilterDate && resetDate) { fpFilterDate.setDate(resetDate, true); }
            else if (filterDateEl)         { filterDateEl.value = resetDate; }
            applyDateFilter(resetDate);
        });

        // ── Kurir detail modal ───────────────────────────────────────────
        const detailModalEl  = document.getElementById('modal_kurir_detail');
        const detailModal    = detailModalEl ? new bootstrap.Modal(detailModalEl) : null;
        const detailSubtitle = document.getElementById('kurir_detail_subtitle');
        const detailTotal    = document.getElementById('kurir_detail_total');
        const detailScanned  = document.getElementById('kurir_detail_scanned');
        const detailRemaining = document.getElementById('kurir_detail_remaining');
        const detailCanceled = document.getElementById('kurir_detail_canceled');
        const detailBody     = document.getElementById('kurir_detail_body');
        const detailSearchForm = document.getElementById('kurir_detail_search_form');
        const detailSearchInput = document.getElementById('kurir_detail_search');
        const detailSearchReset = document.getElementById('kurir_detail_search_reset');
        const detailSearchSummary = document.getElementById('kurir_detail_search_summary');
        const detailTypeButtons = Array.from(document.querySelectorAll('.js-kurir-detail-type'));
        const scanDiscrepancyBtn = document.getElementById('btn_scan_discrepancy');
        const scanDiscrepancyModalEl = document.getElementById('modal_scan_discrepancy');
        const scanDiscrepancyModal = scanDiscrepancyModalEl ? new bootstrap.Modal(scanDiscrepancyModalEl) : null;
        const scanDiscrepancySubtitle = document.getElementById('scan_discrepancy_subtitle');
        const scanDiscrepancyBody = document.getElementById('scan_discrepancy_body');
        const scanDiscrepancyOver = document.getElementById('scan_discrepancy_over');
        const scanDiscrepancyUnder = document.getElementById('scan_discrepancy_under');
        const scanDiscrepancyTypeButtons = Array.from(document.querySelectorAll('.js-scan-discrepancy-type'));
        let scanDiscrepancyRows = { over: [], under: [] };
        let activeScanDiscrepancyType = 'over';
        let activeDetailRequest = {
            kurirId: null,
            kurirName: '-',
            date: '',
            type: 'remaining',
            search: '',
        };

        const detailTypeLabels = {
            total: 'Total Resi',
            scanned: 'Scan Out',
            remaining: 'Siap Scan',
            canceled: 'Canceled',
        };

        const statusBadgeClasses = {
            'Scan Out': 'badge-light-success',
            'Canceled': 'badge-light-danger',
            'Siap Scan Out': 'badge-light-warning',
        };

        const escapeHtml = (value) => String(value ?? '-')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');

        const setScanDiscrepancyActiveType = (type) => {
            activeScanDiscrepancyType = type;
            scanDiscrepancyTypeButtons.forEach(button => {
                button.classList.toggle('is-active', button.getAttribute('data-type') === type);
            });
        };

        const setScanDiscrepancyLoading = () => {
            if (scanDiscrepancySubtitle) scanDiscrepancySubtitle.textContent = 'Memuat data selisih scan out...';
            if (scanDiscrepancyOver) scanDiscrepancyOver.textContent = '-';
            if (scanDiscrepancyUnder) scanDiscrepancyUnder.textContent = '-';
            if (scanDiscrepancyBody) scanDiscrepancyBody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center text-muted py-6">
                        <span class="spinner-border spinner-border-sm me-2"></span>Memuat data...
                    </td>
                </tr>`;
        };

        const renderScanDiscrepancyRows = (type) => {
            if (!scanDiscrepancyBody) return;

            const rows = scanDiscrepancyRows[type] || [];
            if (!Array.isArray(rows) || !rows.length) {
                scanDiscrepancyBody.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center text-muted py-8">
                            <i class="fas fa-check-circle text-success fs-2 mb-3 d-block"></i>
                            Tidak ada data ${type === 'over' ? 'lebih scan out' : 'kurang scan out'} untuk tanggal ini.
                        </td>
                    </tr>`;
                return;
            }

            const badgeClass = type === 'over' ? 'badge-light-danger' : 'badge-light-warning';
            scanDiscrepancyBody.innerHTML = rows.map(row => `
                <tr>
                    <td>
                        <span class="badge ${badgeClass}">${escapeHtml(row.type_label)}</span>
                        <div class="text-muted fs-8 mt-1">${escapeHtml(row.reason)}</div>
                    </td>
                    <td>${escapeHtml(row.id_pesanan)}</td>
                    <td>${escapeHtml(row.no_resi)}</td>
                    <td>${escapeHtml(row.kurir)}</td>
                    <td>${escapeHtml(row.sku)}</td>
                    <td>${escapeHtml(row.tanggal_upload)}</td>
                    <td>${escapeHtml(row.scanned_at)}</td>
                </tr>`).join('');
        };

        const loadScanDiscrepancy = async () => {
            if (!scanDiscrepancyModal) return;

            scanDiscrepancyModal.show();
            setScanDiscrepancyActiveType(activeScanDiscrepancyType);
            setScanDiscrepancyLoading();

            try {
                const params = new URLSearchParams({ date: selectedDateStr || currentDateStr || '' });
                const response = await fetch(`${scanDiscrepancyUrl}?${params.toString()}`);
                const payload = await response.json();

                if (!response.ok) throw new Error(payload?.message || 'Gagal memuat data.');

                scanDiscrepancyRows = payload?.data || { over: [], under: [] };
                const overTotal = Number(payload?.meta?.over_total || 0);
                const underTotal = Number(payload?.meta?.under_total || 0);
                const difference = Number(payload?.meta?.difference || 0);
                if (scanDiscrepancyOver) scanDiscrepancyOver.textContent = overTotal.toLocaleString('id-ID');
                if (scanDiscrepancyUnder) scanDiscrepancyUnder.textContent = underTotal.toLocaleString('id-ID');
                if (scanDiscrepancySubtitle) {
                    const diffLabel = difference > 0
                        ? `${difference.toLocaleString('id-ID')} lebih scan out`
                        : (difference < 0 ? `${Math.abs(difference).toLocaleString('id-ID')} kurang scan out` : 'tidak ada selisih');
                    scanDiscrepancySubtitle.textContent = `Tanggal ${payload?.meta?.date || selectedDateStr || '-'} - ${diffLabel}.`;
                }
                setScanDiscrepancyActiveType(overTotal > 0 ? 'over' : 'under');
                renderScanDiscrepancyRows(activeScanDiscrepancyType);
            } catch (error) {
                if (scanDiscrepancySubtitle) scanDiscrepancySubtitle.textContent = 'Gagal memuat data.';
                if (scanDiscrepancyBody) scanDiscrepancyBody.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center text-danger py-6">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            ${escapeHtml(error.message || 'Gagal memuat data.')}
                        </td>
                    </tr>`;
            }
        };

        const setActiveDetailType = (type) => {
            detailTypeButtons.forEach(button => {
                button.classList.toggle('is-active', button.getAttribute('data-type') === type);
            });
        };

        const setLoadingState = (kurirName, date, type) => {
            setActiveDetailType(type);
            if (detailSubtitle) detailSubtitle.textContent = `${kurirName || '-'} · ${date || '-'} · ${detailTypeLabels[type] || 'Detail'}`;
            [detailTotal, detailScanned, detailRemaining, detailCanceled]
                .forEach(el => { if (el) el.textContent = '–'; });
            if (detailBody) detailBody.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center text-muted py-6">
                        <span class="spinner-border spinner-border-sm me-2"></span>Memuat data…
                    </td>
                </tr>`;
        };

        const setSearchSummary = (searchTerms = [], unmatchedTerms = []) => {
            if (!detailSearchSummary) return;

            if (!Array.isArray(searchTerms) || !searchTerms.length) {
                detailSearchSummary.textContent = '';
                detailSearchSummary.classList.add('d-none');
                return;
            }

            const foundTotal = Math.max(0, searchTerms.length - (unmatchedTerms || []).length);
            let message = `${foundTotal} dari ${searchTerms.length} kata kunci ditemukan.`;
            if (Array.isArray(unmatchedTerms) && unmatchedTerms.length) {
                message += ` Tidak ditemukan: ${unmatchedTerms.join(', ')}.`;
            }
            detailSearchSummary.textContent = message;
            detailSearchSummary.classList.remove('d-none');
        };

        const renderRows = (rows) => {
            if (!detailBody) return;
            if (!Array.isArray(rows) || !rows.length) {
                detailBody.innerHTML = `
                    <tr>
                        <td colspan="5" class="text-center text-muted py-8">
                            <i class="fas fa-info-circle text-primary fs-2 mb-3 d-block"></i>
                            Tidak ada data untuk kategori ini.
                        </td>
                    </tr>`;
                return;
            }
            detailBody.innerHTML = rows.map(row => `
                <tr>
                    <td>${escapeHtml(row.id_pesanan)}</td>
                    <td>${escapeHtml(row.no_resi)}</td>
                    <td>${escapeHtml(row.sku)}</td>
                    <td>
                        <span class="badge ${statusBadgeClasses[row.status] || 'badge-light'}">${escapeHtml(row.status)}</span>
                        ${row.scanned_at ? `<div class="text-muted fs-8 mt-1">${escapeHtml(row.scanned_at)}</div>` : ''}
                    </td>
                    <td>${escapeHtml(row.tanggal_upload)}</td>
                </tr>`).join('');
        };

        const loadKurirDetail = async ({ kurirId, kurirName = '-', date = '', type = 'remaining', search = '' }) => {
            if (!kurirId || !detailModal) return;

            activeDetailRequest = { kurirId, kurirName, date, type, search };
            setLoadingState(kurirName, date, type);

            try {
                const params = new URLSearchParams({ kurir_id: kurirId, date, type });
                if (search.trim()) params.set('search', search.trim());
                const response = await fetch(`${kurirDetailUrl}?${params.toString()}`);
                const payload  = await response.json();

                if (!response.ok) throw new Error(payload?.message || 'Gagal memuat detail kurir.');

                const meta = payload?.meta || {};
                const activeType = meta.type || type;
                setActiveDetailType(activeType);
                if (detailSubtitle) {
                    detailSubtitle.textContent = `${meta.kurir_name || kurirName} · ${meta.date || date || '-'} · ${detailTypeLabels[activeType] || 'Detail'}`;
                }
                if (detailTotal)     detailTotal.textContent     = Number(meta.total_resi     || 0).toLocaleString('id-ID');
                if (detailScanned)   detailScanned.textContent   = Number(meta.scanned_total  || 0).toLocaleString('id-ID');
                if (detailRemaining) detailRemaining.textContent = Number(meta.remaining_total || 0).toLocaleString('id-ID');
                if (detailCanceled)  detailCanceled.textContent  = Number(meta.canceled_total || 0).toLocaleString('id-ID');
                setSearchSummary(meta.search_terms || [], meta.unmatched_search_terms || []);
                renderRows(payload?.data || []);
            } catch (error) {
                setSearchSummary();
                if (detailBody) detailBody.innerHTML = `
                    <tr>
                        <td colspan="5" class="text-center text-danger py-6">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            ${escapeHtml(error.message || 'Gagal memuat detail kurir.')}
                        </td>
                    </tr>`;
            }
        };

        detailTypeButtons.forEach(button => {
            button.addEventListener('click', () => {
                const type = button.getAttribute('data-type') || 'remaining';
                loadKurirDetail({ ...activeDetailRequest, type });
            });
        });

        detailSearchForm?.addEventListener('submit', (event) => {
            event.preventDefault();
            loadKurirDetail({
                ...activeDetailRequest,
                search: detailSearchInput?.value || '',
            });
        });

        detailSearchReset?.addEventListener('click', () => {
            if (detailSearchInput) detailSearchInput.value = '';
            loadKurirDetail({ ...activeDetailRequest, search: '' });
        });

        document.querySelectorAll('.btn-kurir-detail').forEach(button => {
            button.addEventListener('click', () => {
                const kurirId   = button.getAttribute('data-kurir-id');
                const kurirName = button.getAttribute('data-kurir-name') || '-';
                const date      = button.getAttribute('data-date') || '';

                if (!kurirId || !detailModal) return;

                detailModal.show();
                if (detailSearchInput) detailSearchInput.value = '';
                setSearchSummary();
                loadKurirDetail({ kurirId, kurirName, date, type: 'remaining' });
            });
        });

        scanDiscrepancyTypeButtons.forEach(button => {
            button.addEventListener('click', () => {
                const type = button.getAttribute('data-type') || 'over';
                setScanDiscrepancyActiveType(type);
                renderScanDiscrepancyRows(type);
            });
        });

        scanDiscrepancyBtn?.addEventListener('click', loadScanDiscrepancy);
    });
</script>
@endpush
