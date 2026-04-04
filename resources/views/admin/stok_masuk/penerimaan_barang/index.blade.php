@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <style>
        .status-card-icon .symbol-label { background: #F3E8FF; }
        .status-card-icon i { color: #7C3AED; }
        .status-metrics { display: flex; gap: 1.5rem; align-items: center; flex-wrap: wrap; justify-content: flex-end; }
        .status-metrics .metric { text-align: center; min-width: 88px; }
        .status-metrics .chip { display: inline-block; padding: 6px 12px; border-radius: 10px; font-weight: 700; font-size: 1rem; line-height: 1; }
        .chip-secondary { background: #EEF2FF; color: #4F46E5; }
        .chip-warning { background: #FEF3C7; color: #D97706; }
        .chip-success { background: #ECFDF5; color: #047857; }
        @media (max-width: 576px) { .status-metrics { justify-content: flex-start; } .status-metrics .metric { min-width: 70px; } }
    </style>
@endpush

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Penerimaan Barang',
        'breadcrumbs' => ['Admin', 'Stok Masuk', 'Penerimaan Barang'],
    ])
@endpush

@php
    $permissionResolver = app(\App\Support\MenuPermissionResolver::class);
    $canCreate = $permissionResolver->userCan('create');
    $canEdit = $permissionResolver->userCan('edit');
    $canDelete = $permissionResolver->userCan('delete');
    $canApprove = $permissionResolver->userCan('approve');
@endphp

@section('content')
    <div class="content flex-row-fluid" id="kt_content">
        <div class="row g-5 g-xl-8">
            <div class="col-12">
                <div class="card card-xl-stretch mb-xl-8">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between flex-wrap">
                            <div class="d-flex align-items-center mb-4 mb-md-0">
                                <div class="symbol symbol-50px me-5 status-card-icon">
                                    <div class="symbol-label">
                                        <i class="fas fa-clipboard-check fs-2x"></i>
                                    </div>
                                </div>
                                <div>
                                    <div class="text-dark fw-bolder fs-5 mb-1">Status Penerimaan Barang</div>
                                    <div class="text-muted fw-bold">Dokumen Goods Receipt</div>
                                </div>
                            </div>
                            <div class="status-metrics">
                                <div class="metric">
                                    <div class="chip chip-secondary" id="status_draft">
                                        <div class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">Loading...</span></div>
                                    </div>
                                    <div class="text-muted fs-8 mt-1">Draft</div>
                                </div>
                                <div class="metric">
                                    <div class="chip chip-warning" id="status_partial">
                                        <div class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">Loading...</span></div>
                                    </div>
                                    <div class="text-muted fs-8 mt-1">Partial</div>
                                </div>
                                <div class="metric">
                                    <div class="chip chip-success" id="status_completed">
                                        <div class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">Loading...</span></div>
                                    </div>
                                    <div class="text-muted fs-8 mt-1">Completed</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header border-0 pt-6">
                <div class="card-title">
                    <div class="d-flex align-items-center position-relative my-1">
                        <span class="svg-icon svg-icon-1 position-absolute ms-6">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                <rect opacity="0.5" x="17.0365" y="15.1223" width="8.15546" height="2" rx="1" transform="rotate(45 17.0365 15.1223)" fill="black"></rect>
                                <path d="M11 19C6.55556 19 3 15.4444 3 11C3 6.55556 6.55556 3 11 3C15.4444 3 19 6.55556 19 11C19 15.4444 15.4444 19 11 19ZM11 5C7.53333 5 5 7.53333 5 11C5 14.4667 7.53333 17 11 17C14.4667 17 17 14.4667 17 11C17 7.53333 14.4667 5 11 5Z" fill="black" />
                            </svg>
                        </span>
                        <input type="text" id="search_input" class="form-control form-control-solid w-250px ps-15"
                            placeholder="Cari Penerimaan Barang">
                    </div>
                </div>
                <div class="card-toolbar">
                    @if($canCreate)
                    <a href="{{ route('admin.stok-masuk.penerimaan-barang.create') }}" class="btn btn-primary me-3">
                        <i class="fas fa-plus me-1"></i>Tambah Penerimaan Barang
                    </a>
                    @endif
                    <div class="d-flex justify-content-end" data-kt-customer-table-toolbar="base">
                        <button type="button" class="btn btn-light-primary" data-kt-menu-trigger="click"
                            data-kt-menu-placement="bottom-end">
                            <span class="svg-icon svg-icon-2">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                    fill="none">
                                    <path d="M19.0759 3H4.72777C3.95892 3 3.47768 3.83148 3.86067 4.49814L8.56967 12.6949C9.17923 13.7559 9.5 14.9582 9.5 16.1819V19.5072C9.5 20.2189 10.2223 20.7028 10.8805 20.432L13.8805 19.1977C14.2553 19.0435 14.5 18.6783 14.5 18.273V13.8372C14.5 12.8089 14.8171 11.8056 15.408 10.964L19.8943 4.57465C20.3596 3.912 19.8856 3 19.0759 3Z" fill="black" />
                                </svg>
                            </span>
                            Filter
                        </button>
                        <div class="menu menu-sub menu-sub-dropdown w-300px w-md-325px" data-kt-menu="true" id="kt-toolbar-filter">
                            <div class="px-7 py-5">
                                <div class="fs-4 text-dark fw-bolder">Filter Options</div>
                            </div>
                            <div class="separator border-gray-200"></div>
                            <div class="px-7 py-5">
                                <div class="mb-10">
                                    <label class="form-label fs-5 fw-bold mb-3">Gudang:</label>
                                    <select class="form-select form-select-solid fw-bolder" data-kt-select2="true"
                                        id="warehouse_filter" data-dropdown-parent="#kt-toolbar-filter">
                                        <option value="semua">Semua</option>
                                        @foreach ($warehouses as $warehouse)
                                            <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="mb-10">
                                    <label class="form-label fs-5 fw-bold mb-3">Status:</label>
                                    <select class="form-select form-select-solid fw-bolder" data-kt-select2="true"
                                        id="status_filter" data-dropdown-parent="#kt-toolbar-filter">
                                        <option value="semua">Semua</option>
                                        <option value="draft">Draft</option>
                                        <option value="partial">Partial</option>
                                        <option value="completed">Completed</option>
                                    </select>
                                </div>
                                <div class="mb-10">
                                    <label class="form-label fs-5 fw-bold mb-3">Tanggal:</label>
                                    <input class="form-control form-control-solid" placeholder="Pilih Tanggal"
                                        id="date_filter" />
                                </div>
                                <div class="d-flex justify-content-end">
                                    <button type="reset" class="btn btn-light btn-active-light-primary me-2"
                                        data-kt-menu-dismiss="true">Batal</button>
                                    <button type="button" class="btn btn-primary" id="apply_filter">Terapkan</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body pt-4">
                <div class="text-center mb-5">
                    <h3 class="mb-0">Daftar Goods Receipt</h3>
                    <small id="filter-info" class="text-muted"></small>
                </div>
                <div class="dataTables_wrapper dt-bootstrap4 no-footer">
                    <div class="table-responsive min-h-500px">
                        <table class="table align-middle table-row-dashed fs-6 gy-5 dataTable no-footer" id="table-on-page">
                            <thead>
                                <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                    <th class="min-w-125px sorting">Kode</th>
                                    <th class="min-w-125px sorting">Tanggal</th>
                                    <th class="min-w-100px sorting">Tipe</th>
                                    <th class="min-w-150px sorting">Pengiriman</th>
                                    <th class="min-w-150px sorting">Gudang</th>
                                    <th class="min-w-125px sorting">Status</th>
                                    <th class="min-w-150px sorting">Diterima Oleh</th>
                                    <th class="text-center min-w-100px sorting_disabled">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="fw-bold text-gray-600"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            const canEdit = @json($canEdit);
            const canDelete = @json($canDelete);
            const canApprove = @json($canApprove);
            const forcedWarehouseId = @json(optional(auth()->user())->warehouse_id);

            toastr.options = {
                "closeButton": true,
                "progressBar": true,
                "positionClass": "toast-top-center",
            };

            $('#date_filter').flatpickr({
                dateFormat: 'Y-m-d'
            });

            @if (Session::has('success'))
                toastr.success("{{ session('success') }}");
            @endif

            @if (Session::has('error'))
                toastr.error("{{ session('error') }}");
            @endif

            const debounce = (callback, wait = 400) => {
                let timeoutId;
                return (...args) => {
                    clearTimeout(timeoutId);
                    timeoutId = setTimeout(() => callback.apply(null, args), wait);
                };
            };

            function loadStatusCounts() {
                $.ajax({
                    url: "{{ route('admin.stok-masuk.penerimaan-barang.status-counts') }}",
                    type: "GET",
                    data: {
                        warehouse_id: $('#warehouse_filter').val(),
                        status: $('#status_filter').val(),
                        date: $('#date_filter').val()
                    },
                    success: function(response) {
                        $('#status_draft').text(response.draft ?? 0);
                        $('#status_partial').text(response.partial ?? 0);
                        $('#status_completed').text(response.completed ?? 0);
                    }
                });
            }

            let table;

            // If user has fixed warehouse, preselect and lock the filter
            if (forcedWarehouseId !== null) {
                $('#warehouse_filter').val(String(forcedWarehouseId)).trigger('change');
                $('#warehouse_filter').prop('disabled', true);
            }

            function loadDataTable() {
                if ($.fn.DataTable.isDataTable('#table-on-page')) {
                    $('#table-on-page').DataTable().destroy();
                }

                table = $('#table-on-page').DataTable({
                    processing: true,
                    serverSide: true,
                    ajax: {
                        url: "{{ route('admin.stok-masuk.penerimaan-barang.index') }}",
                        type: "GET",
                        data: function(d) {
                            d.search.value = $('#search_input').val();
                            d.status = $('#status_filter').val();
                            d.warehouse_id = $('#warehouse_filter').val();
                            d.date = $('#date_filter').val();
                        }
                    },
                    columns: [
                        { data: 'code', name: 'gr.code' },
                        { data: 'receipt_date', name: 'gr.receipt_date' },
                        { data: 'type', name: 'gr.type' },
                        { data: 'shipment_code', name: 's.code' },
                        { data: 'warehouse_name', name: 'w.name' },
                        { data: 'status', name: 'gr.status' },
                        { data: 'receiver_name', name: 'u.name' },
                        { data: 'id', name: 'gr.id', orderable: false, searchable: false },
                    ],
                    order: [
                        [1, 'desc']
                    ],
                    columnDefs: [
                        {
                            targets: 1,
                            render: function(data) {
                                if (!data) return '-';
                                const d = new Date(data);
                                return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
                            }
                        },
                        {
                            targets: 2,
                            render: function(data) {
                                if (!data) return '-';
                                const label = data.replace(/_/g,' ').replace(/\b\w/g, c => c.toUpperCase());
                                const badge = data === 'transfer' ? 'badge-light-primary' : 'badge-light-info';
                                return `<span class="badge ${badge}">${label}</span>`;
                            }
                        },
                        {
                            targets: 5,
                            render: function(data) {
                                const label = data ? data.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) : '-';
                                let badgeClass = 'badge-light-secondary';
                                if (data === 'partial') badgeClass = 'badge-light-warning';
                                if (data === 'completed') badgeClass = 'badge-light-success';
                                return `<span class="badge ${badgeClass}">${label}</span>`;
                            }
                        },
                        {
                            targets: 7,
                            render: function(data, type, row) {
                                let showUrl = "{{ route('admin.stok-masuk.penerimaan-barang.show', ':id') }}".replace(':id', row.id);
                                let editUrl = "{{ route('admin.stok-masuk.penerimaan-barang.edit', ':id') }}".replace(':id', row.id);
                                let destroyUrl = "{{ route('admin.stok-masuk.penerimaan-barang.destroy', ':id') }}".replace(':id', row.id);
                                let csrfToken = "{{ csrf_token() }}";

                                let actionsHtml = `
                                    <a href="#" class="btn btn-sm btn-light btn-active-light-primary" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">Actions
                                        <span class="svg-icon svg-icon-5 m-0">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                                <path d="M11.4343 12.7344L7.25 8.55005C6.83579 8.13583 6.16421 8.13584 5.75 10.05L11.2929 15.5929C11.6834 15.9835 12.3166 15.9835 12.7071 15.5929L18.25 10.05C18.6642 9.63584 18.6642 8.96426 18.25 8.55005C17.8358 8.13584 17.1642 8.13584 16.75 8.55005L12.5657 12.7344C12.2533 13.0468 11.7467 13.0468 11.4343 12.7344Z" fill="black"></path>
                                            </svg>
                                        </span>
                                    </a>
                                    <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-600 menu-state-bg-light-primary fw-bold fs-7 w-125px py-4" data-kt-menu="true">
                                        <div class="menu-item px-3">
                                            <a href="${showUrl}" class="menu-link px-3">View</a>
                                        </div>`;

                                if (row.status === 'draft' && canEdit) {
                                    actionsHtml += `
                                        <div class="menu-item px-3">
                                            <a href="${editUrl}" class="menu-link px-3">Edit</a>
                                        </div>`;
                                }

                                if (row.status === 'draft' && canDelete) {
                                    actionsHtml += `
                                        <div class="menu-item px-3">
                                            <form class="form-delete" action="${destroyUrl}" method="POST">
                                                <input type="hidden" name="_token" value="${csrfToken}">
                                                <input type="hidden" name="_method" value="DELETE">
                                                <button type="submit" class="menu-link px-3 border-0 bg-transparent w-100 text-start" data-document-code="${row.code}">
                                                    Delete
                                                </button>
                                            </form>
                                        </div>`;
                                }
                                if (row.status !== 'completed' && canApprove) {
                                    const completeUrl = "{{ route('admin.stok-masuk.penerimaan-barang.complete', ':id') }}".replace(':id', row.id);
                                    actionsHtml += `
                                        <div class="menu-item px-3">
                                            <button type="button" class="menu-link px-3 border-0 bg-transparent w-100 text-start action-complete" data-complete-url="${completeUrl}" data-document-code="${row.code}">
                                                Complete
                                            </button>
                                        </div>`;
                                }

                                actionsHtml += `</div>`;
                                return actionsHtml;
                            }
                        }
                    ],
                    drawCallback: function() {
                        loadStatusCounts();
                        KTMenu.createInstances(); // Re-initialize KTMenu after table redraw
                    }
                });

                const statusText = $('#status_filter option:selected').text();
                const warehouseText = $('#warehouse_filter option:selected').text();
                const dateText = $('#date_filter').val() || 'Semua Tanggal';
                $('#filter-info').text(`Tanggal: ${dateText} | Status: ${statusText} | Gudang: ${warehouseText}`);
            }

            loadDataTable();
            loadStatusCounts();

            $('#apply_filter').on('click', function() {
                loadDataTable();
                $('[data-kt-menu-dismiss="true"]').click();
            });

            const triggerSearch = debounce(function() {
                table.draw();
            });

            $('#search_input').on('keyup', function() {
                triggerSearch();
            });

            $('#table-on-page').on('submit', '.form-delete', function(e) {
                e.preventDefault();

                var form = $(this);
                var n = form.find('button[data-document-code]').data('document-code');
                var url = form.attr('action');
                var data = form.serialize();

                Swal.fire({
                    text: "Apakah yakin ingin menghapus dokumen " + n + "?",
                    icon: "warning",
                    showCancelButton: true,
                    buttonsStyling: false,
                    confirmButtonText: "Ya, hapus!",
                    cancelButtonText: "Tidak, batalkan",
                    customClass: {
                        confirmButton: "btn fw-bold btn-danger",
                        cancelButton: "btn fw-bold btn-active-light-light"
                    }
                }).then(function(result) {
                    if (result.value) {
                        $.ajax({
                            url: url,
                            type: 'POST',
                            data: data,
                            success: function(response) {
                                toastr.success("Dokumen " + n + " berhasil dihapus.");
                                loadDataTable();
                            },
                            error: function(xhr) {
                                toastr.error("Gagal menghapus dokumen " + n + ". Silakan coba lagi.");
                            }
                        });
                    } else if (result.dismiss === 'cancel') {
                        toastr.info("Penghapusan dokumen " + n + " dibatalkan.");
                    }
                });
            });

            $('#table-on-page').on('click', '.action-complete', function(e){
                e.preventDefault();
                const url = $(this).data('complete-url');
                const code = $(this).data('document-code');
                Swal.fire({
                    text: "Setujui dan selesaikan dokumen " + code + "?",
                    icon: "question",
                    showCancelButton: true,
                    buttonsStyling: false,
                    confirmButtonText: "Ya, selesaikan!",
                    cancelButtonText: "Batal",
                    customClass: {
                        confirmButton: "btn fw-bold btn-primary",
                        cancelButton: "btn fw-bold btn-active-light-light"
                    }
                }).then(function(result){
                    if(result.value){
                        $.ajax({
                            url: url,
                            type: 'POST',
                            data: { _token: "{{ csrf_token() }}" },
                            success: function(res){
                                if(res.success){
                                    toastr.success(res.message || 'Berhasil menyelesaikan dokumen.');
                                    loadDataTable();
                                    loadStatusCounts();
                                }else{
                                    toastr.error(res.message || 'Gagal menyelesaikan.');
                                }
                            },
                            error: function(xhr){
                                const msg = xhr.responseJSON?.message || 'Terjadi kesalahan saat menyelesaikan dokumen.';
                                toastr.error(msg);
                            }
                        });
                    }
                });
            });
        });
    </script>
@endpush
