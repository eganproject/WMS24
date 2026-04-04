@extends('layouts.app')

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Penerimaan Retur',
        'breadcrumbs' => ['Admin', 'Stok Masuk', 'Penerimaan Retur'],
    ])
@endpush

@php
    $permissionResolver = app(\App\Support\MenuPermissionResolver::class);
    $canCreate = $permissionResolver->userCan('create');
    $canEdit = $permissionResolver->userCan('edit');
    $canDelete = $permissionResolver->userCan('delete');
    $canApprove = $permissionResolver->userCan('approve');
@endphp
@push('styles')
    <style>
        .status-card-icon .symbol-label { background: #F3E8FF; }
        .status-card-icon i { color: #7C3AED; }
        .status-metrics { display: flex; gap: 1.5rem; align-items: center; flex-wrap: wrap; justify-content: flex-end; }
        .status-metrics .metric { text-align: center; min-width: 88px; }
        .status-metrics .chip { display: inline-block; padding: 6px 12px; border-radius: 10px; font-weight: 700; font-size: 1rem; line-height: 1; }
        .chip-secondary { background: #EEF2FF; color: #4F46E5; }
        .chip-success { background: #ECFDF5; color: #047857; }
        @media (max-width: 576px) { .status-metrics { justify-content: flex-start; } .status-metrics .metric { min-width: 70px; } }
    </style>
@endpush

@section('content')
<div class="content flex-row-fluid" id="kt_content">
    <div class="row g-5 g-xl-8">
        <div class="col-12">
            <div class="card card-xl-stretch mb-xl-8">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between flex-wrap">
                        <div class="d-flex align-items-center mb-4 mb-md-0">
                            <div class="symbol symbol-50px me-5 status-card-icon">
                                <div class="symbol-label">
                                    <i class="fas fa-undo-alt fs-2x"></i>
                                </div>
                            </div>
                            <div>
                                <div class="text-dark fw-bolder fs-5 mb-1">Status Penerimaan Retur</div>
                                <div class="text-muted fw-bold">Dokumen Return Receipt</div>
                            </div>
                        </div>
                        <div class="status-metrics">
                            <div class="metric">
                                <div class="chip chip-secondary" id="status_draft">
                                    <div class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">Loading...</span></div>
                                </div>
                                <div class="text-muted fs-8 mt-1">Draft</div>
                            </div>
                            <div class="metric">
                                <div class="chip chip-success" id="status_completed">
                                    <div class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">Loading...</span></div>
                                </div>
                                <div class="text-muted fs-8 mt-1">Completed</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-header border-0 pt-6 d-flex justify-content-between align-items-center">
            <h3 class="card-title fw-bolder">Daftar Penerimaan Retur</h3>
            @if ($canCreate)
                <a href="{{ route('admin.stok-masuk.penerimaan-retur.create') }}" class="btn btn-primary">Tambah</a>
            @endif
        </div>
        <div class="card-body pt-0">
            <div class="table-responsive min-h-500px">
                <table class="table align-middle table-row-dashed fs-6 gy-5 dataTable no-footer" id="rr-table">
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
    const canCreate = @json($canCreate);
    function loadStatusCounts(){
        $.get(`{{ route('admin.stok-masuk.penerimaan-retur.status-counts') }}`, function(res){
            $('#status_draft').text(res.draft ?? 0);
            $('#status_completed').text(res.completed ?? 0);
        });
    }

    if ($.fn.DataTable.isDataTable('#rr-table')) {
        $('#rr-table').DataTable().destroy();
    }

    const table = $('#rr-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: `{{ route('admin.stok-masuk.penerimaan-retur.index') }}`,
            type: 'GET',
            data: function(d){
                d.search.value = $('#search_input').val();
            }
        },
        columns: [
            { data: 'code', name: 'code' },
            { data: 'return_date', name: 'return_date' },
            { data: 'warehouse', name: 'warehouse' },
            { data: 'items', name: 'items', orderable: false, searchable: false },
            { data: 'status', name: 'status' },
            { data: 'id', name: 'id', orderable: false, searchable: false },
        ],
        order: [[1,'desc']],
        columnDefs: [
            { targets: 1, render: function(data){ if(!data) return '-'; const d=new Date(data); return d.toLocaleDateString('id-ID',{day:'2-digit', month:'short', year:'numeric'}); } },
            { targets: 4, render: function(data){ const badge = data==='completed'?'badge-light-success':'badge-light-secondary'; return `<span class="badge ${badge}">${data}</span>`; } },
            { targets: 5, render: function(data,type,row){
                const showUrl = `{{ route('admin.stok-masuk.penerimaan-retur.show', ':id') }}`.replace(':id', row.id);
                const editUrl = `{{ route('admin.stok-masuk.penerimaan-retur.edit', ':id') }}`.replace(':id', row.id);
                const delUrl = `{{ route('admin.stok-masuk.penerimaan-retur.destroy', ':id') }}`.replace(':id', row.id);
                const completeUrl = `{{ route('admin.stok-masuk.penerimaan-retur.complete', ':id') }}`.replace(':id', row.id);
                const csrfToken = `{{ csrf_token() }}`;
                let html = `
                <a href="#" class="btn btn-sm btn-light btn-active-light-primary" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">Actions
                    <span class="svg-icon svg-icon-5 m-0"></span>
                </a>
                <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-600 menu-state-bg-light-primary fw-bold fs-7 w-150px py-4" data-kt-menu="true">
                    <div class="menu-item px-3"><a href="${showUrl}" class="menu-link px-3">Detail</a></div>`;

                if (canEdit) {
                    html += `<div class="menu-item px-3"><a href="${editUrl}" class="menu-link px-3">Edit</a></div>`;
                }
                if (row.status === 'draft' && canApprove) {
                    html += `<div class="menu-item px-3">
                        <button type="button" class="menu-link px-3 border-0 bg-transparent w-100 text-start action-complete" data-complete-url="${completeUrl}" data-document-code="${row.code}">Complete</button>
                    </div>`;
                }
                if (canDelete) {
                    html += `<div class="menu-item px-3">
                        <form method="POST" action="${delUrl}" class="rr-del">
                            <input type="hidden" name="_token" value="${csrfToken}" />
                            <input type="hidden" name="_method" value="DELETE" />
                            <button type="submit" class="menu-link px-3 border-0 bg-transparent w-100 text-start">Delete</button>
                        </form>
                    </div>`;
                }
                html += `</div>`;
                return html;
            } }
        ],
        drawCallback: function(){
            loadStatusCounts();
            if (typeof KTMenu !== 'undefined' && KTMenu.createInstances) { KTMenu.createInstances(); }
        }
    });

    // Debounced search using global search input if present
    const debounce = (callback, wait=400) => { let t; return (...args)=>{ clearTimeout(t); t=setTimeout(()=>callback.apply(null,args), wait); }; };
    $('#search_input').on('keyup', debounce(function(){ table.draw(); }));

    // Delete action
    $('#rr-table').on('submit','.rr-del', function(e){
        e.preventDefault();
        const f = this;
        $.post($(f).attr('action'), $(f).serialize())
            .done(function(){ table.ajax.reload(null,false); })
            .fail(function(){ toastr.error('Gagal menghapus dokumen.'); });
    });

    // Complete action
    $('#rr-table').on('click', '.action-complete', function(e){
        e.preventDefault();
        const url = $(this).data('complete-url');
        const code = $(this).data('document-code');
        Swal.fire({
            text: `Setujui dan selesaikan dokumen ${code}?`,
            icon: 'question',
            showCancelButton: true,
            buttonsStyling: false,
            confirmButtonText: 'Ya, selesaikan!',
            cancelButtonText: 'Batal',
            customClass: { confirmButton: 'btn fw-bold btn-primary', cancelButton: 'btn fw-bold btn-active-light-light' }
        }).then(function(result){
            if(result.value){
                $.ajax({
                    url: url,
                    type: 'POST',
                    data: { _token: `{{ csrf_token() }}` },
                    success: function(res){
                        if(res.success){ toastr.success(res.message || 'Berhasil menyelesaikan dokumen.'); table.ajax.reload(null,false); loadStatusCounts(); }
                        else { toastr.error(res.message || 'Gagal menyelesaikan.'); }
                    },
                    error: function(xhr){ const msg = xhr.responseJSON?.message || 'Terjadi kesalahan saat menyelesaikan dokumen.'; toastr.error(msg); }
                });
            }
        });
    });

    // Initial load of counts
    loadStatusCounts();
});
</script>
@endpush
