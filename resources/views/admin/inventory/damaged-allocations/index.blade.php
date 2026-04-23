@extends('layouts.admin')

@section('title', 'Alokasi Barang Rusak')
@section('page_title', 'Alokasi Barang Rusak')

@php
    use App\Support\Permission as Perm;
    $canCreate = Perm::can(auth()->user(), 'admin.inventory.damaged-allocations.index', 'create');
    $canUpdate = Perm::can(auth()->user(), 'admin.inventory.damaged-allocations.index', 'update');
    $canDelete = Perm::can(auth()->user(), 'admin.inventory.damaged-allocations.index', 'delete');
@endphp

@push('styles')
<style>
    #modal_damaged_allocation .invalid-feedback.d-block:empty {
        display: none !important;
    }

    #modal_damaged_allocation .select2-container .select2-selection.is-invalid {
        border-color: var(--bs-danger, #f1416c) !important;
    }
</style>
@endpush

@section('content')
<div class="card mb-6">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div class="d-flex flex-column">
                <div class="fw-bolder fs-5">Saldo Rusak Tersedia</div>
                <div class="text-muted fs-7">Ringkasan item intake yang masih bisa dialokasikan dari {{ $damagedWarehouseLabel ?? 'Gudang Rusak' }}.</div>
            </div>
        </div>
        <div class="card-toolbar">
            <span class="badge badge-light-danger">Gudang Rusak: {{ $damagedWarehouseLabel ?? 'Gudang Rusak' }}</span>
        </div>
    </div>
    <div class="card-body py-6">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5" id="damaged_source_summary_table">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                        <th>Intake</th>
                        <th>Gudang Asal</th>
                        <th>Item</th>
                        <th class="text-end">Qty Intake</th>
                        <th class="text-end">Dialokasikan</th>
                        <th class="text-end">Sisa</th>
                        <th>Tanggal Intake</th>
                    </tr>
                </thead>
                <tbody id="damaged_source_summary_body">
                    <tr>
                        <td colspan="7" class="text-muted">Memuat data...</td>
                    </tr>
                </tbody>
            </table>
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
                <input type="text" class="form-control form-control-solid w-250px ps-14" placeholder="Search" data-kt-filter="search" />
            </div>
        </div>
        <div class="card-toolbar">
            @if($canCreate)
                <button type="button" class="btn btn-primary" id="btn_open_allocation" data-bs-toggle="modal" data-bs-target="#modal_damaged_allocation">Tambah</button>
            @endif
        </div>
    </div>
    <div class="card-body py-6">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5" id="damaged_allocations_table">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                        <th>ID</th>
                        <th>Kode</th>
                        <th>Tipe</th>
                        <th>Status</th>
                        <th>Tanggal</th>
                        <th>Submit By</th>
                        <th>Input Rusak</th>
                        <th>Output</th>
                        <th>Tujuan</th>
                        <th>Catatan</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modal_damaged_allocation" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable mw-1000px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bolder" id="allocation_modal_title">Tambah Alokasi Barang Rusak</h2>
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
                <form class="form" id="damaged_allocation_form">
                    @csrf
                    <div class="row g-3 mb-6">
                        <div class="col-md-4">
                            <label class="required fs-6 fw-bold form-label mb-2">Tipe Alokasi</label>
                            <select class="form-select form-select-solid" name="type" id="allocation_type" required>
                                <option value="">Pilih tipe alokasi</option>
                                <option value="return_supplier">Retur Supplier</option>
                                <option value="disposal">Disposal</option>
                                <option value="rework">Rework SKU</option>
                            </select>
                            <div class="invalid-feedback d-block" id="error_type"></div>
                        </div>
                        <div class="col-md-4" id="supplier_field_wrap" style="display:none;">
                            <label class="required fs-6 fw-bold form-label mb-2">Supplier</label>
                            <select class="form-select form-select-solid" name="supplier_id" id="allocation_supplier_id">
                                <option value="">Pilih supplier</option>
                                @foreach(($suppliers ?? []) as $supplier)
                                    <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback d-block" id="error_supplier_id"></div>
                        </div>
                        <div class="col-md-4" id="recipe_field_wrap" style="display:none;">
                            <label class="fs-6 fw-bold form-label mb-2">Resep Rework</label>
                            <select class="form-select form-select-solid" name="recipe_id" id="allocation_recipe_id">
                                <option value="">Pilih resep rework</option>
                            </select>
                            <div class="form-text text-muted">Kosongkan bila ingin memakai mode manual legacy.</div>
                            <div class="invalid-feedback d-block" id="error_recipe_id"></div>
                        </div>
                        <div class="col-md-4" id="target_warehouse_wrap" style="display:none;">
                            <label class="required fs-6 fw-bold form-label mb-2">Gudang Hasil</label>
                            <select class="form-select form-select-solid" name="target_warehouse_id" id="allocation_target_warehouse_id">
                                <option value="">Pilih gudang hasil</option>
                                @foreach(($targetWarehouses ?? []) as $warehouse)
                                    <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback d-block" id="error_target_warehouse_id"></div>
                        </div>
                    </div>

                    <div class="row g-3 mb-6">
                        <div class="col-md-5">
                            <label class="fs-6 fw-bold form-label mb-2">Ref Alokasi</label>
                            <input type="text" class="form-control form-control-solid" name="source_ref" id="allocation_source_ref" placeholder="Contoh: BA retur, berita acara disposal, atau nomor pekerjaan rework" />
                            <div class="invalid-feedback d-block" id="error_source_ref"></div>
                        </div>
                        <div class="col-md-3" id="recipe_multiplier_wrap" style="display:none;">
                            <label class="required fs-6 fw-bold form-label mb-2">Batch Recipe</label>
                            <input type="number" min="1" class="form-control form-control-solid" name="recipe_multiplier" id="allocation_recipe_multiplier" value="1" />
                            <div class="form-text text-muted">Multiplier untuk BOM recipe.</div>
                            <div class="invalid-feedback d-block" id="error_recipe_multiplier"></div>
                        </div>
                        <div class="col-md-4">
                            <label class="required fs-6 fw-bold form-label mb-2">Tanggal</label>
                            <input type="text" class="form-control form-control-solid" name="transacted_at" id="allocation_transacted_at" placeholder="YYYY-MM-DD HH:mm" required />
                            <div class="invalid-feedback d-block" id="error_transacted_at"></div>
                        </div>
                    </div>

                    <div class="separator separator-dashed my-8"></div>

                    <div id="recipe_preview_section" class="mb-8" style="display:none;">
                        <div class="card card-bordered bg-light">
                            <div class="card-body">
                                <div class="fw-bolder fs-5 mb-2">Ringkasan Recipe</div>
                                <div class="text-muted fs-7 mb-4">Komposisi ini dipakai untuk validasi exact-match pada saat simpan dan approve.</div>
                                <div id="recipe_summary_content" class="fs-7 text-gray-700"></div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <div class="fw-bolder fs-5">Item Sumber Barang Rusak</div>
                            <div class="text-muted fs-7">Pilih item dari intake rusak yang sudah approved. Jika memakai recipe, total sumber per SKU harus tepat sama dengan kebutuhan BOM.</div>
                        </div>
                        <button type="button" class="btn btn-light" id="btn_add_source_item">Tambah Sumber</button>
                    </div>
                    <div id="allocation_source_items_container"></div>
                    <div class="invalid-feedback d-block" id="error_source_items"></div>

                    <div id="output_section" style="display:none;">
                        <div class="separator separator-dashed my-8"></div>

                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <div class="fw-bolder fs-5">Item Hasil Rework Manual</div>
                                <div class="text-muted fs-7">Bagian ini hanya dipakai untuk mode legacy tanpa recipe. Jika recipe dipilih, output akan diturunkan otomatis dari recipe.</div>
                            </div>
                            <button type="button" class="btn btn-light" id="btn_add_output_item">Tambah Output</button>
                        </div>
                        <div id="allocation_output_items_container"></div>
                        <div class="invalid-feedback d-block" id="error_output_items"></div>
                    </div>

                    <div class="fv-row mt-8">
                        <label class="fs-6 fw-bold form-label mb-2">Catatan</label>
                        <textarea class="form-control form-control-solid" name="note" id="allocation_note" rows="3"></textarea>
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
    const sourceItemsUrl = '{{ $sourceItemsUrl }}';
    const recipeOptionsUrl = '{{ $recipeOptionsUrl }}';
    const storeUrl = '{{ $storeUrl }}';
    const showUrlTpl = '{{ route('admin.inventory.damaged-allocations.show', ':id') }}';
    const updateUrlTpl = '{{ route('admin.inventory.damaged-allocations.update', ':id') }}';
    const deleteUrlTpl = '{{ route('admin.inventory.damaged-allocations.destroy', ':id') }}';
    const approveUrlTpl = '{{ route('admin.inventory.damaged-allocations.approve', ':id') }}';
    const csrfToken = '{{ csrf_token() }}';
    const canUpdate = {{ $canUpdate ? 'true' : 'false' }};
    const canDelete = {{ $canDelete ? 'true' : 'false' }};
    const itemOptionsHtml = `@foreach($items as $item)<option value="{{ $item->id }}">{{ $item->sku }} - {{ $item->name }}</option>@endforeach`;
    const defaultTargetWarehouseId = {{ isset($defaultTargetWarehouseId) ? (int) $defaultTargetWarehouseId : 'null' }};

    document.addEventListener('DOMContentLoaded', () => {
        const tableEl = $('#damaged_allocations_table');
        const summaryBody = document.getElementById('damaged_source_summary_body');
        const searchInput = document.querySelector('[data-kt-filter="search"]');
        const form = document.getElementById('damaged_allocation_form');
        const modalEl = document.getElementById('modal_damaged_allocation');
        const modal = modalEl ? new bootstrap.Modal(modalEl) : null;
        const modalContentEl = modalEl?.querySelector('.modal-content') || modalEl;
        const sourceItemsContainer = document.getElementById('allocation_source_items_container');
        const outputItemsContainer = document.getElementById('allocation_output_items_container');
        const addSourceItemBtn = document.getElementById('btn_add_source_item');
        const addOutputItemBtn = document.getElementById('btn_add_output_item');
        const openBtn = document.getElementById('btn_open_allocation');
        const modalTitle = document.getElementById('allocation_modal_title');
        const typeEl = document.getElementById('allocation_type');
        const supplierWrap = document.getElementById('supplier_field_wrap');
        const supplierEl = document.getElementById('allocation_supplier_id');
        const recipeWrap = document.getElementById('recipe_field_wrap');
        const recipeEl = document.getElementById('allocation_recipe_id');
        const recipeMultiplierWrap = document.getElementById('recipe_multiplier_wrap');
        const recipeMultiplierEl = document.getElementById('allocation_recipe_multiplier');
        const targetWarehouseWrap = document.getElementById('target_warehouse_wrap');
        const targetWarehouseEl = document.getElementById('allocation_target_warehouse_id');
        const outputSection = document.getElementById('output_section');
        const recipePreviewSection = document.getElementById('recipe_preview_section');
        const recipeSummaryContent = document.getElementById('recipe_summary_content');
        const transactedAtEl = document.getElementById('allocation_transacted_at');
        let fpTransacted = null;
        let sourceLineOptions = [];
        let recipeOptions = [];

        const formatDateTime = (date) => {
            const pad = (n) => String(n).padStart(2, '0');
            return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`;
        };

        const getJakartaNow = () => {
            const jkt = new Date(new Date().toLocaleString('en-US', { timeZone: 'Asia/Jakarta' }));
            return formatDateTime(jkt);
        };

        const statusLabel = (status) => {
            if (status === 'approved') return '<span class="badge badge-light-success">Disetujui</span>';
            return '<span class="badge badge-light-warning">Menunggu</span>';
        };

        const typeLabel = (type) => {
            if (type === 'return_supplier') return 'Retur Supplier';
            if (type === 'disposal') return 'Disposal';
            if (type === 'rework') return 'Rework SKU';
            return type || '-';
        };

        const clearErrors = () => {
            ['error_type','error_supplier_id','error_recipe_id','error_recipe_multiplier','error_target_warehouse_id','error_source_ref','error_transacted_at','error_source_items','error_output_items','error_note'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.textContent = '';
            });
            sourceItemsContainer?.querySelectorAll('[data-error-for]').forEach(el => { el.textContent = ''; });
            outputItemsContainer?.querySelectorAll('[data-error-for]').forEach(el => { el.textContent = ''; });
            form?.querySelectorAll('.is-invalid').forEach((el) => {
                el.classList.remove('is-invalid');
            });
            modalEl?.querySelectorAll('.select2-selection.is-invalid').forEach((el) => {
                el.classList.remove('is-invalid');
            });
        };

        const setFieldInvalid = (fieldEl) => {
            if (!fieldEl) return;
            fieldEl.classList.add('is-invalid');
            if (typeof $ !== 'undefined' && $.fn.select2 && $(fieldEl).data('select2')) {
                $(fieldEl).next('.select2-container').find('.select2-selection').addClass('is-invalid');
            }
        };

        const clearFieldInvalid = (fieldEl) => {
            if (!fieldEl) return;
            fieldEl.classList.remove('is-invalid');
            if (typeof $ !== 'undefined' && $.fn.select2 && $(fieldEl).data('select2')) {
                $(fieldEl).next('.select2-container').find('.select2-selection').removeClass('is-invalid');
            }
        };

        const getTopLevelField = (key) => {
            const fieldMap = {
                type: typeEl,
                supplier_id: supplierEl,
                recipe_id: recipeEl,
                recipe_multiplier: recipeMultiplierEl,
                target_warehouse_id: targetWarehouseEl,
                source_ref: document.getElementById('allocation_source_ref'),
                transacted_at: transactedAtEl,
                note: document.getElementById('allocation_note'),
            };

            return fieldMap[key] || null;
        };

        const ensureRecipeOption = (recipe) => {
            if (!recipe || !recipe.id) return;
            const exists = recipeOptions.some(row => Number(row.id) === Number(recipe.id));
            if (!exists) {
                recipeOptions = [...recipeOptions, recipe];
            }
        };

        const getRecipeOption = (id) => recipeOptions.find(row => Number(row.id) === Number(id));

        const populateRecipeSelect = (selectedId = null) => {
            if (!recipeEl) return;
            const currentValue = selectedId ? String(selectedId) : (recipeEl.value || '');
            let optionsHtml = '<option value="">Pilih resep rework</option>';
            recipeOptions.forEach((row) => {
                const selected = currentValue && Number(currentValue) === Number(row.id) ? 'selected' : '';
                optionsHtml += `<option value="${row.id}" ${selected}>${row.label}</option>`;
            });
            recipeEl.innerHTML = optionsHtml;
            if (typeof $ !== 'undefined' && $(recipeEl).data('select2')) {
                $(recipeEl).val(currentValue || null).trigger('change.select2');
            }
        };

        const renderRecipeSummary = () => {
            if (!recipeSummaryContent) return;
            const recipe = getRecipeOption(recipeEl?.value);
            const multiplier = Math.max(1, Number(recipeMultiplierEl?.value || 1));
            if (!recipe) {
                recipeSummaryContent.innerHTML = '<div class="text-muted">Pilih resep untuk melihat BOM.</div>';
                return;
            }

            const selectedSourceMap = {};
            sourceItemsContainer.querySelectorAll('.allocation-source-row').forEach((row) => {
                const option = getSourceOption(row.querySelector('.allocation-source-select')?.value);
                const qty = Number(row.querySelector('input[data-name="qty"]')?.value || 0);
                if (option?.item_id && qty > 0) {
                    selectedSourceMap[option.item_id] = (selectedSourceMap[option.item_id] || 0) + qty;
                }
            });

            const inputHtml = (recipe.input_items || []).map((row) => {
                const expected = Number(row.qty || 0) * multiplier;
                const actual = Number(selectedSourceMap[row.item_id] || 0);
                const badgeClass = actual === expected ? 'badge-light-success' : 'badge-light-warning';
                return `<li>${row.item_label || '-'} : ${expected} <span class="badge ${badgeClass} ms-2">Terpilih ${actual}</span></li>`;
            }).join('');
            const outputHtml = (recipe.output_items || []).map((row) => {
                const expected = Number(row.qty || 0) * multiplier;
                return `<li>${row.item_label || '-'} : ${expected}</li>`;
            }).join('');

            recipeSummaryContent.innerHTML = `
                <div class="mb-3"><span class="fw-bolder">${recipe.label || '-'}</span>${recipe.target_warehouse ? ` | Gudang Default: ${recipe.target_warehouse}` : ''}</div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="fw-bold mb-2">Input BOM x${multiplier}</div>
                        <ul class="mb-0">${inputHtml || '<li>-</li>'}</ul>
                    </div>
                    <div class="col-md-6">
                        <div class="fw-bold mb-2">Output BOM x${multiplier}</div>
                        <ul class="mb-0">${outputHtml || '<li>-</li>'}</ul>
                    </div>
                </div>
            `;
        };

        const loadRecipeOptions = async () => {
            try {
                const res = await fetch(recipeOptionsUrl, { headers: { 'Accept': 'application/json' } });
                const json = await res.json();
                if (!res.ok) throw new Error(json.message || 'Gagal memuat resep rework');
                recipeOptions = Array.isArray(json.data) ? json.data : [];
                populateRecipeSelect(recipeEl?.value || null);
                renderRecipeSummary();
            } catch (err) {
                if (recipeSummaryContent) {
                    recipeSummaryContent.innerHTML = `<div class="text-danger">${err.message || 'Gagal memuat resep rework'}</div>`;
                }
            }
        };

        const ensureSourceOption = (option) => {
            if (!option || !option.id) return;
            const exists = sourceLineOptions.some(row => Number(row.id) === Number(option.id));
            if (!exists) {
                sourceLineOptions = [...sourceLineOptions, option];
            }
        };

        const getSourceOption = (id) => sourceLineOptions.find(row => Number(row.id) === Number(id));

        const renderSummary = () => {
            if (!summaryBody) return;
            if (!sourceLineOptions.length) {
                summaryBody.innerHTML = '<tr><td colspan="7" class="text-muted">Belum ada saldo rusak yang tersedia untuk dialokasikan.</td></tr>';
                return;
            }

            summaryBody.innerHTML = sourceLineOptions.map((row) => `
                <tr>
                    <td>${row.damage_code || '-'}</td>
                    <td>${row.source_warehouse_name || '-'}</td>
                    <td>${row.item_sku || ''} - ${row.item_name || ''}</td>
                    <td class="text-end">${row.received_qty ?? 0}</td>
                    <td class="text-end">${row.allocated_qty ?? 0}</td>
                    <td class="text-end">${row.remaining_qty ?? 0}</td>
                    <td>${row.damage_transacted_at ? String(row.damage_transacted_at).substring(0, 16).replace('T', ' ') : '-'}</td>
                </tr>
            `).join('');
        };

        const loadSourceLines = async () => {
            try {
                const res = await fetch(sourceItemsUrl, { headers: { 'Accept': 'application/json' } });
                const json = await res.json();
                if (!res.ok) throw new Error(json.message || 'Gagal memuat saldo rusak');
                sourceLineOptions = Array.isArray(json.data) ? json.data : [];
                renderSummary();
                sourceItemsContainer?.querySelectorAll('.allocation-source-select').forEach(selectEl => populateSourceSelect(selectEl, selectEl.value));
            } catch (err) {
                if (summaryBody) {
                    summaryBody.innerHTML = `<tr><td colspan="7" class="text-danger">${err.message || 'Gagal memuat saldo rusak'}</td></tr>`;
                }
            }
        };

        const populateSourceSelect = (selectEl, selectedId = null) => {
            if (!selectEl) return;
            const currentValue = selectedId ? String(selectedId) : (selectEl.value || '');
            let optionsHtml = '<option value=""></option>';
            sourceLineOptions.forEach((row) => {
                const selected = currentValue && Number(currentValue) === Number(row.id) ? 'selected' : '';
                optionsHtml += `<option value="${row.id}" ${selected}>${row.label}</option>`;
            });
            selectEl.innerHTML = optionsHtml;
            updateSourceInfo(selectEl.closest('.allocation-source-row'));
            if (typeof $ !== 'undefined' && $(selectEl).data('select2')) {
                $(selectEl).trigger('change.select2');
            }
        };

        const updateSourceInfo = (row) => {
            if (!row) return;
            const selectEl = row.querySelector('.allocation-source-select');
            const infoEl = row.querySelector('[data-role="source-info"]');
            if (!selectEl || !infoEl) return;

            const option = getSourceOption(selectEl.value);
            if (!option) {
                infoEl.textContent = 'Pilih sumber item rusak.';
                return;
            }

            infoEl.textContent = `Intake ${option.damage_code} | Gudang Asal ${option.source_warehouse_name || '-'} | Qty Intake ${option.received_qty} | Sudah dialokasikan ${option.allocated_qty} | Sisa ${option.remaining_qty}`;
        };

        const validateUniqueSources = () => {
            const rows = Array.from(sourceItemsContainer.querySelectorAll('.allocation-source-row'));
            const counts = {};
            rows.forEach((row) => {
                const val = row.querySelector('.allocation-source-select')?.value;
                if (val) counts[val] = (counts[val] || 0) + 1;
            });

            let hasDuplicate = false;
            rows.forEach((row) => {
                const selectEl = row.querySelector('.allocation-source-select');
                const errEl = row.querySelector('[data-error-for="damaged_good_item_id"]');
                const val = selectEl?.value;
                if (selectEl && val && counts[val] > 1) {
                    hasDuplicate = true;
                    setFieldInvalid(selectEl);
                    if (errEl) errEl.textContent = 'Sumber item rusak tidak boleh duplikat';
                } else {
                    clearFieldInvalid(selectEl);
                    if (errEl && errEl.textContent === 'Sumber item rusak tidak boleh duplikat') errEl.textContent = '';
                }
            });

            return !hasDuplicate;
        };

        const validateUniqueOutputs = () => {
            const rows = Array.from(outputItemsContainer.querySelectorAll('.allocation-output-row'));
            const counts = {};
            rows.forEach((row) => {
                const val = row.querySelector('.allocation-output-select')?.value;
                if (val) counts[val] = (counts[val] || 0) + 1;
            });

            let hasDuplicate = false;
            rows.forEach((row) => {
                const selectEl = row.querySelector('.allocation-output-select');
                const errEl = row.querySelector('[data-error-for="item_id"]');
                const val = selectEl?.value;
                if (selectEl && val && counts[val] > 1) {
                    hasDuplicate = true;
                    setFieldInvalid(selectEl);
                    if (errEl) errEl.textContent = 'Item output tidak boleh duplikat';
                } else {
                    clearFieldInvalid(selectEl);
                    if (errEl && errEl.textContent === 'Item output tidak boleh duplikat') errEl.textContent = '';
                }
            });

            return !hasDuplicate;
        };

        const renumberRows = () => {
            sourceItemsContainer.querySelectorAll('.allocation-source-row').forEach((row, idx) => {
                row.querySelectorAll('[data-name]').forEach((el) => {
                    el.name = `source_items[${idx}][${el.getAttribute('data-name')}]`;
                });
            });
            outputItemsContainer.querySelectorAll('.allocation-output-row').forEach((row, idx) => {
                row.querySelectorAll('[data-name]').forEach((el) => {
                    el.name = `output_items[${idx}][${el.getAttribute('data-name')}]`;
                });
            });
        };

        const initSelect2 = (selectEl, placeholder) => {
            if (!selectEl || typeof $ === 'undefined' || !$.fn.select2) return;
            $(selectEl).select2({
                placeholder,
                allowClear: true,
                width: '100%',
                dropdownParent: modalContentEl,
                minimumResultsForSearch: 0,
            }).on('select2:opening select2:closing select2:close', function(e) { e.stopPropagation(); });
        };

        const createSourceRow = (data = {}) => {
            const row = document.createElement('div');
            row.className = 'row g-3 align-items-end mb-4 allocation-source-row';
            row.innerHTML = `
                <div class="col-md-6">
                    <label class="required fs-6 fw-bold form-label mb-2">Sumber Item Rusak</label>
                    <select class="form-select form-select-solid allocation-source-select" data-name="damaged_good_item_id" required></select>
                    <div class="form-text text-muted" data-role="source-info">Pilih sumber item rusak.</div>
                    <div class="invalid-feedback d-block" data-error-for="damaged_good_item_id"></div>
                </div>
                <div class="col-md-2">
                    <label class="required fs-6 fw-bold form-label mb-2">Qty</label>
                    <input type="number" min="1" class="form-control form-control-solid" data-name="qty" required />
                    <div class="invalid-feedback d-block" data-error-for="qty"></div>
                </div>
                <div class="col-md-3">
                    <label class="fs-6 fw-bold form-label mb-2">Catatan Item</label>
                    <input type="text" class="form-control form-control-solid" data-name="note" />
                </div>
                <div class="col-md-1 text-end">
                    <button type="button" class="btn btn-light btn-sm btn-remove-source-item">Hapus</button>
                </div>
            `;

            sourceItemsContainer.appendChild(row);
            const selectEl = row.querySelector('.allocation-source-select');
            if (data.damaged_good_item_id && data.option_label) {
                ensureSourceOption({
                    id: Number(data.damaged_good_item_id),
                    item_id: Number(data.item_id || 0),
                    remaining_qty: Number(data.remaining_qty || data.qty || 0),
                    allocated_qty: Number(data.allocated_qty || 0),
                    received_qty: Number(data.received_qty || data.qty || 0),
                    damage_code: data.damage_code || '-',
                    source_warehouse_name: data.source_warehouse || '-',
                    item_sku: '',
                    item_name: data.item_label || '',
                    label: data.option_label,
                });
            }
            populateSourceSelect(selectEl, data.damaged_good_item_id || null);
            const qtyEl = row.querySelector('input[data-name="qty"]');
            if (qtyEl) qtyEl.value = data.qty ?? '';
            const noteEl = row.querySelector('input[data-name="note"]');
            if (noteEl) noteEl.value = data.note ?? '';
            initSelect2(selectEl, 'Pilih sumber item rusak');
            renumberRows();
            updateSourceInfo(row);
            validateUniqueSources();
            renderRecipeSummary();
        };

        const createOutputRow = (data = {}) => {
            const row = document.createElement('div');
            row.className = 'row g-3 align-items-end mb-4 allocation-output-row';
            row.innerHTML = `
                <div class="col-md-6">
                    <label class="required fs-6 fw-bold form-label mb-2">Item Hasil</label>
                    <select class="form-select form-select-solid allocation-output-select" data-name="item_id" required>
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
                    <label class="fs-6 fw-bold form-label mb-2">Catatan Output</label>
                    <input type="text" class="form-control form-control-solid" data-name="note" />
                </div>
                <div class="col-md-1 text-end">
                    <button type="button" class="btn btn-light btn-sm btn-remove-output-item">Hapus</button>
                </div>
            `;

            outputItemsContainer.appendChild(row);
            const selectEl = row.querySelector('.allocation-output-select');
            if (data.item_id) selectEl.value = String(data.item_id);
            const qtyEl = row.querySelector('input[data-name="qty"]');
            if (qtyEl) qtyEl.value = data.qty ?? '';
            const noteEl = row.querySelector('input[data-name="note"]');
            if (noteEl) noteEl.value = data.note ?? '';
            initSelect2(selectEl, 'Pilih item hasil');
            renumberRows();
            validateUniqueOutputs();
        };

        const toggleTypeFields = () => {
            const type = typeEl?.value || '';
            if (supplierWrap) supplierWrap.style.display = type === 'return_supplier' ? '' : 'none';
            if (recipeWrap) recipeWrap.style.display = type === 'rework' ? '' : 'none';
            if (recipeMultiplierWrap) recipeMultiplierWrap.style.display = type === 'rework' ? '' : 'none';
            if (targetWarehouseWrap) targetWarehouseWrap.style.display = type === 'rework' ? '' : 'none';
            const hasRecipe = type === 'rework' && !!(recipeEl?.value || '');
            if (outputSection) outputSection.style.display = type === 'rework' && !hasRecipe ? '' : 'none';
            if (recipePreviewSection) recipePreviewSection.style.display = hasRecipe ? '' : 'none';
            if (type !== 'return_supplier' && supplierEl) {
                supplierEl.value = '';
                if (typeof $ !== 'undefined' && $(supplierEl).data('select2')) {
                    $(supplierEl).val(null).trigger('change.select2');
                }
            }
            if (type !== 'rework') {
                if (recipeEl) {
                    recipeEl.value = '';
                    if (typeof $ !== 'undefined' && $(recipeEl).data('select2')) {
                        $(recipeEl).val(null).trigger('change.select2');
                    }
                }
                if (recipeMultiplierEl) recipeMultiplierEl.value = '1';
            }
            if (type !== 'rework' && targetWarehouseEl) {
                targetWarehouseEl.value = '';
                if (typeof $ !== 'undefined' && $(targetWarehouseEl).data('select2')) {
                    $(targetWarehouseEl).val(null).trigger('change.select2');
                }
                outputItemsContainer.innerHTML = '';
            }
            if (hasRecipe) {
                const recipe = getRecipeOption(recipeEl?.value);
                if (recipe?.target_warehouse_id && targetWarehouseEl && !targetWarehouseEl.value) {
                    targetWarehouseEl.value = String(recipe.target_warehouse_id);
                    if (typeof $ !== 'undefined' && $(targetWarehouseEl).data('select2')) {
                        $(targetWarehouseEl).val(targetWarehouseEl.value).trigger('change.select2');
                    }
                }
                outputItemsContainer.innerHTML = '';
            }
            if (type === 'rework' && !hasRecipe && outputItemsContainer.children.length === 0) {
                createOutputRow();
            }
            renumberRows();
            renderRecipeSummary();
        };

        const resetForm = async () => {
            form?.reset();
            form.dataset.editId = '';
            if (modalTitle) modalTitle.textContent = 'Tambah Alokasi Barang Rusak';
            if (typeEl) typeEl.value = 'return_supplier';
            if (recipeEl) recipeEl.value = '';
            if (recipeMultiplierEl) recipeMultiplierEl.value = '1';
            if (targetWarehouseEl) {
                targetWarehouseEl.value = defaultTargetWarehouseId ? String(defaultTargetWarehouseId) : '';
            }
            if (typeof $ !== 'undefined' && $.fn.select2) {
                if ($(supplierEl).data('select2')) $(supplierEl).val(null).trigger('change.select2');
                if ($(recipeEl).data('select2')) $(recipeEl).val(null).trigger('change.select2');
                if ($(targetWarehouseEl).data('select2')) $(targetWarehouseEl).val(targetWarehouseEl.value || null).trigger('change.select2');
            }
            if (fpTransacted) {
                fpTransacted.setDate(getJakartaNow(), true, 'Y-m-d H:i');
            } else if (transactedAtEl) {
                transactedAtEl.value = getJakartaNow();
            }
            sourceItemsContainer.innerHTML = '';
            outputItemsContainer.innerHTML = '';
            clearErrors();
            await loadSourceLines();
            await loadRecipeOptions();
            createSourceRow();
            toggleTypeFields();
            validateUniqueSources();
            validateUniqueOutputs();
        };

        if (typeof flatpickr !== 'undefined' && transactedAtEl) {
            fpTransacted = flatpickr(transactedAtEl, { enableTime: true, dateFormat: 'Y-m-d H:i', allowInput: true });
        }

        initSelect2(supplierEl, 'Pilih supplier');
        initSelect2(recipeEl, 'Pilih resep rework');
        initSelect2(targetWarehouseEl, 'Pilih gudang hasil');

        typeEl?.addEventListener('change', toggleTypeFields);
        recipeEl?.addEventListener('change', toggleTypeFields);
        recipeMultiplierEl?.addEventListener('input', renderRecipeSummary);
        addSourceItemBtn?.addEventListener('click', () => createSourceRow());
        addOutputItemBtn?.addEventListener('click', () => createOutputRow());
        openBtn?.addEventListener('click', () => { resetForm(); });

        sourceItemsContainer?.addEventListener('change', (e) => {
            if (e.target.matches('.allocation-source-select')) {
                clearFieldInvalid(e.target);
                const errEl = e.target.closest('.allocation-source-row')?.querySelector('[data-error-for="damaged_good_item_id"]');
                if (errEl) errEl.textContent = '';
                updateSourceInfo(e.target.closest('.allocation-source-row'));
                validateUniqueSources();
                renderRecipeSummary();
            }
        });

        sourceItemsContainer?.addEventListener('input', (e) => {
            if (e.target.matches('input[data-name="qty"]')) {
                clearFieldInvalid(e.target);
                const errEl = e.target.closest('.allocation-source-row')?.querySelector('[data-error-for="qty"]');
                if (errEl) errEl.textContent = '';
                renderRecipeSummary();
            }
        });

        outputItemsContainer?.addEventListener('change', (e) => {
            if (e.target.matches('.allocation-output-select')) {
                clearFieldInvalid(e.target);
                const errEl = e.target.closest('.allocation-output-row')?.querySelector('[data-error-for="item_id"]');
                if (errEl) errEl.textContent = '';
                validateUniqueOutputs();
            }
        });

        outputItemsContainer?.addEventListener('input', (e) => {
            if (e.target.matches('input[data-name="qty"]')) {
                clearFieldInvalid(e.target);
                const errEl = e.target.closest('.allocation-output-row')?.querySelector('[data-error-for="qty"]');
                if (errEl) errEl.textContent = '';
            }
        });

        form?.addEventListener('input', (e) => {
            const target = e.target;
            if (!(target instanceof HTMLElement)) return;
            const fieldName = target.getAttribute('name');
            if (!fieldName || fieldName.includes('[')) return;
            clearFieldInvalid(target);
            const errEl = document.getElementById(`error_${fieldName}`);
            if (errEl) errEl.textContent = '';
        });

        form?.addEventListener('change', (e) => {
            const target = e.target;
            if (!(target instanceof HTMLElement)) return;
            const fieldName = target.getAttribute('name');
            if (!fieldName || fieldName.includes('[')) return;
            clearFieldInvalid(target);
            const errEl = document.getElementById(`error_${fieldName}`);
            if (errEl) errEl.textContent = '';
        });

        sourceItemsContainer?.addEventListener('click', (e) => {
            const btn = e.target.closest('.btn-remove-source-item');
            if (!btn) return;
            const row = btn.closest('.allocation-source-row');
            if (row) row.remove();
            if (sourceItemsContainer.querySelectorAll('.allocation-source-row').length === 0) {
                createSourceRow();
            } else {
                renumberRows();
            }
            validateUniqueSources();
        });

        outputItemsContainer?.addEventListener('click', (e) => {
            const btn = e.target.closest('.btn-remove-output-item');
            if (!btn) return;
            const row = btn.closest('.allocation-output-row');
            if (row) row.remove();
            if ((typeEl?.value || '') === 'rework' && outputItemsContainer.querySelectorAll('.allocation-output-row').length === 0) {
                createOutputRow();
            } else {
                renumberRows();
            }
            validateUniqueOutputs();
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
                }
            },
            columns: [
                { data: 'id' },
                { data: 'code' },
                { data: 'type' },
                { data: 'status', orderable: false, searchable: false, render: (data) => statusLabel(data) },
                { data: 'transacted_at' },
                { data: 'submit_by' },
                { data: 'source_items' },
                { data: 'output_items' },
                { data: 'target' },
                { data: 'note' },
                { data: 'id', orderable:false, searchable:false, className:'text-end', render: (data, type, row) => {
                    const isApproved = row?.status === 'approved';
                    const approveItem = (!isApproved && canUpdate)
                        ? `<div class="menu-item px-3"><a href="#" class="menu-link px-3 text-success btn-approve" data-id="${data}">Approve</a></div>`
                        : '';
                    const editItem = (!isApproved && canUpdate)
                        ? `<div class="menu-item px-3"><a href="#" class="menu-link px-3 btn-edit" data-id="${data}">Edit</a></div>`
                        : '';
                    const delItem = (!isApproved && canDelete)
                        ? `<div class="menu-item px-3"><a href="#" class="menu-link px-3 text-danger btn-delete" data-id="${data}">Hapus</a></div>`
                        : '';
                    const actions = `${approveItem}${editItem}${delItem}`;
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
                await loadSourceLines();
                await loadRecipeOptions();
                const res = await fetch(showUrlTpl.replace(':id', id), { headers: { 'Accept': 'application/json' } });
                const json = await res.json();
                if (!res.ok) {
                    if (typeof Swal !== 'undefined') Swal.fire('Error', json.message || 'Gagal memuat data', 'error');
                    return;
                }

                form.dataset.editId = id;
                if (modalTitle) modalTitle.textContent = `Edit ${json.code || ''}`.trim();
                if (typeEl) typeEl.value = json.type || '';
                if (json.recipe) {
                    ensureRecipeOption(json.recipe);
                    populateRecipeSelect(json.recipe_id || null);
                }
                if (recipeEl) recipeEl.value = json.recipe_id ? String(json.recipe_id) : '';
                if (recipeMultiplierEl) recipeMultiplierEl.value = json.recipe_multiplier ? String(json.recipe_multiplier) : '1';
                if (supplierEl) supplierEl.value = json.supplier_id ? String(json.supplier_id) : '';
                if (targetWarehouseEl) targetWarehouseEl.value = json.target_warehouse_id ? String(json.target_warehouse_id) : '';
                if (typeof $ !== 'undefined' && $.fn.select2) {
                    if ($(recipeEl).data('select2')) $(recipeEl).val(recipeEl.value || null).trigger('change.select2');
                    if ($(supplierEl).data('select2')) $(supplierEl).val(supplierEl.value || null).trigger('change.select2');
                    if ($(targetWarehouseEl).data('select2')) $(targetWarehouseEl).val(targetWarehouseEl.value || null).trigger('change.select2');
                }
                document.getElementById('allocation_source_ref').value = json.source_ref || '';
                document.getElementById('allocation_note').value = json.note || '';
                if (fpTransacted) {
                    fpTransacted.setDate(json.transacted_at || null, true, 'Y-m-d H:i');
                } else {
                    transactedAtEl.value = json.transacted_at || '';
                }

                sourceItemsContainer.innerHTML = '';
                outputItemsContainer.innerHTML = '';
                (json.source_items || []).forEach((row) => {
                    createSourceRow(row);
                });
                if ((json.source_items || []).length === 0) createSourceRow();
                (json.output_items || []).forEach((row) => createOutputRow(row));
                toggleTypeFields();
                clearErrors();
                validateUniqueSources();
                validateUniqueOutputs();
                renderRecipeSummary();
                modal?.show();
            } catch (err) {
                if (typeof Swal !== 'undefined') Swal.fire('Error', err.message || 'Gagal memuat data', 'error');
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
                    text: 'Data alokasi barang rusak akan dihapus',
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
                await loadSourceLines();
                reloadTable();
            } catch (err) {
                if (typeof Swal !== 'undefined') Swal.fire('Error', 'Gagal menghapus', 'error');
            }
        });

        tableEl.on('click', '.btn-approve', async function(e) {
            e.preventDefault();
            const id = this.getAttribute('data-id');
            if (!id) return;
            let confirmed = true;
            if (typeof Swal !== 'undefined') {
                const res = await Swal.fire({
                    title: 'Setujui data ini?',
                    text: 'Setelah disetujui, stok rusak akan dialokasikan permanen dan data tidak bisa diubah atau dihapus.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Approve',
                    cancelButtonText: 'Batal',
                    buttonsStyling: false,
                    customClass: {
                        confirmButton: 'btn btn-success',
                        cancelButton: 'btn btn-light'
                    }
                });
                confirmed = res.isConfirmed;
            }
            if (!confirmed) return;

            try {
                const res = await fetch(approveUrlTpl.replace(':id', id), {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                });
                const json = await res.json();
                if (!res.ok) {
                    if (typeof Swal !== 'undefined') Swal.fire('Error', json.message || 'Gagal menyetujui', 'error');
                    return;
                }
                if (typeof Swal !== 'undefined') Swal.fire('Berhasil', json.message || 'Berhasil', 'success');
                await loadSourceLines();
                reloadTable();
            } catch (err) {
                if (typeof Swal !== 'undefined') Swal.fire('Error', 'Gagal menyetujui', 'error');
            }
        });

        form?.addEventListener('submit', async (e) => {
            e.preventDefault();
            clearErrors();

            if (!validateUniqueSources()) {
                if (typeof Swal !== 'undefined') Swal.fire('Error', 'Sumber item rusak tidak boleh duplikat', 'error');
                return;
            }
            if ((typeEl?.value || '') === 'rework' && !validateUniqueOutputs()) {
                if (typeof Swal !== 'undefined') Swal.fire('Error', 'Item output tidak boleh duplikat', 'error');
                return;
            }

            const isEdit = !!form.dataset.editId;
            const url = isEdit ? updateUrlTpl.replace(':id', form.dataset.editId) : storeUrl;
            const formData = new FormData(form);
            if (isEdit) formData.append('_method', 'PUT');

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
                            if (key.startsWith('source_items.')) {
                                const parts = key.split('.');
                                const idx = parseInt(parts[1], 10);
                                const field = parts[2];
                                const row = sourceItemsContainer.querySelectorAll('.allocation-source-row')[idx];
                                const fieldEl = row ? row.querySelector(`[data-name="${field}"]`) : null;
                                const errEl = row ? row.querySelector(`[data-error-for="${field}"]`) : null;
                                if (fieldEl) setFieldInvalid(fieldEl);
                                if (errEl) errEl.textContent = msgs.join(', ');
                                else unhandled.push(msgs.join(', '));
                            } else if (key.startsWith('output_items.')) {
                                const parts = key.split('.');
                                const idx = parseInt(parts[1], 10);
                                const field = parts[2];
                                const row = outputItemsContainer.querySelectorAll('.allocation-output-row')[idx];
                                const fieldEl = row ? row.querySelector(`[data-name="${field}"]`) : null;
                                const errEl = row ? row.querySelector(`[data-error-for="${field}"]`) : null;
                                if (fieldEl) setFieldInvalid(fieldEl);
                                if (errEl) errEl.textContent = msgs.join(', ');
                                else unhandled.push(msgs.join(', '));
                            } else {
                                const fieldEl = getTopLevelField(key);
                                const errEl = document.getElementById(`error_${key}`);
                                if (fieldEl) setFieldInvalid(fieldEl);
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
                await loadSourceLines();
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
