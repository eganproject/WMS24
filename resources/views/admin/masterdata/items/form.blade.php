@extends('layouts.admin')

@php
    $isEdit = !empty($item);
    $pageTitle = $isEdit ? 'Edit Item' : 'Tambah Item';
@endphp

@section('title', $pageTitle)
@section('page_title', $pageTitle)

@section('page_breadcrumbs')
    <li class="breadcrumb-item text-gray-600">
        <a href="{{ route('admin.dashboard') }}" class="text-gray-600 text-hover-primary">Admin</a>
    </li>
    <li class="breadcrumb-item text-gray-600">
        <a href="{{ route('admin.masterdata.items.index') }}" class="text-gray-600 text-hover-primary">Items</a>
    </li>
    @if($isEdit)
        <li class="breadcrumb-item text-gray-600">
            <a href="{{ route('admin.masterdata.items.show', $item->id) }}" class="text-gray-600 text-hover-primary">{{ $item->sku }}</a>
        </li>
    @endif
    <li class="breadcrumb-item text-gray-500">{{ $isEdit ? 'Edit' : 'Tambah' }}</li>
@endsection

@section('page_actions')
    <a href="{{ $isEdit ? route('admin.masterdata.items.show', $item->id) : route('admin.masterdata.items.index') }}" class="btn btn-sm btn-light">
        <i class="fas fa-arrow-left me-1"></i> Kembali
    </a>
@endsection

@section('content')
<form class="form w-100" id="item_form">
    @csrf
    <input type="hidden" name="item_id" id="item_id" value="{{ $isEdit ? $item->id : '' }}" />

    <div class="row g-6">
        <div class="col-xl-7">
            <div class="card mb-6">
                <div class="card-header border-0 pt-6">
                    <div class="card-title flex-column">
                        <h3 class="fw-bolder mb-1">Informasi Item</h3>
                        <div class="text-muted fs-7">Identitas SKU, tipe, status, dan kategori.</div>
                    </div>
                </div>
                <div class="card-body pt-2">
                    <div class="row g-5 mb-7">
                        <div class="col-md-5">
                            <label class="required fs-6 fw-bold form-label mb-2">SKU</label>
                            <input type="text" class="form-control form-control-solid" name="sku" id="item_sku" required />
                            <div class="invalid-feedback d-block" id="error_sku"></div>
                        </div>
                        <div class="col-md-7">
                            <label class="required fs-6 fw-bold form-label mb-2">Nama</label>
                            <input type="text" class="form-control form-control-solid" name="name" id="item_name" required />
                            <div class="invalid-feedback d-block" id="error_name"></div>
                        </div>
                    </div>
                    <div class="row g-5 mb-7">
                        <div class="col-md-6">
                            <label class="required fs-6 fw-bold form-label mb-2">Tipe Item</label>
                            <select name="item_type" id="item_type" class="form-select form-select-solid">
                                <option value="single">Single / Stok Fisik</option>
                                <option value="bundle">Bundle / Virtual Stock</option>
                            </select>
                            <div class="form-text">Bundle tidak memiliki stok fisik. Stoknya dihitung dari komponen.</div>
                            <div class="invalid-feedback d-block" id="error_item_type"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="required fs-6 fw-bold form-label mb-2">Status SKU</label>
                            <select name="status" id="item_status" class="form-select form-select-solid">
                                <option value="active">Aktif</option>
                                <option value="inactive">Nonaktif</option>
                            </select>
                            <div class="form-text">SKU nonaktif tidak tampil default di Item Stocks, low stock, snapshot, dan replenishment.</div>
                            <div class="invalid-feedback d-block" id="error_status"></div>
                        </div>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fs-6 fw-bold form-label mb-2">Kategori</label>
                        <select name="category_id" id="item_category_id" class="form-select form-select-solid" data-placeholder="Pilih kategori">
                            <option value="0">Tanpa Kategori</option>
                            @foreach($categories as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                        <div class="invalid-feedback d-block" id="error_category_id"></div>
                    </div>
                    <div class="fv-row">
                        <label class="fs-6 fw-bold form-label mb-2">Deskripsi</label>
                        <textarea class="form-control form-control-solid" name="description" id="item_description" rows="3"></textarea>
                        <div class="invalid-feedback d-block" id="error_description"></div>
                    </div>
                </div>
            </div>

            <div class="card mb-6" id="external_barcodes_card">
                <div class="card-header border-0 pt-6">
                    <div class="card-title flex-column">
                        <h3 class="fw-bolder mb-1">Barcode / QR Eksternal</h3>
                        <div class="text-muted fs-7">Dipakai untuk QC Scan SKU dan Stock Opname. Setiap barcode wajib unik.</div>
                    </div>
                    <div class="card-toolbar">
                        <button type="button" class="btn btn-sm btn-light-primary" id="btn_add_external_barcode">Tambah Barcode</button>
                    </div>
                </div>
                <div class="card-body pt-2">
                    <div id="external_barcodes_container" class="d-flex flex-column gap-4"></div>
                    <div class="text-muted fs-7" id="external_barcodes_empty">Belum ada barcode eksternal.</div>
                    <div class="invalid-feedback d-block" id="error_external_barcodes"></div>
                </div>
            </div>

            <div class="card border border-dashed border-primary bg-light-primary mb-6" id="bundle_components_card" style="display:none;">
                <div class="card-header border-0 pt-6">
                    <div class="card-title flex-column">
                        <h3 class="fw-bolder text-primary mb-1">Komponen Bundle</h3>
                        <div class="text-muted fs-7">Virtual stock bundle dihitung dari komponen. Komponen harus item fisik, bukan bundle lain.</div>
                    </div>
                    <div class="card-toolbar">
                        <button type="button" class="btn btn-sm btn-primary" id="btn_add_bundle_component">Tambah Komponen</button>
                    </div>
                </div>
                <div class="card-body pt-2">
                    <div id="bundle_components_container" class="d-flex flex-column gap-4"></div>
                    <div class="invalid-feedback d-block" id="error_bundle_components"></div>
                </div>
            </div>
        </div>

        <div class="col-xl-5">
            <div class="card mb-6">
                <div class="card-header border-0 pt-6">
                    <div class="card-title flex-column">
                        <h3 class="fw-bolder mb-1">Lokasi</h3>
                        <div class="text-muted fs-7">Area dan slot rak item. Tidak berlaku untuk bundle.</div>
                    </div>
                </div>
                <div class="card-body pt-2">
                    <div class="fv-row mb-7">
                        <label class="fs-6 fw-bold form-label mb-2">Area</label>
                        <select name="area_id" id="item_area_id" class="form-select form-select-solid" data-placeholder="Pilih area">
                            <option value="">Pilih area</option>
                            @foreach($areas as $area)
                                <option value="{{ $area->id }}" data-code="{{ $area->code }}">{{ $area->code }} - {{ $area->name }}</option>
                            @endforeach
                        </select>
                        <div class="invalid-feedback d-block" id="error_area_id"></div>
                    </div>
                    <div class="row g-3 mb-7">
                        <div class="col-4">
                            <label class="fs-6 fw-bold form-label mb-2">Rack</label>
                            <input type="text" class="form-control form-control-solid" name="rack_code" id="item_rack_code" />
                            <div class="invalid-feedback d-block" id="error_rack_code"></div>
                        </div>
                        <div class="col-4">
                            <label class="fs-6 fw-bold form-label mb-2">Kolom</label>
                            <input type="number" min="1" class="form-control form-control-solid" name="column_no" id="item_column_no" />
                            <div class="invalid-feedback d-block" id="error_column_no"></div>
                        </div>
                        <div class="col-4">
                            <label class="fs-6 fw-bold form-label mb-2">Baris</label>
                            <input type="number" min="1" class="form-control form-control-solid" name="row_no" id="item_row_no" />
                            <div class="invalid-feedback d-block" id="error_row_no"></div>
                        </div>
                    </div>
                    <div class="fv-row">
                        <label class="fs-6 fw-bold form-label mb-2">Alamat</label>
                        <input type="text" class="form-control form-control-solid" name="address" id="item_address" placeholder="KAB atau KAB-A-03-05" />
                        <div class="form-text">Boleh hanya area jika slot detail belum ada. Jika rack, kolom, atau baris diisi, semuanya wajib lengkap.</div>
                        <div class="invalid-feedback d-block" id="error_address"></div>
                    </div>
                </div>
            </div>

            <div class="card mb-6">
                <div class="card-header border-0 pt-6">
                    <div class="card-title flex-column">
                        <h3 class="fw-bolder mb-1">Koli &amp; Stok Pengaman</h3>
                        <div class="text-muted fs-7">Dipakai saat input inbound per koli dan peringatan stok.</div>
                    </div>
                </div>
                <div class="card-body pt-2">
                    <div class="fv-row mb-7">
                        <label class="fs-6 fw-bold form-label mb-2">Isi per Koli (pcs)</label>
                        <input type="number" min="0" class="form-control form-control-solid" name="koli_qty" id="item_koli_qty" placeholder="Contoh: 24" />
                        <div class="form-text">Isi 0 atau kosong jika tidak menggunakan koli.</div>
                        <div class="invalid-feedback d-block" id="error_koli_qty"></div>
                    </div>
                    <div class="fv-row">
                        <label class="fs-6 fw-bold form-label mb-2">Jumlah Stok Pengaman</label>
                        <input type="number" min="0" class="form-control form-control-solid" name="safety_stock" id="item_safety_stock" value="0" />
                        <div class="invalid-feedback d-block" id="error_safety_stock"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body d-flex justify-content-end gap-3 py-5">
            <a href="{{ $isEdit ? route('admin.masterdata.items.show', $item->id) : route('admin.masterdata.items.index') }}" class="btn btn-light">Batal</a>
            <button type="submit" class="btn btn-primary" id="btn_submit_item">
                <span class="indicator-label">Simpan</span>
                <span class="indicator-progress">Please wait...
                <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
            </button>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
    const csrfToken = '{{ csrf_token() }}';
    const storeUrl  = '{{ route('admin.masterdata.items.store') }}';
    const updateTpl = '{{ route('admin.masterdata.items.update', ':id') }}';
    const indexUrl  = '{{ route('admin.masterdata.items.index') }}';
    const showTpl   = '{{ route('admin.masterdata.items.show', ':id') }}';
    const initialItem = @json($itemPayload);
    const componentItemOptionsHtml = `@foreach($componentItems as $componentItem)<option value="{{ $componentItem->id }}" data-sku="{{ $componentItem->sku }}" data-name="{{ $componentItem->name }}">{{ $componentItem->sku }} - {{ $componentItem->name }}</option>@endforeach`;

    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('item_form');
        const formSku = document.getElementById('item_sku');
        const formName = document.getElementById('item_name');
        const formType = document.getElementById('item_type');
        const formStatus = document.getElementById('item_status');
        const formCategory = document.getElementById('item_category_id');
        const formArea = document.getElementById('item_area_id');
        const formRack = document.getElementById('item_rack_code');
        const formColumn = document.getElementById('item_column_no');
        const formRow = document.getElementById('item_row_no');
        const formId = document.getElementById('item_id');
        const formAddress = document.getElementById('item_address');
        const formDescription = document.getElementById('item_description');
        const formSafetyStock = document.getElementById('item_safety_stock');
        const formKoliQty = document.getElementById('item_koli_qty');
        const externalBarcodesCard = document.getElementById('external_barcodes_card');
        const externalBarcodesContainer = document.getElementById('external_barcodes_container');
        const externalBarcodesEmpty = document.getElementById('external_barcodes_empty');
        const addExternalBarcodeBtn = document.getElementById('btn_add_external_barcode');
        const bundleCard = document.getElementById('bundle_components_card');
        const bundleContainer = document.getElementById('bundle_components_container');
        const addBundleComponentBtn = document.getElementById('btn_add_bundle_component');
        const submitBtn = document.getElementById('btn_submit_item');

        const notifyError = (message, title = 'Error') => {
            if (window.AppSwal?.error) {
                return window.AppSwal.error(message, title);
            }
            if (typeof Swal !== 'undefined') {
                return Swal.fire(title, message || 'Terjadi kesalahan', 'error');
            }
            alert(message || 'Terjadi kesalahan');
        };

        const escapeHtml = (value) => String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');

        const setCategoryValue = (val) => {
            if (!formCategory) return;
            const normalized = (val === null || val === undefined || val === '' || val === 'null') ? '0' : String(val);
            formCategory.value = normalized;
            if (typeof $ !== 'undefined' && $(formCategory).data('select2')) {
                $(formCategory).val(normalized).trigger('change');
            }
        };

        const setAreaValue = (val) => {
            if (!formArea) return;
            const normalized = (val === null || val === undefined || val === '' || val === 'null') ? '' : String(val);
            formArea.value = normalized;
            if (typeof $ !== 'undefined' && $(formArea).data('select2')) {
                $(formArea).val(normalized).trigger('change');
            }
        };

        const refreshExternalBarcodesEmpty = () => {
            if (!externalBarcodesEmpty || !externalBarcodesContainer) return;
            externalBarcodesEmpty.style.display = externalBarcodesContainer.querySelector('.external-barcode-row') ? 'none' : '';
        };

        const syncExternalBarcodeFieldNames = () => {
            if (!externalBarcodesContainer) return;
            Array.from(externalBarcodesContainer.querySelectorAll('.external-barcode-row')).forEach((row, index) => {
                row.querySelectorAll('[data-field]').forEach((field) => {
                    field.setAttribute('name', `external_barcodes[${index}][${field.getAttribute('data-field')}]`);
                });
            });
            refreshExternalBarcodesEmpty();
        };

        const createExternalBarcodeRow = (barcode = {}) => {
            if (!externalBarcodesContainer) return null;
            const row = document.createElement('div');
            row.className = 'row g-3 align-items-end external-barcode-row';
            row.innerHTML = `
                <div class="col-md-5">
                    <label class="fs-7 fw-bold form-label mb-2">Barcode / QR</label>
                    <input type="text" class="form-control form-control-solid external-barcode-value" data-field="barcode_value" value="${escapeHtml(barcode.barcode_value || '')}" />
                </div>
                <div class="col-md-3">
                    <label class="fs-7 fw-bold form-label mb-2">Sumber</label>
                    <input type="text" class="form-control form-control-solid external-barcode-source" data-field="source_name" value="${escapeHtml(barcode.source_name || '')}" placeholder="Supplier/marketplace" />
                </div>
                <div class="col-md-3">
                    <label class="fs-7 fw-bold form-label mb-2">Catatan</label>
                    <input type="text" class="form-control form-control-solid external-barcode-note" data-field="note" value="${escapeHtml(barcode.note || '')}" />
                </div>
                <div class="col-md-1">
                    <button type="button" class="btn btn-icon btn-light-danger btn-remove-external-barcode">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `;
            externalBarcodesContainer.appendChild(row);
            syncExternalBarcodeFieldNames();
            return row;
        };

        const clearExternalBarcodes = () => {
            if (externalBarcodesContainer) externalBarcodesContainer.innerHTML = '';
            refreshExternalBarcodesEmpty();
        };

        const syncBundleComponentFieldNames = () => {
            if (!bundleContainer) return;
            Array.from(bundleContainer.querySelectorAll('.bundle-component-row')).forEach((row, index) => {
                const itemField = row.querySelector('.bundle-component-item');
                const qtyField = row.querySelector('.bundle-component-qty');
                if (itemField) {
                    itemField.setAttribute('name', `bundle_components[${index}][component_item_id]`);
                }
                if (qtyField) {
                    qtyField.setAttribute('name', `bundle_components[${index}][required_qty]`);
                }
            });
        };

        const createBundleComponentRow = (component = {}) => {
            if (!bundleContainer) return null;
            const row = document.createElement('div');
            row.className = 'row g-3 align-items-end bundle-component-row';
            row.innerHTML = `
                <div class="col-md-8">
                    <label class="fs-7 fw-bold form-label mb-2">Komponen</label>
                    <select class="form-select form-select-solid bundle-component-item" data-field="component_item_id" data-placeholder="Pilih komponen">
                        <option value="">Pilih komponen</option>
                        ${componentItemOptionsHtml}
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="fs-7 fw-bold form-label mb-2">Qty</label>
                    <input type="number" min="1" class="form-control form-control-solid bundle-component-qty" data-field="required_qty" value="${component.required_qty ?? ''}" />
                </div>
                <div class="col-md-1">
                    <button type="button" class="btn btn-icon btn-light-danger btn-remove-bundle-component">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `;
            bundleContainer.appendChild(row);
            const selectEl = row.querySelector('.bundle-component-item');
            if (selectEl && typeof $ !== 'undefined' && $.fn.select2) {
                $(selectEl).select2({
                    placeholder: 'Pilih komponen',
                    allowClear: true,
                    width: '100%',
                });
            }
            if (selectEl && component.component_item_id) {
                selectEl.value = String(component.component_item_id);
                if (typeof $ !== 'undefined' && $(selectEl).data('select2')) {
                    $(selectEl).val(String(component.component_item_id)).trigger('change');
                }
            }
            syncBundleComponentFieldNames();
            return row;
        };

        const clearBundleComponents = () => {
            if (bundleContainer) bundleContainer.innerHTML = '';
        };

        const ensureBundleComponentRow = () => {
            if (bundleContainer && !bundleContainer.querySelector('.bundle-component-row')) {
                createBundleComponentRow();
            }
        };

        const toggleBundleMode = () => {
            const isBundle = (formType?.value || 'single') === 'bundle';
            if (bundleCard) bundleCard.style.display = isBundle ? '' : 'none';
            if (externalBarcodesCard) externalBarcodesCard.style.display = isBundle ? 'none' : '';

            [formArea, formRack, formColumn, formRow, formAddress, formKoliQty, formSafetyStock].forEach((field) => {
                if (!field) return;
                field.disabled = isBundle;
            });

            if (isBundle) {
                setAreaValue('');
                if (formRack) formRack.value = '';
                if (formColumn) formColumn.value = '';
                if (formRow) formRow.value = '';
                if (formAddress) formAddress.value = '';
                if (formKoliQty) formKoliQty.value = '';
                if (formSafetyStock) formSafetyStock.value = 0;
                ensureBundleComponentRow();
                syncBundleComponentFieldNames();
                clearExternalBarcodes();
            } else {
                clearBundleComponents();
            }
        };

        const clearErrors = () => {
            ['error_sku','error_name','error_item_type','error_status','error_category_id','error_area_id','error_rack_code','error_column_no','error_row_no','error_address','error_description','error_koli_qty','error_safety_stock','error_external_barcodes','error_bundle_components'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.textContent = '';
            });
        };

        const buildAddress = () => {
            if (!formArea || !formRack || !formColumn || !formRow) return '';
            const areaOpt = formArea.options[formArea.selectedIndex];
            const areaCode = areaOpt ? (areaOpt.getAttribute('data-code') || '').trim() : '';
            const rack = (formRack.value || '').trim().toUpperCase();
            const rawCol = (formColumn.value || '').trim();
            const rawRow = (formRow.value || '').trim();
            const col = parseInt(formColumn.value || '', 10);
            const row = parseInt(formRow.value || '', 10);
            if (!areaCode) return '';
            if (!rack && !rawCol && !rawRow) return areaCode;
            if (!rack || !col || !row) return '';
            const colLabel = String(col).padStart(2, '0');
            const rowLabel = String(row).padStart(2, '0');
            return `${areaCode}-${rack}-${colLabel}-${rowLabel}`;
        };

        const syncAddress = () => {
            if (!formAddress) return;
            const code = buildAddress();
            if (code) {
                formAddress.value = code;
            }
        };

        if (typeof $ !== 'undefined' && $.fn.select2) {
            $(formCategory).select2({ placeholder: 'Pilih kategori', allowClear: true, width: '100%' });
            $(formArea).select2({ placeholder: 'Pilih area', allowClear: true, width: '100%' });
        }

        const fillForm = (item) => {
            formId.value = item?.id ?? '';
            if (formSku) formSku.value = item?.sku || '';
            if (formName) formName.value = item?.name || '';
            if (formType) formType.value = item?.item_type || 'single';
            if (formStatus) formStatus.value = item?.status || 'active';
            if (formAddress) formAddress.value = item?.address || '';
            setAreaValue(item?.area_id ?? '');
            if (formRack) formRack.value = item?.rack_code || '';
            if (formColumn) formColumn.value = item?.column_no || '';
            if (formRow) formRow.value = item?.row_no || '';
            if (formDescription) formDescription.value = item?.description || '';
            if (formKoliQty) formKoliQty.value = item?.koli_qty ?? '';
            if (formSafetyStock) formSafetyStock.value = item?.safety_stock ?? 0;
            setCategoryValue(item?.category_id ?? '0');
            clearExternalBarcodes();
            (item?.external_barcodes || []).forEach((barcode) => createExternalBarcodeRow(barcode));
            clearBundleComponents();
            (item?.bundle_components || []).forEach((component) => createBundleComponentRow(component));
            clearErrors();
            toggleBundleMode();
        };

        [formArea, formRack, formColumn, formRow].forEach((field) => {
            field?.addEventListener('input', syncAddress);
            field?.addEventListener('change', syncAddress);
        });
        if (formArea && typeof $ !== 'undefined') {
            $(formArea).on('select2:select select2:clear', syncAddress);
        }
        formType?.addEventListener('change', toggleBundleMode);
        addExternalBarcodeBtn?.addEventListener('click', () => createExternalBarcodeRow());
        externalBarcodesContainer?.addEventListener('click', (event) => {
            const button = event.target.closest('.btn-remove-external-barcode');
            if (!button) return;
            button.closest('.external-barcode-row')?.remove();
            syncExternalBarcodeFieldNames();
        });
        addBundleComponentBtn?.addEventListener('click', () => createBundleComponentRow());
        bundleContainer?.addEventListener('click', (event) => {
            const button = event.target.closest('.btn-remove-bundle-component');
            if (!button) return;
            button.closest('.bundle-component-row')?.remove();
            if ((formType?.value || 'single') === 'bundle') {
                ensureBundleComponentRow();
            }
            syncBundleComponentFieldNames();
        });

        fillForm(initialItem || {});

        form?.addEventListener('submit', async (e) => {
            e.preventDefault();
            clearErrors();
            syncExternalBarcodeFieldNames();
            syncBundleComponentFieldNames();
            const id = formId.value;
            const url = id ? updateTpl.replace(':id', id) : storeUrl;
            const formData = new FormData(form);
            if (id) formData.append('_method', 'PUT');
            submitBtn?.setAttribute('data-kt-indicator', 'on');
            if (submitBtn) submitBtn.disabled = true;
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
                try { json = JSON.parse(text); } catch (parseErr) {
                    console.error('Invalid JSON', text);
                    notifyError('Respons server tidak valid');
                    return;
                }
                if (!res.ok) {
                    if (json?.errors) {
                        Object.entries(json.errors).forEach(([key, msgs]) => {
                            const errEl = document.getElementById(`error_${key}`);
                            if (errEl) errEl.textContent = msgs.join(', ');
                        });
                        const msg = Object.values(json.errors).flat().join(', ');
                        if (msg) notifyError(msg);
                    } else {
                        notifyError(json.message || 'Gagal menyimpan item');
                    }
                    return;
                }
                const savedId = json?.item?.id || id;
                const redirectUrl = savedId ? showTpl.replace(':id', savedId) : indexUrl;
                if (typeof Swal !== 'undefined') {
                    await Swal.fire({
                        title: 'Berhasil',
                        text: json.message || 'Berhasil',
                        icon: 'success',
                        timer: 1200,
                        showConfirmButton: false,
                    });
                }
                window.location.href = redirectUrl;
            } catch (err) {
                console.error(err);
                notifyError('Gagal menyimpan item');
            } finally {
                submitBtn?.removeAttribute('data-kt-indicator');
                if (submitBtn) submitBtn.disabled = false;
            }
        });
    });
</script>
@endpush

@include('layouts.partials.form-submit-confirmation')
