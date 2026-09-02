@extends('layouts.admin')

@section('title', 'Laporan Saldo Stok')
@section('page_title', 'Laporan Saldo Stok')

@push('styles')
<style>
    .stock-balance-filter-grid {
        display: grid;
        grid-template-columns: minmax(240px, 1.4fr) repeat(3, minmax(170px, 0.7fr)) auto;
        gap: 0.85rem;
        align-items: end;
    }

    .stock-balance-summary {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 1rem;
    }

    .stock-balance-summary-card {
        background: #f8f9fc;
        border: 1px solid #eef2f7;
        border-radius: 0.9rem;
        min-height: 118px;
        padding: 1.1rem 1.2rem;
        position: relative;
        overflow: hidden;
    }

    .stock-balance-summary-card::after {
        border-radius: 999px;
        content: '';
        height: 72px;
        opacity: 0.08;
        position: absolute;
        right: -18px;
        top: -18px;
        width: 72px;
    }

    .stock-balance-summary-card.opening::after { background: #7239ea; }
    .stock-balance-summary-card.incoming::after { background: #50cd89; }
    .stock-balance-summary-card.outgoing::after { background: #f1416c; }
    .stock-balance-summary-card.ending::after { background: #009ef7; }

    .stock-balance-summary-label {
        color: #7e8299;
        font-size: 0.76rem;
        font-weight: 700;
        letter-spacing: 0.03em;
        text-transform: uppercase;
    }

    .stock-balance-summary-value {
        color: #181c32;
        font-size: 1.75rem;
        font-weight: 800;
        line-height: 1.15;
        margin-top: 0.4rem;
    }

    .stock-balance-qty {
        font-variant-numeric: tabular-nums;
        font-weight: 700;
        white-space: nowrap;
    }

    @media (max-width: 1199.98px) {
        .stock-balance-filter-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }

    @media (max-width: 767.98px) {
        .stock-balance-filter-grid,
        .stock-balance-summary { grid-template-columns: 1fr; }
        .stock-balance-filter-grid .btn { width: 100%; }
    }
</style>
@endpush

@section('content')
<div class="card mb-6">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div>
                <h3 class="fw-bolder mb-1">Posisi Stok Per Periode</h3>
                <div class="text-muted fs-7">Pantau stok awal, pergerakan barang, dan saldo akhir setiap SKU.</div>
            </div>
        </div>
        <div class="card-toolbar">
            <button type="button" class="btn btn-light-success" id="btn_export_stock_balance">
                <i class="fas fa-file-excel me-1"></i> Export Excel
            </button>
        </div>
    </div>
    <div class="card-body pt-3">
        <div class="stock-balance-filter-grid mb-6">
            <div>
                <label class="text-muted fs-7 mb-1">Cari Barang</label>
                <input type="text" class="form-control form-control-solid" id="report_search" placeholder="SKU atau nama barang" autocomplete="off" />
            </div>
            <div>
                <label class="text-muted fs-7 mb-1">Gudang (bisa pilih beberapa)</label>
                <select class="form-select form-select-solid" id="filter_warehouse" multiple>
                    <option value="all" selected>Seluruh Gudang</option>
                    @foreach($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}">
                            {{ $warehouse->name }}{{ $warehouse->code ? ' ('.$warehouse->code.')' : '' }}
                        </option>
                    @endforeach
                </select>
                <div class="text-muted fs-8 mt-1">Pilih satu atau beberapa gudang.</div>
            </div>
            <div>
                <label class="text-muted fs-7 mb-1">Tanggal Awal</label>
                <input type="text" class="form-control form-control-solid" id="filter_date_from" value="{{ $defaultDateFrom }}" placeholder="YYYY-MM-DD" />
            </div>
            <div>
                <label class="text-muted fs-7 mb-1">Tanggal Akhir</label>
                <input type="text" class="form-control form-control-solid" id="filter_date_to" value="{{ $defaultDateTo }}" placeholder="YYYY-MM-DD" />
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-primary" id="filter_apply">Terapkan</button>
                <button type="button" class="btn btn-light" id="filter_reset">Reset</button>
            </div>
        </div>

        <div class="alert alert-light-primary d-flex align-items-start mb-6">
            <i class="fas fa-info-circle text-primary mt-1 me-3"></i>
            <div>
                <div class="fw-semibold">Saldo akhir = stok awal + stok masuk − stok keluar.</div>
                <div class="text-muted fs-8">Tanggal mencakup seluruh transaksi dari pukul 00:00 pada tanggal awal sampai 23:59 pada tanggal akhir. Mutasi yang dibatalkan tidak dihitung.</div>
            </div>
        </div>

        <div class="stock-balance-summary">
            <div class="stock-balance-summary-card opening">
                <div class="stock-balance-summary-label">Total Stok Awal</div>
                <div class="stock-balance-summary-value text-dark" id="summary_opening">0</div>
                <div class="text-muted fs-8 mt-1" id="summary_scope">0 item</div>
            </div>
            <div class="stock-balance-summary-card incoming">
                <div class="stock-balance-summary-label">Total Masuk</div>
                <div class="stock-balance-summary-value text-success" id="summary_in">0</div>
                <div class="text-muted fs-8 mt-1">Selama periode terpilih</div>
            </div>
            <div class="stock-balance-summary-card outgoing">
                <div class="stock-balance-summary-label">Total Keluar</div>
                <div class="stock-balance-summary-value text-danger" id="summary_out">0</div>
                <div class="text-muted fs-8 mt-1">Selama periode terpilih</div>
            </div>
            <div class="stock-balance-summary-card ending">
                <div class="stock-balance-summary-label">Total Saldo Akhir</div>
                <div class="stock-balance-summary-value text-primary" id="summary_ending">0</div>
                <div class="text-muted fs-8 mt-1" id="summary_period">-</div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <h3 class="fw-bolder mb-0">Rincian Saldo per SKU dan Gudang</h3>
        </div>
    </div>
    <div class="card-body py-6">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5" id="stock_balance_table">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                        <th class="w-50px">No</th>
                        <th>SKU</th>
                        <th class="min-w-250px">Nama Barang</th>
                        <th>Gudang</th>
                        <th class="text-end">Stok Awal</th>
                        <th class="text-end">Masuk</th>
                        <th class="text-end">Keluar</th>
                        <th class="text-end">Saldo Akhir</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const stockBalanceDataUrl = @json($dataUrl);
    const stockBalanceExportUrl = @json($exportUrl);
    const stockBalanceDefaults = {
        warehouseValues: ['all'],
        dateFrom: @json($defaultDateFrom),
        dateTo: @json($defaultDateTo),
    };

    document.addEventListener('DOMContentLoaded', () => {
        const searchInput = document.getElementById('report_search');
        const warehouseFilter = document.getElementById('filter_warehouse');
        const dateFromInput = document.getElementById('filter_date_from');
        const dateToInput = document.getElementById('filter_date_to');
        const applyButton = document.getElementById('filter_apply');
        const resetButton = document.getElementById('filter_reset');
        const exportButton = document.getElementById('btn_export_stock_balance');
        let searchTimer = null;

        const numberFormat = new Intl.NumberFormat('id-ID');
        const formatQty = (value) => numberFormat.format(Number(value || 0));
        const escapeHtml = (value) => String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');

        const selectedWarehouseIds = () => Array.from(warehouseFilter.selectedOptions)
            .map((option) => option.value)
            .filter((value) => value && value !== 'all');

        const currentParams = () => {
            const params = new URLSearchParams();
            params.set('date_from', dateFromInput.value);
            params.set('date_to', dateToInput.value);
            const selectedWarehouses = selectedWarehouseIds();
            selectedWarehouses.forEach((value) => params.append('warehouse_ids[]', value));
            if (searchInput.value.trim()) params.set('q', searchInput.value.trim());
            return params;
        };

        const validatePeriod = () => {
            if (!dateFromInput.value || !dateToInput.value) {
                if (typeof Swal !== 'undefined') Swal.fire('Filter belum lengkap', 'Tanggal awal dan tanggal akhir wajib diisi.', 'warning');
                return false;
            }
            if (dateFromInput.value > dateToInput.value) {
                if (typeof Swal !== 'undefined') Swal.fire('Rentang tanggal tidak valid', 'Tanggal akhir tidak boleh sebelum tanggal awal.', 'warning');
                return false;
            }
            return true;
        };

        const updateSummary = (summary, period) => {
            document.getElementById('summary_opening').textContent = formatQty(summary.opening_stock);
            document.getElementById('summary_in').textContent = formatQty(summary.stock_in);
            document.getElementById('summary_out').textContent = formatQty(summary.stock_out);
            document.getElementById('summary_ending').textContent = formatQty(summary.ending_stock);
            document.getElementById('summary_scope').textContent = `${formatQty(summary.total_items)} item · ${formatQty(summary.total_warehouses)} gudang`;
            document.getElementById('summary_period').textContent = period?.date_from && period?.date_to
                ? `${period.date_from} s.d. ${period.date_to}`
                : '-';
        };

        if (typeof flatpickr !== 'undefined') {
            flatpickr(dateFromInput, { dateFormat: 'Y-m-d', allowInput: true });
            flatpickr(dateToInput, { dateFormat: 'Y-m-d', allowInput: true });
        }

        if (typeof $ !== 'undefined' && $.fn.select2) {
            $(warehouseFilter).select2({
                width: '100%',
                placeholder: 'Pilih gudang',
                closeOnSelect: false,
            });
        }

        let previousWarehouseValues = ['all'];
        const normalizeWarehouseSelection = () => {
            let values = Array.from(warehouseFilter.selectedOptions).map((option) => option.value);
            const allWasSelected = previousWarehouseValues.includes('all');
            const allIsSelected = values.includes('all');

            if (allIsSelected && !allWasSelected) {
                values = ['all'];
            } else if (allIsSelected && values.length > 1) {
                values = values.filter((value) => value !== 'all');
            } else if (!values.length) {
                values = ['all'];
            }

            Array.from(warehouseFilter.options).forEach((option) => {
                option.selected = values.includes(option.value);
            });
            if (typeof $ !== 'undefined' && $.fn.select2) {
                $(warehouseFilter).trigger('change.select2');
            }
            previousWarehouseValues = values;
        };

        const table = $('#stock_balance_table').DataTable({
            processing: true,
            serverSide: true,
            searchDelay: 400,
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            dom: 'rt<"d-flex flex-stack flex-wrap pt-5"lip>',
            order: [[2, 'asc']],
            ajax: {
                url: stockBalanceDataUrl,
                data: function (requestParams) {
                    requestParams.date_from = dateFromInput.value;
                    requestParams.date_to = dateToInput.value;
                    requestParams.warehouse_ids = selectedWarehouseIds();
                    requestParams.q = searchInput.value.trim();
                },
                dataSrc: function (json) {
                    updateSummary(json.summary || {}, json.period || {});
                    return json.data || [];
                },
                error: function (xhr) {
                    const message = xhr.responseJSON?.message || 'Data laporan gagal dimuat.';
                    if (typeof toastr !== 'undefined') toastr.error(message);
                },
            },
            columns: [
                { data: null, orderable: false, searchable: false, render: (data, type, row, meta) => meta.row + meta.settings._iDisplayStart + 1 },
                { data: 'sku', render: (value, type, row) => `<div class="fw-bolder text-gray-900">${escapeHtml(value || '-')}</div>${row.item_status === 'inactive' ? '<span class="badge badge-light-secondary mt-1">Nonaktif</span>' : ''}` },
                { data: 'item_name', render: (value) => `<span class="fw-semibold text-gray-800">${escapeHtml(value || '-')}</span>` },
                { data: 'warehouse_name', render: (value, type, row) => `<div>${escapeHtml(value || '-')}</div><div class="text-muted fs-8">${escapeHtml(row.warehouse_code || '')}</div>` },
                { data: 'opening_stock', className: 'text-end stock-balance-qty', render: (value) => formatQty(value) },
                { data: 'stock_in', className: 'text-end stock-balance-qty text-success', render: (value) => value > 0 ? `+${formatQty(value)}` : '0' },
                { data: 'stock_out', className: 'text-end stock-balance-qty text-danger', render: (value) => value > 0 ? `−${formatQty(value)}` : '0' },
                { data: 'ending_stock', className: 'text-end stock-balance-qty', render: (value) => `<span class="badge ${Number(value) < 0 ? 'badge-light-danger' : 'badge-light-primary'} fs-7">${formatQty(value)}</span>` },
                { data: null, orderable: false, searchable: false, className: 'text-end', render: (data, type, row) => row.mutation_url ? `<a href="${escapeHtml(row.mutation_url)}" class="btn btn-sm btn-light-primary" title="Lihat mutasi SKU"><i class="fas fa-list-ul"></i> Mutasi</a>` : '<span class="text-muted">-</span>' },
            ],
            language: {
                emptyTable: 'Tidak ada data stok yang sesuai dengan filter.',
                info: 'Menampilkan _START_–_END_ dari _TOTAL_ baris',
                infoEmpty: 'Tidak ada data',
                lengthMenu: 'Tampilkan _MENU_',
                loadingRecords: 'Memuat...',
                processing: 'Menghitung saldo stok...',
                paginate: { previous: 'Sebelumnya', next: 'Berikutnya' },
            },
        });

        const reload = () => {
            if (validatePeriod()) table.ajax.reload();
        };

        applyButton.addEventListener('click', reload);
        warehouseFilter.addEventListener('change', () => {
            normalizeWarehouseSelection();
            reload();
        });
        searchInput.addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(reload, 450);
        });
        [dateFromInput, dateToInput].forEach((input) => input.addEventListener('change', reload));

        resetButton.addEventListener('click', () => {
            searchInput.value = '';
            Array.from(warehouseFilter.options).forEach((option) => {
                option.selected = stockBalanceDefaults.warehouseValues.includes(option.value);
            });
            previousWarehouseValues = [...stockBalanceDefaults.warehouseValues];
            if (typeof $ !== 'undefined' && $.fn.select2) {
                $(warehouseFilter).trigger('change.select2');
            }
            dateFromInput.value = stockBalanceDefaults.dateFrom;
            dateToInput.value = stockBalanceDefaults.dateTo;
            reload();
        });

        exportButton.addEventListener('click', () => {
            if (!validatePeriod()) return;
            window.location.href = `${stockBalanceExportUrl}?${currentParams().toString()}`;
        });
    });
</script>
@endpush
