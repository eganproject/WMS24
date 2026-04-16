@extends('layouts.admin')

@section('title', 'Item Stocks')
@section('page_title', 'Item Stocks')

@section('content')
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
                <input type="text" class="form-control form-control-solid w-250px ps-14" placeholder="Search items" data-kt-filter="search" />
            </div>
        </div>
        <div class="card-toolbar">
            <button type="button" class="btn btn-light-primary" id="btn_export_item_stocks">Export Excel</button>
        </div>
    </div>
    <div class="card-body py-6">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5" id="item_stocks_table">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                        <th>ID</th>
                        <th>SKU</th>
                        <th>Nama</th>
                        <th class="text-end">Stok {{ $defaultWarehouseLabel ?? 'Gudang Besar' }}</th>
                        <th class="text-end">Safety {{ $defaultWarehouseLabel ?? 'Gudang Besar' }}</th>
                        <th class="text-end">Stok {{ $displayWarehouseLabel ?? 'Gudang Display' }}</th>
                        <th class="text-end">Safety {{ $displayWarehouseLabel ?? 'Gudang Display' }}</th>
                        <th class="text-end">Stok {{ $damagedWarehouseLabel ?? 'Gudang Rusak' }}</th>
                        <th class="text-end">Total Stok Baik</th>
                        <th class="text-end">Total Fisik</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modal_safety_stock" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-550px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bolder">Safety Stock per Gudang</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <span class="svg-icon svg-icon-1">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black" />
                            <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black" />
                        </svg>
                    </span>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <form class="form" id="safety_stock_form">
                    @csrf
                    <input type="hidden" name="item_id" id="safety_item_id" />
                    <div class="mb-6">
                        <div class="fw-bold">Item</div>
                        <div id="safety_item_label" class="text-muted">-</div>
                    </div>
                    <div class="fv-row mb-6">
                        <label class="fs-6 fw-bold form-label mb-2">Safety {{ $defaultWarehouseLabel ?? 'Gudang Besar' }}</label>
                        <input type="number" min="0" class="form-control form-control-solid" name="safety_main" id="safety_main" />
                        <div class="form-text text-muted">Kosongkan untuk gunakan safety default item.</div>
                    </div>
                    <div class="fv-row mb-6">
                        <label class="fs-6 fw-bold form-label mb-2">Safety {{ $displayWarehouseLabel ?? 'Gudang Display' }}</label>
                        <input type="number" min="0" class="form-control form-control-solid" name="safety_display" id="safety_display" />
                        <div class="form-text text-muted">Kosongkan untuk gunakan safety default item.</div>
                    </div>
                    <div class="text-end pt-3">
                        <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">
                            <span class="indicator-label">Simpan</span>
                            <span class="indicator-progress">Please wait...
                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const dataUrl = '{{ route('admin.inventory.item-stocks.data') }}';
    const exportUrl = '{{ route('admin.inventory.item-stocks.export') }}';
    const updateSafetyUrl = '{{ $updateSafetyUrl ?? '' }}';
    const csrfToken = '{{ csrf_token() }}';

    document.addEventListener('DOMContentLoaded', () => {
        const tableEl = $('#item_stocks_table');
        const searchInput = document.querySelector('[data-kt-filter="search"]');
        const exportBtn = document.getElementById('btn_export_item_stocks');
        const safetyModalEl = document.getElementById('modal_safety_stock');
        const safetyModal = safetyModalEl ? new bootstrap.Modal(safetyModalEl) : null;
        const safetyForm = document.getElementById('safety_stock_form');
        const safetyItemId = document.getElementById('safety_item_id');
        const safetyItemLabel = document.getElementById('safety_item_label');
        const safetyMain = document.getElementById('safety_main');
        const safetyDisplay = document.getElementById('safety_display');

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
                dataSrc: 'data',
                data: function(params) {
                    params.q = searchInput?.value || '';
                }
            },
            columns: [
                { data: 'id' },
                { data: 'sku' },
                { data: 'name' },
                { data: 'stock_main', className: 'text-end', render: (data) => data ?? 0 },
                { data: 'safety_main', className: 'text-end', render: (data) => data ?? 0 },
                { data: 'stock_display', className: 'text-end', render: (data) => data ?? 0 },
                { data: 'safety_display', className: 'text-end', render: (data) => data ?? 0 },
                { data: 'stock_damaged', className: 'text-end', render: (data) => data ?? 0 },
                { data: 'stock_good_total', className: 'text-end', render: (data) => data ?? 0 },
                { data: 'stock_total', className: 'text-end', render: (data) => data ?? 0 },
                { data: 'id', orderable:false, searchable:false, className: 'text-end', render: (data, type, row) => {
                    return `<button type="button" class="btn btn-light-primary btn-sm btn-safety" data-id="${data}" data-sku="${row.sku}" data-name="${row.name}" data-safety-main="${row.safety_main_raw ?? ''}" data-safety-display="${row.safety_display_raw ?? ''}" data-safety-base="${row.safety_base ?? 0}">Set Safety</button>`;
                }},
            ]
        });

        const reloadTable = () => dt.ajax.reload();
        searchInput?.addEventListener('keyup', reloadTable);
        exportBtn?.addEventListener('click', () => {
            const q = searchInput?.value?.trim() || '';
            const url = q ? `${exportUrl}?q=${encodeURIComponent(q)}` : exportUrl;
            window.location.href = url;
        });

        tableEl.on('click', '.btn-safety', function() {
            const id = this.getAttribute('data-id');
            const sku = this.getAttribute('data-sku') || '';
            const name = this.getAttribute('data-name') || '';
            const mainRaw = this.getAttribute('data-safety-main');
            const displayRaw = this.getAttribute('data-safety-display');
            const base = this.getAttribute('data-safety-base') || 0;

            if (safetyItemId) safetyItemId.value = id || '';
            if (safetyItemLabel) safetyItemLabel.textContent = `${sku} - ${name}`.trim();
            if (safetyMain) safetyMain.value = mainRaw !== null && mainRaw !== '' ? mainRaw : '';
            if (safetyDisplay) safetyDisplay.value = displayRaw !== null && displayRaw !== '' ? displayRaw : '';
            if (safetyMain) safetyMain.placeholder = `Default: ${base}`;
            if (safetyDisplay) safetyDisplay.placeholder = `Default: ${base}`;
            safetyModal?.show();
        });

        safetyForm?.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (!updateSafetyUrl) return;
            const formData = new FormData(safetyForm);
            try {
                const res = await fetch(updateSafetyUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: formData,
                });
                const text = await res.text();
                let json;
                try { json = JSON.parse(text); } catch (err) {
                    if (typeof Swal !== 'undefined') Swal.fire('Error', 'Respons server tidak valid', 'error');
                    return;
                }
                if (!res.ok) {
                    const msg = json?.message || 'Gagal menyimpan';
                    if (typeof Swal !== 'undefined') Swal.fire('Error', msg, 'error');
                    return;
                }
                if (typeof Swal !== 'undefined') Swal.fire('Berhasil', json.message || 'Berhasil', 'success');
                safetyModal?.hide();
                reloadTable();
            } catch (err) {
                if (typeof Swal !== 'undefined') Swal.fire('Error', 'Gagal menyimpan', 'error');
            }
        });
    });
</script>
@endpush
