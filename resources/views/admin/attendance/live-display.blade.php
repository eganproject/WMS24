@extends('layouts.admin')

@section('title', 'Live Display Absensi')
@section('page_title', 'Live Display Absensi')

@push('styles')
<style>
    .live-shell {
        --ink: #111827;
        --muted: #6b7280;
        --line: #e5e7eb;
        --soft: #f8fafc;
        --blue: #2563eb;
        --green: #16a34a;
        --amber: #d97706;
        --rose: #e11d48;
        display: grid;
        gap: 18px;
    }
    .live-nav {
        display: flex;
        flex-wrap: nowrap;
        gap: 8px;
        overflow-x: auto;
        padding: 6px 2px;
        scrollbar-width: thin;
    }
    .live-nav-item {
        flex: 0 0 auto;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 14px;
        border-radius: 8px;
        background: #f5f8fa;
        color: #5e6278;
        font-weight: 700;
        font-size: 13px;
        text-decoration: none;
        border: 1px solid transparent;
        white-space: nowrap;
    }
    .live-nav-item:hover { background: #eef3f7; color: #1b84ff; }
    .live-nav-item.active {
        background: #111827;
        color: #fff;
        box-shadow: 0 8px 18px rgba(17, 24, 39, .18);
    }
    .live-stage {
        position: relative;
        min-height: 460px;
        border: 1px solid #dbe1ea;
        border-radius: 8px;
        overflow: hidden;
        background:
            linear-gradient(135deg, rgba(37, 99, 235, .11), rgba(22, 163, 74, .08) 42%, rgba(217, 119, 6, .10)),
            #ffffff;
        display: grid;
        grid-template-columns: minmax(0, 1.25fr) minmax(340px, .75fr);
    }
    .live-stage:fullscreen {
        width: 100vw;
        height: 100vh;
        min-height: 100vh;
        border: 0;
        border-radius: 0;
        background:
            linear-gradient(135deg, rgba(37, 99, 235, .16), rgba(22, 163, 74, .12) 42%, rgba(217, 119, 6, .12)),
            #ffffff;
    }
    .live-stage:fullscreen .live-welcome { padding: clamp(40px, 6vw, 86px); }
    .live-stage:fullscreen .live-greeting { font-size: clamp(52px, 6.8vw, 112px); }
    .live-stage:fullscreen .live-name { font-size: clamp(34px, 4vw, 62px); }
    .live-stage.is-event-in,
    .live-stage.is-event-out {
        animation: liveStagePulse 900ms ease-out;
    }
    .live-stage::after {
        content: "";
        position: absolute;
        inset: 0;
        pointer-events: none;
        opacity: 0;
        background: radial-gradient(circle at 26% 30%, rgba(37, 99, 235, .30), transparent 34%);
        transition: opacity .25s ease;
    }
    .live-stage.is-event-out::after {
        background: radial-gradient(circle at 26% 30%, rgba(22, 163, 74, .30), transparent 34%);
    }
    .live-stage.is-event-in::after,
    .live-stage.is-event-out::after {
        opacity: 1;
        animation: liveGlowFade 1200ms ease-out forwards;
    }
    .live-particle-layer {
        position: absolute;
        inset: 0;
        pointer-events: none;
        overflow: hidden;
        z-index: 3;
    }
    .live-particle {
        position: absolute;
        left: var(--x);
        top: var(--y);
        width: var(--size);
        height: var(--size);
        border-radius: 999px;
        background: var(--color);
        transform: translate(-50%, -50%);
        animation: liveParticleFly var(--duration) cubic-bezier(.17, .84, .44, 1) forwards;
    }
    .live-particle.is-square {
        border-radius: 4px;
        transform: translate(-50%, -50%) rotate(18deg);
    }
    .live-welcome {
        position: relative;
        z-index: 2;
        padding: clamp(28px, 5vw, 58px);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        gap: 34px;
    }
    .live-topline {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        flex-wrap: wrap;
    }
    .live-kicker {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: #374151;
    }
    .live-clock {
        font-size: 32px;
        font-weight: 900;
        color: var(--ink);
        line-height: 1;
        font-variant-numeric: tabular-nums;
    }
    .live-date {
        color: var(--muted);
        font-size: 13px;
        font-weight: 700;
        text-align: right;
    }
    .live-event-badge {
        width: max-content;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        border-radius: 8px;
        padding: 10px 14px;
        background: #fff;
        border: 1px solid rgba(17, 24, 39, .08);
        color: var(--blue);
        font-weight: 800;
        box-shadow: 0 10px 28px rgba(15, 23, 42, .08);
    }
    .live-event-badge.is-out { color: var(--green); }
    .live-event-badge.is-animated { animation: liveBadgeBounce 620ms ease-out; }
    .live-greeting {
        margin: 18px 0 0;
        font-size: clamp(32px, 5.2vw, 72px);
        line-height: 1.02;
        font-weight: 900;
        color: var(--ink);
        letter-spacing: 0;
    }
    .live-greeting.is-animated { animation: liveGreetingPop 680ms ease-out; }
    .live-name {
        margin-top: 18px;
        font-size: clamp(24px, 3vw, 42px);
        font-weight: 900;
        color: #1f2937;
    }
    .live-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 16px;
    }
    .live-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        border-radius: 8px;
        padding: 9px 12px;
        background: rgba(255, 255, 255, .78);
        border: 1px solid rgba(17, 24, 39, .08);
        color: #374151;
        font-weight: 700;
        font-size: 13px;
    }
    .live-summary {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 10px;
    }
    .live-stat {
        background: rgba(255, 255, 255, .78);
        border: 1px solid rgba(17, 24, 39, .08);
        border-radius: 8px;
        padding: 15px;
    }
    .live-stat.is-animated { animation: liveStatPulse 700ms ease-out; }
    .live-stat-label {
        color: var(--muted);
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .05em;
    }
    .live-stat-value {
        margin-top: 5px;
        font-size: 30px;
        font-weight: 900;
        color: var(--ink);
        line-height: 1;
        font-variant-numeric: tabular-nums;
    }
    .live-side {
        position: relative;
        z-index: 2;
        background: rgba(255, 255, 255, .86);
        border-left: 1px solid rgba(17, 24, 39, .08);
        padding: 24px;
        display: grid;
        grid-template-rows: auto 1fr;
        gap: 18px;
    }
    .live-side-head {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 12px;
    }
    .live-side-title {
        margin: 0;
        color: var(--ink);
        font-size: 17px;
        font-weight: 900;
    }
    .live-connection {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        font-size: 12px;
        color: var(--muted);
        font-weight: 700;
        white-space: nowrap;
    }
    .live-dot {
        width: 9px;
        height: 9px;
        border-radius: 999px;
        background: var(--green);
        box-shadow: 0 0 0 5px rgba(22, 163, 74, .13);
    }
    .live-list {
        display: grid;
        align-content: start;
        gap: 10px;
        max-height: 560px;
        overflow: auto;
        padding-right: 4px;
    }
    .live-row {
        display: grid;
        grid-template-columns: 50px minmax(0, 1fr);
        gap: 12px;
        align-items: center;
        background: #fff;
        border: 1px solid #edf0f5;
        border-radius: 8px;
        padding: 12px;
        transition: border-color .2s ease, transform .2s ease, box-shadow .2s ease;
    }
    .live-row.is-new {
        border-color: rgba(37, 99, 235, .45);
        transform: translateY(-1px);
        box-shadow: 0 10px 24px rgba(37, 99, 235, .12);
    }
    .live-row-icon {
        width: 46px;
        height: 46px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #eff6ff;
        color: var(--blue);
        font-size: 18px;
    }
    .live-row.is-out .live-row-icon {
        background: #ecfdf5;
        color: var(--green);
    }
    .live-row-name {
        color: var(--ink);
        font-weight: 900;
        font-size: 14px;
        overflow-wrap: anywhere;
    }
    .live-row-meta {
        color: var(--muted);
        font-size: 12px;
        font-weight: 600;
        margin-top: 2px;
    }
    .live-empty {
        border: 1px dashed #cbd5e1;
        border-radius: 8px;
        color: var(--muted);
        padding: 28px;
        text-align: center;
        font-weight: 700;
        background: rgba(255, 255, 255, .68);
    }
    .live-toolbar {
        background: #fff;
        border: 1px solid #edf0f5;
        border-radius: 8px;
        padding: 14px;
        display: flex;
        align-items: end;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
    }
    .live-toolbar .form-control { max-width: 180px; }
    @keyframes liveStagePulse {
        0% { transform: scale(.994); box-shadow: 0 0 0 rgba(37, 99, 235, 0); }
        35% { transform: scale(1); box-shadow: 0 18px 42px rgba(37, 99, 235, .16); }
        100% { transform: scale(1); box-shadow: 0 0 0 rgba(37, 99, 235, 0); }
    }
    @keyframes liveGlowFade {
        0% { opacity: 1; }
        100% { opacity: 0; }
    }
    @keyframes liveGreetingPop {
        0% { transform: translateY(14px) scale(.97); opacity: .35; }
        55% { transform: translateY(-4px) scale(1.015); opacity: 1; }
        100% { transform: translateY(0) scale(1); opacity: 1; }
    }
    @keyframes liveBadgeBounce {
        0% { transform: translateY(8px) scale(.94); opacity: .45; }
        55% { transform: translateY(-4px) scale(1.03); opacity: 1; }
        100% { transform: translateY(0) scale(1); opacity: 1; }
    }
    @keyframes liveStatPulse {
        0% { transform: translateY(0); box-shadow: 0 0 0 rgba(37, 99, 235, 0); }
        42% { transform: translateY(-3px); box-shadow: 0 12px 24px rgba(37, 99, 235, .13); }
        100% { transform: translateY(0); box-shadow: 0 0 0 rgba(37, 99, 235, 0); }
    }
    @keyframes liveParticleFly {
        0% {
            opacity: 1;
            transform: translate(-50%, -50%) scale(1) rotate(0deg);
        }
        100% {
            opacity: 0;
            transform: translate(calc(-50% + var(--dx)), calc(-50% + var(--dy))) scale(.25) rotate(var(--rot));
        }
    }
    @media (max-width: 1199px) {
        .live-stage { grid-template-columns: 1fr; }
        .live-side { border-left: 0; border-top: 1px solid rgba(17, 24, 39, .08); }
    }
    @media (max-width: 767px) {
        .live-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .live-welcome { padding: 24px; }
        .live-side { padding: 18px; }
        .live-clock { font-size: 26px; }
        .live-date { text-align: left; }
    }
</style>
@endpush

@section('content')
@php $sectionLinks = $sectionLinks ?? []; @endphp

<div class="live-shell" id="live_shell">
    <div class="card shadow-sm">
        <div class="card-body py-3">
            <nav class="live-nav">
                @foreach($sectionLinks as $sectionKey => $section)
                    <a href="{{ route($section['route']) }}" class="live-nav-item {{ $sectionKey === 'live_display' ? 'active' : '' }}">
                        <i class="{{ $section['icon'] }}"></i>
                        <span>{{ $section['label'] }}</span>
                    </a>
                @endforeach
            </nav>
        </div>
    </div>

    <div class="live-toolbar">
        <div>
            <div class="fw-bolder text-gray-900">Live Display Mesin Absensi</div>
            <div class="text-muted fs-7">Tampilan otomatis untuk monitor area fingerprint.</div>
        </div>
        <div class="d-flex align-items-end gap-2 flex-wrap">
            <div>
                <label class="form-label fw-bold mb-1">Tanggal</label>
                <input type="text" class="form-control form-control-solid" id="live_date" value="{{ $today }}" placeholder="YYYY-MM-DD">
            </div>
            <button type="button" class="btn btn-dark" id="btn_refresh_live">
                <i class="fas fa-sync-alt me-1"></i>Refresh
            </button>
            <button type="button" class="btn btn-light-dark" id="btn_fullscreen_live">
                <i class="fas fa-expand me-1"></i>Full Layar
            </button>
        </div>
    </div>

    <section class="live-stage" id="live_stage">
        <div class="live-particle-layer" id="particle_layer"></div>
        <div class="live-welcome">
            <div class="live-topline">
                <div class="live-kicker"><i class="fas fa-fingerprint"></i> Attendance Gate</div>
                <div>
                    <div class="live-clock" id="live_clock">--:--:--</div>
                    <div class="live-date" id="live_today">-</div>
                </div>
            </div>

            <div>
                <div class="live-event-badge" id="event_badge">
                    <i class="fas fa-id-card"></i>
                    <span id="event_label">Menunggu scan</span>
                </div>
                <h1 class="live-greeting" id="event_greeting">Silakan scan fingerprint Anda.</h1>
                <div class="live-name" id="employee_name">Belum ada aktivitas hari ini</div>
                <div class="live-meta">
                    <span class="live-pill"><i class="fas fa-user-tag"></i><span id="employee_code">-</span></span>
                    <span class="live-pill"><i class="fas fa-briefcase"></i><span id="employee_position">-</span></span>
                    <span class="live-pill"><i class="fas fa-map-marker-alt"></i><span id="device_location">-</span></span>
                    <span class="live-pill"><i class="fas fa-clock"></i><span id="scan_time">-</span></span>
                </div>
            </div>

            <div class="live-summary">
                <div class="live-stat">
                    <div class="live-stat-label">Check-in</div>
                    <div class="live-stat-value" id="sum_checked_in">0</div>
                </div>
                <div class="live-stat">
                    <div class="live-stat-label">Check-out</div>
                    <div class="live-stat-value" id="sum_checked_out">0</div>
                </div>
                <div class="live-stat">
                    <div class="live-stat-label">Belum Pulang</div>
                    <div class="live-stat-value" id="sum_incomplete">0</div>
                </div>
                <div class="live-stat">
                    <div class="live-stat-label">Terlambat</div>
                    <div class="live-stat-value" id="sum_late">0</div>
                </div>
            </div>
        </div>

        <aside class="live-side">
            <div class="live-side-head">
                <div>
                    <h2 class="live-side-title">Aktivitas Terbaru</h2>
                    <div class="text-muted fs-7" id="server_status">Sinkronisasi awal...</div>
                </div>
                <div class="live-connection"><span class="live-dot"></span><span>Live</span></div>
            </div>
            <div class="live-list" id="recent_list">
                <div class="live-empty">Belum ada data scan untuk tanggal ini.</div>
            </div>
        </aside>
    </section>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const feedUrl = @json($feedUrl);
    const el = {
        shell: document.getElementById('live_shell'),
        stage: document.getElementById('live_stage'),
        particles: document.getElementById('particle_layer'),
        date: document.getElementById('live_date'),
        refresh: document.getElementById('btn_refresh_live'),
        fullscreen: document.getElementById('btn_fullscreen_live'),
        clock: document.getElementById('live_clock'),
        today: document.getElementById('live_today'),
        badge: document.getElementById('event_badge'),
        label: document.getElementById('event_label'),
        greeting: document.getElementById('event_greeting'),
        name: document.getElementById('employee_name'),
        code: document.getElementById('employee_code'),
        position: document.getElementById('employee_position'),
        location: document.getElementById('device_location'),
        scanTime: document.getElementById('scan_time'),
        checkedIn: document.getElementById('sum_checked_in'),
        checkedOut: document.getElementById('sum_checked_out'),
        incomplete: document.getElementById('sum_incomplete'),
        late: document.getElementById('sum_late'),
        status: document.getElementById('server_status'),
        recent: document.getElementById('recent_list'),
    };
    let latestId = 0;
    let busy = false;
    let lastRenderedEventId = 0;
    let hasRenderedInitial = false;

    const nf = new Intl.NumberFormat('id-ID');
    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const setClock = () => {
        const now = new Date();
        el.clock.textContent = now.toLocaleTimeString('id-ID', { hour12: false });
        el.today.textContent = now.toLocaleDateString('id-ID', {
            weekday: 'long',
            day: '2-digit',
            month: 'long',
            year: 'numeric',
        });
    };

    const restartCssAnimation = (node, className) => {
        if (!node) return;
        node.classList.remove(className);
        void node.offsetWidth;
        node.classList.add(className);
        window.setTimeout(() => node.classList.remove(className), 900);
    };

    const emitParticles = (event) => {
        if (!el.particles || !el.stage) return;
        const isOut = event?.event_type === 'out';
        const colors = isOut
            ? ['#16a34a', '#22c55e', '#86efac', '#0f766e']
            : ['#2563eb', '#38bdf8', '#93c5fd', '#f59e0b'];
        const count = el.stage.matches(':fullscreen') ? 46 : 30;
        const originX = window.innerWidth < 768 ? 50 : 32;
        const originY = window.innerWidth < 768 ? 40 : 42;

        for (let i = 0; i < count; i += 1) {
            const particle = document.createElement('span');
            const angle = (Math.PI * 2 * i / count) + (Math.random() * .65);
            const distance = 90 + Math.random() * (el.stage.matches(':fullscreen') ? 260 : 170);
            const size = 6 + Math.random() * 9;
            particle.className = `live-particle ${i % 3 === 0 ? 'is-square' : ''}`;
            particle.style.setProperty('--x', `${originX + (Math.random() * 8 - 4)}%`);
            particle.style.setProperty('--y', `${originY + (Math.random() * 8 - 4)}%`);
            particle.style.setProperty('--dx', `${Math.cos(angle) * distance}px`);
            particle.style.setProperty('--dy', `${Math.sin(angle) * distance}px`);
            particle.style.setProperty('--rot', `${Math.round(Math.random() * 420 - 210)}deg`);
            particle.style.setProperty('--size', `${size}px`);
            particle.style.setProperty('--duration', `${760 + Math.random() * 520}ms`);
            particle.style.setProperty('--color', colors[i % colors.length]);
            el.particles.appendChild(particle);
            window.setTimeout(() => particle.remove(), 1500);
        }
    };

    const triggerEventAnimation = (event) => {
        if (!event || !el.stage) return;
        const eventClass = event.event_type === 'out' ? 'is-event-out' : 'is-event-in';
        el.stage.classList.remove('is-event-in', 'is-event-out');
        void el.stage.offsetWidth;
        el.stage.classList.add(eventClass);
        window.setTimeout(() => el.stage.classList.remove(eventClass), 1200);
        restartCssAnimation(el.greeting, 'is-animated');
        restartCssAnimation(el.badge, 'is-animated');
        document.querySelectorAll('.live-stat').forEach((node, index) => {
            window.setTimeout(() => restartCssAnimation(node, 'is-animated'), index * 65);
        });
        emitParticles(event);
    };

    const renderLatest = (event, isNew = false) => {
        if (!event) return;
        el.badge.classList.toggle('is-out', event.event_type === 'out');
        el.label.textContent = event.event_label || 'Scan Absensi';
        el.greeting.textContent = event.greeting || 'Scan berhasil.';
        el.name.textContent = event.employee_name || '-';
        el.code.textContent = event.employee_code || '-';
        el.position.textContent = event.position || '-';
        el.location.textContent = [event.device, event.location].filter((v) => v && v !== '-').join(' | ') || '-';
        el.scanTime.textContent = `${event.scan_date || '-'} ${event.scan_time || '-'}`;

        if (isNew) {
            triggerEventAnimation(event);
        }
    };

    const renderSummary = (summary = {}) => {
        el.checkedIn.textContent = nf.format(summary.checked_in || 0);
        el.checkedOut.textContent = nf.format(summary.checked_out || 0);
        el.incomplete.textContent = nf.format(summary.incomplete || 0);
        el.late.textContent = nf.format(summary.late || 0);
    };

    const renderRecent = (rows = [], newIds = new Set()) => {
        if (!rows.length) {
            el.recent.innerHTML = '<div class="live-empty">Belum ada data scan untuk tanggal ini.</div>';
            return;
        }

        el.recent.innerHTML = rows.map((row) => {
            const isOut = row.event_type === 'out';
            const isNew = newIds.has(Number(row.id));
            const icon = isOut ? 'fa-sign-out-alt' : 'fa-sign-in-alt';
            return `
                <div class="live-row ${isOut ? 'is-out' : ''} ${isNew ? 'is-new' : ''}">
                    <div class="live-row-icon"><i class="fas ${icon}"></i></div>
                    <div>
                        <div class="live-row-name">${escapeHtml(row.employee_name || '-')}</div>
                        <div class="live-row-meta">${escapeHtml(row.event_label || '-')} | ${escapeHtml(row.scan_time || '-')} | ${escapeHtml(row.device || '-')}</div>
                    </div>
                </div>
            `;
        }).join('');
    };

    const loadFeed = async (manual = false) => {
        if (busy) return;
        busy = true;
        try {
            const url = new URL(feedUrl, window.location.origin);
            url.searchParams.set('date', el.date.value || '');
            if (latestId > 0) url.searchParams.set('latest_id', latestId);
            const response = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Gagal memuat data.');

            const newIds = new Set((data.new_events || []).map((row) => Number(row.id)));
            const newestEvent = (data.new_events || []).slice(-1)[0] || data.latest;
            const shouldAnimate = hasRenderedInitial && newestEvent && Number(newestEvent.id) !== lastRenderedEventId;

            renderSummary(data.summary || {});
            renderRecent(data.recent || [], newIds);
            if (newestEvent) {
                renderLatest(newestEvent, shouldAnimate);
                lastRenderedEventId = Number(newestEvent.id || 0);
                hasRenderedInitial = true;
            }

            latestId = Number(data.latest_id || latestId || 0);
            el.status.textContent = `Terakhir sinkron ${data.server_time || '-'}`;
        } catch (error) {
            el.status.textContent = error.message || 'Koneksi feed bermasalah.';
        } finally {
            busy = false;
        }
    };

    if (typeof flatpickr !== 'undefined') {
        flatpickr(el.date, {
            dateFormat: 'Y-m-d',
            allowInput: true,
            onChange: () => {
                latestId = 0;
                lastRenderedEventId = 0;
                loadFeed(true);
            },
        });
    }

    el.refresh.addEventListener('click', () => loadFeed(true));
    el.fullscreen?.addEventListener('click', async () => {
        if (!el.stage || !document.fullscreenEnabled) {
            return;
        }

        try {
            if (document.fullscreenElement) {
                await document.exitFullscreen();
                el.fullscreen.innerHTML = '<i class="fas fa-expand me-1"></i>Full Layar';
                return;
            }

            await el.stage.requestFullscreen();
            el.fullscreen.innerHTML = '<i class="fas fa-compress me-1"></i>Keluar Full Layar';
        } catch (error) {
            el.status.textContent = 'Mode full layar tidak bisa diaktifkan oleh browser.';
        }
    });
    document.addEventListener('fullscreenchange', () => {
        if (!el.fullscreen) return;
        el.fullscreen.innerHTML = document.fullscreenElement
            ? '<i class="fas fa-compress me-1"></i>Keluar Full Layar'
            : '<i class="fas fa-expand me-1"></i>Full Layar';
    });
    el.date.addEventListener('change', () => {
        latestId = 0;
        lastRenderedEventId = 0;
        hasRenderedInitial = false;
        loadFeed(true);
    });

    setClock();
    window.setInterval(setClock, 1000);
    loadFeed(true);
    window.setInterval(loadFeed, 3000);
});
</script>
@endpush
