@extends('layouts.admin')

@section('title', 'Laporan Replenishment')
@section('page_title', 'Laporan Replenishment Display')

@section('content')
<div class="card mb-6">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div class="d-flex align-items-center position-relative my-1">
                <span class="svg-icon svg-icon-1 position-absolute ms-6">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <rect opacity="0.5" x="17.0365" y="15.1223" width="8.15546" height="2" rx="1" transform="rotate(45 17.0365 15.1223)" fill="black" />
                        <path d="M11 19C6.55556 19 3 15.4444 3 11C3 6.55556 6.55556 3 11 3C15.4444 3 19 6.55556 19 11C19 15.4444 15.4444 19 11 19ZM11 5C7.53333 5 5 7.53333 5 11C5 14.4667 7.53333 17 11 17C14.4667 17 17 14.4667 17 11C17 7.53333 14.4667 5 11 5Z" fill="black" />
                    </svg>
                </span>
                <input type="text" class="form-control form-control-solid w-250px ps-14" placeholder="Cari SKU / Nama" id="report_search" />
            </div>
        </div>
        <div class="card-toolbar">
            <div class="d-flex align-items-end gap-3 flex-wrap">
                <div class="min-w-200px">
                    <label class="text-muted fs-7 mb-1">Kategori</label>
                    <select id="filter_category" class="form-select form-select-solid w-200px">
                        <option value="">Semua Kategori</option>
                        <option value="0">Tanpa Kategori</option>
                        @foreach($categories as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="min-w-100px">
                    <label class="text-muted fs-7 mb-1">Limit</label>
                    <select id="filter_limit" class="form-select form-select-solid w-100px">
                        <option value="10" selected>10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>
                <div>
                    <button type="button" class="btn btn-light" id="filter_reset">Reset</button>
                </div>
            </div>
        </div>
    </div>
    <div class="card-body pt-2">
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card bg-light border-0 h-100">
                    <div class="card-body">
                        <div class="text-muted small">Total SKU Butuh Replenishment</div>
                        <div class="fs-2 fw-bolder text-danger" id="summary_total_items">0</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-light border-0 h-100">
                    <div class="card-body">
                        <div class="text-muted small">Total Kebutuhan ({{ $displayWarehouseLabel ?? 'Gudang Display' }})</div>
                        <div class="fs-2 fw-bolder" id="summary_total_need">0</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-light border-0 h-100">
                    <div class="card-body">
                        <div class="text-muted small">Rekomendasi Transfer ({{ $defaultWarehouseLabel ?? 'Gudang Besar' }})</div>
                        <div class="fs-2 fw-bolder" id="summary_total_suggest">0</div>
                        <div class="text-muted small">Dihitung dari stok utama yang masih di atas safety aktif.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <h3 class="card-label fw-bolder">Detail Replenishment Display</h3>
        </div>
    </div>
    <div class="card-body py-6">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5" id="replenishment_table">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                        <th width="5%">No</th>
                        <th>SKU</th>
                        <th>Nama</th>
                        <th>Kategori</th>
                        <th class="text-end">Stok {{ $displayWarehouseLabel ?? 'Gudang Display' }}</th>
                        <th class="text-end">Safety</th>
                        <th class="text-end">Kebutuhan</th>
                        <th class="text-end">Stok {{ $defaultWarehouseLabel ?? 'Gudang Besar' }}</th>
                        <th class="text-end">Rekomendasi Transfer</th>
                        <th>Alamat</th>
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
    const dataUrl = '{{ $dataUrl }}';
    const transferUrl = '{{ $transferUrl ?? '' }}';
    const defaultWarehouseId = '{{ $defaultWarehouseId ?? '' }}';
    const displayWarehouseId = '{{ $displayWarehouseId ?? '' }}';

    document.addEventListener('DOMContentLoaded', () => {
        const tableEl = $('#replenishment_table');
        const searchInput = document.getElementById('report_search');
        const categoryFilter = document.getElementById('filter_category');
        const limitFilter = document.getElementById('filter_limit');
        const resetBtn = document.getElementById('filter_reset');
        const summaryItemsEl = document.getElementById('summary_total_items');
        const summaryNeedEl = document.getElementById('summary_total_need');
        const summarySuggestEl = document.getElementById('summary_total_suggest');

        if (!tableEl.length || !$.fn.DataTable) {
            console.error('DataTables unavailable');
            return;
        }

        if (typeof $ !== 'undefined' && $.fn.select2) {
            $(categoryFilter).select2({ placeholder: 'Semua', allowClear: true, width: '100%' });
        }

        const dt = tableEl.DataTable({
            processing: true,
            serverSide: true,
            dom: 'rtip',
            order: [],
            pageLength: Number(limitFilter?.value || 10),
            ajax: {
                url: dataUrl,
                dataSrc: function (json) {
                    const summary = json?.summary || {};
                    if (summaryItemsEl) summaryItemsEl.textContent = summary.total_items ?? 0;
                    if (summaryNeedEl) summaryNeedEl.textContent = summary.total_need ?? 0;
                    if (summarySuggestEl) summarySuggestEl.textContent = summary.total_suggest ?? 0;
                    return json.data || [];
                },
                data: function (params) {
                    params.q = searchInput?.value || '';
                    params.category_id = categoryFilter?.value || '';
                }
            },
            columns: [
                { data: null, orderable: false, searchable: false, render: (data, type, row, meta) => meta.row + meta.settings._iDisplayStart + 1 },
                { data: 'sku' },
                { data: 'name' },
                { data: 'category' },
                { data: 'display_stock', className: 'text-end', render: (data, type) => {
                    const value = Number.isFinite(Number(data)) ? Number(data) : 0;
                    if (type !== 'display') return value;
                    const textClass = value <= 0 ? 'text-danger' : 'text-warning';
                    return `<span class="fw-bold ${textClass}">${value}</span>`;
                }},
                { data: 'safety_stock', className: 'text-end', render: (data, type, row) => {
                    const value = Number.isFinite(Number(data)) ? Number(data) : 0;
                    if (type !== 'display') return value;
                    const source = row?.display_safety_source || 'Default item';
                    return `<span class="fw-semibold">${value}</span><div class="text-muted fs-8">${source}</div>`;
                }},
                { data: 'need_qty', className: 'text-end', render: (data) => data ?? 0 },
                { data: 'main_stock', className: 'text-end', render: (data, type, row) => {
                    const stock = Number.isFinite(Number(data)) ? Number(data) : 0;
                    const safety = Number.isFinite(Number(row?.main_safety_stock)) ? Number(row.main_safety_stock) : 0;
                    const available = Number.isFinite(Number(row?.available_main_qty)) ? Number(row.available_main_qty) : 0;
                    if (type !== 'display') return stock;
                    return `<span class="fw-semibold">${stock}</span><div class="text-muted fs-8">safety ${safety} | siap ${available}</div>`;
                }},
                { data: 'suggest_qty', className: 'text-end', render: (data) => data ?? 0 },
                { data: 'address' },
                { data: null, orderable: false, searchable: false, className: 'text-end', render: (data, type, row) => {
                    const suggest = Number(row?.suggest_qty || 0);
                    const itemId = row?.id;
                    if (!transferUrl || !itemId) return '';
                    if (suggest <= 0) {
                        return '<span class="text-muted">-</span>';
                    }
                    const params = new URLSearchParams({
                        prefill: '1',
                        item_id: String(itemId),
                        qty: String(suggest),
                        from: String(defaultWarehouseId || ''),
                        to: String(displayWarehouseId || ''),
                    });
                    const href = `${transferUrl}?${params.toString()}`;
                    return `<a href="${href}" class="btn btn-sm btn-light-primary">Buat Transfer</a>`;
                }},
            ],
            language: {
                emptyTable: 'Tidak ada kebutuhan replenishment',
                processing: 'Memuat...',
            }
        });

        const reloadTable = () => dt.ajax.reload();

        searchInput?.addEventListener('keyup', (e) => {
            if (e.key === 'Enter') reloadTable();
        });
        categoryFilter?.addEventListener('change', reloadTable);
        limitFilter?.addEventListener('change', () => {
            const val = Number(limitFilter.value || 10);
            dt.page.len(val).draw();
        });
        resetBtn?.addEventListener('click', () => {
            if (searchInput) searchInput.value = '';
            if (categoryFilter) {
                categoryFilter.value = '';
                if (typeof $ !== 'undefined' && $(categoryFilter).data('select2')) {
                    $(categoryFilter).val('').trigger('change.select2');
                }
            }
            if (limitFilter) {
                limitFilter.value = '10';
                dt.page.len(10).draw();
            }
            reloadTable();
        });
    });
</script>
@endpush
