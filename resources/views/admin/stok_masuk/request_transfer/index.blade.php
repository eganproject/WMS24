@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <style>
        /* Status card styling (responsive) */
        .status-card-icon .symbol-label { background: #F3E8FF; }
        .status-card-icon i { color: #7C3AED; }
        .status-metrics { display: flex; gap: 1.5rem; align-items: center; flex-wrap: wrap; justify-content: flex-end; }
        .status-metrics .metric { text-align: center; min-width: 88px; }
        .status-metrics .chip { display: inline-block; padding: 6px 12px; border-radius: 10px; font-weight: 700; font-size: 1rem; line-height: 1; }
        .chip-warning { background: #FFF7E6; color: #F59E0B; }
        .chip-danger { background: #FEE2E2; color: #EF4444; }
        .chip-primary { background: #E6F0FF; color: #3B82F6; }
        .chip-indigo { background: #EDE9FE; color: #6366F1; }
        .chip-success { background: #ECFDF5; color: #10B981; }
        @media (max-width: 576px) { .status-metrics { justify-content: flex-start; } .status-metrics .metric { min-width: 70px; } }
    </style>
@endpush

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Request Transfer',
        'breadcrumbs' => ['Admin', 'Stok Masuk', 'Request Transfer'],
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
                                        <i class="fas fa-exchange-alt fs-2x"></i>
                                    </div>
                                </div>
                                <div>
                                    <div class="text-dark fw-bolder fs-5 mb-1">Status Request Transfer</div>
                                    <div class="text-muted fw-bold">Dokumen Request</div>
                                </div>
                            </div>
                            <div class="status-metrics">
                                <div class="metric">
                                    <div class="chip chip-warning" id="status_requested_count">
                                        <div class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">Loading...</span></div>
                                    </div>
                                    <div class="text-muted fs-8 mt-1">Requested</div>
                                </div>
                                <div class="metric">
                                    <div class="chip chip-danger" id="status_rejected_count">
                                        <div class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">Loading...</span></div>
                                    </div>
                                    <div class="text-muted fs-8 mt-1">Rejected</div>
                                </div>
                                <div class="metric">
                                    <div class="chip chip-indigo" id="status_approved_count">
                                        <div class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">Loading...</span></div>
                                    </div>
                                    <div class="text-muted fs-8 mt-1">Approved</div>
                                </div>
                                <div class="metric">
                                    <div class="chip chip-primary" id="status_on_progress_count">
                                        <div class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">Loading...</span></div>
                                    </div>
                                    <div class="text-muted fs-8 mt-1">On Progress</div>
                                </div>
                                <div class="metric">
                                    <div class="chip chip-success" id="status_completed_count">
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
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none">
                                <rect opacity="0.5" x="17.0365" y="15.1223" width="8.15546" height="2" rx="1"
                                    transform="rotate(45 17.0365 15.1223)" fill="black"></rect>
                                <path
                                    d="M11 19C6.55556 19 3 15.4444 3 11C3 6.55556 6.55556 3 11 3C15.4444 3 19 6.55556 19 11C19 15.4444 15.4444 19 11 19ZM11 5C7.53333 5 5 7.53333 5 11C5 14.4667 7.53333 17 11 17C14.4667 17 17 14.4667 17 11C17 7.53333 14.4667 5 11 5Z"
                                    fill="black"></path>
                            </svg>
                        </span>
                        <input type="text" id="search_input" class="form-control form-control-solid w-250px ps-15"
                            placeholder="Cari Request Transfer">
                    </div>
                </div>
                <div class="card-toolbar">
                    <div class="d-flex justify-content-end" data-kt-customer-table-toolbar="base">
                        <!--begin::Filter-->
                        <button type="button" class="btn btn-light-primary me-3" data-kt-menu-trigger="click"
                            data-kt-menu-placement="bottom-end">
                            <!--begin::Svg Icon | path: icons/duotune/general/gen031.svg-->
                            <span class="svg-icon svg-icon-2">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                    fill="none">
                                    <path
                                        d="M19.0759 3H4.72777C3.95892 3 3.47768 3.83148 3.86067 4.49814L8.56967 12.6949C9.17923 13.7559 9.5 14.9582 9.5 16.1819V19.5072C9.5 20.2189 10.2223 20.7028 10.8805 20.432L13.8805 19.1977C14.2553 19.0435 14.5 18.6783 14.5 18.273V13.8372C14.5 12.8089 14.8171 11.8056 15.408 10.964L19.8943 4.57465C20.3596 3.912 19.8856 3 19.0759 3Z"
                                        fill="black" />
                                </svg>
                            </span>
                            <!--end::Svg Icon-->Filter</button>
                        <!--begin::Menu 1-->
                        <div class="menu menu-sub menu-sub-dropdown w-300px w-md-325px" data-kt-menu="true"
                            id="kt-toolbar-filter">
                            <!--begin::Header-->
                            <div class="px-7 py-5">
                                <div class="fs-4 text-dark fw-bolder">Filter Options</div>
                            </div>
                            <!--end::Header-->
                            <!--begin::Separator-->
                            <div class="separator border-gray-200"></div>
                            <!--end::Separator-->
                            <!--begin::Content-->
                            <div class="px-7 py-5">
                                <div class="mb-10">
                                    <label class="form-label fs-5 fw-bold mb-3">Gudang Asal:</label>
                                    <select class="form-select form-select-solid fw-bolder" data-kt-select2="true"
                                        id="from_warehouse_filter" data-dropdown-parent="#kt-toolbar-filter">
                                        <option value="semua">Semua</option>
                                        @foreach ($warehouses as $warehouse)
                                            <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                @if (auth()->user()->warehouse_id === null)
                                    <div class="mb-10">
                                        <label class="form-label fs-5 fw-bold mb-3">Gudang Tujuan:</label>
                                        <select class="form-select form-select-solid fw-bolder" data-kt-select2="true"
                                            id="to_warehouse_filter" data-dropdown-parent="#kt-toolbar-filter">
                                            <option value="semua">Semua</option>
                                            @foreach ($warehouses as $warehouse)
                                                <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                                <div class="mb-10">
                                    <label class="form-label fs-5 fw-bold mb-3">Status:</label>
                                    <select class="form-select form-select-solid fw-bolder" data-kt-select2="true"
                                        id="status_filter" data-dropdown-parent="#kt-toolbar-filter">
                                        <option value="semua">Semua</option>
                                        <option value="pending">Pending</option>
                                        <option value="approved">Disetujui</option>
                                        <option value="on_progress">Dalam Proses</option>
                                        <option value="completed">Selesai</option>
                                        <option value="rejected">Ditolak</option>
                                    </select>
                                </div>
                                <div class="mb-10">
                                    <label class="form-label fs-5 fw-bold mb-3">Tanggal:</label>
                                    <select class="form-select form-select-solid fw-bolder" data-kt-select2="true"
                                        id="date_filter_options" data-dropdown-parent="#kt-toolbar-filter">
                                        <option value="semua">Semua</option>
                                        <option value="pilih_tanggal">Pilih Tanggal</option>
                                    </select>
                                    <input class="form-control form-control-solid mt-3" placeholder="Pilih Tanggal"
                                        id="date_filter" style="display: none;" />
                                </div>
                                <div class="d-flex justify-content-end">
                                    <button type="reset" class="btn btn-light btn-active-light-primary me-2"
                                        data-kt-menu-dismiss="true">Batal</button>
                                    <button type="button" class="btn btn-primary" id="apply_filter">Submit</button>
                                </div>
                            </div>
                            <!--end::Content-->
                        </div>
                    </div>

                    <div class="d-flex justify-content-end">
                        @if ($canCreate)
                            <a href="{{ route('admin.stok-masuk.request-transfer.create') }}" class="btn btn-primary">Buat Request Transfer</a>
                        @endif
                    </div>
                </div>
            </div>
            <div class="card-body pt-4">
                <div class="text-center mb-5">
                    <h3 class="mb-0">Daftar Request Transfer</h3>
                    <small id="filter-info" class="text-muted"></small>
                </div>
                <div class="dataTables_wrapper dt-bootstrap4 no-footer">
                    <div class="table-responsive min-h-500px">
                        <table class="table align-middle table-row-dashed fs-6 gy-5 dataTable no-footer"
                            id="table-on-page">
                            <thead>
                                <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                    <th class="min-w-125px sorting">Kode</th>
                                    <th class="min-w-125px sorting">Tanggal</th>
                                    <th class="min-w-125px sorting">Gudang Asal</th>
                                    @if (auth()->user()->warehouse_id == null)
                                        <th class="min-w-125px sorting">Gudang Tujuan</th>
                                    @endif
                                    <th class="min-w-250px sorting">Items</th>
                                    <th class="min-w-125px sorting">Status</th>
                                    <th class="min-w-125px sorting">Dibuat Oleh</th>
                                    <th class="text-center min-w-125px sorting_disabled">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="fw-bold text-gray-600">
                                <!-- Data will be loaded by DataTables Ajax -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    var table; // Declare table globally

    function animateCountUp(elementId, finalValue) {
        let start = 0;
        const duration = 1000; // 1 second
        const element = document.getElementById(elementId);
        if (!element) return;

        let startTime = null;

        const step = (timestamp) => {
            if (!startTime) startTime = timestamp;
            const progress = Math.min((timestamp - startTime) / duration, 1);
            element.innerHTML = Math.floor(progress * finalValue).toLocaleString();
            if (progress < 1) {
                window.requestAnimationFrame(step);
            } else {
                element.innerHTML = finalValue.toLocaleString();
            }
        };

        window.requestAnimationFrame(step);
    }
    $(document).ready(function() {
        const canEdit = @json($canEdit);
        const canDelete = @json($canDelete);
        const canApprove = @json($canApprove);
        const canCreate = @json($canCreate);
        $('#date_filter').flatpickr({
            defaultDate: new Date(),
            onChange: function(selectedDates, dateStr, instance) {
                if ($('#date_filter_options').val() !== 'pilih_tanggal') {
                    $('#date_filter_options').val('pilih_tanggal').trigger('change');
                }
            }
        });

        $('#date_filter_options').on('change', function() {
            if ($(this).val() === 'pilih_tanggal') {
                $('#date_filter').show();
            } else {
                $('#date_filter').hide();
                $('#date_filter').val(''); // Clear the date when 'Semua' is selected
            }
        });

        toastr.options = {
            "closeButton": true,
            "debug": false,
            "newestOnTop": false,
            "progressBar": true,
            "positionClass": "toast-top-center",
            "preventDuplicates": false,
            "showDuration": "300",
            "hideDuration": "1000",
            "timeOut": "5000",
            "extendedTimeOut": "1000",
            "showEasing": "swing",
            "hideEasing": "linear",
            "showMethod": "fadeIn",
            "hideMethod": "fadeOut"
        };

        @if (Session::has('success'))
            toastr.success("{{ session('success') }}");
        @endif

        @if (Session::has('error'))
            toastr.error("{{ session('error') }}");
        @endif

        function updateStatusCards() {
            const fromWarehouseId = $('#from_warehouse_filter').val();
            const toWarehouseId = $('#to_warehouse_filter').val();
            const status = $('#status_filter').val();
            const date = $('#date_filter_options').val() === 'semua' ? 'semua' : $('#date_filter').val();

            // Show spinners
            $('#status_requested_count').html('<div class="spinner-border spinner-border-sm text-warning" role="status"><span class="visually-hidden">Loading...</span></div>');
            $('#status_rejected_count').html('<div class="spinner-border spinner-border-sm text-danger" role="status"><span class="visually-hidden">Loading...</span></div>');
            $('#status_approved_count').html('<div class="spinner-border spinner-border-sm text-primary" role="status"><span class="visually-hidden">Loading...</span></div>');
            $('#status_on_progress_count').html('<div class="spinner-border spinner-border-sm text-primary" role="status"><span class="visually-hidden">Loading...</span></div>');
            $('#status_completed_count').html('<div class="spinner-border spinner-border-sm text-success" role="status"><span class="visually-hidden">Loading...</span></div>');

            $.ajax({
                url: "{{ route('admin.stok-masuk.request-transfer.status-counts') }}",
                type: 'GET',
                data: {
                    from_warehouse_id: fromWarehouseId,
                    to_warehouse_id: toWarehouseId,
                    status: status,
                    date: date
                },
                success: function(response) {
                    animateCountUp('status_requested_count', response.requested);
                    animateCountUp('status_rejected_count', response.rejected);
                    animateCountUp('status_approved_count', response.approved);
                    animateCountUp('status_on_progress_count', response.on_progress ?? 0);
                    animateCountUp('status_completed_count', response.completed ?? 0);
                },
                error: function() {
                    console.error('Gagal memuat data status.');
                    $('#status_requested_count').text('Error');
                    $('#status_rejected_count').text('Error');
                    $('#status_approved_count').text('Error');
                    $('#status_on_progress_count').text('Error');
                    $('#status_completed_count').text('Error');
                }
            });
        }

        function loadDataTable() {
            updateStatusCards(); // Update cards when table loads
            var fromWarehouseFilter = $('#from_warehouse_filter').val();
            var toWarehouseFilter = $('#to_warehouse_filter').val(); // Get to_warehouse_filter value
            var statusFilter = $('#status_filter').val();
            var dateFilter = $('#date_filter_options').val() === 'semua' ? 'semua' : $('#date_filter').val();

            var fromWarehouseText = $('#from_warehouse_filter option:selected').text();
            var toWarehouseText = $('#to_warehouse_filter option:selected').text();
            var statusText = $('#status_filter option:selected').text();
            var dateText = dateFilter === 'semua' ? 'Semua Tanggal' : dateFilter;

            let filterInfoText = `Tanggal: ${dateText} | Gudang Asal: ${fromWarehouseText} | Gudang Tujuan: ${toWarehouseText} | Status: ${statusText}`;
            $('#filter-info').text(filterInfoText);

            if ($.fn.DataTable.isDataTable('#table-on-page')) {
                $('#table-on-page').DataTable().destroy();
            }

            table = $('#table-on-page').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: "{{ route('admin.stok-masuk.request-transfer.index') }}",
                    type: "GET",
                    data: function(d) {
                        d.search.value = $('#search_input').val();
                        d.from_warehouse_id = fromWarehouseFilter;
                        d.to_warehouse_id = toWarehouseFilter; // Add to_warehouse_id to data
                        d.status = statusFilter;
                        d.date = dateFilter;
                    }
                },
                drawCallback: function(settings) {
                    KTMenu.createInstances();
                },
                columns: (function() {
                    const cols = [{
                        data: 'code',
                        name: 'code'
                    }, {
                        data: 'date',
                        name: 'date'
                    }, {
                        data: 'from_warehouse_name',
                        name: 'fromWarehouse.name'
                    }];
                    @if (auth()->user()->warehouse_id == null)
                        cols.push({
                            data: 'to_warehouse_name',
                            name: 'toWarehouse.name'
                        });
                    @endif
                    cols.push({
                        data: 'items_list',
                        name: 'items_list'
                    }, {
                        data: 'status',
                        name: 'status'
                    }, {
                        data: 'requester_name',
                        name: 'requester.name'
                    }, {
                        data: 'id',
                        name: 'id',
                        orderable: false,
                        searchable: false
                    });
                    return cols;
                })(),
                order: [
                    [1, 'desc']
                ], // Default order by code descending
                columnDefs: (function() {
                    const defs = [{
                        targets: 1, // Date column
                        render: function(data) {
                            const d = new Date(data);
                            const day = ('0' + d.getDate()).slice(-2);
                            const month = d.toLocaleString('en-GB', {
                                month: 'short'
                            });
                            const year = d.getFullYear();
                            return `${day} ${month} ${year}`;
                        }
                    }];
                    const statusIdx = (@json(auth()->user()->warehouse_id == null) ? 5 : 4);
                    const actionsIdx = (@json(auth()->user()->warehouse_id == null) ? 7 : 6);
                    defs.push({
                        targets: statusIdx,
                        render: function(data) {
                            let badgeClass = 'dark';
                            if (data === 'completed') badgeClass = 'success';
                            else if (data === 'rejected') badgeClass = 'danger';
                            else if (data === 'on_progress') badgeClass = 'primary';
                            else if (data === 'approved') badgeClass = 'info';
                            return `<span class="badge badge-light-${badgeClass}">${data}</span>`;
                        }
                    });
                    defs.push({
                        targets: actionsIdx,
                        render: function(data, type, row) {
                            let showUrl = "{{ route('admin.stok-masuk.request-transfer.show', ':id') }}".replace(':id', row.id);
                            let editUrl = "{{ route('admin.stok-masuk.request-transfer.edit', ':id') }}".replace(':id', row.id);
                            let destroyUrl = "{{ route('admin.stok-masuk.request-transfer.destroy', ':id') }}".replace(':id', row.id);
                            let csrfToken = "{{ csrf_token() }}";
                            let actionsHtml = `
                                <a href="#" class="btn btn-sm btn-light btn-active-light-primary" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">Actions
                                    <span class="svg-icon svg-icon-5 m-0">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                            <path d="M11.4343 12.7344L7.25 8.55005C6.83579 8.13583 6.16421 8.13584 5.75 8.55005C5.33579 8.96426 5.33579 9.63583 5.75 10.05L11.2929 15.5929C11.6834 15.9835 12.3166 15.9835 12.7071 15.5929L18.25 10.05C18.6642 9.63584 18.6642 8.96426 18.25 8.55005C17.8358 8.13584 17.1642 8.13584 16.75 8.55005L12.5657 12.7344C12.2533 13.0468 11.7467 13.0468 11.4343 12.7344Z" fill="black"></path>
                                        </svg>
                                    </span>
                                </a>
                                <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-600 menu-state-bg-light-primary fw-bold fs-7 w-125px py-4" data-kt-menu="true">
                                    <div class="menu-item px-3">
                                        <a href="${showUrl}" class="menu-link px-3">View</a>
                                    </div>`;

                            if (row.status === 'pending') {
                                if (canEdit) {
                                    actionsHtml += `
                                        <div class="menu-item px-3">
                                            <a href="${editUrl}" class="menu-link px-3">Edit</a>
                                        </div>`;
                                }
                                if (canDelete) {
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
                            }

                            actionsHtml += `</div>`;
                            return actionsHtml;
                        }
                    });
                    return defs;
                })()
            });
        }

        loadDataTable();

        $('#apply_filter').on('click', function() {
            loadDataTable();
            $('[data-kt-menu-dismiss="true"]').click();
        });

        // --- Debounce function start ---
        const debounce = (callback, wait) => {
            let timeoutId = null;
            return (...args) => {
                window.clearTimeout(timeoutId);
                timeoutId = window.setTimeout(() => {
                    callback.apply(null, args);
                }, wait);
            };
        }
        // --- Debounce function end ---

        // Re-draw table on search input change with debounce
        $('#search_input').on('keyup', debounce(function() {
            table.draw();
        }, 500)); // 500ms delay

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
                            table.ajax.reload(null, false); // Reload table data
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

        window.confirmStatusChange = function(id, status, code) {
            let confirmationText = "";
            let successText = "";
            if (status === 'approved') {
                confirmationText = `Apakah Anda yakin ingin menyetujui request transfer ${code}?`;
                successText = `Request transfer ${code} berhasil disetujui.`;
            } else if (status === 'rejected') {
                confirmationText = `Apakah Anda yakin ingin menolak request transfer ${code}?`;
                successText = `Request transfer ${code} berhasil ditolak.`;
            } else if (status === 'on_progress') {
                confirmationText = `Apakah Anda yakin ingin mengubah status request transfer ${code} menjadi 'Dalam Proses'?`;
                successText = `Status request transfer ${code} berhasil diubah menjadi 'Dalam Proses'.`;
            } else if (status === 'completed') {
                confirmationText = `Apakah Anda yakin ingin mengubah status request transfer ${code} menjadi 'Selesai'?`;
                successText = `Status request transfer ${code} berhasil diubah menjadi 'Selesai'.`;
            }

            Swal.fire({
                text: confirmationText,
                icon: "warning",
                showCancelButton: true,
                buttonsStyling: false,
                confirmButtonText: "Ya, lanjutkan!",
                cancelButtonText: "Tidak, batalkan",
                customClass: {
                    confirmButton: "btn fw-bold btn-success",
                    cancelButton: "btn fw-bold btn-active-light-light"
                }
            }).then(function(result) {
                if (result.value) {
                    $.ajax({
                        url: `/admin/stok-masuk/request-transfer/${id}/update-status`,
                        type: 'POST',
                        data: {
                            _token: $('meta[name="csrf-token"]').attr('content'),
                            status: status
                        },
                        success: function(response) {
                            toastr.success(successText);
                            table.ajax.reload(null, false);
                        },
                        error: function(xhr) {
                            toastr.error("Gagal mengubah status. Silakan coba lagi.");
                        }
                    });
                } else if (result.dismiss === 'cancel') {
                    toastr.info("Perubahan status dibatalkan.");
                }
            });
        };
    });
</script>
@endpush
