@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
@endpush

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Manajemen Hak Akses',
        'breadcrumbs' => ['Admin', 'Masterdata', 'Hak Akses'],
    ])
@endpush

@section('content')
    <div class="content flex-row-fluid" id="kt_content">
        <div class="card">
            <div class="card-header border-0 pt-6">
                <div class="card-title">
                    <div class="d-flex align-items-center position-relative my-1">
                        <select id="jabatan-filter" class="form-select form-select-solid" data-kt-select2="true">
                            @foreach ($jabatans as $jabatan)
                                <option value="{{ $jabatan->id }}">{{ $jabatan->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="card-body pt-4">
                <div class="dataTables_wrapper dt-bootstrap4 no-footer">
                    <div class="table-responsive min-h-500px">
                        <table class="table align-middle table-row-dashed fs-6 gy-5">
                            <thead>
                                <tr>
                                    <td colspan="6" class="fs-5 fw-bolder form-label mb-2">Akses Jabatan</td>
                                </tr>
                            </thead>
                            <tbody id="permissions-table-body" class="text-gray-600 fw-bold">
                                <!-- Permissions rows will be loaded here via AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
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

            function loadPermissions(jabatanId) {
                axios.get(`{{ route('admin.masterdata.permissions.get_by_jabatan') }}?jabatan_id=${jabatanId}`)
                    .then(function(response) {
                        const permissions = response.data.permissions;
                        const menus = response.data.menus;
                        const jabatans = response.data.jabatans;
                        let tableBody = $('#permissions-table-body');
                        tableBody.empty();

                        menus.forEach(function(menu) {
                            tableBody.append(renderMenuPermissionRow(menu, 0, jabatans, permissions));
                        });
                    })
                    .catch(function(error) {
                        console.error(error);
                        toastr.error('Gagal memuat hak akses.');
                    });
            }

            function renderMenuPermissionRow(menu, level, jabatans, permissions) {
                let row = `<tr>
                    <td class="text-gray-800">${'--'.repeat(level)} ${menu.name}</td>`;

                jabatans.forEach(function(jabatan) {
                    let permission = permissions[jabatan.id + '-' + menu.id] || {};
                    row +=
                        `<td>
                            <label class="form-check form-check-sm form-check-custom form-check-solid me-5 me-lg-20">
                                <input class="form-check-input permission-checkbox" data-jabatan-id="${jabatan.id}" data-menu-id="${menu.id}" data-permission-type="can_create" ${permission.can_create ? 'checked' : ''} type="checkbox">
                                <span class="form-check-label">Create</span>
                            </label>								
                        </td>
                        <td>
                             <label class="form-check form-check-sm form-check-custom form-check-solid me-5 me-lg-20">
                                <input class="form-check-input permission-checkbox" data-jabatan-id="${jabatan.id}" data-menu-id="${menu.id}" data-permission-type="can_read" ${permission.can_read ? 'checked' : ''} type="checkbox">
                                <span class="form-check-label">Read</span>
                            </label>	
                        </td>
                        <td>
                             <label class="form-check form-check-sm form-check-custom form-check-solid me-5 me-lg-20">
                                <input class="form-check-input permission-checkbox"  data-jabatan-id="${jabatan.id}" data-menu-id="${menu.id}" data-permission-type="can_edit" ${permission.can_edit ? 'checked' : ''} type="checkbox">
                                <span class="form-check-label">Update</span>
                            </label>
                        </td>
                        <td>
                             <label class="form-check form-check-sm form-check-custom form-check-solid me-5 me-lg-20">
                                <input class="form-check-input permission-checkbox"  data-jabatan-id="${jabatan.id}" data-menu-id="${menu.id}" data-permission-type="can_delete" ${permission.can_delete ? 'checked' : ''} type="checkbox">
                                <span class="form-check-label">Delete</span>
                            </label>
                        </td>
                        <td>
                             <label class="form-check form-check-sm form-check-custom form-check-solid me-5 me-lg-20">
                                <input class="form-check-input permission-checkbox" data-jabatan-id="${jabatan.id}" data-menu-id="${menu.id}" data-permission-type="can_approve" ${permission.can_approve ? 'checked' : ''} type="checkbox">
                                <span class="form-check-label">Approval</span>
                            </label>
                        </td>`;
                });

                row += `</tr>`;

                if (menu.children && menu.children.length > 0) {
                    menu.children.forEach(function(child) {
                        row += renderMenuPermissionRow(child, level + 1, jabatans, permissions);
                    });
                }

                return row;
            }

            // Initial load
            loadPermissions($('#jabatan-filter').val());

            // Handle filter change
            $('#jabatan-filter').on('change', function() {
                loadPermissions($(this).val());
            });

            // Handle checkbox change
            $(document).on('change', '.permission-checkbox', function() {
                const jabatanId = $(this).data('jabatan-id');
                const menuId = $(this).data('menu-id');
                const permissionType = $(this).data('permission-type');
                const isChecked = $(this).is(':checked');

                axios.post('{{ route('admin.masterdata.permissions.update') }}', {
                        jabatan_id: jabatanId,
                        menu_id: menuId,
                        permission_type: permissionType,
                        is_checked: isChecked
                    })
                    .then(function(response) {
                        toastr.success('Hak akses berhasil diperbarui.');
                    })
                    .catch(function(error) {
                        console.error(error);
                        toastr.error('Terjadi kesalahan saat memperbarui hak akses.');
                        $(this).prop('checked', !isChecked);
                    });
            });
        });
    </script>
@endpush
