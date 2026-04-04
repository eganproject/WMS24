@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
@endpush

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Assembly Recipes',
        'breadcrumbs' => ['Admin', 'Masterdata', 'Assembly Recipes'],
    ])
@endpush

@section('content')
    @php
        $permissionResolver = app(\App\Support\MenuPermissionResolver::class);
        $canCreate = $permissionResolver->userCan('create');
        $canEdit = $permissionResolver->userCan('edit');
        $canDelete = $permissionResolver->userCan('delete');
    @endphp
    <div class="content flex-row-fluid" id="kt_content">
        <div class="card">
            <div class="card-header border-0 pt-6">
                <div class="card-title">
                    <div class="d-flex align-items-center position-relative my-1">
                        <span class="svg-icon svg-icon-1 position-absolute ms-6">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                <rect opacity="0.5" x="17.0365" y="15.1223" width="8.15546" height="2" rx="1" transform="rotate(45 17.0365 15.1223)" fill="black"></rect>
                                <path d="M11 19C6.55556 19 3 15.4444 3 11C3 6.55556 6.55556 3 11 3C15.4444 3 19 6.55556 19 11C19 15.4444 15.4444 19 11 19ZM11 5C7.53333 5 5 7.53333 5 11C5 14.4667 7.53333 17 11 17C14.4667 17 17 14.4667 17 11C17 7.53333 14.4667 5 11 5Z" fill="black"></path>
                            </svg>
                        </span>
                        <form method="GET" action="{{ route('admin.masterdata.assemblyrecipes.index') }}" class="d-flex align-items-center gap-2">
                            <input type="text" name="search" value="{{ $search ?? '' }}" class="form-control form-control-solid w-250px ps-15" placeholder="Cari Recipe">
                            <button type="submit" class="btn btn-light">Cari</button>
                        </form>
                    </div>
                </div>
                <div class="card-toolbar">
                    @if ($canCreate)
                        <div class="d-flex justify-content-end">
                            <a href="{{ route('admin.masterdata.assemblyrecipes.create') }}" class="btn btn-primary">Tambah Recipe</a>
                        </div>
                    @endif
                </div>
            </div>
            <div class="card-body pt-4">
                <div class="dataTables_wrapper dt-bootstrap4 no-footer">
                    <div class="table-responsive">
                        <table class="table align-middle table-row-dashed fs-6 gy-5" id="table-on-page">
                            <thead>
                            <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                <th class="sorting">No</th>
                                <th class="min-w-125px sorting">Kode</th>
                                <th class="min-w-200px sorting">Deskripsi</th>
                                <th class="min-w-200px sorting">Produk Jadi</th>
                                <th class="min-w-100px sorting">Output</th>
                                <th class="min-w-100px sorting">Aktif</th>
                                <th class="min-w-125px sorting">Actions</th>
                            </tr>
                            </thead>
                            <tbody class="fw-bold text-gray-600">
                            @forelse($recipes as $recipe)
                                <tr>
                                    <td>{{ ($recipes->currentPage() - 1) * $recipes->perPage() + $loop->iteration }}</td>
                                    <td>{{ $recipe->code }}</td>
                                    <td>{{ $recipe->description ?? '-' }}</td>
                                    <td>{{ $recipe->finishedItem->nama_barang ?? '-' }} ({{ $recipe->finishedItem->sku ?? '-' }})</td>
                                    <td>{{ number_format($recipe->output_quantity, 2, ',', '.') }}</td>
                                    <td>
                                        @if($recipe->is_active)
                                            <span class="badge badge-light-success">Aktif</span>
                                        @else
                                            <span class="badge badge-light-danger">Nonaktif</span>
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $permissionResolver = app(\App\Support\MenuPermissionResolver::class);
                                            $canEdit = $permissionResolver->userCan('edit');
                                            $canDelete = $permissionResolver->userCan('delete');
                                        @endphp
                                        @if ($canEdit || $canDelete)
                                            <a href="#" class="btn btn-sm btn-light btn-active-light-primary" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">Actions
                                                <span class="svg-icon svg-icon-5 m-0">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                                        <path d="M11.4343 12.7344L7.25 8.55005C6.83579 8.13583 6.16421 8.13584 5.75 8.55005C5.33579 8.96426 5.33579 9.63583 5.75 10.05L11.2929 15.5929C11.6834 15.9835 12.3166 15.9835 12.7071 15.5929L18.25 10.05C18.6642 9.63584 18.6642 8.96426 18.25 8.55005C17.8358 8.13584 17.1642 8.13584 16.75 8.55005L12.5657 12.7344C12.2533 13.0468 11.7467 13.0468 11.4343 12.7344Z" fill="black"></path>
                                                    </svg>
                                                </span>
                                            </a>
                                            <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-600 menu-state-bg-light-primary fw-bold fs-7 w-125px py-4" data-kt-menu="true">
                                                @if ($canEdit)
                                                    <div class="menu-item px-3">
                                                        <a href="{{ route('admin.masterdata.assemblyrecipes.edit', $recipe->id) }}" class="menu-link px-3">Edit</a>
                                                    </div>
                                                @endif
                                                @if ($canDelete)
                                                    <div class="menu-item px-3">
                                                        <form action="{{ route('admin.masterdata.assemblyrecipes.destroy', $recipe->id) }}" method="POST" class="form-delete">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="menu-link px-3 bg-transparent border-0 p-0" data-recipe-name="{{ $recipe->code }}">Delete</button>
                                                        </form>
                                                    </div>
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-muted text-center">Tidak ada data</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                        <div class="mt-4">
                            {{ $recipes->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        toastr.options = {
            "closeButton": true,
            "debug": false,
            "newestOnTop": false,
            "progressBar": true,
            "positionClass": "toast-top-center",
            "preventDuplicates": false,
            "showDuration": "300",
            "hideDuration": "1000",
            "timeOut": "5000",
            "extendedTimeOut": "1000",
            "showEasing": "swing",
            "hideEasing": "linear",
            "showMethod": "fadeIn",
            "hideMethod": "fadeOut"
        };

        @if (Session::has('success'))
            toastr.success("{{ session('success') }}");
        @endif

        @if (Session::has('error'))
            toastr.error("{{ session('error') }}");
        @endif

        // no datatable; using simple pagination and search submit

        const canDelete = @json((bool) $canDelete);
        if (canDelete) {
            $('#table-on-page').on('submit', '.form-delete', function(e) {
                e.preventDefault();

                var form = $(this);
                var n = form.find('button[data-recipe-name]').data('recipe-name');

                Swal.fire({
                    text: "Apakah yakin ingin menghapus data " + n + "?",
                    icon: "warning",
                    showCancelButton: true,
                    buttonsStyling: false,
                    confirmButtonText: "Ya, hapus!",
                    cancelButtonText: "Tidak, batalkan",
                    customClass: {
                        confirmButton: "btn fw-bold btn-danger",
                        cancelButton: "btn fw-bold btn-active-light-primary"
                    }
                }).then(function(result) {
                    if (result.value) {
                        form.off('submit');
                        form.trigger('submit');
                    } else if (result.dismiss === 'cancel') {
                        toastr.info("Penghapusan data " + n + " dibatalkan.");
                    }
                });
            });
        }
    });
</script>
@endpush
