@extends('layouts.admin')

@section('title', 'Laporan Transfer Gudang')
@section('page_title', 'Laporan Transfer Gudang')

@push('styles')
<style>
    .transfer-report-toolbar {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 18px;
        margin-bottom: 18px;
    }

    .transfer-report-document {
        background: #fff;
        border: 1px solid #d1d5db;
        box-shadow: 0 10px 28px rgba(15, 23, 42, 0.08);
        color: #111827;
        margin: 0 auto;
        max-width: 1320px;
        padding: 28px;
    }

    .report-tabs {
        align-items: center;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        display: flex;
        gap: 8px;
        margin: 0 auto 18px;
        max-width: 1320px;
        padding: 6px;
    }

    .report-tab {
        background: transparent;
        border: 0;
        border-radius: 6px;
        color: #6b7280;
        font-weight: 700;
        padding: 10px 16px;
    }

    .report-tab.active {
        background: #111827;
        color: #fff;
    }

    .report-panel {
        display: none;
    }

    .report-panel.active {
        display: block;
    }

    .transfer-analytics {
        margin: 0 auto;
        max-width: 1320px;
    }

    .analytics-kpi-grid,
    .analytics-grid {
        display: grid;
        gap: 14px;
    }

    .analytics-kpi-grid {
        grid-template-columns: repeat(4, minmax(0, 1fr));
        margin-bottom: 14px;
    }

    .analytics-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .analytics-card,
    .analytics-kpi {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
    }

    .analytics-card {
        padding: 18px;
    }

    .analytics-kpi {
        padding: 16px;
    }

    .analytics-title {
        color: #111827;
        font-size: 15px;
        font-weight: 800;
        margin-bottom: 2px;
    }

    .analytics-subtitle {
        color: #6b7280;
        font-size: 12px;
        margin-bottom: 14px;
    }

    .analytics-kpi-label {
        color: #6b7280;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
    }

    .analytics-kpi-value {
        color: #111827;
        font-size: 26px;
        font-weight: 800;
        line-height: 1.2;
        margin-top: 6px;
    }

    .analytics-kpi-note {
        color: #6b7280;
        font-size: 12px;
        margin-top: 4px;
    }

    .chart-box {
        min-height: 315px;
    }

    .analytics-list {
        display: grid;
        gap: 10px;
        margin: 0;
        padding: 0;
    }

    .analytics-list li {
        align-items: flex-start;
        border-bottom: 1px solid #f3f4f6;
        display: flex;
        gap: 10px;
        justify-content: space-between;
        list-style: none;
        padding: 0 0 10px;
    }

    .analytics-list li:last-child {
        border-bottom: 0;
        padding-bottom: 0;
    }

    .analytics-list strong {
        color: #111827;
    }

    .analytics-list span {
        color: #6b7280;
        font-size: 12px;
    }

    .analytics-state {
        align-items: center;
        border: 1px dashed #d1d5db;
        color: #6b7280;
        display: flex;
        justify-content: center;
        min-height: 220px;
        padding: 18px;
        text-align: center;
    }

    .document-header {
        border-bottom: 3px double #111827;
        display: flex;
        justify-content: space-between;
        gap: 24px;
        padding-bottom: 16px;
    }

    .document-title {
        font-size: 22px;
        font-weight: 800;
        letter-spacing: .04em;
        margin-bottom: 6px;
        text-transform: uppercase;
    }

    .document-meta {
        display: grid;
        grid-template-columns: 130px 1fr;
        gap: 4px 12px;
        font-size: 12px;
        min-width: 300px;
    }

    .document-summary {
        display: grid;
        grid-template-columns: repeat(6, minmax(120px, 1fr));
        gap: 10px;
        margin: 18px 0;
    }

    .summary-cell {
        border: 1px solid #d1d5db;
        padding: 10px 12px;
    }

    .summary-label {
        color: #6b7280;
        font-size: 11px;
        text-transform: uppercase;
    }

    .summary-value {
        font-size: 20px;
        font-weight: 800;
        line-height: 1.2;
    }

    .document-table {
        border-collapse: collapse;
        font-size: 11px;
        width: 100%;
    }

    .document-table th,
    .document-table td {
        border: 1px solid #9ca3af;
        padding: 7px 8px;
        vertical-align: top;
    }

    .document-table th {
        background: #f3f4f6;
        color: #111827;
        font-weight: 800;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .document-table .text-end {
        text-align: right;
    }

    .badge-report {
        border: 1px solid #9ca3af;
        border-radius: 4px;
        display: inline-block;
        font-size: 10px;
        font-weight: 700;
        padding: 2px 6px;
        text-transform: uppercase;
    }

    .badge-report.ok {
        background: #ecfdf3;
        border-color: #86efac;
        color: #166534;
    }

    .badge-report.warn {
        background: #fff7ed;
        border-color: #fdba74;
        color: #9a3412;
    }

    .badge-report.danger {
        background: #fef2f2;
        border-color: #fca5a5;
        color: #991b1b;
    }

    .inbound-list {
        line-height: 1.35;
        min-width: 190px;
    }

    .muted-line {
        color: #6b7280;
        font-size: 10px;
    }

    .table-state {
        border: 1px dashed #d1d5db;
        color: #6b7280;
        padding: 18px;
        text-align: center;
    }

    @media (max-width: 991.98px) {
        .document-header,
        .document-summary,
        .analytics-kpi-grid,
        .analytics-grid {
            display: block;
        }

        .summary-cell,
        .analytics-kpi,
        .analytics-card {
            margin-bottom: 8px;
        }
    }

    @media print {
        body * {
            visibility: hidden;
        }

        .transfer-report-document,
        .transfer-report-document * {
            visibility: visible;
        }

        .transfer-report-document {
            border: 0;
            box-shadow: none;
            left: 0;
            max-width: none;
            padding: 0;
            position: absolute;
            top: 0;
            width: 100%;
        }

        .report-panel {
            display: block;
        }

        .document-table {
            font-size: 9px;
        }
    }
</style>
@endpush

@section('content')
<div class="transfer-report-toolbar no-print">
    <div class="row g-4 align-items-end">
        <div class="col-lg-3 col-md-6">
            <label class="form-label text-muted">Cari</label>
            <input type="text" id="filter_q" class="form-control form-control-solid" placeholder="Kode transfer / SKU / inbound">
        </div>
        <div class="col-lg-2 col-md-3">
            <label class="form-label text-muted">Dari Tanggal</label>
            <input type="date" id="filter_date_from" class="form-control form-control-solid" value="{{ $defaultDateFrom }}">
        </div>
        <div class="col-lg-2 col-md-3">
            <label class="form-label text-muted">Sampai Tanggal</label>
            <input type="date" id="filter_date_to" class="form-control form-control-solid" value="{{ $defaultDateTo }}">
        </div>
        <div class="col-lg-2 col-md-4">
            <label class="form-label text-muted">Gudang Asal</label>
            <select id="filter_from_warehouse" class="form-select form-select-solid">
                <option value="">Semua</option>
                @foreach($warehouses as $warehouse)
                    <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-2 col-md-4">
            <label class="form-label text-muted">Gudang Tujuan</label>
            <select id="filter_to_warehouse" class="form-select form-select-solid">
                <option value="">Semua</option>
                @foreach($warehouses as $warehouse)
                    <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-1 col-md-4">
            <label class="form-label text-muted">Limit</label>
            <select id="filter_limit" class="form-select form-select-solid">
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100">100</option>
                <option value="-1">Semua</option>
            </select>
        </div>
        <div class="col-lg-2 col-md-4">
            <label class="form-label text-muted">Status</label>
            <select id="filter_status" class="form-select form-select-solid">
                <option value="">Semua Status</option>
                <option value="qc_pending">Menunggu QC</option>
                <option value="completed">Selesai</option>
                <option value="canceled">Dibatalkan</option>
            </select>
        </div>
        <div class="col-lg-2 col-md-4">
            <label class="form-label text-muted">Traceability</label>
            <select id="filter_traceability" class="form-select form-select-solid">
                <option value="">Semua Mode</option>
                <option value="qr">QR Inbound</option>
                <option value="legacy">Legacy / Tanpa QR</option>
                <option value="none">Belum Ada Mode</option>
            </select>
        </div>
        <div class="col-lg-2 col-md-4">
            <label class="form-label text-muted">Kekurangan</label>
            <select id="filter_shortage" class="form-select form-select-solid">
                <option value="">Semua</option>
                <option value="yes">Ada Kekurangan</option>
                <option value="no">Tidak Ada</option>
            </select>
        </div>
        <div class="col-lg-2 col-md-4">
            <label class="form-label text-muted">Reject</label>
            <select id="filter_reject" class="form-select form-select-solid">
                <option value="">Semua</option>
                <option value="yes">Ada Reject</option>
                <option value="no">Tidak Ada</option>
            </select>
        </div>
        <div class="col-lg-2 col-md-4">
            <label class="form-label text-muted">Dibuat Oleh</label>
            <select id="filter_created_by" class="form-select form-select-solid">
                <option value="">Semua User</option>
                @foreach($users as $user)
                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-2 col-md-4">
            <label class="form-label text-muted">QC Oleh</label>
            <select id="filter_qc_by" class="form-select form-select-solid">
                <option value="">Semua User</option>
                @foreach($users as $user)
                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-4 col-md-8">
            <div class="d-flex gap-2 flex-wrap">
                <button type="button" id="btn_apply" class="btn btn-primary">Terapkan</button>
                <button type="button" id="btn_reset" class="btn btn-light">Reset</button>
                <button type="button" id="btn_print" class="btn btn-light-primary">Cetak</button>
            </div>
        </div>
    </div>
</div>

<div class="report-tabs no-print" role="tablist" aria-label="Tab laporan transfer gudang">
    <button type="button" class="report-tab active" data-report-tab="data">Data Laporan</button>
    <button type="button" class="report-tab" data-report-tab="analytics">Analitik Transfer</button>
</div>

<div class="report-panel active" id="report_tab_data">
<div class="transfer-report-document" id="report_document">
    <div class="document-header">
        <div>
            <div class="document-title">Laporan Transfer Gudang</div>
            <div class="text-muted">Dokumen kontrol perpindahan stok antar gudang, termasuk hasil QC dan sumber inbound.</div>
        </div>
        <div class="document-meta">
            <span>Periode</span><strong id="doc_period">-</strong>
            <span>Dicetak</span><strong id="doc_printed_at">-</strong>
            <span>Filter</span><strong id="doc_filter_summary">Semua data</strong>
        </div>
    </div>

    <div class="document-summary">
        <div class="summary-cell">
            <div class="summary-label">Transfer</div>
            <div class="summary-value" id="summary_transfer_count">0</div>
        </div>
        <div class="summary-cell">
            <div class="summary-label">Baris SKU</div>
            <div class="summary-value" id="summary_sku_line_count">0</div>
        </div>
        <div class="summary-cell">
            <div class="summary-label">Qty Kirim</div>
            <div class="summary-value" id="summary_total_qty">0</div>
        </div>
        <div class="summary-cell">
            <div class="summary-label">Qty OK</div>
            <div class="summary-value" id="summary_total_ok">0</div>
        </div>
        <div class="summary-cell">
            <div class="summary-label">Reject</div>
            <div class="summary-value" id="summary_total_reject">0</div>
        </div>
        <div class="summary-cell">
            <div class="summary-label">Kurang</div>
            <div class="summary-value" id="summary_total_short">0</div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="document-table" id="transfer_report_table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Tanggal</th>
                    <th>No. Transfer</th>
                    <th>Gudang</th>
                    <th>SKU / Barang</th>
                    <th class="text-end">Kirim</th>
                    <th class="text-end">OK</th>
                    <th class="text-end">Reject</th>
                    <th class="text-end">Kurang</th>
                    <th>Trace</th>
                    <th>Sumber Inbound</th>
                    <th>Petugas</th>
                    <th>Catatan</th>
                </tr>
            </thead>
            <tbody id="report_rows">
                <tr>
                    <td colspan="13"><div class="table-state">Memuat laporan...</div></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
</div>

<div class="report-panel no-print" id="report_tab_analytics">
    <div class="transfer-analytics">
        <div class="analytics-kpi-grid">
            <div class="analytics-kpi">
                <div class="analytics-kpi-label">Total Transfer</div>
                <div class="analytics-kpi-value" id="analytics_transfer_count">0</div>
                <div class="analytics-kpi-note" id="analytics_sku_line_count">0 baris SKU</div>
            </div>
            <div class="analytics-kpi">
                <div class="analytics-kpi-label">Total Qty Kirim</div>
                <div class="analytics-kpi-value" id="analytics_total_qty">0</div>
                <div class="analytics-kpi-note" id="analytics_total_ok">0 qty OK</div>
            </div>
            <div class="analytics-kpi">
                <div class="analytics-kpi-label">Akurasi QC</div>
                <div class="analytics-kpi-value" id="analytics_accuracy_rate">0%</div>
                <div class="analytics-kpi-note">Qty OK dibanding qty kirim</div>
            </div>
            <div class="analytics-kpi">
                <div class="analytics-kpi-label">Qty Bermasalah</div>
                <div class="analytics-kpi-value" id="analytics_issue_qty">0</div>
                <div class="analytics-kpi-note" id="analytics_issue_rate">0% dari qty kirim</div>
            </div>
        </div>

        <div class="analytics-grid">
            <div class="analytics-card">
                <div class="analytics-title">Tren Volume Harian</div>
                <div class="analytics-subtitle">Perbandingan qty OK, reject, dan kurang per tanggal transfer.</div>
                <div id="chart_daily_volume" class="chart-box"></div>
            </div>
            <div class="analytics-card">
                <div class="analytics-title">Arus Antar Gudang</div>
                <div class="analytics-subtitle">Rute transfer dengan volume qty kirim terbesar.</div>
                <div id="chart_warehouse_flow" class="chart-box"></div>
            </div>
            <div class="analytics-card">
                <div class="analytics-title">SKU Bermasalah</div>
                <div class="analytics-subtitle">SKU dengan akumulasi reject dan kekurangan terbesar.</div>
                <div id="chart_issue_skus" class="chart-box"></div>
            </div>
            <div class="analytics-card">
                <div class="analytics-title">SKU Paling Banyak Ditransfer</div>
                <div class="analytics-subtitle">SKU dengan total qty kirim tertinggi.</div>
                <div id="chart_moved_skus" class="chart-box"></div>
            </div>
            <div class="analytics-card">
                <div class="analytics-title">Status Transfer</div>
                <div class="analytics-subtitle">Distribusi jumlah dokumen transfer per status.</div>
                <div id="chart_status" class="chart-box"></div>
            </div>
            <div class="analytics-card">
                <div class="analytics-title">Mode Traceability</div>
                <div class="analytics-subtitle">Perbandingan transfer QR inbound, legacy, dan belum bermode.</div>
                <div id="chart_traceability" class="chart-box"></div>
            </div>
            <div class="analytics-card">
                <div class="analytics-title">Insight Cepat</div>
                <div class="analytics-subtitle" id="analytics_period">Periode: -</div>
                <ul class="analytics-list" id="analytics_insights">
                    <li><span>Memuat insight...</span></li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const dataUrl = @json($dataUrl);
    const chartUrl = @json($chartUrl);
    const defaultDateFrom = @json($defaultDateFrom);
    const defaultDateTo = @json($defaultDateTo);
    const rowsEl = document.getElementById('report_rows');
    let abortController = null;
    let analyticsAbortController = null;
    let draw = 0;
    let activeTab = 'data';
    let analyticsLoaded = false;
    const charts = {};

    const filters = {
        q: document.getElementById('filter_q'),
        date_from: document.getElementById('filter_date_from'),
        date_to: document.getElementById('filter_date_to'),
        from_warehouse_id: document.getElementById('filter_from_warehouse'),
        to_warehouse_id: document.getElementById('filter_to_warehouse'),
        status: document.getElementById('filter_status'),
        traceability_mode: document.getElementById('filter_traceability'),
        shortage: document.getElementById('filter_shortage'),
        reject: document.getElementById('filter_reject'),
        created_by: document.getElementById('filter_created_by'),
        qc_by: document.getElementById('filter_qc_by'),
        length: document.getElementById('filter_limit'),
    };

    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const number = (value) => new Intl.NumberFormat('id-ID').format(Number(value || 0));
    const percent = (value) => `${new Intl.NumberFormat('id-ID', {
        maximumFractionDigits: 2,
    }).format(Number(value || 0))}%`;

    const filterValueText = (select) => {
        if (!select || !select.value) return '';
        return select.options[select.selectedIndex]?.text || '';
    };

    const params = () => {
        const query = new URLSearchParams();
        Object.entries(filters).forEach(([key, el]) => {
            if (!el) return;
            const value = el.value || '';
            if (key === 'length') {
                query.set('length', value || '25');
                return;
            }
            if (value !== '') query.set(key, value);
        });
        query.set('start', '0');
        query.set('draw', String(++draw));
        return query;
    };

    const stateRow = (message) => {
        rowsEl.innerHTML = `<tr><td colspan="13"><div class="table-state">${escapeHtml(message)}</div></td></tr>`;
    };

    const badgeClass = (row) => {
        if (row.status === 'canceled') return 'danger';
        if (Number(row.qty_short || 0) > 0 || Number(row.qty_reject || 0) > 0) return 'warn';
        if (row.status === 'completed') return 'ok';
        return '';
    };

    const inboundHtml = (row) => {
        const sources = Array.isArray(row.inbound_sources) ? row.inbound_sources : [];
        if (row.traceability_mode === 'legacy') {
            return `<div class="inbound-list"><strong>Legacy / tanpa QR</strong><div class="muted-line">${escapeHtml(row.legacy_reason || '-')}</div></div>`;
        }
        if (!sources.length) {
            return '<span class="text-muted">-</span>';
        }
        return `<div class="inbound-list">${sources.map((source) => `
            <div>
                <strong>${escapeHtml(source.inbound_code)}</strong>
                <div class="muted-line">${escapeHtml(source.koli_code)} | Qty ${number(source.qty)} | OK ${number(source.qty_ok)} | R ${number(source.qty_reject)} | K ${number(source.qty_short)}</div>
            </div>
        `).join('')}</div>`;
    };

    const renderRows = (rows) => {
        if (!rows.length) {
            stateRow('Tidak ada data transfer sesuai filter.');
            return;
        }

        rowsEl.innerHTML = rows.map((row, index) => `
            <tr>
                <td>${index + 1}</td>
                <td>${escapeHtml(row.date)}</td>
                <td>
                    <strong>${escapeHtml(row.transfer_code)}</strong>
                    <div class="muted-line"><span class="badge-report ${badgeClass(row)}">${escapeHtml(row.status_label)}</span></div>
                </td>
                <td>
                    <strong>${escapeHtml(row.from_warehouse)}</strong>
                    <div class="muted-line">ke ${escapeHtml(row.to_warehouse)}</div>
                </td>
                <td>
                    <strong>${escapeHtml(row.sku)}</strong>
                    <div>${escapeHtml(row.item_name)}</div>
                    <div class="muted-line">Koli ${number(row.koli_qty)} | Lokasi ${escapeHtml(row.item_address)}</div>
                </td>
                <td class="text-end">${number(row.qty)}</td>
                <td class="text-end">${number(row.qty_ok)}</td>
                <td class="text-end">${number(row.qty_reject)}</td>
                <td class="text-end">${number(row.qty_short)}</td>
                <td>${escapeHtml(row.traceability_label)}</td>
                <td>${inboundHtml(row)}</td>
                <td>
                    <div>Buat: ${escapeHtml(row.created_by)}</div>
                    <div class="muted-line">QC: ${escapeHtml(row.qc_by)} | ${escapeHtml(row.qc_at)}</div>
                </td>
                <td>
                    ${escapeHtml(row.transfer_note || row.note || '-')}
                    ${row.qc_note ? `<div class="muted-line">QC: ${escapeHtml(row.qc_note)}</div>` : ''}
                </td>
            </tr>
        `).join('');
    };

    const setText = (id, value) => {
        const el = document.getElementById(id);
        if (el) el.textContent = value;
    };

    const renderSummary = (json) => {
        const summary = json.summary || {};
        setText('summary_transfer_count', number(summary.transfer_count));
        setText('summary_sku_line_count', number(summary.sku_line_count));
        setText('summary_total_qty', number(summary.total_qty));
        setText('summary_total_ok', number(summary.total_ok));
        setText('summary_total_reject', number(summary.total_reject));
        setText('summary_total_short', number(summary.total_short));

        const from = filters.date_from?.value || '-';
        const to = filters.date_to?.value || '-';
        setText('doc_period', `${from} s/d ${to}`);
        setText('doc_printed_at', json.period?.printed_at || '-');

        const active = [
            filters.q?.value ? `Cari: ${filters.q.value}` : '',
            filterValueText(filters.from_warehouse_id) ? `Asal: ${filterValueText(filters.from_warehouse_id)}` : '',
            filterValueText(filters.to_warehouse_id) ? `Tujuan: ${filterValueText(filters.to_warehouse_id)}` : '',
            filterValueText(filters.status) ? `Status: ${filterValueText(filters.status)}` : '',
            filterValueText(filters.traceability_mode) ? `Trace: ${filterValueText(filters.traceability_mode)}` : '',
            filterValueText(filters.shortage) ? `Kurang: ${filterValueText(filters.shortage)}` : '',
            filterValueText(filters.reject) ? `Reject: ${filterValueText(filters.reject)}` : '',
        ].filter(Boolean);
        setText('doc_filter_summary', active.length ? active.join(' | ') : 'Semua data');
    };

    const emptyChart = (id, message = 'Tidak ada data untuk filter ini.') => {
        if (charts[id]) {
            charts[id].destroy();
            delete charts[id];
        }
        const el = document.getElementById(id);
        if (el) {
            el.innerHTML = `<div class="analytics-state">${escapeHtml(message)}</div>`;
        }
    };

    const renderChart = (id, options, hasData) => {
        const el = document.getElementById(id);
        if (!el) return;
        if (!hasData) {
            emptyChart(id);
            return;
        }
        if (typeof ApexCharts === 'undefined') {
            emptyChart(id, 'Library grafik belum tersedia.');
            return;
        }
        if (charts[id]) {
            charts[id].destroy();
        }
        el.innerHTML = '';
        const chartOptions = {
            dataLabels: { enabled: false },
            grid: { borderColor: '#eef2f7' },
            legend: { position: 'top', horizontalAlign: 'left' },
            stroke: { width: 2, curve: 'smooth' },
            tooltip: {
                y: { formatter: (value) => number(value) },
            },
            ...options,
            chart: {
                fontFamily: 'Inter, Helvetica, sans-serif',
                toolbar: { show: false },
                zoom: { enabled: false },
                ...(options.chart || {}),
            },
            tooltip: {
                y: { formatter: (value) => number(value) },
                ...(options.tooltip || {}),
            },
        };
        charts[id] = new ApexCharts(el, chartOptions);
        charts[id].render();
    };

    const analyticsKpi = (summary) => {
        setText('analytics_transfer_count', number(summary.transfer_count));
        setText('analytics_sku_line_count', `${number(summary.sku_line_count)} baris SKU`);
        setText('analytics_total_qty', number(summary.total_qty));
        setText('analytics_total_ok', `${number(summary.total_ok)} qty OK`);
        setText('analytics_accuracy_rate', percent(summary.accuracy_rate));
        setText('analytics_issue_qty', number(summary.issue_qty));
        setText('analytics_issue_rate', `${percent(summary.issue_rate)} dari qty kirim`);
    };

    const renderInsights = (payload) => {
        const summary = payload.summary || {};
        const chartsData = payload.charts || {};
        const topRoute = (chartsData.warehouse_flow || [])[0];
        const topIssue = (chartsData.top_issue_skus || [])[0];
        const topMoved = (chartsData.top_moved_skus || [])[0];
        const insights = [];

        insights.push({
            title: `Akurasi QC ${percent(summary.accuracy_rate)}`,
            body: `${number(summary.total_ok)} dari ${number(summary.total_qty)} qty kirim tercatat OK.`,
        });

        if (topRoute) {
            insights.push({
                title: `Rute terbesar: ${topRoute.route}`,
                body: `${number(topRoute.qty)} qty dari ${number(topRoute.transfer_count)} transfer.`,
            });
        }

        if (topIssue) {
            insights.push({
                title: `SKU issue tertinggi: ${topIssue.sku}`,
                body: `${number(topIssue.issue_qty)} qty bermasalah, terdiri dari ${number(topIssue.reject)} reject dan ${number(topIssue.short)} kurang.`,
            });
        } else {
            insights.push({
                title: 'Tidak ada SKU bermasalah',
                body: 'Tidak ditemukan reject atau kekurangan pada filter aktif.',
            });
        }

        if (topMoved) {
            insights.push({
                title: `SKU paling aktif: ${topMoved.sku}`,
                body: `${number(topMoved.qty)} qty ditransfer untuk ${topMoved.name}.`,
            });
        }

        const list = document.getElementById('analytics_insights');
        if (list) {
            list.innerHTML = insights.map((insight) => `
                <li>
                    <div>
                        <strong>${escapeHtml(insight.title)}</strong>
                        <div><span>${escapeHtml(insight.body)}</span></div>
                    </div>
                </li>
            `).join('');
        }
    };

    const renderAnalytics = (payload) => {
        const summary = payload.summary || {};
        const chartsData = payload.charts || {};
        analyticsKpi(summary);
        setText('analytics_period', `Periode: ${payload.period?.from || '-'} s/d ${payload.period?.to || '-'} | Dibuat ${payload.period?.generated_at || '-'}`);

        const daily = chartsData.daily_volume || [];
        renderChart('chart_daily_volume', {
            chart: { type: 'bar', height: 315, stacked: true },
            colors: ['#16a34a', '#dc2626', '#f59e0b'],
            series: [
                { name: 'OK', data: daily.map((row) => row.ok || 0) },
                { name: 'Reject', data: daily.map((row) => row.reject || 0) },
                { name: 'Kurang', data: daily.map((row) => row.short || 0) },
            ],
            xaxis: { categories: daily.map((row) => row.date) },
            yaxis: { labels: { formatter: (value) => number(value) } },
        }, daily.length > 0);

        const flows = chartsData.warehouse_flow || [];
        renderChart('chart_warehouse_flow', {
            chart: { type: 'bar', height: 315 },
            plotOptions: { bar: { horizontal: true, borderRadius: 3 } },
            colors: ['#2563eb'],
            series: [{ name: 'Qty Kirim', data: flows.map((row) => row.qty || 0) }],
            xaxis: { categories: flows.map((row) => row.route), labels: { formatter: (value) => number(value) } },
        }, flows.length > 0);

        const issues = chartsData.top_issue_skus || [];
        renderChart('chart_issue_skus', {
            chart: { type: 'bar', height: 315, stacked: true },
            plotOptions: { bar: { horizontal: true, borderRadius: 3 } },
            colors: ['#dc2626', '#f59e0b'],
            series: [
                { name: 'Reject', data: issues.map((row) => row.reject || 0) },
                { name: 'Kurang', data: issues.map((row) => row.short || 0) },
            ],
            xaxis: { categories: issues.map((row) => row.sku), labels: { formatter: (value) => number(value) } },
        }, issues.length > 0);

        const moved = chartsData.top_moved_skus || [];
        renderChart('chart_moved_skus', {
            chart: { type: 'bar', height: 315 },
            plotOptions: { bar: { horizontal: true, borderRadius: 3 } },
            colors: ['#0f766e'],
            series: [{ name: 'Qty Kirim', data: moved.map((row) => row.qty || 0) }],
            xaxis: { categories: moved.map((row) => row.sku), labels: { formatter: (value) => number(value) } },
        }, moved.length > 0);

        const status = chartsData.status_breakdown || [];
        renderChart('chart_status', {
            chart: { type: 'donut', height: 315 },
            colors: ['#16a34a', '#f59e0b', '#dc2626', '#6b7280', '#2563eb'],
            labels: status.map((row) => row.label),
            series: status.map((row) => row.transfer_count || 0),
            tooltip: { y: { formatter: (value) => `${number(value)} transfer` } },
        }, status.length > 0);

        const traceability = chartsData.traceability_breakdown || [];
        renderChart('chart_traceability', {
            chart: { type: 'donut', height: 315 },
            colors: ['#2563eb', '#f97316', '#6b7280'],
            labels: traceability.map((row) => row.label),
            series: traceability.map((row) => row.transfer_count || 0),
            tooltip: { y: { formatter: (value) => `${number(value)} transfer` } },
        }, traceability.length > 0);

        renderInsights(payload);
    };

    const loadAnalytics = async () => {
        if (analyticsAbortController) analyticsAbortController.abort();
        analyticsAbortController = new AbortController();
        ['chart_daily_volume', 'chart_warehouse_flow', 'chart_issue_skus', 'chart_moved_skus', 'chart_status', 'chart_traceability'].forEach((id) => {
            emptyChart(id, 'Memuat grafik...');
        });

        try {
            const response = await fetch(`${chartUrl}?${params().toString()}`, {
                headers: { 'Accept': 'application/json' },
                signal: analyticsAbortController.signal,
            });

            const json = await response.json();
            if (!response.ok) {
                throw new Error(json.message || 'Gagal memuat analitik.');
            }

            analyticsLoaded = true;
            renderAnalytics(json);
        } catch (error) {
            if (error.name === 'AbortError') return;
            ['chart_daily_volume', 'chart_warehouse_flow', 'chart_issue_skus', 'chart_moved_skus', 'chart_status', 'chart_traceability'].forEach((id) => {
                emptyChart(id, error.message || 'Gagal memuat analitik.');
            });
            const list = document.getElementById('analytics_insights');
            if (list) {
                list.innerHTML = `<li><span>${escapeHtml(error.message || 'Gagal memuat analitik.')}</span></li>`;
            }
        }
    };

    const loadReport = async () => {
        if (abortController) abortController.abort();
        abortController = new AbortController();
        stateRow('Memuat laporan...');

        try {
            const response = await fetch(`${dataUrl}?${params().toString()}`, {
                headers: { 'Accept': 'application/json' },
                signal: abortController.signal,
            });

            const json = await response.json();
            if (!response.ok) {
                throw new Error(json.message || 'Gagal memuat laporan.');
            }

            renderSummary(json);
            renderRows(Array.isArray(json.data) ? json.data : []);
        } catch (error) {
            if (error.name === 'AbortError') return;
            stateRow(error.message || 'Gagal memuat laporan.');
        }
    };

    document.querySelectorAll('[data-report-tab]').forEach((button) => {
        button.addEventListener('click', () => {
            activeTab = button.dataset.reportTab || 'data';
            document.querySelectorAll('[data-report-tab]').forEach((tab) => {
                tab.classList.toggle('active', tab === button);
            });
            document.getElementById('report_tab_data')?.classList.toggle('active', activeTab === 'data');
            document.getElementById('report_tab_analytics')?.classList.toggle('active', activeTab === 'analytics');
            if (activeTab === 'analytics' && !analyticsLoaded) {
                loadAnalytics();
            }
        });
    });

    document.getElementById('btn_apply')?.addEventListener('click', () => {
        loadReport();
        if (activeTab === 'analytics') {
            loadAnalytics();
        } else {
            analyticsLoaded = false;
        }
    });
    document.getElementById('btn_print')?.addEventListener('click', () => window.print());
    document.getElementById('btn_reset')?.addEventListener('click', () => {
        Object.values(filters).forEach((el) => {
            if (!el) return;
            el.value = '';
        });
        filters.date_from.value = defaultDateFrom;
        filters.date_to.value = defaultDateTo;
        filters.length.value = '25';
        loadReport();
        if (activeTab === 'analytics') {
            loadAnalytics();
        } else {
            analyticsLoaded = false;
        }
    });
    filters.q?.addEventListener('keyup', (event) => {
        if (event.key === 'Enter') loadReport();
    });

    loadReport();
});
</script>
@endpush
