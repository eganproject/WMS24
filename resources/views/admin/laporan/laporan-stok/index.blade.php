@extends('layouts.app')

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Laporan Stok',
        'breadcrumbs' => ['Admin', 'Laporan', 'Laporan Stok'],
    ])
@endpush

@section('content')
    <div class="content flex-row-fluid" id="kt_content">
        <!--begin::Row-->
        <div class="row g-5 g-xl-8">
            <div class="col-xl-4">
                <div class="card bg-body-white hoverable card-xl-stretch mb-xl-8 bgi-no-repeat" style="background-position: right top; background-size: 30% auto; background-image: url({{ asset('metronic/assets/media/svg/shapes/abstract-4.svg') }})">
                    <div class="card-body">
                        <span class="svg-icon svg-icon-3x svg-icon-primary ms-n1">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
								<path d="M21.5 6.5C21.5 5.9 21.1 5.5 20.5 5.5H2.5C1.9 5.5 1.5 5.9 1.5 6.5V17.5C1.5 18.1 1.9 18.5 2.5 18.5H20.5C21.1 18.5 21.5 18.1 21.5 17.5V6.5Z" fill="black"/>
							</svg>
                        </span>
                        <div class="text-gray-900 fw-bolder fs-2 mb-2 mt-5" id="total_unique_items">-</div>
                        <div class="fw-bold text-gray-400">Total Item Unik</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="card bg-primary hoverable card-xl-stretch mb-xl-8 bgi-no-repeat" style="background-position: right top; background-size: 30% auto; background-image: url({{ asset('metronic/assets/media/svg/shapes/abstract-2.svg') }})">
                    <div class="card-body">
                        <span class="svg-icon svg-icon-3x svg-icon-white ms-n1">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
								<path opacity="0.3" d="M20 15H4C2.9 15 2 14.1 2 13V7C2 5.9 2.9 5 4 5H20C21.1 5 22 5.9 22 7V13C22 14.1 21.1 15 20 15Z" fill="black"/>
								<path d="M20 7H4C3.4 7 3 6.6 3 6V6C3 5.4 3.4 5 4 5H20C20.6 5 21 5.4 21 6V6C21 6.6 20.6 7 20 7Z" fill="black"/>
							</svg>
                        </span>
                        <div class="text-white fw-bolder fs-2 mb-2 mt-5" id="total_stock_quantity">-</div>
                        <div class="fw-bold text-white">Total Stok Keseluruhan</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <a href="{{ route('admin.laporan.laporanstok.menipis') }}" class="card bg-danger hoverable card-xl-stretch mb-5 mb-xl-8 bgi-no-repeat" style="background-position: right top; background-size: 30% auto; background-image: url({{ asset('metronic/assets/media/svg/shapes/abstract-1.svg') }})">
                    <div class="card-body">
                        <span class="svg-icon svg-icon-3x svg-icon-white ms-n1">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
								<path opacity="0.3" d="M12 22C17.5 22 22 17.5 22 12C22 6.5 17.5 2 12 2C6.5 2 2 6.5 2 12C2 17.5 6.5 22 12 22Z" fill="black"/>
								<path d="M12 15C13.7 15 15 13.7 15 12C15 10.3 13.7 9 12 9C10.3 9 9 10.3 9 12C9 13.7 10.3 15 12 15Z" fill="black"/>
							</svg>
                        </span>
                        <div class="text-white fw-bolder fs-2 mb-2 mt-5" id="low_stock_items">-</div>
                        <div class="fw-bold text-white">Item Stok Menipis (<= 10)</div>
                    </div>
                </a>
            </div>
        </div>
        <!--end::Row-->

        <!--begin::Row-->
        <div class="row g-5 g-xl-8">
            <div class="col-xl-7">
                <div class="card card-xl-stretch mb-5 mb-xl-8">
                    <div class="card-header border-0 pt-5">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bolder text-dark">Tren Pergerakan Stok</span>
                            <span class="text-muted mt-1 fw-bold fs-7">Stok Masuk vs Stok Keluar</span>
                        </h3>
                    </div>
                    <div class="card-body pt-5">
                        <div id="stock_movement_chart" style="height: 350px;"></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-5">
                 <div class="card card-xl-stretch mb-5 mb-xl-8">
                    <div class="card-header border-0 pt-5">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bolder text-dark">Top 5 Item Stok Terbanyak</span>
                            <span class="text-muted mt-1 fw-bold fs-7">Berdasarkan kuantitas</span>
                        </h3>
                    </div>
                    <div class="card-body pt-5">
                        <div id="top_items_chart" style="height: 350px;"></div>
                    </div>
                </div>
            </div>
        </div>
        <!--end::Row-->

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
                            placeholder="Cari Stok by SKU/Nama">
                    </div>
                </div>
                <div class="card-toolbar">
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
                        Filter
                    </button>
                    <div class="menu menu-sub menu-sub-dropdown w-300px w-md-325px" data-kt-menu="true">
                        <div class="px-7 py-5">
                            <div class="fs-4 text-dark fw-bolder">Filter Options</div>
                        </div>
                        <div class="separator border-gray-200"></div>
                        <div class="px-7 py-5">
                            @if (!$hideWarehouseFilter)
                                <div class="mb-10">
                                    <label class="form-label fs-5 fw-bold mb-3">Gudang:</label>
                                    <select class="form-select form-select-solid fw-bolder" data-kt-select2="true" id="warehouse_filter">
                                        <option value="semua">Semua Gudang</option>
                                        @foreach ($warehouses as $warehouse)
                                            <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                            <div class="mb-10">
                                <label class="form-label fs-5 fw-bold mb-3">Kategori Item:</label>
                                <select class="form-select form-select-solid fw-bolder" data-kt-select2="true" id="category_filter">
                                    <option value="semua">Semua Kategori</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="d-flex justify-content-end">
                                <button type="button" class="btn btn-light btn-active-light-primary me-2" data-kt-menu-dismiss="true">Reset</button>
                                <button type="button" class="btn btn-primary" id="apply_filter">Apply</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body pt-4">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-5" id="inventory_table">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                @if(!$hideWarehouseFilter)
                                <th>Gudang</th>
                                @endif
                                <th>SKU</th>
                                <th>Nama Item</th>
                                <th>Kategori</th>
                                <th>Stok</th>
                                <th>UOM</th>
                            </tr>
                        </thead>
                        <tbody class="fw-bold text-gray-600">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('metronic/assets/plugins/custom/apexcharts/apexcharts.bundle.js') }}"></script>
    <script>
        $(document).ready(function() {
            let stockMovementChart, topItemsChart;
            let table;

            function initCharts(trendData, topItemsData) {
                if (stockMovementChart) stockMovementChart.destroy();
                var movementElement = document.getElementById('stock_movement_chart');
                var movementOptions = {
                    series: [{
                        name: 'Stok Masuk',
                        data: trendData.stock_in
                    }, {
                        name: 'Stok Keluar',
                        data: trendData.stock_out
                    }],
                    chart: { type: 'area', height: 350, toolbar: { show: false } },
                    xaxis: {
                        categories: trendData.dates,
                        axisBorder: { show: false },
                        axisTicks: { show: false },
                        labels: { style: { colors: '#A1A5B7', fontSize: '12px' } }
                    },
                    yaxis: { labels: { style: { colors: '#A1A5B7', fontSize: '12px' } } },
                    fill: { opacity: 1, type: 'solid' },
                    dataLabels: { enabled: false },
                    stroke: { curve: 'smooth', show: true, width: 3 },
                    colors: ['#50CD89', '#F1416C']
                };
                stockMovementChart = new ApexCharts(movementElement, movementOptions);
                stockMovementChart.render();

                if (topItemsChart) topItemsChart.destroy();
                var topItemsElement = document.getElementById('top_items_chart');
                var topItemsOptions = {
                    series: [{ name: 'Kuantitas', data: topItemsData.quantities }],
                    chart: { type: 'bar', height: 350, toolbar: { show: false } },
                    plotOptions: { bar: { borderRadius: 4, horizontal: false, } },
                    xaxis: {
                        categories: topItemsData.names,
                        labels: { style: { colors: '#A1A5B7', fontSize: '12px' } }
                    },
                    yaxis: { labels: { style: { colors: '#A1A5B7', fontSize: '12px' } } },
                    dataLabels: { enabled: false },
                    colors: ['#009EF7']
                };
                topItemsChart = new ApexCharts(topItemsElement, topItemsOptions);
                topItemsChart.render();
            }

            function loadSummaryData() {
                $.ajax({
                    url: "{{ route('admin.laporan.laporanstok.index') }}",
                    type: "GET",
                    data: {
                        warehouse_filter: $('#warehouse_filter').val(),
                        category_filter: $('#category_filter').val(),
                        // You might want to add date filters for summary data as well
                    },
                    success: function(response) {
                        $('#total_unique_items').text(response.totalUniqueItems);
                        $('#total_stock_quantity').text(response.totalStockQuantity);
                        $('#low_stock_items').text(response.lowStockItems);
                        initCharts(response.trendData, response.topItemsData);
                    },
                    error: function() {
                        toastr.error('Gagal memuat data ringkasan.');
                    }
                });
            }

            function initializeDataTable() {
                table = $('#inventory_table').DataTable({
                    processing: true,
                    serverSide: true,
                    ajax: {
                        url: "{{ route('admin.laporan.laporanstok.index') }}",
                        type: "GET",
                        data: function(d) {
                            d.warehouse_filter = $('#warehouse_filter').val();
                            d.category_filter = $('#category_filter').val();
                            d.search = { value: $('#search_input').val() };
                        }
                    },
                    columns: (function() {
                        let cols = [];
                        if (@json(!$hideWarehouseFilter)) {
                            cols.push({ data: 'warehouse.name', name: 'warehouse.name' });
                        }
                        cols.push({ data: 'item.sku', name: 'item.sku' });
                        cols.push({ data: 'item.nama_barang', name: 'item.nama_barang' });
                        cols.push({ data: 'item.item_category.name', name: 'item.item_category.name' });
                        cols.push({ data: 'quantity', name: 'quantity' });
                        cols.push({ data: 'item.uom.name', name: 'item.uom.name' });
                        return cols;
                    })(),
                    columnDefs: [
                        {
                            targets: -2, // Quantity column
                            render: function(data, type, row) {
                                return data ? parseFloat(data).toLocaleString('id-ID') : '0';
                            }
                        }
                    ]
                });
            }

            $('#apply_filter').on('click', function() {
                table.ajax.reload();
                loadSummaryData();
                $('[data-kt-menu-dismiss="true"]').click();
            });

            const debounce = (callback, wait) => {
                let timeoutId = null;
                return (...args) => {
                    window.clearTimeout(timeoutId);
                    timeoutId = window.setTimeout(() => {
                        callback.apply(null, args);
                    }, wait);
                };
            }

            $('#search_input').on('keyup', debounce(function() {
                table.draw();
            }, 500));

            initializeDataTable();
            loadSummaryData();
        });
    </script>
@endpush
