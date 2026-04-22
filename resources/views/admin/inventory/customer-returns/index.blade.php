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

    @media (max-width: 767.98px) {
        .customer-return-index-controls {
            width: 100%;
        }

        .customer-return-index-controls .form-select,
        .customer-return-index-controls .btn,
        .customer-return-index-search {
            width: 100% !important;
        }
    }
</style>
@endpush

@section('content')
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div class="d-flex align-items-center position-relative my-1">
                <span class="svg-icon svg-icon-1 position-absolute ms-6">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <rect opacity="0.5" x="17.0365" y="15.1223" width="8.15546" height="2" rx="1" transform="rotate(45 17.0365 15.1223)" fill="black" />
                        <path d="M11 19C6.55556 19 3 15.4444 3 11C3 6.55556 6.55556 3 11 3C15.4444 3 19 6.55556 19 11C19 15.4444 15.4444 19 11 19ZM11 5C7.53333 5 5 7.53333 5 11C5 14.4667 7.53333 17 11 17C14.4667 17 17 14.4667 17 11C17 7.53333 14.4667 5 11 5Z" fill="black" />
                    </svg>
                </span>
                <input type="text" class="form-control form-control-solid w-250px ps-14 customer-return-index-search" placeholder="Search" data-kt-filter="search" />
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
                <span class="text-gray-700">Scan resi dan inspeksi sekarang dilakukan di halaman penuh agar lebih stabil di mobile maupun desktop. Finalisasi stok tetap dilakukan dari list ini.</span>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5" id="customer_returns_table">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                        <th class="w-40px">
                            <div class="form-check form-check-sm form-check-custom form-check-solid">
                                <input class="form-check-input" type="checkbox" id="check_all_customer_returns" />
                            </div>
                        </th>
                        <th>ID</th>
                        <th>Kode</th>
                        <th>Resi</th>
                        <th>Status</th>
                        <th>Tanggal Terima</th>
                        <th>Order Ref</th>
                        <th>Item</th>
                        <th class="text-end">Diterima</th>
                        <th class="text-end">Bagus</th>
                        <th class="text-end">Rusak</th>
                        <th>Catatan</th>
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
            ? '<span class="badge badge-light-primary ms-2">Resi Match</span>'
            : '<span class="badge badge-light-warning ms-2">Manual</span>';

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
                scrollX: true,
                autoWidth: false,
                order: [[5, 'desc']],
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
                                <div class="form-check form-check-sm form-check-custom form-check-solid">
                                    <input class="form-check-input customer-return-checkbox" type="checkbox" value="${row.id}" />
                                </div>
                            `;
                        },
                    },
                    { data: 'id', visible: false },
                    { data: 'code', name: 'code' },
                    {
                        data: null,
                        name: 'resi_no',
                        render: function (data, type, row) {
                            const orderRef = row.order_ref && row.order_ref !== '-' ? `<div class="text-muted fs-7">${row.order_ref}</div>` : '';
                            return `
                                <div class="fw-bold">${row.resi_no || '-'}</div>
                                ${orderRef}
                                ${matchBadgeHtml(row.matched)}
                            `;
                        },
                    },
                    {
                        data: 'status',
                        name: 'status',
                        render: function (data) {
                            return statusBadgeHtml(data);
                        },
                    },
                    { data: 'received_at', name: 'received_at' },
                    { data: 'order_ref', name: 'order_ref' },
                    { data: 'item_summary', name: 'item_summary', orderable: false },
                    { data: 'total_received', className: 'text-end', name: 'total_received', orderable: false },
                    { data: 'total_good', className: 'text-end', name: 'total_good', orderable: false },
                    { data: 'total_damaged', className: 'text-end', name: 'total_damaged', orderable: false },
                    { data: 'note', name: 'note', orderable: false },
                    {
                        data: null,
                        orderable: false,
                        searchable: false,
                        className: 'text-end',
                        render: function (data, type, row) {
                            const buttons = [];
                            buttons.push(`<a href="${customerReturnShowUrlTpl.replace(':id', row.id)}" class="btn btn-sm btn-light">Detail</a>`);

                            if (row.can_finalize && canUpdateCustomerReturn) {
                                buttons.push(`<a href="${customerReturnEditUrlTpl.replace(':id', row.id)}" class="btn btn-sm btn-light-primary">Edit</a>`);
                                buttons.push(`<button type="button" class="btn btn-sm btn-primary btn_finalize_customer_return" data-id="${row.id}">Finalisasi</button>`);
                            }

                            if (row.can_finalize && canDeleteCustomerReturn) {
                                buttons.push(`<button type="button" class="btn btn-sm btn-light-danger btn_delete_customer_return" data-id="${row.id}">Hapus</button>`);
                            }

                            return `<div class="d-flex justify-content-end flex-wrap gap-2">${buttons.join('')}</div>`;
                        },
                    },
                ],
            });
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
