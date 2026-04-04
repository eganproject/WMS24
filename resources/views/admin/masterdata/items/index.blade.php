@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
@endpush

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Items',
        'breadcrumbs' => ['Admin', 'Masterdata', 'Items'],
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
                        <!--begin::Svg Icon | path: icons/duotune/general/gen021.svg-->
                        <span class="svg-icon svg-icon-1 position-absolute ms-6">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none">
                                <rect opacity="0.5" x="17.0365" y="15.1223" width="8.15546" height="2" rx="1"
                                    transform="rotate(45 17.0365 15.1223)" fill="black"></rect>
                                <path
                                    d="M11 19C6.55556 19 3 15.4444 3 11C3 6.55556 6.55556 3 11 3C15.4444 3 19 6.55556 19 11C19 15.4444 15.4444 19 11 19ZM11 5C7.53333 5 5 7.53333 5 11C5 14.4667 7.53333 17 11 17C14.4667 17 17 14.4667 17 11C17 7.53333 14.4667 5 11 5Z"
                                    fill="black"></path>
                            </svg>
                        </span>
                        <!--end::Svg Icon-->
                        <input type="text" id="item_search_input" class="form-control form-control-solid w-250px ps-15"
                            placeholder="Search Items">
                    </div>
                    <div class="d-flex align-items-center position-relative my-1 ms-3">
                        <select id="category_filter" class="form-select form-select-solid" data-control="select2" data-placeholder="Filter by Kategori">
                            <option></option>
                            <option value="semua">Semua Kategori</option>
                            @foreach ($itemcategories as $category)
                                <option value="{{ $category->id }}" {{ (isset($selected_category_id) && $selected_category_id == $category->id) ? 'selected' : '' }}>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="card-toolbar">
                    <!--begin::Toolbar-->
                    @if ($canCreate)
                        <div class="d-flex justify-content-end">
                            <a href="{{ route('admin.masterdata.items.create') }}" class="btn btn-primary">Tambah Item</a>
                        </div>
                    @endif
                    <!--end::Toolbar-->
                </div>

            </div>
            <div class="card-body pt-4">
                <div class="dataTables_wrapper dt-bootstrap4 no-footer">
                    <div class="table-responsive min-h-500px">
                        <table class="table align-middle table-row-dashed fs-6 gy-5 dataTable no-footer" id="table-on-page">
                            <thead>
                                <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                    <th class="sorting">No</th>
                                    <th class="min-w-125px sorting">Item</th>
                                    <th class="min-w-125px sorting">SKU</th>
                                    <th class="min-w-125px sorting">Kategori</th>
                                    <th class="min-w-125px sorting">Koli</th>
                                    <th class="min-w-125px sorting">UOM</th>
                                    <th class="min-w-125px sorting">Deskripsi</th>
                                    <th class="min-w-125px sorting">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="fw-bold text-gray-600">
                                @foreach ($items as $item)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>
                                            {{ $item->nama_barang }}<br>
                                            <span class="text-muted fs-7">{{ $item->product_code }}</span>
                                        </td>
                                        <td>{{ $item->sku }}</td>
                                        <td>{{ $item->itemCategory->name ?? '' }}</td>
                                        <td>{{ $item->koli }}</td>
                                        <td>{{ $item->uom->name ?? '' }}</td>
                                        <td>{{ $item->deskripsi }}</td>
                                        <td>
                                            @if ($canEdit || $canDelete)
                                                <a href="#" class="btn btn-sm btn-light btn-active-light-primary"
                                                    data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">Actions
                                                    <span class="svg-icon svg-icon-5 m-0">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                            viewBox="0 0 24 24" fill="none">
                                                            <path
                                                                d="M11.4343 12.7344L7.25 8.55005C6.83579 8.13583 6.16421 8.13584 5.75 8.55005C5.33579 8.96426 5.33579 9.63583 5.75 10.05L11.2929 15.5929C11.6834 15.9835 12.3166 15.9835 12.7071 15.5929L18.25 10.05C18.6642 9.63584 18.6642 8.96426 18.25 8.55005C17.8358 8.13584 17.1642 8.13584 16.75 8.55005L12.5657 12.7344C12.2533 13.0468 11.7467 13.0468 11.4343 12.7344Z"
                                                                fill="black"></path>
                                                        </svg>
                                                    </span></a>
                                                <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-600 menu-state-bg-light-primary fw-bold fs-7 w-125px py-4"
                                                    data-kt-menu="true" style="">
                                                    @if ($canEdit)
                                                        <div class="menu-item px-3">
                                                            <a href="{{ route('admin.masterdata.items.edit', $item->id) }}"
                                                                class="menu-link px-3">Edit</a>
                                                        </div>
                                                    @endif
                                                    @if ($canDelete)
                                                        <div class="menu-item px-3">
                                                            <form class="form-delete"
                                                                action="{{ route('admin.masterdata.items.destroy', $item->id) }}" method="POST"
                                                                style="display: inline-block;">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit"
                                                                    class="menu-link px-3 border-0 bg-transparent w-100 text-start"
                                                                    data-item-sku="{{ $item->sku }}">
                                                                    Delete
                                                                </button>
                                                            </form>
                                                        </div>
                                                    @endif
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
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

            var table = $('#table-on-page').DataTable({
                "info": false,
                'order': [],
                'columnDefs': [{
                    orderable: false,
                    targets: 7 // Adjusted target for Actions column
                }, ]
            });

            $('#item_search_input').on('keyup', function() {
                table.search(this.value).draw();
            });

            const canDelete = @json((bool) $canDelete);

            if (canDelete) {
                $('#table-on-page').on('submit', '.form-delete', function(e) {
                    e.preventDefault();

                    var form = $(this);
                    var n = form.find('button[data-item-sku]').data('item-sku');
                    var url = form.attr('action');
                    var data = form.serialize();

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
                            $.ajax({
                                url: url,
                                type: 'POST',
                                data: data,
                                success: function(response) {

                                    toastr.success("Data " + n + " berhasil dihapus.");
                                    table.row(form.closest('tr')).remove().draw();
                                },
                                error: function(xhr) {

                                    toastr.error("Gagal menghapus data " + n +
                                        ". Silakan coba lagi.");
                                }
                            });
                        } else if (result.dismiss === 'cancel') {

                            toastr.info("Penghapusan data " + n + " dibatalkan.");
                        }
                    });
                });
            }

            $('#category_filter').on('change', function() {
                var categoryId = $(this).val();
                var url = new URL(window.location.href);
                if (categoryId) {
                    url.searchParams.set('category_id', categoryId);
                } else {
                    url.searchParams.delete('category_id');
                }
                window.location.href = url.toString();
            });
        });
    </script>
@endpush
