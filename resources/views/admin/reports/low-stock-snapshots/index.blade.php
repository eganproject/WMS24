@extends('layouts.admin')

@section('title', 'Snapshot Low Stock')
@section('page_title', 'Snapshot Low Stock')

@section('content')
<div class="card mb-6">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <h3 class="card-label fw-bolder">Riwayat Snapshot</h3>
        </div>
        <div class="card-toolbar">
            <div class="d-flex align-items-end gap-3 flex-wrap">
                <div class="w-200px">
                    <label class="text-muted fs-7 mb-1">Gudang</label>
                    <select id="snapshot_warehouse" class="form-select form-select-solid">
                        <option value="">Semua Scope</option>
                        <option value="all">Snapshot Semua Gudang</option>
                        @foreach($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="w-150px">
                    <label class="text-muted fs-7 mb-1">Dari</label>
                    <input type="date" id="snapshot_date_from" class="form-control form-control-solid" />
                </div>
                <div class="w-150px">
                    <label class="text-muted fs-7 mb-1">Sampai</label>
                    <input type="date" id="snapshot_date_to" class="form-control form-control-solid" />
                </div>
                <div class="w-200px">
                    <label class="text-muted fs-7 mb-1">Buat Snapshot</label>
                    <select id="create_warehouse" class="form-select form-select-solid">
                        <option value="all">Semua Gudang</option>
                        @foreach($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="button" class="btn btn-primary" id="btn_create_snapshot">Buat Snapshot</button>
                <button type="button" class="btn btn-light" id="btn_reset_snapshot">Reset</button>
            </div>
        </div>
    </div>
    <div class="card-body py-6">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5" id="snapshots_table">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                        <th>Waktu</th>
                        <th>Gudang</th>
                        <th class="text-end">Total Low</th>
                        <th class="text-end">Out</th>
                        <th class="text-end">Gap</th>
                        <th>Sumber</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title flex-column align-items-start">
            <h3 class="card-label fw-bolder mb-1">Detail Snapshot</h3>
            <div class="text-muted fs-7" id="detail_title">Pilih snapshot untuk melihat detail.</div>
        </div>
        <div class="card-toolbar">
            <div class="d-flex align-items-end gap-3 flex-wrap">
                <div class="w-250px">
                    <label class="text-muted fs-7 mb-1">Cari SKU / Nama</label>
                    <input type="text" class="form-control form-control-solid" id="detail_search" />
                </div>
                <div class="w-175px">
                    <label class="text-muted fs-7 mb-1">Status</label>
                    <select id="detail_status" class="form-select form-select-solid">
                        <option value="">Semua</option>
                        <option value="out">Out of Stock</option>
                        <option value="low">Low Stock</option>
                    </select>
                </div>
            </div>
        </div>
    </div>
    <div class="card-body py-6">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5" id="snapshot_items_table">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                        <th>SKU</th>
                        <th>Nama</th>
                        <th>Gudang</th>
                        <th>Kategori</th>
                        <th class="text-end">Stok</th>
                        <th class="text-end">Safety</th>
                        <th class="text-end">Gap</th>
                        <th>Status</th>
                        <th>Alamat</th>
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
    const snapshotDataUrl = @json($dataUrl);
    const snapshotStoreUrl = @json($storeUrl);
    const detailDataUrlTpl = @json($detailDataUrlTpl);
    const csrfToken = @json(csrf_token());

    document.addEventListener('DOMContentLoaded', () => {
        const snapshotTableEl = $('#snapshots_table');
        const detailTableEl = $('#snapshot_items_table');
        const warehouseFilter = document.getElementById('snapshot_warehouse');
        const dateFrom = document.getElementById('snapshot_date_from');
        const dateTo = document.getElementById('snapshot_date_to');
        const createWarehouse = document.getElementById('create_warehouse');
        const createBtn = document.getElementById('btn_create_snapshot');
        const resetBtn = document.getElementById('btn_reset_snapshot');
        const detailTitle = document.getElementById('detail_title');
        const detailSearch = document.getElementById('detail_search');
        const detailStatus = document.getElementById('detail_status');
        let selectedSnapshotId = null;

        const escapeHtml = (value) => String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');

        const formatNumber = (value) => {
            const number = Number(value);
            return Number.isFinite(number) ? number.toLocaleString('id-ID') : '0';
        };

        const snapshotsDt = snapshotTableEl.DataTable({
            processing: true,
            serverSide: true,
            dom: 'rtip',
            order: [],
            ajax: {
                url: snapshotDataUrl,
                dataSrc: 'data',
                data: (params) => {
                    params.warehouse_id = warehouseFilter?.value || '';
                    params.date_from = dateFrom?.value || '';
                    params.date_to = dateTo?.value || '';
                },
            },
            columns: [
                { data: 'snapshot_at' },
                { data: 'warehouse' },
                { data: 'total_low', className: 'text-end', render: (data) => formatNumber(data) },
                { data: 'total_out_of_stock', className: 'text-end', render: (data) => formatNumber(data) },
                { data: 'total_gap', className: 'text-end', render: (data) => formatNumber(data) },
                { data: 'source', render: (data) => data === 'scheduled' ? 'Terjadwal' : 'Manual' },
                { data: 'id', orderable: false, searchable: false, className: 'text-end', render: (data) => `
                    <button type="button" class="btn btn-sm btn-light-primary btn-detail-snapshot" data-id="${data}">Detail</button>
                ` },
            ],
        });

        const detailDt = detailTableEl.DataTable({
            processing: true,
            serverSide: true,
            dom: 'rtip',
            order: [],
            ajax: {
                url: detailDataUrlTpl.replace(':id', 0),
                dataSrc: 'data',
                data: (params) => {
                    params.q = detailSearch?.value || '';
                    params.status = detailStatus?.value || '';
                },
            },
            columns: [
                { data: 'sku' },
                { data: 'name' },
                { data: 'warehouse' },
                { data: 'category' },
                { data: 'stock', className: 'text-end', render: (data) => formatNumber(data) },
                { data: 'safety_stock', className: 'text-end', render: (data) => formatNumber(data) },
                { data: 'gap', className: 'text-end', render: (data) => formatNumber(data) },
                { data: 'status', render: (data) => data === 'Out of Stock'
                    ? '<span class="badge badge-light-danger">Out of Stock</span>'
                    : '<span class="badge badge-light-warning">Low Stock</span>' },
                { data: 'address', render: (data) => escapeHtml(data || '-') },
            ],
        });

        warehouseFilter?.addEventListener('change', () => snapshotsDt.ajax.reload());
        dateFrom?.addEventListener('change', () => snapshotsDt.ajax.reload());
        dateTo?.addEventListener('change', () => snapshotsDt.ajax.reload());
        detailSearch?.addEventListener('input', () => selectedSnapshotId && detailDt.ajax.reload());
        detailStatus?.addEventListener('change', () => selectedSnapshotId && detailDt.ajax.reload());

        resetBtn?.addEventListener('click', () => {
            if (warehouseFilter) warehouseFilter.value = '';
            if (dateFrom) dateFrom.value = '';
            if (dateTo) dateTo.value = '';
            snapshotsDt.ajax.reload();
        });

        snapshotTableEl.on('click', '.btn-detail-snapshot', function () {
            selectedSnapshotId = this.getAttribute('data-id');
            const row = snapshotsDt.row($(this).closest('tr')).data();
            if (detailTitle) detailTitle.textContent = `${row.snapshot_at} - ${row.warehouse}`;
            detailDt.ajax.url(detailDataUrlTpl.replace(':id', selectedSnapshotId)).load();
        });

        createBtn?.addEventListener('click', async () => {
            const formData = new FormData();
            formData.append('_token', csrfToken);
            formData.append('warehouse_id', createWarehouse?.value || 'all');

            createBtn.disabled = true;
            try {
                const response = await fetch(snapshotStoreUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    body: formData,
                });
                const json = await response.json();
                if (!response.ok) {
                    throw new Error(json.message || 'Gagal membuat snapshot');
                }
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Berhasil', json.message || 'Snapshot dibuat', 'success');
                }
                snapshotsDt.ajax.reload();
            } catch (error) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Error', error.message || 'Gagal membuat snapshot', 'error');
                } else {
                    alert(error.message || 'Gagal membuat snapshot');
                }
            } finally {
                createBtn.disabled = false;
            }
        });
    });
</script>
@endpush
