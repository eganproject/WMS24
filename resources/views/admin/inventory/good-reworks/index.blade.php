@extends('layouts.admin')

@section('title', 'Rework Barang Bagus')
@section('page_title', 'Rework Barang Bagus')

@php
    use App\Support\Permission as Perm;
    $canCreate = Perm::can(auth()->user(), 'admin.inventory.good-reworks.index', 'create');
    $canUpdate = Perm::can(auth()->user(), 'admin.inventory.good-reworks.index', 'update');
    $canDelete = Perm::can(auth()->user(), 'admin.inventory.good-reworks.index', 'delete');
@endphp

@section('content')
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div class="d-flex align-items-center position-relative my-1 me-3">
                <span class="svg-icon svg-icon-1 position-absolute ms-6">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <rect opacity="0.5" x="17.0365" y="15.1223" width="8.15546" height="2" rx="1" transform="rotate(45 17.0365 15.1223)" fill="black" />
                        <path d="M11 19C6.55556 19 3 15.4444 3 11C3 6.55556 6.55556 3 11 3C15.4444 3 19 6.55556 19 11C19 15.4444 15.4444 19 11 19ZM11 5C7.53333 5 5 7.53333 5 11C5 14.4667 7.53333 17 11 17C14.4667 17 17 14.4667 17 11C17 7.53333 14.4667 5 11 5Z" fill="black" />
                    </svg>
                </span>
                <input type="text" class="form-control form-control-solid w-250px ps-14" placeholder="Search" data-kt-filter="search" />
            </div>
            <select class="form-select form-select-solid w-175px my-1" id="filter_status">
                <option value="">Semua status</option>
                <option value="pending">Menunggu Approval</option>
                <option value="approved">Disetujui</option>
            </select>
        </div>
        <div class="card-toolbar">
            @if($canCreate)
                <button type="button" class="btn btn-primary" id="btn_open_rework" data-bs-toggle="modal" data-bs-target="#modal_good_rework">Tambah</button>
            @endif
        </div>
    </div>
    <div class="card-body py-6">
        <div class="text-muted fs-7 mb-4">Rework dari stok bagus: bahan keluar dari gudang asal dan hasil masuk ke gudang hasil saat disetujui. Untuk barang rusak tetap gunakan Alokasi Barang Rusak.</div>
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5" id="good_reworks_table">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                        <th>ID</th>
                        <th>Kode</th>
                        <th>Tanggal</th>
                        <th>Status</th>
                        <th>Gudang Asal</th>
                        <th>Gudang Hasil</th>
                        <th>Bahan</th>
                        <th>Hasil</th>
                        <th>Resep</th>
                        <th>Catatan</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modal_good_rework" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable mw-1000px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bolder" id="rework_modal_title">Tambah Rework Barang Bagus</h2>
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
                <form class="form" id="good_rework_form">
                    @csrf
                    <div class="row g-3 mb-6">
                        <div class="col-md-4">
                            <label class="required fs-6 fw-bold form-label mb-2">Tanggal</label>
                            <input type="datetime-local" class="form-control form-control-solid" name="transacted_at" id="rework_transacted_at" required />
                            <div class="invalid-feedback d-block" id="error_transacted_at"></div>
                        </div>
                        <div class="col-md-4">
                            <label class="required fs-6 fw-bold form-label mb-2">Gudang Asal (bahan)</label>
                            <select class="form-select form-select-solid" name="source_warehouse_id" id="rework_source_warehouse_id" required>
                                @foreach($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback d-block" id="error_source_warehouse_id"></div>
                        </div>
                        <div class="col-md-4">
                            <label class="required fs-6 fw-bold form-label mb-2">Gudang Hasil</label>
                            <select class="form-select form-select-solid" name="target_warehouse_id" id="rework_target_warehouse_id" required>
                                @foreach($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback d-block" id="error_target_warehouse_id"></div>
                        </div>
                    </div>

                    <div class="row g-3 mb-6 align-items-end">
                        <div class="col-md-6">
                            <label class="fs-6 fw-bold form-label mb-2">Resep Rework (opsional)</label>
                            <select class="form-select form-select-solid" name="recipe_id" id="rework_recipe_id">
                                <option value="">Tanpa resep (isi manual)</option>
                                @foreach($recipes as $recipe)
                                    <option value="{{ $recipe['id'] }}">{{ $recipe['label'] }}</option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback d-block" id="error_recipe_id"></div>
                        </div>
                        <div class="col-md-3">
                            <label class="fs-6 fw-bold form-label mb-2">Jumlah Batch</label>
                            <input type="number" min="1" class="form-control form-control-solid" name="recipe_multiplier" id="rework_recipe_multiplier" value="1" />
                            <div class="invalid-feedback d-block" id="error_recipe_multiplier"></div>
                        </div>
                        <div class="col-md-3">
                            <button type="button" class="btn btn-light-primary w-100" id="btn_apply_recipe">Terapkan Resep</button>
                        </div>
                    </div>

                    <div class="fv-row mb-6">
                        <label class="fs-6 fw-bold form-label mb-2">No. Referensi</label>
                        <input type="text" class="form-control form-control-solid" name="source_ref" id="rework_source_ref" maxlength="100" placeholder="Contoh: nomor pekerjaan rework" />
                        <div class="invalid-feedback d-block" id="error_source_ref"></div>
                    </div>

                    <div class="separator separator-dashed my-8"></div>

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <div class="fw-bolder fs-5">Bahan</div>
                            <div class="text-muted fs-7">Item yang keluar dari gudang asal.</div>
                        </div>
                        <button type="button" class="btn btn-light" id="btn_add_rework_input">Tambah Bahan</button>
                    </div>
                    <div class="alert alert-light-warning py-3 fs-7 d-none" id="koli_hint_input">Gudang asal adalah Gudang Besar: qty bahan wajib kelipatan isi/koli.</div>
                    <div id="rework_input_items_container"></div>
                    <div class="invalid-feedback d-block" id="error_input_items"></div>

                    <div class="separator separator-dashed my-8"></div>

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <div class="fw-bolder fs-5">Hasil</div>
                            <div class="text-muted fs-7">Item yang masuk ke gudang hasil.</div>
                        </div>
                        <button type="button" class="btn btn-light" id="btn_add_rework_output">Tambah Hasil</button>
                    </div>
                    <div class="alert alert-light-warning py-3 fs-7 d-none" id="koli_hint_output">Gudang hasil adalah Gudang Besar: qty hasil wajib kelipatan isi/koli.</div>
                    <div id="rework_output_items_container"></div>
                    <div class="invalid-feedback d-block" id="error_output_items"></div>

                    <div class="fv-row mt-8">
                        <label class="fs-6 fw-bold form-label mb-2">Catatan</label>
                        <textarea class="form-control form-control-solid" name="note" id="rework_note" rows="3"></textarea>
                        <div class="invalid-feedback d-block" id="error_note"></div>
                    </div>

                    <div class="text-end pt-10">
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
    const dataUrl = '{{ $dataUrl }}';
    const storeUrl = '{{ $storeUrl }}';
    const showUrlTpl = '{{ route('admin.inventory.good-reworks.show', ':id') }}';
    const updateUrlTpl = '{{ route('admin.inventory.good-reworks.update', ':id') }}';
    const deleteUrlTpl = '{{ route('admin.inventory.good-reworks.destroy', ':id') }}';
    const approveUrlTpl = '{{ route('admin.inventory.good-reworks.approve', ':id') }}';
    const csrfToken = '{{ csrf_token() }}';
    const canUpdate = {{ $canUpdate ? 'true' : 'false' }};
    const canDelete = {{ $canDelete ? 'true' : 'false' }};
    const itemOptionsHtml = `@foreach($items as $item)<option value="{{ $item->id }}" data-koli="{{ (int) $item->koli_qty }}">{{ $item->sku }} - {{ $item->name }}</option>@endforeach`;
    const mainWarehouseId = {{ (int) ($mainWarehouseId ?? 0) }};
    const recipes = @json($recipes);
    const defaultWarehouseId = {{ (int) ($defaultWarehouseId ?? 0) }};

    document.addEventListener('DOMContentLoaded', () => {
        const tableEl = $('#good_reworks_table');
        const searchInput = document.querySelector('[data-kt-filter="search"]');
        const statusFilter = document.getElementById('filter_status');
        const form = document.getElementById('good_rework_form');
        const modalEl = document.getElementById('modal_good_rework');
        const modal = modalEl ? new bootstrap.Modal(modalEl) : null;
        const modalContentEl = modalEl?.querySelector('.modal-content') || modalEl;
        const inputContainer = document.getElementById('rework_input_items_container');
        const outputContainer = document.getElementById('rework_output_items_container');
        const modalTitle = document.getElementById('rework_modal_title');
        const sourceWarehouseEl = document.getElementById('rework_source_warehouse_id');
        const targetWarehouseEl = document.getElementById('rework_target_warehouse_id');
        const recipeEl = document.getElementById('rework_recipe_id');
        const multiplierEl = document.getElementById('rework_recipe_multiplier');

        const swal = (title, text, icon) => { if (typeof Swal !== 'undefined') Swal.fire(title, text, icon); };
        const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

        const statusLabel = (status) => status === 'approved'
            ? '<span class="badge badge-light-success">Disetujui</span>'
            : '<span class="badge badge-light-warning">Menunggu Approval</span>';

        const setSelectValue = (el, value) => {
            if (!el) return;
            el.value = value ? String(value) : '';
            if (typeof $ !== 'undefined' && $(el).data('select2')) {
                $(el).val(el.value || null).trigger('change.select2');
            }
        };

        const nowLocal = () => {
            const d = new Date();
            d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
            return d.toISOString().slice(0, 16);
        };

        const clearErrors = () => {
            form.querySelectorAll('[id^="error_"]').forEach(el => { el.textContent = ''; });
            form.querySelectorAll('[data-error-for]').forEach(el => { el.textContent = ''; });
            form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        };

        const renumberRows = () => {
            [[inputContainer, 'input_items'], [outputContainer, 'output_items']].forEach(([container, key]) => {
                container.querySelectorAll('.rework-row').forEach((row, idx) => {
                    row.querySelectorAll('[data-name]').forEach((el) => {
                        el.name = `${key}[${idx}][${el.getAttribute('data-name')}]`;
                    });
                });
            });
        };

        const initSelect2 = (selectEl, placeholder = 'Pilih item') => {
            if (!selectEl || typeof $ === 'undefined' || !$.fn.select2) return;
            $(selectEl).select2({
                placeholder,
                allowClear: true,
                width: '100%',
                dropdownParent: modalContentEl,
                minimumResultsForSearch: 0,
            }).on('select2:opening select2:closing select2:close', function(e) { e.stopPropagation(); });
        };

        const createItemRow = (container, data = {}) => {
            const row = document.createElement('div');
            row.className = 'row g-3 align-items-end mb-4 rework-row';
            row.innerHTML = `
                <div class="col-md-6">
                    <label class="required fs-6 fw-bold form-label mb-2">Item</label>
                    <select class="form-select form-select-solid rework-item-select" data-name="item_id" required>
                        <option value=""></option>
                        ${itemOptionsHtml}
                    </select>
                    <div class="invalid-feedback d-block" data-error-for="item_id"></div>
                </div>
                <div class="col-md-2">
                    <label class="required fs-6 fw-bold form-label mb-2">Qty</label>
                    <input type="number" min="1" class="form-control form-control-solid" data-name="qty" required />
                    <div class="invalid-feedback d-block" data-error-for="qty"></div>
                </div>
                <div class="col-md-3">
                    <label class="fs-6 fw-bold form-label mb-2">Catatan</label>
                    <input type="text" class="form-control form-control-solid" data-name="note" />
                </div>
                <div class="col-md-1 text-end">
                    <button type="button" class="btn btn-light btn-sm btn-remove-rework-item">Hapus</button>
                </div>
            `;
            container.appendChild(row);
            const selectEl = row.querySelector('.rework-item-select');
            if (data.item_id) selectEl.value = String(data.item_id);
            row.querySelector('input[data-name="qty"]').value = data.qty ?? '';
            row.querySelector('input[data-name="note"]').value = data.note ?? '';
            initSelect2(selectEl);
            renumberRows();
        };

        const fillRows = (container, rows) => {
            container.innerHTML = '';
            (rows || []).forEach((row) => createItemRow(container, row));
            if (!container.querySelector('.rework-row')) createItemRow(container);
        };

        const resetForm = () => {
            form.reset();
            form.dataset.editId = '';
            if (modalTitle) modalTitle.textContent = 'Tambah Rework Barang Bagus';
            document.getElementById('rework_transacted_at').value = nowLocal();
            setSelectValue(sourceWarehouseEl, defaultWarehouseId || sourceWarehouseEl.options[0]?.value);
            setSelectValue(targetWarehouseEl, defaultWarehouseId || targetWarehouseEl.options[0]?.value);
            setSelectValue(recipeEl, '');
            multiplierEl.value = 1;
            fillRows(inputContainer, []);
            fillRows(outputContainer, []);
            clearErrors();
            updateKoliHints();
        };

        const updateKoliHints = () => {
            const srcMain = String(sourceWarehouseEl.value) === String(mainWarehouseId);
            const dstMain = String(targetWarehouseEl.value) === String(mainWarehouseId);
            document.getElementById('koli_hint_input')?.classList.toggle('d-none', !srcMain);
            document.getElementById('koli_hint_output')?.classList.toggle('d-none', !dstMain);
        };
        $(sourceWarehouseEl).on('change', updateKoliHints);
        $(targetWarehouseEl).on('change', updateKoliHints);
        sourceWarehouseEl.addEventListener('change', updateKoliHints);
        targetWarehouseEl.addEventListener('change', updateKoliHints);

        initSelect2(sourceWarehouseEl, 'Pilih gudang');
        initSelect2(targetWarehouseEl, 'Pilih gudang');
        initSelect2(recipeEl, 'Tanpa resep (isi manual)');

        document.getElementById('btn_add_rework_input')?.addEventListener('click', () => createItemRow(inputContainer));
        document.getElementById('btn_add_rework_output')?.addEventListener('click', () => createItemRow(outputContainer));
        document.getElementById('btn_open_rework')?.addEventListener('click', resetForm);

        document.getElementById('btn_apply_recipe')?.addEventListener('click', () => {
            const recipe = recipes.find((r) => String(r.id) === String(recipeEl.value));
            if (!recipe) {
                swal('Info', 'Pilih resep rework terlebih dahulu.', 'info');
                return;
            }
            const multiplier = Math.max(1, parseInt(multiplierEl.value, 10) || 1);
            multiplierEl.value = multiplier;
            const scale = (rows) => (rows || []).map((row) => ({ ...row, qty: row.qty * multiplier }));
            fillRows(inputContainer, scale(recipe.input_items));
            fillRows(outputContainer, scale(recipe.output_items));
            if (recipe.target_warehouse_id) setSelectValue(targetWarehouseEl, recipe.target_warehouse_id);
            updateKoliHints();
        });

        [inputContainer, outputContainer].forEach((container) => {
            container.addEventListener('click', (e) => {
                const btn = e.target.closest('.btn-remove-rework-item');
                if (!btn) return;
                btn.closest('.rework-row')?.remove();
                if (!container.querySelector('.rework-row')) createItemRow(container);
                renumberRows();
            });
        });

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
                    params.status = statusFilter?.value || '';
                }
            },
            columns: [
                { data: 'id' },
                { data: 'code' },
                { data: 'transacted_at' },
                { data: 'status', orderable: false, render: (data) => statusLabel(data) },
                { data: 'source_warehouse', orderable: false },
                { data: 'target_warehouse', orderable: false },
                { data: 'inputs', orderable: false, render: (data) => escapeHtml(data) },
                { data: 'outputs', orderable: false, render: (data) => escapeHtml(data) },
                { data: 'recipe', orderable: false, render: (data) => escapeHtml(data) },
                { data: 'note', orderable: false, render: (data) => escapeHtml(data) },
                { data: 'id', orderable: false, searchable: false, className: 'text-end', render: (data, type, row) => {
                    const pending = row.status !== 'approved';
                    const viewItem = `<div class="menu-item px-3"><a href="#" class="menu-link px-3 btn-view" data-id="${data}">${pending && canUpdate ? 'Edit' : 'Lihat'}</a></div>`;
                    const approveItem = pending && canUpdate ? `<div class="menu-item px-3"><a href="#" class="menu-link px-3 text-success btn-approve" data-id="${data}">Approve</a></div>` : '';
                    const delItem = pending && canDelete ? `<div class="menu-item px-3"><a href="#" class="menu-link px-3 text-danger btn-delete" data-id="${data}">Hapus</a></div>` : '';
                    return `
                        <div class="text-end">
                            <a href="#" class="btn btn-sm btn-light btn-active-light-primary" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">Actions</a>
                            <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-600 menu-state-bg-light-primary fw-bold fs-7 w-175px py-3" data-kt-menu="true">
                                ${viewItem}${approveItem}${delItem}
                            </div>
                        </div>
                    `;
                }},
            ]
        });

        const refreshMenus = () => { if (window.KTMenu) KTMenu.createInstances(); };
        dt.on('draw', refreshMenus);
        refreshMenus();

        const reloadTable = () => dt.ajax.reload();
        searchInput?.addEventListener('keyup', reloadTable);
        statusFilter?.addEventListener('change', reloadTable);

        const parseJson = async (res) => {
            const text = await res.text();
            try { return JSON.parse(text); } catch (err) { return null; }
        };

        const confirmAction = async (text, confirmText, btnClass) => {
            if (typeof Swal === 'undefined') return true;
            const res = await Swal.fire({
                title: 'Apakah Anda yakin?',
                text,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: confirmText,
                cancelButtonText: 'Batal',
                buttonsStyling: false,
                customClass: { confirmButton: `btn ${btnClass}`, cancelButton: 'btn btn-light' },
            });
            return res.isConfirmed;
        };

        tableEl.on('click', '.btn-view', async function(e) {
            e.preventDefault();
            const id = this.getAttribute('data-id');
            try {
                const res = await fetch(showUrlTpl.replace(':id', id), { headers: { 'Accept': 'application/json' } });
                const json = await parseJson(res);
                if (!res.ok || !json) {
                    swal('Error', json?.message || 'Gagal memuat data', 'error');
                    return;
                }
                const readOnly = json.status === 'approved' || !canUpdate;
                form.dataset.editId = readOnly ? '' : id;
                form.dataset.readOnly = readOnly ? '1' : '';
                if (modalTitle) modalTitle.textContent = `${readOnly ? 'Detail' : 'Edit'} ${json.code || ''}`.trim();
                document.getElementById('rework_transacted_at').value = json.transacted_at || nowLocal();
                setSelectValue(sourceWarehouseEl, json.source_warehouse_id);
                setSelectValue(targetWarehouseEl, json.target_warehouse_id);
                setSelectValue(recipeEl, json.recipe_id);
                multiplierEl.value = json.recipe_multiplier || 1;
                document.getElementById('rework_source_ref').value = json.source_ref || '';
                document.getElementById('rework_note').value = json.note || '';
                fillRows(inputContainer, json.input_items);
                fillRows(outputContainer, json.output_items);
                clearErrors();
                updateKoliHints();
                form.querySelector('button[type="submit"]').classList.toggle('d-none', readOnly);
                modal?.show();
            } catch (err) {
                swal('Error', 'Gagal memuat data', 'error');
            }
        });

        document.getElementById('btn_open_rework')?.addEventListener('click', () => {
            form.dataset.readOnly = '';
            form.querySelector('button[type="submit"]').classList.remove('d-none');
        });

        const postAction = async (url, method, successFallback) => {
            const body = new URLSearchParams();
            if (method !== 'POST') body.append('_method', method);
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
                body,
            });
            const json = await parseJson(res);
            if (!res.ok || !json) {
                const firstError = json?.errors ? Object.values(json.errors).flat()[0] : null;
                swal('Error', firstError || json?.message || 'Terjadi kesalahan', 'error');
                return;
            }
            swal('Berhasil', json.message || successFallback, 'success');
            reloadTable();
        };

        tableEl.on('click', '.btn-approve', async function(e) {
            e.preventDefault();
            const id = this.getAttribute('data-id');
            if (!(await confirmAction('Stok bahan akan keluar dari gudang asal dan hasil masuk ke gudang hasil.', 'Approve', 'btn-success'))) return;
            try { await postAction(approveUrlTpl.replace(':id', id), 'POST', 'Berhasil'); } catch (err) { swal('Error', 'Gagal approve', 'error'); }
        });

        tableEl.on('click', '.btn-delete', async function(e) {
            e.preventDefault();
            const id = this.getAttribute('data-id');
            if (!(await confirmAction('Rework akan dihapus', 'Hapus', 'btn-danger'))) return;
            try { await postAction(deleteUrlTpl.replace(':id', id), 'DELETE', 'Berhasil'); } catch (err) { swal('Error', 'Gagal menghapus', 'error'); }
        });

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (form.dataset.readOnly) return;
            clearErrors();
            const isEdit = !!form.dataset.editId;
            const url = isEdit ? updateUrlTpl.replace(':id', form.dataset.editId) : storeUrl;
            const formData = new FormData(form);
            if (isEdit) formData.append('_method', 'PUT');
            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    body: formData,
                });
                const json = await parseJson(res);
                if (!json) {
                    swal('Error', 'Respons server tidak valid', 'error');
                    return;
                }
                if (!res.ok) {
                    if (json.errors) {
                        const unhandled = [];
                        Object.entries(json.errors).forEach(([key, msgs]) => {
                            const match = key.match(/^(input_items|output_items)\.(\d+)\.(\w+)$/);
                            let errEl = null;
                            if (match) {
                                const container = match[1] === 'input_items' ? inputContainer : outputContainer;
                                errEl = container.querySelectorAll('.rework-row')[parseInt(match[2], 10)]?.querySelector(`[data-error-for="${match[3]}"]`);
                            } else {
                                errEl = document.getElementById(`error_${key}`);
                            }
                            if (errEl) errEl.textContent = msgs.join(', ');
                            else unhandled.push(msgs.join(', '));
                        });
                        if (unhandled.length) swal('Error', unhandled.join(', '), 'error');
                    } else {
                        swal('Error', json.message || 'Gagal menyimpan', 'error');
                    }
                    return;
                }
                swal('Berhasil', json.message || 'Berhasil', 'success');
                modal?.hide();
                reloadTable();
            } catch (err) {
                swal('Error', 'Gagal menyimpan', 'error');
            }
        });

        resetForm();
    });
</script>
@endpush
