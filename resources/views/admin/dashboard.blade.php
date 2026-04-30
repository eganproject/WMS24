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
        border-radius: 12px;
        padding: 12px 14px;
        display: flex;
        flex-direction: column;
        gap: 2px;
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
</style>
@endpush

@section('content')
@php
    $isToday = ($today ?? '') === ($currentDate ?? '');
    $totalActive = ($totalResi ?? 0);
    $totalCanceledVal = ($totalCanceled ?? 0);
    $totalScanVal = ($totalScanOut ?? 0);
    $overallPct = $totalActive > 0 ? min(100, round($totalScanVal / $totalActive * 100)) : 0;
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
                    <div class="modal-kpi-chip" style="background:#eff6ff;">
                        <div class="chip-label" style="color:#3b82f6;">Total Resi</div>
                        <div class="chip-value" style="color:#1d4ed8;" id="kurir_detail_total">0</div>
                    </div>
                    <div class="modal-kpi-chip" style="background:#f0fdf4;">
                        <div class="chip-label" style="color:#22c55e;">Scan Out</div>
                        <div class="chip-value" style="color:#15803d;" id="kurir_detail_scanned">0</div>
                    </div>
                    <div class="modal-kpi-chip" style="background:#fffbeb;">
                        <div class="chip-label" style="color:#f59e0b;">Siap Scan</div>
                        <div class="chip-value" style="color:#b45309;" id="kurir_detail_remaining">0</div>
                    </div>
                    <div class="modal-kpi-chip" style="background:#fef2f2;">
                        <div class="chip-label" style="color:#ef4444;">Canceled</div>
                        <div class="chip-value" style="color:#b91c1c;" id="kurir_detail_canceled">0</div>
                    </div>
                </div>

                {{-- table --}}
                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                <th width="28%">ID Pesanan</th>
                                <th width="28%">No Resi</th>
                                <th width="22%">Status</th>
                                <th width="22%">Tanggal Upload</th>
                            </tr>
                        </thead>
                        <tbody id="kurir_detail_body">
                            <tr>
                                <td colspan="4" class="text-center text-muted py-6">Belum ada data.</td>
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

        const setLoadingState = (kurirName, date) => {
            if (detailSubtitle) detailSubtitle.textContent = `${kurirName || '-'} · ${date || '-'}`;
            [detailTotal, detailScanned, detailRemaining, detailCanceled]
                .forEach(el => { if (el) el.textContent = '–'; });
            if (detailBody) detailBody.innerHTML = `
                <tr>
                    <td colspan="4" class="text-center text-muted py-6">
                        <span class="spinner-border spinner-border-sm me-2"></span>Memuat data…
                    </td>
                </tr>`;
        };

        const renderRows = (rows) => {
            if (!detailBody) return;
            if (!Array.isArray(rows) || !rows.length) {
                detailBody.innerHTML = `
                    <tr>
                        <td colspan="4" class="text-center text-muted py-8">
                            <i class="fas fa-check-circle text-success fs-2 mb-3 d-block"></i>
                            Semua resi sudah di-scan out.
                        </td>
                    </tr>`;
                return;
            }
            detailBody.innerHTML = rows.map(row => `
                <tr>
                    <td>${row.id_pesanan || '-'}</td>
                    <td>${row.no_resi || '-'}</td>
                    <td>
                        <span class="badge badge-light-warning">${row.status || '-'}</span>
                    </td>
                    <td>${row.tanggal_upload || '-'}</td>
                </tr>`).join('');
        };

        document.querySelectorAll('.btn-kurir-detail').forEach(button => {
            button.addEventListener('click', async () => {
                const kurirId   = button.getAttribute('data-kurir-id');
                const kurirName = button.getAttribute('data-kurir-name') || '-';
                const date      = button.getAttribute('data-date') || '';

                if (!kurirId || !detailModal) return;

                setLoadingState(kurirName, date);
                detailModal.show();

                try {
                    const params   = new URLSearchParams({ kurir_id: kurirId, date });
                    const response = await fetch(`${kurirDetailUrl}?${params.toString()}`);
                    const payload  = await response.json();

                    if (!response.ok) throw new Error(payload?.message || 'Gagal memuat detail kurir.');

                    const meta = payload?.meta || {};
                    if (detailSubtitle) {
                        detailSubtitle.textContent = `${meta.kurir_name || kurirName} · ${meta.date || date || '-'}`;
                    }
                    if (detailTotal)     detailTotal.textContent     = Number(meta.total_resi     || 0).toLocaleString('id-ID');
                    if (detailScanned)   detailScanned.textContent   = Number(meta.scanned_total  || 0).toLocaleString('id-ID');
                    if (detailRemaining) detailRemaining.textContent = Number(meta.remaining_total || 0).toLocaleString('id-ID');
                    if (detailCanceled)  detailCanceled.textContent  = Number(meta.canceled_total || 0).toLocaleString('id-ID');
                    renderRows(payload?.data || []);
                } catch (error) {
                    if (detailBody) detailBody.innerHTML = `
                        <tr>
                            <td colspan="4" class="text-center text-danger py-6">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                ${error.message || 'Gagal memuat detail kurir.'}
                            </td>
                        </tr>`;
                }
            });
        });
    });
</script>
@endpush
