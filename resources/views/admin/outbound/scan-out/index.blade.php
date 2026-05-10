@extends('layouts.admin')

@section('title', 'Scan Out Desktop')
@section('page_title', 'Scan Out Desktop')

@section('content')
<style>
    .scanout-shell {
        display: grid;
        grid-template-columns: minmax(0, 1.35fr) minmax(340px, .65fr);
        gap: 1rem;
    }
    .scanout-panel {
        background: #fff;
        border: 1px solid #eef0f8;
        border-radius: .75rem;
        box-shadow: 0 10px 28px rgba(15, 23, 42, .04);
    }
    .scanout-hero {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        align-items: flex-start;
        padding: 1.25rem;
        margin-bottom: 1rem;
    }
    .scanout-title {
        font-size: 1.35rem;
        font-weight: 800;
        color: #181c32;
        margin: 0;
    }
    .scanout-subtitle {
        color: #7e8299;
        font-size: .875rem;
        margin-top: .25rem;
    }
    .scanout-actions {
        display: flex;
        flex-wrap: wrap;
        gap: .5rem;
        justify-content: flex-end;
    }
    .scan-card {
        padding: 1.5rem;
        min-height: 560px;
        display: flex;
        flex-direction: column;
    }
    .scan-mode {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .75rem;
        margin-bottom: 1rem;
    }
    .mode-option {
        position: relative;
        cursor: pointer;
        margin: 0;
    }
    .mode-option input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }
    .mode-box {
        border: 1px solid #e4e6ef;
        border-radius: .75rem;
        padding: .9rem 1rem;
        background: #f9fafc;
        color: #3f4254;
        font-weight: 700;
        min-height: 68px;
        display: flex;
        align-items: center;
        gap: .75rem;
        transition: all .15s ease;
    }
    .mode-box i {
        width: 34px;
        height: 34px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: .5rem;
        background: #eef6ff;
        color: #1b84ff;
    }
    .mode-option input:checked + .mode-box {
        border-color: #1b84ff;
        background: #f2f8ff;
        box-shadow: 0 0 0 3px rgba(27, 132, 255, .1);
    }
    .scan-input-wrap {
        position: relative;
        margin-bottom: 1rem;
    }
    .scan-input-wrap i {
        position: absolute;
        left: 1.1rem;
        top: 50%;
        transform: translateY(-50%);
        color: #a1a5b7;
        font-size: 1.15rem;
    }
    .scan-input {
        height: 76px;
        padding-left: 3.1rem;
        padding-right: 1rem;
        border-radius: .85rem;
        border: 2px solid #e4e6ef;
        background: #fff;
        font-size: 1.55rem;
        font-weight: 800;
        letter-spacing: 0;
        color: #181c32;
    }
    .scan-input:focus {
        border-color: #1b84ff;
        box-shadow: 0 0 0 4px rgba(27, 132, 255, .12);
    }
    .scan-input.is-priority {
        border-color: #1b84ff;
        background: #f2f8ff;
        box-shadow: 0 0 0 4px rgba(27, 132, 255, .10);
    }
    .scanner-state {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        margin: -.25rem 0 1rem;
        color: #7e8299;
        font-size: .78rem;
        font-weight: 700;
    }
    .scanner-state-pill {
        display: inline-flex;
        align-items: center;
        gap: .4rem;
        border-radius: 999px;
        background: #e8fff3;
        color: #0e9f6e;
        padding: .4rem .7rem;
        white-space: nowrap;
    }
    .scanner-state-pill i {
        font-size: .72rem;
    }
    .scan-controls {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto auto;
        gap: .75rem;
        align-items: stretch;
    }
    .scan-controls .btn {
        min-height: 52px;
        font-weight: 800;
    }
    .scan-feedback {
        margin-top: 1rem;
        border-radius: .85rem;
        border: 1px solid #e4e6ef;
        background: #f9fafc;
        padding: 1rem;
        min-height: 150px;
    }
    .feedback-state {
        display: flex;
        gap: 1rem;
        align-items: flex-start;
    }
    .feedback-icon {
        width: 48px;
        height: 48px;
        border-radius: .75rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 48px;
        background: #eef6ff;
        color: #1b84ff;
        font-size: 1.25rem;
    }
    .feedback-title {
        font-size: 1.05rem;
        font-weight: 800;
        color: #181c32;
    }
    .feedback-message {
        color: #7e8299;
        margin-top: .2rem;
    }
    .feedback-meta {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: .75rem;
        margin-top: 1rem;
    }
    .meta-box {
        border: 1px solid #eef0f8;
        border-radius: .65rem;
        background: #fff;
        padding: .75rem;
    }
    .meta-label {
        color: #a1a5b7;
        font-size: .72rem;
        font-weight: 700;
        text-transform: uppercase;
    }
    .meta-value {
        color: #181c32;
        font-size: .95rem;
        font-weight: 800;
        margin-top: .25rem;
        word-break: break-word;
    }
    .scan-feedback.success {
        border-color: #b7ebd1;
        background: #f1fbf6;
    }
    .scan-feedback.success .feedback-icon {
        background: #dff8eb;
        color: #1aae6f;
    }
    .scan-feedback.error {
        border-color: #ffd0dc;
        background: #fff5f8;
    }
    .scan-feedback.error .feedback-icon {
        background: #ffe5ee;
        color: #f1416c;
    }
    .scan-feedback.pending .feedback-icon {
        background: #fff8dd;
        color: #b58a00;
    }
    .scan-hints {
        margin-top: auto;
        padding-top: 1rem;
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: .75rem;
    }
    .hint {
        border: 1px dashed #e4e6ef;
        border-radius: .65rem;
        padding: .75rem;
        color: #7e8299;
        font-size: .78rem;
        background: #fff;
    }
    .hint strong {
        color: #3f4254;
        display: block;
        margin-bottom: .2rem;
    }
    .side-panel {
        padding: 1rem;
        display: grid;
        gap: 1rem;
        align-content: start;
    }
    .stat-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .75rem;
    }
    .stat-box {
        border: 1px solid #eef0f8;
        border-radius: .75rem;
        padding: .9rem;
        background: #f9fafc;
    }
    .stat-label {
        color: #7e8299;
        font-size: .72rem;
        font-weight: 700;
        text-transform: uppercase;
    }
    .stat-value {
        font-size: 1.65rem;
        font-weight: 900;
        color: #181c32;
        line-height: 1;
        margin-top: .45rem;
    }
    .recent-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: .75rem;
        margin-bottom: .75rem;
    }
    .recent-title {
        font-weight: 800;
        color: #181c32;
    }
    .recent-list {
        display: grid;
        gap: .55rem;
        max-height: 560px;
        overflow: auto;
        padding-right: .25rem;
    }
    .recent-item {
        border: 1px solid #eef0f8;
        border-radius: .65rem;
        padding: .75rem;
        background: #fff;
    }
    .recent-code {
        font-weight: 900;
        color: #181c32;
        word-break: break-word;
    }
    .recent-meta {
        color: #7e8299;
        font-size: .78rem;
        margin-top: .25rem;
    }
    .recent-empty {
        border: 1px dashed #e4e6ef;
        border-radius: .75rem;
        padding: 1rem;
        color: #7e8299;
        text-align: center;
        background: #f9fafc;
    }
    @media (max-width: 1200px) {
        .scanout-shell {
            grid-template-columns: 1fr;
        }
        .scan-card {
            min-height: auto;
        }
        .recent-list {
            max-height: 360px;
        }
    }
    @media (max-width: 768px) {
        .scanout-hero {
            flex-direction: column;
            padding: 1rem;
        }
        .scanout-actions,
        .scanout-actions .btn {
            width: 100%;
        }
        .scan-card,
        .side-panel {
            padding: 1rem;
        }
        .scan-mode,
        .scan-controls,
        .feedback-meta,
        .scan-hints,
        .stat-grid {
            grid-template-columns: 1fr;
        }
        .scan-input {
            height: 64px;
            font-size: 1.05rem;
        }
        .scan-controls .btn {
            width: 100%;
        }
        .scanner-state {
            align-items: flex-start;
            flex-direction: column;
        }
    }
</style>

<div class="scanout-panel scanout-hero">
    <div>
        <h1 class="scanout-title"><i class="fas fa-truck-loading text-primary me-2"></i>Scan Out Desktop</h1>
        <div class="scanout-subtitle">Mode kerja cepat untuk scanner. Fokus input otomatis, tekan Enter atau scan barcode untuk proses.</div>
    </div>
    <div class="scanout-actions">
        <a href="{{ $routes['transitQc'] }}" class="btn btn-light-info">
            <i class="fas fa-clipboard-check me-1"></i>Siap Scan Out
        </a>
        <a href="{{ $routes['history'] }}" class="btn btn-light-primary">
            <i class="fas fa-history me-1"></i>Riwayat
        </a>
    </div>
</div>

<div class="scanout-shell">
    <div class="scanout-panel scan-card">
        <div class="scan-mode" role="radiogroup" aria-label="Jenis scan">
            <label class="mode-option">
                <input type="radio" name="scan_type" value="no_resi" checked>
                <span class="mode-box"><i class="fas fa-barcode"></i><span>No Resi<br><small class="text-muted fw-semibold">Default scanner expedisi</small></span></span>
            </label>
            <label class="mode-option">
                <input type="radio" name="scan_type" value="id_pesanan">
                <span class="mode-box"><i class="fas fa-receipt"></i><span>ID Pesanan<br><small class="text-muted fw-semibold">Alternatif scan order</small></span></span>
            </label>
        </div>

        <div class="scan-input-wrap">
            <i class="fas fa-barcode"></i>
            <input type="text" id="scan_code" class="form-control scan-input" placeholder="Scan No Resi di sini" autocomplete="off" inputmode="none">
        </div>
        <div class="scanner-state">
            <span class="scanner-state-pill" id="scanner_state"><i class="fas fa-circle"></i>Scanner siap</span>
            <span>Enter, Tab, atau paste barcode akan langsung diproses. F1 No Resi, F2 ID Pesanan.</span>
        </div>

        <div class="scan-controls">
            <button type="button" id="btn_scan" class="btn btn-primary">
                <i class="fas fa-check me-1"></i>Proses Scan Out
            </button>
            <button type="button" id="btn_clear" class="btn btn-light">
                <i class="fas fa-times me-1"></i>Bersihkan
            </button>
            <button type="button" id="btn_refocus" class="btn btn-light-primary">
                <i class="fas fa-crosshairs me-1"></i>Fokus
            </button>
        </div>

        <div class="scan-feedback" id="scan_feedback">
            <div class="feedback-state">
                <div class="feedback-icon" id="feedback_icon"><i class="fas fa-barcode"></i></div>
                <div class="flex-grow-1">
                    <div class="feedback-title" id="feedback_title">Siap Scan</div>
                    <div class="feedback-message" id="feedback_message">Arahkan scanner ke barcode resi. Sistem akan memproses saat Enter diterima.</div>
                    <div class="feedback-meta" id="feedback_meta">
                        <div class="meta-box">
                            <div class="meta-label">ID Pesanan</div>
                            <div class="meta-value" id="meta_order">-</div>
                        </div>
                        <div class="meta-box">
                            <div class="meta-label">No Resi</div>
                            <div class="meta-value" id="meta_resi">-</div>
                        </div>
                        <div class="meta-box">
                            <div class="meta-label">Kurir</div>
                            <div class="meta-value" id="meta_kurir">-</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="scan-hints">
            <div class="hint"><strong>Enter otomatis</strong> Scanner umumnya mengirim Enter setelah barcode.</div>
            <div class="hint"><strong>Fokus dijaga</strong> Setelah sukses/gagal, input kembali aktif.</div>
            <div class="hint"><strong>Validasi QC</strong> Resi hanya bisa scan out setelah lolos QC.</div>
        </div>
    </div>

    <aside class="scanout-panel side-panel">
        <div class="stat-grid">
            <div class="stat-box">
                <div class="stat-label">Scan Hari Ini</div>
                <div class="stat-value" id="stat_today">-</div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Scan Terakhir</div>
                <div class="stat-value" id="stat_last" style="font-size:1.15rem;">-</div>
            </div>
        </div>

        <div>
            <div class="recent-head">
                <div>
                    <div class="recent-title">Scan Terbaru</div>
                    <div class="text-muted fs-8">{{ $today }}</div>
                </div>
                <button type="button" class="btn btn-sm btn-light" id="btn_refresh_recent">
                    <i class="fas fa-sync-alt me-1"></i>Refresh
                </button>
            </div>
            <div class="recent-list" id="recent_list">
                <div class="recent-empty">Memuat scan terbaru...</div>
            </div>
        </div>
    </aside>
</div>

<script>
const routes = @json($routes);
const csrfToken = '{{ csrf_token() }}';

const el = {
    code: document.getElementById('scan_code'),
    btnScan: document.getElementById('btn_scan'),
    btnClear: document.getElementById('btn_clear'),
    btnRefocus: document.getElementById('btn_refocus'),
    btnRefreshRecent: document.getElementById('btn_refresh_recent'),
    scannerState: document.getElementById('scanner_state'),
    feedback: document.getElementById('scan_feedback'),
    feedbackIcon: document.getElementById('feedback_icon'),
    feedbackTitle: document.getElementById('feedback_title'),
    feedbackMessage: document.getElementById('feedback_message'),
    metaOrder: document.getElementById('meta_order'),
    metaResi: document.getElementById('meta_resi'),
    metaKurir: document.getElementById('meta_kurir'),
    statToday: document.getElementById('stat_today'),
    statLast: document.getElementById('stat_last'),
    recentList: document.getElementById('recent_list'),
};

let isSubmitting = false;
let audioCtx = null;
let scannerFocusPaused = false;
let pasteSubmitTimer = null;

const selectedType = () => document.querySelector('input[name="scan_type"]:checked')?.value || 'no_resi';
const isScannerFocusPaused = () => scannerFocusPaused || !!document.querySelector('.swal2-container.swal2-shown');
const setScannerState = (message, type = 'ready') => {
    if (!el.scannerState) return;
    const icon = type === 'pending' ? 'fa-spinner fa-spin' : type === 'error' ? 'fa-exclamation-circle' : 'fa-circle';
    el.scannerState.innerHTML = `<i class="fas ${icon}"></i>${escapeHtml(message)}`;
    el.scannerState.style.background = type === 'error' ? '#fff5f8' : type === 'pending' ? '#fff8dd' : '#e8fff3';
    el.scannerState.style.color = type === 'error' ? '#f1416c' : type === 'pending' ? '#b58a00' : '#0e9f6e';
};
const focusScanner = () => {
    if (isScannerFocusPaused()) return;
    window.setTimeout(() => {
        if (isScannerFocusPaused()) return;
        el.code.focus({ preventScroll: true });
        el.code.select();
        el.code.classList.add('is-priority');
        setScannerState('Scanner siap');
    }, 30);
};
const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
}[char]));
const getAudioCtx = () => {
    if (!audioCtx) {
        const Ctx = window.AudioContext || window.webkitAudioContext;
        if (!Ctx) return null;
        audioCtx = new Ctx();
    }
    if (audioCtx.state === 'suspended') audioCtx.resume().catch(() => {});
    return audioCtx;
};
const beep = (frequency, duration = 120, volume = .32) => {
    const ctx = getAudioCtx();
    if (!ctx) return;
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.frequency.value = frequency;
    osc.type = 'sine';
    gain.gain.value = volume;
    osc.connect(gain);
    gain.connect(ctx.destination);
    osc.start();
    setTimeout(() => {
        try { osc.stop(); } catch (error) {}
        osc.disconnect();
        gain.disconnect();
    }, duration);
};
const setFeedback = (state, title, message, scanOut = null) => {
    el.feedback.className = `scan-feedback ${state || ''}`.trim();
    const icon = state === 'success' ? 'fa-check' : state === 'error' ? 'fa-exclamation-triangle' : state === 'pending' ? 'fa-spinner fa-spin' : 'fa-barcode';
    el.feedbackIcon.innerHTML = `<i class="fas ${icon}"></i>`;
    el.feedbackTitle.textContent = title;
    el.feedbackMessage.textContent = message;
    el.metaOrder.textContent = scanOut?.id_pesanan || '-';
    el.metaResi.textContent = scanOut?.no_resi || '-';
    el.metaKurir.textContent = scanOut?.kurir || '-';
};
const scanContextFromPayload = (payload = {}) => {
    const resi = payload?.resi || {};
    const qc = payload?.qc || {};
    const scanOut = payload?.scan_out || {};

    return {
        id_pesanan: scanOut.id_pesanan || resi.id_pesanan || '-',
        no_resi: scanOut.no_resi || resi.no_resi || '-',
        kurir: scanOut.kurir || resi.kurir || qc.status_label || '-',
    };
};
const rejectionTitle = (payload = {}) => {
    if (payload.reason_code === 'qc_not_started') return 'Belum QC Scan';
    if (payload.reason_code === 'qc_not_passed') return 'QC Belum Selesai';
    return 'Scan Ditolak';
};
const rejectionMessage = (error) => {
    if (error.status === 419) {
        return 'Sesi halaman sudah kedaluwarsa atau token keamanan tidak cocok. Refresh halaman, lalu scan ulang. Jika masih terjadi, login ulang.';
    }

    const payload = error.payload || {};
    return [error.message || 'Gagal memproses scan out.', payload.detail || '']
        .filter(Boolean)
        .join(' ');
};
const fetchJson = async (url, options = {}) => {
    const response = await fetch(url, {
        credentials: 'same-origin',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
            ...(options.headers || {}),
        },
        ...options,
    });
    const json = await response.json().catch(() => ({}));
    if (!response.ok) {
        const error = new Error(
            response.status === 419
                ? 'Sesi kedaluwarsa atau token keamanan tidak valid.'
                : (json?.message || 'Request gagal.')
        );
        error.payload = json;
        error.status = response.status;
        throw error;
    }
    return json;
};
const schedulePasteSubmit = () => {
    window.clearTimeout(pasteSubmitTimer);
    pasteSubmitTimer = window.setTimeout(() => submitScan(), 80);
};
const selectScanType = (type) => {
    const input = document.querySelector(`input[name="scan_type"][value="${type}"]`);
    if (!input) return;
    input.checked = true;
    el.code.placeholder = type === 'id_pesanan' ? 'Scan ID Pesanan di sini' : 'Scan No Resi di sini';
    focusScanner();
};
const renderRecent = (items = []) => {
    if (!items.length) {
        el.recentList.innerHTML = '<div class="recent-empty">Belum ada scan out hari ini.</div>';
        return;
    }
    el.recentList.innerHTML = items.map((item) => `
        <div class="recent-item">
            <div class="d-flex justify-content-between gap-2">
                <div class="recent-code">${escapeHtml(item.no_resi || '-')}</div>
                <span class="badge badge-light-success">${escapeHtml(item.scanned_time || '-')}</span>
            </div>
            <div class="recent-meta">ID: ${escapeHtml(item.id_pesanan || '-')} | ${escapeHtml(item.kurir || '-')}</div>
            <div class="recent-meta">Oleh: ${escapeHtml(item.scanner || '-')}</div>
        </div>
    `).join('');
};
const refreshRecent = async () => {
    try {
        const data = await fetchJson(`${routes.recent}?limit=12`);
        el.statToday.textContent = data?.summary?.today ?? 0;
        el.statLast.textContent = data?.summary?.last_scan_at || '-';
        renderRecent(data?.items || []);
    } catch (error) {
        el.recentList.innerHTML = '<div class="recent-empty text-danger">Gagal memuat scan terbaru.</div>';
    }
};
const submitScan = async () => {
    if (isSubmitting) return;
    getAudioCtx();
    const code = el.code.value.trim();
    if (!code) {
        setFeedback('error', 'Kode Kosong', 'Scan atau masukkan nomor terlebih dahulu.');
        setScannerState('Kode kosong', 'error');
        beep(280, 160, .28);
        focusScanner();
        return;
    }
    isSubmitting = true;
    el.btnScan.disabled = true;
    setScannerState('Memproses scan...', 'pending');
    setFeedback('pending', 'Memproses', `Memvalidasi ${code}...`);
    try {
        const data = await fetchJson(routes.scan, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ type: selectedType(), code, _token: csrfToken }),
        });
        beep(1180, 120, .4);
        setFeedback('success', 'Scan Out Berhasil', data?.message || 'Resi berhasil discan keluar.', data?.scan_out);
        setScannerState('Scan berhasil, siap berikutnya');
        el.code.value = '';
        await refreshRecent();
    } catch (error) {
        beep(220, 180, .3);
        const isSessionExpired = error.status === 419;
        const title = isSessionExpired ? 'Sesi Kedaluwarsa' : rejectionTitle(error.payload || {});
        const message = rejectionMessage(error);
        const context = scanContextFromPayload(error.payload || {});
        setScannerState(isSessionExpired ? 'Sesi kedaluwarsa' : 'Scan ditolak', 'error');
        setFeedback('error', title, message, context);
        if (typeof Swal !== 'undefined') {
            scannerFocusPaused = isSessionExpired;
            Swal.fire({
                icon: 'error',
                title,
                html: `
                    <div class="text-start">
                        <div class="fw-bold mb-2">${escapeHtml(error.message || 'Gagal memproses scan out.')}</div>
                        ${error.payload?.detail ? `<div class="mb-2">${escapeHtml(error.payload.detail)}</div>` : ''}
                        <div class="text-muted small">
                            ID Pesanan: ${escapeHtml(context.id_pesanan)}<br>
                            No Resi: ${escapeHtml(context.no_resi)}<br>
                            Kurir/Status: ${escapeHtml(context.kurir)}
                        </div>
                    </div>
                `,
                timer: isSessionExpired ? undefined : 4200,
                showConfirmButton: isSessionExpired,
                confirmButtonText: 'Refresh Halaman',
            }).then((result) => {
                if (isSessionExpired && result.isConfirmed) {
                    window.location.reload();
                    return;
                }
                scannerFocusPaused = false;
                focusScanner();
            });
        }
    } finally {
        isSubmitting = false;
        el.btnScan.disabled = false;
        focusScanner();
    }
};

document.querySelectorAll('input[name="scan_type"]').forEach((input) => {
    input.addEventListener('change', () => {
        selectScanType(input.value);
    });
});
el.code.addEventListener('keydown', (event) => {
    if (event.key === 'Enter' || event.key === 'Tab') {
        event.preventDefault();
        submitScan();
    }
});
el.code.addEventListener('paste', schedulePasteSubmit);
el.btnScan.addEventListener('click', submitScan);
el.btnClear.addEventListener('click', () => {
    el.code.value = '';
    setFeedback('', 'Siap Scan', 'Input dibersihkan. Arahkan scanner ke barcode berikutnya.');
    setScannerState('Scanner siap');
    focusScanner();
});
el.btnRefocus.addEventListener('click', focusScanner);
el.btnRefreshRecent.addEventListener('click', refreshRecent);
document.addEventListener('keydown', (event) => {
    if (isScannerFocusPaused()) return;
    if (event.key === 'F1') {
        event.preventDefault();
        selectScanType('no_resi');
        return;
    }
    if (event.key === 'F2') {
        event.preventDefault();
        selectScanType('id_pesanan');
        return;
    }
    if (event.key === 'Escape') {
        event.preventDefault();
        el.code.value = '';
        setFeedback('', 'Siap Scan', 'Input dibersihkan. Arahkan scanner ke barcode berikutnya.');
        setScannerState('Scanner siap');
        focusScanner();
        return;
    }
    if (!event.ctrlKey && !event.altKey && !event.metaKey && event.key.length === 1 && document.activeElement !== el.code) {
        const target = event.target;
        const tag = (target?.tagName || '').toLowerCase();
        const isTextTarget = tag === 'input' || tag === 'textarea' || tag === 'select' || target?.isContentEditable;
        if (isTextTarget) return;
        event.preventDefault();
        el.code.focus({ preventScroll: true });
        el.code.value = `${el.code.value || ''}${event.key}`;
        el.code.classList.add('is-priority');
        setScannerState('Scanner siap');
    }
});
document.addEventListener('click', (event) => {
    if (isScannerFocusPaused()) return;
    if (event.target.closest('a, button, input, label, select, textarea')) return;
    focusScanner();
});
document.addEventListener('visibilitychange', () => {
    if (!document.hidden) focusScanner();
});
window.addEventListener('focus', focusScanner);
el.code.addEventListener('blur', () => {
    if (isScannerFocusPaused()) return;
    const active = document.activeElement;
    const tag = (active?.tagName || '').toLowerCase();
    if (tag === 'input' || tag === 'textarea' || tag === 'select' || active?.isContentEditable) return;
    focusScanner();
});
document.addEventListener('DOMContentLoaded', () => {
    refreshRecent();
    setTimeout(focusScanner, 250);
});
</script>
@endsection
