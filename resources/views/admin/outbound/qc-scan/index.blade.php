@extends('layouts.admin')

@section('title', 'QC Scan Desktop')
@section('page_title', 'QC Scan Desktop')

@section('content')
<style>
    .qc-workbench {
        display: grid;
        gap: 24px;
    }
    .qc-topbar {
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 20px;
        background:
            radial-gradient(circle at top right, rgba(16, 185, 129, 0.10), transparent 30%),
            linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        padding: 20px 24px;
        box-shadow: 0 14px 36px rgba(15, 23, 42, 0.05);
        display: grid;
        gap: 14px;
    }
    .qc-topbar-main {
        display: flex;
        justify-content: space-between;
        gap: 18px;
        align-items: flex-start;
    }
    .qc-topbar-title {
        font-size: 24px;
        line-height: 1.2;
        font-weight: 800;
        color: #0f172a;
        margin-bottom: 6px;
    }
    .qc-topbar-subtitle {
        color: #475569;
        max-width: 720px;
        font-size: 14px;
        line-height: 1.6;
    }
    .qc-topbar-tools {
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
        justify-content: flex-end;
    }
    .qc-ghost-btn {
        border: 1px solid #cbd5e1;
        background: #fff;
        color: #0f172a;
        border-radius: 12px;
        padding: 10px 14px;
        font-size: 13px;
        font-weight: 700;
        line-height: 1;
    }
    .qc-ghost-btn:hover {
        background: #f8fafc;
    }
    .qc-help-panel {
        display: none;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
    }
    .qc-help-panel.is-open {
        display: grid;
    }
    .qc-help-card {
        border: 1px solid rgba(148, 163, 184, 0.22);
        border-radius: 16px;
        padding: 14px 16px;
        background: rgba(255, 255, 255, 0.86);
    }
    .qc-help-label {
        color: #64748b;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        margin-bottom: 8px;
        font-weight: 800;
    }
    .qc-help-value {
        color: #0f172a;
        font-size: 13px;
        line-height: 1.6;
        font-weight: 700;
    }
    .qc-layout {
        display: grid;
        grid-template-columns: 430px minmax(0, 1fr);
        gap: 24px;
        align-items: start;
    }
    .qc-panel {
        border: 1px solid #e2e8f0;
        border-radius: 20px;
        background: #ffffff;
        box-shadow: 0 12px 30px rgba(15, 23, 42, 0.06);
    }
    .qc-panel-header {
        padding: 20px 22px 16px;
        border-bottom: 1px solid #eef2f7;
    }
    .qc-panel-title {
        font-size: 16px;
        font-weight: 800;
        color: #0f172a;
        margin-bottom: 4px;
    }
    .qc-panel-desc {
        color: #64748b;
        font-size: 13px;
    }
    .qc-panel-body {
        padding: 22px;
        display: grid;
        gap: 18px;
    }
    .qc-field-grid {
        display: grid;
        gap: 14px;
    }
    .qc-field-label {
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #64748b;
        margin-bottom: 8px;
        display: block;
    }
    .qc-input,
    .qc-select,
    .qc-number {
        width: 100%;
        border: 1px solid #cbd5e1;
        background: #fff;
        border-radius: 16px;
        font-size: 24px;
        line-height: 1.2;
        font-weight: 800;
        color: #0f172a;
        padding: 18px 18px;
        transition: border-color .2s ease, box-shadow .2s ease, background-color .2s ease;
    }
    .qc-select {
        font-size: 15px;
        font-weight: 700;
        padding-top: 15px;
        padding-bottom: 15px;
    }
    .qc-number {
        font-size: 20px;
        text-align: center;
    }
    .qc-input:focus,
    .qc-select:focus,
    .qc-number:focus {
        outline: none;
        border-color: #0f766e;
        box-shadow: 0 0 0 5px rgba(15, 118, 110, 0.14);
    }
    .qc-input::placeholder {
        color: #94a3b8;
        font-weight: 700;
    }
    .qc-input-row {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 116px;
        gap: 12px;
        align-items: stretch;
    }
    .qc-primary-btn,
    .qc-secondary-btn,
    .qc-danger-btn {
        border: none;
        border-radius: 16px;
        font-size: 15px;
        font-weight: 800;
        padding: 15px 18px;
        transition: transform .15s ease, box-shadow .15s ease, opacity .15s ease;
    }
    .qc-primary-btn:hover,
    .qc-secondary-btn:hover,
    .qc-danger-btn:hover {
        transform: translateY(-1px);
    }
    .qc-primary-btn:disabled,
    .qc-secondary-btn:disabled,
    .qc-danger-btn:disabled {
        opacity: .6;
        cursor: not-allowed;
        transform: none;
    }
    .qc-primary-btn {
        background: linear-gradient(135deg, #0f766e, #14b8a6);
        color: #fff;
        box-shadow: 0 12px 24px rgba(15, 118, 110, 0.18);
    }
    .qc-secondary-btn {
        background: #eff6ff;
        color: #1d4ed8;
        border: 1px solid #bfdbfe;
    }
    .qc-danger-btn {
        background: #fff1f2;
        color: #be123c;
        border: 1px solid #fecdd3;
    }
    .qc-action-grid {
        display: grid;
        gap: 10px;
    }
    .qc-inline-tools {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    .qc-status-box {
        border-radius: 16px;
        padding: 14px 16px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        color: #475569;
        font-size: 13px;
        line-height: 1.6;
        min-height: 74px;
    }
    .qc-status-box.error {
        background: #fff1f2;
        border-color: #fecdd3;
        color: #be123c;
    }
    .qc-status-box.success {
        background: #ecfdf5;
        border-color: #a7f3d0;
        color: #047857;
    }
    .qc-status-box.pending {
        background: #fff7ed;
        border-color: #fed7aa;
        color: #c2410c;
    }
    .qc-log-panel {
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        background: #f8fafc;
        overflow: hidden;
    }
    .qc-log-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 12px 14px;
        border-bottom: 1px solid #e2e8f0;
    }
    .qc-log-title {
        font-size: 12px;
        font-weight: 800;
        color: #334155;
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }
    .qc-log-reset {
        border: none;
        background: transparent;
        color: #64748b;
        font-size: 12px;
        font-weight: 700;
        padding: 0;
    }
    .qc-log-list {
        display: grid;
        gap: 8px;
        padding: 12px 14px;
        max-height: 186px;
        overflow: auto;
    }
    .qc-log-empty {
        color: #94a3b8;
        font-size: 12px;
        line-height: 1.6;
    }
    .qc-log-item {
        border-radius: 12px;
        padding: 10px 12px;
        background: #fff;
        border: 1px solid #e2e8f0;
        display: grid;
        gap: 4px;
    }
    .qc-log-item.success {
        border-color: #a7f3d0;
        background: #f0fdf4;
    }
    .qc-log-item.error {
        border-color: #fecdd3;
        background: #fff1f2;
    }
    .qc-log-item.pending {
        border-color: #fed7aa;
        background: #fff7ed;
    }
    .qc-log-meta {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        align-items: center;
        font-size: 11px;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-weight: 700;
    }
    .qc-log-message {
        color: #0f172a;
        font-size: 13px;
        line-height: 1.5;
        font-weight: 700;
    }
    .qc-log-detail {
        color: #64748b;
        font-size: 12px;
        line-height: 1.5;
    }
    .qc-summary-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 14px;
    }
    .qc-summary-card {
        border-radius: 18px;
        padding: 16px 18px;
        background: #ffffff;
        border: 1px solid #e2e8f0;
    }
    .qc-summary-label {
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #64748b;
        font-weight: 700;
        margin-bottom: 8px;
    }
    .qc-summary-value {
        font-size: 28px;
        line-height: 1;
        font-weight: 800;
        color: #0f172a;
    }
    .qc-summary-note {
        margin-top: 8px;
        color: #64748b;
        font-size: 12px;
    }
    .qc-detail-card {
        border: 1px solid #e2e8f0;
        border-radius: 20px;
        background: #ffffff;
        box-shadow: 0 12px 30px rgba(15, 23, 42, 0.06);
        overflow: hidden;
    }
    .qc-detail-header {
        padding: 22px 24px 18px;
        display: flex;
        justify-content: space-between;
        gap: 16px;
        align-items: flex-start;
        border-bottom: 1px solid #eef2f7;
    }
    .qc-detail-title {
        font-size: 20px;
        font-weight: 800;
        color: #0f172a;
        margin-bottom: 6px;
    }
    .qc-detail-meta {
        font-size: 13px;
        color: #64748b;
        line-height: 1.7;
    }
    .qc-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 8px 14px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        white-space: nowrap;
    }
    .qc-badge.pending {
        background: #fff7ed;
        color: #c2410c;
    }
    .qc-badge.hold {
        background: #fff1f2;
        color: #be123c;
    }
    .qc-badge.passed {
        background: #ecfdf5;
        color: #047857;
    }
    .qc-detail-body {
        padding: 0;
    }
    .qc-table-wrap {
        overflow: auto;
    }
    .qc-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }
    .qc-table thead th {
        background: #f8fafc;
        color: #64748b;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        font-weight: 800;
        padding: 14px 18px;
        border-bottom: 1px solid #e2e8f0;
    }
    .qc-table tbody td {
        padding: 16px 18px;
        border-bottom: 1px solid #eef2f7;
        vertical-align: middle;
        font-size: 14px;
        color: #0f172a;
    }
    .qc-table tbody tr:last-child td {
        border-bottom: none;
    }
    .qc-item-sku {
        font-weight: 800;
        font-size: 15px;
    }
    .qc-item-target {
        color: #64748b;
        font-size: 12px;
        margin-top: 4px;
    }
    .qc-progress-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 76px;
        padding: 8px 12px;
        border-radius: 999px;
        font-size: 13px;
        font-weight: 800;
        background: #eff6ff;
        color: #1d4ed8;
    }
    .qc-progress-pill.done {
        background: #ecfdf5;
        color: #047857;
    }
    .qc-audit-grid {
        padding: 18px 24px 22px;
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px 20px;
        background: #fcfdff;
        border-top: 1px solid #eef2f7;
    }
    .qc-audit-grid.is-collapsed {
        display: none;
    }
    .qc-audit-item {
        color: #475569;
        font-size: 13px;
        line-height: 1.6;
    }
    .qc-audit-item strong {
        color: #0f172a;
        font-weight: 800;
    }
    .qc-empty {
        padding: 48px 24px;
        text-align: center;
        color: #64748b;
        font-size: 14px;
    }
    .qc-toolbar-links {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        align-items: center;
    }
    .qc-toolbar-links a {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 10px 14px;
        border-radius: 12px;
        background: #fff;
        border: 1px solid #dbeafe;
        color: #1d4ed8;
        font-size: 13px;
        font-weight: 700;
    }
    @media (max-width: 1400px) {
        .qc-layout {
            grid-template-columns: 390px minmax(0, 1fr);
        }
    }
    @media (max-width: 1200px) {
        .qc-topbar-main,
        .qc-layout {
            grid-template-columns: 1fr;
        }
        .qc-topbar-main {
            display: grid;
        }
        .qc-help-panel {
            grid-template-columns: 1fr;
        }
        .qc-summary-grid,
        .qc-audit-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="qc-workbench">
    <div class="qc-topbar">
        <div class="qc-topbar-main">
            <div>
                <div class="qc-topbar-title">Workbench QC untuk Scanner Desktop</div>
                <div class="qc-topbar-subtitle">
                    Area utama dibuat fokus untuk scan resi, scan SKU, dan selesaikan QC. Bantuan kerja dan detail audit tetap tersedia,
                    tetapi disimpan di panel yang bisa dibuka saat dibutuhkan supaya operator lebih nyaman sepanjang shift.
                </div>
            </div>
            <div class="qc-topbar-tools">
                <button type="button" class="qc-ghost-btn" id="btn_toggle_help">Bantuan Shortcut</button>
                <a href="{{ $routes['history'] }}" class="qc-ghost-btn">Riwayat QC</a>
                <a href="{{ $routes['scanOutHistory'] }}" class="qc-ghost-btn">Riwayat Scan Out</a>
            </div>
        </div>
        <div class="qc-help-panel" id="help_panel">
            <div class="qc-help-card">
                <div class="qc-help-label">Shortcut</div>
                <div class="qc-help-value">`F1` fokus resi, `F2` fokus SKU, `Ctrl + Enter` untuk selesaikan QC.</div>
            </div>
            <div class="qc-help-card">
                <div class="qc-help-label">Mode Scanner</div>
                <div class="qc-help-value">Scanner tipe keyboard bisa langsung dipakai. Setelah barcode terbaca, tekan `Enter` untuk kirim.</div>
            </div>
            <div class="qc-help-card">
                <div class="qc-help-label">Catatan</div>
                <div class="qc-help-value">Panel audit disembunyikan secara default agar fokus operator tetap ke area scan dan progres item.</div>
            </div>
        </div>
    </div>

    <div class="qc-summary-grid">
        <div class="qc-summary-card">
            <div class="qc-summary-label">Resi Aktif</div>
            <div class="qc-summary-value" id="summary_qc_id">-</div>
            <div class="qc-summary-note" id="summary_resi_line">Belum ada resi aktif.</div>
        </div>
        <div class="qc-summary-card">
            <div class="qc-summary-label">Progress Scan</div>
            <div class="qc-summary-value" id="summary_progress">0 / 0</div>
            <div class="qc-summary-note" id="summary_remaining">Sisa 0 qty.</div>
        </div>
        <div class="qc-summary-card">
            <div class="qc-summary-label">Status QC</div>
            <div class="qc-summary-value" id="summary_status">-</div>
            <div class="qc-summary-note" id="summary_audit">Menunggu resi pertama.</div>
        </div>
    </div>

    <div class="qc-layout">
        <div class="qc-panel">
            <div class="qc-panel-header">
                <div class="qc-panel-title">Input Scanner</div>
                <div class="qc-panel-desc">Area ini dibuat besar agar nyaman dipakai operator QC sepanjang shift.</div>
            </div>
            <div class="qc-panel-body">
                <div class="qc-field-grid">
                    <div>
                        <label class="qc-field-label" for="resi_type">Jenis Pencarian Resi</label>
                        <select class="qc-select" id="resi_type">
                            <option value="no_resi">No Resi</option>
                            <option value="id_pesanan">ID Pesanan</option>
                        </select>
                    </div>

                    <div>
                        <label class="qc-field-label" for="resi_code">Scan Resi</label>
                        <div class="qc-input-row">
                            <input type="text" class="qc-input" id="resi_code" placeholder="Scan No Resi lalu Enter" autocomplete="off" />
                            <button type="button" class="qc-primary-btn" id="btn_scan_resi">Proses</button>
                        </div>
                    </div>

                    <div class="qc-status-box" id="resi_status">
                        Siap menerima scan resi pertama.
                    </div>

                    <div>
                        <label class="qc-field-label" for="sku_code">Scan SKU</label>
                        <div class="qc-input-row">
                            <input type="text" class="qc-input" id="sku_code" placeholder="Scan SKU lalu Enter" autocomplete="off" />
                            <input type="number" class="qc-number" id="sku_qty" min="1" value="1" />
                        </div>
                    </div>

                    <button type="button" class="qc-primary-btn" id="btn_scan_sku">Kirim SKU</button>

                    <div class="qc-status-box" id="sku_status">
                        Menunggu resi aktif sebelum scan SKU.
                    </div>
                </div>

                <div class="qc-action-grid">
                    <button type="button" class="qc-secondary-btn" id="btn_hold_qc">Tunda QC</button>
                    <button type="button" class="qc-danger-btn" id="btn_reset_qc">Reset QC</button>
                    <button type="button" class="qc-primary-btn" id="btn_complete_qc">Selesaikan QC</button>
                </div>

                <div class="qc-toolbar-links">
                    <div class="qc-inline-tools">
                        <button type="button" class="qc-ghost-btn" id="btn_toggle_audit">Tampilkan Audit</button>
                    </div>
                </div>

                <div class="qc-status-box" id="qc_status">
                    Tidak ada QC aktif.
                </div>

                <div class="qc-log-panel">
                    <div class="qc-log-header">
                        <div class="qc-log-title">Log Aktivitas</div>
                        <button type="button" class="qc-log-reset" id="btn_clear_log">Bersihkan</button>
                    </div>
                    <div class="qc-log-list" id="activity_log">
                        <div class="qc-log-empty">Belum ada aktivitas scan.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="qc-detail-card">
            <div class="qc-detail-header">
                <div>
                    <div class="qc-detail-title" id="detail_title">Belum Ada Resi Aktif</div>
                    <div class="qc-detail-meta" id="detail_meta">
                        Scan resi terlebih dahulu untuk menampilkan detail SKU yang harus diperiksa.
                    </div>
                </div>
                <div class="qc-badge pending" id="detail_badge">Menunggu</div>
            </div>

            <div class="qc-detail-body">
                <div class="qc-table-wrap">
                    <table class="qc-table">
                        <thead>
                            <tr>
                                <th width="40%">SKU</th>
                                <th width="16%">Target</th>
                                <th width="16%">Terscan</th>
                                <th width="18%">Progress</th>
                                <th width="10%">Sisa</th>
                            </tr>
                        </thead>
                        <tbody id="detail_items">
                            <tr>
                                <td colspan="5" class="qc-empty">Belum ada item untuk ditampilkan.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="qc-audit-grid is-collapsed" id="detail_audit">
                    <div class="qc-audit-item"><strong>Petunjuk:</strong> fokus otomatis kembali ke input scanner agar operator tidak perlu klik ulang.</div>
                    <div class="qc-audit-item"><strong>Tip:</strong> gunakan qty jika scanner membaca satu SKU yang mewakili lebih dari satu unit.</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const routes = @json($routes);
    const csrfToken = '{{ csrf_token() }}';

    const el = {
        resiType: document.getElementById('resi_type'),
        resiCode: document.getElementById('resi_code'),
        btnScanResi: document.getElementById('btn_scan_resi'),
        resiStatus: document.getElementById('resi_status'),
        skuCode: document.getElementById('sku_code'),
        skuQty: document.getElementById('sku_qty'),
        btnScanSku: document.getElementById('btn_scan_sku'),
        skuStatus: document.getElementById('sku_status'),
        btnHoldQc: document.getElementById('btn_hold_qc'),
        btnResetQc: document.getElementById('btn_reset_qc'),
        btnCompleteQc: document.getElementById('btn_complete_qc'),
        qcStatus: document.getElementById('qc_status'),
        summaryQcId: document.getElementById('summary_qc_id'),
        summaryResiLine: document.getElementById('summary_resi_line'),
        summaryProgress: document.getElementById('summary_progress'),
        summaryRemaining: document.getElementById('summary_remaining'),
        summaryStatus: document.getElementById('summary_status'),
        summaryAudit: document.getElementById('summary_audit'),
        detailTitle: document.getElementById('detail_title'),
        detailMeta: document.getElementById('detail_meta'),
        detailBadge: document.getElementById('detail_badge'),
        detailItems: document.getElementById('detail_items'),
        detailAudit: document.getElementById('detail_audit'),
        btnToggleHelp: document.getElementById('btn_toggle_help'),
        helpPanel: document.getElementById('help_panel'),
        btnToggleAudit: document.getElementById('btn_toggle_audit'),
        activityLog: document.getElementById('activity_log'),
        btnClearLog: document.getElementById('btn_clear_log'),
    };

    let qcState = {
        id: null,
        status: null,
        items: [],
        summary: null,
        resi: null,
        audit: null,
    };

    let actionBusy = false;
    let helpOpen = false;
    let auditOpen = false;
    let activityLogEntries = [];
    let audioContext = null;

    const setStatusBox = (node, message, type = 'default') => {
        node.textContent = message;
        node.className = 'qc-status-box';
        if (type === 'error') node.classList.add('error');
        if (type === 'success') node.classList.add('success');
        if (type === 'pending') node.classList.add('pending');
    };

    const buildErrorMessage = (res, json) => {
        if (json?.message) return json.message;
        if (res.status === 419) return 'Sesi habis. Refresh halaman lalu coba lagi.';
        if (res.status === 403) return 'Akses ditolak.';
        if (res.status === 404) return 'Endpoint tidak ditemukan.';
        if (res.status >= 500) return 'Terjadi kesalahan server.';
        return 'Terjadi kesalahan.';
    };

    const fetchJson = async (url, payload) => {
        const res = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify(payload),
        });

        const text = await res.text();
        let json = null;
        try { json = JSON.parse(text); } catch (error) { json = null; }

        if (!res.ok) {
            const err = new Error(buildErrorMessage(res, json));
            err.status = res.status;
            err.details = json?.details || [];
            throw err;
        }

        return json;
    };

    const showError = (message, details = []) => {
        const detailText = Array.isArray(details) && details.length
            ? details.map((row) => {
                const parts = [];
                if (row.sku) parts.push(row.sku);
                if (row.required !== undefined) parts.push(`Target ${row.required}`);
                if (row.scanned !== undefined) parts.push(`Sudah ${row.scanned}`);
                if (row.available !== undefined) parts.push(`Tersedia ${row.available}`);
                if (row.attempt !== undefined) parts.push(`Input ${row.attempt}`);
                if (row.reason) parts.push(row.reason);
                return parts.join(' | ');
            }).join(' || ')
            : '';

        notify({
            label: 'Error',
            title: message,
            type: 'error',
            detail: detailText,
            tone: 'error',
        });
    };

    const askReason = async (title, placeholder, confirmButtonText) => {
        if (typeof Swal === 'undefined') {
            return (window.prompt(title) || '').trim();
        }

        const result = await Swal.fire({
            title,
            input: 'text',
            inputPlaceholder: placeholder,
            inputAttributes: { maxlength: '500' },
            showCancelButton: true,
            confirmButtonText,
            cancelButtonText: 'Batal',
            inputValidator: (value) => {
                if (!value || !value.trim()) {
                    return 'Alasan wajib diisi.';
                }
                return null;
            },
        });

        if (!result.isConfirmed) {
            return null;
        }

        return (result.value || '').trim();
    };

    const badgeClass = (status) => {
        if (status === 'passed') return 'passed';
        if (status === 'hold') return 'hold';
        return 'pending';
    };

    const badgeLabel = (status) => {
        if (status === 'passed') return 'Selesai';
        if (status === 'hold') return 'Ditunda';
        if (status === 'draft') return 'Proses';
        return 'Menunggu';
    };

    const focusResi = () => {
        window.setTimeout(() => {
            el.resiCode.focus();
            el.resiCode.select();
        }, 30);
    };

    const focusSku = () => {
        window.setTimeout(() => {
            el.skuCode.focus();
            el.skuCode.select();
        }, 30);
    };

    const nowLabel = () => {
        return new Date().toLocaleTimeString('id-ID', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
        });
    };

    const renderActivityLog = () => {
        if (!el.activityLog) return;

        if (!activityLogEntries.length) {
            el.activityLog.innerHTML = '<div class="qc-log-empty">Belum ada aktivitas scan.</div>';
            return;
        }

        el.activityLog.innerHTML = activityLogEntries.map((entry) => `
            <div class="qc-log-item ${entry.type}">
                <div class="qc-log-meta">
                    <span>${entry.label}</span>
                    <span>${entry.time}</span>
                </div>
                <div class="qc-log-message">${entry.message}</div>
                ${entry.detail ? `<div class="qc-log-detail">${entry.detail}</div>` : ''}
            </div>
        `).join('');
    };

    const pushActivity = (label, message, type = 'success', detail = '') => {
        activityLogEntries = [
            {
                label,
                message,
                type,
                detail,
                time: nowLabel(),
            },
            ...activityLogEntries,
        ].slice(0, 8);
        renderActivityLog();
    };

    const ensureAudioContext = async () => {
        if (!window.AudioContext && !window.webkitAudioContext) {
            return null;
        }
        if (!audioContext) {
            const Context = window.AudioContext || window.webkitAudioContext;
            audioContext = new Context();
        }
        if (audioContext.state === 'suspended') {
            await audioContext.resume();
        }
        return audioContext;
    };

    const playTone = async (tone) => {
        try {
            const context = await ensureAudioContext();
            if (!context) return;

            const gain = context.createGain();
            gain.connect(context.destination);
            gain.gain.setValueAtTime(0.0001, context.currentTime);

            const makeOsc = (frequency, start, duration) => {
                const osc = context.createOscillator();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(frequency, start);
                osc.connect(gain);
                osc.start(start);
                osc.stop(start + duration);
            };

            if (tone === 'success') {
                gain.gain.linearRampToValueAtTime(0.07, context.currentTime + 0.01);
                gain.gain.exponentialRampToValueAtTime(0.0001, context.currentTime + 0.18);
                makeOsc(880, context.currentTime, 0.08);
                makeOsc(1180, context.currentTime + 0.08, 0.08);
                return;
            }

            if (tone === 'complete') {
                gain.gain.linearRampToValueAtTime(0.08, context.currentTime + 0.01);
                gain.gain.exponentialRampToValueAtTime(0.0001, context.currentTime + 0.28);
                makeOsc(880, context.currentTime, 0.08);
                makeOsc(1174, context.currentTime + 0.08, 0.08);
                makeOsc(1568, context.currentTime + 0.16, 0.10);
                return;
            }

            gain.gain.linearRampToValueAtTime(0.07, context.currentTime + 0.01);
            gain.gain.exponentialRampToValueAtTime(0.0001, context.currentTime + 0.24);
            makeOsc(320, context.currentTime, 0.10);
            makeOsc(220, context.currentTime + 0.10, 0.10);
        } catch (error) {
            // Ignore audio failures so scan flow never blocks.
        }
    };

    const showToast = (title, message = '', type = 'success') => {
        if (typeof Swal === 'undefined') {
            return;
        }

        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: type,
            title,
            text: message || undefined,
            timer: type === 'error' ? 3200 : 1800,
            timerProgressBar: true,
            showConfirmButton: false,
            showCloseButton: false,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer);
                toast.addEventListener('mouseleave', Swal.resumeTimer);
            },
        });
    };

    const notify = ({ label, title, message = '', type = 'success', detail = '', tone = null }) => {
        pushActivity(label, title, type, detail || message);
        showToast(title, message, type);
        if (tone) {
            playTone(tone);
        }
    };

    const renderPanels = () => {
        if (el.helpPanel) {
            el.helpPanel.classList.toggle('is-open', helpOpen);
        }
        if (el.btnToggleHelp) {
            el.btnToggleHelp.textContent = helpOpen ? 'Sembunyikan Bantuan' : 'Bantuan Shortcut';
        }
        if (el.detailAudit) {
            el.detailAudit.classList.toggle('is-collapsed', !auditOpen);
        }
        if (el.btnToggleAudit) {
            el.btnToggleAudit.textContent = auditOpen ? 'Sembunyikan Audit' : 'Tampilkan Audit';
        }
    };

    const renderQc = () => {
        const qc = qcState;
        const summary = qc.summary || { total_expected: 0, total_scanned: 0, remaining: 0 };
        const audit = qc.audit || {};
        const hasQc = !!qc.id;

        el.summaryQcId.textContent = hasQc ? `#${qc.id}` : '-';
        el.summaryResiLine.textContent = hasQc
            ? `${qc.resi?.id_pesanan || '-'} | ${qc.resi?.no_resi || '-'}`
            : 'Belum ada resi aktif.';
        el.summaryProgress.textContent = `${summary.total_scanned || 0} / ${summary.total_expected || 0}`;
        el.summaryRemaining.textContent = `Sisa ${summary.remaining || 0} qty.`;
        el.summaryStatus.textContent = hasQc ? badgeLabel(qc.status) : '-';

        const auditLine = [];
        if (audit.started_by && audit.started_by !== '-') auditLine.push(`Mulai: ${audit.started_by}`);
        if (audit.last_scanned_by && audit.last_scanned_by !== '-' && audit.last_scanned_at) {
            auditLine.push(`Scan terakhir: ${audit.last_scanned_by} @ ${audit.last_scanned_at}`);
        }
        if (audit.completed_by && audit.completed_by !== '-') auditLine.push(`Selesai: ${audit.completed_by}`);
        if (audit.hold_reason) auditLine.push(`Hold: ${audit.hold_reason}`);
        if ((audit.reset_count || 0) > 0) auditLine.push(`Reset ${audit.reset_count}x`);
        el.summaryAudit.textContent = auditLine.length ? auditLine.join(' | ') : 'Menunggu resi pertama.';

        el.detailTitle.textContent = hasQc ? `QC ${qc.resi?.no_resi || qc.resi?.id_pesanan || '#' + qc.id}` : 'Belum Ada Resi Aktif';
        el.detailMeta.textContent = hasQc
            ? [
                qc.resi?.id_pesanan ? `ID Pesanan: ${qc.resi.id_pesanan}` : null,
                qc.resi?.no_resi ? `No Resi: ${qc.resi.no_resi}` : null,
                qc.resi?.tanggal_pesanan ? `Tanggal Order: ${qc.resi.tanggal_pesanan}` : null,
              ].filter(Boolean).join(' | ')
            : 'Scan resi terlebih dahulu untuk menampilkan detail SKU yang harus diperiksa.';
        el.detailBadge.textContent = hasQc ? badgeLabel(qc.status) : 'Menunggu';
        el.detailBadge.className = `qc-badge ${hasQc ? badgeClass(qc.status) : 'pending'}`;

        if (!hasQc || !(qc.items || []).length) {
            el.detailItems.innerHTML = '<tr><td colspan="5" class="qc-empty">Belum ada item untuk ditampilkan.</td></tr>';
        } else {
            el.detailItems.innerHTML = (qc.items || []).map((item) => {
                const expected = Number(item.expected_qty || 0);
                const scanned = Number(item.scanned_qty || 0);
                const remaining = Math.max(0, expected - scanned);
                const done = expected > 0 && scanned >= expected;
                return `
                    <tr>
                        <td>
                            <div class="qc-item-sku">${item.sku || '-'}</div>
                            <div class="qc-item-target">Target pemeriksaan SKU</div>
                        </td>
                        <td>${expected}</td>
                        <td>${scanned}</td>
                        <td><span class="qc-progress-pill ${done ? 'done' : ''}">${scanned}/${expected}</span></td>
                        <td>${remaining}</td>
                    </tr>
                `;
            }).join('');
        }

        el.detailAudit.innerHTML = hasQc ? `
            <div class="qc-audit-item"><strong>Mulai oleh:</strong> ${audit.started_by || '-'}</div>
            <div class="qc-audit-item"><strong>Scan terakhir:</strong> ${(audit.last_scanned_by || '-')}${audit.last_scanned_at ? ` @ ${audit.last_scanned_at}` : ''}</div>
            <div class="qc-audit-item"><strong>Hold:</strong> ${audit.hold_reason || '-'}</div>
            <div class="qc-audit-item"><strong>Reset:</strong> ${(audit.reset_count || 0)}x${audit.reset_reason ? ` | ${audit.reset_reason}` : ''}</div>
            <div class="qc-audit-item"><strong>Selesai oleh:</strong> ${audit.completed_by || '-'}</div>
            <div class="qc-audit-item"><strong>Status:</strong> ${badgeLabel(qc.status)}</div>
        ` : `
            <div class="qc-audit-item"><strong>Petunjuk:</strong> fokus otomatis kembali ke input scanner agar operator tidak perlu klik ulang.</div>
            <div class="qc-audit-item"><strong>Tip:</strong> gunakan qty jika scanner membaca satu SKU yang mewakili lebih dari satu unit.</div>
        `;

        const disableAction = !hasQc || actionBusy || qc.status === 'passed';
        el.btnHoldQc.disabled = disableAction;
        el.btnResetQc.disabled = disableAction;
        el.btnCompleteQc.disabled = disableAction || summary.remaining > 0;
    };

    const syncQcState = (payload) => {
        qcState = {
            ...qcState,
            id: payload?.qc?.id || qcState.id,
            status: payload?.qc?.status || qcState.status,
            items: payload?.qc?.items || qcState.items,
            summary: payload?.qc?.summary || qcState.summary,
            resi: payload?.resi || qcState.resi,
            audit: payload?.qc?.audit || qcState.audit,
        };
        renderQc();
    };

    const submitResi = async () => {
        const code = el.resiCode.value.trim();
        if (!code) {
            const message = 'Masukkan atau scan resi terlebih dahulu.';
            setStatusBox(el.resiStatus, message, 'error');
            notify({
                label: 'Resi',
                title: message,
                type: 'error',
                tone: 'error',
            });
            focusResi();
            return;
        }

        el.btnScanResi.disabled = true;
        setStatusBox(el.resiStatus, 'Memproses resi...', 'pending');

        try {
            const payload = await fetchJson(routes.scanResi, {
                type: el.resiType.value,
                code,
                _token: csrfToken,
            });

            qcState = {
                id: null,
                status: null,
                items: [],
                summary: null,
                resi: null,
                audit: null,
            };
            syncQcState(payload);

            setStatusBox(el.resiStatus, payload.message || 'Resi siap diproses QC.', 'success');
            setStatusBox(el.skuStatus, 'Resi aktif sudah siap. Lanjutkan scan SKU.', 'success');
            notify({
                label: 'Resi',
                title: payload.message || 'Resi siap diproses QC.',
                message: qcState.resi?.no_resi || qcState.resi?.id_pesanan || '',
                type: 'success',
                detail: qcState.resi?.id_pesanan && qcState.resi?.no_resi
                    ? `ID Pesanan: ${qcState.resi.id_pesanan} | No Resi: ${qcState.resi.no_resi}`
                    : '',
                tone: 'success',
            });
            el.resiCode.value = '';
            focusSku();
        } catch (error) {
            setStatusBox(el.resiStatus, error.message || 'Gagal memproses resi.', 'error');
            showError(error.message || 'Gagal memproses resi.', error.details || []);
            focusResi();
        } finally {
            el.btnScanResi.disabled = false;
        }
    };

    const submitSku = async () => {
        if (!qcState.id) {
            const message = 'Belum ada resi aktif. Scan resi lebih dulu.';
            setStatusBox(el.skuStatus, message, 'error');
            notify({
                label: 'SKU',
                title: message,
                type: 'error',
                tone: 'error',
            });
            focusResi();
            return;
        }

        const code = el.skuCode.value.trim();
        const qty = parseInt(el.skuQty.value || '1', 10);
        if (!code) {
            const message = 'Masukkan atau scan SKU terlebih dahulu.';
            setStatusBox(el.skuStatus, message, 'error');
            notify({
                label: 'SKU',
                title: message,
                type: 'error',
                tone: 'error',
            });
            focusSku();
            return;
        }
        if (!qty || qty <= 0) {
            const message = 'Qty minimal 1.';
            setStatusBox(el.skuStatus, message, 'error');
            notify({
                label: 'SKU',
                title: message,
                type: 'error',
                tone: 'error',
            });
            el.skuQty.focus();
            return;
        }

        el.btnScanSku.disabled = true;
        setStatusBox(el.skuStatus, 'Memproses SKU...', 'pending');

        try {
            const payload = await fetchJson(routes.scanSku, {
                qc_id: qcState.id,
                code,
                qty,
                _token: csrfToken,
            });

            syncQcState(payload);
            setStatusBox(el.skuStatus, payload.message || 'SKU berhasil diproses.', 'success');
            notify({
                label: 'SKU',
                title: payload.message || 'SKU berhasil diproses.',
                message: code,
                type: 'success',
                detail: `Qty ${qty}`,
                tone: 'success',
            });
            el.skuCode.value = '';
            el.skuQty.value = '1';
            focusSku();
        } catch (error) {
            setStatusBox(el.skuStatus, error.message || 'Gagal memproses SKU.', 'error');
            showError(error.message || 'Gagal memproses SKU.', error.details || []);
            focusSku();
        } finally {
            el.btnScanSku.disabled = false;
        }
    };

    const holdQc = async () => {
        if (!qcState.id || actionBusy) return;

        const reason = await askReason('Simpan dan tunda QC', 'Contoh: pemeriksaan fisik belum lengkap', 'Simpan');
        if (!reason) return;

        actionBusy = true;
        renderQc();
        setStatusBox(el.qcStatus, 'Menyimpan QC sebagai hold...', 'pending');

        try {
            const payload = await fetchJson(routes.hold, {
                qc_id: qcState.id,
                reason,
                _token: csrfToken,
            });

            syncQcState(payload);
            setStatusBox(el.qcStatus, payload.message || 'QC berhasil ditunda.', 'success');
            notify({
                label: 'QC',
                title: payload.message || 'QC berhasil ditunda.',
                message: qcState.resi?.no_resi || '',
                type: 'success',
                detail: reason,
                tone: 'success',
            });
            focusSku();
        } catch (error) {
            setStatusBox(el.qcStatus, error.message || 'Gagal menunda QC.', 'error');
            showError(error.message || 'Gagal menunda QC.', error.details || []);
        } finally {
            actionBusy = false;
            renderQc();
        }
    };

    const resetQc = async () => {
        if (!qcState.id || actionBusy) return;

        const reason = await askReason('Reset QC aktif', 'Contoh: resi salah / scan ganda', 'Reset');
        if (!reason) return;

        actionBusy = true;
        renderQc();
        setStatusBox(el.qcStatus, 'Mereset QC...', 'pending');

        try {
            const payload = await fetchJson(routes.reset, {
                qc_id: qcState.id,
                reason,
                _token: csrfToken,
            });

            syncQcState(payload);
            setStatusBox(el.qcStatus, payload.message || 'QC berhasil direset.', 'success');
            notify({
                label: 'QC',
                title: payload.message || 'QC berhasil direset.',
                message: qcState.resi?.no_resi || '',
                type: 'success',
                detail: reason,
                tone: 'success',
            });
            focusSku();
        } catch (error) {
            setStatusBox(el.qcStatus, error.message || 'Gagal reset QC.', 'error');
            showError(error.message || 'Gagal reset QC.', error.details || []);
        } finally {
            actionBusy = false;
            renderQc();
        }
    };

    const completeQc = async () => {
        if (!qcState.id || actionBusy) return;

        actionBusy = true;
        renderQc();
        setStatusBox(el.qcStatus, 'Menyelesaikan QC...', 'pending');

        try {
            const payload = await fetchJson(routes.complete, {
                qc_id: qcState.id,
                _token: csrfToken,
            });

            syncQcState(payload);
            setStatusBox(el.qcStatus, payload.message || 'QC selesai.', 'success');
            notify({
                label: 'QC',
                title: payload.message || 'QC selesai.',
                message: qcState.resi?.no_resi || '',
                type: 'success',
                detail: 'Resi siap ke tahap scan out.',
                tone: 'complete',
            });
            focusResi();
        } catch (error) {
            setStatusBox(el.qcStatus, error.message || 'Gagal menyelesaikan QC.', 'error');
            showError(error.message || 'Gagal menyelesaikan QC.', error.details || []);
        } finally {
            actionBusy = false;
            renderQc();
        }
    };

    document.addEventListener('keydown', (event) => {
        if (event.key === 'F1') {
            event.preventDefault();
            focusResi();
            return;
        }
        if (event.key === 'F2') {
            event.preventDefault();
            focusSku();
            return;
        }
        if (event.key === 'Enter' && document.activeElement === el.resiCode) {
            event.preventDefault();
            submitResi();
            return;
        }
        if (event.key === 'Enter' && (document.activeElement === el.skuCode || document.activeElement === el.skuQty)) {
            event.preventDefault();
            submitSku();
            return;
        }
        if (event.ctrlKey && event.key === 'Enter') {
            event.preventDefault();
            completeQc();
            return;
        }
    });

    el.resiType.addEventListener('change', () => {
        el.resiCode.placeholder = el.resiType.value === 'id_pesanan'
            ? 'Scan ID Pesanan lalu Enter'
            : 'Scan No Resi lalu Enter';
        focusResi();
    });

    el.btnScanResi.addEventListener('click', submitResi);
    el.btnScanSku.addEventListener('click', submitSku);
    el.btnHoldQc.addEventListener('click', holdQc);
    el.btnResetQc.addEventListener('click', resetQc);
    el.btnCompleteQc.addEventListener('click', completeQc);
    if (el.btnToggleHelp) {
        el.btnToggleHelp.addEventListener('click', () => {
            helpOpen = !helpOpen;
            renderPanels();
        });
    }
    if (el.btnToggleAudit) {
        el.btnToggleAudit.addEventListener('click', () => {
            auditOpen = !auditOpen;
            renderPanels();
        });
    }
    if (el.btnClearLog) {
        el.btnClearLog.addEventListener('click', () => {
            activityLogEntries = [];
            renderActivityLog();
        });
    }

    renderQc();
    renderPanels();
    renderActivityLog();
    focusResi();
</script>
@endpush
