@extends('layouts.mobile')

@section('title', 'QC Resi')

@section('content')
<style>
    .section-title {
        font-weight: 700;
        margin-bottom: 8px;
    }
    .scan-actions {
        display: grid;
        gap: 10px;
        margin-top: 10px;
    }
    .scan-row {
        display: grid;
        gap: 8px;
        grid-template-columns: 1fr auto;
        align-items: center;
    }
    .scan-btn {
        width: auto;
        padding: 10px 12px;
        font-size: 12px;
        border-radius: 12px;
        font-weight: 700;
        border: 1px solid var(--border);
        background: #fff;
    }
    .photo-scan {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .photo-btn {
        width: auto;
        padding: 10px 12px;
        font-size: 12px;
        border-radius: 12px;
        font-weight: 700;
        border: 1px dashed var(--border);
        background: #fff;
    }
    .status-line {
        font-size: 12px;
        color: var(--muted);
        margin-top: 6px;
    }
    .result-card {
        display: none;
    }
    .result-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 8px;
    }
    .result-badge {
        padding: 4px 10px;
        border-radius: 999px;
        background: rgba(16, 185, 129, 0.15);
        color: #047857;
        font-weight: 700;
        font-size: 11px;
    }
    .result-badge.pending {
        background: rgba(249, 115, 22, 0.15);
        color: #c2410c;
    }
    .result-badge.hold {
        background: rgba(239, 68, 68, 0.16);
        color: #b91c1c;
    }
    .result-items {
        display: grid;
        gap: 10px;
        margin-top: 10px;
    }
    .result-item {
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 10px 12px;
        background: #fff;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        font-size: 13px;
    }
    .result-meta {
        font-size: 12px;
        color: var(--muted);
    }
    .count-pill {
        padding: 6px 10px;
        border-radius: 999px;
        background: rgba(15, 118, 110, 0.12);
        color: #0f766e;
        font-weight: 700;
        font-size: 12px;
        white-space: nowrap;
    }
    .count-pill.done {
        background: rgba(34, 197, 94, 0.16);
        color: #15803d;
    }
    .topbar-actions {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .topbar-actions form {
        margin: 0;
    }
    .scanner-modal {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.72);
        display: none;
        align-items: center;
        justify-content: center;
        padding: 16px;
        z-index: 50;
    }
    .scanner-card {
        width: 100%;
        max-width: 520px;
        background: #fff;
        border-radius: 18px;
        padding: 14px;
        border: 1px solid var(--border);
        box-shadow: var(--shadow);
        display: grid;
        gap: 10px;
    }
    .scanner-video,
    .scanner-qr {
        width: 100%;
        border-radius: 14px;
        background: #111827;
    }
    .scanner-qr {
        overflow: hidden;
        display: none;
    }
    .scanner-actions {
        display: flex;
        justify-content: space-between;
        gap: 8px;
    }
    .scanner-actions .primary-btn {
        width: auto;
        padding: 10px 12px;
        font-size: 12px;
    }
    .qc-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 10px;
    }
    .qc-actions .ghost-btn,
    .qc-actions .primary-btn {
        width: auto;
        padding: 10px 12px;
        font-size: 12px;
    }
    .qty-input {
        width: 120px;
    }
</style>

<div class="screen">
    <div class="topbar">
        <div>
            <div class="brand">{{ config('app.name', 'Gudang 24') }}</div>
            <div class="subtitle">QC Resi</div>
        </div>
        <div class="topbar-actions">
            <a href="{{ $routes['dashboard'] }}" class="logout">Dashboard</a>
            <form method="POST" action="{{ $routes['logout'] }}">
                @csrf
                <button type="submit" class="logout">Logout</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="section-title">Scan Resi QC</div>
        <div class="muted">Scan resi terlebih dahulu untuk mengambil daftar SKU.</div>
        <div class="scan-actions">
            <select class="input" id="resi_type">
                <option value="no_resi">No Resi</option>
                <option value="id_pesanan">ID Pesanan</option>
            </select>
            <div class="scan-row">
                <input type="text" class="input" id="resi_code" placeholder="Scan No. Resi" autocomplete="off" />
                <button type="button" class="scan-btn" id="btn_open_resi_scanner">Scan</button>
            </div>
            <div class="photo-scan" id="photo_scan_wrap">
                <button type="button" class="photo-btn" id="btn_scan_photo">Scan via Foto</button>
                <span class="muted">Alternatif untuk iPhone.</span>
            </div>
            <input type="file" id="scan_photo" accept="image/*" capture="environment" style="display:none;" />
            <button type="button" class="primary-btn" id="btn_scan_resi">Proses Resi</button>
        </div>
        <div class="status-line" id="resi_status">Siap memproses resi.</div>
    </div>

    <div class="card">
        <div class="section-title">Scan SKU</div>
        <div class="muted">Scan setiap SKU sesuai detail resi.</div>
        <div class="scan-actions">
            <div class="scan-row">
                <input type="text" class="input" id="sku_code" placeholder="Scan SKU" autocomplete="off" />
                <button type="button" class="scan-btn" id="btn_open_sku_scanner">Scan</button>
            </div>
            <div class="scan-row">
                <input type="number" class="input qty-input" id="sku_qty" min="1" value="1" />
                <button type="button" class="primary-btn" id="btn_scan_sku">Tambah SKU</button>
            </div>
        </div>
        <div class="status-line" id="sku_status">Menunggu resi.</div>
    </div>

    <div class="card result-card" id="result_card">
        <div class="result-header">
            <div>
                <div style="font-weight:700;" id="result_title">QC Berjalan</div>
                <div class="result-meta" id="result_meta">-</div>
            </div>
            <div class="result-badge pending" id="result_badge">Proses</div>
        </div>
        <div class="result-meta" id="result_summary">-</div>
        <div class="result-items" id="result_items"></div>
        <div class="qc-actions">
            <button type="button" class="ghost-btn" id="btn_hold_qc">Simpan & Lewatkan</button>
            <button type="button" class="ghost-btn" id="btn_reset_qc">Reset QC</button>
            <button type="button" class="primary-btn" id="btn_complete_qc">Selesaikan QC</button>
        </div>
        <div class="status-line" id="qc_status">-</div>
    </div>
</div>

<div class="scanner-modal" id="scanner_modal">
    <div class="scanner-card">
        <div style="font-weight:700;" id="scanner_title">Kamera Scanner</div>
        <video class="scanner-video" id="scanner_video" playsinline></video>
        <div class="scanner-qr" id="scanner_qr"></div>
        <div class="scanner-actions">
            <button type="button" class="ghost-btn" id="btn_close_scanner">Tutup</button>
            <button type="button" class="primary-btn" id="btn_start_scan">Coba Lagi</button>
        </div>
        <div class="muted" id="scanner_hint">Kamera aktif otomatis. Arahkan ke barcode.</div>
    </div>
</div>

<script>
    const routes = @json($routes);
    const csrfToken = '{{ csrf_token() }}';

    let audioCtx = null;
    const getAudioCtx = () => {
        if (!audioCtx) {
            const Ctx = window.AudioContext || window.webkitAudioContext;
            if (!Ctx) return null;
            audioCtx = new Ctx();
        }
        if (audioCtx.state === 'suspended') {
            audioCtx.resume().catch(() => {});
        }
        return audioCtx;
    };
    const playBeep = (frequency = 880, duration = 120, volume = 0.35) => {
        const ctx = getAudioCtx();
        if (!ctx) return;
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.value = frequency;
        gain.gain.value = volume;
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start();
        setTimeout(() => {
            try { osc.stop(); } catch (e) {}
            osc.disconnect();
            gain.disconnect();
        }, duration);
    };
    const playScanSound = () => playBeep(760, 120, 0.35);
    const playSuccessSound = () => playBeep(1200, 140, 0.45);

    const el = {
        resiType: document.getElementById('resi_type'),
        resiCode: document.getElementById('resi_code'),
        btnScanResi: document.getElementById('btn_scan_resi'),
        resiStatus: document.getElementById('resi_status'),
        btnOpenResiScanner: document.getElementById('btn_open_resi_scanner'),
        btnOpenSkuScanner: document.getElementById('btn_open_sku_scanner'),
        skuCode: document.getElementById('sku_code'),
        skuQty: document.getElementById('sku_qty'),
        btnScanSku: document.getElementById('btn_scan_sku'),
        skuStatus: document.getElementById('sku_status'),
        resultCard: document.getElementById('result_card'),
        resultMeta: document.getElementById('result_meta'),
        resultItems: document.getElementById('result_items'),
        resultBadge: document.getElementById('result_badge'),
        resultTitle: document.getElementById('result_title'),
        resultSummary: document.getElementById('result_summary'),
        qcStatus: document.getElementById('qc_status'),
        btnHoldQc: document.getElementById('btn_hold_qc'),
        btnResetQc: document.getElementById('btn_reset_qc'),
        btnCompleteQc: document.getElementById('btn_complete_qc'),
        scannerModal: document.getElementById('scanner_modal'),
        scannerVideo: document.getElementById('scanner_video'),
        scannerQr: document.getElementById('scanner_qr'),
        btnCloseScanner: document.getElementById('btn_close_scanner'),
        btnStartScan: document.getElementById('btn_start_scan'),
        scannerHint: document.getElementById('scanner_hint'),
        scannerTitle: document.getElementById('scanner_title'),
        photoScanWrap: document.getElementById('photo_scan_wrap'),
        scanPhotoInput: document.getElementById('scan_photo'),
        btnScanPhoto: document.getElementById('btn_scan_photo'),
    };

    let qcState = {
        id: null,
        status: null,
        items: [],
        summary: null,
        resi: null,
        audit: null,
    };

    let scannerStream = null;
    let scannerActive = false;
    let barcodeDetector = null;
    let scanLoopId = null;
    let html5Qr = null;
    let scanMode = 'native';
    let html5LoadPromise = null;
    let scanTarget = 'resi';
    let qcActionBusy = false;
    const isIOS = (() => {
        const ua = navigator.userAgent || '';
        const platform = navigator.platform || '';
        const isAppleMobile = /iPad|iPhone|iPod/.test(ua);
        const isIpadOs = platform === 'MacIntel' && navigator.maxTouchPoints > 1;
        return isAppleMobile || isIpadOs;
    })();

    const loadHtml5Qr = () => {
        if (typeof Html5Qrcode !== 'undefined') {
            return Promise.resolve(true);
        }
        if (html5LoadPromise) {
            return html5LoadPromise;
        }

        const sources = [
            '{{ asset('vendor/html5-qrcode.min.js') }}',
        ];

        html5LoadPromise = new Promise((resolve) => {
            const tryLoad = (index) => {
                if (index >= sources.length) {
                    resolve(false);
                    return;
                }

                const script = document.createElement('script');
                script.src = sources[index];
                script.async = true;
                script.onload = () => resolve(true);
                script.onerror = () => tryLoad(index + 1);
                document.head.appendChild(script);
            };

            tryLoad(0);
        });

        return html5LoadPromise;
    };

    const setStatus = (elNode, text, type = 'muted') => {
        elNode.textContent = text;
        if (type === 'error') {
            elNode.style.color = '#b91c1c';
        } else if (type === 'success') {
            elNode.style.color = '#047857';
        } else if (type === 'pending') {
            elNode.style.color = '#f97316';
        } else {
            elNode.style.color = '#6b7280';
        }
    };

    const buildErrorMessage = (res, json) => {
        if (json?.message) {
            return json.message;
        }
        if (res.status === 419) {
            return 'Sesi habis. Silakan refresh halaman dan coba lagi.';
        }
        if (res.status === 403) {
            return 'Akses ditolak.';
        }
        if (res.status === 404) {
            return 'Endpoint tidak ditemukan.';
        }
        if (res.status >= 500) {
            return 'Terjadi kesalahan server. Coba lagi.';
        }
        return 'Terjadi kesalahan.';
    };

    const fetchJson = async (url, options = {}) => {
        const res = await fetch(url, {
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                ...(options.headers || {}),
            },
            ...options,
        });

        const text = await res.text();
        let json = null;
        try { json = JSON.parse(text); } catch (err) { json = null; }

        if (!res.ok) {
            const error = new Error(buildErrorMessage(res, json));
            if (json?.details) {
                error.details = json.details;
            }
            error.status = res.status;
            throw error;
        }

        return json;
    };

    const showError = (message, details = []) => {
        if (typeof Swal !== 'undefined') {
            let html = `<div style="text-align:left; font-size:13px;">${message}</div>`;
            if (Array.isArray(details) && details.length) {
                const list = details.map((row) => {
                    const sku = row.sku || '-';
                    const detailBits = [];

                    if (row.required !== undefined && row.scanned !== undefined) {
                        detailBits.push(`Butuh ${row.required}, sudah ${row.scanned}`);
                    } else if (row.required !== undefined) {
                        detailBits.push(`Butuh ${row.required}`);
                    }

                    if (row.attempt !== undefined) {
                        detailBits.push(`Scan sekarang ${row.attempt}`);
                    }

                    if (row.available !== undefined) {
                        detailBits.push(`Tersedia ${row.available}`);
                    }

                    if (row.reason) {
                        detailBits.push(row.reason);
                    }

                    const detailLine = detailBits.length
                        ? `<div style="color:#64748b; font-size:12px;">${detailBits.join(' • ')}</div>`
                        : '';

                    return `<li style="margin-bottom:8px;"><strong>${sku}</strong>${detailLine}</li>`;
                }).join('');
                html += `<ul style="text-align:left; padding-left:18px; margin-top:8px;">${list}</ul>`;
            }
            Swal.fire({
                icon: 'error',
                title: 'Gagal',
                html,
            });
            return;
        }

        setStatus(el.qcStatus, message, 'error');
    };

    const renderQc = () => {
        const qc = qcState;
        if (!qc.id) {
            el.resultCard.style.display = 'none';
            return;
        }

        const resi = qc.resi || {};
        const resiLine = [
            resi.id_pesanan ? `ID Pesanan: ${resi.id_pesanan}` : null,
            resi.no_resi ? `No Resi: ${resi.no_resi}` : null,
            resi.tanggal_pesanan ? `Tanggal Order: ${resi.tanggal_pesanan}` : null,
        ].filter(Boolean).join(' • ');

        el.resultMeta.textContent = resiLine || '-';

        const summary = qc.summary || { total_expected: 0, total_scanned: 0, remaining: 0 };
        const audit = qc.audit || {};
        const auditBits = [];
        if (audit.started_by && audit.started_by !== '-') {
            auditBits.push(`Mulai: ${audit.started_by}`);
        }
        if (audit.last_scanned_by && audit.last_scanned_by !== '-' && audit.last_scanned_at) {
            auditBits.push(`Scan terakhir: ${audit.last_scanned_by} @ ${audit.last_scanned_at}`);
        }
        if ((audit.reset_count || 0) > 0) {
            auditBits.push(`Reset: ${audit.reset_count}x`);
        }
        if (audit.hold_by && audit.hold_by !== '-' && audit.hold_at) {
            auditBits.push(`Ditunda: ${audit.hold_by} @ ${audit.hold_at}`);
        }
        if (audit.hold_reason) {
            auditBits.push(`Alasan hold: ${audit.hold_reason}`);
        }

        const summaryBits = [
            `Total: ${summary.total_scanned}/${summary.total_expected}`,
            `Sisa: ${summary.remaining}`,
            ...auditBits,
        ];
        el.resultSummary.textContent = summaryBits.join(' • ');

        const statusPassed = qc.status === 'passed';
        const statusHold = qc.status === 'hold';
        el.resultTitle.textContent = statusPassed
            ? 'QC Selesai'
            : (statusHold ? 'QC Ditunda' : 'QC Berjalan');
        el.resultBadge.textContent = statusPassed
            ? 'Selesai'
            : (statusHold ? 'Ditunda' : 'Proses');
        el.resultBadge.className = `result-badge${statusHold ? ' hold' : (!statusPassed ? ' pending' : '')}`;

        el.resultItems.innerHTML = (qc.items || []).map((row) => {
            const expected = row.expected_qty ?? 0;
            const scanned = row.scanned_qty ?? 0;
            const done = expected > 0 && scanned >= expected;
            return `<div class="result-item">
                <div>
                    <strong>${row.sku || '-'}</strong>
                    <div class="result-meta">Target ${expected} qty</div>
                </div>
                <div class="count-pill ${done ? 'done' : ''}">${scanned}/${expected}</div>
            </div>`;
        }).join('');

        el.resultCard.style.display = 'block';
        el.btnHoldQc.disabled = statusPassed || qcActionBusy;
        el.btnCompleteQc.disabled = statusPassed || summary.remaining > 0 || qcActionBusy;
        el.btnResetQc.disabled = statusPassed || qcActionBusy;

        if (statusPassed) {
            setStatus(el.qcStatus, 'QC selesai. Resi siap dipacking.', 'success');
        } else if (statusHold) {
            setStatus(el.qcStatus, audit.hold_reason ? `QC ditunda: ${audit.hold_reason}` : 'QC ditunda. Bisa dilanjutkan nanti.', 'pending');
        } else {
            setStatus(el.qcStatus, 'QC belum selesai.', summary.remaining === 0 ? 'success' : 'pending');
        }
    };

    const submitResi = async () => {
        getAudioCtx();
        const type = el.resiType.value;
        const code = el.resiCode.value.trim();
        if (!code) {
            setStatus(el.resiStatus, 'Masukkan nomor resi atau ID pesanan.', 'error');
            el.resiCode.focus();
            return;
        }

        el.btnScanResi.disabled = true;
        setStatus(el.resiStatus, 'Memproses resi...', 'pending');

        try {
            const data = await fetchJson(routes.scanResi, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ code, type, _token: csrfToken }),
            });

            qcState = {
                id: data?.qc?.id || null,
                status: data?.qc?.status || null,
                items: data?.qc?.items || [],
                summary: data?.qc?.summary || null,
                resi: data?.resi || null,
                audit: data?.qc?.audit || null,
            };

            setStatus(el.resiStatus, data?.message || 'Resi siap QC.', 'success');
            setStatus(el.skuStatus, 'Siap scan SKU.', 'success');
            playSuccessSound();
            renderQc();
            el.resiCode.value = '';
            el.resiCode.focus();
        } catch (error) {
            showError(error.message || 'Gagal memproses resi.', error.details || []);
            setStatus(el.resiStatus, error.message || 'Gagal memproses resi.', 'error');
        } finally {
            el.btnScanResi.disabled = false;
        }
    };

    const submitSku = async () => {
        getAudioCtx();
        if (!qcState.id) {
            setStatus(el.skuStatus, 'Scan resi terlebih dahulu.', 'error');
            return;
        }

        const code = el.skuCode.value.trim();
        const qty = parseInt(el.skuQty.value || '1', 10);
        if (!code) {
            setStatus(el.skuStatus, 'Masukkan SKU.', 'error');
            el.skuCode.focus();
            return;
        }
        if (!qty || qty <= 0) {
            setStatus(el.skuStatus, 'Qty minimal 1.', 'error');
            el.skuQty.focus();
            return;
        }

        el.btnScanSku.disabled = true;
        setStatus(el.skuStatus, 'Memproses SKU...', 'pending');

        try {
            const data = await fetchJson(routes.scanSku, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ qc_id: qcState.id, code, qty, _token: csrfToken }),
            });

            qcState = {
                ...qcState,
                status: data?.qc?.status || qcState.status,
                items: data?.qc?.items || qcState.items,
                summary: data?.qc?.summary || qcState.summary,
                audit: data?.qc?.audit || qcState.audit,
            };

            setStatus(el.skuStatus, data?.message || 'SKU berhasil discan.', 'success');
            playScanSound();
            renderQc();
            el.skuCode.value = '';
            el.skuQty.value = '1';
            el.skuCode.focus();
        } catch (error) {
            showError(error.message || 'Gagal memproses SKU.', error.details || []);
            setStatus(el.skuStatus, error.message || 'Gagal memproses SKU.', 'error');
        } finally {
            el.btnScanSku.disabled = false;
        }
    };

    const holdQc = async () => {
        if (!qcState.id || qcActionBusy) return;

        let reason = '';
        if (typeof Swal !== 'undefined') {
            const result = await Swal.fire({
                title: 'Simpan & Lewatkan',
                text: 'Masukkan alasan penundaan untuk audit.',
                input: 'text',
                inputPlaceholder: 'Contoh: transit SKU belum cukup',
                inputAttributes: {
                    maxlength: '500',
                },
                showCancelButton: true,
                confirmButtonText: 'Simpan',
                cancelButtonText: 'Batal',
                inputValidator: (value) => {
                    if (!value || !value.trim()) {
                        return 'Alasan simpan & lewatkan wajib diisi.';
                    }
                    return null;
                },
            });

            if (!result.isConfirmed) {
                return;
            }

            reason = (result.value || '').trim();
        } else {
            reason = (window.prompt('Alasan simpan & lewatkan:') || '').trim();
            if (!reason) {
                setStatus(el.qcStatus, 'Simpan & lewatkan dibatalkan. Alasan wajib diisi.', 'error');
                return;
            }
        }

        qcActionBusy = true;
        renderQc();
        setStatus(el.qcStatus, 'Menyimpan QC untuk dilewatkan...', 'pending');

        try {
            const data = await fetchJson(routes.hold, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ qc_id: qcState.id, reason, _token: csrfToken }),
            });

            qcState = {
                ...qcState,
                status: data?.qc?.status || qcState.status,
                items: data?.qc?.items || qcState.items,
                summary: data?.qc?.summary || qcState.summary,
                audit: data?.qc?.audit || qcState.audit,
            };

            setStatus(el.qcStatus, data?.message || 'QC disimpan untuk dilewatkan.', 'success');
            renderQc();
        } catch (error) {
            showError(error.message || 'Gagal menyimpan QC untuk dilewatkan.', error.details || []);
            setStatus(el.qcStatus, error.message || 'Gagal menyimpan QC untuk dilewatkan.', 'error');
        } finally {
            qcActionBusy = false;
            renderQc();
        }
    };

    const completeQc = async () => {
        if (!qcState.id || qcActionBusy) return;

        qcActionBusy = true;
        renderQc();
        setStatus(el.qcStatus, 'Menyelesaikan QC...', 'pending');

        try {
            const data = await fetchJson(routes.complete, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ qc_id: qcState.id, _token: csrfToken }),
            });

            qcState = {
                ...qcState,
                status: data?.qc?.status || qcState.status,
                items: data?.qc?.items || qcState.items,
                summary: data?.qc?.summary || qcState.summary,
                audit: data?.qc?.audit || qcState.audit,
            };

            setStatus(el.qcStatus, data?.message || 'QC selesai.', 'success');
            playSuccessSound();
            renderQc();
        } catch (error) {
            showError(error.message || 'Gagal menyelesaikan QC.', error.details || []);
            setStatus(el.qcStatus, error.message || 'Gagal menyelesaikan QC.', 'error');
        } finally {
            qcActionBusy = false;
            renderQc();
        }
    };

    const resetQc = async () => {
        if (!qcState.id || qcActionBusy) return;

        let reason = '';
        if (typeof Swal !== 'undefined') {
            const result = await Swal.fire({
                title: 'Reset QC',
                text: 'Masukkan alasan reset untuk audit.',
                input: 'text',
                inputPlaceholder: 'Contoh: scan ganda / resi salah',
                inputAttributes: {
                    maxlength: '500',
                },
                showCancelButton: true,
                confirmButtonText: 'Reset',
                cancelButtonText: 'Batal',
                inputValidator: (value) => {
                    if (!value || !value.trim()) {
                        return 'Alasan reset wajib diisi.';
                    }
                    return null;
                },
            });

            if (!result.isConfirmed) {
                return;
            }

            reason = (result.value || '').trim();
        } else {
            reason = (window.prompt('Alasan reset QC:') || '').trim();
            if (!reason) {
                setStatus(el.qcStatus, 'Reset dibatalkan. Alasan wajib diisi.', 'error');
                return;
            }
        }

        qcActionBusy = true;
        renderQc();
        setStatus(el.qcStatus, 'Mereset QC...', 'pending');

        try {
            const data = await fetchJson(routes.reset, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ qc_id: qcState.id, reason, _token: csrfToken }),
            });

            qcState = {
                ...qcState,
                status: data?.qc?.status || qcState.status,
                items: data?.qc?.items || qcState.items,
                summary: data?.qc?.summary || qcState.summary,
                audit: data?.qc?.audit || qcState.audit,
            };

            setStatus(el.qcStatus, data?.message || 'QC direset.', 'success');
            renderQc();
        } catch (error) {
            showError(error.message || 'Gagal reset QC.', error.details || []);
            setStatus(el.qcStatus, error.message || 'Gagal reset QC.', 'error');
        } finally {
            qcActionBusy = false;
            renderQc();
        }
    };

    const stopScanner = () => {
        scannerActive = false;
        if (scanLoopId) {
            cancelAnimationFrame(scanLoopId);
            scanLoopId = null;
        }
        if (scannerStream) {
            scannerStream.getTracks().forEach((track) => track.stop());
            scannerStream = null;
        }
        if (html5Qr) {
            html5Qr.stop()
                .then(() => html5Qr.clear())
                .catch(() => {})
                .finally(() => {
                    html5Qr = null;
                });
        }
        el.scannerVideo.srcObject = null;
    };

    const closeScanner = () => {
        stopScanner();
        el.scannerModal.style.display = 'none';
        el.btnStartScan.disabled = false;
        el.scannerHint.textContent = 'Kamera aktif otomatis. Arahkan ke barcode.';
    };

    const openScanner = async (target) => {
        getAudioCtx();
        scanTarget = target;
        el.scannerTitle.textContent = target === 'sku' ? 'Scan SKU' : 'Scan Resi';

        if (!window.isSecureContext) {
            showError('Akses kamera membutuhkan HTTPS. Gunakan domain HTTPS atau localhost.');
            return;
        }

        const hasNative = 'BarcodeDetector' in window && !isIOS;
        const html5Ready = await loadHtml5Qr();
        const hasHtml5 = html5Ready && typeof Html5Qrcode !== 'undefined';

        if (!hasNative && !hasHtml5) {
            showError('Browser belum mendukung scan kamera. Gunakan input manual atau pastikan file html5-qrcode tersedia.');
            return;
        }

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            showError('Akses kamera tidak tersedia di browser ini. Gunakan input manual.');
            return;
        }

        scanMode = hasNative ? 'native' : 'html5';
        el.scannerVideo.style.display = scanMode === 'native' ? 'block' : 'none';
        el.scannerQr.style.display = scanMode === 'html5' ? 'block' : 'none';

        if (scanMode === 'native') {
            try {
                barcodeDetector = new BarcodeDetector({
                    formats: ['code_128', 'code_39', 'ean_13', 'ean_8', 'qr_code', 'upc_a', 'upc_e'],
                });
            } catch (error) {
                if (hasHtml5) {
                    scanMode = 'html5';
                    el.scannerVideo.style.display = 'none';
                    el.scannerQr.style.display = 'block';
                } else {
                    showError('Fitur scan tidak tersedia. Gunakan input manual.');
                    return;
                }
            }
        }

        el.scannerModal.style.display = 'flex';
        await startScanner();
    };

    const startScanner = async () => {
        if (scanMode === 'html5') {
            try {
                el.btnStartScan.disabled = true;
                el.scannerHint.textContent = 'Mengaktifkan kamera...';
                const config = {
                    fps: 10,
                    qrbox: { width: 250, height: 250 },
                };
                if (typeof Html5QrcodeSupportedFormats !== 'undefined') {
                    config.formatsToSupport = [
                        Html5QrcodeSupportedFormats.CODE_128,
                        Html5QrcodeSupportedFormats.CODE_39,
                        Html5QrcodeSupportedFormats.EAN_13,
                        Html5QrcodeSupportedFormats.EAN_8,
                        Html5QrcodeSupportedFormats.QR_CODE,
                        Html5QrcodeSupportedFormats.UPC_A,
                        Html5QrcodeSupportedFormats.UPC_E,
                    ];
                }

                html5Qr = new Html5Qrcode('scanner_qr');
                await html5Qr.start(
                    { facingMode: 'environment' },
                    config,
                    (decodedText) => {
                        if (decodedText) {
                            playScanSound();
                            if (scanTarget === 'sku') {
                                el.skuCode.value = decodedText;
                                el.skuCode.focus();
                            } else {
                                el.resiCode.value = decodedText;
                                el.resiCode.focus();
                            }
                            closeScanner();
                        }
                    },
                    () => {}
                );
                scannerActive = true;
                el.scannerHint.textContent = 'Scan berjalan. Arahkan ke barcode.';
                return;
            } catch (error) {
                stopScanner();
                el.btnStartScan.disabled = false;
                el.scannerHint.textContent = 'Gagal mengaktifkan kamera. Tekan Coba Lagi.';
                showError('Tidak bisa membuka kamera. Pastikan izin kamera aktif.');
                return;
            }
        }

        try {
            el.btnStartScan.disabled = true;
            el.scannerHint.textContent = 'Mengaktifkan kamera...';
            scannerStream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: { ideal: 'environment' },
                },
                audio: false,
            });
            el.scannerVideo.srcObject = scannerStream;
            await el.scannerVideo.play();
            scannerActive = true;
            el.scannerHint.textContent = 'Scan berjalan. Arahkan ke barcode.';
            scanLoop();
        } catch (error) {
            stopScanner();
            el.btnStartScan.disabled = false;
            el.scannerHint.textContent = 'Gagal mengaktifkan kamera. Tekan Coba Lagi.';
            showError('Tidak bisa membuka kamera. Pastikan izin kamera aktif.');
        }
    };

    const scanLoop = async () => {
        if (!scannerActive || !barcodeDetector) return;
        try {
            const barcodes = await barcodeDetector.detect(el.scannerVideo);
            if (Array.isArray(barcodes) && barcodes.length) {
                const code = barcodes[0].rawValue || '';
                if (code) {
                    playScanSound();
                    if (scanTarget === 'sku') {
                        el.skuCode.value = code;
                        el.skuCode.focus();
                    } else {
                        el.resiCode.value = code;
                        el.resiCode.focus();
                    }
                    closeScanner();
                    return;
                }
            }
        } catch (error) {
            // ignore frame errors
        }
        scanLoopId = requestAnimationFrame(scanLoop);
    };

    const scanFromPhoto = async (file) => {
        if (!file) return;

        setStatus(el.resiStatus, 'Memproses foto...', 'pending');
        const ready = await loadHtml5Qr();
        if (!ready || typeof Html5Qrcode === 'undefined') {
            showError('Library scan belum tersedia. Gunakan input manual.');
            setStatus(el.resiStatus, 'Scan foto gagal.', 'error');
            return;
        }

        try {
            closeScanner();
            const photoScanner = new Html5Qrcode('scanner_qr');
            const decodedText = await photoScanner.scanFile(file, true);
            await photoScanner.clear();
            playScanSound();
            el.resiCode.value = decodedText || '';
            el.resiCode.focus();
            setStatus(el.resiStatus, 'Hasil scan foto siap. Tekan Proses Resi.', 'success');
        } catch (error) {
            showError('Gagal membaca barcode dari foto. Pastikan barcode jelas dan tidak blur.');
            setStatus(el.resiStatus, 'Scan foto gagal.', 'error');
        } finally {
            el.scanPhotoInput.value = '';
        }
    };

    const updateScanAvailability = async () => {
        const hasNative = 'BarcodeDetector' in window && !isIOS;
        const html5Ready = await loadHtml5Qr();
        const hasHtml5 = html5Ready && typeof Html5Qrcode !== 'undefined';
        const canUseCamera = window.isSecureContext && navigator.mediaDevices && navigator.mediaDevices.getUserMedia;
        const supported = canUseCamera && (hasNative || hasHtml5);

        if (!supported) {
            if (el.btnOpenResiScanner) el.btnOpenResiScanner.style.display = 'none';
            if (el.btnOpenSkuScanner) el.btnOpenSkuScanner.style.display = 'none';
            if (el.photoScanWrap) el.photoScanWrap.style.display = 'none';
            setStatus(el.resiStatus, 'Scan kamera tidak tersedia. Gunakan input manual.', 'error');
            return;
        }
    };

    el.btnScanResi.addEventListener('click', submitResi);
    el.btnScanSku.addEventListener('click', submitSku);
    el.btnHoldQc.addEventListener('click', holdQc);
    el.btnResetQc.addEventListener('click', resetQc);
    el.btnCompleteQc.addEventListener('click', completeQc);
    el.btnOpenResiScanner.addEventListener('click', () => openScanner('resi'));
    el.btnOpenSkuScanner.addEventListener('click', () => openScanner('sku'));
    el.btnCloseScanner.addEventListener('click', closeScanner);
    el.btnStartScan.addEventListener('click', startScanner);

    if (el.photoScanWrap) {
        el.photoScanWrap.style.display = isIOS ? 'flex' : 'none';
    }
    el.btnScanPhoto.addEventListener('click', () => {
        el.scanPhotoInput.click();
    });
    el.scanPhotoInput.addEventListener('change', (event) => {
        const file = event.target.files && event.target.files[0];
        scanFromPhoto(file);
    });
    el.scannerModal.addEventListener('click', (event) => {
        if (event.target === el.scannerModal) {
            closeScanner();
        }
    });
    el.resiType.addEventListener('change', () => {
        const type = el.resiType.value;
        el.resiCode.placeholder = type === 'id_pesanan' ? 'Scan ID Pesanan' : 'Scan No. Resi';
        el.resiCode.focus();
    });
    el.resiCode.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            submitResi();
        }
    });
    el.skuCode.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            submitSku();
        }
    });

    updateScanAvailability();
</script>
@endsection
