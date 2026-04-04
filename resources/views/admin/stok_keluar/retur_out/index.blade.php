@extends('layouts.app')

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Retur Out',
        'breadcrumbs' => ['Admin', 'Stok Keluar', 'Retur Out'],
    ])
@endpush

@php
    $permissionResolver = app(\App\Support\MenuPermissionResolver::class);
    $canCreate = $permissionResolver->userCan('create');
    $canEdit = $permissionResolver->userCan('edit');
    $canDelete = $permissionResolver->userCan('delete');
    $canApprove = $permissionResolver->userCan('approve');
@endphp

@section('content')
<div class="content flex-row-fluid" id="kt_content">
    <div class="row g-5 g-xl-8">
        <div class="col-12">
            <div class="card card-xl-stretch mb-xl-8">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <div class="symbol symbol-50px me-5">
                            <div class="symbol-label bg-light-primary"><i class="fas fa-undo-alt fs-2x text-primary"></i></div>
                        </div>
                        <div>
                            <div class="text-dark fw-bolder fs-5 mb-1">Status Retur Out</div>
                            <div class="text-muted fw-bold">Dokumen pengembalian keluar</div>
                        </div>
                    </div>
                    <div class="d-flex gap-5">
                        <div class="text-center">
                            <div class="badge badge-light-secondary fs-5 fw-bolder" id="status_draft">0</div>
                            <div class="text-muted fs-8 mt-1">Draft</div>
                        </div>
                        <div class="text-center">
                            <div class="badge badge-light-success fs-5 fw-bolder" id="status_completed">0</div>
                            <div class="text-muted fs-8 mt-1">Completed</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-header border-0 pt-6 d-flex justify-content-between align-items-center">
            <h3 class="card-title fw-bolder">Daftar Retur Out</h3>
            @if ($canCreate)
                <a href="{{ route('admin.stok-keluar.retur-out.create') }}" class="btn btn-primary">Tambah</a>
            @endif
        </div>
        <div class="card-body pt-0">
            <div class="table-responsive min-h-500px">
                <table class="table align-middle table-row-dashed fs-6 gy-5 dataTable no-footer" id="ri-table">
                    <thead>
                        <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                            <th class="min-w-125px">Kode</th>
                            <th class="min-w-125px">Tanggal</th>
                            <th class="min-w-150px">Gudang</th>
                            <th class="min-w-250px">Produk</th>
                            <th class="min-w-125px">Status</th>
                            <th class="text-center min-w-100px">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="fw-bold text-gray-600"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(function(){
    const canEdit = @json($canEdit);
    const canDelete = @json($canDelete);
    const canApprove = @json($canApprove);
    const csrf = `{{ csrf_token() }}`;

    function loadCounts(){
        $.get(`{{ route('admin.stok-keluar.retur-out.status-counts') }}`, function(res){
            $('#status_draft').text(res.draft ?? 0);
            $('#status_completed').text(res.completed ?? 0);
        });
    }

    const table = $('#ri-table').DataTable({
        processing: true,
        serverSide: true,
        searching: true,
        ajax: {
            url: `{{ route('admin.stok-keluar.retur-out.index') }}`,
            data: function(d){ }
        },
        columns: [
            { data: 'code', name: 'ro.code' },
            { data: 'return_date', name: 'ro.return_date' },
            { data: 'warehouse', name: 'w.name' },
            { data: 'items', name: 'i.sku', orderable: false, searchable: false },
            { data: 'status', name: 'ro.status' },
            { data: 'id', name: 'ro.id', orderable: false, searchable: false },
        ],
        order: [[1, 'desc']],
        columnDefs: [
            { targets: 1, render: function(data){ if(!data) return '-'; const d = new Date(data); return d.toLocaleDateString('id-ID',{day:'2-digit',month:'short',year:'numeric'}); } },
            { targets: 4, render: function(data){ const badge = data==='completed'?'badge-light-success':'badge-light-secondary'; return `<span class="badge ${badge}">${data}</span>`; } },
            { targets: 5, render: function(data,type,row){
                const showUrl = `{{ route('admin.stok-keluar.retur-out.show', ':id') }}`.replace(':id', row.id);
                const editUrl = `{{ route('admin.stok-keluar.retur-out.edit', ':id') }}`.replace(':id', row.id);
                const delUrl = `{{ route('admin.stok-keluar.retur-out.destroy', ':id') }}`.replace(':id', row.id);
                const completeUrl = `{{ route('admin.stok-keluar.retur-out.complete', ':id') }}`.replace(':id', row.id);
                let html = `
                <a href="#" class="btn btn-sm btn-light btn-active-light-primary" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">Actions
                    <span class="svg-icon svg-icon-5 m-0"></span>
                </a>
                <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-600 menu-state-bg-light-primary fw-bold fs-7 w-150px py-4" data-kt-menu="true">
                    <div class="menu-item px-3"><a href="${showUrl}" class="menu-link px-3">Detail</a></div>`;
                if (canEdit) html += `<div class="menu-item px-3"><a href="${editUrl}" class="menu-link px-3">Edit</a></div>`;
                if (row.status === 'draft' && canApprove) html += `<div class="menu-item px-3"><button type="button" class="menu-link px-3 border-0 bg-transparent w-100 text-start action-complete" data-url="${completeUrl}" data-code="${row.code}">Complete</button></div>`;
                if (canDelete) html += `<div class="menu-item px-3"><form method="POST" action="${delUrl}" class="ri-del"><input type="hidden" name="_token" value="${csrf}" /><input type="hidden" name="_method" value="DELETE" /><button type="submit" class="menu-link px-3 border-0 bg-transparent w-100 text-start">Delete</button></form></div>`;
                html += `</div>`; return html;
            } },
        ],
        drawCallback: function(){ loadCounts(); if (typeof KTMenu !== 'undefined' && KTMenu.createInstances) { KTMenu.createInstances(); } }
    });

    $('#ri-table').on('submit','.ri-del', function(e){ e.preventDefault(); const f=this; $.post($(f).attr('action'), $(f).serialize()).done(()=>table.ajax.reload(null,false)).fail(()=>toastr.error('Gagal menghapus')); });
    $('#ri-table').on('click','.action-complete', function(){ const url=$(this).data('url'); const code=$(this).data('code'); Swal.fire({text:`Selesaikan dokumen ${code}?`, icon:'question', showCancelButton:true, buttonsStyling:false, confirmButtonText:'Ya, selesaikan!', cancelButtonText:'Batal', customClass:{confirmButton:'btn fw-bold btn-primary', cancelButton:'btn fw-bold btn-active-light-light'}}).then(function(res){ if(res.value){ $.post(url,{_token:csrf}).done((r)=>{ if(r.success){ toastr.success(r.message||'Berhasil'); table.ajax.reload(null,false); loadCounts(); } else { toastr.error(r.message||'Gagal'); } }).fail((xhr)=>{ toastr.error(xhr.responseJSON?.message||'Gagal'); }); } }); });

    loadCounts();
});
</script>
@endpush
