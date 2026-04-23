@extends('layouts.admin')

@section('title', 'Laporan Retur')
@section('page_title', 'Laporan Retur')

@section('content')
<div class="card mb-6">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div class="d-flex align-items-center position-relative my-1">
                <span class="svg-icon svg-icon-1 position-absolute ms-6">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <rect opacity="0.5" x="17.0365" y="15.1223" width="8.15546" height="2" rx="1" transform="rotate(45 17.0365 15.1223)" fill="black" />
                        <path d="M11 19C6.55556 19 3 15.4444 3 11C3 6.55556 6.55556 3 11 3C15.4444 3 19 6.55556 19 11C19 15.4444 15.4444 19 11 19ZM11 5C7.53333 5 5 7.53333 5 11C5 14.4667 7.53333 17 11 17C14.4667 17 17 14.4667 17 11C17 7.53333 14.4667 5 11 5Z" fill="black" />
                    </svg>
                </span>
                <input type="text" class="form-control form-control-solid w-250px ps-14" placeholder="Cari kode, resi, supplier, SKU, atau catatan" id="report_search" />
            </div>
        </div>
        <div class="card-toolbar">
            <div class="d-flex align-items-end gap-3 flex-wrap">
                <div class="min-w-180px">
                    <label class="text-muted fs-7 mb-1">Jenis Retur</label>
                    <select id="filter_source" class="form-select form-select-solid w-180px">
                        <option value="">Semua</option>
                        <option value="customer">Retur Customer</option>
                        <option value="outbound">Retur Outbound</option>
                    </select>
                </div>
                <div class="min-w-180px">
                    <label class="text-muted fs-7 mb-1">Status</label>
                    <select id="filter_status" class="form-select form-select-solid w-180px">
                        <option value="">Semua Status</option>
                        <option value="inspected">Menunggu Finalisasi</option>
                        <option value="completed">Selesai</option>
                        <option value="pending">Menunggu Approval</option>
                        <option value="approved">Disetujui</option>
                    </select>
                </div>
                <div class="min-w-180px">
                    <label class="text-muted fs-7 mb-1">Status Resi</label>
                    <select id="filter_match_state" class="form-select form-select-solid w-180px">
                        <option value="">Semua</option>
                        <option value="matched">Resi Ditemukan</option>
                        <option value="unmatched">Input Manual / Tidak Match</option>
                    </select>
                </div>
                <div class="min-w-140px">
                    <label class="text-muted fs-7 mb-1">Dari</label>
                    <input type="text" id="filter_date_from" class="form-control form-control-solid" placeholder="YYYY-MM-DD" />
                </div>
                <div class="min-w-140px">
                    <label class="text-muted fs-7 mb-1">Sampai</label>
                    <input type="text" id="filter_date_to" class="form-control form-control-solid" placeholder="YYYY-MM-DD" />
                </div>
                <div class="min-w-100px">
                    <label class="text-muted fs-7 mb-1">Limit</label>
                    <select id="filter_limit" class="form-select form-select-solid w-100px">
                        <option value="10" selected>10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>
                <div>
                    <button type="button" class="btn btn-light" id="filter_reset">Reset</button>
                </div>
            </div>
        </div>
    </div>
    <div class="card-body pt-2">
        <div class="row g-4">
            <div class="col-md-6 col-xl-3">
                <div class="card bg-light border-0 h-100">
                    <div class="card-body">
                        <div class="text-muted small">Total Dokumen Retur</div>
                        <div class="fs-2 fw-bolder text-gray-900" id="summary_total_documents">0</div>
                        <div class="text-muted small" id="summary_documents_meta">Customer 0 | Outbound 0 | Unmatched 0</div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card bg-light border-0 h-100">
                    <div class="card-body">
                        <div class="text-muted small">Qty Diterima Retur Customer</div>
                        <div class="fs-2 fw-bolder text-primary" id="summary_customer_received">0</div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card bg-light border-0 h-100">
                    <div class="card-body">
                        <div class="text-muted small">Qty Rusak Retur Customer</div>
                        <div class="fs-2 fw-bolder text-danger" id="summary_customer_damaged">0</div>
                        <div class="text-muted small" id="summary_customer_good_meta">Qty Bagus 0</div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card bg-light border-0 h-100">
                    <div class="card-body">
                        <div class="text-muted small">Qty Retur Outbound</div>
                        <div class="fs-2 fw-bolder text-warning" id="summary_outbound_qty">0</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <h3 class="card-label fw-bolder">Detail Laporan Retur</h3>
        </div>
    </div>
    <div class="card-body py-6">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5" id="returns_report_table">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                        <th width="5%">No</th>
                        <th>Tanggal</th>
                        <th>Jenis</th>
                        <th>Dokumen & Referensi</th>
                        <th>Ringkasan Item</th>
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
@endsection

@push('scripts')
<script>
    const dataUrl = '{{ $dataUrl }}';

    document.addEventListener('DOMContentLoaded', () => {
        const tableEl = $('#returns_report_table');
        const searchInput = document.getElementById('report_search');
        const sourceFilter = document.getElementById('filter_source');
        const statusFilter = document.getElementById('filter_status');
        const matchFilter = document.getElementById('filter_match_state');
        const dateFromEl = document.getElementById('filter_date_from');
        const dateToEl = document.getElementById('filter_date_to');
        const limitFilter = document.getElementById('filter_limit');
        const resetBtn = document.getElementById('filter_reset');

        const summaryTotalDocuments = document.getElementById('summary_total_documents');
        const summaryDocumentsMeta = document.getElementById('summary_documents_meta');
        const summaryCustomerReceived = document.getElementById('summary_customer_received');
        const summaryCustomerDamaged = document.getElementById('summary_customer_damaged');
        const summaryCustomerGoodMeta = document.getElementById('summary_customer_good_meta');
        const summaryOutboundQty = document.getElementById('summary_outbound_qty');

        let fpFrom = null;
        let fpTo = null;

        if (!tableEl.length || !$.fn.DataTable) {
            console.error('DataTables unavailable');
            return;
        }

        if (typeof flatpickr !== 'undefined') {
            fpFrom = flatpickr(dateFromEl, { dateFormat: 'Y-m-d', allowInput: true });
            fpTo = flatpickr(dateToEl, { dateFormat: 'Y-m-d', allowInput: true });
        }

        if (typeof $ !== 'undefined' && $.fn.select2) {
            $(sourceFilter).select2({ placeholder: 'Semua', allowClear: true, width: '100%' });
            $(statusFilter).select2({ placeholder: 'Semua', allowClear: true, width: '100%' });
            $(matchFilter).select2({ placeholder: 'Semua', allowClear: true, width: '100%' });
        }

        const escapeHtml = (value) => String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');

        const renderDocumentCell = (row) => {
            const extraRef = row?.extra_reference
                ? `<div class="text-muted fs-8 mt-1">${escapeHtml(row.extra_reference_label || 'Referensi')}: <span class="fw-semibold">${escapeHtml(row.extra_reference)}</span></div>`
                : '';
            const noteBlock = row?.note
                ? `<div class="text-muted fs-8 mt-2">${escapeHtml(row.note)}</div>`
                : '';

            return `
                <div class="d-flex flex-column gap-1">
                    <div class="fw-bolder text-gray-900">${escapeHtml(row.code || '-')}</div>
                    <div class="text-muted fs-8">${escapeHtml(row.ref_primary_label || 'Referensi')}: <span class="fw-semibold">${escapeHtml(row.ref_primary_value || '-')}</span></div>
                    <div class="text-muted fs-8">${escapeHtml(row.ref_secondary_label || 'Referensi Tambahan')}: <span class="fw-semibold">${escapeHtml(row.ref_secondary_value || '-')}</span></div>
                    <div class="text-muted fs-8">${escapeHtml(row.counterparty_label || 'Info')}: <span class="fw-semibold">${escapeHtml(row.counterparty_value || '-')}</span></div>
                    ${extraRef}
                    ${noteBlock}
                </div>
            `;
        };

        const renderItemSummary = (row) => {
            const text = row?.item_summary || '-';
            return `<div class="text-gray-800">${escapeHtml(text)}</div>`;
        };

        const renderQtySummary = (row) => {
            if (row?.report_source === 'customer') {
                return `
                    <div class="d-flex flex-column align-items-end gap-1">
                        <span class="badge badge-light-primary">Diterima ${Number(row.qty_received || 0)}</span>
                        <span class="badge badge-light-success">Bagus ${Number(row.qty_good || 0)}</span>
                        <span class="badge badge-light-danger">Rusak ${Number(row.qty_damaged || 0)}</span>
                    </div>
                `;
            }

            return `
                <div class="d-flex flex-column align-items-end gap-1">
                    <span class="fw-bolder text-warning fs-5">${Number(row.qty_total || 0)}</span>
                    <span class="text-muted fs-8">Total retur keluar</span>
                </div>
            `;
        };

        const renderStatusPic = (row) => {
            const secondary = row?.secondary_by_label
                ? `<div class="text-muted fs-8">${escapeHtml(row.secondary_by_label)}: <span class="fw-semibold">${escapeHtml(row.secondary_by || '-')}</span></div>`
                : '';
            const tertiary = row?.tertiary_by_label
                ? `<div class="text-muted fs-8">${escapeHtml(row.tertiary_by_label)}: <span class="fw-semibold">${escapeHtml(row.tertiary_by || '-')}</span></div>`
                : '';

            return `
                <div class="d-flex flex-column gap-1">
                    <div><span class="badge ${escapeHtml(row.status_badge || 'badge-light-secondary')}">${escapeHtml(row.status_label || '-')}</span></div>
                    <div class="text-muted fs-8">Input By: <span class="fw-semibold">${escapeHtml(row.submit_by || '-')}</span></div>
                    ${secondary}
                    ${tertiary}
                </div>
            `;
        };

        const updateSummary = (summary) => {
            if (summaryTotalDocuments) summaryTotalDocuments.textContent = summary.total_documents ?? 0;
            if (summaryDocumentsMeta) {
                summaryDocumentsMeta.textContent = `Customer ${summary.customer_documents ?? 0} | Outbound ${summary.outbound_documents ?? 0} | Unmatched ${summary.unmatched_resi ?? 0}`;
            }
            if (summaryCustomerReceived) summaryCustomerReceived.textContent = summary.customer_received_qty ?? 0;
            if (summaryCustomerDamaged) summaryCustomerDamaged.textContent = summary.customer_damaged_qty ?? 0;
            if (summaryCustomerGoodMeta) summaryCustomerGoodMeta.textContent = `Qty Bagus ${summary.customer_good_qty ?? 0}`;
            if (summaryOutboundQty) summaryOutboundQty.textContent = summary.outbound_qty ?? 0;
        };

        const dt = tableEl.DataTable({
            processing: true,
            serverSide: true,
            dom: 'rtip',
            order: [],
            pageLength: Number(limitFilter?.value || 10),
            ajax: {
                url: dataUrl,
                dataSrc: function (json) {
                    updateSummary(json?.summary || {});
                    return json.data || [];
                },
                data: function (params) {
                    params.q = searchInput?.value || '';
                    params.source = sourceFilter?.value || '';
                    params.status = statusFilter?.value || '';
                    params.match_state = matchFilter?.value || '';
                    params.date_from = dateFromEl?.value || '';
                    params.date_to = dateToEl?.value || '';
                }
            },
            columns: [
                { data: null, orderable: false, searchable: false, render: (data, type, row, meta) => meta.row + meta.settings._iDisplayStart + 1 },
                { data: 'transacted_at' },
                { data: 'source_label', render: (data, type, row) => `<span class="badge ${escapeHtml(row.source_badge || 'badge-light-secondary')}">${escapeHtml(data || '-')}</span>` },
                { data: null, orderable: false, searchable: false, render: (data, type, row) => renderDocumentCell(row) },
                { data: null, orderable: false, searchable: false, render: (data, type, row) => renderItemSummary(row) },
                { data: null, orderable: false, searchable: false, className: 'text-end', render: (data, type, row) => renderQtySummary(row) },
                { data: null, orderable: false, searchable: false, render: (data, type, row) => renderStatusPic(row) },
                { data: null, orderable: false, searchable: false, className: 'text-end', render: (data, type, row) => {
                    if (!row?.detail_url) return '<span class="text-muted">-</span>';
                    return `<a href="${escapeHtml(row.detail_url)}" class="btn btn-sm btn-light-primary">${escapeHtml(row.detail_label || 'Detail')}</a>`;
                }},
            ],
            language: {
                emptyTable: 'Belum ada data retur yang cocok dengan filter',
                processing: 'Memuat...',
            }
        });

        const reloadTable = () => dt.ajax.reload();

        searchInput?.addEventListener('keyup', (e) => {
            if (e.key === 'Enter') reloadTable();
        });
        sourceFilter?.addEventListener('change', reloadTable);
        statusFilter?.addEventListener('change', reloadTable);
        matchFilter?.addEventListener('change', reloadTable);
        dateFromEl?.addEventListener('change', reloadTable);
        dateToEl?.addEventListener('change', reloadTable);
        limitFilter?.addEventListener('change', () => {
            const val = Number(limitFilter.value || 10);
            dt.page.len(val).draw();
        });
        resetBtn?.addEventListener('click', () => {
            if (searchInput) searchInput.value = '';
            if (sourceFilter) {
                sourceFilter.value = '';
                if (typeof $ !== 'undefined' && $(sourceFilter).data('select2')) {
                    $(sourceFilter).val('').trigger('change.select2');
                }
            }
            if (statusFilter) {
                statusFilter.value = '';
                if (typeof $ !== 'undefined' && $(statusFilter).data('select2')) {
                    $(statusFilter).val('').trigger('change.select2');
                }
            }
            if (matchFilter) {
                matchFilter.value = '';
                if (typeof $ !== 'undefined' && $(matchFilter).data('select2')) {
                    $(matchFilter).val('').trigger('change.select2');
                }
            }
            if (fpFrom) fpFrom.clear(); else if (dateFromEl) dateFromEl.value = '';
            if (fpTo) fpTo.clear(); else if (dateToEl) dateToEl.value = '';
            if (limitFilter) {
                limitFilter.value = '10';
                dt.page.len(10).draw();
            }
            reloadTable();
        });
    });
</script>
@endpush
