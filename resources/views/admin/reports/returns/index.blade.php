@extends('layouts.admin')

@section('title', 'Laporan Retur')
@section('page_title', 'Laporan Retur')

@push('styles')
<style>
    .return-report-toolbar {
        display: grid;
        grid-template-columns: minmax(220px, 1fr) repeat(5, minmax(140px, auto));
        gap: 0.75rem;
        align-items: end;
    }

    .return-report-summary {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 1rem;
    }

    .return-report-summary-card {
        background: #f8f9fc;
        border: 1px solid #eef2f7;
        border-radius: 0.85rem;
        padding: 1rem;
        min-height: 100%;
    }

    .return-report-summary-label {
        color: #7e8299;
        font-size: 0.78rem;
        font-weight: 700;
        text-transform: uppercase;
    }

    .return-report-summary-value {
        color: #181c32;
        font-size: 1.65rem;
        font-weight: 800;
        line-height: 1.1;
        margin-top: 0.35rem;
    }

    .return-report-item-list {
        display: grid;
        gap: 0.45rem;
        min-width: 260px;
    }

    .return-report-item-chip {
        background: #f8f9fc;
        border: 1px solid #eef2f7;
        border-radius: 0.75rem;
        padding: 0.65rem 0.75rem;
    }

    .return-report-qty-stack {
        display: flex;
        flex-wrap: wrap;
        gap: 0.4rem;
        justify-content: flex-end;
        min-width: 190px;
    }

    .return-report-tab-card .nav-link {
        white-space: nowrap;
    }

    @media (max-width: 1199.98px) {
        .return-report-toolbar {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 767.98px) {
        .return-report-toolbar,
        .return-report-summary {
            grid-template-columns: 1fr;
        }

        .return-report-toolbar .btn,
        .return-report-toolbar .form-control,
        .return-report-toolbar .form-select {
            width: 100% !important;
        }

        .return-report-qty-stack {
            justify-content: flex-start;
            min-width: 0;
        }
    }
</style>
@endpush

@section('content')
<div class="card mb-6">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <h3 class="fw-bolder mb-0">Filter Laporan</h3>
        </div>
        <div class="card-toolbar">
            <button type="button" class="btn btn-light-success" id="btn_export_return_report">Export Excel</button>
        </div>
    </div>
    <div class="card-body pt-2">
        <div class="return-report-toolbar mb-6">
            <div>
                <label class="text-muted fs-7 mb-1">Pencarian</label>
                <input type="text" class="form-control form-control-solid" placeholder="Cari kode, resi, supplier, SKU, atau catatan" id="report_search" />
            </div>
            <div>
                <label class="text-muted fs-7 mb-1">Status</label>
                <select id="filter_status" class="form-select form-select-solid">
                    <option value="">Semua Status</option>
                    <option value="inspected">Belum Finalisasi</option>
                    <option value="completed">Selesai</option>
                    <option value="no_received">Tidak Diterima</option>
                    <option value="pending">Menunggu Approval</option>
                    <option value="approved">Disetujui</option>
                </select>
            </div>
            <div id="match_state_wrap">
                <label class="text-muted fs-7 mb-1">Status Resi</label>
                <select id="filter_match_state" class="form-select form-select-solid">
                    <option value="">Semua</option>
                    <option value="matched">Resi Ditemukan</option>
                    <option value="unmatched">Input Manual</option>
                </select>
            </div>
            <div>
                <label class="text-muted fs-7 mb-1">Dari</label>
                <input type="text" id="filter_date_from" class="form-control form-control-solid" placeholder="YYYY-MM-DD" />
            </div>
            <div>
                <label class="text-muted fs-7 mb-1">Sampai</label>
                <input type="text" id="filter_date_to" class="form-control form-control-solid" placeholder="YYYY-MM-DD" />
            </div>
            <div>
                <button type="button" class="btn btn-light w-100" id="filter_reset">Reset</button>
            </div>
        </div>

        <div class="return-report-summary">
            <div class="return-report-summary-card">
                <div class="return-report-summary-label">Dokumen</div>
                <div class="return-report-summary-value" id="summary_total_documents">0</div>
                <div class="text-muted fs-8" id="summary_documents_meta">Customer 0 | Outbound 0</div>
            </div>
            <div class="return-report-summary-card">
                <div class="return-report-summary-label">Qty Customer Diterima</div>
                <div class="return-report-summary-value text-primary" id="summary_customer_received">0</div>
                <div class="text-muted fs-8" id="summary_customer_good_meta">Bagus 0</div>
            </div>
            <div class="return-report-summary-card">
                <div class="return-report-summary-label">Qty Customer Rusak</div>
                <div class="return-report-summary-value text-danger" id="summary_customer_damaged">0</div>
                <div class="text-muted fs-8" id="summary_customer_lost_meta">Hilang 0</div>
            </div>
            <div class="return-report-summary-card">
                <div class="return-report-summary-label">Qty Retur Outbound</div>
                <div class="return-report-summary-value text-warning" id="summary_outbound_qty">0</div>
            </div>
        </div>
    </div>
</div>

<div class="card return-report-tab-card">
    <div class="card-header border-0 pt-6">
        <ul class="nav nav-tabs nav-line-tabs nav-line-tabs-2x border-transparent fs-6 fw-bold" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" data-bs-toggle="tab" data-return-source="customer" data-bs-target="#tab_return_customer" type="button" role="tab">Retur Customer</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-return-source="outbound" data-bs-target="#tab_return_outbound" type="button" role="tab">Retur Outbound</button>
            </li>
        </ul>
    </div>
    <div class="card-body py-6">
        <div class="tab-content">
            <div class="tab-pane fade show active" id="tab_return_customer" role="tabpanel">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-5" id="customer_returns_report_table">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                <th>No</th>
                                <th>Tanggal</th>
                                <th>Dokumen & Resi</th>
                                <th>Item</th>
                                <th class="text-end">Qty</th>
                                <th>Status & PIC</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            <div class="tab-pane fade" id="tab_return_outbound" role="tabpanel">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-5" id="outbound_returns_report_table">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                <th>No</th>
                                <th>Tanggal</th>
                                <th>Dokumen & Supplier</th>
                                <th>Item</th>
                                <th class="text-end">Qty</th>
                                <th>Status & PIC</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const returnReportDataUrl = @json($dataUrl);
    const returnReportExportUrl = @json($exportUrl);

    document.addEventListener('DOMContentLoaded', () => {
        const searchInput = document.getElementById('report_search');
        const statusFilter = document.getElementById('filter_status');
        const matchFilter = document.getElementById('filter_match_state');
        const matchWrap = document.getElementById('match_state_wrap');
        const dateFromEl = document.getElementById('filter_date_from');
        const dateToEl = document.getElementById('filter_date_to');
        const resetBtn = document.getElementById('filter_reset');
        const exportBtn = document.getElementById('btn_export_return_report');
        let activeSource = 'customer';
        let customerDt = null;
        let outboundDt = null;

        const escapeHtml = (value) => String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');

        const params = (source) => {
            const p = new URLSearchParams();
            p.set('source', source);
            if (searchInput?.value) p.set('q', searchInput.value);
            if (statusFilter?.value) p.set('status', statusFilter.value);
            if (source === 'customer' && matchFilter?.value) p.set('match_state', matchFilter.value);
            if (dateFromEl?.value) p.set('date_from', dateFromEl.value);
            if (dateToEl?.value) p.set('date_to', dateToEl.value);
            return p;
        };

        const updateSummary = (summary) => {
            document.getElementById('summary_total_documents').textContent = summary.total_documents ?? 0;
            document.getElementById('summary_documents_meta').textContent = `Customer ${summary.customer_documents ?? 0} | Outbound ${summary.outbound_documents ?? 0} | Unmatched ${summary.unmatched_resi ?? 0}`;
            document.getElementById('summary_customer_received').textContent = summary.customer_received_qty ?? 0;
            document.getElementById('summary_customer_damaged').textContent = summary.customer_damaged_qty ?? 0;
            document.getElementById('summary_customer_good_meta').textContent = `Bagus ${summary.customer_good_qty ?? 0}`;
            document.getElementById('summary_customer_lost_meta').textContent = `Hilang ${summary.customer_lost_qty ?? 0}`;
            document.getElementById('summary_outbound_qty').textContent = summary.outbound_qty ?? 0;
        };

        const renderDocument = (row) => `
            <div class="d-flex flex-column gap-1">
                <div class="fw-bolder text-gray-900">${escapeHtml(row.code || '-')}</div>
                <div class="text-muted fs-8">${escapeHtml(row.ref_primary_label || 'Ref')}: <span class="fw-semibold">${escapeHtml(row.ref_primary_value || '-')}</span></div>
                <div class="text-muted fs-8">${escapeHtml(row.ref_secondary_label || 'Ref 2')}: <span class="fw-semibold">${escapeHtml(row.ref_secondary_value || '-')}</span></div>
                <div class="text-muted fs-8">${escapeHtml(row.counterparty_label || 'Info')}: <span class="fw-semibold">${escapeHtml(row.counterparty_value || '-')}</span></div>
                ${row.extra_reference ? `<div><span class="badge badge-light-danger">${escapeHtml(row.extra_reference_label || 'Ref')}: ${escapeHtml(row.extra_reference)}</span></div>` : ''}
                ${row.note ? `<div class="text-muted fs-8 mt-1">${escapeHtml(row.note)}</div>` : ''}
            </div>
        `;

        const renderItems = (row) => {
            const parts = String(row.item_summary || '-').split(', ').filter(Boolean).slice(0, 4);
            if (!parts.length || parts[0] === '-') {
                return '<span class="text-muted">Tidak ada item.</span>';
            }

            return `<div class="return-report-item-list">${parts.map((part) => `<div class="return-report-item-chip">${escapeHtml(part)}</div>`).join('')}</div>`;
        };

        const renderCustomerQty = (row) => `
            <div class="return-report-qty-stack">
                <span class="badge badge-light-primary">Resi ${Number(row.qty_expected || 0)}</span>
                <span class="badge badge-light-info">Diterima ${Number(row.qty_received || 0)}</span>
                <span class="badge badge-light-success">Bagus ${Number(row.qty_good || 0)}</span>
                <span class="badge badge-light-danger">Rusak ${Number(row.qty_damaged || 0)}</span>
                <span class="badge badge-light-warning">Hilang ${Number(row.qty_lost || 0)}</span>
            </div>
        `;

        const renderOutboundQty = (row) => `
            <div class="return-report-qty-stack">
                <span class="badge badge-light-warning">Outbound ${Number(row.qty_total || 0)}</span>
            </div>
        `;

        const renderStatus = (row) => `
            <div class="d-flex flex-column gap-1">
                <div><span class="badge ${escapeHtml(row.status_badge || 'badge-light-secondary')}">${escapeHtml(row.status_label || '-')}</span></div>
                <div class="text-muted fs-8">Input: <span class="fw-semibold">${escapeHtml(row.submit_by || '-')}</span></div>
                ${row.secondary_by_label ? `<div class="text-muted fs-8">${escapeHtml(row.secondary_by_label)}: <span class="fw-semibold">${escapeHtml(row.secondary_by || '-')}</span></div>` : ''}
                ${row.tertiary_by_label ? `<div class="text-muted fs-8">${escapeHtml(row.tertiary_by_label)}: <span class="fw-semibold">${escapeHtml(row.tertiary_by || '-')}</span></div>` : ''}
            </div>
        `;

        const commonColumns = (source) => [
            { data: null, orderable: false, searchable: false, render: (data, type, row, meta) => meta.row + meta.settings._iDisplayStart + 1 },
            { data: 'transacted_at' },
            { data: null, orderable: false, searchable: false, render: (data, type, row) => renderDocument(row) },
            { data: null, orderable: false, searchable: false, render: (data, type, row) => renderItems(row) },
            { data: null, orderable: false, searchable: false, className: 'text-end', render: (data, type, row) => source === 'customer' ? renderCustomerQty(row) : renderOutboundQty(row) },
            { data: null, orderable: false, searchable: false, render: (data, type, row) => renderStatus(row) },
            { data: null, orderable: false, searchable: false, className: 'text-end', render: (data, type, row) => row.detail_url ? `<a href="${escapeHtml(row.detail_url)}" class="btn btn-sm btn-light-primary">${escapeHtml(row.detail_label || 'Detail')}</a>` : '<span class="text-muted">-</span>' },
        ];

        const makeTable = (selector, source) => $(selector).DataTable({
            processing: true,
            serverSide: true,
            dom: 'rtip',
            order: [],
            ajax: {
                url: returnReportDataUrl,
                dataSrc: function (json) {
                    if (activeSource === source) {
                        updateSummary(json.summary || {});
                    }
                    return json.data || [];
                },
                data: function (requestParams) {
                    params(source).forEach((value, key) => requestParams[key] = value);
                },
            },
            columns: commonColumns(source),
            language: {
                emptyTable: 'Belum ada data retur yang cocok dengan filter',
                processing: 'Memuat...',
            },
        });

        if (typeof flatpickr !== 'undefined') {
            flatpickr(dateFromEl, { dateFormat: 'Y-m-d', allowInput: true });
            flatpickr(dateToEl, { dateFormat: 'Y-m-d', allowInput: true });
        }

        if (typeof $ !== 'undefined' && $.fn.select2) {
            $(statusFilter).select2({ minimumResultsForSearch: Infinity, width: '100%' });
            $(matchFilter).select2({ minimumResultsForSearch: Infinity, width: '100%' });
        }

        customerDt = makeTable('#customer_returns_report_table', 'customer');
        outboundDt = makeTable('#outbound_returns_report_table', 'outbound');

        const reloadActive = () => {
            if (activeSource === 'customer') {
                customerDt.ajax.reload();
            } else {
                outboundDt.ajax.reload();
            }
        };

        document.querySelectorAll('[data-return-source]').forEach((tab) => {
            tab.addEventListener('shown.bs.tab', () => {
                activeSource = tab.getAttribute('data-return-source') || 'customer';
                if (matchWrap) matchWrap.classList.toggle('d-none', activeSource !== 'customer');
                reloadActive();
            });
        });

        searchInput?.addEventListener('keyup', (event) => {
            if (event.key === 'Enter') reloadActive();
        });
        statusFilter?.addEventListener('change', reloadActive);
        matchFilter?.addEventListener('change', reloadActive);
        dateFromEl?.addEventListener('change', reloadActive);
        dateToEl?.addEventListener('change', reloadActive);
        resetBtn?.addEventListener('click', () => {
            if (searchInput) searchInput.value = '';
            if (statusFilter) {
                statusFilter.value = '';
                if ($(statusFilter).data('select2')) $(statusFilter).val('').trigger('change.select2');
            }
            if (matchFilter) {
                matchFilter.value = '';
                if ($(matchFilter).data('select2')) $(matchFilter).val('').trigger('change.select2');
            }
            if (dateFromEl) dateFromEl.value = '';
            if (dateToEl) dateToEl.value = '';
            reloadActive();
        });
        exportBtn?.addEventListener('click', () => {
            const query = params(activeSource).toString();
            window.location.href = `${returnReportExportUrl}?${query}`;
        });
    });
</script>
@endpush
