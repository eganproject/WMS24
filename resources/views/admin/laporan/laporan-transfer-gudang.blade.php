@extends('layouts.app')

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Laporan Transfer Gudang',
        'breadcrumbs' => ['Admin', 'Laporan', 'Transfer Gudang'],
    ])
@endpush

@section('content')
    {{-- Hidden JSON for charts to support AJAX updates --}}
    <div id="chart_data_json" style="display: none;">
        {{ json_encode(['trendData' => $trendData, 'pieChartData' => $pieChartData]) }}
    </div>

    <div class="content flex-row-fluid" id="kt_content">
        <div id="stats_wrapper" class="row g-5 g-xl-8">
            <div class="col-xl-4">
                <a href="#" class="card bg-body-white hoverable card-xl-stretch mb-xl-8 bgi-no-repeat" style="background-position: right top; background-size: 30% auto; background-image: url({{ asset('metronic/assets/media/svg/shapes/abstract-4.svg') }})">
                    <div class="card-body">
                        <span class="svg-icon svg-icon-3x svg-icon-primary ms-n1">
                            <i class="fas fa-exchange-alt fs-2x text-primary"></i>
                        </span>
                        <div class="text-gray-900 fw-bolder fs-2 mb-2 mt-5">{{ number_format($stats['total_transfers'], 0, ',', '.') }}</div>
                        <div class="fw-bold text-gray-400">Total Permintaan Transfer</div>
                    </div>
                </a>
            </div>
            <div class="col-xl-4">
                <a href="#" class="card bg-warning hoverable card-xl-stretch mb-xl-8 bgi-no-repeat" style="background-position: right top; background-size: 30% auto; background-image: url({{ asset('metronic/assets/media/svg/shapes/abstract-3.svg') }})">
                    <div class="card-body">
                        <span class="svg-icon svg-icon-3x svg-icon-white ms-n1">
                            <i class="fas fa-hourglass-half fs-2x text-white"></i>
                        </span>
                        <div class="text-white fw-bolder fs-2 mb-2 mt-5">{{ number_format($stats['total_in_process'], 0, ',', '.') }}</div>
                        <div class="fw-bold text-white">Dalam Proses</div>
                    </div>
                </a>
            </div>
            <div class="col-xl-4">
                <a href="#" class="card bg-info hoverable card-xl-stretch mb-xl-8 bgi-no-repeat" style="background-position: right top; background-size: 30% auto; background-image: url({{ asset('metronic/assets/media/svg/shapes/abstract-2.svg') }})">
                    <div class="card-body">
                        <span class="svg-icon svg-icon-3x svg-icon-white ms-n1">
                            <i class="fas fa-truck fs-2x text-white"></i>
                        </span>
                        <div class="text-white fw-bolder fs-2 mb-2 mt-5">{{ number_format($stats['total_shipped'], 0, ',', '.') }}</div>
                        <div class="fw-bold text-white">Dalam Pengiriman</div>
                    </div>
                </a>
            </div>
            <div class="col-xl-4">
                <a href="#" class="card bg-success hoverable card-xl-stretch mb-5 mb-xl-8 bgi-no-repeat" style="background-position: right top; background-size: 30% auto; background-image: url({{ asset('metronic/assets/media/svg/shapes/abstract-1.svg') }})">
                    <div class="card-body">
                        <span class="svg-icon svg-icon-3x svg-icon-white ms-n1">
                            <i class="fas fa-check fs-2x text-white"></i>
                        </span>
                        <div class="text-white fw-bolder fs-2 mb-2 mt-5">{{ number_format($stats['total_completed'], 0, ',', '.') }}</div>
                        <div class="fw-bold text-white">Selesai Diterima</div>
                    </div>
                </a>
            </div>
            <div class="col-xl-4">
                <a href="#" class="card bg-danger hoverable card-xl-stretch mb-5 mb-xl-8 bgi-no-repeat" style="background-position: right top; background-size: 30% auto; background-image: url({{ asset('metronic/assets/media/svg/shapes/abstract-6.svg') }})">
                    <div class="card-body">
                        <span class="svg-icon svg-icon-3x svg-icon-white ms-n1">
                            <i class="fas fa-ban fs-2x text-white"></i>
                        </span>
                        <div class="text-white fw-bolder fs-2 mb-2 mt-5">{{ number_format($stats['total_rejected'], 0, ',', '.') }}</div>
                        <div class="fw-bold text-white">Ditolak</div>
                    </div>
                </a>
            </div>
        </div>

        <div class="row g-5 g-xl-8">
            <div class="col-xl-7">
                <div class="card card-xl-stretch mb-5 mb-xl-8">
                    <div class="card-header border-0 pt-5">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bolder text-dark">Tren Permintaan Transfer</span>
                            <span class="text-muted mt-1 fw-bold fs-7">Jumlah permintaan per hari</span>
                        </h3>
                    </div>
                    <div class="card-body pt-5">
                        <div id="transfer_trend_chart" style="height: 350px;"></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-5">
                <div class="card card-xl-stretch mb-5 mb-xl-8">
                    <div class="card-header border-0 pt-5">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bolder text-dark">Distribusi Status</span>
                            <span class="text-muted mt-1 fw-bold fs-7">Berdasarkan jumlah permintaan</span>
                        </h3>
                    </div>
                    <div class="card-body pt-5 d-flex justify-content-center align-items-center">
                        <div id="status_pie_chart" style="height: 350px;"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header border-0 pt-6">
                <div class="card-title">
                    <form id="filter_form" class="d-flex align-items-center position-relative my-1">
                        <div class="d-flex align-items-center position-relative my-1">
                            <span class="svg-icon svg-icon-1 position-absolute ms-6">
                                <i class="fas fa-calendar-alt"></i>
                            </span>
                            <input class="form-control form-control-solid w-350px ps-15" placeholder="Pilih Rentang Tanggal" id="kt_daterangepicker" name="date_range" value="{{ $dateRange ?? '' }}"/>
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
                    <div class="menu menu-sub menu-sub-dropdown w-300px w-md-325px" data-kt-menu="true">
                        <div class="px-7 py-5">
                            <div class="fs-4 text-dark fw-bolder">Filter Options</div>
                        </div>
                        <div class="separator border-gray-200"></div>
                        <div class="px-7 py-5">
                            <div class="mb-10">
                                <label class="form-label fs-5 fw-bold mb-3">Status:</label>
                                <select class="form-select form-select-solid fw-bolder" data-kt-select2="true" data-hide-search="true" data-placeholder="Pilih Status" name="status_filter" form="filter_form">
                                    <option value="semua" {{ ($statusFilter ?? 'semua') === 'semua' ? 'selected' : '' }}>Semua Status</option>
                                    <option value="pending" {{ ($statusFilter ?? '') == 'pending' ? 'selected' : '' }}>Pending</option>
                                    <option value="approved" {{ ($statusFilter ?? '') == 'approved' ? 'selected' : '' }}>Approved</option>
                                    <option value="rejected" {{ ($statusFilter ?? '') == 'rejected' ? 'selected' : '' }}>Rejected</option>
                                    <option value="shipped" {{ ($statusFilter ?? '') == 'shipped' ? 'selected' : '' }}>Shipped</option>
                                    <option value="completed" {{ ($statusFilter ?? '') == 'completed' ? 'selected' : '' }}>Completed</option>
                                </select>
                            </div>

                            @if (!auth()->user()->warehouse_id)
                                <div class="mb-10">
                                    <label class="form-label fs-5 fw-bold mb-3">Dari Gudang:</label>
                                    <select class="form-select form-select-solid fw-bolder" data-kt-select2="true" name="from_warehouse_filter" form="filter_form">
                                        <option value="">Semua</option>
                                        @foreach ($warehouses as $warehouse)
                                            <option value="{{ $warehouse->id }}" {{ request('from_warehouse_filter') == $warehouse->id ? 'selected' : '' }}>{{ $warehouse->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="mb-10">
                                    <label class="form-label fs-5 fw-bold mb-3">Ke Gudang:</label>
                                    <select class="form-select form-select-solid fw-bolder" data-kt-select2="true" name="to_warehouse_filter" form="filter_form">
                                        <option value="">Semua</option>
                                        @foreach ($warehouses as $warehouse)
                                            <option value="{{ $warehouse->id }}" {{ request('to_warehouse_filter') == $warehouse->id ? 'selected' : '' }}>{{ $warehouse->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                            <div class="d-flex justify-content-end">
                                <a href="{{ route('admin.laporan.laporan-transfer-gudang') }}" class="btn btn-light btn-active-light-primary me-2">Reset</a>
                                <button type="submit" class="btn btn-primary" form="filter_form">Apply</button>
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
                                <th>Kode</th>
                                <th>Dari Gudang</th>
                                <th>Ke Gudang</th>
                                <th>Status</th>
                                <th>Jumlah Item</th>
                                <th>Diminta Oleh</th>
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
        var transferTrendChart;
        var statusPieChart;
        var table;

        $(document).ready(function() {
            // Initialize Select2 for dropdowns if available
            if ($.fn.select2) {
                $('[data-kt-select2="true"]').select2();
            }
            // Ensure default status is 'semua' when no query param present
            var hasStatusQuery = @json(request()->has('status_filter'));
            if (!hasStatusQuery) {
                var $sf = $('[name="status_filter"]');
                if ($sf.length) {
                    $sf.val('semua').trigger('change');
                }
            }

            // daterangepicker
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
                locale: { format: 'DD/MM/YYYY' }
            }, cb);
            cb(start, end);

            // Init charts
            var initialChartData = JSON.parse($('#chart_data_json').text());
            initTrendChart(initialChartData.trendData);
            initStatusPie(initialChartData.pieChartData);

            // Init DataTable
            table = $('#table-on-page').DataTable({
                processing: true,
                serverSide: true,
                searching: true,
                ajax: {
                    url: `{{ route('admin.laporan.laporan-transfer-gudang.data') }}`,
                    data: function(d) {
                        d.date_range = $('#kt_daterangepicker').val();
                        d.status_filter = $('[name="status_filter"]').val() || 'semua';
                        d.from_warehouse_filter = $('[name="from_warehouse_filter"]').val();
                        d.to_warehouse_filter = $('[name="to_warehouse_filter"]').val();
                    }
                },
                columns: [
                    { data: 'date', name: 'tr.date' },
                    { data: 'code', name: 'tr.code' },
                    { data: 'from_warehouse_name', name: 'fw.name', defaultContent: '-' },
                    { data: 'to_warehouse_name', name: 'tw.name', defaultContent: '-' },
                    { data: 'status', name: 'tr.status', render: function(data){
                        if(data === 'pending') return '<span class="badge badge-light-warning">Pending</span>';
                        if(data === 'approved') return '<span class="badge badge-light-info">Approved</span>';
                        if(data === 'rejected') return '<span class="badge badge-light-danger">Rejected</span>';
                        if(data === 'shipped') return '<span class="badge badge-light-primary">Shipped</span>';
                        return '<span class="badge badge-light-success">Completed</span>';
                    }},
                    { data: 'items_count', name: 'items_count', className: 'text-end', render: function(data){ return (parseInt(data || 0)).toLocaleString('id-ID'); } },
                    { data: 'requester_name', name: 'u.name', defaultContent: '-' },
                ],
                order: [[0, 'desc']],
            });

            // Intercept filter form submit to apply via AJAX
            $('#filter_form').on('submit', function(e){
                e.preventDefault();
                applyFiltersAjax();
            });
        });

        function initTrendChart(trendData) {
            var element = document.getElementById('transfer_trend_chart');
            if (!element) return;
            var options = {
                series: [{ name: 'Transfer', data: trendData.totals }],
                chart: { type: 'area', height: 350, toolbar: { show: false } },
                xaxis: { categories: trendData.dates, labels: { style: { colors: '#A1A5B7', fontSize: '12px' } }, axisBorder: { show:false }, axisTicks:{show:false} },
                yaxis: { labels: { style: { colors: '#A1A5B7', fontSize: '12px' } } },
                dataLabels: { enabled: false },
                stroke: { curve: 'smooth', width: 3 },
                colors: ['#009EF7'],
                fill: { opacity: 0.85, type: 'solid' },
            };
            transferTrendChart = new ApexCharts(element, options);
            transferTrendChart.render();
        }

        function initStatusPie(pieChartData) {
            var element = document.getElementById('status_pie_chart');
            if (!element) return;
            var options = {
                series: pieChartData.series,
                labels: pieChartData.labels,
                chart: { type: 'donut', height: 350 },
                responsive: [{ breakpoint: 480, options: { chart: { width: 200 }, legend: { position: 'bottom' } } }]
            };
            statusPieChart = new ApexCharts(element, options);
            statusPieChart.render();
        }

        function applyFiltersAjax() {
            // show loading overlay on content
            const contentWrapper = document.getElementById('kt_content');
            const loadingEl = document.createElement('div');
            loadingEl.classList.add('loading-overlay');
            contentWrapper.appendChild(loadingEl);

            $.ajax({
                url: `{{ route('admin.laporan.laporan-transfer-gudang') }}`,
                type: 'GET',
                dataType: 'html',
                data: {
                    date_range: $('#kt_daterangepicker').val(),
                    status_filter: $('[name="status_filter"]').val(),
                    from_warehouse_filter: $('[name="from_warehouse_filter"]').val(),
                    to_warehouse_filter: $('[name="to_warehouse_filter"]').val(),
                },
                success: function(response){
                    var newContent = $($.parseHTML(response));
                    // Update stats
                    $('#stats_wrapper').html(newContent.find('#stats_wrapper').html());
                    // Update charts via new JSON
                    var newChartData = JSON.parse(newContent.find('#chart_data_json').text());
                    if (transferTrendChart) {
                        transferTrendChart.updateSeries([{ name: 'Transfer', data: newChartData.trendData.totals }]);
                        transferTrendChart.updateOptions({ xaxis: { categories: newChartData.trendData.dates } });
                    }
                    if (statusPieChart) {
                        statusPieChart.updateOptions({ labels: newChartData.pieChartData.labels });
                        statusPieChart.updateSeries(newChartData.pieChartData.series);
                    }
                    // Reload table data
                    if (table) { table.ajax.reload(); }
                },
                complete: function(){
                    loadingEl.remove();
                }
            });
        }
    </script>
    <style>
        .loading-overlay { position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255,255,255,0.7); z-index: 10; display: flex; justify-content: center; align-items: center; }
        .loading-overlay::after { content: 'Loading...'; font-size: 1.2rem; color: #333; }
    </style>
@endpush
