@extends('layouts.mobile')

@section('title', 'Scan Inbound')

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
    .search-results,
    .result-items {
        display: grid;
        gap: 10px;
        margin-top: 10px;
    }
    .search-item,
    .result-item {
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 12px;
        background: #fff;
    }
    .search-item {
        display: grid;
        gap: 8px;
    }
    .search-top,
    .result-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
    }
    .result-card {
        display: none;
    }
    .result-badge {
        padding: 4px 10px;
        border-radius: 999px;
        background: rgba(249, 115, 22, 0.15);
        color: #c2410c;
        font-weight: 700;
        font-size: 11px;
    }
    .result-badge.done {
        background: rgba(34, 197, 94, 0.16);
        color: #15803d;
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
    .result-row {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        align-items: center;
    }
    .result-row strong {
        display: block;
        margin-bottom: 4px;
    }
    .result-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 10px;
    }
    .result-actions .ghost-btn,
    .result-actions .primary-btn {
        width: auto;
        padding: 10px 12px;
        font-size: 12px;
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
    .scanner-actions .primary-btn,
    .scanner-actions .ghost-btn {
        width: auto;
        padding: 10px 12px;
        font-size: 12px;
    }
</style>

<div class="screen">
    <div class="topbar">
        <div>
            <div class="brand">{{ config('app.name', 'Gudang 24') }}</div>
            <div class="subtitle">Scan Inbound</div>
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
        <div class="section-title">Pilih Inbound</div>
        <div class="muted">Cari berdasarkan kode inbound, ref no, atau nomor surat jalan.</div>
        <div class="scan-actions">
            <div class="scan-row">
                <input type="text" class="input" id="search_query" placeholder="Cari inbound..." autocomplete="off" />
                <button type="button" class="scan-btn" id="btn_search_inbound">Cari</button>
            </div>
        </div>
        <div class="status-line" id="search_status">Menunggu pencarian inbound.</div>
        <div class="search-results" id="search_results"></div>
    </div>

    <div class="card">
        <div class="section-title">Scan SKU</div>
        <div class="muted">Setiap scan SKU dihitung sebagai 1 koli.</div>
        <div class="scan-actions">
            <div class="scan-row">
                <input type="text" class="input" id="sku_code" placeholder="Scan SKU" autocomplete="off" />
                <button type="button" class="scan-btn" id="btn_open_scanner">Scan</button>
            </div>
            <div class="photo-scan" id="photo_scan_wrap">
                <button type="button" class="photo-btn" id="btn_scan_photo">Scan via Foto</button>
                <span class="muted">Alternatif untuk iPhone.</span>
            </div>
            <input type="file" id="scan_photo" accept="image/*" capture="environment" style="display:none;" />
            <button type="button" class="primary-btn" id="btn_scan_sku">Tambah 1 Koli</button>
        </div>
        <div class="status-line" id="scan_status">Pilih inbound terlebih dahulu.</div>
    </div>

    <div class="card result-card" id="result_card">
        <div class="result-top">
            <div>
                <div style="font-weight:700;" id="result_title">Inbound Aktif</div>
                <div class="result-meta" id="result_meta">-</div>
            </div>
            <div class="result-badge" id="result_badge">Sedang Scan</div>
        </div>
        <div class="result-meta" id="result_summary">-</div>
        <div class="result-items" id="result_items"></div>
        <div class="result-actions">
            <button type="button" class="ghost-btn" id="btn_reset_inbound">Reset Scan</button>
            <button type="button" class="primary-btn" id="btn_complete_inbound">Complete Inbound</button>
        </div>
        <div class="status-line" id="result_status">-</div>
    </div>
</div>

<div class="scanner-modal" id="scanner_modal">
    <div class="scanner-card">
        <div style="font-weight:700;">Kamera Scanner SKU</div>
        <video class="scanner-video" id="scanner_video" playsinline></video>
        <div class="scanner-qr" id="scanner_qr"></div>
        <div class="scanner-actions">
            <button type="button" class="ghost-btn" id="btn_close_scanner">Tutup</button>
            <button type="button" class="primary-btn" id="btn_start_scan">Coba Lagi</button>
        </div>
        <div class="muted" id="scanner_hint">Kamera aktif otomatis. Arahkan ke barcode SKU.</div>
    </div>
</div>

<script>
    const routes = @json($routes);
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    const el = {
        searchQuery: document.getElementById('search_query'),
        btnSearchInbound: document.getElementById('btn_search_inbound'),
        searchStatus: document.getElementById('search_status'),
        searchResults: document.getElementById('search_results'),
        skuCode: document.getElementById('sku_code'),
        btnOpenScanner: document.getElementById('btn_open_scanner'),
        btnScanPhoto: document.getElementById('btn_scan_photo'),
        scanPhotoInput: document.getElementById('scan_photo'),
        photoScanWrap: document.getElementById('photo_scan_wrap'),
        btnScanSku: document.getElementById('btn_scan_sku'),
        scanStatus: document.getElementById('scan_status'),
        resultCard: document.getElementById('result_card'),
        resultTitle: document.getElementById('result_title'),
        resultMeta: document.getElementById('result_meta'),
        resultBadge: document.getElementById('result_badge'),
        resultSummary: document.getElementById('result_summary'),
        resultItems: document.getElementById('result_items'),
        resultStatus: document.getElementById('result_status'),
        btnResetInbound: document.getElementById('btn_reset_inbound'),
        btnCompleteInbound: document.getElementById('btn_complete_inbound'),
        scannerModal: document.getElementById('scanner_modal'),
        scannerVideo: document.getElementById('scanner_video'),
        scannerQr: document.getElementById('scanner_qr'),
        btnCloseScanner: document.getElementById('btn_close_scanner'),
        btnStartScan: document.getElementById('btn_start_scan'),
        scannerHint: document.getElementById('scanner_hint'),
    };

    const state = {
        transaction: null,
        searching: false,
        scanning: false,
        completing: false,
        resetting: false,
    };

    let scannerStream = null;
    let scannerActive = false;
    let barcodeDetector = null;
    let scanLoopId = null;
    let html5Qr = null;
    let scanMode = 'native';
    let html5LoadPromise = null;
    const isIOS = (() => {
        const ua = navigator.userAgent || '';
        const platform = navigator.platform || '';
        const isAppleMobile = /iPad|iPhone|iPod/.test(ua);
        const isIpadOs = platform === 'MacIntel' && navigator.maxTouchPoints > 1;
        return isAppleMobile || isIpadOs;
    })();

    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const setStatus = (target, text, type = 'muted') => {
        if (!target) return;
        target.textContent = text;
        if (type === 'error') {
            target.style.color = '#b91c1c';
        } else if (type === 'success') {
            target.style.color = '#047857';
        } else if (type === 'pending') {
            target.style.color = '#f97316';
        } else {
            target.style.color = '#6b7280';
        }
    };

    const buildErrorMessage = (res, json) => {
        if (json?.message) return json.message;
        if (res.status === 419) return 'Sesi habis. Refresh halaman lalu coba lagi.';
        if (res.status === 403) return 'Akses ditolak.';
        if (res.status === 404) return 'Endpoint tidak ditemukan.';
        if (res.status >= 500) return 'Terjadi kesalahan server. Coba lagi.';
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
        try { json = JSON.parse(text); } catch (error) { json = null; }

        if (!res.ok) {
            const err = new Error(buildErrorMessage(res, json));
            err.details = json?.details || [];
            throw err;
        }

        return json;
    };

    const showError = (message, details = []) => {
        if (typeof Swal !== 'undefined') {
            let html = `<div style="text-align:left; font-size:13px;">${escapeHtml(message)}</div>`;
            if (Array.isArray(details) && details.length) {
                const items = details.map((row) => {
                    const sku = escapeHtml(row.sku || '-');
                    const expectedKoli = row.expected_koli ?? row.required ?? '-';
                    const scannedKoli = row.scanned_koli ?? row.scanned ?? '-';
                    const expectedQty = row.expected_qty ?? '-';
                    const scannedQty = row.scanned_qty ?? '-';
                    return `
                        <li style="margin-bottom:8px;">
                            <strong>${sku}</strong>
                            <div style="color:#64748b; font-size:12px;">Koli ${scannedKoli}/${expectedKoli}</div>
                            ${expectedQty !== '-' ? `<div style="color:#64748b; font-size:12px;">Qty ${scannedQty}/${expectedQty}</div>` : ''}
                        </li>
                    `;
                }).join('');
                html += `<ul style="text-align:left; padding-left:18px; margin-top:8px;">${items}</ul>`;
            }

            Swal.fire({
                icon: 'error',
                title: 'Gagal',
                html,
            });
            return;
        }

        setStatus(el.resultStatus, message, 'error');
    };

    const typeLabel = (type) => {
        if (type === 'receipt') return 'Penerimaan Barang';
        if (type === 'return') return 'Retur';
        if (type === 'manual') return 'Manual';
        return type || '-';
    };

    const statusLabel = (status) => {
        if (status === 'completed') return 'Selesai';
        if (status === 'scanning') return 'Sedang Scan';
        return 'Menunggu Scan';
    };

    const renderSearchResults = (transactions = []) => {
        if (!Array.isArray(transactions) || transactions.length === 0) {
            el.searchResults.innerHTML = '<div class="muted">Tidak ada inbound yang cocok.</div>';
            return;
        }

        el.searchResults.innerHTML = transactions.map((row) => {
            const summary = row.summary || {};
            const meta = [
                row.ref_no ? `Ref: ${escapeHtml(row.ref_no)}` : null,
                row.surat_jalan_no ? `SJ: ${escapeHtml(row.surat_jalan_no)}` : null,
                row.transacted_at ? `Tanggal: ${escapeHtml(row.transacted_at)}` : null,
            ].filter(Boolean).join(' • ');

            return `
                <div class="search-item">
                    <div class="search-top">
                        <div>
                            <strong>${escapeHtml(row.code || '-')}</strong>
                            <div class="result-meta">${escapeHtml(typeLabel(row.type))}</div>
                        </div>
                        <div class="count-pill">${escapeHtml(statusLabel(row.status))}</div>
                    </div>
                    <div class="result-meta">${meta || '-'}</div>
                    <div class="result-meta">Koli ${summary.scanned_koli || 0}/${summary.expected_koli || 0} • Qty ${summary.scanned_qty || 0}/${summary.expected_qty || 0}</div>
                    <button type="button" class="primary-btn btn-open-transaction" data-id="${row.id}" style="width:auto;">Pilih Inbound</button>
                </div>
            `;
        }).join('');
    };

    const renderTransaction = (transaction) => {
        state.transaction = transaction || null;
        if (!transaction) {
            el.resultCard.style.display = 'none';
            el.btnCompleteInbound.disabled = true;
            el.btnResetInbound.disabled = true;
            el.btnScanSku.disabled = true;
            el.btnOpenScanner.disabled = true;
            return;
        }

        const summary = transaction.summary || {};
        const session = transaction.session || {};
        const audit = session.audit || {};
        const items = Array.isArray(transaction.items) ? transaction.items : [];
        const meta = [
            transaction.ref_no ? `Ref: ${transaction.ref_no}` : null,
            transaction.surat_jalan_no ? `SJ: ${transaction.surat_jalan_no}` : null,
            transaction.surat_jalan_at ? `Tgl SJ: ${transaction.surat_jalan_at}` : null,
            transaction.transacted_at ? `Inbound: ${transaction.transacted_at}` : null,
        ].filter(Boolean).join(' • ');

        el.resultTitle.textContent = transaction.code || 'Inbound Aktif';
        el.resultMeta.textContent = meta || '-';
        el.resultBadge.textContent = statusLabel(transaction.status);
        el.resultBadge.className = `result-badge${transaction.status === 'completed' ? ' done' : ''}`;
        el.resultSummary.textContent = `Koli ${summary.scanned_koli || 0}/${summary.expected_koli || 0} • Qty ${summary.scanned_qty || 0}/${summary.expected_qty || 0}`;
        el.resultItems.innerHTML = items.map((row) => {
            const done = (row.scanned_koli || 0) >= (row.expected_koli || 0);
            return `
                <div class="result-item">
                    <div class="result-row">
                        <div>
                            <strong>${escapeHtml(row.sku || '-')}</strong>
                            <div class="result-meta">${escapeHtml(row.item_name || '-')} • ${row.qty_per_koli || 0} pcs/koli</div>
                            <div class="result-meta">Qty ${row.scanned_qty || 0}/${row.expected_qty || 0} • Koli ${row.scanned_koli || 0}/${row.expected_koli || 0}</div>
                        </div>
                        <div class="count-pill${done ? ' done' : ''}">${done ? 'Done' : 'Progress'}</div>
                    </div>
                </div>
            `;
        }).join('');
        el.resultCard.style.display = 'block';
        const isCompleted = transaction.status === 'completed';
        el.btnCompleteInbound.disabled = isCompleted;
        el.btnResetInbound.disabled = isCompleted;
        el.btnScanSku.disabled = isCompleted;
        el.btnOpenScanner.disabled = isCompleted;

        const footer = [];
        if (session.started_at) footer.push(`Mulai: ${session.started_at} oleh ${audit.started_by || '-'}`);
        if (audit.last_scanned_at) footer.push(`Scan terakhir: ${audit.last_scanned_at} oleh ${audit.last_scanned_by || '-'}`);
        if (session.completed_at) footer.push(`Selesai: ${session.completed_at} oleh ${audit.completed_by || '-'}`);
        if (audit.reset_count > 0) footer.push(`Reset ${audit.reset_count}x${audit.reset_reason ? ` • ${audit.reset_reason}` : ''}`);
        setStatus(el.resultStatus, footer.join(' | ') || 'Inbound siap discan.', transaction.status === 'completed' ? 'success' : 'muted');
    };

    const searchTransactions = async () => {
        if (state.searching) return;
        state.searching = true;
        el.btnSearchInbound.disabled = true;
        setStatus(el.searchStatus, 'Mencari inbound...', 'pending');

        try {
            const query = (el.searchQuery.value || '').trim();
            const url = new URL(routes.search, window.location.origin);
            if (query) url.searchParams.set('query', query);
            const data = await fetchJson(url.toString());
            renderSearchResults(data.transactions || []);
            setStatus(el.searchStatus, 'Daftar inbound siap dipilih.', 'success');
        } catch (error) {
            el.searchResults.innerHTML = '';
            setStatus(el.searchStatus, error.message || 'Gagal mencari inbound.', 'error');
            showError(error.message || 'Gagal mencari inbound.');
        } finally {
            state.searching = false;
            el.btnSearchInbound.disabled = false;
        }
    };

    const openTransaction = async (transactionId) => {
        setStatus(el.searchStatus, 'Membuka inbound...', 'pending');
        try {
            const data = await fetchJson(routes.open, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ transaction_id: transactionId, _token: csrfToken }),
            });
            renderTransaction(data.transaction || null);
            setStatus(el.searchStatus, data.message || 'Inbound siap discan.', 'success');
            setStatus(el.scanStatus, 'Scan SKU untuk menambah 1 koli.', 'muted');
            el.skuCode.focus();
        } catch (error) {
            showError(error.message || 'Gagal membuka inbound.', error.details || []);
            setStatus(el.searchStatus, error.message || 'Gagal membuka inbound.', 'error');
        }
    };

    const submitScanSku = async () => {
        if (state.scanning) return;
        if (!state.transaction?.session?.id) {
            setStatus(el.scanStatus, 'Pilih inbound terlebih dahulu.', 'error');
            return;
        }

        const code = (el.skuCode.value || '').trim();
        if (!code) {
            setStatus(el.scanStatus, 'Scan atau input SKU terlebih dahulu.', 'error');
            el.skuCode.focus();
            return;
        }

        state.scanning = true;
        el.btnScanSku.disabled = true;
        setStatus(el.scanStatus, 'Memproses scan SKU...', 'pending');

        try {
            const data = await fetchJson(routes.scanSku, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    session_id: state.transaction.session.id,
                    code,
                    _token: csrfToken,
                }),
            });
            renderTransaction(data.transaction || null);
            el.skuCode.value = '';
            el.skuCode.focus();
            setStatus(el.scanStatus, data.message || 'SKU berhasil discan.', 'success');
        } catch (error) {
            showError(error.message || 'Gagal scan SKU.', error.details || []);
            setStatus(el.scanStatus, error.message || 'Gagal scan SKU.', 'error');
        } finally {
            state.scanning = false;
            el.btnScanSku.disabled = false;
        }
    };

    const completeInbound = async () => {
        if (state.completing) return;
        if (!state.transaction?.session?.id) {
            setStatus(el.resultStatus, 'Pilih inbound terlebih dahulu.', 'error');
            return;
        }

        state.completing = true;
        el.btnCompleteInbound.disabled = true;
        setStatus(el.resultStatus, 'Menyelesaikan scan inbound...', 'pending');

        try {
            const data = await fetchJson(routes.complete, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    session_id: state.transaction.session.id,
                    _token: csrfToken,
                }),
            });
            renderTransaction(data.transaction || null);
            setStatus(el.resultStatus, data.message || 'Inbound selesai.', 'success');
            searchTransactions();
        } catch (error) {
            showError(error.message || 'Gagal menyelesaikan inbound.', error.details || []);
            setStatus(el.resultStatus, error.message || 'Gagal menyelesaikan inbound.', 'error');
        } finally {
            state.completing = false;
            el.btnCompleteInbound.disabled = false;
        }
    };

    const resetInbound = async () => {
        if (state.resetting) return;
        if (!state.transaction?.session?.id) {
            setStatus(el.resultStatus, 'Pilih inbound terlebih dahulu.', 'error');
            return;
        }

        let reason = window.prompt('Alasan reset scan inbound:', '');
        if (reason === null) return;
        reason = reason.trim();
        if (!reason) {
            setStatus(el.resultStatus, 'Alasan reset wajib diisi.', 'error');
            return;
        }

        state.resetting = true;
        el.btnResetInbound.disabled = true;
        setStatus(el.resultStatus, 'Mereset scan inbound...', 'pending');

        try {
            const data = await fetchJson(routes.reset, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    session_id: state.transaction.session.id,
                    reason,
                    _token: csrfToken,
                }),
            });
            renderTransaction(data.transaction || null);
            setStatus(el.resultStatus, data.message || 'Scan inbound berhasil direset.', 'success');
        } catch (error) {
            showError(error.message || 'Gagal reset scan inbound.', error.details || []);
            setStatus(el.resultStatus, error.message || 'Gagal reset scan inbound.', 'error');
        } finally {
            state.resetting = false;
            el.btnResetInbound.disabled = false;
        }
    };

    const loadHtml5Qr = () => {
        if (typeof Html5Qrcode !== 'undefined') return Promise.resolve(true);
        if (html5LoadPromise) return html5LoadPromise;

        const sources = [
            '{{ asset('vendor/html5-qrcode.min.js') }}',
            'https://unpkg.com/html5-qrcode@2.3.10/minified/html5-qrcode.min.js',
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
        el.scannerHint.textContent = 'Kamera aktif otomatis. Arahkan ke barcode SKU.';
    };

    const handleDetectedCode = (code) => {
        if (!code) return;
        el.skuCode.value = code;
        el.skuCode.focus();
        closeScanner();
        submitScanSku();
    };

    const scanLoop = async () => {
        if (!scannerActive || !barcodeDetector) return;
        try {
            const barcodes = await barcodeDetector.detect(el.scannerVideo);
            if (Array.isArray(barcodes) && barcodes.length) {
                const code = barcodes[0].rawValue || '';
                if (code) {
                    handleDetectedCode(code);
                    return;
                }
            }
        } catch (error) {
            // Ignore frame-level errors.
        }
        scanLoopId = requestAnimationFrame(scanLoop);
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
                        if (decodedText) handleDetectedCode(decodedText);
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
                video: { facingMode: { ideal: 'environment' } },
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

    const openScanner = async () => {
        if (!state.transaction?.session?.id) {
            setStatus(el.scanStatus, 'Pilih inbound terlebih dahulu.', 'error');
            return;
        }
        if (!window.isSecureContext) {
            showError('Akses kamera membutuhkan HTTPS. Gunakan domain HTTPS atau localhost.');
            return;
        }

        const hasNative = 'BarcodeDetector' in window && !isIOS;
        const html5Ready = await loadHtml5Qr();
        const hasHtml5 = html5Ready && typeof Html5Qrcode !== 'undefined';

        if (!hasNative && !hasHtml5) {
            showError('Browser belum mendukung scan kamera. Gunakan input manual atau foto.');
            return;
        }

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            showError('Akses kamera tidak tersedia di browser ini.');
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

    const scanFromPhoto = async (file) => {
        if (!file) return;
        if (!state.transaction?.session?.id) {
            setStatus(el.scanStatus, 'Pilih inbound terlebih dahulu.', 'error');
            return;
        }

        setStatus(el.scanStatus, 'Memproses foto...', 'pending');
        const ready = await loadHtml5Qr();
        if (!ready || typeof Html5Qrcode === 'undefined') {
            showError('Library scan belum tersedia. Gunakan input manual.');
            setStatus(el.scanStatus, 'Scan foto gagal.', 'error');
            return;
        }

        try {
            closeScanner();
            const photoScanner = new Html5Qrcode('scanner_qr');
            const decodedText = await photoScanner.scanFile(file, true);
            await photoScanner.clear();
            el.skuCode.value = decodedText || '';
            el.skuCode.focus();
            setStatus(el.scanStatus, 'Hasil scan foto siap diproses.', 'success');
            submitScanSku();
        } catch (error) {
            showError('Gagal membaca barcode dari foto. Pastikan barcode jelas dan tidak blur.');
            setStatus(el.scanStatus, 'Scan foto gagal.', 'error');
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
            if (el.btnOpenScanner) el.btnOpenScanner.style.display = 'none';
            if (el.photoScanWrap) el.photoScanWrap.style.display = 'none';
            setStatus(el.scanStatus, 'Scan kamera tidak tersedia. Gunakan input manual.', 'error');
            return;
        }

        if (el.photoScanWrap) {
            el.photoScanWrap.style.display = isIOS ? 'flex' : 'none';
        }
    };

    el.btnSearchInbound.addEventListener('click', searchTransactions);
    el.searchQuery.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            searchTransactions();
        }
    });
    el.searchResults.addEventListener('click', (event) => {
        const btn = event.target.closest('.btn-open-transaction');
        if (!btn) return;
        openTransaction(btn.getAttribute('data-id'));
    });
    el.btnScanSku.addEventListener('click', submitScanSku);
    el.skuCode.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            submitScanSku();
        }
    });
    el.btnCompleteInbound.addEventListener('click', completeInbound);
    el.btnResetInbound.addEventListener('click', resetInbound);
    el.btnOpenScanner.addEventListener('click', openScanner);
    el.btnCloseScanner.addEventListener('click', closeScanner);
    el.btnStartScan.addEventListener('click', startScanner);
    el.btnScanPhoto.addEventListener('click', () => el.scanPhotoInput.click());
    el.scanPhotoInput.addEventListener('change', (event) => {
        const file = event.target.files && event.target.files[0];
        scanFromPhoto(file);
    });
    el.scannerModal.addEventListener('click', (event) => {
        if (event.target === el.scannerModal) closeScanner();
    });

    renderTransaction(null);
    searchTransactions();
    updateScanAvailability();
</script>
@endsection
