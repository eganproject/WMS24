@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <style>
        .status-card-icon .symbol-label { background: #F3E8FF; }
        .status-card-icon i { color: #7C3AED; }
        .status-metrics { display: flex; gap: 1.5rem; align-items: center; flex-wrap: wrap; justify-content: flex-end; }
        .status-metrics .metric { text-align: center; min-width: 88px; }
        .status-metrics .chip { display: inline-block; padding: 6px 12px; border-radius: 10px; font-weight: 700; font-size: 1rem; line-height: 1; }
        .chip-warning { background: #FFF7E6; color: #F59E0B; }
        .chip-indigo { background: #EDE9FE; color: #6366F1; }
        .chip-primary { background: #E6F0FF; color: #3B82F6; }
        .chip-success { background: #ECFDF5; color: #10B981; }
        .chip-danger { background: #FEE2E2; color: #EF4444; }
        @media (max-width: 576px) { .status-metrics { justify-content: flex-start; } .status-metrics .metric { min-width: 70px; } }

        /* Modal items UI/UX improvements */
        #shipment_items_container .table thead th { position: sticky; top: 0; background: #fff; z-index: 1; }
        #shipment_items_container .table { margin-bottom: 0; }
        #shipment_items_container .table tbody tr td { vertical-align: middle; }
        #shipment_items_container .item-cell .badge-sku { font-weight: 600; background: #F1F5F9; color: #0F172A; }
        #shipment_items_container .item-meta { color: #64748B; }
        #shipment_items_container .input-group.input-group-sm .btn { min-width: 32px; }
        #shipment_items_container .items-toolbar { display: flex; gap: .75rem; align-items: center; justify-content: space-between; margin-bottom: .75rem; }
        #shipment_items_container .items-toolbar .summary { font-weight: 600; color: #334155; }
        #shipment_items_container .items-scroll { max-height: 420px; overflow: auto; border: 1px solid #EFF2F5; border-radius: .475rem; }
        #shipment_items_container .help-muted { font-size: 12px; color: #6B7280; }
        #shipment_items_container .empty-state { padding: 1rem; border: 1px dashed #E5E7EB; border-radius: .5rem; color: #6B7280; background: #F9FAFB; }
    </style>
@endpush

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Permintaan Barang',
        'breadcrumbs' => ['Admin', 'Stok Keluar', 'Permintaan Barang'],
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
                                        <i class="fas fa-truck-loading fs-2x"></i>
                                    </div>
                                </div>
                                <div>
                                    <div class="text-dark fw-bolder fs-5 mb-1">Status Permintaan Barang</div>
                                    <div class="text-muted fw-bold">Dokumen Transfer</div>
                                </div>
                            </div>
                            <div class="status-metrics">
                                <div class="metric">
                                    <div class="chip chip-warning" id="status_requested">
                                        <div class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">Loading...</span></div>
                                    </div>
                                    <div class="text-muted fs-8 mt-1">Requested</div>
                                </div>
                                <div class="metric">
                                    <div class="chip chip-indigo" id="status_approved">
                                        <div class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">Loading...</span></div>
                                    </div>
                                    <div class="text-muted fs-8 mt-1">Menunggu Dikirim</div>
                                </div>
                                <div class="metric">
                                    <div class="chip chip-primary" id="status_on_progress">
                                        <div class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">Loading...</span></div>
                                    </div>
                                    <div class="text-muted fs-8 mt-1">On Progress</div>
                                </div>
                                <div class="metric">
                                    <div class="chip chip-success" id="status_completed">
                                        <div class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">Loading...</span></div>
                                    </div>
                                    <div class="text-muted fs-8 mt-1">Completed</div>
                                </div>
                                <div class="metric">
                                    <div class="chip chip-danger" id="status_rejected">
                                        <div class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">Loading...</span></div>
                                    </div>
                                    <div class="text-muted fs-8 mt-1">Rejected</div>
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
                            placeholder="Cari Permintaan Barang">
                    </div>
                </div>
                <div class="card-toolbar">
                    <div class="d-flex justify-content-end" data-kt-customer-table-toolbar="base">
                        <!--begin::Filter-->
                        <button type="button" class="btn btn-light-primary me-3" data-kt-menu-trigger="click"
                            data-kt-menu-placement="bottom-end">
                            <span class="svg-icon svg-icon-2">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                    fill="none">
                                    <path
                                        d="M19.0759 3H4.72777C3.95892 3 3.47768 3.83148 3.86067 4.49814L8.56967 12.6949C9.17923 13.7559 9.5 14.9582 9.5 16.1819V19.5072C9.5 20.2189 10.2223 20.7028 10.8805 20.432L13.8805 19.1977C14.2553 19.0435 14.5 18.6783 14.5 18.273V13.8372C14.5 12.8089 14.8171 11.8056 15.408 10.964L19.8943 4.57465C20.3596 3.912 19.8856 3 19.0759 3Z"
                                        fill="black" />
                                </svg>
                            </span>
                            Filter</button>
                        <!--begin::Menu 1-->
                        <div class="menu menu-sub menu-sub-dropdown w-300px w-md-325px" data-kt-menu="true"
                            id="kt-toolbar-filter">
                            <div class="px-7 py-5">
                                <div class="fs-4 text-dark fw-bolder">Filter Options</div>
                            </div>
                            <div class="separator border-gray-200"></div>
                            <div class="px-7 py-5">
                                @if (auth()->user()->warehouse_id == null)
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
                                @endif
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
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body pt-4">
                <div class="text-center mb-5">
                    <h3 class="mb-0">Daftar Permintaan Barang</h3>
                    <small id="filter-info" class="text-muted"></small>
                </div>
                <div class="dataTables_wrapper dt-bootstrap4 no-footer">
                    <div class="table-responsive min-h-500px">
                        <table class="table align-middle table-row-dashed fs-6 gy-5 dataTable no-footer" id="table-on-page">
                            <thead>
                                <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                    <th class="min-w-125px sorting">Kode</th>
                                    <th class="min-w-125px sorting">Tanggal</th>
                                    @if(auth()->user()->warehouse_id === null)
                                    <th class="min-w-125px sorting">Gudang Asal</th>
                                    @endif
                                    <th class="min-w-125px sorting">Gudang Tujuan</th>
                                    <th class="min-w-250px sorting_disabled">Items</th>
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

    <div class="modal fade" tabindex="-1" id="kt_modal_kirim_barang">
        <div class="modal-dialog modal-dialog-centered mw-1000px">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Form Pengiriman Barang</h5>
                    <div class="btn btn-icon btn-sm btn-active-light-primary ms-2" data-bs-dismiss="modal"
                        aria-label="Close">
                        <span class="svg-icon svg-icon-2x"></span>
                    </div>
                </div>
                <form id="kirim_barang_form" method="POST">
                    @csrf
                    <div class="modal-body">
                        <h6 class="mb-4">Detail Pengiriman</h6>
                        <div class="mb-3">
                            <label for="shipping_date" class="form-label required">Tanggal Kirim</label>
                            <input type="text" class="form-control" id="shipping_date" name="shipping_date" required>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="vehicle_type" class="form-label required">Tipe Kendaraan</label>
                                <input type="text" class="form-control" id="vehicle_type" name="vehicle_type"
                                    required>
                                <div class="invalid-feedback"></div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="license_plate" class="form-label required">Plat Nomor</label>
                                <input type="text" class="form-control" id="license_plate" name="license_plate"
                                    required>
                                <div class="invalid-feedback"></div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="driver_name" class="form-label required">Nama Pengemudi</label>
                                <input type="text" class="form-control" id="driver_name" name="driver_name" required>
                                <div class="invalid-feedback"></div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="driver_contact" class="form-label">Kontak Pengemudi</label>
                                <input type="text" class="form-control" id="driver_contact" name="driver_contact">
                                <div class="invalid-feedback"></div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Deskripsi / Catatan</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                            <div class="invalid-feedback"></div>
                        </div>

                        <hr>
                        <h6 class="mb-3">Item yang Akan Dikirim</h6>
                        <!-- Item details will be loaded here -->
                        <div id="shipment_items_container" class="mb-5">
                            <div class="text-center">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p class="mt-2">Memuat item...</p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Buat Pengiriman</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
@push('scripts')
    <script>
        var table; // Declare table globally
        $(document).ready(function() {
            const canEdit = @json($canEdit);
            const canApprove = @json($canApprove);
            const canDelete = @json($canDelete);
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

            $('#shipping_date').flatpickr({
                enableTime: false,
                dateFormat: "Y-m-d",
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

            function loadDataTable() {
                var toWarehouseFilter = $('#to_warehouse_filter').val();
                var fromWarehouseFilter = $('#from_warehouse_filter').val();
                var statusFilter = $('#status_filter').val();
                var dateFilter = $('#date_filter_options').val() === 'semua' ? 'semua' : $('#date_filter').val();

                $.ajax({
                    url: "{{ route('admin.stok-keluar.permintaan-barang.status-counts') }}",
                    type: "GET",
                    data: {
                        to_warehouse_id: toWarehouseFilter,
                        from_warehouse_id: fromWarehouseFilter,
                        date: dateFilter
                    },
                    success: function(response) {
                        $('#status_requested').text(response.requested);
                        $('#status_approved').text(response.approved);
                        $('#status_on_progress').text(response.on_progress || 0);
                        $('#status_completed').text(response.completed);
                        $('#status_rejected').text(response.rejected);
                    }
                });

                var toWarehouseText = $('#to_warehouse_filter option:selected').text();
                var fromWarehouseText = $('#from_warehouse_filter option:selected').text();
                var statusText = $('#status_filter option:selected').text();
                var dateText = dateFilter === 'semua' ? 'Semua Tanggal' : dateFilter;

                let filterInfoText = `Tanggal: ${dateText} | Gudang Tujuan: ${toWarehouseText} | Status: ${statusText}`;

                if ("{{ auth()->user()->warehouse_id == null }}") {
                    filterInfoText += ` | Gudang Asal: ${fromWarehouseText}`;
                }

                $('#filter-info').text(filterInfoText);

                if ($.fn.DataTable.isDataTable('#table-on-page')) {
                    $('#table-on-page').DataTable().destroy();
                }

                table = $('#table-on-page').DataTable({
                    processing: true,
                    serverSide: true,
                    ajax: {
                        url: "{{ route('admin.stok-keluar.permintaan-barang.index') }}",
                        type: "GET",
                        data: function(d) {
                            d.search.value = $('#search_input').val();
                            d.to_warehouse_id = toWarehouseFilter;
                            d.from_warehouse_id = fromWarehouseFilter;
                            d.status = statusFilter;
                            d.date = dateFilter;
                        }
                    },
                    drawCallback: function(settings) {
                        KTMenu.createInstances();
                    },
                    columns: (function() {
                        let cols = [
                            { data: 'code', name: 'code' },
                            { data: 'date', name: 'date' }
                        ];

                        if (@json(auth()->user()->warehouse_id === null)) {
                            cols.push({ data: 'from_warehouse_name', name: 'fromWarehouse.name' });
                        }

                        cols.push({ data: 'to_warehouse_name', name: 'toWarehouse.name' });
                        cols.push({ data: 'items_list', name: 'items_list', orderable: false, searchable: false });
                        cols.push({ data: 'status', name: 'status' });
                        cols.push({ data: 'requester_name', name: 'requester.name' });
                        cols.push({ data: 'id', name: 'id', orderable: false, searchable: false });

                        return cols;
                    })(),
                    order: [
                        [1, 'desc']
                    ], // Default order by date descending
                    columnDefs: (function() {
                        const hasFromWarehouse = @json(auth()->user()->warehouse_id === null);
                        const statusIdx = hasFromWarehouse ? 5 : 4;
                        const actionsIdx = hasFromWarehouse ? 7 : 6;

                        let defs = [{
                            targets: 1, // Date column
                            render: function(data, type, row) {
                                const d = new Date(data);
                                const day = ('0' + d.getDate()).slice(-2);
                                const month = d.toLocaleString('en-GB', {
                                    month: 'short'
                                });
                                const year = d.getFullYear();
                                return `${day} ${month} ${year}`;
                            }
                        },
                        {
                            targets: statusIdx, // Status column
                            render: function(data, type, row) {
                                let badgeClass = 'primary';
                                if (data === 'completed') {
                                    badgeClass = 'success';
                                } else if (data === 'rejected') {
                                    badgeClass = 'danger';
                                } else if (data === 'on_progress') {
                                    badgeClass = 'warning';
                                } else if (data === 'approved') {
                                    badgeClass = 'info';
                                }
                                return `<span class="badge badge-light-${badgeClass}">${data}</span>`;
                            }
                        },
                        {
                            targets: actionsIdx, // Actions column
                            render: function(data, type, row) {
                                let showUrl =
                                    "{{ route('admin.stok-keluar.permintaan-barang.show', ':id') }}"
                                    .replace(':id', row.id);
                                // Surat Jalan dihilangkan sesuai permintaan

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
                                        <a href="${showUrl}" class="menu-link px-3">Detail</a>
                                    </div>`;

                                if (row.status === 'pending' && canApprove) {
                                    actionsHtml += `
                                    <div class="menu-item px-3">
                                        <a href="#" class="menu-link px-3" onclick="confirmStatusChange(${row.id}, 'approved', '${row.code}')">Approve</a>
                                    </div>`;
                                } else if ((row.status === 'approved' || row.status === 'on_progress') && canEdit) {
                                    actionsHtml += `
                                    <div class="menu-item px-3">
                                        <a href="#" class="menu-link px-3 kirim-barang-btn" data-bs-toggle="modal" data-bs-target="#kt_modal_kirim_barang" data-id="${row.id}" data-code="${row.code}">Kirim Barang</a>
                                    </div>`;
                                }

                                // Tombol Surat Jalan dihapus dari menu actions

                                actionsHtml += `</div>`;
                                return actionsHtml;
                            }
                        }
                        ];
                        return defs;
                    })(),
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

            window.confirmStatusChange = function(id, status, code) {
                let confirmationText = "";
                let successText = "";
                if (status === 'approved') {
                    confirmationText = `Apakah Anda yakin ingin menyetujui permintaan transfer ${code}?`;
                    successText = `Permintaan transfer ${code} berhasil disetujui.`;
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
                            url: `/admin/stok-keluar/permintaan-barang/${id}/update-status`,
                            type: 'POST',
                            data: {
                                _token: '{{ csrf_token() }}',
                                status: status
                            },
                            success: function(response) {
                                $(kirimBarangModal).modal('hide');
                                toastr.success(successText);
                                loadDataTable();
                                form.reset();
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

            const kirimBarangModal = document.getElementById('kt_modal_kirim_barang');
            const form = kirimBarangModal.querySelector('#kirim_barang_form');
            const itemsContainer = $('#shipment_items_container'); // Changed to jQuery object

            kirimBarangModal.addEventListener('show.bs.modal', event => {
                const button = event.relatedTarget;
                const transferId = button.getAttribute('data-id');
                const transferCode = button.getAttribute('data-code');

                const modalTitle = kirimBarangModal.querySelector('.modal-title');
                modalTitle.textContent = 'Form Pengiriman Barang: ' + transferCode;

                let action = `/admin/stok-keluar/permintaan-barang/${transferId}/create-shipment`;
                form.action = action;

                // Reset and show loading
                itemsContainer.html(`<div class="text-center">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <p class="mt-2">Memuat item...</p>
                                </div>`);

                // This URL must be created in web.php and return the items for the transfer request
                const itemsUrl =
                    `/admin/stok-keluar/permintaan-barang/${transferId}/items-to-ship`;

                $.ajax({
                    url: itemsUrl,
                    type: 'GET',
                    success: function(items) {
                        itemsContainer.html(''); // Clear loading
                        if (items.length === 0) {
                            itemsContainer.html('<div class="empty-state text-center">Tidak ada item yang perlu dikirim untuk permintaan ini.</div>');
                            return;
                        }

                        // Toolbar with search and summary
                        const toolbar = $(`
                            <div class="items-toolbar">
                                <div class="w-50">
                                    <div class="position-relative">
                                        <i class="fas fa-search text-gray-400 position-absolute ms-3 mt-2"></i>
                                        <input type="text" id="modal-item-search" class="form-control form-control-sm ps-10" placeholder="Cari item (SKU/Nama)">
                                    </div>
                                </div>
                                <div class="summary" id="modal-item-summary">-</div>
                            </div>
                        `);

                        const table = $('<table class="table align-middle table-sm table-hover"></table>');
                        const thead = $('<thead><tr><th class="text-gray-600">Item</th><th class="text-gray-600" width="200">Qty Kirim</th><th class="text-gray-600" width="180">Koli Kirim</th></tr></thead>');
                        const tbody = $('<tbody></tbody>');

                        items.forEach(item => {
                            const koliRatio = parseFloat(item.item.koli) || 1;
                            const requestedQty = parseFloat(item.quantity ?? 0);
                            const requestedKoli = parseFloat(item.koli ?? 0);
                            const row = `
                                <tr data-name="${(item.item.nama_barang || '').toLowerCase()}" data-sku="${(item.item.sku || '').toLowerCase()}"
                                    data-koli-ratio="${koliRatio}" data-max-qty="${item.remaining_quantity}" data-max-koli="${item.remaining_koli}">
                                    <td class="item-cell">
                                        <div class="d-flex flex-column">
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="badge badge-sku">${item.item.sku || '-'}</span>
                                                <span class="fw-semibold">${item.item.nama_barang || '-'}</span>
                                            </div>
                                            <div class="item-meta help-muted mt-1">Diminta: ${requestedQty} Qty (${requestedKoli} Koli) • Sisa: ${item.remaining_quantity} Qty (${item.remaining_koli} Koli) • Rasio: 1 Koli = ${koliRatio} Qty</div>
                                            <input type="text" class="form-control form-control-sm mt-2" name="items[${item.id}][description]" placeholder="Catatan item (opsional)">
                                        </div>
                                        <input type="hidden" name="items[${item.id}][item_id]" value="${item.item_id}">
                                    </td>
                                    <td>
                                        <div class="input-group input-group-sm">
                                            <button type="button" class="btn btn-light minus-btn" data-type="qty" aria-label="Kurangi Qty">-</button>
                                            <input type="number" step="any" class="form-control text-center qty-input" name="items[${item.id}][quantity]" value="${item.remaining_quantity}" min="0" max="${item.remaining_quantity}" placeholder="Qty">
                                            <button type="button" class="btn btn-light plus-btn" data-type="qty" aria-label="Tambah Qty">+</button>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="input-group input-group-sm">
                                            <button type="button" class="btn btn-light minus-btn" data-type="koli" aria-label="Kurangi Koli">-</button>
                                            <input type="number" step="any" class="form-control text-center koli-input" name="items[${item.id}][koli]" value="${item.remaining_koli}" min="0" max="${item.remaining_koli}" placeholder="Koli">
                                            <button type="button" class="btn btn-light plus-btn" data-type="koli" aria-label="Tambah Koli">+</button>
                                        </div>
                                    </td>
                                </tr>`;
                            tbody.append(row);
                        });

                        table.append(thead).append(tbody);
                        const scroll = $('<div class="items-scroll"></div>').append(table);
                        itemsContainer.append(toolbar).append(scroll);

                        // Search filter
                        $('#modal-item-search').on('input', function() {
                            const q = $(this).val().toString().toLowerCase();
                            tbody.find('tr').each(function() {
                                const name = $(this).data('name')?.toString() || '';
                                const sku = $(this).data('sku')?.toString() || '';
                                $(this).toggle(name.includes(q) || sku.includes(q));
                            });
                        });

                        // Summary
                        function updateSummary() {
                            let totalQty = 0, totalKoli = 0, itemCount = 0;
                            tbody.find('tr:visible').each(function() {
                                const qty = parseFloat($(this).find('.qty-input').val()) || 0;
                                const koli = parseFloat($(this).find('.koli-input').val()) || 0;
                                totalQty += qty; totalKoli += koli; itemCount += 1;
                            });
                            $('#modal-item-summary').text(`${itemCount} item • Total: ${totalQty} Qty | ${totalKoli} Koli`);
                        }
                        updateSummary();
                        tbody.on('input', '.qty-input, .koli-input', updateSummary);
                        itemsContainer.on('click', '.plus-btn, .minus-btn', updateSummary);
                    },
                    error: function() {
                        itemsContainer.html(
                            '<div class="alert alert-danger">Gagal memuat item. Silakan coba lagi. Pastikan endpoint untuk mengambil data item sudah dibuat.</div>'
                            );
                    }
                });
            });

            // Automatic calculation for quantity and koli
            itemsContainer.on('input', '.qty-input', function() {
                const row = $(this).closest('tr');
                if (row.hasClass('is-calculating')) return;

                const ratio = parseFloat(row.data('koli-ratio')) || 1;
                const maxQty = parseFloat(row.data('max-qty'));
                let currentQty = parseFloat($(this).val());

                if (isNaN(currentQty)) return;

                if (currentQty > maxQty) {
                    currentQty = maxQty;
                    $(this).val(currentQty);
                }

                const koliInput = row.find('.koli-input');
                const newKoli = (ratio > 0) ? (currentQty / ratio).toFixed(2) : 0;

                row.addClass('is-calculating');
                koliInput.val(newKoli);
                row.removeClass('is-calculating');
            });

            itemsContainer.on('input', '.koli-input', function() {
                const row = $(this).closest('tr');
                if (row.hasClass('is-calculating')) return;

                const ratio = parseFloat(row.data('koli-ratio')) || 1;
                const maxKoli = parseFloat(row.data('max-koli'));
                const maxQty = parseFloat(row.data('max-qty'));
                let currentKoli = parseFloat($(this).val());

                if (isNaN(currentKoli)) return;

                if (currentKoli > maxKoli) {
                    currentKoli = maxKoli;
                    $(this).val(currentKoli);
                }

                const qtyInput = row.find('.qty-input');
                let newQty = currentKoli * ratio;

                if (newQty > maxQty) {
                    newQty = maxQty;
                }

                row.addClass('is-calculating');
                qtyInput.val(newQty.toFixed(2));
                row.removeClass('is-calculating');
            });

            // Stepper controls for qty & koli
            itemsContainer.on('click', '.plus-btn, .minus-btn', function() {
                const btn = $(this);
                const row = btn.closest('tr');
                const type = btn.data('type'); // 'qty' or 'koli'
                const input = row.find(type === 'qty' ? '.qty-input' : '.koli-input');
                const max = parseFloat(input.attr('max')) || Infinity;
                let val = parseFloat(input.val()) || 0;
                const delta = btn.hasClass('plus-btn') ? 1 : -1;
                val = Math.max(0, Math.min(max, val + delta));
                input.val(val).trigger('input');
            });


            $(form).on('submit', function(e) {
                e.preventDefault();
                $('.is-invalid').removeClass('is-invalid');
                $('.invalid-feedback').text('');

                Swal.fire({
                    text: "Apakah Anda yakin ingin menyimpan data pengiriman ini?",
                    icon: "question",
                    showCancelButton: true,
                    buttonsStyling: false,
                    confirmButtonText: "Ya, Simpan!",
                    cancelButtonText: "Tidak, Batalkan",
                    customClass: {
                        confirmButton: "btn fw-bold btn-primary",
                        cancelButton: "btn fw-bold btn-active-light-primary"
                    }
                }).then(function(result) {
                    if (result.value) {
                        let formData = new FormData(form);
                        $.ajax({
                            url: $(form).attr('action'),
                            type: 'POST',
                            data: formData,
                            processData: false,
                            contentType: false,
                            success: function(response) {
                                $(kirimBarangModal).modal('hide');
                                toastr.success(`Data pengiriman berhasil disimpan.`);
                                loadDataTable();
                                form.reset();
                            },
                            error: function(xhr) {
                                if (xhr.status === 422) {
                                    var errors = xhr.responseJSON.errors;
                                    $.each(errors, function(key, value) {
                                        let field = $('[name="' + key + '"]');
                                        field.addClass('is-invalid');
                                        field.next('.invalid-feedback').text(value[0]);
                                    });
                                    toastr.error('Silakan perbaiki error validasi yang ada.',
                                        'Validasi Gagal');
                                } else {
                                    toastr.error(
                                        "Gagal menyimpan data pengiriman. Silakan coba lagi."
                                        );
                                }
                            }
                        });
                    }
                });
            });

        });
    </script>
@endpush
