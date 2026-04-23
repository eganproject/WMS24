@extends('layouts.admin')

@section('title', 'Retur Customer')
@section('page_title', 'Retur Customer')

@php
    use App\Support\Permission as Perm;

    $canCreate = Perm::can(auth()->user(), 'admin.inventory.customer-returns.index', 'create');
    $canUpdate = Perm::can(auth()->user(), 'admin.inventory.customer-returns.index', 'update');
    $canDelete = Perm::can(auth()->user(), 'admin.inventory.customer-returns.index', 'delete');
@endphp

@push('styles')
<style>
    .customer-return-index-card {
        background: linear-gradient(180deg, #ffffff 0%, #fbfcff 100%);
    }

    .customer-return-index-toolbar {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
        align-items: center;
        justify-content: space-between;
    }

    .customer-return-index-controls {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
        align-items: center;
    }

    .customer-return-index-controls .form-select,
    .customer-return-index-controls .btn {
        min-width: 180px;
    }

    .customer-return-index-search {
        min-width: 280px;
    }

    .customer-return-index-table-wrap .dataTables_wrapper {
        overflow: visible;
    }

    #customer_returns_table {
        width: 100% !important;
        border-collapse: separate;
        border-spacing: 0 0.85rem;
    }

    #customer_returns_table thead th {
        border: 0 !important;
        padding-top: 0;
        padding-bottom: 0.35rem;
        white-space: nowrap;
    }

    #customer_returns_table tbody td {
        background: #fff;
        border-top: 1px solid #eef2f7;
        border-bottom: 1px solid #eef2f7;
        vertical-align: top;
        padding-top: 1rem;
        padding-bottom: 1rem;
        white-space: normal;
    }

    #customer_returns_table tbody td:first-child {
        border-left: 1px solid #eef2f7;
        border-top-left-radius: 1rem;
        border-bottom-left-radius: 1rem;
    }

    #customer_returns_table tbody td:last-child {
        border-right: 1px solid #eef2f7;
        border-top-right-radius: 1rem;
        border-bottom-right-radius: 1rem;
    }

    .customer-return-row-select {
        display: flex;
        justify-content: center;
        padding-top: 0.2rem;
    }

    .customer-return-doc-title {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        align-items: center;
        margin-bottom: 0.35rem;
    }

    .customer-return-doc-meta,
    .customer-return-doc-submeta,
    .customer-return-meta-line {
        display: flex;
        flex-wrap: wrap;
        gap: 0.35rem 0.6rem;
        align-items: center;
    }

    .customer-return-doc-submeta,
    .customer-return-meta-line + .customer-return-meta-line {
        margin-top: 0.35rem;
    }

    .customer-return-meta-label {
        color: #7e8299;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        font-weight: 700;
    }

    .customer-return-item-list {
        display: flex;
        flex-direction: column;
        gap: 0.45rem;
    }

    .customer-return-item-chip {
        display: inline-flex;
        align-items: center;
        width: 100%;
        padding: 0.55rem 0.75rem;
        border-radius: 0.9rem;
        background: #f8f9fc;
        color: #3f4254;
        font-size: 0.825rem;
        line-height: 1.35;
    }

    .customer-return-note-box {
        margin-top: 0.75rem;
        padding: 0.7rem 0.8rem;
        border-radius: 0.9rem;
        background: #fff8dd;
        color: #5e6278;
        font-size: 0.825rem;
        line-height: 1.45;
    }

    .customer-return-qty-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.55rem;
        min-width: 250px;
    }

    .customer-return-qty-pill {
        border-radius: 0.95rem;
        padding: 0.75rem 0.7rem;
        text-align: center;
        border: 1px solid transparent;
    }

    .customer-return-qty-pill.is-total {
        background: #f8f9fc;
        border-color: #eef2f7;
    }

    .customer-return-qty-pill.is-good {
        background: #e8fff3;
        border-color: #d4f5e3;
    }

    .customer-return-qty-pill.is-damaged {
        background: #fff5f8;
        border-color: #ffd5e2;
    }

    .customer-return-qty-label {
        display: block;
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #7e8299;
        font-weight: 700;
        margin-bottom: 0.25rem;
    }

    .customer-return-qty-value {
        display: block;
        font-size: 1.15rem;
        font-weight: 700;
        color: #181c32;
        line-height: 1;
    }

    .customer-return-action-cell .btn {
        white-space: nowrap;
    }

    .customer-return-table-empty {
        color: #a1a5b7;
        font-size: 0.85rem;
    }

    @media (max-width: 767.98px) {
        .customer-return-index-toolbar {
            display: grid;
        }

        .customer-return-index-controls {
            width: 100%;
        }

        .customer-return-index-controls .form-select,
        .customer-return-index-controls .btn,
        .customer-return-index-search {
            width: 100% !important;
        }

        .customer-return-index-search {
            min-width: 100%;
        }

        #customer_returns_table thead {
            display: none;
        }

        #customer_returns_table,
        #customer_returns_table tbody,
        #customer_returns_table tr,
        #customer_returns_table td {
            display: block;
            width: 100% !important;
        }

        #customer_returns_table {
            border-spacing: 0 1rem;
        }

        #customer_returns_table tbody td {
            border-left: 1px solid #eef2f7;
            border-right: 1px solid #eef2f7;
            border-radius: 0;
            padding: 0.75rem 1rem;
        }

        #customer_returns_table tbody td:first-child {
            border-top-left-radius: 1rem;
            border-top-right-radius: 1rem;
            padding-bottom: 0.25rem;
        }

        #customer_returns_table tbody td:last-child {
            border-bottom-left-radius: 1rem;
            border-bottom-right-radius: 1rem;
            padding-top: 0.35rem;
        }

        #customer_returns_table tbody td.text-end {
            text-align: left !important;
        }

        .customer-return-row-select {
            justify-content: flex-end;
        }

        .customer-return-qty-grid {
            min-width: 0;
        }

        .customer-return-action-cell {
            padding-top: 0.5rem !important;
        }
    }
</style>
@endpush

@section('content')
<div class="card customer-return-index-card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div class="d-flex align-items-center position-relative my-1">
                <span class="svg-icon svg-icon-1 position-absolute ms-6">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <rect opacity="0.5" x="17.0365" y="15.1223" width="8.15546" height="2" rx="1" transform="rotate(45 17.0365 15.1223)" fill="black" />
                        <path d="M11 19C6.55556 19 3 15.4444 3 11C3 6.55556 6.55556 3 11 3C15.4444 3 19 6.55556 19 11C19 15.4444 15.4444 19 11 19ZM11 5C7.53333 5 5 7.53333 5 11C5 14.4667 7.53333 17 11 17C14.4667 17 17 14.4667 17 11C17 7.53333 14.4667 5 11 5Z" fill="black" />
                    </svg>
                </span>
                <input type="text" class="form-control form-control-solid w-250px ps-14 customer-return-index-search" placeholder="Cari kode, resi, order ref, SKU, atau catatan" data-kt-filter="search" />
            </div>
        </div>
        <div class="card-toolbar customer-return-index-toolbar">
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <span class="badge badge-light-success">Masuk ke {{ $displayWarehouseLabel }}</span>
                <span class="badge badge-light-danger">Rusak ke {{ $damagedWarehouseLabel }}</span>
            </div>
            <div class="customer-return-index-controls">
                <select class="form-select form-select-solid w-180px" id="filter_status">
                    <option value="">Semua Status</option>
                    <option value="inspected">Belum Finalisasi</option>
                    <option value="completed">Selesai</option>
                </select>
                @if($canUpdate)
                    <button type="button" class="btn btn-light-primary" id="btn_finalize_selected">Finalisasi Terpilih</button>
                @endif
                @if($canCreate)
                    <a href="{{ $createUrl }}" class="btn btn-primary">Tambah</a>
                @endif
            </div>
        </div>
    </div>
    <div class="card-body py-6">
        <div class="alert alert-light-primary d-flex align-items-start p-5 mb-8">
            <span class="svg-icon svg-icon-2hx svg-icon-primary me-4 mt-1">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <path opacity="0.3" d="M12 22C17.523 22 22 17.523 22 12C22 6.477 17.523 2 12 2C6.477 2 2 6.477 2 12C2 17.523 6.477 22 12 22Z" fill="black" />
                    <path d="M12 7C11.45 7 11 7.45 11 8V13C11 13.55 11.45 14 12 14C12.55 14 13 13.55 13 13V8C13 7.45 12.55 7 12 7ZM12 17C11.45 17 11 17.45 11 18C11 18.55 11.45 19 12 19C12.55 19 13 18.55 13 18C13 17.45 12.55 17 12 17Z" fill="black" />
                </svg>
            </span>
            <div class="d-flex flex-column">
                <h4 class="mb-1 text-gray-800">Flow Retur Customer</h4>
                <span class="text-gray-700">List ini menampilkan retur yang sudah diinspeksi. Barang bagus akan masuk ke stok display dan barang rusak akan masuk ke stok barang rusak saat finalisasi.</span>
            </div>
        </div>

        <div class="table-responsive customer-return-index-table-wrap">
            <table class="table align-middle table-row-dashed fs-6 gy-5" id="customer_returns_table">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                        <th class="w-40px">
                            <div class="form-check form-check-sm form-check-custom form-check-solid">
                                <input class="form-check-input" type="checkbox" id="check_all_customer_returns" />
                            </div>
                        </th>
                        <th>Dokumen & Status</th>
                        <th>Waktu & PIC</th>
                        <th>Ringkasan Item</th>
                        <th>Qty</th>
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
    const customerReturnDataUrl = @json($dataUrl);
    const customerReturnFinalizeUrl = @json($finalizeUrl);
    const customerReturnShowUrlTpl = @json(route('admin.inventory.customer-returns.show', ':id'));
    const customerReturnEditUrlTpl = @json(route('admin.inventory.customer-returns.edit', ':id'));
    const customerReturnDeleteUrlTpl = @json(route('admin.inventory.customer-returns.destroy', ':id'));
    const csrfToken = @json(csrf_token());
    const canUpdateCustomerReturn = {{ $canUpdate ? 'true' : 'false' }};
    const canDeleteCustomerReturn = {{ $canDelete ? 'true' : 'false' }};

    document.addEventListener('DOMContentLoaded', () => {
        const tableEl = $('#customer_returns_table');
        const searchInput = document.querySelector('[data-kt-filter="search"]');
        const statusFilter = document.getElementById('filter_status');
        const checkAllEl = document.getElementById('check_all_customer_returns');
        const finalizeSelectedBtn = document.getElementById('btn_finalize_selected');
        let dt = null;

        const escapeHtml = (value) => String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');

        const debounce = (fn, wait = 300) => {
            let timeoutId = null;
            return (...args) => {
                clearTimeout(timeoutId);
                timeoutId = setTimeout(() => fn(...args), wait);
            };
        };

        const statusBadgeHtml = (status) => {
            if (status === 'completed') {
                return '<span class="badge badge-light-success">Selesai</span>';
            }

            return '<span class="badge badge-light-warning">Belum Finalisasi</span>';
        };

        const matchBadgeHtml = (matched) => matched
            ? '<span class="badge badge-light-primary">Resi Ditemukan</span>'
            : '<span class="badge badge-light-warning">Input Manual</span>';

        const refreshMenus = () => {
            if (typeof KTMenu !== 'undefined' && KTMenu.createInstances) {
                KTMenu.createInstances();
            }
        };

        const documentCellHtml = (row) => {
            const orderRef = row.order_ref && row.order_ref !== '-' ? row.order_ref : 'Tanpa order ref';
            const damagedGood = row.damaged_good_code
                ? `<div class="customer-return-doc-submeta"><span class="badge badge-light-danger">Barang Rusak ${escapeHtml(row.damaged_good_code)}</span></div>`
                : '';

            return `
                <div class="customer-return-doc-cell">
                    <div class="customer-return-doc-title">
                        <span class="fw-bolder text-gray-900">${escapeHtml(row.code || '-')}</span>
                        ${statusBadgeHtml(row.status)}
                        ${matchBadgeHtml(row.matched)}
                    </div>
                    <div class="customer-return-doc-meta">
                        <span class="customer-return-meta-label">Resi</span>
                        <span class="fw-semibold text-gray-800">${escapeHtml(row.resi_no || '-')}</span>
                    </div>
                    <div class="customer-return-doc-submeta">
                        <span class="customer-return-meta-label">Order Ref</span>
                        <span class="text-gray-700">${escapeHtml(orderRef)}</span>
                    </div>
                    ${damagedGood}
                </div>
            `;
        };

        const activityCellHtml = (row) => {
            const finalizedLine = row.finalized_at
                ? `
                    <div class="customer-return-meta-line">
                        <span class="customer-return-meta-label">Finalisasi</span>
                        <span class="text-gray-800 fw-semibold">${escapeHtml(row.finalized_at)}</span>
                    </div>
                  `
                : '';

            const picFinal = row.finalized_by && row.finalized_by !== '-'
                ? `
                    <div class="customer-return-meta-line">
                        <span class="customer-return-meta-label">PIC Final</span>
                        <span class="text-gray-700">${escapeHtml(row.finalized_by)}</span>
                    </div>
                  `
                : '';

            return `
                <div class="customer-return-meta-cell">
                    <div class="customer-return-meta-line">
                        <span class="customer-return-meta-label">Diterima</span>
                        <span class="text-gray-800 fw-semibold">${escapeHtml(row.received_at || '-')}</span>
                    </div>
                    <div class="customer-return-meta-line">
                        <span class="customer-return-meta-label">Dicatat</span>
                        <span class="text-gray-700">${escapeHtml(row.submit_by || '-')}</span>
                    </div>
                    <div class="customer-return-meta-line">
                        <span class="customer-return-meta-label">Inspector</span>
                        <span class="text-gray-700">${escapeHtml(row.inspected_by || '-')}</span>
                    </div>
                    ${finalizedLine}
                    ${picFinal}
                </div>
            `;
        };

        const itemCellHtml = (row) => {
            const items = String(row.item_summary || '')
                .split(', ')
                .map((part) => part.trim())
                .filter((part) => part && part !== '-');

            const visibleItems = items.slice(0, 3).map((part) => `<div class="customer-return-item-chip">${escapeHtml(part)}</div>`).join('');
            const moreItems = items.length > 3
                ? `<div class="text-muted fs-8 mt-2">+${items.length - 3} item lain</div>`
                : '';
            const emptyState = !items.length ? '<div class="customer-return-table-empty">Belum ada ringkasan item.</div>' : '';
            const noteBox = row.note
                ? `<div class="customer-return-note-box"><span class="fw-bold text-gray-800">Catatan:</span> ${escapeHtml(row.note)}</div>`
                : '';

            return `
                <div class="customer-return-items-cell">
                    <div class="customer-return-item-list">
                        ${visibleItems || emptyState}
                    </div>
                    ${moreItems}
                    ${noteBox}
                </div>
            `;
        };

        const qtyCellHtml = (row) => `
            <div class="customer-return-qty-grid">
                <div class="customer-return-qty-pill is-total">
                    <span class="customer-return-qty-label">Diterima</span>
                    <span class="customer-return-qty-value">${Number(row.total_received || 0)}</span>
                </div>
                <div class="customer-return-qty-pill is-good">
                    <span class="customer-return-qty-label">Bagus</span>
                    <span class="customer-return-qty-value">${Number(row.total_good || 0)}</span>
                </div>
                <div class="customer-return-qty-pill is-damaged">
                    <span class="customer-return-qty-label">Rusak</span>
                    <span class="customer-return-qty-value">${Number(row.total_damaged || 0)}</span>
                </div>
            </div>
        `;

        const actionMenuHtml = (row) => {
            const actions = [];
            actions.push(`<div class="menu-item px-3"><a href="${customerReturnShowUrlTpl.replace(':id', row.id)}" class="menu-link px-3">Detail</a></div>`);

            if (row.can_finalize && canUpdateCustomerReturn) {
                actions.push(`<div class="menu-item px-3"><a href="${customerReturnEditUrlTpl.replace(':id', row.id)}" class="menu-link px-3">Edit</a></div>`);
                actions.push(`<div class="menu-item px-3"><a href="#" class="menu-link px-3 text-primary btn_finalize_customer_return" data-id="${row.id}">Finalisasi</a></div>`);
            }

            if (row.can_finalize && canDeleteCustomerReturn) {
                actions.push(`<div class="menu-item px-3"><a href="#" class="menu-link px-3 text-danger btn_delete_customer_return" data-id="${row.id}">Hapus</a></div>`);
            }

            return `
                <div class="customer-return-action-cell text-end">
                    <a href="#" class="btn btn-sm btn-light btn-active-light-primary" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                        Aksi
                        <span class="svg-icon svg-icon-5 m-0">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                <path d="M11.4343 12.7344L7.25 8.55005C6.83579 8.13583 6.16421 8.13584 5.75 8.55005C5.33579 8.96426 5.33579 9.63583 5.75 10.05L11.2929 15.5929C11.6834 15.9835 12.3166 15.9835 12.7071 15.5929L18.25 10.05C18.6642 9.63584 18.6642 8.96426 18.25 8.55005C17.8358 8.13584 17.1642 8.13584 16.75 8.55005L12.5657 12.7344C12.2533 13.0468 11.7467 13.0468 11.4343 12.7344Z" fill="black"></path>
                            </svg>
                        </span>
                    </a>
                    <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-600 menu-state-bg-light-primary fw-bold fs-7 w-175px py-3" data-kt-menu="true">
                        ${actions.join('')}
                    </div>
                </div>
            `;
        };

        const showToast = (type, message) => {
            if (typeof Swal !== 'undefined' && Swal.fire) {
                Swal.fire({
                    text: message,
                    icon: type,
                    buttonsStyling: false,
                    confirmButtonText: 'OK',
                    customClass: { confirmButton: 'btn btn-primary' }
                });
                return;
            }

            alert(message);
        };

        const collectSelectedIds = () => Array.from(document.querySelectorAll('.customer-return-checkbox:checked'))
            .map((checkbox) => Number(checkbox.value))
            .filter((value) => value > 0);

        const submitFinalize = (ids) => {
            if (!ids.length) {
                showToast('warning', 'Pilih minimal 1 retur customer yang belum difinalisasi.');
                return;
            }

            const doRequest = () => $.ajax({
                url: customerReturnFinalizeUrl,
                method: 'POST',
                data: {
                    _token: csrfToken,
                    ids,
                },
            }).done((response) => {
                if (checkAllEl) checkAllEl.checked = false;
                dt.ajax.reload(null, false);
                showToast('success', response.message || 'Retur customer berhasil difinalisasi.');
            }).fail((xhr) => {
                const message = xhr.responseJSON?.message || xhr.responseJSON?.errors?.ids?.[0] || 'Gagal memfinalisasi retur customer.';
                showToast('error', message);
            });

            if (typeof Swal !== 'undefined' && Swal.fire) {
                Swal.fire({
                    text: `Finalisasi ${ids.length} retur customer ke stok display / barang rusak?`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, finalisasi',
                    cancelButtonText: 'Batal',
                    buttonsStyling: false,
                    customClass: {
                        confirmButton: 'btn btn-primary',
                        cancelButton: 'btn btn-light',
                    },
                }).then((result) => {
                    if (result.isConfirmed) {
                        doRequest();
                    }
                });
                return;
            }

            doRequest();
        };

        if (statusFilter && typeof $ !== 'undefined' && $.fn.select2) {
            $(statusFilter).select2({ minimumResultsForSearch: Infinity, width: '180px' });
        }

        if (tableEl.length && $.fn.DataTable) {
            dt = tableEl.DataTable({
                processing: true,
                serverSide: true,
                dom: 'rtip',
                autoWidth: false,
                ajax: {
                    url: customerReturnDataUrl,
                    dataSrc: 'data',
                    data: function (params) {
                        params.q = searchInput?.value || '';
                        params.status = statusFilter?.value || '';
                    },
                },
                columns: [
                    {
                        data: null,
                        orderable: false,
                        searchable: false,
                        className: 'text-center',
                        render: function (data, type, row) {
                            if (!row.can_finalize || !canUpdateCustomerReturn) {
                                return '';
                            }

                            return `
                                <div class="form-check form-check-sm form-check-custom form-check-solid customer-return-row-select">
                                    <input class="form-check-input customer-return-checkbox" type="checkbox" value="${row.id}" />
                                </div>
                            `;
                        },
                    },
                    {
                        data: null,
                        name: 'code',
                        render: function (data, type, row) {
                            if (type !== 'display') {
                                return `${row.code || ''} ${row.resi_no || ''} ${row.order_ref || ''} ${row.status || ''}`;
                            }

                            return documentCellHtml(row);
                        },
                    },
                    {
                        data: null,
                        name: 'received_at',
                        render: function (data, type, row) {
                            if (type !== 'display') {
                                return `${row.received_at || ''} ${row.submit_by || ''} ${row.inspected_by || ''} ${row.finalized_at || ''} ${row.finalized_by || ''}`;
                            }

                            return activityCellHtml(row);
                        },
                    },
                    {
                        data: null,
                        name: 'item_summary',
                        orderable: false,
                        render: function (data, type, row) {
                            if (type !== 'display') {
                                return `${row.item_summary || ''} ${row.note || ''}`;
                            }

                            return itemCellHtml(row);
                        },
                    },
                    {
                        data: null,
                        orderable: false,
                        searchable: false,
                        render: function (data, type, row) {
                            if (type !== 'display') {
                                return `${row.total_received || 0} ${row.total_good || 0} ${row.total_damaged || 0}`;
                            }

                            return qtyCellHtml(row);
                        },
                    },
                    {
                        data: null,
                        orderable: false,
                        searchable: false,
                        className: 'text-end',
                        render: function (data, type, row) {
                            if (type !== 'display') {
                                return row.id;
                            }

                            return actionMenuHtml(row);
                        },
                    },
                ],
                drawCallback: function () {
                    refreshMenus();
                },
            });

            refreshMenus();
        }

        searchInput?.addEventListener('input', debounce(() => dt?.ajax.reload(), 300));
        statusFilter?.addEventListener('change', () => dt?.ajax.reload());

        checkAllEl?.addEventListener('change', (event) => {
            const checked = !!event.target.checked;
            document.querySelectorAll('.customer-return-checkbox').forEach((checkbox) => {
                checkbox.checked = checked;
            });
        });

        finalizeSelectedBtn?.addEventListener('click', () => {
            submitFinalize(collectSelectedIds());
        });

        tableEl.on('click', '.btn_finalize_customer_return', function () {
            const id = Number($(this).data('id'));
            submitFinalize([id]);
        });

        tableEl.on('click', '.btn_delete_customer_return', function () {
            const id = $(this).data('id');
            const url = customerReturnDeleteUrlTpl.replace(':id', id);

            const doDelete = () => $.ajax({
                url,
                method: 'POST',
                data: {
                    _token: csrfToken,
                    _method: 'DELETE',
                },
            }).done((response) => {
                dt?.ajax.reload(null, false);
                showToast('success', response.message || 'Retur customer berhasil dihapus.');
            }).fail((xhr) => {
                showToast('error', xhr.responseJSON?.message || 'Gagal menghapus retur customer.');
            });

            if (typeof Swal !== 'undefined' && Swal.fire) {
                Swal.fire({
                    text: 'Hapus retur customer ini?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, hapus',
                    cancelButtonText: 'Batal',
                    buttonsStyling: false,
                    customClass: {
                        confirmButton: 'btn btn-danger',
                        cancelButton: 'btn btn-light',
                    },
                }).then((result) => {
                    if (result.isConfirmed) {
                        doDelete();
                    }
                });
                return;
            }

            doDelete();
        });
    });
</script>
@endpush
