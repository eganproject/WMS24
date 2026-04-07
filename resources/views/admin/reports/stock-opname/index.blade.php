@extends('layouts.admin')

@section('title', 'Laporan Stock Opname')
@section('page_title', 'Laporan Stock Opname Harian')

@section('content')
<div class="card mb-6">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div class="d-flex align-items-center gap-2">
                @if(!empty($warehouses ?? []))
                    <select id="filter_warehouse" class="form-select form-select-solid w-200px">
                        <option value="all">Semua Gudang</option>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}" @if(!empty($defaultWarehouseId) && $defaultWarehouseId === $wh->id) selected @endif>{{ $wh->name }}</option>
                        @endforeach
                    </select>
                @endif
                <input type="text" class="form-control form-control-solid w-150px" id="filter_date_from" placeholder="Dari" />
                <input type="text" class="form-control form-control-solid w-150px" id="filter_date_to" placeholder="Sampai" />
                <button type="button" class="btn btn-light" id="filter_apply">Filter</button>
                <button type="button" class="btn btn-light" id="filter_reset">Reset</button>
            </div>
        </div>
        <div class="card-toolbar">
            <div class="d-flex align-items-center gap-2">
            @if(!empty($warehouseLabel ?? null))
                @php
                    $currentWarehouseId = $defaultWarehouseId ?? null;
                    $warehouseBadge = 'badge-light-secondary';
                    if (!empty($displayWarehouseId) && $currentWarehouseId == $displayWarehouseId) {
                        $warehouseBadge = 'badge-light-success';
                    } elseif (!empty($defaultWarehouseId) && $currentWarehouseId == $defaultWarehouseId) {
                        $warehouseBadge = 'badge-light-primary';
                    }
                @endphp
                <span class="badge {{ $warehouseBadge }} me-2" id="warehouse_badge">Gudang: {{ $warehouseLabel }}</span>
            @endif
                <span class="text-muted small">Akurasi dihitung dari jumlah SKU vs SKU selisih.</span>
                <button type="button" class="btn btn-light-primary btn-sm" id="btn_export_report">Export Excel</button>
            </div>
        </div>
    </div>
    <div class="card-body pt-2">
        <div class="row g-4">
            <div class="col-md-2">
                <div class="card bg-light border-0 h-100">
                    <div class="card-body">
                        <div class="text-muted small">Total Hari</div>
                        <div class="fs-2 fw-bolder" id="report_total_days">0</div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card bg-light border-0 h-100">
                    <div class="card-body">
                        <div class="text-muted small">Total Batch</div>
                        <div class="fs-2 fw-bolder" id="report_total_batches">0</div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card bg-light border-0 h-100">
                    <div class="card-body">
                        <div class="text-muted small">Total SKU</div>
                        <div class="fs-2 fw-bolder" id="report_total_sku">0</div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card bg-light border-0 h-100">
                    <div class="card-body">
                        <div class="text-muted small">SKU Selisih</div>
                        <div class="fs-2 fw-bolder text-danger" id="report_total_diff">0</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-light border-0 h-100">
                    <div class="card-body">
                        <div class="text-muted small">Akurasi</div>
                        <div class="fs-2 fw-bolder" id="report_accuracy">0%</div>
                        <div class="text-muted small">Semakin tinggi semakin akurat.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-6">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header border-0 pt-6">
                <div class="card-title">
                    <h3 class="card-label fw-bolder">SKU Selisih Lebih</h3>
                </div>
            </div>
            <div class="card-body pt-0">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-5" id="diff_plus_table">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                <th>SKU</th>
                                <th>Nama</th>
                                <th class="text-end">Jumlah Selisih</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header border-0 pt-6">
                <div class="card-title">
                    <h3 class="card-label fw-bolder">SKU Selisih Kurang</h3>
                </div>
            </div>
            <div class="card-body pt-0">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-5" id="diff_minus_table">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                <th>SKU</th>
                                <th>Nama</th>
                                <th class="text-end">Jumlah Selisih</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
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
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <rect opacity="0.5" x="17.0365" y="15.1223" width="8.15546" height="2" rx="1" transform="rotate(45 17.0365 15.1223)" fill="black" />
                        <path d="M11 19C6.55556 19 3 15.4444 3 11C3 6.55556 6.55556 3 11 3C15.4444 3 19 6.55556 19 11C19 15.4444 15.4444 19 11 19ZM11 5C7.53333 5 5 7.53333 5 11C5 14.4667 7.53333 17 11 17C14.4667 17 17 14.4667 17 11C17 7.53333 14.4667 5 11 5Z" fill="black" />
                    </svg>
                </span>
                <input type="text" class="form-control form-control-solid w-250px ps-14" placeholder="Cari tanggal / SKU" id="report_search" />
            </div>
        </div>
    </div>
    <div class="card-body py-6">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5" id="stock_opname_report_table">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                        <th>Tanggal</th>
                        <th>Total Batch</th>
                        <th>Jumlah SKU</th>
                        <th>SKU Selisih</th>
                        <th>Akurasi</th>
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
    const dataUrl = '{{ $dataUrl }}';
    const diffUrl = '{{ route('admin.reports.stock-opname.diff-sku') }}';
    const exportUrl = '{{ route('admin.reports.stock-opname.export') }}';
    const defaultWarehouseId = {{ !empty($defaultWarehouseId) ? (int) $defaultWarehouseId : 'null' }};
    const displayWarehouseId = {{ !empty($displayWarehouseId) ? (int) $displayWarehouseId : 'null' }};

    document.addEventListener('DOMContentLoaded', () => {
        const tableEl = $('#stock_opname_report_table');
        const diffPlusEl = $('#diff_plus_table');
        const diffMinusEl = $('#diff_minus_table');
        const searchInput = document.getElementById('report_search');
        const warehouseFilter = document.getElementById('filter_warehouse');
        const dateFromEl = document.getElementById('filter_date_from');
        const dateToEl = document.getElementById('filter_date_to');
        const applyBtn = document.getElementById('filter_apply');
        const resetBtn = document.getElementById('filter_reset');
        const exportBtn = document.getElementById('btn_export_report');

        const elSummary = {
            totalDays: document.getElementById('report_total_days'),
            totalBatches: document.getElementById('report_total_batches'),
            totalSku: document.getElementById('report_total_sku'),
            totalDiff: document.getElementById('report_total_diff'),
            accuracy: document.getElementById('report_accuracy'),
        };

        let fpFrom = null;
        let fpTo = null;
        if (typeof flatpickr !== 'undefined') {
            if (dateFromEl) {
                fpFrom = flatpickr(dateFromEl, { dateFormat: 'Y-m-d', allowInput: true });
            }
            if (dateToEl) {
                fpTo = flatpickr(dateToEl, { dateFormat: 'Y-m-d', allowInput: true });
            }
        }

        if (warehouseFilter && typeof $ !== 'undefined' && $.fn.select2) {
            $(warehouseFilter).select2({ placeholder: 'Semua Gudang', allowClear: true, width: '200px' });
        }

        const warehouseBadgeEl = document.getElementById('warehouse_badge');
        const warehouseBadgeClass = (warehouseId) => {
            const id = Number(warehouseId || 0);
            if (displayWarehouseId && id === Number(displayWarehouseId)) return 'badge-light-success';
            if (defaultWarehouseId && id === Number(defaultWarehouseId)) return 'badge-light-primary';
            return 'badge-light-secondary';
        };
        const updateWarehouseBadge = () => {
            if (!warehouseBadgeEl) return;
            const selectedVal = warehouseFilter?.value || '';
            if (!selectedVal || selectedVal === 'all') {
                warehouseBadgeEl.className = 'badge badge-light-secondary me-2';
                warehouseBadgeEl.textContent = 'Gudang: Semua Gudang';
                return;
            }
            const label = warehouseFilter?.selectedOptions?.[0]?.textContent?.trim() || 'Gudang';
            const badgeClass = warehouseBadgeClass(selectedVal);
            warehouseBadgeEl.className = `badge ${badgeClass} me-2`;
            warehouseBadgeEl.textContent = `Gudang: ${label}`;
        };

        const updateSummary = (summary = {}) => {
            if (elSummary.totalDays) elSummary.totalDays.textContent = summary.total_days ?? 0;
            if (elSummary.totalBatches) elSummary.totalBatches.textContent = summary.total_batches ?? 0;
            if (elSummary.totalSku) elSummary.totalSku.textContent = summary.total_sku ?? 0;
            if (elSummary.totalDiff) elSummary.totalDiff.textContent = summary.total_diff_sku ?? 0;
            if (elSummary.accuracy) {
                const acc = typeof summary.accuracy === 'number' ? summary.accuracy : 0;
                elSummary.accuracy.textContent = `${acc.toFixed(2)}%`;
            }
        };

        if (!tableEl.length || !$.fn.DataTable) {
            console.error('DataTables unavailable');
            return;
        }

        const dt = tableEl.DataTable({
            processing: true,
            serverSide: true,
            dom: 'rtip',
            order: [[0, 'desc']],
            ajax: {
                url: dataUrl,
                dataSrc: function(json) {
                    updateSummary(json?.summary || {});
                    return json.data || [];
                },
                data: function(params) {
                    params.q = searchInput?.value || '';
                    if (warehouseFilter?.value) params.warehouse_id = warehouseFilter.value;
                    if (dateFromEl?.value) params.date_from = dateFromEl.value;
                    if (dateToEl?.value) params.date_to = dateToEl.value;
                }
            },
            columns: [
                { data: 'date' },
                { data: 'batch_count' },
                { data: 'sku_count' },
                { data: 'diff_sku_count' },
                { data: 'accuracy', render: (data) => {
                    const val = parseFloat(data || 0);
                    let badge = 'badge-light-success';
                    if (val < 90) badge = 'badge-light-danger';
                    else if (val < 95) badge = 'badge-light-warning';
                    return `<span class="badge ${badge}">${val.toFixed(2)}%</span>`;
                }},
            ]
        });

        const initDiffTable = (el, type) => {
            if (!el.length) return null;
            return el.DataTable({
                processing: true,
                serverSide: true,
                dom: 'rtip',
                ordering: false,
                ajax: {
                    url: diffUrl,
                    dataSrc: 'data',
                data: function(params) {
                    params.type = type;
                    params.q = searchInput?.value || '';
                    if (warehouseFilter?.value) params.warehouse_id = warehouseFilter.value;
                    if (dateFromEl?.value) params.date_from = dateFromEl.value;
                    if (dateToEl?.value) params.date_to = dateToEl.value;
                }
            },
                columns: [
                    { data: 'sku' },
                    { data: 'name' },
                    { data: 'qty', className: 'text-end' },
                ]
            });
        };

        const diffPlusTable = initDiffTable(diffPlusEl, 'plus');
        const diffMinusTable = initDiffTable(diffMinusEl, 'minus');

        const reloadAll = () => {
            dt.ajax.reload();
            diffPlusTable?.ajax.reload();
            diffMinusTable?.ajax.reload();
        };

        searchInput?.addEventListener('keyup', reloadAll);
        warehouseFilter?.addEventListener('change', reloadAll);
        warehouseFilter?.addEventListener('change', updateWarehouseBadge);
        applyBtn?.addEventListener('click', reloadAll);
        resetBtn?.addEventListener('click', () => {
            if (warehouseFilter) {
                warehouseFilter.value = 'all';
                if (typeof $ !== 'undefined' && $(warehouseFilter).data('select2')) {
                    $(warehouseFilter).val('all').trigger('change.select2');
                }
            }
            updateWarehouseBadge();
            if (fpFrom) fpFrom.clear(); else if (dateFromEl) dateFromEl.value = '';
            if (fpTo) fpTo.clear(); else if (dateToEl) dateToEl.value = '';
            reloadAll();
        });

        exportBtn?.addEventListener('click', () => {
            const params = new URLSearchParams();
            const q = searchInput?.value?.trim();
            if (q) params.set('q', q);
            if (warehouseFilter?.value) params.set('warehouse_id', warehouseFilter.value);
            if (dateFromEl?.value) params.set('date_from', dateFromEl.value);
            if (dateToEl?.value) params.set('date_to', dateToEl.value);
            const url = params.toString() ? `${exportUrl}?${params.toString()}` : exportUrl;
            window.location.href = url;
        });
        updateWarehouseBadge();
    });
</script>
@endpush
