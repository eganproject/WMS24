@extends('layouts.admin')

@section('title', 'Forecast Stok Harian')
@section('page_title', 'Forecast Stok Harian')

@section('content')
<style>
    .forecast-card {
        border: 1px solid #eef0f4;
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.04);
    }
    .forecast-metric {
        min-height: 112px;
    }
    .forecast-daily-wrap {
        display: flex;
        flex-wrap: wrap;
        gap: 0.35rem;
        max-width: 320px;
    }
    .forecast-daily-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        border-radius: 0.5rem;
        padding: 0.28rem 0.5rem;
        background: #f5f8fa;
        color: #3f4254;
        font-size: 11px;
        font-weight: 600;
        white-space: nowrap;
    }
    .forecast-stock-number {
        font-variant-numeric: tabular-nums;
    }
</style>

<div class="card forecast-card mb-6">
    <div class="card-body p-6 p-lg-8">
        <div class="d-flex flex-column flex-xl-row justify-content-between gap-6">
            <div>
                <div class="text-muted fw-semibold mb-2">Forecast stok display setelah dikurangi remaining picking list</div>
                <h1 class="fs-2hx fw-bolder text-dark mb-3">Forecast Stok Harian</h1>
                <div class="text-muted">
                    Stok utama dihitung dari <span class="fw-bold">{{ $displayWarehouse?->name ?? 'Gudang Display' }}</span>.
                    Stok <span class="fw-bold">{{ $defaultWarehouse?->name ?? 'Gudang Besar' }}</span> ditampilkan sebagai informasi cadangan/replenishment.
                </div>
            </div>
            <div class="d-flex align-items-end gap-3 flex-wrap">
                <div>
                    <label class="text-muted fs-7 mb-1">Tanggal Dari</label>
                    <input type="date" id="filter_date_from" class="form-control form-control-solid w-160px" value="{{ $dateFrom }}">
                </div>
                <div>
                    <label class="text-muted fs-7 mb-1">Tanggal Sampai</label>
                    <input type="date" id="filter_date_to" class="form-control form-control-solid w-160px" value="{{ $dateTo }}">
                </div>
                <div>
                    <label class="text-muted fs-7 mb-1">Kategori</label>
                    <select id="filter_category" class="form-select form-select-solid w-190px">
                        <option value="">Semua Kategori</option>
                        <option value="0">Tanpa Kategori</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-muted fs-7 mb-1">Kondisi</label>
                    <select id="filter_status" class="form-select form-select-solid w-150px">
                        <option value="all">Semua</option>
                        <option value="out">Kurang</option>
                        <option value="low">Menipis</option>
                        <option value="safe">Aman</option>
                        <option value="high">Banyak</option>
                    </select>
                </div>
                <button type="button" class="btn btn-primary" id="filter_apply">Terapkan</button>
                <button type="button" class="btn btn-light" id="filter_reset">Reset</button>
            </div>
        </div>
    </div>
</div>

<div class="row g-5 mb-6">
    <div class="col-md-6 col-xl-2">
        <div class="card forecast-card forecast-metric">
            <div class="card-body">
                <div class="text-muted fw-semibold mb-2">Total Item</div>
                <div class="fs-2 fw-bolder forecast-stock-number" id="summary_total_items">0</div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-2">
        <div class="card forecast-card forecast-metric bg-light-danger">
            <div class="card-body">
                <div class="text-muted fw-semibold mb-2">Kurang</div>
                <div class="fs-2 fw-bolder text-danger forecast-stock-number" id="summary_out_stock">0</div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-2">
        <div class="card forecast-card forecast-metric bg-light-warning">
            <div class="card-body">
                <div class="text-muted fw-semibold mb-2">Menipis</div>
                <div class="fs-2 fw-bolder text-warning forecast-stock-number" id="summary_low_stock">0</div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-2">
        <div class="card forecast-card forecast-metric bg-light-primary">
            <div class="card-body">
                <div class="text-muted fw-semibold mb-2">Aman</div>
                <div class="fs-2 fw-bolder text-primary forecast-stock-number" id="summary_safe_stock">0</div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-2">
        <div class="card forecast-card forecast-metric bg-light-success">
            <div class="card-body">
                <div class="text-muted fw-semibold mb-2">Banyak</div>
                <div class="fs-2 fw-bolder text-success forecast-stock-number" id="summary_high_stock">0</div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-2">
        <div class="card forecast-card forecast-metric">
            <div class="card-body">
                <div class="text-muted fw-semibold mb-2">Remaining Picking</div>
                <div class="fs-2 fw-bolder forecast-stock-number" id="summary_remaining">0</div>
            </div>
        </div>
    </div>
</div>

<div class="card forecast-card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div class="d-flex align-items-center position-relative my-1">
                <span class="svg-icon svg-icon-1 position-absolute ms-6">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <rect opacity="0.5" x="17.0365" y="15.1223" width="8.15546" height="2" rx="1" transform="rotate(45 17.0365 15.1223)" fill="black" />
                        <path d="M11 19C6.55556 19 3 15.4444 3 11C3 6.55556 6.55556 3 11 3C15.4444 3 19 6.55556 19 11C19 15.4444 15.4444 19 11 19ZM11 5C7.53333 5 5 7.53333 5 11C5 14.4667 7.53333 17 11 17C14.4667 17 17 14.4667 17 11C17 7.53333 14.4667 5 11 5Z" fill="black" />
                    </svg>
                </span>
                <input type="text" class="form-control form-control-solid w-300px ps-14" placeholder="Cari SKU / nama barang" id="forecast_search" />
            </div>
        </div>
        <div class="card-toolbar">
            <span class="badge badge-light-secondary">Display - remaining picking = forecast</span>
        </div>
    </div>
    <div class="card-body py-6">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5" id="daily_stock_forecast_table">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                        <th>Barang</th>
                        <th>Area / Alamat</th>
                        <th class="text-end">Stok Display</th>
                        <th class="text-end">Remaining Picking</th>
                        <th class="text-end">Forecast</th>
                        <th class="text-end">Safety</th>
                        <th class="text-end">Stok Gudang Besar</th>
                        <th>Remaining per Hari</th>
                        <th>Status</th>
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
    document.addEventListener('DOMContentLoaded', function() {
        const dataUrl = '{{ $dataUrl }}';
        const tableEl = $('#daily_stock_forecast_table');
        const searchInput = document.getElementById('forecast_search');
        const dateFrom = document.getElementById('filter_date_from');
        const dateTo = document.getElementById('filter_date_to');
        const categoryFilter = document.getElementById('filter_category');
        const statusFilter = document.getElementById('filter_status');
        const applyBtn = document.getElementById('filter_apply');
        const resetBtn = document.getElementById('filter_reset');
        const defaultDateFrom = '{{ $dateFrom }}';
        const defaultDateTo = '{{ $dateTo }}';

        const formatNumber = (value) => Number(value || 0).toLocaleString('id-ID');
        const escapeHtml = (value) => $('<div>').text(value ?? '').html();

        const setSummary = (summary = {}) => {
            $('#summary_total_items').text(formatNumber(summary.total_items));
            $('#summary_out_stock').text(formatNumber(summary.out_stock));
            $('#summary_low_stock').text(formatNumber(summary.low_stock));
            $('#summary_safe_stock').text(formatNumber(summary.safe_stock));
            $('#summary_high_stock').text(formatNumber(summary.high_stock));
            $('#summary_remaining').text(formatNumber(summary.total_remaining));
        };

        const statusBadge = (row) => {
            const cls = row.status_class || 'secondary';
            return `<span class="badge badge-light-${cls}">${escapeHtml(row.status_label)}</span>`;
        };

        const dailyBadges = (rows) => {
            if (!Array.isArray(rows) || rows.length === 0) {
                return '<span class="text-muted">Tidak ada remaining</span>';
            }

            return `<div class="forecast-daily-wrap">${rows.map((row) => {
                const date = String(row.date || '').slice(5);
                return `<span class="forecast-daily-badge">${escapeHtml(date)}<strong>${formatNumber(row.qty)}</strong></span>`;
            }).join('')}</div>`;
        };

        if (typeof $ !== 'undefined' && $.fn.select2) {
            $(categoryFilter).select2({ placeholder: 'Semua Kategori', allowClear: true, width: '100%' });
            $(statusFilter).select2({ minimumResultsForSearch: Infinity, width: '100%' });
        }

        if (!tableEl.length || !$.fn.DataTable) {
            console.error('DataTables unavailable');
            return;
        }

        const dt = tableEl.DataTable({
            processing: true,
            serverSide: false,
            dom: 'rtip',
            pageLength: 25,
            order: [[4, 'asc']],
            ajax: {
                url: dataUrl,
                dataSrc: function(json) {
                    setSummary(json.summary || {});
                    return json.data || [];
                },
                data: function(params) {
                    params.q = searchInput?.value || '';
                    params.date_from = dateFrom?.value || '';
                    params.date_to = dateTo?.value || '';
                    params.category_id = categoryFilter?.value || '';
                    params.status = statusFilter?.value || 'all';
                    params.limit = 300;
                }
            },
            columns: [
                {
                    data: null,
                    render: (row) => `
                        <div class="fw-bold">${escapeHtml(row.sku)}</div>
                        <div class="text-muted">${escapeHtml(row.name)}</div>
                        <div class="text-muted small">${escapeHtml(row.category)}</div>
                    `,
                },
                {
                    data: null,
                    render: (row) => `
                        <div class="fw-semibold">${escapeHtml(row.area)}</div>
                        <div class="text-muted small">${escapeHtml(row.address)}</div>
                    `,
                },
                { data: 'display_stock', className: 'text-end forecast-stock-number', render: formatNumber },
                { data: 'total_remaining', className: 'text-end forecast-stock-number fw-bold', render: formatNumber },
                {
                    data: 'forecast_stock',
                    className: 'text-end forecast-stock-number fw-bolder',
                    render: (value, type, row) => {
                        const cls = row.status === 'out' ? 'text-danger' : (row.status === 'low' ? 'text-warning' : 'text-dark');
                        return `<span class="${cls}">${formatNumber(value)}</span>`;
                    },
                },
                { data: 'safety_stock', className: 'text-end forecast-stock-number', render: formatNumber },
                { data: 'default_stock', className: 'text-end forecast-stock-number', render: formatNumber },
                { data: 'daily_remaining', orderable: false, render: dailyBadges },
                { data: null, render: statusBadge },
            ],
        });

        let searchTimer = null;
        searchInput?.addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => dt.ajax.reload(), 250);
        });
        applyBtn?.addEventListener('click', () => dt.ajax.reload());
        resetBtn?.addEventListener('click', () => {
            if (searchInput) searchInput.value = '';
            if (dateFrom) dateFrom.value = defaultDateFrom;
            if (dateTo) dateTo.value = defaultDateTo;
            if (categoryFilter) {
                categoryFilter.value = '';
                $(categoryFilter).trigger('change.select2');
            }
            if (statusFilter) {
                statusFilter.value = 'all';
                $(statusFilter).trigger('change.select2');
            }
            dt.ajax.reload();
        });
    });
</script>
@endpush
