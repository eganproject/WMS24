@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
@endpush

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Kartu Stok',
        'breadcrumbs' => ['Admin', 'Manajemen Stok', 'Kartu Stok'],
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
                                    transform="rotate(45 17.0365 15.1223)" fill="black" />
                                <path
                                    d="M11 19C6.55556 19 3 15.4444 3 11C3 6.55556 6.55556 3 11 3C15.4444 3 19 6.55556 19 11C19 15.4444 15.4444 19 11 19ZM11 5C7.53333 5 5 7.53333 5 11C5 14.4667 7.53333 17 11 17C14.4667 17 17 14.4667 17 11C17 7.53333 14.4667 5 11 5Z"
                                    fill="black" />
                            </svg>
                        </span>
                        <input type="text" id="search_input" class="form-control form-control-solid w-250px ps-15"
                            placeholder="Cari Stok">
                    </div>
                </div>
                <div class="card-toolbar">
                    <a href="#" class="btn btn-light-success  me-3" id="export_excel">
                        <span class="svg-icon svg-icon-2">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none">
                                <rect opacity="0.3" x="12.75" y="4.25" width="12" height="2" rx="1"
                                    transform="rotate(90 12.75 4.25)" fill="black"></rect>
                                <path
                                    d="M12.0573 6.11875L13.5203 7.87435C13.9121 8.34457 14.6232 8.37683 15.056 7.94401C15.4457 7.5543 15.4641 6.92836 15.0979 6.51643L12.4974 3.59084C12.0996 3.14332 11.4004 3.14332 11.0026 3.59084L8.40206 6.51643C8.0359 6.92836 8.0543 7.5543 8.44401 7.94401C8.87683 8.37683 9.58785 8.34458 9.9797 7.87435L11.4427 6.11875C11.6026 5.92684 11.8974 5.92684 12.0573 6.11875Z"
                                    fill="black"></path>
                                <path
                                    d="M18.75 8.25H17.75C17.1977 8.25 16.75 8.69772 16.75 9.25C16.75 9.80228 17.1977 10.25 17.75 10.25C18.3023 10.25 18.75 10.6977 18.75 11.25V18.25C18.75 18.8023 18.3023 19.25 17.75 19.25H5.75C5.19772 19.25 4.75 18.8023 4.75 18.25V11.25C4.75 10.6977 5.19771 10.25 5.75 10.25C6.30229 10.25 6.75 9.80228 6.75 9.25C6.75 8.69772 6.30229 8.25 5.75 8.25H4.75C3.64543 8.25 2.75 9.14543 2.75 10.25V19.25C2.75 20.3546 3.64543 21.25 4.75 21.25H18.75C19.8546 21.25 20.75 20.3546 20.75 19.25V10.25C20.75 9.14543 19.8546 8.25 18.75 8.25Z"
                                    fill="#C4C4C4"></path>
                            </svg>
                        </span>
                        Export Excel
                    </a>
                    <button type="button" class="btn btn-light-primary" data-kt-menu-trigger="click"
                        data-kt-menu-placement="bottom-end">
                        <span class="svg-icon svg-icon-2">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none">
                                <path
                                    d="M19.0759 3H4.72777C3.95892 3 3.47768 3.83148 3.86067 4.49814L8.56967 12.6949C9.17923 13.7559 9.5 14.9582 9.5 16.1819V19.5072C9.5 20.2189 10.2223 20.7028 10.8805 20.432L13.8805 19.1977C14.2553 19.0435 14.5 18.6783 14.5 18.273V13.8372C14.5 12.8089 14.8171 11.8056 15.408 10.964L19.8943 4.57465C20.3596 3.912 19.8856 3 19.0759 3Z"
                                    fill="black" />
                            </svg>
                        </span>
                        Filter
                    </button>
                    <div class="menu menu-sub menu-sub-dropdown w-300px w-md-325px" data-kt-menu="true"
                        id="kt-toolbar-filter">
                        <div class="px-7 py-5">
                            <div class="fs-4 text-dark fw-bolder">Filter Options</div>
                        </div>
                        <div class="separator border-gray-200"></div>
                        <div class="px-7 py-5">
                            @php
                                $userWarehouseId = auth()->user()->warehouse_id;
                                $userWarehouseName = optional(auth()->user()->warehouse)->name;
                            @endphp
                            @if (!$userWarehouseId)
                                <div class="mb-10">
                                    <label class="form-label fs-5 fw-bold mb-3">Gudang:</label>
                                    <select class="form-select form-select-solid fw-bolder" data-kt-select2="true"
                                        id="warehouse_filter" data-dropdown-parent="#kt-toolbar-filter">
                                        <option value="semua">Semua Gudang</option>
                                        @foreach ($warehouses as $warehouse)
                                            <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @else
                                <div class="mb-10">
                                    <label class="form-label fs-5 fw-bold mb-3">Gudang:</label>
                                    <input type="text" class="form-control form-control-solid"
                                        value="{{ $userWarehouseName ?? 'Gudang Anda' }}" readonly>
                                    <input type="hidden" id="warehouse_filter" value="{{ $userWarehouseId }}">
                                </div>
                            @endif
                            <div class="mb-10">
                                <label class="form-label fs-5 fw-bold mb-3">Produk:</label>
                                <select class="form-select form-select-solid fw-bolder" data-kt-select2="true"
                                    id="product_filter" data-dropdown-parent="#kt-toolbar-filter" data-placeholder="Pilih Produk">
                                    <option value="semua">Semua Produk</option>
                                    @foreach ($items as $item)
                                        <option value="{{ $item->id }}">{{ ($item->sku ?? '-') . ' - ' . ($item->nama_barang ?? '-') }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-10">
                                <label class="form-label fs-5 fw-bold mb-3">Tanggal:</label>
                                <input class="form-control form-control-solid" placeholder="Pilih Rentang Tanggal"
                                    id="date_filter" />
                            </div>
                            <div class="d-flex justify-content-end">
                                <button type="reset" class="btn btn-light btn-active-light-primary me-2"
                                    data-kt-menu-dismiss="true">Batal</button>
                                <button type="button" class="btn btn-primary" id="apply_filter"
                                    data-kt-menu-dismiss="true">Terapkan</button>
                            </div>
                        </div>
                    </div>
                    
                </div>
            </div>
            <div class="card-body pt-4">
                <div class="text-center mb-5">
                    <h3 class="mb-0">Kartu Stok Item</h3>
                    <small id="filter-info" class="text-muted"></small>
                </div>
                <div class="dataTables_wrapper dt-bootstrap4 no-footer">
                    <div class="table-responsive min-h-500px">
                        <table class="table align-middle table-row-dashed fs-6 gy-5 dataTable no-footer"
                            id="table-on-page">
                            <thead>
                                <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                    <th class="min-w-125px" rowspan="2">Tanggal</th>
                                    @if (!$userWarehouseId)
                                        <th class="min-w-150px" rowspan="2">Gudang</th>
                                    @endif
                                    <th class="min-w-200px" style="width:200px" rowspan="2">Item</th>
                                    <th class="min-w-200px" rowspan="2">Keterangan</th>
                                    <th class="text-center" colspan="2">Stok Awal</th>
                                    <th class="text-center" colspan="2">Stok Masuk</th>
                                    <th class="text-center" colspan="2">Stok Keluar</th>
                                    <th class="text-center" colspan="2">Stok Akhir</th>
                                    <th class="min-w-150px" rowspan="2">User</th>
                                </tr>
                                <tr class="text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                    <th class="min-w-110px text-center">Qty</th>
                                    <th class="min-w-110px text-center">Koli</th>
                                    <th class="min-w-110px text-center">Qty</th>
                                    <th class="min-w-110px text-center">Koli</th>
                                    <th class="min-w-110px text-center">Qty</th>
                                    <th class="min-w-110px text-center">Koli</th>
                                    <th class="min-w-110px text-center">Qty</th>
                                    <th class="min-w-110px text-center">Koli</th>
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
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        var table;
        $(document).ready(function() {
            const userWarehouseId = @json(auth()->user()->warehouse_id);
            const userWarehouseName = @json(optional(auth()->user()->warehouse)->name);
            const exportUrl = @json(route('admin.manajemenstok.kartustok.export'));

            flatpickr('#date_filter', {
                mode: 'range',
                dateFormat: 'Y-m-d',
                allowInput: true,
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

            function formatNumber(value) {
                const number = parseFloat(value ?? 0);
                if (isNaN(number)) {
                    return '0';
                }
                return number.toLocaleString('id-ID', {
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 2,
                });
            }

            function loadDataTable() {
                const dateFilter = $('#date_filter').val();
                let warehouseFilter = $('#warehouse_filter').val();

                const showWarehouseColumn = !userWarehouseId;

                let warehouseText = 'Semua Gudang';
                if (userWarehouseId) {
                    warehouseFilter = userWarehouseId;
                    warehouseText = userWarehouseName || 'Gudang Anda';
                } else if (warehouseFilter && warehouseFilter !== 'semua') {
                    warehouseText = $('#warehouse_filter option:selected').text();
                }

                const productSelect = $('#product_filter');
                let productFilter = productSelect.val();
                if (!productFilter) {
                    productFilter = 'semua';
                }
                let productText = 'Semua Produk';
                if (productFilter !== 'semua') {
                    productText = productSelect.find('option:selected').text();
                }

                const dateText = dateFilter ? `Periode: ${dateFilter}` : 'Menampilkan semua data';
                $('#filter-info').text(`${dateText} | Gudang: ${warehouseText} | Produk: ${productText}`);

                if ($.fn.DataTable.isDataTable('#table-on-page')) {
                    $('#table-on-page').DataTable().destroy();
                }

                const columns = [{
                    data: 'date',
                    name: 'sm.date',
                    render: function(data) {
                        return data || '-';
                    }
                }];

                if (showWarehouseColumn) {
                    columns.push({
                        data: 'warehouse_name',
                        name: 'w.name',
                        render: function(data) {
                            return data || '-';
                        }
                    });
                }

                columns.push({
                    data: 'sku_name',
                    name: 'i.sku',
                    width: '200px',
                    render: function(data, type, row) {
                        const sku = data || '-';
                        const itemName = row && row.item_name ? row.item_name : '';

                        if (type !== 'display') {
                            return itemName ? `${sku} ${itemName}`.trim() : sku;
                        }

                        if (!itemName) {
                            return sku;
                        }

                        return `<div class="fw-bold">${sku}</div><div class="text-muted">${itemName}</div>`;
                    }
                }, {
                    data: 'description',
                    name: 'sm.description',
                    render: function(data) {
                        return data || '-';
                    }
                }, {
                    data: 'stock_before',
                    name: 'sm.stock_before',
                    className: 'text-center',
                    render: function(data) {
                        return formatNumber(data);
                    }
                }, {
                    data: 'stock_before_koli',
                    name: 'stock_before_koli',
                    searchable: false,
                    className: 'text-center',
                    render: function(data) {
                        return formatNumber(data);
                    }
                }, {
                    data: 'stock_in',
                    name: 'stock_in',
                    searchable: false,
                    className: 'text-center',
                    render: function(data) {
                        return formatNumber(data);
                    }
                }, {
                    data: 'stock_in_koli',
                    name: 'stock_in_koli',
                    searchable: false,
                    className: 'text-center',
                    render: function(data) {
                        return formatNumber(data);
                    }
                }, {
                    data: 'stock_out',
                    name: 'stock_out',
                    searchable: false,
                    className: 'text-center',
                    render: function(data) {
                        return formatNumber(data);
                    }
                }, {
                    data: 'stock_out_koli',
                    name: 'stock_out_koli',
                    searchable: false,
                    className: 'text-center',
                    render: function(data) {
                        return formatNumber(data);
                    }
                }, {
                    data: 'stock_after',
                    name: 'sm.stock_after',
                    className: 'text-center',
                    render: function(data) {
                        return formatNumber(data);
                    }
                }, {
                    data: 'stock_after_koli',
                    name: 'stock_after_koli',
                    searchable: false,
                    className: 'text-center',
                    render: function(data) {
                        return formatNumber(data);
                    }
                }, {
                    data: 'user_name',
                    name: 'u.name',
                    render: function(data) {
                        return data || '-';
                    }
                });

                table = $('#table-on-page').DataTable({
                    processing: true,
                    serverSide: true,
                    ajax: {
                        url: "{{ route('admin.manajemenstok.kartustok.index') }}",
                        type: "GET",
                        data: function(d) {
                            d.search = d.search || {};
                            d.search.value = $('#search_input').val();
                            d.date_filter = dateFilter;
                            d.warehouse_filter = warehouseFilter;
                            d.product_filter = productFilter;
                        }
                    },
                    order: [
                        [0, 'desc']
                    ],
                    columns: columns
                });
            }

            loadDataTable();

            $('#apply_filter').on('click', function() {
                loadDataTable();
            });

            $('#kt-toolbar-filter [type="reset"]').on('click', function() {
                if (!userWarehouseId) {
                    $('#warehouse_filter').val('semua').trigger('change');
                }
                $('#product_filter').val('semua').trigger('change');
                $('#date_filter').val('');
                loadDataTable();
            });

            const debounce = (callback, wait) => {
                let timeoutId = null;
                return (...args) => {
                    window.clearTimeout(timeoutId);
                    timeoutId = window.setTimeout(() => {
                        callback.apply(null, args);
                    }, wait);
                };
            };

            $('#search_input').on('keyup', debounce(function() {
                if (table) {
                    table.ajax.reload();
                }
            }, 500));

            $('#export_excel').on('click', function(e) {
                e.preventDefault();

                Swal.fire({
                    title: 'Apakah anda akan mengeksport data excel ?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Ya, ekspor!',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        const params = new URLSearchParams();
                        const searchValue = $('#search_input').val();
                        const dateFilter = $('#date_filter').val();
                        let warehouseFilter = $('#warehouse_filter').val();
                        let productFilter = $('#product_filter').val();

                        if (userWarehouseId) {
                            warehouseFilter = userWarehouseId;
                        }

                        if (!productFilter) {
                            productFilter = 'semua';
                        }

                        if (searchValue) {
                            params.append('search', searchValue);
                        }
                        if (dateFilter) {
                            params.append('date_filter', dateFilter);
                        }
                        if (warehouseFilter && warehouseFilter !== 'semua') {
                            params.append('warehouse_filter', warehouseFilter);
                        }
                        if (productFilter && productFilter !== 'semua') {
                            params.append('product_filter', productFilter);
                        }

                        const targetUrl = params.toString() ? `${exportUrl}?${params.toString()}` :
                            exportUrl;
                        window.location.href = targetUrl;
                    }
                });
            });
        });
    </script>
@endpush
