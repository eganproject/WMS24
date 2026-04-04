@extends('layouts.app')

@push('styles')
    <style>
        .status-card-icon .symbol-label { background: #F3E8FF; }
        .status-card-icon i { color: #7C3AED; }
        .status-metrics { display: flex; gap: 1.5rem; align-items: center; flex-wrap: wrap; justify-content: flex-end; }
        .status-metrics .metric { text-align: center; min-width: 88px; }
        .status-metrics .chip { display: inline-block; padding: 6px 12px; border-radius: 10px; font-weight: 700; font-size: 1rem; line-height: 1; }
        .chip-warning { background: #FFF7E6; color: #F59E0B; }
        .chip-primary { background: #E6F0FF; color: #3B82F6; }
        .chip-success { background: #ECFDF5; color: #10B981; }
        .chip-danger { background: #FEE2E2; color: #EF4444; }
        @media (max-width: 576px) { .status-metrics { justify-content: flex-start; } .status-metrics .metric { min-width: 70px; } }

        /* Distribution Modal */
        #modal-import .modal-content.dist-modal .modal-body { max-height: 70vh; overflow: auto; }
        #modal-import .modal-content.dist-modal .table thead th { position: sticky; top: 0; background: #fff; z-index: 5; }
        #modal-import .modal-content.dist-modal .form-control-sm, 
        #modal-import .modal-content.dist-modal .form-select-sm { min-width: 80px; }
        #modal-import .modal-content.dist-modal .name-col { max-width: 240px; }
        #modal-import .modal-content.dist-modal .name-col .truncate { display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        /* Header meta */
        #modal-import .dist-header .meta-label { font-size: .825rem; color: #6B7280; }
        #modal-import .dist-header .meta-value { font-weight: 600; color: #111827; }

        /* Cards */
        #modal-import .dist-card { border: 1px solid #EFF2F5; border-radius: .75rem; background: #FBFBFC; }
        #modal-import .dist-card + .dist-card { margin-top: .75rem; }
        #modal-import .dist-card .card-body { padding: 1rem 1rem; }
        #modal-import .sisa-box .label { font-size: .8rem; color: #6B7280; }
        #modal-import .sisa-box .value { font-weight: 700; color: #334155; }
        #modal-import .form-label { font-size: .8rem; color: #6B7280; margin-bottom: .35rem; }
        @media (max-width: 576px) {
          #modal-import .dist-card .card-body { padding: .75rem; }
        }
    </style>
@endpush

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Pengadaan',
        'breadcrumbs' => ['Admin', 'Stok Masuk', 'Pengadaan'],
    ])
@endpush

@php
    $permissionResolver = app(\App\Support\MenuPermissionResolver::class);
    $canCreate = $permissionResolver->userCan('create');
    $canEdit = $permissionResolver->userCan('edit');
    $canDelete = $permissionResolver->userCan('delete');
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
                                    <div class="text-dark fw-bolder fs-5 mb-1">Status Pengadaan</div>
                                    <div class="text-muted fw-bold">Dokumen Pengadaan</div>
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
                            placeholder="Cari Pengadaan">
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
                                @if (is_null(auth()->user()->warehouse_id))
                                    <div class="mb-10">
                                        <label class="form-label fs-5 fw-bold mb-3">Gudang:</label>
                                        <select class="form-select form-select-solid fw-bolder" data-kt-select2="true"
                                            id="warehouse_filter" data-dropdown-parent="#kt-toolbar-filter">
                                            <option value="semua">Semua</option>
                                            @foreach ($warehouses as $warehouse)
                                                <option value="{{ $warehouse->id }}">{{ $warehouse->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                                <div class="mb-10">
                                    <label class="form-label fs-5 fw-bold mb-3">Status:</label>
                                    <select class="form-select form-select-solid fw-bolder" data-kt-select2="true"
                                        id="status_filter" data-dropdown-parent="#kt-toolbar-filter">
                                        <option value="semua">Semua</option>
                                        <option value="requested">Dalam Request</option>
                                        <option value="on_progress">Dalam Pengiriman</option>
                                        <option value="completed">Selesai</option>
                                        <option value="rejected">Ditolak</option>
                                    </select>
                                </div>
                                <div class="mb-10">
                                    <label class="form-label fs-5 fw-bold mb-3">Tanggal:</label>
                                    <select class="form-select form-select-solid fw-bolder" data-kt-select2="true"
                                        id="date_filter_options" data-dropdown-parent="#kt-toolbar-filter">
                                        <option value="semua">Semua Tanggal</option>
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

                    @if($canCreate)
                    <div class="d-flex justify-content-end">
                        <a href="{{ route('admin.stok-masuk.pengadaan.create') }}"
                            class="btn btn-primary">Tambah Pengadaan</a>
                    </div>
                    @endif
                </div>
            </div>
            <div class="card-body pt-4">
                <div class="text-center mb-5">
                    <h3 class="mb-0">Pengadaan</h3>
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
                                    @if(auth()->user()->warehouse_id === null)
                                    <th class="min-w-125px sorting">Gudang</th>
                                    @endif
                                    <th class="min-w-125px sorting">Item</th>
                                    <th class="min-w-125px sorting">Status</th>
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

{{-- Modal Kirim Barang dihapus --}}

@push('scripts')
    <script>
        var table; // Declare table globally
        $(document).ready(function() {
            const canEdit = @json($canEdit);
            const canDelete = @json($canDelete);
            $('#date_filter').flatpickr({
                mode: "range",
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

            function loadDataTable() {
                var statusFilter = $('#status_filter').val();
                var dateFilter = $('#date_filter_options').val() === 'semua' ? 'semua' : $('#date_filter').val();
                var warehouseFilter = $('#warehouse_filter').length ? $('#warehouse_filter').val() : null;

                $.ajax({
                    url: "{{ route('admin.stok-masuk.pengadaan.status-counts') }}",
                    type: "GET",
                    data: {
                        status: statusFilter,
                        date: dateFilter,
                        warehouse: warehouseFilter
                    },
                    success: function(response) {
                        $('#status_requested').text(response.requested);
                        $('#status_on_progress').text(response.on_progress);
                        $('#status_completed').text(response.completed);
                        $('#status_rejected').text(response.rejected);
                    }
                });

                var statusText = $('#status_filter option:selected').text();
                var dateText = dateFilter === 'semua' ? 'Semua Tanggal' : dateFilter;

                var filterInfoText = `Tanggal: ${dateText} | Status: ${statusText}`;
                if ($('#warehouse_filter').length) {
                    var warehouseText = $('#warehouse_filter option:selected').text();
                    filterInfoText += ` | Gudang: ${warehouseText}`;
                }

                $('#filter-info').text(filterInfoText);

                if ($.fn.DataTable.isDataTable('#table-on-page')) {
                    $('#table-on-page').DataTable().destroy();
                }

                table = $('#table-on-page').DataTable({
                    processing: true,
                    serverSide: true,
                    ajax: {
                        url: "{{ route('admin.stok-masuk.pengadaan.index') }}",
                        type: "GET",
                        data: function(d) {
                            d.search.value = $('#search_input').val();
                            d.status = statusFilter;
                            d.date = dateFilter;
                            if (warehouseFilter) {
                                d.warehouse = warehouseFilter;
                            }
                        }
                    },
                    drawCallback: function(settings) {
                        KTMenu.createInstances();
                    },
                    columns: (function() {
                        let cols = [
                            { data: 'code', name: 'sio.code' },
                            { data: 'date', name: 'sio.date' },
                            { data: 'type', name: 'sio.type' },
                        ];

                        if (@json(auth()->user()->warehouse_id === null)) {
                            cols.push({ data: 'warehouse_name', name: 'warehouse_name' });
                        }

                        cols.push({ data: 'items_name', name: 'items_name' });
                        cols.push({ data: 'status', name: 'sio.status' });
                        cols.push({ data: 'id', name: 'sio.id', orderable: false, searchable: false });

                        return cols;
                    })(),
                    order: [
                        [0, 'desc']
                    ], // Default order by code descending
                    columnDefs: (function(){
                        const hasWarehouse = @json(auth()->user()->warehouse_id === null);
                        const statusIdx = hasWarehouse ? 5 : 4;
                        const actionsIdx = hasWarehouse ? 6 : 5;

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
                                }
                                const label = data.replace(/_/g, " ").replace(/\b\w/g, (char) => char.toUpperCase());
                                return `<span class="badge badge-light-${badgeClass}">${label}</span>`;
                            }
                        },
                        {
                            targets: actionsIdx, // Actions column
                            render: function(data, type, row) {
                                let showUrl =
                                    "{{ route('admin.stok-masuk.pengadaan.show', ':id') }}"
                                    .replace(':id', row.id);
                                let editUrl =
                                    "{{ route('admin.stok-masuk.pengadaan.edit', ':id') }}"
                                    .replace(':id', row.id);
                                let destroyUrl =
                                    "{{ route('admin.stok-masuk.pengadaan.destroy', ':id') }}"
                                    .replace(':id', row.id);
                                let distributionsUrl =
                                    "{{ route('admin.stok-masuk.pengadaan.distributions', ':id') }}"
                                    .replace(':id', row.id);
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

                                // Tambah tombol distribusi untuk tipe import dan belum completed
                                if (row.type === 'import' && row.status !== 'completed') {
                                    actionsHtml += `
                                    <div class="menu-item px-3">
                                        <a href="${distributionsUrl}" class="menu-link px-3">Lihat Distribusi</a>
                                    </div>
                                    <div class="menu-item px-3">
                                        <button type="button" class="menu-link px-3 w-100 text-start border-0 bg-transparent btn-open-import-modal" data-sio-id="${row.id}">Sesuaikan Distribusi</button>
                                    </div>`;
                                }

                                // Kirim Barang action removed

                                if (row.status === 'requested') {
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
                                loadDataTable()
                            },
                            error: function(xhr) {

                                toastr.error("Gagal menghapus dokumen " + n +
                                    ". Silakan coba lagi.");
                            }
                        });
                    } else if (result.dismiss === 'cancel') {

                        toastr.info("Penghapusan dokumen " + n + " dibatalkan.");
                    }
                });
            });

            // Modal detail import
            $(document).on('click', '.btn-open-import-modal', function(){
                const id = $(this).data('sio-id');
                const url = "{{ route('admin.stok-masuk.pengadaan.details', ':id') }}".replace(':id', id);
                $('#modal-import .modal-body').html('<div class="text-center py-10">Memuat...</div>');
                const modalEl = new bootstrap.Modal(document.getElementById('modal-import'));
                modalEl.show();
                fetch(url)
                  .then(r=>r.json())
                  .then(data=>{
                      let headerHtml = `
                        <div class="dist-header mb-4">
                          <div class="row g-3 align-items-center">
                            <div class="col-12 col-md-4">
                              <div class="meta-label">Kode</div>
                              <div class="meta-value">${data.code}</div>
                            </div>
                            <div class="col-6 col-md-3">
                              <div class="meta-label">Tanggal</div>
                              <div class="meta-value">${data.date ?? '-'}</div>
                            </div>
                            <div class="col-6 col-md-3">
                              <div class="meta-label">Gudang</div>
                              <div class="meta-value">${data.warehouse ?? '-'}</div>
                            </div>
                            <div class="col-6 col-md-2">
                              <div class="meta-label">Status</div>
                              <div class="meta-value text-capitalize">${(data.status || '').replace(/_/g,' ')}</div>
                            </div>
                          </div>
                        </div>
                      `;
                      // Build form with remaining only + input qty + koli + dropdown warehouse dan catatan
                      const whOptions = (data.warehouses||[]).map(w=>`<option value="${w.id}" ${String(w.id)===String(data.warehouse_id)?'selected':''}>${w.name}</option>`).join('');
                      let rows = (data.items||[]).map((it,idx)=>{
                        const disabled = (it.remaining_quantity <= 0 && it.remaining_koli <= 0) ? 'disabled' : '';
                        return `<tr data-koli-ratio="${it.item_koli_ratio||1}" data-remaining-qty="${it.remaining_quantity||0}" data-remaining-koli="${it.remaining_koli||0}">
                          <td>${idx+1}<input type="hidden" name="distributions[${idx}][stock_in_order_item_id]" value="${it.id}"></td>
                          <td class="d-none d-md-table-cell">${it.sku}</td>
                          <td class="name-col"><div class="truncate">${it.name}</div></td>
                          <td class="text-end">${it.remaining_quantity}</td>
                          <td class="text-end d-none d-lg-table-cell">${it.remaining_koli}</td>
                          <td class="text-end"><input ${disabled} type="number" min="0" max="${it.remaining_quantity}" step="1" class="form-control form-control-sm text-end dist-qty" name="distributions[${idx}][quantity]" value="0"></td>
                          <td class="text-end d-none d-lg-table-cell"><input ${disabled} type="number" min="0" max="${it.remaining_koli}" step="0.01" class="form-control form-control-sm text-end dist-koli" name="distributions[${idx}][koli]" value="0"></td>
                          <td><select ${disabled} class="form-select form-select-sm" name="distributions[${idx}][to_warehouse_id]">${whOptions}</select></td>
                          <td class="d-none d-md-table-cell"><input ${disabled} type="text" class="form-control form-control-sm" name="distributions[${idx}][note]" placeholder="Catatan (opsional)"></td>
                        </tr>`
                      }).join('');
                      // Build card-based list for better responsiveness
                      const today = new Date();
                      const todayStr = today.toISOString().slice(0,10);
                      let cardsHtml = (data.items||[]).map((it,idx)=>{
                        const disabled = (it.remaining_quantity <= 0 && it.remaining_koli <= 0) ? 'disabled' : '';
                        return `
                        <div class="card dist-card" data-koli-ratio="${it.item_koli_ratio||1}" data-remaining-qty="${it.remaining_quantity||0}" data-remaining-koli="${it.remaining_koli||0}">
                          <div class="card-body">
                            <input type="hidden" name="distributions[${idx}][stock_in_order_item_id]" value="${it.id}">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                              <div class="me-3">
                                <div class="fw-bold">${it.sku}</div>
                                <div class="text-muted name-col"><span class="truncate">${it.name}</span></div>
                              </div>
                              <div class="d-flex gap-4 sisa-box">
                                <div class="text-end">
                                  <div class="label">Sisa Qty</div>
                                  <div class="value">${it.remaining_quantity}</div>
                                </div>
                                <div class="text-end d-none d-lg-block">
                                  <div class="label">Sisa Koli</div>
                                  <div class="value">${it.remaining_koli}</div>
                                </div>
                              </div>
                            </div>
                            <div class="row g-3 align-items-end">
                              <div class="col-6 col-md-3">
                                <label class="form-label">Tanggal</label>
                                <input ${disabled} type="date" class="form-control form-control-sm" name="distributions[${idx}][date]" value="${todayStr}">
                              </div>
                              <div class="col-6 col-md-2">
                                <label class="form-label">Qty Distribusi</label>
                                <input ${disabled} type="number" min="0" max="${it.remaining_quantity}" step="1" class="form-control form-control-sm text-end dist-qty" name="distributions[${idx}][quantity]" value="0">
                              </div>
                              <div class="col-6 col-md-2 d-none d-lg-block">
                                <label class="form-label">Koli Distribusi</label>
                                <input ${disabled} type="number" min="0" max="${it.remaining_koli}" step="0.01" class="form-control form-control-sm text-end dist-koli" name="distributions[${idx}][koli]" value="0">
                              </div>
                              <div class="col-6 col-md-3">
                                <label class="form-label">Tujuan</label>
                                <select ${disabled} class="form-select form-select-sm" name="distributions[${idx}][to_warehouse_id]">${whOptions}</select>
                              </div>
                              <div class="col-12 col-md">
                                <label class="form-label d-none d-md-inline">Catatan</label>
                                <input ${disabled} type="text" class="form-control form-control-sm" name="distributions[${idx}][note]" placeholder="Catatan (opsional)">
                              </div>
                            </div>
                          </div>
                        </div>`;
                      }).join('');
                      let formHtml = `
                        <form id="form-distribution" method="POST" action="${"{{ route('admin.stok-masuk.pengadaan.save-distributions', ':id') }}".replace(':id', id)}">
                          <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                          ${cardsHtml}
                        </form>`;
                      $('#modal-import .modal-body').html(headerHtml + formHtml);
                      // Attach auto-calc handlers like create page
                      const $form = $('#form-distribution');
                      $form.off('input.dist');
                      $form.on('input', '.dist-qty', function(){
                          const $row = $(this).closest('.dist-card');
                          const ratio = parseFloat($row.data('koli-ratio')) || 1;
                          const remQty = parseFloat($row.data('remaining-qty')) || 0;
                          const remKoli = parseFloat($row.data('remaining-koli')) || 0;
                          let qty = parseFloat($(this).val());
                          if (isNaN(qty)) qty = 0;
                          qty = Math.round(qty);
                          if (qty > remQty) qty = Math.round(remQty);
                          if (qty < 0) qty = 0;
                          $(this).val(qty);
                          let koli = ratio > 0 ? qty / ratio : 0;
                          let koliRounded = parseFloat(koli.toFixed(2));
                          if (!isNaN(remKoli) && Math.abs(remKoli - koliRounded) <= 0.01) {
                              koliRounded = remKoli;
                          }
                          if (!isNaN(remKoli) && koliRounded > remKoli) {
                              koliRounded = remKoli;
                          }
                          $row.find('.dist-koli').val(koliRounded.toFixed(2));
                      });
                      $form.on('input', '.dist-koli', function(){
                          const $row = $(this).closest('.dist-card');
                          const ratio = parseFloat($row.data('koli-ratio')) || 1;
                          const remQty = parseFloat($row.data('remaining-qty')) || 0;
                          const remKoli = parseFloat($row.data('remaining-koli')) || 0;
                          let koli = parseFloat($(this).val());
                          if (isNaN(koli)) koli = 0;
                          if (koli > remKoli) { koli = remKoli; $(this).val(koli); }
                          if (!isNaN(remKoli) && Math.abs(remKoli - koli) <= 0.01) { koli = remKoli; $(this).val(koli.toFixed(2)); }
                          let qty = Math.round(koli * ratio);
                          if (!isNaN(remQty) && qty > remQty) qty = Math.round(remQty);
                          if (qty < 0) qty = 0;
                          $row.find('.dist-qty').val(qty);
                      });

                      // Attach submit handler
                      $('#modal-import .btn-submit').off('click').on('click', function(){
                          Swal.fire({
                              title: 'Simpan distribusi?',
                              text: 'Pastikan data distribusi sudah benar.',
                              icon: 'warning',
                              showCancelButton: true,
                              confirmButtonText: 'Ya, simpan',
                              cancelButtonText: 'Batal',
                              confirmButtonColor: '#3085d6',
                              cancelButtonColor: '#d33'
                          }).then((result)=>{
                              if (!result.isConfirmed) return;
                              const btn = $('#modal-import .btn-submit');
                              btn.prop('disabled', true).addClass('disabled');
                              fetch($form.attr('action'), {
                                  method: 'POST',
                                  headers: { 'X-Requested-With':'XMLHttpRequest' },
                                  body: new FormData($form[0])
                              }).then(r=>r.json()).then(resp=>{
                                  if (resp.success){
                                      toastr.success(resp.message || 'Distribusi tersimpan');
                                      table.draw(false);
                                      $('#modal-import').modal ? $('#modal-import').modal('hide') : (bootstrap.Modal.getInstance(document.getElementById('modal-import'))?.hide());
                                  } else {
                                      toastr.error(resp.message || 'Gagal menyimpan distribusi');
                                  }
                              }).catch(()=> toastr.error('Gagal menyimpan distribusi'))
                                .finally(()=> btn.prop('disabled', false).removeClass('disabled'));
                          });
                      });
                  })
                  .catch(()=>{
                      $('#modal-import .modal-body').html('<div class="text-danger">Gagal memuat data.</div>');
                  });
            });
        });
    </script>
@endpush

<div class="modal fade" id="modal-import" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Detail Pengadaan (Import)</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="text-center py-10">Memuat...</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary btn-submit">Simpan Distribusi</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
 </div>
