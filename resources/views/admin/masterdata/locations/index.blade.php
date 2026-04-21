@extends('layouts.admin')

@section('title', 'Locations')
@section('page_title', 'Locations')

@php
    use App\Support\Permission as Perm;
    $canCreate = Perm::can(auth()->user(), 'admin.masterdata.locations.index', 'create');
    $canUpdate = Perm::can(auth()->user(), 'admin.masterdata.locations.index', 'update');
    $canDelete = Perm::can(auth()->user(), 'admin.masterdata.locations.index', 'delete');
@endphp

@section('content')
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div class="d-flex align-items-center position-relative my-1">
                <span class="svg-icon svg-icon-1 position-absolute ms-6">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <rect opacity="0.5" x="17.0365" y="15.1223" width="8.15546" height="2" rx="1" transform="rotate(45 17.0365 15.1223)" fill="black" />
                        <path d="M11 19C6.55556 19 3 15.4444 3 11C3 6.55556 6.55556 3 11 3C15.4444 3 19 6.55556 19 11C19 15.4444 15.4444 19 11 19ZM11 5C7.53333 5 5 7.53333 5 11C5 14.4667 7.53333 17 11 17C14.4667 17 17.53333 14.4667 17.53333 11C17.53333 7.53333 14.4667 5 11 5Z" fill="black" />
                    </svg>
                </span>
                <input type="text" class="form-control form-control-solid w-250px ps-14" placeholder="Search location" data-kt-filter="search" />
            </div>
        </div>
        <div class="card-toolbar">
            <div class="d-flex justify-content-end" data-kt-user-table-toolbar="base">
                @if($canCreate)
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal_location_form" id="btn_open_create">
                        Add Location
                    </button>
                @endif
            </div>
        </div>
    </div>
    <div class="card-body py-6">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5" id="location_table">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                        <th>ID</th>
                        <th>Kode</th>
                        <th>Area</th>
                        <th>Rack</th>
                        <th>Kolom</th>
                        <th>Baris</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<!--begin::Modal-->
<div class="modal fade" id="modal_location_form" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bolder" id="modal_location_title">Add Location</h2>
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
                <form class="form" id="location_form">
                    @csrf
                    <input type="hidden" name="location_id" id="location_id" />
                    <div class="fv-row mb-7">
                        <label class="required fs-6 fw-bold form-label mb-2">Area</label>
                        <select name="area_id" id="location_area_id" class="form-select form-select-solid" data-control="select2" data-placeholder="Pilih area" required>
                            <option value="">Pilih area</option>
                            @foreach($areas as $area)
                                <option value="{{ $area->id }}" data-code="{{ $area->code }}">{{ $area->code }} - {{ $area->name }}</option>
                            @endforeach
                        </select>
                        <div class="invalid-feedback" id="error_area_id"></div>
                    </div>
                    <div class="row g-3 mb-7">
                        <div class="col-md-6">
                            <label class="required fs-6 fw-bold form-label mb-2">Rack</label>
                            <input type="text" class="form-control form-control-solid" name="rack_code" id="location_rack_code" required />
                            <div class="invalid-feedback" id="error_rack_code"></div>
                        </div>
                        <div class="col-md-3">
                            <label class="required fs-6 fw-bold form-label mb-2">Kolom</label>
                            <input type="number" min="1" class="form-control form-control-solid" name="column_no" id="location_column_no" required />
                            <div class="invalid-feedback" id="error_column_no"></div>
                        </div>
                        <div class="col-md-3">
                            <label class="required fs-6 fw-bold form-label mb-2">Baris</label>
                            <input type="number" min="1" class="form-control form-control-solid" name="row_no" id="location_row_no" required />
                            <div class="invalid-feedback" id="error_row_no"></div>
                        </div>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fs-6 fw-bold form-label mb-2">Kode Lokasi</label>
                        <input type="text" class="form-control form-control-solid" id="location_code_preview" readonly />
                        <div class="form-text">Kode akan dibuat otomatis (Area-Rack-Kolom-Baris).</div>
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
<!--end::Modal-->
@endsection

@push('scripts')
<script>
    const csrfToken = '{{ csrf_token() }}';
    const dataUrl   = '{{ route('admin.masterdata.locations.data') }}';
    const storeUrl  = '{{ route('admin.masterdata.locations.store') }}';
    const updateTpl = '{{ route('admin.masterdata.locations.update', ':id') }}';
    const deleteTpl = '{{ route('admin.masterdata.locations.destroy', ':id') }}';
    const canUpdate = {{ $canUpdate ? 'true' : 'false' }};
    const canDelete = {{ $canDelete ? 'true' : 'false' }};

    document.addEventListener('DOMContentLoaded', () => {
        const tableEl = $('#location_table');
        const searchInput = document.querySelector('[data-kt-filter="search"]');
        const form = document.getElementById('location_form');
        const modalEl = document.getElementById('modal_location_form');
        const modal = modalEl ? new bootstrap.Modal(modalEl) : null;
        const formId = document.getElementById('location_id');
        const formArea = document.getElementById('location_area_id');
        const formRack = document.getElementById('location_rack_code');
        const formColumn = document.getElementById('location_column_no');
        const formRow = document.getElementById('location_row_no');
        const formCodePreview = document.getElementById('location_code_preview');
        const titleEl = document.getElementById('modal_location_title');

        if (!tableEl.length || !$.fn.DataTable) {
            console.error('DataTables unavailable');
            return;
        }

        if (typeof $ !== 'undefined' && $.fn.select2) {
            $(formArea).select2({ placeholder: 'Pilih area', allowClear: true, width: '100%' });
        }

        const refreshMenus = () => {
            if (window.KTMenu) {
                KTMenu.createInstances();
            }
        };

        const buildCode = () => {
            if (!formArea || !formRack || !formColumn || !formRow) return '';
            const areaOpt = formArea.options[formArea.selectedIndex];
            const areaCode = areaOpt ? (areaOpt.getAttribute('data-code') || '').trim() : '';
            const rack = (formRack.value || '').trim().toUpperCase();
            const col = parseInt(formColumn.value || '', 10);
            const row = parseInt(formRow.value || '', 10);
            if (!areaCode || !rack || !col || !row) return '';
            return `${areaCode}-${rack}-${String(col).padStart(2,'0')}-${String(row).padStart(2,'0')}`;
        };

        const syncPreview = () => {
            if (!formCodePreview) return;
            formCodePreview.value = buildCode();
        };

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
                { data: 'area_code', render: (val, type, row) => `${row.area_code || '-'} - ${row.area_name || '-'}` },
                { data: 'rack_code' },
                { data: 'column_no' },
                { data: 'row_no' },
                { data: 'id', orderable:false, searchable:false, className:'text-end', render: (data, type, row)=>{
                    const editItem = canUpdate ? `<div class="menu-item px-3"><a href="#" class="menu-link px-3 btn-edit" data-id="${data}" data-area-id="${row.area_id}" data-rack-code="${row.rack_code}" data-column-no="${row.column_no}" data-row-no="${row.row_no}">Edit</a></div>` : '';
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
                }}
            ]
        });
        refreshMenus();
        dt.on('draw', refreshMenus);

        const reloadTable = () => dt.ajax.reload();
        searchInput?.addEventListener('keyup', reloadTable);

        document.getElementById('btn_open_create')?.addEventListener('click', () => {
            if (!form) return;
            form.reset();
            formId.value = '';
            if (typeof $ !== 'undefined' && $(formArea).data('select2')) {
                $(formArea).val('').trigger('change');
            }
            syncPreview();
            clearErrors();
            if (titleEl) titleEl.textContent = 'Add Location';
        });

        const clearErrors = () => {
            ['error_area_id','error_rack_code','error_column_no','error_row_no'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.textContent = '';
            });
        };

        form?.addEventListener('submit', async (e) => {
            e.preventDefault();
            clearErrors();
            const id = formId.value;
            const url = id ? updateTpl.replace(':id', id) : storeUrl;
            const method = id ? 'PUT' : 'POST';
            const formData = new FormData(form);
            if (id) formData.append('_method', 'PUT');
            try {
                const res = await fetch(url, {
                    method: method === 'PUT' ? 'POST' : method,
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
                    if (typeof Swal !== 'undefined') Swal.fire('Error', 'Respons server tidak valid', 'error');
                    return;
                }
                if (!res.ok) {
                    if (json?.errors) {
                        Object.entries(json.errors).forEach(([key, msgs])=>{
                            const errEl = document.getElementById(`error_${key}`);
                            if (errEl) errEl.textContent = msgs.join(', ');
                        });
                    } else if (typeof Swal !== 'undefined') {
                        Swal.fire('Error', json.message || 'Gagal menyimpan lokasi', 'error');
                    }
                    return;
                }
                if (typeof Swal !== 'undefined') Swal.fire('Berhasil', json.message || 'Berhasil', 'success');
                modal?.hide();
                reloadTable();
            } catch (err) {
                console.error(err);
                if (typeof Swal !== 'undefined') Swal.fire('Error', 'Gagal menyimpan lokasi', 'error');
            }
        });

        tableEl.on('click', '.btn-edit', function(e) {
            e.preventDefault();
            const id = this.getAttribute('data-id');
            const areaId = this.getAttribute('data-area-id');
            const rackCode = this.getAttribute('data-rack-code');
            const columnNo = this.getAttribute('data-column-no');
            const rowNo = this.getAttribute('data-row-no');
            if (!form) return;
            formId.value = id;
            if (typeof $ !== 'undefined' && $(formArea).data('select2')) {
                $(formArea).val(areaId || '').trigger('change');
            } else if (formArea) {
                formArea.value = areaId || '';
            }
            if (formRack) formRack.value = rackCode || '';
            if (formColumn) formColumn.value = columnNo || '';
            if (formRow) formRow.value = rowNo || '';
            syncPreview();
            clearErrors();
            if (titleEl) titleEl.textContent = 'Edit Location';
            modal?.show();
        });

        tableEl.on('click', '.btn-delete', async function(e) {
            e.preventDefault();
            const id = this.getAttribute('data-id');
            let confirmed = true;
            if (typeof Swal !== 'undefined') {
                const res = await Swal.fire({
                    title: 'Apakah Anda yakin?',
                    text: 'Lokasi akan dihapus',
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
                const res = await fetch(deleteTpl.replace(':id', id), {
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
                try { json = JSON.parse(text); } catch (parseErr) {
                    console.error('Invalid JSON', text);
                    if (typeof Swal !== 'undefined') Swal.fire('Error', 'Respons server tidak valid', 'error');
                    return;
                }
                if (!res.ok) {
                    if (typeof Swal !== 'undefined') Swal.fire('Error', json.message || 'Gagal menghapus lokasi', 'error');
                    return;
                }
                if (typeof Swal !== 'undefined') Swal.fire('Berhasil', json.message || 'Berhasil', 'success');
                reloadTable();
            } catch (err) {
                console.error(err);
                if (typeof Swal !== 'undefined') Swal.fire('Error', 'Gagal menghapus lokasi', 'error');
            }
        });

        [formArea, formRack, formColumn, formRow].forEach((el) => {
            el?.addEventListener('input', syncPreview);
            el?.addEventListener('change', syncPreview);
        });
    });
</script>
@endpush

@include('layouts.partials.form-submit-confirmation')

