@extends('layouts.admin')

@section('title', 'Resep Rework')
@section('page_title', 'Resep Rework')

@php
    use App\Support\Permission as Perm;
    $canCreate = Perm::can(auth()->user(), 'admin.inventory.rework-recipes.index', 'create');
    $canUpdate = Perm::can(auth()->user(), 'admin.inventory.rework-recipes.index', 'update');
    $canDelete = Perm::can(auth()->user(), 'admin.inventory.rework-recipes.index', 'delete');
@endphp

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
                <input type="text" class="form-control form-control-solid w-250px ps-14" placeholder="Search" data-kt-filter="search" />
            </div>
        </div>
        <div class="card-toolbar">
            @if($canCreate)
                <button type="button" class="btn btn-primary" id="btn_open_recipe" data-bs-toggle="modal" data-bs-target="#modal_rework_recipe">Tambah</button>
            @endif
        </div>
    </div>
    <div class="card-body py-6">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5" id="rework_recipes_table">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                        <th>ID</th>
                        <th>Kode</th>
                        <th>Nama</th>
                        <th>Status</th>
                        <th>Gudang Hasil</th>
                        <th>Input</th>
                        <th>Output</th>
                        <th>Catatan</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modal_rework_recipe" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-1000px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bolder" id="recipe_modal_title">Tambah Resep Rework</h2>
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
                <form class="form" id="rework_recipe_form">
                    @csrf
                    <div class="row g-3 mb-6">
                        <div class="col-md-6">
                            <label class="required fs-6 fw-bold form-label mb-2">Nama Resep</label>
                            <input type="text" class="form-control form-control-solid" name="name" id="recipe_name" required />
                            <div class="invalid-feedback" id="error_name"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="fs-6 fw-bold form-label mb-2">Gudang Hasil Default</label>
                            <select class="form-select form-select-solid" name="target_warehouse_id" id="recipe_target_warehouse_id">
                                <option value="">Pilih gudang hasil</option>
                                @foreach(($targetWarehouses ?? []) as $warehouse)
                                    <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback" id="error_target_warehouse_id"></div>
                        </div>
                    </div>

                    <div class="mb-6">
                        <label class="form-check form-switch form-check-custom form-check-solid">
                            <input class="form-check-input" type="checkbox" name="is_active" id="recipe_is_active" checked />
                            <span class="form-check-label fw-bold text-gray-700">Resep aktif</span>
                        </label>
                    </div>

                    <div class="separator separator-dashed my-8"></div>

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <div class="fw-bolder fs-5">Input BOM</div>
                            <div class="text-muted fs-7">Daftar item rusak yang harus tersedia untuk 1 batch recipe.</div>
                        </div>
                        <button type="button" class="btn btn-light" id="btn_add_recipe_input">Tambah Input</button>
                    </div>
                    <div id="recipe_input_items_container"></div>
                    <div class="invalid-feedback d-block" id="error_input_items"></div>

                    <div class="separator separator-dashed my-8"></div>

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <div class="fw-bolder fs-5">Output BOM</div>
                            <div class="text-muted fs-7">Daftar item hasil yang dihasilkan dari 1 batch recipe.</div>
                        </div>
                        <button type="button" class="btn btn-light" id="btn_add_recipe_output">Tambah Output</button>
                    </div>
                    <div id="recipe_output_items_container"></div>
                    <div class="invalid-feedback d-block" id="error_output_items"></div>

                    <div class="fv-row mt-8">
                        <label class="fs-6 fw-bold form-label mb-2">Catatan</label>
                        <textarea class="form-control form-control-solid" name="note" id="recipe_note" rows="3"></textarea>
                        <div class="invalid-feedback" id="error_note"></div>
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
    const showUrlTpl = '{{ route('admin.inventory.rework-recipes.show', ':id') }}';
    const updateUrlTpl = '{{ route('admin.inventory.rework-recipes.update', ':id') }}';
    const deleteUrlTpl = '{{ route('admin.inventory.rework-recipes.destroy', ':id') }}';
    const csrfToken = '{{ csrf_token() }}';
    const canUpdate = {{ $canUpdate ? 'true' : 'false' }};
    const canDelete = {{ $canDelete ? 'true' : 'false' }};
    const itemOptionsHtml = `@foreach($items as $item)<option value="{{ $item->id }}">{{ $item->sku }} - {{ $item->name }}</option>@endforeach`;
    const defaultTargetWarehouseId = {{ isset($defaultTargetWarehouseId) ? (int) $defaultTargetWarehouseId : 'null' }};

    document.addEventListener('DOMContentLoaded', () => {
        const tableEl = $('#rework_recipes_table');
        const searchInput = document.querySelector('[data-kt-filter="search"]');
        const form = document.getElementById('rework_recipe_form');
        const modalEl = document.getElementById('modal_rework_recipe');
        const modal = modalEl ? new bootstrap.Modal(modalEl) : null;
        const inputContainer = document.getElementById('recipe_input_items_container');
        const outputContainer = document.getElementById('recipe_output_items_container');
        const addInputBtn = document.getElementById('btn_add_recipe_input');
        const addOutputBtn = document.getElementById('btn_add_recipe_output');
        const openBtn = document.getElementById('btn_open_recipe');
        const modalTitle = document.getElementById('recipe_modal_title');
        const targetWarehouseEl = document.getElementById('recipe_target_warehouse_id');
        const activeEl = document.getElementById('recipe_is_active');

        const statusLabel = (status) => {
            if (status === 'active') return '<span class="badge badge-light-success">Aktif</span>';
            return '<span class="badge badge-light-danger">Nonaktif</span>';
        };

        const clearErrors = () => {
            ['error_name','error_target_warehouse_id','error_input_items','error_output_items','error_note'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.textContent = '';
            });
            inputContainer?.querySelectorAll('[data-error-for]').forEach(el => { el.textContent = ''; });
            outputContainer?.querySelectorAll('[data-error-for]').forEach(el => { el.textContent = ''; });
            inputContainer?.querySelectorAll('.recipe-item-select.is-invalid').forEach(el => el.classList.remove('is-invalid'));
            outputContainer?.querySelectorAll('.recipe-item-select.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        };

        const renumberRows = () => {
            inputContainer.querySelectorAll('.recipe-input-row').forEach((row, idx) => {
                row.querySelectorAll('[data-name]').forEach((el) => {
                    el.name = `input_items[${idx}][${el.getAttribute('data-name')}]`;
                });
            });
            outputContainer.querySelectorAll('.recipe-output-row').forEach((row, idx) => {
                row.querySelectorAll('[data-name]').forEach((el) => {
                    el.name = `output_items[${idx}][${el.getAttribute('data-name')}]`;
                });
            });
        };

        const initSelect2 = (selectEl) => {
            if (!selectEl || typeof $ === 'undefined' || !$.fn.select2) return;
            $(selectEl).select2({
                placeholder: 'Pilih item',
                allowClear: true,
                width: '100%',
                dropdownParent: modalEl,
                minimumResultsForSearch: 0,
            }).on('select2:opening select2:closing select2:close', function(e) { e.stopPropagation(); });
        };

        const validateUniqueByContainer = (container, message) => {
            const rows = Array.from(container.querySelectorAll('.recipe-row'));
            const counts = {};
            rows.forEach((row) => {
                const val = row.querySelector('.recipe-item-select')?.value;
                if (val) counts[val] = (counts[val] || 0) + 1;
            });
            let hasDuplicate = false;
            rows.forEach((row) => {
                const selectEl = row.querySelector('.recipe-item-select');
                const errEl = row.querySelector('[data-error-for="item_id"]');
                const val = selectEl?.value;
                if (selectEl && val && counts[val] > 1) {
                    hasDuplicate = true;
                    selectEl.classList.add('is-invalid');
                    if (errEl) errEl.textContent = message;
                } else {
                    selectEl?.classList.remove('is-invalid');
                    if (errEl && errEl.textContent === message) errEl.textContent = '';
                }
            });
            return !hasDuplicate;
        };

        const validateUniqueInputs = () => validateUniqueByContainer(inputContainer, 'Item input tidak boleh duplikat');
        const validateUniqueOutputs = () => validateUniqueByContainer(outputContainer, 'Item output tidak boleh duplikat');

        const createItemRow = (container, type, data = {}) => {
            const row = document.createElement('div');
            row.className = `row g-3 align-items-end mb-4 recipe-row ${type === 'input' ? 'recipe-input-row' : 'recipe-output-row'}`;
            row.innerHTML = `
                <div class="col-md-6">
                    <label class="required fs-6 fw-bold form-label mb-2">Item</label>
                    <select class="form-select form-select-solid recipe-item-select" data-name="item_id" required>
                        <option value=""></option>
                        ${itemOptionsHtml}
                    </select>
                    <div class="invalid-feedback" data-error-for="item_id"></div>
                </div>
                <div class="col-md-2">
                    <label class="required fs-6 fw-bold form-label mb-2">Qty</label>
                    <input type="number" min="1" class="form-control form-control-solid" data-name="qty" required />
                    <div class="invalid-feedback" data-error-for="qty"></div>
                </div>
                <div class="col-md-3">
                    <label class="fs-6 fw-bold form-label mb-2">Catatan</label>
                    <input type="text" class="form-control form-control-solid" data-name="note" />
                </div>
                <div class="col-md-1 text-end">
                    <button type="button" class="btn btn-light btn-sm btn-remove-recipe-item">Hapus</button>
                </div>
            `;
            container.appendChild(row);
            const selectEl = row.querySelector('.recipe-item-select');
            if (data.item_id) selectEl.value = String(data.item_id);
            const qtyEl = row.querySelector('input[data-name="qty"]');
            if (qtyEl) qtyEl.value = data.qty ?? '';
            const noteEl = row.querySelector('input[data-name="note"]');
            if (noteEl) noteEl.value = data.note ?? '';
            initSelect2(selectEl);
            renumberRows();
            if (type === 'input') validateUniqueInputs();
            if (type === 'output') validateUniqueOutputs();
        };

        const resetForm = () => {
            form?.reset();
            form.dataset.editId = '';
            if (modalTitle) modalTitle.textContent = 'Tambah Resep Rework';
            if (targetWarehouseEl) {
                targetWarehouseEl.value = defaultTargetWarehouseId ? String(defaultTargetWarehouseId) : '';
                if (typeof $ !== 'undefined' && $(targetWarehouseEl).data('select2')) {
                    $(targetWarehouseEl).val(targetWarehouseEl.value || null).trigger('change.select2');
                }
            }
            if (activeEl) activeEl.checked = true;
            inputContainer.innerHTML = '';
            outputContainer.innerHTML = '';
            clearErrors();
            createItemRow(inputContainer, 'input');
            createItemRow(outputContainer, 'output');
        };

        initSelect2(targetWarehouseEl);
        addInputBtn?.addEventListener('click', () => createItemRow(inputContainer, 'input'));
        addOutputBtn?.addEventListener('click', () => createItemRow(outputContainer, 'output'));
        openBtn?.addEventListener('click', resetForm);

        inputContainer?.addEventListener('change', (e) => {
            if (e.target.matches('.recipe-item-select')) validateUniqueInputs();
        });
        outputContainer?.addEventListener('change', (e) => {
            if (e.target.matches('.recipe-item-select')) validateUniqueOutputs();
        });

        const handleRemove = (container, type, event) => {
            const btn = event.target.closest('.btn-remove-recipe-item');
            if (!btn) return;
            const row = btn.closest('.recipe-row');
            if (row) row.remove();
            if (container.querySelectorAll('.recipe-row').length === 0) {
                createItemRow(container, type);
            } else {
                renumberRows();
            }
            if (type === 'input') validateUniqueInputs();
            if (type === 'output') validateUniqueOutputs();
        };
        inputContainer?.addEventListener('click', (e) => handleRemove(inputContainer, 'input', e));
        outputContainer?.addEventListener('click', (e) => handleRemove(outputContainer, 'output', e));

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
                data: function(params) { params.q = searchInput?.value || ''; }
            },
            columns: [
                { data: 'id' },
                { data: 'code' },
                { data: 'name' },
                { data: 'status', orderable: false, searchable: false, render: (data) => statusLabel(data) },
                { data: 'target_warehouse' },
                { data: 'inputs' },
                { data: 'outputs' },
                { data: 'note' },
                { data: 'id', orderable:false, searchable:false, className:'text-end', render: (data) => {
                    const editItem = canUpdate ? `<div class="menu-item px-3"><a href="#" class="menu-link px-3 btn-edit" data-id="${data}">Edit</a></div>` : '';
                    const delItem = canDelete ? `<div class="menu-item px-3"><a href="#" class="menu-link px-3 text-danger btn-delete" data-id="${data}">Hapus</a></div>` : '';
                    const actions = `${editItem}${delItem}`;
                    if (!actions) return '';
                    return `
                        <div class="text-end">
                            <a href="#" class="btn btn-sm btn-light btn-active-light-primary" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                Actions
                                <span class="svg-icon svg-icon-5 m-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                        <path d="M11.4343 12.7344L7.25 8.55005C6.83579 8.13583 6.16421 8.13584 5.75 8.55005C5.33579 8.96426 5.33579 9.63583 5.75 10.05L11.2929 15.5929C11.6834 15.9835 12.3166 15.9835 12.7071 15.5929L18.25 10.05C18.6642 9.63584 18.6642 8.96426 18.25 8.55005C17.8358 8.13584 17.1642 8.13584 16.75 8.55005L12.5657 12.7344C12.2533 13.0468 11.7467 13.0468 11.4343 12.7344Z" fill="black"></path>
                                    </svg>
                                </span>
                            </a>
                            <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-600 menu-state-bg-light-primary fw-bold fs-7 w-175px py-3" data-kt-menu="true">
                                ${actions}
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

        tableEl.on('click', '.btn-edit', async function(e) {
            e.preventDefault();
            const id = this.getAttribute('data-id');
            if (!id) return;
            try {
                const res = await fetch(showUrlTpl.replace(':id', id), { headers: { 'Accept': 'application/json' } });
                const json = await res.json();
                if (!res.ok) {
                    if (typeof Swal !== 'undefined') Swal.fire('Error', json.message || 'Gagal memuat data', 'error');
                    return;
                }

                form.dataset.editId = id;
                if (modalTitle) modalTitle.textContent = `Edit ${json.code || ''}`.trim();
                document.getElementById('recipe_name').value = json.name || '';
                if (targetWarehouseEl) {
                    targetWarehouseEl.value = json.target_warehouse_id ? String(json.target_warehouse_id) : '';
                    if (typeof $ !== 'undefined' && $(targetWarehouseEl).data('select2')) {
                        $(targetWarehouseEl).val(targetWarehouseEl.value || null).trigger('change.select2');
                    }
                }
                if (activeEl) activeEl.checked = !!json.is_active;
                document.getElementById('recipe_note').value = json.note || '';
                inputContainer.innerHTML = '';
                outputContainer.innerHTML = '';
                (json.input_items || []).forEach((row) => createItemRow(inputContainer, 'input', row));
                (json.output_items || []).forEach((row) => createItemRow(outputContainer, 'output', row));
                if ((json.input_items || []).length === 0) createItemRow(inputContainer, 'input');
                if ((json.output_items || []).length === 0) createItemRow(outputContainer, 'output');
                clearErrors();
                validateUniqueInputs();
                validateUniqueOutputs();
                modal?.show();
            } catch (err) {
                if (typeof Swal !== 'undefined') Swal.fire('Error', 'Gagal memuat data', 'error');
            }
        });

        tableEl.on('click', '.btn-delete', async function(e) {
            e.preventDefault();
            const id = this.getAttribute('data-id');
            if (!id) return;
            let confirmed = true;
            if (typeof Swal !== 'undefined') {
                const res = await Swal.fire({
                    title: 'Apakah Anda yakin?',
                    text: 'Resep rework akan dihapus',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Hapus',
                    cancelButtonText: 'Batal',
                    buttonsStyling: false,
                    customClass: {
                        confirmButton: 'btn btn-danger',
                        cancelButton: 'btn btn-light'
                    }
                });
                confirmed = res.isConfirmed;
            }
            if (!confirmed) return;

            try {
                const res = await fetch(deleteUrlTpl.replace(':id', id), {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'Accept': 'application/json',
                    },
                    body: new URLSearchParams({ _method: 'DELETE' }),
                });
                const text = await res.text();
                let json;
                try { json = JSON.parse(text); } catch (err) {
                    if (typeof Swal !== 'undefined') Swal.fire('Error', 'Respons server tidak valid', 'error');
                    return;
                }
                if (!res.ok) {
                    if (typeof Swal !== 'undefined') Swal.fire('Error', json.message || 'Gagal menghapus', 'error');
                    return;
                }
                if (typeof Swal !== 'undefined') Swal.fire('Berhasil', json.message || 'Berhasil', 'success');
                reloadTable();
            } catch (err) {
                if (typeof Swal !== 'undefined') Swal.fire('Error', 'Gagal menghapus', 'error');
            }
        });

        form?.addEventListener('submit', async (e) => {
            e.preventDefault();
            clearErrors();
            if (!validateUniqueInputs()) {
                if (typeof Swal !== 'undefined') Swal.fire('Error', 'Item input tidak boleh duplikat', 'error');
                return;
            }
            if (!validateUniqueOutputs()) {
                if (typeof Swal !== 'undefined') Swal.fire('Error', 'Item output tidak boleh duplikat', 'error');
                return;
            }

            const isEdit = !!form.dataset.editId;
            const url = isEdit ? updateUrlTpl.replace(':id', form.dataset.editId) : storeUrl;
            const formData = new FormData(form);
            if (isEdit) formData.append('_method', 'PUT');
            if (!activeEl?.checked) formData.set('is_active', '0');
            try {
                const res = await fetch(url, {
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
                    if (json?.errors) {
                        const unhandled = [];
                        Object.entries(json.errors).forEach(([key, msgs]) => {
                            if (key.startsWith('input_items.')) {
                                const parts = key.split('.');
                                const idx = parseInt(parts[1], 10);
                                const field = parts[2];
                                const row = inputContainer.querySelectorAll('.recipe-input-row')[idx];
                                const errEl = row ? row.querySelector(`[data-error-for="${field}"]`) : null;
                                if (errEl) errEl.textContent = msgs.join(', ');
                                else unhandled.push(msgs.join(', '));
                            } else if (key.startsWith('output_items.')) {
                                const parts = key.split('.');
                                const idx = parseInt(parts[1], 10);
                                const field = parts[2];
                                const row = outputContainer.querySelectorAll('.recipe-output-row')[idx];
                                const errEl = row ? row.querySelector(`[data-error-for="${field}"]`) : null;
                                if (errEl) errEl.textContent = msgs.join(', ');
                                else unhandled.push(msgs.join(', '));
                            } else {
                                const errEl = document.getElementById(`error_${key}`);
                                if (errEl) errEl.textContent = msgs.join(', ');
                                else unhandled.push(msgs.join(', '));
                            }
                        });
                        if (unhandled.length && typeof Swal !== 'undefined') {
                            Swal.fire('Error', unhandled.join(', '), 'error');
                        }
                    } else if (typeof Swal !== 'undefined') {
                        Swal.fire('Error', json.message || 'Gagal menyimpan', 'error');
                    }
                    return;
                }
                if (typeof Swal !== 'undefined') Swal.fire('Berhasil', json.message || 'Berhasil', 'success');
                modal?.hide();
                reloadTable();
            } catch (err) {
                if (typeof Swal !== 'undefined') Swal.fire('Error', 'Gagal menyimpan', 'error');
            }
        });

        resetForm();
    });
</script>
@endpush

@include('layouts.partials.form-submit-confirmation')
