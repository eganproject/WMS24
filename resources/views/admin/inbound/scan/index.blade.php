@extends('layouts.admin')

@section('title', 'Scan Inbound')
@section('page_title', 'Scan Inbound')

@section('content')
<style>
    .inbound-scan-page {
        display: grid;
        gap: 16px;
    }
    .scan-hero {
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        background: #fff;
        box-shadow: 0 8px 20px rgba(15, 23, 42, 0.04);
        padding: 18px 20px;
        display: grid;
        gap: 14px;
    }
    .scan-hero-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 18px;
    }
    .scan-hero-title {
        font-size: 20px;
        line-height: 1.25;
        font-weight: 800;
        color: #0f172a;
        margin-bottom: 4px;
    }
    .scan-hero-subtitle {
        max-width: 820px;
        color: #475569;
        font-size: 13px;
        line-height: 1.5;
    }
    .scan-hero-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        justify-content: flex-end;
    }
    .scan-hero-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        min-height: 44px;
        padding: 0 16px;
        border-radius: 8px;
        border: 1px solid #cbd5e1;
        background: #fff;
        color: #0f172a;
        font-size: 13px;
        font-weight: 700;
    }
    .scan-hero-link:hover {
        background: #f8fafc;
    }
    .scan-hero-hints {
        display: none;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
    }
    .scan-hero-hints.is-open {
        display: grid;
    }
    .scan-hint-card {
        border-radius: 8px;
        border: 1px solid rgba(148, 163, 184, 0.22);
        background: rgba(255, 255, 255, 0.88);
        padding: 14px 16px;
    }
    .scan-hint-label {
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #64748b;
        margin-bottom: 8px;
    }
    .scan-hint-value {
        color: #0f172a;
        font-size: 13px;
        line-height: 1.6;
        font-weight: 700;
    }
    .scan-summary-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(150px, 1fr));
        gap: 12px;
    }
    .scan-summary-card {
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        background: #fff;
        box-shadow: 0 8px 20px rgba(15, 23, 42, 0.04);
        padding: 14px 16px;
        min-width: 0;
    }
    .scan-summary-label {
        color: #64748b;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        margin-bottom: 8px;
    }
    .scan-summary-value {
        color: #0f172a;
        font-size: 24px;
        line-height: 1;
        font-weight: 800;
        word-break: break-word;
    }
    .scan-summary-note {
        margin-top: 8px;
        color: #64748b;
        font-size: 12px;
        line-height: 1.6;
    }
    .scan-layout {
        display: grid;
        grid-template-columns: minmax(320px, 380px) minmax(0, 1fr);
        gap: 16px;
        align-items: start;
    }
    .scan-panel,
    .scan-detail-card {
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        background: #fff;
        box-shadow: 0 8px 20px rgba(15, 23, 42, 0.04);
        overflow: hidden;
    }
    .scan-panel {
        position: sticky;
        top: 90px;
    }
    .scan-panel-header,
    .scan-detail-header {
        padding: 16px 18px 14px;
        border-bottom: 1px solid #eef2f7;
    }
    .scan-panel-title,
    .scan-detail-title {
        color: #0f172a;
        font-size: 16px;
        font-weight: 800;
        margin-bottom: 6px;
    }
    .scan-panel-desc,
    .scan-detail-meta {
        color: #64748b;
        font-size: 13px;
        line-height: 1.6;
    }
    .scan-panel-body {
        padding: 16px;
        display: grid;
        gap: 14px;
    }
    .scan-field-label {
        display: block;
        margin-bottom: 8px;
        color: #64748b;
        font-size: 12px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }
    .scan-search-row,
    .scan-sku-row {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 104px;
        gap: 10px;
        align-items: stretch;
    }
    .scan-input {
        width: 100%;
        min-height: 54px;
        border-radius: 8px;
        border: 1px solid #cbd5e1;
        background: #fff;
        padding: 14px 16px;
        color: #0f172a;
        font-size: 20px;
        line-height: 1.2;
        font-weight: 800;
        transition: border-color .2s ease, box-shadow .2s ease, transform .2s ease;
    }
    .scan-input::placeholder {
        color: #94a3b8;
        font-weight: 700;
    }
    .scan-input:focus {
        outline: none;
        border-color: #0284c7;
        box-shadow: 0 0 0 5px rgba(2, 132, 199, 0.12);
    }
    .scan-btn,
    .scan-btn-secondary,
    .scan-btn-danger {
        min-height: 58px;
        border: none;
        border-radius: 8px;
        padding: 12px 14px;
        font-size: 14px;
        font-weight: 800;
        transition: transform .15s ease, box-shadow .15s ease, opacity .15s ease;
    }
    .scan-btn:hover,
    .scan-btn-secondary:hover,
    .scan-btn-danger:hover {
        transform: translateY(-1px);
    }
    .scan-btn:disabled,
    .scan-btn-secondary:disabled,
    .scan-btn-danger:disabled {
        opacity: .6;
        cursor: not-allowed;
        transform: none;
    }
    .scan-btn {
        color: #fff;
        background: linear-gradient(135deg, #0284c7, #0ea5e9);
        box-shadow: 0 12px 24px rgba(2, 132, 199, 0.18);
    }
    .scan-btn-secondary {
        color: #0f172a;
        border: 1px solid #cbd5e1;
        background: #f8fafc;
    }
    .scan-btn-danger {
        color: #be123c;
        border: 1px solid #fecdd3;
        background: #fff1f2;
    }
    .scan-status-box {
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
        color: #475569;
        font-size: 13px;
        line-height: 1.6;
        padding: 14px 16px;
        min-height: 52px;
    }
    .scan-status-box.success {
        background: #ecfdf5;
        border-color: #a7f3d0;
        color: #047857;
    }
    .scan-status-box.error {
        background: #fff1f2;
        border-color: #fecdd3;
        color: #be123c;
    }
    .scan-status-box.pending {
        background: #fff7ed;
        border-color: #fed7aa;
        color: #c2410c;
    }
    .scan-search-list {
        display: grid;
        gap: 8px;
        max-height: 340px;
        overflow: auto;
    }
    .scan-search-empty {
        border: 1px dashed #cbd5e1;
        border-radius: 8px;
        padding: 18px;
        text-align: center;
        color: #64748b;
        font-size: 13px;
    }
    .scan-search-item {
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        background: #fff;
        padding: 12px 14px;
        display: grid;
        gap: 10px;
        transition: border-color .2s ease, box-shadow .2s ease, transform .2s ease;
    }
    .scan-search-item:hover {
        border-color: #7dd3fc;
        box-shadow: 0 12px 24px rgba(2, 132, 199, 0.08);
        transform: translateY(-1px);
    }
    .scan-search-top {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        align-items: flex-start;
    }
    .scan-search-code {
        color: #0f172a;
        font-size: 15px;
        font-weight: 800;
        margin-bottom: 4px;
    }
    .scan-search-meta,
    .scan-search-progress {
        color: #64748b;
        font-size: 12px;
        line-height: 1.6;
    }
    .scan-search-progress {
        font-weight: 700;
    }
    .scan-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 7px 12px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        white-space: nowrap;
    }
    .scan-badge.pending {
        background: #fff7ed;
        color: #c2410c;
    }
    .scan-badge.scanning {
        background: #eff6ff;
        color: #1d4ed8;
    }
    .scan-badge.completed {
        background: #ecfdf5;
        color: #047857;
    }
    .scan-search-open {
        width: 100%;
    }
    .scan-action-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
    }
    .scan-action-grid #btn_complete {
        grid-column: 1 / -1;
    }
    .scan-log-panel {
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
        overflow: hidden;
    }
    .scan-log-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        padding: 12px 14px;
        border-bottom: 1px solid #e2e8f0;
    }
    .scan-log-title {
        color: #334155;
        font-size: 12px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }
    .scan-log-clear {
        border: none;
        background: transparent;
        color: #64748b;
        font-size: 12px;
        font-weight: 700;
    }
    .scan-log-list {
        display: grid;
        gap: 8px;
        padding: 12px 14px;
        max-height: 190px;
        overflow: auto;
    }
    .scan-log-empty {
        color: #94a3b8;
        font-size: 12px;
        line-height: 1.6;
    }
    .scan-log-item {
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        background: #fff;
        padding: 10px 12px;
        display: grid;
        gap: 4px;
    }
    .scan-log-item.success {
        border-color: #a7f3d0;
        background: #f0fdf4;
    }
    .scan-log-item.error {
        border-color: #fecdd3;
        background: #fff1f2;
    }
    .scan-log-item.pending {
        border-color: #fed7aa;
        background: #fff7ed;
    }
    .scan-log-meta {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        align-items: center;
        color: #64748b;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    .scan-log-message {
        color: #0f172a;
        font-size: 13px;
        line-height: 1.5;
        font-weight: 700;
    }
    .scan-log-detail {
        color: #64748b;
        font-size: 12px;
        line-height: 1.5;
    }
    .scan-detail-header {
        display: flex;
        justify-content: space-between;
        gap: 16px;
        align-items: flex-start;
    }
    .scan-detail-meta {
        max-width: 860px;
    }
    .scan-detail-body {
        padding: 0;
    }
    .scan-detail-toolbar {
        padding: 14px 18px 0;
        display: flex;
        justify-content: space-between;
        gap: 12px;
        align-items: center;
        flex-wrap: wrap;
    }
    .scan-detail-status {
        color: #475569;
        font-size: 13px;
        line-height: 1.6;
    }
    .scan-table-wrap {
        overflow: auto;
        padding: 14px 18px 18px;
    }
    .scan-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        min-width: 760px;
    }
    .scan-table thead th {
        background: #f8fafc;
        color: #64748b;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        padding: 12px 14px;
        border-bottom: 1px solid #e2e8f0;
    }
    .scan-table tbody td {
        padding: 14px;
        border-bottom: 1px solid #eef2f7;
        color: #0f172a;
        font-size: 14px;
        vertical-align: middle;
    }
    .scan-table tbody tr:last-child td {
        border-bottom: none;
    }
    .scan-item-sku {
        color: #0f172a;
        font-size: 15px;
        font-weight: 800;
        margin-bottom: 4px;
    }
    .scan-item-name {
        color: #64748b;
        font-size: 12px;
        line-height: 1.5;
    }
    .scan-progress-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 90px;
        padding: 8px 12px;
        border-radius: 999px;
        font-size: 13px;
        font-weight: 800;
        background: #eff6ff;
        color: #1d4ed8;
    }
    .scan-progress-pill.match {
        background: #ecfdf5;
        color: #047857;
    }
    .scan-progress-pill.over {
        background: #fff7ed;
        color: #c2410c;
    }
    .scan-audit-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px 20px;
        padding: 0 18px 18px;
    }
    .scan-audit-item {
        color: #475569;
        font-size: 13px;
        line-height: 1.6;
    }
    .scan-audit-item strong {
        color: #0f172a;
        font-weight: 800;
    }
    .scan-empty {
        padding: 40px 18px;
        text-align: center;
        color: #64748b;
        font-size: 14px;
    }
    @media (max-width: 1400px) {
        .scan-layout {
            grid-template-columns: 360px minmax(0, 1fr);
        }
    }
    @media (max-width: 1200px) {
        .scan-hero-top,
        .scan-layout {
            display: grid;
            grid-template-columns: 1fr;
        }
        .scan-hero-hints,
        .scan-summary-grid,
        .scan-audit-grid {
            grid-template-columns: 1fr;
        }
        .scan-panel {
            position: static;
        }
    }
    @media (max-width: 767.98px) {
        .inbound-scan-page {
            gap: 12px;
        }
        .scan-hero,
        .scan-panel-body,
        .scan-panel-header,
        .scan-detail-header,
        .scan-table-wrap,
        .scan-detail-toolbar,
        .scan-audit-grid {
            padding-left: 16px;
            padding-right: 16px;
        }
        .scan-hero {
            padding-top: 14px;
            padding-bottom: 14px;
        }
        .scan-hero-top {
            gap: 12px;
        }
        .scan-hero-title {
            font-size: 18px;
        }
        .scan-hero-subtitle {
            font-size: 12px;
        }
        .scan-summary-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }
        .scan-summary-card {
            padding: 12px;
        }
        .scan-summary-label {
            font-size: 10px;
            letter-spacing: 0.05em;
        }
        .scan-summary-value {
            font-size: 20px;
        }
        .scan-summary-note {
            font-size: 11px;
        }
        .scan-search-row,
        .scan-sku-row {
            grid-template-columns: 1fr;
        }
        .scan-input {
            min-height: 52px;
            font-size: 18px;
        }
        .scan-btn,
        .scan-btn-secondary,
        .scan-btn-danger {
            min-height: 48px;
            font-size: 13px;
        }
        .scan-detail-header,
        .scan-search-top {
            display: grid;
        }
        .scan-hero-actions {
            justify-content: stretch;
        }
        .scan-hero-link {
            width: 100%;
        }
        .scan-action-grid {
            grid-template-columns: 1fr;
        }
        .scan-action-grid #btn_complete {
            grid-column: auto;
        }
        .scan-search-list {
            max-height: 300px;
        }
        .scan-log-list {
            max-height: 160px;
        }
        .scan-table {
            min-width: 0;
            border-spacing: 0 10px;
        }
        .scan-table thead {
            display: none;
        }
        .scan-table tbody,
        .scan-table tr,
        .scan-table td {
            display: block;
            width: 100%;
        }
        .scan-table tbody tr {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #fff;
            overflow: hidden;
        }
        .scan-table tbody td {
            display: grid;
            grid-template-columns: 104px minmax(0, 1fr);
            gap: 12px;
            align-items: start;
            padding: 12px 14px;
            border-bottom: 1px solid #eef2f7;
            word-break: break-word;
        }
        .scan-table tbody td::before {
            content: attr(data-label);
            color: #64748b;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }
        .scan-table tbody td.scan-empty {
            display: block;
            border: 0;
        }
        .scan-table tbody td.scan-empty::before {
            content: '';
        }
    }
    @media (max-width: 420px) {
        .scan-summary-grid {
            grid-template-columns: 1fr;
        }
        .scan-table tbody td {
            grid-template-columns: 1fr;
            gap: 4px;
        }
    }
</style>

<div class="inbound-scan-page">
    <div class="scan-hero">
        <div class="scan-hero-top">
            <div>
                <div class="scan-hero-title">Workbench Scan Inbound</div>
                <div class="scan-hero-subtitle">
                    Halaman ini disiapkan untuk operator inbound yang memakai scanner barcode seperti keyboard.
                    Fokus utama ada di pencarian inbound, area scan SKU berukuran besar, progres koli per SKU, dan kontrol reset atau complete yang cepat tetapi tetap aman.
                </div>
            </div>
            <div class="scan-hero-actions">
                <button type="button" class="scan-hero-link" id="btn_toggle_help">
                    <i class="fas fa-keyboard"></i>
                    <span>Bantuan Shortcut</span>
                </button>
                <a href="{{ $routes['receipts'] }}" class="scan-hero-link">
                    <i class="fas fa-dolly"></i>
                    <span>Daftar Penerimaan</span>
                </a>
            </div>
        </div>
        <div class="scan-hero-hints" id="help_panel">
            <div class="scan-hint-card">
                <div class="scan-hint-label">Shortcut</div>
                <div class="scan-hint-value">`F1` fokus pencarian inbound, `F2` fokus scan SKU, `Ctrl + Enter` untuk complete inbound.</div>
            </div>
            <div class="scan-hint-card">
                <div class="scan-hint-label">Mode Kerja</div>
                <div class="scan-hint-value">Scanner tipe keyboard bisa langsung dipakai. Setelah barcode terbaca, cukup kirim `Enter`.</div>
            </div>
            <div class="scan-hint-card">
                <div class="scan-hint-label">Catatan</div>
                <div class="scan-hint-value">Setiap 1 kali scan SKU dihitung sebagai 1 koli, mengikuti alur inbound scan yang sudah ada di aplikasi.</div>
            </div>
        </div>
    </div>

    <div class="scan-summary-grid">
        <div class="scan-summary-card">
            <div class="scan-summary-label">Inbound Aktif</div>
            <div class="scan-summary-value" id="summary_code">-</div>
            <div class="scan-summary-note" id="summary_meta">Belum ada inbound aktif.</div>
        </div>
        <div class="scan-summary-card">
            <div class="scan-summary-label">Progress Koli</div>
            <div class="scan-summary-value" id="summary_koli">0 / 0</div>
            <div class="scan-summary-note" id="summary_remaining">Sisa 0 koli.</div>
        </div>
        <div class="scan-summary-card">
            <div class="scan-summary-label">Progress Qty</div>
            <div class="scan-summary-value" id="summary_qty">0 / 0</div>
            <div class="scan-summary-note" id="summary_qty_note">Belum ada scan SKU.</div>
        </div>
        <div class="scan-summary-card">
            <div class="scan-summary-label">Status</div>
            <div class="scan-summary-value" id="summary_status">-</div>
            <div class="scan-summary-note" id="summary_audit">Menunggu inbound dipilih.</div>
        </div>
    </div>

    <div class="scan-layout">
        <div class="scan-panel">
            <div class="scan-panel-header">
                <div class="scan-panel-title">Panel Scanner</div>
                <div class="scan-panel-desc">Area kiri dipakai operator sepanjang shift untuk pilih inbound, scan SKU, dan kontrol aksi.</div>
            </div>
            <div class="scan-panel-body">
                <div>
                    <label class="scan-field-label" for="search_query">Cari Inbound</label>
                    <div class="scan-search-row">
                        <input type="text" class="scan-input" id="search_query" placeholder="Scan kode inbound / ref / surat jalan" autocomplete="off" />
                        <button type="button" class="scan-btn" id="btn_search">Cari</button>
                    </div>
                </div>

                <div class="scan-status-box" id="search_status">Menunggu pencarian inbound.</div>

                <div class="scan-search-list" id="search_results">
                    <div class="scan-search-empty">Belum ada daftar inbound.</div>
                </div>

                <div>
                    <label class="scan-field-label" for="sku_code">Scan SKU</label>
                    <div class="scan-sku-row">
                        <input type="text" class="scan-input" id="sku_code" placeholder="Scan SKU lalu Enter" autocomplete="off" />
                        <button type="button" class="scan-btn" id="btn_scan_sku">Scan</button>
                    </div>
                </div>

                <div class="scan-status-box" id="scan_status">Pilih inbound aktif sebelum mulai scan SKU.</div>

                <div class="scan-action-grid">
                    <button type="button" class="scan-btn-secondary" id="btn_reset">Reset Scan</button>
                    <button type="button" class="scan-btn" id="btn_complete">Complete Inbound</button>
                    <button type="button" class="scan-btn-danger" id="btn_refresh">Refresh Daftar</button>
                </div>

                <div class="scan-status-box" id="workbench_status">Tidak ada inbound aktif.</div>

                <div class="scan-log-panel">
                    <div class="scan-log-header">
                        <div class="scan-log-title">Log Aktivitas</div>
                        <button type="button" class="scan-log-clear" id="btn_clear_log">Bersihkan</button>
                    </div>
                    <div class="scan-log-list" id="activity_log">
                        <div class="scan-log-empty">Belum ada aktivitas scan.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="scan-detail-card">
            <div class="scan-detail-header">
                <div>
                    <div class="scan-detail-title" id="detail_title">Belum Ada Inbound Aktif</div>
                    <div class="scan-detail-meta" id="detail_meta">
                        Pilih inbound dari panel kiri untuk melihat daftar SKU, audit scan, dan progres inbound.
                    </div>
                </div>
                <div class="scan-badge pending" id="detail_badge">Menunggu</div>
            </div>

            <div class="scan-detail-body">
                <div class="scan-detail-toolbar">
                    <div class="scan-detail-status" id="detail_status">Belum ada progress scan.</div>
                </div>

                <div class="scan-table-wrap">
                    <table class="scan-table">
                        <thead>
                            <tr>
                                <th>SKU</th>
                                <th>Qty / Koli</th>
                                <th>Target</th>
                                <th>Hasil Scan</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="detail_items">
                            <tr>
                                <td colspan="5" class="scan-empty">Belum ada item untuk ditampilkan.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="scan-audit-grid" id="detail_audit">
                    <div class="scan-audit-item"><strong>Petunjuk:</strong> fokus akan tetap diarahkan ke input utama supaya operator tidak perlu klik berulang.</div>
                    <div class="scan-audit-item"><strong>Alur:</strong> inbound dibuka dulu, lalu scan SKU per koli sampai complete.</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const routes = @json($routes);
    const csrfToken = @json(csrf_token());

    const el = {
        searchQuery: document.getElementById('search_query'),
        btnSearch: document.getElementById('btn_search'),
        searchStatus: document.getElementById('search_status'),
        searchResults: document.getElementById('search_results'),
        skuCode: document.getElementById('sku_code'),
        btnScanSku: document.getElementById('btn_scan_sku'),
        scanStatus: document.getElementById('scan_status'),
        btnReset: document.getElementById('btn_reset'),
        btnComplete: document.getElementById('btn_complete'),
        btnRefresh: document.getElementById('btn_refresh'),
        workbenchStatus: document.getElementById('workbench_status'),
        detailTitle: document.getElementById('detail_title'),
        detailMeta: document.getElementById('detail_meta'),
        detailBadge: document.getElementById('detail_badge'),
        detailStatus: document.getElementById('detail_status'),
        detailItems: document.getElementById('detail_items'),
        detailAudit: document.getElementById('detail_audit'),
        summaryCode: document.getElementById('summary_code'),
        summaryMeta: document.getElementById('summary_meta'),
        summaryKoli: document.getElementById('summary_koli'),
        summaryRemaining: document.getElementById('summary_remaining'),
        summaryQty: document.getElementById('summary_qty'),
        summaryQtyNote: document.getElementById('summary_qty_note'),
        summaryStatus: document.getElementById('summary_status'),
        summaryAudit: document.getElementById('summary_audit'),
        activityLog: document.getElementById('activity_log'),
        btnClearLog: document.getElementById('btn_clear_log'),
        btnToggleHelp: document.getElementById('btn_toggle_help'),
        helpPanel: document.getElementById('help_panel'),
    };

    const state = {
        transaction: null,
        searching: false,
        opening: false,
        scanning: false,
        completing: false,
        resetting: false,
    };

    let logEntries = [];
    let audioContext = null;
    let helpOpen = false;

    const focusSearch = () => el.searchQuery?.focus();
    const focusSku = () => el.skuCode?.focus();

    const renderPanels = () => {
        if (el.helpPanel) {
            el.helpPanel.classList.toggle('is-open', helpOpen);
        }
        if (el.btnToggleHelp) {
            const label = el.btnToggleHelp.querySelector('span');
            if (label) {
                label.textContent = helpOpen ? 'Sembunyikan Bantuan' : 'Bantuan Shortcut';
            } else {
                el.btnToggleHelp.textContent = helpOpen ? 'Sembunyikan Bantuan' : 'Bantuan Shortcut';
            }
        }
    };

    const nowLabel = () => new Date().toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });

    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const setStatusBox = (target, message, type = 'muted') => {
        if (!target) return;
        target.textContent = message;
        target.className = `scan-status-box${type === 'muted' ? '' : ` ${type}`}`;
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

    const badgeClass = (status) => {
        if (status === 'completed') return 'completed';
        if (status === 'scanning') return 'scanning';
        return 'pending';
    };

    const buildErrorMessage = (res, json) => {
        if (json?.message) return json.message;
        if (res.status === 419) return 'Sesi habis. Refresh halaman lalu coba lagi.';
        if (res.status === 403) return 'Akses ditolak.';
        if (res.status >= 500) return 'Terjadi kesalahan server. Coba lagi.';
        return 'Terjadi kesalahan.';
    };

    const fetchJson = async (url, options = {}) => {
        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                ...(options.headers || {}),
            },
            ...options,
        });

        const text = await response.text();
        let json = null;
        try {
            json = JSON.parse(text);
        } catch (error) {
            json = null;
        }

        if (!response.ok) {
            const err = new Error(buildErrorMessage(response, json));
            err.status = response.status;
            err.details = json?.details || [];
            err.payload = json;
            throw err;
        }

        return json;
    };

    const buildDetailsHtml = (message, details = []) => {
        let html = `<div style="text-align:left; font-size:13px;">${escapeHtml(message || '')}</div>`;
        if (Array.isArray(details) && details.length) {
            const rows = details.map((row) => {
                const diffKoli = Number(row.diff_koli || 0);
                const diffQty = Number(row.diff_qty || 0);
                const diffLine = diffKoli || diffQty
                    ? `<div style="color:#c2410c; font-size:12px;">Selisih koli ${diffKoli > 0 ? '+' : ''}${diffKoli} | qty ${diffQty > 0 ? '+' : ''}${diffQty}</div>`
                    : '';
                let badge = '', rowStyle = '';
                if (row.type === 'not_received') {
                    badge = '<span style="background:#fee2e2;color:#b91c1c;font-size:11px;padding:1px 6px;border-radius:4px;font-weight:700;margin-right:4px;">TIDAK DITERIMA</span>';
                    rowStyle = 'border-left:3px solid #ef4444;padding-left:6px;';
                } else if (row.type === 'over') {
                    badge = '<span style="background:#dbeafe;color:#1d4ed8;font-size:11px;padding:1px 6px;border-radius:4px;font-weight:700;margin-right:4px;">LEBIH</span>';
                    rowStyle = 'border-left:3px solid #3b82f6;padding-left:6px;';
                } else if (row.type === 'under') {
                    badge = '<span style="background:#ffedd5;color:#c2410c;font-size:11px;padding:1px 6px;border-radius:4px;font-weight:700;margin-right:4px;">KURANG</span>';
                    rowStyle = 'border-left:3px solid #f97316;padding-left:6px;';
                }
                return `
                    <li style="margin-bottom:8px;${rowStyle}">
                        ${badge}<strong>${escapeHtml(row.sku || '-')}</strong>
                        <div style="color:#64748b; font-size:12px;">Koli ${row.scanned_koli ?? 0}/${row.expected_koli ?? 0}</div>
                        <div style="color:#64748b; font-size:12px;">Qty ${row.scanned_qty ?? 0}/${row.expected_qty ?? 0}</div>
                        ${diffLine}
                    </li>
                `;
            }).join('');
            html += `<ul style="text-align:left; padding-left:18px; margin-top:8px;">${rows}</ul>`;
        }
        return html;
    };

    const showError = (message, details = []) => {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'error',
                title: 'Gagal',
                html: buildDetailsHtml(message, details),
                allowOutsideClick: false,
            });
            return;
        }

        window.alert(message);
    };

    const askReason = async (title, placeholder, confirmText) => {
        if (typeof Swal !== 'undefined') {
            const result = await Swal.fire({
                title,
                input: 'text',
                inputPlaceholder: placeholder,
                inputAttributes: { maxlength: 500 },
                showCancelButton: true,
                confirmButtonText: confirmText,
                cancelButtonText: 'Batal',
                allowOutsideClick: false,
                inputValidator: (value) => {
                    if (!String(value || '').trim()) {
                        return 'Alasan wajib diisi.';
                    }
                    return null;
                },
            });

            return result.isConfirmed ? String(result.value || '').trim() : null;
        }

        const value = window.prompt(placeholder, '');
        return value === null ? null : String(value).trim();
    };

    const confirmWithDetails = async ({ title, message, details = [], confirmText, cancelText }) => {
        if (typeof Swal !== 'undefined') {
            const result = await Swal.fire({
                icon: 'warning',
                title,
                html: buildDetailsHtml(message, details),
                showCancelButton: true,
                confirmButtonText: confirmText,
                cancelButtonText: cancelText,
                allowOutsideClick: false,
            });

            return !!result.isConfirmed;
        }

        return window.confirm(message || 'Lanjutkan?');
    };

    const renderActivityLog = () => {
        if (!el.activityLog) return;

        if (!logEntries.length) {
            el.activityLog.innerHTML = '<div class="scan-log-empty">Belum ada aktivitas scan.</div>';
            return;
        }

        el.activityLog.innerHTML = logEntries.map((entry) => `
            <div class="scan-log-item ${entry.type}">
                <div class="scan-log-meta">
                    <span>${escapeHtml(entry.label)}</span>
                    <span>${escapeHtml(entry.time)}</span>
                </div>
                <div class="scan-log-message">${escapeHtml(entry.message)}</div>
                ${entry.detail ? `<div class="scan-log-detail">${escapeHtml(entry.detail)}</div>` : ''}
            </div>
        `).join('');
    };

    const pushLog = (label, message, type = 'success', detail = '') => {
        logEntries = [
            { label, message, type, detail, time: nowLabel() },
            ...logEntries,
        ].slice(0, 10);
        renderActivityLog();
    };

    const ensureAudio = async () => {
        if (!window.AudioContext && !window.webkitAudioContext) return null;
        if (!audioContext) {
            const Context = window.AudioContext || window.webkitAudioContext;
            audioContext = new Context();
        }
        if (audioContext.state === 'suspended') {
            await audioContext.resume();
        }
        return audioContext;
    };

    const playTone = async (type) => {
        try {
            const context = await ensureAudio();
            if (!context) return;

            const gain = context.createGain();
            gain.connect(context.destination);
            gain.gain.setValueAtTime(0.0001, context.currentTime);

            const tone = (frequency, start, duration) => {
                const osc = context.createOscillator();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(frequency, start);
                osc.connect(gain);
                osc.start(start);
                osc.stop(start + duration);
            };

            if (type === 'success') {
                gain.gain.linearRampToValueAtTime(0.07, context.currentTime + 0.01);
                gain.gain.exponentialRampToValueAtTime(0.0001, context.currentTime + 0.18);
                tone(880, context.currentTime, 0.08);
                tone(1180, context.currentTime + 0.08, 0.08);
                return;
            }

            if (type === 'complete') {
                gain.gain.linearRampToValueAtTime(0.08, context.currentTime + 0.01);
                gain.gain.exponentialRampToValueAtTime(0.0001, context.currentTime + 0.28);
                tone(880, context.currentTime, 0.08);
                tone(1174, context.currentTime + 0.08, 0.08);
                tone(1568, context.currentTime + 0.16, 0.10);
                return;
            }

            gain.gain.linearRampToValueAtTime(0.07, context.currentTime + 0.01);
            gain.gain.exponentialRampToValueAtTime(0.0001, context.currentTime + 0.22);
            tone(320, context.currentTime, 0.10);
            tone(220, context.currentTime + 0.10, 0.10);
        } catch (error) {
            // ignore audio failures
        }
    };

    const renderSearchResults = (transactions = []) => {
        if (!Array.isArray(transactions) || !transactions.length) {
            el.searchResults.innerHTML = '<div class="scan-search-empty">Tidak ada inbound yang cocok.</div>';
            return;
        }

        el.searchResults.innerHTML = transactions.map((row) => {
            const summary = row.summary || {};
            const meta = [
                row.warehouse ? `Gudang: ${row.warehouse}` : null,
                row.ref_no ? `Ref: ${row.ref_no}` : null,
                row.surat_jalan_no ? `SJ: ${row.surat_jalan_no}` : null,
                row.transacted_at ? `Inbound: ${row.transacted_at}` : null,
            ].filter(Boolean).join(' | ');

            return `
                <div class="scan-search-item">
                    <div class="scan-search-top">
                        <div>
                            <div class="scan-search-code">${escapeHtml(row.code || '-')}</div>
                            <div class="scan-search-meta">${escapeHtml(typeLabel(row.type))}</div>
                        </div>
                        <div class="scan-badge ${badgeClass(row.status)}">${escapeHtml(statusLabel(row.status))}</div>
                    </div>
                    <div class="scan-search-meta">${escapeHtml(meta || '-')}</div>
                    <div class="scan-search-progress">Koli ${summary.scanned_koli || 0}/${summary.expected_koli || 0} | Qty ${summary.scanned_qty || 0}/${summary.expected_qty || 0}</div>
                    <button type="button" class="scan-btn scan-search-open btn-open-transaction" data-id="${row.id}">Pilih Inbound</button>
                </div>
            `;
        }).join('');
    };

    const renderTransaction = (transaction) => {
        state.transaction = transaction || null;

        if (!transaction) {
            el.summaryCode.textContent = '-';
            el.summaryMeta.textContent = 'Belum ada inbound aktif.';
            el.summaryKoli.textContent = '0 / 0';
            el.summaryRemaining.textContent = 'Sisa 0 koli.';
            el.summaryQty.textContent = '0 / 0';
            el.summaryQtyNote.textContent = 'Belum ada scan SKU.';
            el.summaryStatus.textContent = '-';
            el.summaryAudit.textContent = 'Menunggu inbound dipilih.';
            el.detailTitle.textContent = 'Belum Ada Inbound Aktif';
            el.detailMeta.textContent = 'Pilih inbound dari panel kiri untuk melihat daftar SKU, audit scan, dan progres inbound.';
            el.detailBadge.textContent = 'Menunggu';
            el.detailBadge.className = 'scan-badge pending';
            el.detailStatus.textContent = 'Belum ada progress scan.';
            el.detailItems.innerHTML = '<tr><td colspan="5" class="scan-empty">Belum ada item untuk ditampilkan.</td></tr>';
            el.detailAudit.innerHTML = `
                <div class="scan-audit-item"><strong>Petunjuk:</strong> fokus akan tetap diarahkan ke input utama supaya operator tidak perlu klik berulang.</div>
                <div class="scan-audit-item"><strong>Alur:</strong> inbound dibuka dulu, lalu scan SKU per koli sampai complete.</div>
            `;
            el.btnScanSku.disabled = true;
            el.btnReset.disabled = true;
            el.btnComplete.disabled = true;
            return;
        }

        const summary = transaction.summary || {};
        const session = transaction.session || {};
        const audit = session.audit || {};
        const items = Array.isArray(transaction.items) ? transaction.items : [];
        const expectedKoli = Number(summary.expected_koli || 0);
        const scannedKoli = Number(summary.scanned_koli || 0);
        const expectedQty = Number(summary.expected_qty || 0);
        const scannedQty = Number(summary.scanned_qty || 0);
        const hasVariance = scannedKoli !== expectedKoli || scannedQty !== expectedQty;
        const metaLine = [
            transaction.warehouse ? `Gudang: ${transaction.warehouse}` : null,
            transaction.ref_no ? `Ref: ${transaction.ref_no}` : null,
            transaction.surat_jalan_no ? `SJ: ${transaction.surat_jalan_no}` : null,
            transaction.surat_jalan_at ? `Tgl SJ: ${transaction.surat_jalan_at}` : null,
            transaction.transacted_at ? `Inbound: ${transaction.transacted_at}` : null,
        ].filter(Boolean).join(' | ');

        el.summaryCode.textContent = transaction.code || '-';
        el.summaryMeta.textContent = metaLine || 'Inbound aktif siap discan.';
        el.summaryKoli.textContent = `${scannedKoli} / ${expectedKoli}`;
        el.summaryRemaining.textContent = `Sisa ${Math.max(0, expectedKoli - scannedKoli)} koli.`;
        el.summaryQty.textContent = `${scannedQty} / ${expectedQty}`;
        el.summaryQtyNote.textContent = hasVariance
            ? 'Masih ada selisih terhadap surat jalan.'
            : 'Progress qty sesuai target.';
        el.summaryStatus.textContent = statusLabel(transaction.status);

        const auditParts = [];
        if (audit.started_by && audit.started_by !== '-') auditParts.push(`Mulai: ${audit.started_by}`);
        if (audit.last_scanned_by && audit.last_scanned_by !== '-' && audit.last_scanned_at) {
            auditParts.push(`Scan terakhir: ${audit.last_scanned_by} @ ${audit.last_scanned_at}`);
        }
        if (audit.completed_by && audit.completed_by !== '-') auditParts.push(`Selesai: ${audit.completed_by}`);
        if ((audit.reset_count || 0) > 0) auditParts.push(`Reset ${audit.reset_count}x`);
        el.summaryAudit.textContent = auditParts.join(' | ') || 'Inbound aktif belum memiliki audit.';

        el.detailTitle.textContent = transaction.code || 'Inbound Aktif';
        el.detailMeta.textContent = metaLine || 'Inbound aktif siap discan.';
        el.detailBadge.textContent = transaction.status === 'completed' && hasVariance
            ? 'Selesai + Selisih'
            : statusLabel(transaction.status);
        el.detailBadge.className = `scan-badge ${badgeClass(transaction.status)}`;
        el.detailStatus.textContent = `Koli ${scannedKoli}/${expectedKoli} | Qty ${scannedQty}/${expectedQty}`;

        el.detailItems.innerHTML = items.map((item) => {
            const itemExpectedKoli = Number(item.expected_koli || 0);
            const itemScannedKoli = Number(item.scanned_koli || 0);
            const itemExpectedQty = Number(item.expected_qty || 0);
            const itemScannedQty = Number(item.scanned_qty || 0);
            const isMatch = itemExpectedKoli === itemScannedKoli && itemExpectedQty === itemScannedQty;
            const isOver = itemScannedKoli > itemExpectedKoli || itemScannedQty > itemExpectedQty;
            const statusText = isMatch ? 'Sesuai' : (isOver ? 'Lebih' : 'Kurang');
            const pillClass = isMatch ? 'match' : (isOver ? 'over' : '');

            return `
                <tr>
                    <td data-label="SKU">
                        <div class="scan-item-sku">${escapeHtml(item.sku || '-')}</div>
                        <div class="scan-item-name">${escapeHtml(item.item_name || '-')}</div>
                    </td>
                    <td data-label="Qty / Koli">${item.qty_per_koli || 0} qty / koli</td>
                    <td data-label="Target">Koli ${itemExpectedKoli} | Qty ${itemExpectedQty}</td>
                    <td data-label="Hasil Scan">Koli ${itemScannedKoli} | Qty ${itemScannedQty}</td>
                    <td data-label="Status"><span class="scan-progress-pill ${pillClass}">${statusText}</span></td>
                </tr>
            `;
        }).join('');

        el.detailAudit.innerHTML = `
            <div class="scan-audit-item"><strong>Mulai oleh:</strong> ${escapeHtml(audit.started_by || '-')}</div>
            <div class="scan-audit-item"><strong>Mulai pada:</strong> ${escapeHtml(session.started_at || '-')}</div>
            <div class="scan-audit-item"><strong>Scan terakhir:</strong> ${escapeHtml(audit.last_scanned_by || '-')}${audit.last_scanned_at ? ` @ ${escapeHtml(audit.last_scanned_at)}` : ''}</div>
            <div class="scan-audit-item"><strong>Complete oleh:</strong> ${escapeHtml(audit.completed_by || '-')}</div>
            <div class="scan-audit-item"><strong>Reset:</strong> ${audit.reset_count || 0}x</div>
            <div class="scan-audit-item"><strong>Alasan reset:</strong> ${escapeHtml(audit.reset_reason || '-')}</div>
        `;

        const isCompleted = transaction.status === 'completed';
        el.btnScanSku.disabled = isCompleted;
        el.btnReset.disabled = isCompleted;
        el.btnComplete.disabled = isCompleted;
    };

    const searchTransactions = async () => {
        if (state.searching) return;
        state.searching = true;
        el.btnSearch.disabled = true;
        setStatusBox(el.searchStatus, 'Mencari inbound...', 'pending');

        try {
            const query = el.searchQuery.value.trim();
            const url = new URL(routes.search, window.location.origin);
            if (query) {
                url.searchParams.set('query', query);
            }

            const payload = await fetchJson(url.toString());
            renderSearchResults(payload.transactions || []);
            setStatusBox(el.searchStatus, 'Daftar inbound siap dipilih.', 'success');
            pushLog('Cari', 'Daftar inbound diperbarui.', 'success', query ? `Query: ${query}` : 'Mode default pending/scanning');
        } catch (error) {
            renderSearchResults([]);
            setStatusBox(el.searchStatus, error.message || 'Gagal mencari inbound.', 'error');
            showError(error.message || 'Gagal mencari inbound.', error.details || []);
            pushLog('Cari', error.message || 'Gagal mencari inbound.', 'error');
        } finally {
            state.searching = false;
            el.btnSearch.disabled = false;
        }
    };

    const openTransaction = async (transactionId) => {
        if (state.opening) return;
        state.opening = true;
        setStatusBox(el.searchStatus, 'Membuka inbound...', 'pending');

        try {
            const payload = await fetchJson(routes.open, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    transaction_id: transactionId,
                    _token: csrfToken,
                }),
            });

            renderTransaction(payload.transaction || null);
            setStatusBox(el.searchStatus, payload.message || 'Inbound siap discan.', 'success');
            setStatusBox(el.scanStatus, payload.transaction?.status === 'completed'
                ? 'Inbound sudah selesai sebelumnya.'
                : 'Inbound aktif siap menerima scan SKU.', payload.transaction?.status === 'completed' ? 'success' : 'success');
            setStatusBox(el.workbenchStatus, payload.message || 'Inbound aktif siap bekerja.', 'success');
            pushLog('Inbound', payload.message || 'Inbound siap discan.', 'success', payload.transaction?.code || '');
            await playTone('success');
            if (payload.transaction?.status !== 'completed') {
                focusSku();
            }
        } catch (error) {
            setStatusBox(el.searchStatus, error.message || 'Gagal membuka inbound.', 'error');
            setStatusBox(el.workbenchStatus, error.message || 'Gagal membuka inbound.', 'error');
            showError(error.message || 'Gagal membuka inbound.', error.details || []);
            pushLog('Inbound', error.message || 'Gagal membuka inbound.', 'error');
        } finally {
            state.opening = false;
        }
    };

    const submitSku = async () => {
        if (state.scanning) return;
        if (!state.transaction?.session?.id) {
            const message = 'Pilih inbound aktif terlebih dahulu.';
            setStatusBox(el.scanStatus, message, 'error');
            setStatusBox(el.workbenchStatus, message, 'error');
            pushLog('SKU', message, 'error');
            focusSearch();
            return;
        }

        const code = el.skuCode.value.trim();
        if (!code) {
            const message = 'Scan atau input SKU terlebih dahulu.';
            setStatusBox(el.scanStatus, message, 'error');
            pushLog('SKU', message, 'error');
            focusSku();
            return;
        }

        state.scanning = true;
        el.btnScanSku.disabled = true;
        setStatusBox(el.scanStatus, 'Memproses scan SKU...', 'pending');

        try {
            const postScan = (extra = {}) => fetchJson(routes.scanSku, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    session_id: state.transaction.session.id,
                    code,
                    ...extra,
                    _token: csrfToken,
                }),
            });

            let payload = null;
            try {
                payload = await postScan();
            } catch (error) {
                if (error?.payload?.action === 'confirm_over_scan') {
                    const ok = await confirmWithDetails({
                        title: 'SKU Melebihi Target',
                        message: error.message || 'Scan berikutnya akan dianggap terima lebih.',
                        details: error.details || [],
                        confirmText: 'Lanjut tambah koli',
                        cancelText: 'Batal',
                    });

                    if (!ok) {
                        setStatusBox(el.scanStatus, 'Scan dibatalkan.', 'pending');
                        pushLog('SKU', 'Scan dibatalkan operator.', 'pending', code);
                        focusSku();
                        return;
                    }

                    payload = await postScan({ allow_over_scan: true });
                } else {
                    throw error;
                }
            }

            renderTransaction(payload.transaction || null);
            setStatusBox(el.scanStatus, payload.message || 'SKU berhasil discan.', 'success');
            setStatusBox(el.workbenchStatus, `Scan terakhir: ${code}`, 'success');
            pushLog('SKU', payload.message || 'SKU berhasil discan.', 'success', code);
            await playTone('success');
            el.skuCode.value = '';
            focusSku();
        } catch (error) {
            setStatusBox(el.scanStatus, error.message || 'Gagal scan SKU.', 'error');
            setStatusBox(el.workbenchStatus, error.message || 'Gagal scan SKU.', 'error');
            showError(error.message || 'Gagal scan SKU.', error.details || []);
            pushLog('SKU', error.message || 'Gagal scan SKU.', 'error', code);
            await playTone('error');
            focusSku();
        } finally {
            state.scanning = false;
            el.btnScanSku.disabled = state.transaction?.status === 'completed';
        }
    };

    const completeInbound = async () => {
        if (state.completing || !state.transaction?.session?.id) return;

        state.completing = true;
        el.btnComplete.disabled = true;
        setStatusBox(el.workbenchStatus, 'Menyelesaikan inbound...', 'pending');

        try {
            const postComplete = (extra = {}) => fetchJson(routes.complete, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    session_id: state.transaction.session.id,
                    ...extra,
                    _token: csrfToken,
                }),
            });

            let payload = null;
            try {
                payload = await postComplete();
            } catch (error) {
                if (error?.payload?.action === 'confirm_variance') {
                    const ok = await confirmWithDetails({
                        title: 'Complete Dengan Selisih',
                        message: error.message || 'Ada selisih antara target dan hasil scan.',
                        details: error.details || [],
                        confirmText: 'Ya, complete inbound',
                        cancelText: 'Batal',
                    });

                    if (!ok) {
                        setStatusBox(el.workbenchStatus, 'Complete dibatalkan.', 'pending');
                        pushLog('Complete', 'Complete dibatalkan operator.', 'pending');
                        return;
                    }

                    payload = await postComplete({ confirm_variance: true });
                } else {
                    throw error;
                }
            }

            renderTransaction(payload.transaction || null);
            setStatusBox(el.workbenchStatus, payload.message || 'Inbound selesai.', 'success');
            setStatusBox(el.scanStatus, 'Inbound selesai. Pilih inbound berikutnya.', 'success');
            pushLog('Complete', payload.message || 'Inbound selesai.', 'success', payload.transaction?.code || '');
            await playTone('complete');
            await searchTransactions();
            focusSearch();
        } catch (error) {
            setStatusBox(el.workbenchStatus, error.message || 'Gagal menyelesaikan inbound.', 'error');
            showError(error.message || 'Gagal menyelesaikan inbound.', error.details || []);
            pushLog('Complete', error.message || 'Gagal menyelesaikan inbound.', 'error');
            await playTone('error');
        } finally {
            state.completing = false;
            el.btnComplete.disabled = state.transaction?.status === 'completed';
        }
    };

    const resetInbound = async () => {
        if (state.resetting || !state.transaction?.session?.id) return;

        const reason = await askReason('Reset scan inbound', 'Contoh: inbound salah pilih / scan ganda', 'Reset');
        if (!reason) return;

        state.resetting = true;
        el.btnReset.disabled = true;
        setStatusBox(el.workbenchStatus, 'Mereset hasil scan inbound...', 'pending');

        try {
            const payload = await fetchJson(routes.reset, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    session_id: state.transaction.session.id,
                    reason,
                    _token: csrfToken,
                }),
            });

            renderTransaction(payload.transaction || null);
            setStatusBox(el.workbenchStatus, payload.message || 'Scan inbound berhasil direset.', 'success');
            setStatusBox(el.scanStatus, 'Hasil scan direset. Mulai scan ulang dari SKU pertama.', 'success');
            pushLog('Reset', payload.message || 'Scan inbound berhasil direset.', 'success', reason);
            await playTone('success');
            focusSku();
        } catch (error) {
            setStatusBox(el.workbenchStatus, error.message || 'Gagal reset scan inbound.', 'error');
            showError(error.message || 'Gagal reset scan inbound.', error.details || []);
            pushLog('Reset', error.message || 'Gagal reset scan inbound.', 'error', reason);
            await playTone('error');
        } finally {
            state.resetting = false;
            el.btnReset.disabled = state.transaction?.status === 'completed';
        }
    };

    document.addEventListener('keydown', (event) => {
        if (event.key === 'F1') {
            event.preventDefault();
            focusSearch();
            return;
        }

        if (event.key === 'F2') {
            event.preventDefault();
            focusSku();
            return;
        }

        if (event.key === 'Enter' && document.activeElement === el.searchQuery) {
            event.preventDefault();
            searchTransactions();
            return;
        }

        if (event.key === 'Enter' && document.activeElement === el.skuCode) {
            event.preventDefault();
            submitSku();
            return;
        }

        if (event.ctrlKey && event.key === 'Enter') {
            event.preventDefault();
            completeInbound();
        }
    });

    el.btnSearch.addEventListener('click', searchTransactions);
    el.btnScanSku.addEventListener('click', submitSku);
    el.btnComplete.addEventListener('click', completeInbound);
    el.btnReset.addEventListener('click', resetInbound);
    el.btnRefresh.addEventListener('click', searchTransactions);
    el.searchResults.addEventListener('click', (event) => {
        const button = event.target.closest('.btn-open-transaction');
        if (!button) return;
        openTransaction(button.getAttribute('data-id'));
    });
    el.btnClearLog.addEventListener('click', () => {
        logEntries = [];
        renderActivityLog();
    });
    if (el.btnToggleHelp) {
        el.btnToggleHelp.addEventListener('click', () => {
            helpOpen = !helpOpen;
            renderPanels();
        });
    }

    renderPanels();
    renderActivityLog();
    renderTransaction(null);
    searchTransactions();
    focusSearch();
</script>
@endpush
