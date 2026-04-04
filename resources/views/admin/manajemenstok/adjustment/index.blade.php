@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
@endpush

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Daftar Penyesuaian Stok',
        'breadcrumbs' => ['Admin', 'Manajemen Stok', 'Penyesuaian Stok'],
    ])
@endpush

@section('content')
    <div class="content flex-row-fluid" id="kt_content">
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
                            placeholder="Cari Penyesuaian Stok">
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
                                @if (auth()->user()->warehouse_id === null)
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
                                @endif
                                <div class="mb-10">
                                    <label class="form-label fs-5 fw-bold mb-3">Status:</label>
                                    <select class="form-select form-select-solid fw-bolder" data-kt-select2="true"
                                        id="status_filter" data-dropdown-parent="#kt-toolbar-filter">
                                        <option value="semua">Semua</option>
                                        <option value="pending">Pending</option>
                                        <option value="completed">Selesai</option>
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

                    <div class="d-flex justify-content-end">
                        <a href="{{ route('admin.manajemenstok.adjustment.create') }}"
                            class="btn btn-primary">Tambah Penyesuaian</a>
                    </div>
                </div>
            </div>
            <div class="card-body pt-4">
                <div class="text-center mb-5">
                    <h3 class="mb-0">Daftar Penyesuaian Stok</h3>
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

@push('scripts')
    <script>
        var table; // Declare table globally
        $(document).ready(function() {
            $("#date_filter").flatpickr({
                mode: "range",
                defaultDate: new Date(),
                onChange: function(selectedDates, dateStr, instance) {
                    if ($("#date_filter_options").val() !== 'pilih_tanggal') {
                        $("#date_filter_options").val('pilih_tanggal').trigger('change');
                    }
                }
            });

            $("#date_filter_options").on('change', function() {
                if ($(this).val() === 'pilih_tanggal') {
                    $("#date_filter").show();
                } else {
                    $("#date_filter").hide();
                    $("#date_filter").val(''); // Clear the date when 'Semua' is selected
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
                var statusFilter = $("#status_filter").val();
                var dateFilter = $("#date_filter_options").val() === 'semua' ? 'semua' : $("#date_filter").val();
                var warehouseFilter = $('#warehouse_filter').length ? $('#warehouse_filter').val() : null;

                var statusText = $("#status_filter option:selected").text();
                var dateText = dateFilter === 'semua' ? 'Semua Tanggal' : dateFilter;

                var filterInfoText = `Tanggal: ${dateText} | Status: ${statusText}`;
                if ($('#warehouse_filter').length) {
                    var warehouseText = $('#warehouse_filter option:selected').text();
                    filterInfoText += ` | Gudang: ${warehouseText}`;
                }

                $("#filter-info").text(filterInfoText);

                if ($.fn.DataTable.isDataTable('#table-on-page')) {
                    $("#table-on-page").DataTable().destroy();
                }

                table = $("#table-on-page").DataTable({
                    processing: true,
                    serverSide: true,
                    ajax: {
                        url: "{{ route('admin.manajemenstok.adjustment.index') }}",
                        type: "GET",
                        data: function(d) {
                            d.search.value = $("#search_input").val();
                            d.status = statusFilter;
                            d.date = dateFilter;
                            if(warehouseFilter) {
                                d.warehouse_id = warehouseFilter;
                            }
                        }
                    },
                    drawCallback: function(settings) {
                        KTMenu.createInstances();
                    },
                    columns: (function() {
                        let cols = [
                            { data: 'code', name: 'adj.code' },
                            { data: 'adjustment_date', name: 'adj.adjustment_date' },
                        ];

                        if (@json(auth()->user()->warehouse_id === null)) {
                            cols.push({ data: 'warehouse_name', name: 'warehouse_name' });
                        }

                        cols.push({ data: 'items_name', name: 'items_name' });
                        cols.push({ data: 'status', name: 'adj.status' });
                        cols.push({ data: 'id', name: 'adj.id', orderable: false, searchable: false });

                        return cols;
                    })(),
                    order: [
                        [0, 'desc']
                    ], // Default order by code descending
                    columnDefs: (function() {
                        const hasWarehouse = @json(auth()->user()->warehouse_id === null);
                        const statusIdx = hasWarehouse ? 4 : 3;
                        const actionsIdx = hasWarehouse ? 5 : 4;

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
                                } else if (data === 'pending') {
                                    badgeClass = 'warning';
                                }
                                return `<span class="badge badge-light-${badgeClass}">${data}</span>`;
                            }
                        },
                        {
                            targets: actionsIdx, // Actions column
                            render: function(data, type, row) {
                                let showUrl =
                                    "{{ route('admin.manajemenstok.adjustment.show', ':id') }}"
                                    .replace(':id', row.id);
                                let editUrl =
                                    "{{ route('admin.manajemenstok.adjustment.edit', ':id') }}"
                                    .replace(':id', row.id);
                                let destroyUrl =
                                    "{{ route('admin.manajemenstok.adjustment.destroy', ':id') }}"
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

                                if (row.status === 'pending') {
                                    actionsHtml += `
                                      <div class="menu-item px-3">
                                        <a href="${editUrl}" class="menu-link px-3">Edit</a>
                                    </div>
                                    <div class="menu-item px-3">
                                        <form class="form-delete" action="${destroyUrl}" method="POST">
                                            <input type="hidden" name="_token" value="${csrfToken}">
                                            <input type="hidden" name="_method" value="DELETE">
                                            <button type="submit" class="menu-link px-3 border-0 bg-transparent w-100 text-start" data-document-code="${row.code}">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                    <div class="menu-item px-3">
                                        <a href="#" class="menu-link px-3" onclick="confirmStatusChange(${row.id}, 'completed', '${row.code}')">Selesaikan</a>
                                    </div>
                                    `;
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

            $("#apply_filter").on('click', function() {
                loadDataTable();
                $("[data-kt-menu-dismiss='true']").click();
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
            $("#search_input").on('keyup', debounce(function() {
                table.draw();
            }, 500)); // 500ms delay

            $("#table-on-page").on('submit', '.form-delete', function(e) {
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

                                toastr.error("Gagal menghapus dokumen " + n +
                                    ". Silakan coba lagi.");
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
                if (status === 'completed') {
                    confirmationText =
                        `Apakah Anda yakin ingin mengubah status penyesuaian stok ${code} menjadi 'Selesai'?`;
                    successText = `Status penyesuaian stok ${code} berhasil diubah menjadi 'Selesai'.`;
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
                            url: `/admin/manajemen-stok/adjustment/${id}/update-status`,
                            type: 'POST',
                            data: {
                                _token: $("meta[name='csrf-token']").attr('content'),
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