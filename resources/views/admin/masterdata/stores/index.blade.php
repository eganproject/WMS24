@extends('layouts.admin')

@section('title', 'Toko & Channel')
@section('page_title', 'Toko & Channel')

@php
    use App\Support\Permission as Perm;
    $canCreate = Perm::can(auth()->user(), 'admin.masterdata.stores.index', 'create');
    $canUpdate = Perm::can(auth()->user(), 'admin.masterdata.stores.index', 'update');
    $canDelete = Perm::can(auth()->user(), 'admin.masterdata.stores.index', 'delete');
@endphp

@section('content')
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div>
                <div class="fs-4 fw-bold">Master Data Toko & Channel</div>
                <div class="text-muted fs-7 mt-1">Nama baru dari import resi akan otomatis masuk ke master data ini.</div>
            </div>
        </div>
    </div>
    <div class="card-body pt-3">
        <ul class="nav nav-tabs nav-line-tabs nav-line-tabs-2x fs-6 fw-bold mb-7" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab_toko" type="button" role="tab">Toko</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab_channel" type="button" role="tab">Channel</button>
            </li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="tab_toko" role="tabpanel">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-5">
                    <div class="d-flex flex-wrap gap-3">
                        <input type="text" class="form-control form-control-solid w-250px" id="store_search" placeholder="Cari nama atau alamat toko" />
                        <select id="store_pic_filter" class="form-select form-select-solid w-200px" data-placeholder="Semua PIC">
                            <option value="">Semua PIC</option>
                            @foreach($pics as $pic)
                                <option value="{{ $pic->id }}">{{ $pic->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if($canCreate)
                        <button type="button" class="btn btn-primary" id="store_create_button" data-bs-toggle="modal" data-bs-target="#store_modal">Tambah Toko</button>
                    @endif
                </div>
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-5" id="stores_table">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                <th>ID</th>
                                <th>Logo</th>
                                <th>Nama</th>
                                <th>PIC</th>
                                <th>Alamat</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            <div class="tab-pane fade" id="tab_channel" role="tabpanel">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-5">
                    <input type="text" class="form-control form-control-solid w-250px" id="channel_search" placeholder="Cari channel" />
                    @if($canCreate)
                        <button type="button" class="btn btn-primary" id="channel_create_button" data-bs-toggle="modal" data-bs-target="#channel_modal">Tambah Channel</button>
                    @endif
                </div>
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-5" id="channels_table">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                <th>ID</th>
                                <th>Nama</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="store_modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bolder" id="store_modal_title">Tambah Toko</h2>
                <button type="button" class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal" aria-label="Tutup">
                    <span class="svg-icon svg-icon-1">&times;</span>
                </button>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <form id="store_form">
                    @csrf
                    <input type="hidden" id="store_id" />
                    <div class="mb-7">
                        <label class="required form-label fw-bold">Nama Toko</label>
                        <input type="text" class="form-control form-control-solid" name="name" id="store_name" maxlength="150" required />
                        <div class="invalid-feedback d-block" id="store_error_name"></div>
                    </div>
                    <div class="mb-7">
                        <label class="form-label fw-bold">PIC</label>
                        <select class="form-select form-select-solid" name="pic_id" id="store_pic_id" data-placeholder="Pilih PIC">
                            <option value="">Pilih PIC</option>
                            @foreach($pics as $pic)
                                <option value="{{ $pic->id }}">{{ $pic->name }}</option>
                            @endforeach
                        </select>
                        <div class="invalid-feedback d-block" id="store_error_pic_id"></div>
                    </div>
                    <div class="mb-7">
                        <label class="form-label fw-bold">Alamat</label>
                        <textarea class="form-control form-control-solid" name="address" id="store_address" rows="3"></textarea>
                        <div class="invalid-feedback d-block" id="store_error_address"></div>
                    </div>
                    <div class="mb-7">
                        <label class="form-label fw-bold">Logo</label>
                        <div class="mb-3"><img id="store_logo_preview" src="{{ asset('metronic/media/logos/logo-demo11.svg') }}" alt="Logo toko" class="w-60px h-60px rounded object-cover"></div>
                        <input type="file" class="form-control form-control-solid" name="logo" id="store_logo" accept=".jpg,.jpeg,.png" />
                        <div class="invalid-feedback d-block" id="store_error_logo"></div>
                    </div>
                    <div class="text-end">
                        <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="channel_modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-500px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bolder" id="channel_modal_title">Tambah Channel</h2>
                <button type="button" class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal" aria-label="Tutup">
                    <span class="svg-icon svg-icon-1">&times;</span>
                </button>
            </div>
            <div class="modal-body mx-5 mx-xl-15 my-7">
                <form id="channel_form">
                    @csrf
                    <input type="hidden" id="channel_id" />
                    <div class="mb-7">
                        <label class="required form-label fw-bold">Nama Channel</label>
                        <input type="text" class="form-control form-control-solid" name="name" id="channel_name" maxlength="150" required />
                        <div class="invalid-feedback d-block" id="channel_error_name"></div>
                    </div>
                    <div class="text-end">
                        <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const csrfToken = '{{ csrf_token() }}';
    const canUpdate = {{ $canUpdate ? 'true' : 'false' }};
    const canDelete = {{ $canDelete ? 'true' : 'false' }};
    const defaultLogo = @json(asset('metronic/media/logos/logo-demo11.svg'));
    const urls = {
        stores: {
            data: @json(route('admin.masterdata.stores.data')),
            store: @json(route('admin.masterdata.stores.store')),
            update: @json(route('admin.masterdata.stores.update', ':id')),
            destroy: @json(route('admin.masterdata.stores.destroy', ':id')),
        },
        channels: {
            data: @json(route('admin.masterdata.stores.channels.data')),
            store: @json(route('admin.masterdata.stores.channels.store')),
            update: @json(route('admin.masterdata.stores.channels.update', ':id')),
            destroy: @json(route('admin.masterdata.stores.channels.destroy', ':id')),
        },
    };

    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    const notify = (title, message, icon) => {
        if (typeof Swal !== 'undefined') return Swal.fire(title, message, icon);
        window.alert(message);
    };
    const parseResponse = async (response) => {
        const text = await response.text();
        try { return JSON.parse(text); } catch (error) { return { message: 'Respons server tidak valid.' }; }
    };
    const actionButtons = (type) => {
        const edit = canUpdate ? `<button type="button" class="btn btn-sm btn-light-primary me-2 js-${type}-edit">Edit</button>` : '';
        const remove = canDelete ? `<button type="button" class="btn btn-sm btn-light-danger js-${type}-delete">Hapus</button>` : '';
        return `<div class="text-end">${edit}${remove}</div>`;
    };
    const confirmDelete = async (label) => {
        if (typeof Swal === 'undefined') return window.confirm(`${label} akan dihapus. Lanjutkan?`);
        const result = await Swal.fire({
            title: 'Apakah Anda yakin?',
            text: `${label} akan dihapus. Relasi pada resi lama akan dikosongkan.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Hapus',
            cancelButtonText: 'Batal',
            buttonsStyling: false,
            customClass: { confirmButton: 'btn btn-danger', cancelButton: 'btn btn-light' },
        });
        return result.isConfirmed;
    };

    if (!$.fn.DataTable) return;

    const storeTableEl = $('#stores_table');
    const storeSearch = document.getElementById('store_search');
    const storePicFilter = document.getElementById('store_pic_filter');
    const storeTable = storeTableEl.DataTable({
        processing: true,
        serverSide: true,
        dom: 'rtip',
        order: [[0, 'desc']],
        ajax: {
            url: urls.stores.data,
            dataSrc: 'data',
            data: (params) => {
                params.q = storeSearch?.value || '';
                params.pic_id = storePicFilter?.value || '';
            },
        },
        columns: [
            { data: 'id' },
            { data: 'logo_url', orderable: false, searchable: false, render: (value) => `<img src="${escapeHtml(value)}" alt="Logo" class="w-40px h-40px rounded object-cover">` },
            { data: 'name', render: escapeHtml },
            { data: 'pic', render: escapeHtml },
            { data: 'address', render: escapeHtml },
            { data: null, orderable: false, searchable: false, className: 'text-end', render: () => actionButtons('store') },
        ],
    });
    storeSearch?.addEventListener('input', () => storeTable.ajax.reload());
    storePicFilter?.addEventListener('change', () => storeTable.ajax.reload());

    const channelTableEl = $('#channels_table');
    const channelSearch = document.getElementById('channel_search');
    const channelTable = channelTableEl.DataTable({
        processing: true,
        serverSide: true,
        dom: 'rtip',
        order: [[0, 'desc']],
        ajax: {
            url: urls.channels.data,
            dataSrc: 'data',
            data: (params) => { params.q = channelSearch?.value || ''; },
        },
        columns: [
            { data: 'id' },
            { data: 'name', render: escapeHtml },
            { data: null, orderable: false, searchable: false, className: 'text-end', render: () => actionButtons('channel') },
        ],
    });
    channelSearch?.addEventListener('input', () => channelTable.ajax.reload());
    document.querySelector('[data-bs-target="#tab_channel"]')?.addEventListener('shown.bs.tab', () => channelTable.columns.adjust());

    const storeModalEl = document.getElementById('store_modal');
    const storeModal = new bootstrap.Modal(storeModalEl);
    const storeForm = document.getElementById('store_form');
    const storeId = document.getElementById('store_id');
    const storeName = document.getElementById('store_name');
    const storePic = document.getElementById('store_pic_id');
    const storeAddress = document.getElementById('store_address');
    const storeLogo = document.getElementById('store_logo');
    const storeLogoPreview = document.getElementById('store_logo_preview');
    const clearStoreErrors = () => ['name', 'pic_id', 'address', 'logo'].forEach((key) => {
        const element = document.getElementById(`store_error_${key}`);
        if (element) element.textContent = '';
    });
    if ($.fn.select2) {
        $(storePicFilter).select2({ allowClear: true, width: '100%', placeholder: 'Semua PIC' });
        $(storePic).select2({ allowClear: true, width: '100%', placeholder: 'Pilih PIC', dropdownParent: $('#store_modal') });
    }
    document.getElementById('store_create_button')?.addEventListener('click', () => {
        storeForm.reset();
        storeId.value = '';
        storeLogoPreview.src = defaultLogo;
        $('#store_pic_id').val('').trigger('change');
        document.getElementById('store_modal_title').textContent = 'Tambah Toko';
        clearStoreErrors();
    });
    storeTableEl.on('click', '.js-store-edit', function () {
        const row = storeTable.row($(this).closest('tr')).data();
        if (!row) return;
        storeForm.reset();
        clearStoreErrors();
        storeId.value = row.id;
        storeName.value = row.name || '';
        storeAddress.value = row.address === '-' ? '' : (row.address || '');
        storeLogoPreview.src = row.logo_url || defaultLogo;
        $('#store_pic_id').val(row.pic_id || '').trigger('change');
        document.getElementById('store_modal_title').textContent = 'Edit Toko';
        storeModal.show();
    });
    storeForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        clearStoreErrors();
        const id = storeId.value;
        const formData = new FormData(storeForm);
        if (id) formData.append('_method', 'PUT');
        const response = await fetch(id ? urls.stores.update.replace(':id', id) : urls.stores.store, {
            method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }, body: formData,
        });
        const json = await parseResponse(response);
        if (!response.ok) {
            Object.entries(json.errors || {}).forEach(([key, messages]) => {
                const element = document.getElementById(`store_error_${key}`);
                if (element) element.textContent = messages.join(', ');
            });
            if (!json.errors) notify('Error', json.message || 'Gagal menyimpan toko.', 'error');
            return;
        }
        storeModal.hide();
        storeTable.ajax.reload(null, false);
        notify('Berhasil', json.message || 'Toko berhasil disimpan.', 'success');
    });
    storeTableEl.on('click', '.js-store-delete', async function () {
        const row = storeTable.row($(this).closest('tr')).data();
        if (!row || !await confirmDelete(`Toko ${row.name}`)) return;
        const response = await fetch(urls.stores.destroy.replace(':id', row.id), {
            method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ _method: 'DELETE' }),
        });
        const json = await parseResponse(response);
        if (!response.ok) return notify('Error', json.message || 'Gagal menghapus toko.', 'error');
        storeTable.ajax.reload(null, false);
        notify('Berhasil', json.message || 'Toko berhasil dihapus.', 'success');
    });

    const channelModalEl = document.getElementById('channel_modal');
    const channelModal = new bootstrap.Modal(channelModalEl);
    const channelForm = document.getElementById('channel_form');
    const channelId = document.getElementById('channel_id');
    const channelName = document.getElementById('channel_name');
    const channelError = document.getElementById('channel_error_name');
    document.getElementById('channel_create_button')?.addEventListener('click', () => {
        channelForm.reset();
        channelId.value = '';
        channelError.textContent = '';
        document.getElementById('channel_modal_title').textContent = 'Tambah Channel';
    });
    channelTableEl.on('click', '.js-channel-edit', function () {
        const row = channelTable.row($(this).closest('tr')).data();
        if (!row) return;
        channelId.value = row.id;
        channelName.value = row.name || '';
        channelError.textContent = '';
        document.getElementById('channel_modal_title').textContent = 'Edit Channel';
        channelModal.show();
    });
    channelForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        channelError.textContent = '';
        const id = channelId.value;
        const formData = new FormData(channelForm);
        if (id) formData.append('_method', 'PUT');
        const response = await fetch(id ? urls.channels.update.replace(':id', id) : urls.channels.store, {
            method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }, body: formData,
        });
        const json = await parseResponse(response);
        if (!response.ok) {
            channelError.textContent = json.errors?.name?.join(', ') || '';
            if (!json.errors) notify('Error', json.message || 'Gagal menyimpan channel.', 'error');
            return;
        }
        channelModal.hide();
        channelTable.ajax.reload(null, false);
        notify('Berhasil', json.message || 'Channel berhasil disimpan.', 'success');
    });
    channelTableEl.on('click', '.js-channel-delete', async function () {
        const row = channelTable.row($(this).closest('tr')).data();
        if (!row || !await confirmDelete(`Channel ${row.name}`)) return;
        const response = await fetch(urls.channels.destroy.replace(':id', row.id), {
            method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ _method: 'DELETE' }),
        });
        const json = await parseResponse(response);
        if (!response.ok) return notify('Error', json.message || 'Gagal menghapus channel.', 'error');
        channelTable.ajax.reload(null, false);
        notify('Berhasil', json.message || 'Channel berhasil dihapus.', 'success');
    });
});
</script>
@endpush
