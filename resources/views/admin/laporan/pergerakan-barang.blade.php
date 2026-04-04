@extends('layouts.app')

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Laporan Pergerakan Barang',
        'breadcrumbs' => ['Admin', 'Laporan', 'Pergerakan Barang'],
    ])
@endpush

@section('content')
    {{-- Hidden div to hold the JSON data for charts for AJAX updates --}}
    <div id="chart_data_json" style="display: none;">
        {{ json_encode(['trendData' => $trendData, 'pieChartData' => $pieChartData]) }}
    </div>

    <div class="content flex-row-fluid" id="kt_content">

        <!-- Keterangan Filter Bar -->
        <div id="filter_info_bar" class="mb-5">
            <div class="alert alert-secondary d-flex align-items-center p-3">
                <span class="me-3"><i class="fas fa-filter"></i></span>
                <div>
                    <div class="fw-bold mb-1">Keterangan Filter</div>
                    <div id="filter_info" class="text-gray-700">Menampilkan semua data</div>
                </div>
            </div>
        </div>

        <div id="stats_wrapper">
            <!--begin::Row-->
            <div class="row g-5 g-xl-8">
                <div class="col-xl-4">
                    <!--begin::Statistics Widget 5-->
                    <a href="#" class="card bg-body-white hoverable card-xl-stretch mb-xl-8">
                        <div class="card-body">
                            <span class="svg-icon svg-icon-3x svg-icon-primary ms-n1">
                                <i class="fas fa-exchange-alt fs-2x text-primary"></i>
                            </span>
                            <div class="text-gray-900 fw-bolder fs-2 mb-2 mt-5">{{ number_format($stats['total_movements'], 0, ',', '.') }}</div>
                            <div class="fw-bold text-gray-400">Total Pergerakan</div>
                        </div>
                    </a>
                    <!--end::Statistics Widget 5-->
                </div>
                <div class="col-xl-4">
                    <!--begin::Statistics Widget 5-->
                    <a href="#" class="card bg-success hoverable card-xl-stretch mb-xl-8">
                        <div class="card-body">
                             <span class="svg-icon svg-icon-3x svg-icon-white ms-n1">
                                <i class="fas fa-arrow-down fs-2x text-white"></i>
                            </span>
                            <div class="text-white fw-bolder fs-2 mb-2 mt-5">{{ number_format($stats['total_in'], 2, ',', '.') }}</div>
                            <div class="fw-bold text-white">Total Kuantitas Masuk</div>
                        </div>
                    </a>
                    <!--end::Statistics Widget 5-->
                </div>
                <div class="col-xl-4">
                    <!--begin::Statistics Widget 5-->
                    <a href="#" class="card bg-danger hoverable card-xl-stretch mb-5 mb-xl-8">
                        <div class="card-body">
                            <span class="svg-icon svg-icon-3x svg-icon-white ms-n1">
                               <i class="fas fa-arrow-up fs-2x text-white"></i>
                            </span>
                            <div class="text-white fw-bolder fs-2 mb-2 mt-5">{{ number_format($stats['total_out'], 2, ',', '.') }}</div>
                            <div class="fw-bold text-white">Total Kuantitas Keluar</div>
                        </div>
                    </a>
                    <!--end::Statistics Widget 5-->
                </div>
            </div>
            <!--end::Row-->
        </div>

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
                            <span class="card-label fw-bolder text-dark">Tipe Pergerakan</span>
                            <span class="text-muted mt-1 fw-bold fs-7">Berdasarkan jumlah transaksi</span>
                        </h3>
                    </div>
                    <div class="card-body pt-5 d-flex justify-content-center align-items-center">
                        <div id="movement_type_chart" style="height: 350px;"></div>
                    </div>
                </div>
            </div>
        </div>
        <!--end::Row-->

        <div class="card">
            <div class="card-header border-0 pt-6">
                <div class="card-title">
                    <form id="filter_form" class="d-flex align-items-center position-relative my-1">
                        <div class="d-flex align-items-center position-relative my-1">
                            <span class="svg-icon svg-icon-1 position-absolute ms-6">
                                <i class="fas fa-calendar-alt"></i>
                            </span>
                            <input class="form-control form-control-solid w-350px ps-15" placeholder="Pilih Rentang Tanggal" id="kt_daterangepicker" name="date_range"/>
                        </div>
                    </form>
                </div>
                <div class="card-toolbar">
                    <button type="button" class="btn btn-light-primary me-3" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                        <span class="svg-icon svg-icon-2">
                            <i class="fas fa-filter"></i>
                        </span>
                        Filter
                    </button>
                    <div class="menu menu-sub menu-sub-dropdown w-300px w-md-325px" data-kt-menu="true" id="kt_menu_624854996627a">
                        <div class="px-7 py-5">
                            <div class="fs-4 text-dark fw-bolder">Filter Options</div>
                        </div>
                        <div class="separator border-gray-200"></div>
                        <div class="px-7 py-5">
                            <div class="mb-10">
                                <label class="form-label fs-5 fw-bold mb-3">Tipe Pergerakan:</label>
                                <select id="type_filter" class="form-select form-select-solid fw-bolder" data-kt-select2="true" data-hide-search="true" data-placeholder="Pilih Tipe" name="type_filter" form="filter_form">
                                    <option value="semua">Semua Tipe</option>
                                    <option value="stock_in" {{ request('type_filter') == 'stock_in' ? 'selected' : '' }}>Stock In</option>
                                    <option value="stock_out" {{ request('type_filter') == 'stock_out' ? 'selected' : '' }}>Stock Out</option>
                                    <option value="transfer_in" {{ request('type_filter') == 'transfer_in' ? 'selected' : '' }}>Transfer In</option>
                                    <option value="transfer_out" {{ request('type_filter') == 'transfer_out' ? 'selected' : '' }}>Transfer Out</option>
                                    <option value="adjustment" {{ request('type_filter') == 'adjustment' ? 'selected' : '' }}>Adjustment</option>
                                </select>
                            </div>

                            @if (!auth()->user()->warehouse_id)
                                <div class="mb-10">
                                    <label class="form-label fs-5 fw-bold mb-3">Gudang:</label>
                                    <select id="warehouse_filter" class="form-select form-select-solid fw-bolder" data-kt-select2="true" name="warehouse_filter" form="filter_form">
                                        <option value="">Semua Gudang</option>
                                        @foreach ($warehouses as $warehouse)
                                            <option value="{{ $warehouse->id }}" {{ request('warehouse_filter') == $warehouse->id ? 'selected' : '' }}>{{ $warehouse->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                            <div class="mb-10">
                                <label class="form-label fs-5 fw-bold mb-3">Barang:</label>
                                <select id="item_filter" class="form-select form-select-solid fw-bolder" data-kt-select2="true" name="item_filter" form="filter_form">
                                    <option value="">Semua Barang</option>
                                    @foreach ($items as $item)
                                        <option value="{{ $item->id }}">{{ $item->sku ? $item->sku . ' - ' : '' }}{{ $item->nama_barang }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="d-flex justify-content-end">
                                <button type="button" class="btn btn-light btn-active-light-primary me-2" id="reset_filter">Reset</button>
                                <button type="button" class="btn btn-primary" id="apply_filter">Apply</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body pt-4" id="table_wrapper">
                <div class="table-responsive">
                    <table id="table-on-page" class="table align-middle table-row-dashed fs-6 gy-5">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                <th>Tanggal</th>
                                <th>Barang</th>
                                <th>Gudang</th>
                                <th>Tipe</th>
                                <th>Deskripsi</th>
                                <th class="text-end">Stok Sebelum</th>
                                <th class="text-end">Jumlah</th>
                                <th class="text-end">Stok Sesudah</th>
                                <th>User</th>
                            </tr>
                        </thead>
                        <tbody class="fw-bold text-gray-600"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('metronic/assets/plugins/custom/apexcharts/apexcharts.bundle.js') }}"></script>
    <script>
        // Define chart instances in a broader scope
        var stockMovementChart;
        var movementTypeChart;
        var table;

        $(document).ready(function() {
            // Initialize Select2 for filter dropdowns (default)
            if ($.fn.select2) {
                $('[data-kt-select2="true"]').select2();
            }
            // Init Daterangepicker
            var start = moment().subtract(29, 'days');
            var end = moment();

            @if($dateRange)
                var dates = "{{$dateRange}}".split(' - ');
                start = moment(dates[0], 'DD/MM/YYYY');
                end = moment(dates[1], 'DD/MM/YYYY');
            @endif

            function cb(start, end) {
                $('#kt_daterangepicker').val(start.format('DD/MM/YYYY') + ' - ' + end.format('DD/MM/YYYY'));
            }

            $("#kt_daterangepicker").daterangepicker({
                startDate: start,
                endDate: end,
                ranges: {
                   'Hari Ini': [moment(), moment()],
                   'Kemarin': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                   '7 Hari Terakhir': [moment().subtract(6, 'days'), moment()],
                   '30 Hari Terakhir': [moment().subtract(29, 'days'), moment()],
                   'Bulan Ini': [moment().startOf('month'), moment().endOf('month')],
                   'Bulan Lalu': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
                },
                locale: {
                    format: 'DD/MM/YYYY'
                }
            }, cb);

            cb(start, end);

            // Init Charts
            var initialChartData = JSON.parse($('#chart_data_json').text());
            initStockMovementChart(initialChartData.trendData);
            initMovementTypeChart(initialChartData.pieChartData);

            // Update filter info text helper
            function updateFilterInfo() {
                var dateText = $('#kt_daterangepicker').val() ? `Periode: ${$('#kt_daterangepicker').val()}` : 'Semua Periode';
                var typeVal = $('#type_filter').val();
                var typeMap = {
                    'semua': 'Semua Tipe',
                    'stock_in': 'Stok Masuk',
                    'stock_out': 'Stok Keluar',
                    'transfer_in': 'Transfer Masuk',
                    'transfer_out': 'Transfer Keluar',
                    'adjustment': 'Adjustment'
                };
                var typeText = `Tipe: ${typeMap[typeVal] || 'Semua Tipe'}`;

                var warehouseText = 'Gudang: ';
                @if (auth()->user()->warehouse_id)
                    warehouseText += `{{ auth()->user()->warehouse->name ?? 'N/A' }}`;
                @else
                    var wVal = $('#warehouse_filter').val();
                    if (wVal) {
                        warehouseText += $('#warehouse_filter option:selected').text();
                    } else {
                        warehouseText += 'Semua Gudang';
                    }
                @endif

                var itemText = 'Barang: ';
                var iVal = $('#item_filter').val();
                if (iVal) {
                    itemText += $('#item_filter option:selected').text();
                } else {
                    itemText += 'Semua Barang';
                }

                $('#filter_info').text(`${dateText} | ${typeText} | ${warehouseText} | ${itemText}`);
            }

            // Initial render of filter info
            updateFilterInfo();

            // AJAX Filter Logic for stats & charts only
            function fetchData(url = `{{ route('admin.laporan.pergerakanBarang') }}`) {
                // Show loading indication
                const contentWrapper = document.getElementById('kt_content');
                const loadingEl = document.createElement("div");
                loadingEl.classList.add("loading-overlay");
                contentWrapper.appendChild(loadingEl);

                $.ajax({
                    url: url,
                    type: 'GET',
                    dataType: 'html',
                    data: {
                        date_range: $('#kt_daterangepicker').val(),
                        type_filter: $('#type_filter').val(),
                        warehouse_filter: $('#warehouse_filter').val(),
                        item_filter: $('#item_filter').val(),
                    },
                    success: function(response) {
                        var newContent = $($.parseHTML(response));
                        // Update stats only (table handled by DataTables)
                        $('#stats_wrapper').html(newContent.find('#stats_wrapper').html());

                        // Parse new chart data and update charts
                        var newChartData = JSON.parse(newContent.find('#chart_data_json').text());
                        
                        stockMovementChart.updateSeries([{
                            name: 'Stok Masuk',
                            data: newChartData.trendData.stock_in
                        }, {
                            name: 'Stok Keluar',
                            data: newChartData.trendData.stock_out
                        }]);
                        stockMovementChart.updateOptions({
                             xaxis: {
                                categories: newChartData.trendData.dates
                            }
                        });

                        movementTypeChart.updateSeries(newChartData.pieChartData.series);
                         movementTypeChart.updateOptions({
                             labels: newChartData.pieChartData.labels
                        });

                    },
                    error: function(xhr, status, error) {
                        console.error("AJAX Error: ", status, error);
                    },
                    complete: function() {
                        // Hide loading indication
                        loadingEl.remove();
                    }
                });
            }

            $('#apply_filter').on('click', function() {
                if ($.fn.DataTable.isDataTable('#table-on-page')) {
                    $('#table-on-page').DataTable().draw();
                }
                fetchData();
                updateFilterInfo();
            });

            $('#kt_daterangepicker').on('apply.daterangepicker', function(ev, picker) {
                if ($.fn.DataTable.isDataTable('#table-on-page')) {
                    $('#table-on-page').DataTable().draw();
                }
                fetchData();
                updateFilterInfo();
            });

            $('#reset_filter').on('click', function() {
                $('#kt_daterangepicker').val(moment().subtract(29, 'days').format('DD/MM/YYYY') + ' - ' + moment().format('DD/MM/YYYY'));
                $('#type_filter').val('semua').trigger('change');
                $('#warehouse_filter').val('').trigger('change');
                $('#item_filter').val('').trigger('change');
                
                if ($.fn.DataTable.isDataTable('#table-on-page')) {
                    $('#table-on-page').DataTable().draw();
                }
                fetchData();
                updateFilterInfo();
            });

            // Initialize DataTable
            function initDataTable() {
                if ($.fn.DataTable.isDataTable('#table-on-page')) {
                    $('#table-on-page').DataTable().destroy();
                }
                table = $('#table-on-page').DataTable({
                    processing: true,
                    serverSide: true,
                    ajax: {
                        url: "{{ route('admin.laporan.pergerakanBarang.data') }}",
                        type: "GET",
                        data: function(d) {
                            d.search.value = d.search.value || '';
                            d.date_range = $('#kt_daterangepicker').val();
                            d.type_filter = $('#type_filter').val();
                            d.warehouse_filter = $('#warehouse_filter').val();
                            d.item_filter = $('#item_filter').val();
                        }
                    },
                    columns: [
                        { data: 'date', name: 'sm.date' },
                        { data: null, name: 'i.nama_barang', render: function(data, type, row){
                            var name = row.item_name || 'N/A';
                            var sku = row.sku || 'N/A';
                            return '<div class="d-flex align-items-center"><div class="d-flex justify-content-start flex-column"><a href="#" class="text-dark fw-bolder text-hover-primary fs-6">' + name + '</a><span class="text-muted fw-bold text-muted d-block fs-7">' + sku + '</span></div></div>';
                        }},
                        { data: 'warehouse_name', name: 'w.name', defaultContent: '-' },
                        { data: 'type', name: 'sm.type', render: function(data){
                            if(data === 'stock_in') return '<span class="badge badge-light-success fs-7 fw-bolder">Stok Masuk</span>';
                            if(data === 'stock_out') return '<span class="badge badge-light-danger fs-7 fw-bolder">Stok Keluar</span>';
                            if(data === 'transfer_in') return '<span class="badge badge-light-info fs-7 fw-bolder">Transfer Masuk</span>';
                            if(data === 'transfer_out') return '<span class="badge badge-light-primary fs-7 fw-bolder">Transfer Keluar</span>';
                            return '<span class="badge badge-light-warning fs-7 fw-bolder">Adjustment</span>';
                        }},
                        { data: 'description', name: 'sm.description', defaultContent: '-' },
                        { data: 'stock_before', name: 'sm.stock_before', className: 'text-end', render: function(data){ return data ? parseFloat(data).toLocaleString('id-ID') : '0'; } },
                        { data: 'quantity', name: 'sm.quantity', className: 'text-end', render: function(data){
                            var val = parseFloat(data || 0);
                            if (val > 0) return '<span class="text-success fw-bolder">+' + val.toLocaleString('id-ID') + '</span>';
                            return '<span class="text-danger fw-bolder">' + val.toLocaleString('id-ID') + '</span>';
                        } },
                        { data: 'stock_after', name: 'sm.stock_after', className: 'text-end', render: function(data){ return data ? parseFloat(data).toLocaleString('id-ID') : '0'; } },
                        { data: 'user_name', name: 'u.name', defaultContent: 'System' },
                    ],
                    order: [[0, 'desc']],
                });
            }

            initDataTable();

        });

        // Chart Initialization Functions
        function initStockMovementChart(trendData) {
            var element = document.getElementById('stock_movement_chart');
            if (!element) { return; }

            var options = {
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

            stockMovementChart = new ApexCharts(element, options);
            stockMovementChart.render();
        }

        function initMovementTypeChart(pieChartData) {
            var element = document.getElementById('movement_type_chart');
            if (!element) { return; }

            var options = {
                series: pieChartData.series,
                labels: pieChartData.labels,
                chart: { type: 'donut', height: 350 },
                responsive: [{
                    breakpoint: 480,
                    options: {
                        chart: { width: 200 },
                        legend: { position: 'bottom' }
                    }
                }]
            };

            movementTypeChart = new ApexCharts(element, options);
            movementTypeChart.render();
        }
    </script>
    <style>
        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.7);
            z-index: 10;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .loading-overlay::after {
            content: 'Loading...';
            font-size: 1.2rem;
            color: #333;
        }
    </style>
@endpush
