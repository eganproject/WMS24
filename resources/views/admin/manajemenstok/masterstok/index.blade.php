@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        #kt-toolbar-filter .select2-container .select2-selection--single {
            height: 2.65rem !important;
            display: flex;
            align-items: center;
            padding: 0 0.75rem;
            background-color: #eff2f5;
            color: #5e6278;
            border: 1px solid transparent;
            border-radius: 0.475rem;
        }

        #kt-toolbar-filter .select2-container .select2-selection--single .select2-selection__rendered {
            padding-left: 0;
            line-height: 1.2;
        }

        #kt-toolbar-filter .select2-container .select2-selection--single .select2-selection__arrow {
            height: 100%;
        }

        .item-cell .sku {
            font-size: 0.75rem;
            color: #a1a5b7;
        }
    </style>
@endpush

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Master Stok',
        'breadcrumbs' => ['Admin', 'Manajemen Stok', 'Master Stok'],
    ])
@endpush

@section('content')
    <div class="content flex-row-fluid" id="kt_content">
        <div class="card">
            <div class="card-header border-0 pt-6">
                <div class="card-title">
                    <div class="d-flex align-items-center position-relative my-1">
                        <span class="svg-icon svg-icon-1 position-absolute ms-6">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                <rect opacity="0.5" x="17.0365" y="15.1223" width="8.15546" height="2" rx="1"
                                    transform="rotate(45 17.0365 15.1223)" fill="black"></rect>
                                <path d="M11 19C6.55556 19 3 15.4444 3 11C3 6.55556 6.55556 3 11 3C15.4444 3 19 6.55556 19 11C19 15.4444 15.4444 19 11 19ZM11 5C7.53333 5 5 7.53333 5 11C5 14.4667 7.53333 17 11 17C14.4667 17 17 14.4667 17 11C17 7.53333 14.4667 5 11 5Z"
                                    fill="black"></path>
                            </svg>
                        </span>
                        <input type="text" id="search_input" class="form-control form-control-solid w-250px ps-15"
                            placeholder="Cari Stok">
                    </div>
                </div>
                <div class="card-toolbar">
                    <button type="button" class="btn btn-light-primary me-3" data-kt-menu-trigger="click"
                        data-kt-menu-placement="bottom-end">
                        <span class="svg-icon svg-icon-2">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none">
                                <path d="M19.0759 3H4.72777C3.95892 3 3.47768 3.83148 3.86067 4.49814L8.56967 12.6949C9.17923 13.7559 9.5 14.9582 9.5 16.1819V19.5072C9.5 20.2189 10.2223 20.7028 10.8805 20.432L13.8805 19.1977C14.2553 19.0435 14.5 18.6783 14.5 18.273V13.8372C14.5 12.8089 14.8171 11.8056 15.408 10.964L19.8943 4.57465C20.3596 3.912 19.8856 3 19.0759 3Z"
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
                            <div class="mb-10">
                                <label class="form-label fs-5 fw-bold mb-3">Kategori Item:</label>
                                <select class="form-select form-select-solid fw-bolder" data-kt-select2="true"
                                    id="item_category_filter" data-dropdown-parent="#kt-toolbar-filter">
                                    <option value="">Semua Kategori</option>
                                    @foreach ($itemCategories as $category)
                                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-10">
                                <label class="form-label fs-5 fw-bold mb-3">Rentang Tanggal:</label>
                                <div class="row g-3">
                                    <div class="col">
                                        <input type="text" class="form-control form-control-solid" id="date_from"
                                            placeholder="Mulai" value="{{ $defaultDateFrom }}" autocomplete="off">
                                    </div>
                                    <div class="col">
                                        <input type="text" class="form-control form-control-solid" id="date_to"
                                            placeholder="Selesai" value="{{ $defaultDateTo }}" autocomplete="off">
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end">
                                <button type="reset" class="btn btn-light btn-active-light-primary me-2"
                                    data-kt-menu-dismiss="true">Reset</button>
                                <button type="button" class="btn btn-primary" id="apply_filter"
                                    data-kt-menu-dismiss="true">Terapkan</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body pt-0">
                <div class="text-center mb-5">
                    <h3 class="mb-0">Master Stok</h3>
                    <small id="filter-info" class="text-muted"></small>
                </div>
                <div class="dataTables_wrapper dt-bootstrap4 no-footer">
                    <div class="table-responsive min-h-500px">
                        <table class="table align-middle table-row-dashed fs-6 gy-5 dataTable no-footer" id="table-on-page">
                            <thead>
                                 <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                     <th class="min-w-220px" rowspan="2">Item</th>
                                     <th class="min-w-140px text-center" colspan="2">Saldo Awal</th>
                                     <th class="min-w-140px text-center" colspan="2">Stok Masuk</th>
                                     <th class="min-w-140px text-center" colspan="2">Stok Keluar</th>
                                     <th class="min-w-140px text-center" colspan="2">Stok Akhir</th>
                                    
                                </tr>
                                <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                    <th class="min-w-140px text-center">Qty</th>
                                    <th class="min-w-140px text-center">Koli</th>
                                    <th class="min-w-140px text-center">Qty</th>
                                    <th class="min-w-140px text-center">Koli</th>
                                    <th class="min-w-140px text-center">Qty</th>
                                    <th class="min-w-140px text-center">Koli</th>
                                    <th class="min-w-140px text-center">Qty</th>
                                    <th class="min-w-140px text-center">Koli</th>
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
        document.addEventListener('DOMContentLoaded', function () {
            const defaultDateFrom = @json($defaultDateFrom);
            const defaultDateTo = @json($defaultDateTo);

            const $searchInput = $('#search_input');
            const $categoryFilter = $('#item_category_filter');
            const $dateFrom = $('#date_from');
            const $dateTo = $('#date_to');
            const $filterInfo = $('#filter-info');

            flatpickr('#date_from', {
                dateFormat: 'Y-m-d',
                allowInput: true,
                defaultDate: $dateFrom.val() || defaultDateFrom,
            });

            flatpickr('#date_to', {
                dateFormat: 'Y-m-d',
                allowInput: true,
                defaultDate: $dateTo.val() || defaultDateTo,
            });

            let table = null;

            const formatNumber = (value) => {
                const number = parseFloat(value ?? 0);
                if (isNaN(number)) {
                    return '0';
                }
                return number.toLocaleString('id-ID', {
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 2,
                });
            };

            const formatItemCell = (name, sku) => {
                const displayName = name || '-';
                const displaySku = sku ? sku : 'SKU: -';
                return `
                    <div class="d-flex flex-column item-cell">
                        <span class="text-primary fw-bold mb-1">${displaySku}</span>
                        <span class="sku">${displayName}</span>
                    </div>
                `;
            };

            const resolveCategoryText = (value) => {
                if (!value) {
                    return 'Semua Kategori';
                }
                const option = $categoryFilter.find('option:selected');
                return option.length ? option.text() : 'Semua Kategori';
            };

            const resolveDateText = (from, to) => {
                if (from && to) {
                    if (from === to) {
                        return from;
                    }
                    return `${from} s/d ${to}`;
                }
                if (from || to) {
                    return from || to;
                }
                return 'Semua Tanggal';
            };

            const metricKeys = [
                'opening_qty',
                'opening_koli',
                'incoming_qty',
                'incoming_koli',
                'outgoing_qty',
                'outgoing_koli',
                'closing_qty',
                'closing_koli',
            ];

            const loadDataTable = () => {
                const categoryValue = $categoryFilter.val();
                const dateFromValue = $dateFrom.val();
                const dateToValue = $dateTo.val();

                const categoryText = resolveCategoryText(categoryValue);
                const dateText = resolveDateText(dateFromValue, dateToValue);

                $filterInfo.text(`Tanggal: ${dateText} | Kategori: ${categoryText}`);

                if ($.fn.DataTable.isDataTable('#table-on-page')) {
                    $('#table-on-page').DataTable().destroy();
                }

                const columns = [
                    { data: 'item_name', name: 'items.nama_barang' },
                ];
                metricKeys.forEach((key) => {
                    columns.push({ data: key, name: key, orderable: false, searchable: false, className: 'text-center' });
                });

                table = $('#table-on-page').DataTable({
                    processing: true,
                    serverSide: true,
                    searching: false,
                    ajax: {
                        url: "{{ route('admin.manajemenstok.masterstok.index') }}",
                        type: 'GET',
                        data: function (d) {
                            d.search = d.search || {};
                            d.search.value = $searchInput.val();
                            d.item_category_filter = categoryValue;
                            d.date_from = dateFromValue;
                            d.date_to = dateToValue;
                        },
                    },
                    order: [[0, 'asc']],
                    columns,
                    columnDefs: [
                        {
                            targets: 0,
                            render: function (data, type, row) {
                                return formatItemCell(data, row.sku);
                            },
                        },
                        {
                            targets: metricKeys.map((_, idx) => idx + 1),
                            render: function (data) {
                                return formatNumber(data);
                            },
                        },
                    ],
                });
            };

            const debounce = (callback, wait = 400) => {
                let timeoutId = null;
                return (...args) => {
                    window.clearTimeout(timeoutId);
                    timeoutId = window.setTimeout(() => callback.apply(null, args), wait);
                };
            };

            loadDataTable();

            $('#apply_filter').on('click', function () {
                loadDataTable();
            });

            $('#kt-toolbar-filter [type="reset"]').on('click', function () {
                $categoryFilter.val('').trigger('change');
                $dateFrom.val(defaultDateFrom);
                $dateTo.val(defaultDateTo);
                loadDataTable();
            });

            $searchInput.on('keyup', debounce(function () {
                if (table) {
                    table.ajax.reload();
                }
            }));
        });
    </script>
@endpush
