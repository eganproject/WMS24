@extends('layouts.admin')

@section('title', 'Penerimaan Gudang Display')
@section('page_title', 'Penerimaan Gudang Display')

@section('content')
<div class="card mb-6">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div>
                <div class="fw-bolder fs-4">Barang Baru Masuk ke {{ $displayWarehouseLabel }}</div>
                <div class="text-muted fs-7">Riwayat mutasi stok masuk, termasuk hasil rework, transfer, inbound, retur, dan penyesuaian.</div>
            </div>
        </div>
        <div class="card-toolbar">
            <a href="{{ route('admin.inventory.stock-mutations.index') }}?warehouse_id={{ $displayWarehouseId }}&direction=in" class="btn btn-light">Buka Mutasi Stok</a>
        </div>
    </div>
    <div class="card-body pt-0">
        <div class="row g-4">
            <div class="col-md-3"><div class="card bg-light h-100"><div class="card-body"><div class="text-muted fs-7">Dokumen/Mutasi Masuk</div><div class="fs-2 fw-bolder" id="summary_receipts">0</div></div></div></div>
            <div class="col-md-3"><div class="card bg-light h-100"><div class="card-body"><div class="text-muted fs-7">SKU Masuk</div><div class="fs-2 fw-bolder" id="summary_sku">0</div></div></div></div>
            <div class="col-md-3"><div class="card bg-light h-100"><div class="card-body"><div class="text-muted fs-7">Total Qty Masuk</div><div class="fs-2 fw-bolder text-primary" id="summary_qty">0</div></div></div></div>
            <div class="col-md-3"><div class="card bg-light-success h-100"><div class="card-body"><div class="text-muted fs-7">Qty Hasil Rework</div><div class="fs-2 fw-bolder text-success" id="summary_rework">0</div></div></div></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title"><h3 class="card-label fw-bolder">Detail Penerimaan</h3></div>
        <div class="card-toolbar w-100 justify-content-end">
            <div class="d-flex flex-wrap gap-3 justify-content-end">
                <input id="filter_search" class="form-control form-control-solid w-250px" placeholder="Cari SKU, dokumen, catatan" />
                <select id="filter_source" class="form-select form-select-solid w-200px">
                    <option value="">Semua Asal</option><option value="rework">Hasil Rework</option><option value="transfer">Transfer Gudang</option><option value="inbound">Inbound</option><option value="return">Retur</option><option value="adjustment">Penyesuaian Stok</option><option value="other">Lainnya</option>
                </select>
                <input id="filter_date_from" type="date" class="form-control form-control-solid w-150px" value="{{ $today }}" title="Tanggal mulai" />
                <input id="filter_date_to" type="date" class="form-control form-control-solid w-150px" value="{{ $today }}" title="Tanggal akhir" />
                <button id="filter_reset" type="button" class="btn btn-light">Reset</button>
            </div>
        </div>
    </div>
    <div class="card-body py-6">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5" id="display_receipts_table">
                <thead><tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0"><th>Waktu Masuk</th><th>SKU Hasil</th><th>Nama Item</th><th class="text-end">Qty</th><th>Asal</th><th>SKU Rusak Sumber / Resep</th><th>Dokumen</th><th>Operator</th><th>Catatan</th><th class="text-end">Aksi</th></tr></thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const tableEl = $('#display_receipts_table');
    const searchEl = document.getElementById('filter_search');
    const sourceEl = document.getElementById('filter_source');
    const dateFromEl = document.getElementById('filter_date_from');
    const dateToEl = document.getElementById('filter_date_to');
    const resetEl = document.getElementById('filter_reset');
    const today = @json($today);
    if (!tableEl.length || !$.fn.DataTable) return;

    const text = (value) => $('<div>').text(value ?? '').html();
    const table = tableEl.DataTable({
        processing: true, serverSide: true, dom: 'rtip', order: [], pageLength: 25,
        ajax: { url: @json($dataUrl), dataSrc: (json) => {
            const summary = json.summary || {};
            document.getElementById('summary_receipts').textContent = summary.total_receipts ?? 0;
            document.getElementById('summary_sku').textContent = summary.total_sku ?? 0;
            document.getElementById('summary_qty').textContent = summary.total_qty ?? 0;
            document.getElementById('summary_rework').textContent = summary.rework_qty ?? 0;
            return json.data || [];
        }, data: (params) => { params.q = searchEl.value; params.source_group = sourceEl.value; params.date_from = dateFromEl.value; params.date_to = dateToEl.value; } },
        columns: [
            { data: 'occurred_at' }, { data: 'sku' }, { data: 'item_name' },
            { data: 'qty', className: 'text-end fw-bolder', render: (data) => Number(data || 0).toLocaleString('id-ID') },
            { data: 'source_group', render: (data) => `<span class="badge badge-light-primary">${text(data)}</span>` },
            { data: 'rework_source_items', render: (data, type, row) => {
                if (row?.source_group !== 'Hasil Rework') return `<span class="text-muted">-</span>`;
                const recipe = row?.rework_recipe && row.rework_recipe !== '-' ? `<div class="text-muted fs-8">${text(row.rework_recipe)}</div>` : '';
                return `<div class="fw-semibold">${text(data)}</div>${recipe}`;
            }},
            { data: 'source_code' }, { data: 'user' },
            { data: 'note', render: (data) => `<span class="text-muted">${text(data)}</span>` },
            { data: 'mutation_url', className: 'text-end', orderable: false, searchable: false, render: (url) => `<a href="${text(url)}" class="btn btn-sm btn-light-primary">Detail</a>` },
        ],
    });
    let timer;
    searchEl.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(() => table.ajax.reload(), 350); });
    [sourceEl, dateFromEl, dateToEl].forEach((el) => el.addEventListener('change', () => table.ajax.reload()));
    resetEl.addEventListener('click', () => { searchEl.value = ''; sourceEl.value = ''; dateFromEl.value = today; dateToEl.value = today; table.ajax.reload(); });
});
</script>
@endpush
