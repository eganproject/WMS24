@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <style>
        #kt-toolbar-filter .select2-container .select2-selection--single {
            height: 2.65rem !important;
            display: flex;
            align-items: center;
            padding: 0 0.75rem;
            border: 1px solid transparent;
        }
        #kt-toolbar-filter .select2-container .select2-selection--single .select2-selection__rendered {
            padding-left: 0;
            line-height: 1.2;
        }
        #kt-toolbar-filter .select2-container .select2-selection--single .select2-selection__arrow {
            height: 100%;
        }
    </style>
@endpush

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Aktifitas User',
        'breadcrumbs' => ['Admin', 'Aktifitas User'],
    ])
@endpush

@section('content')
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
                        <input type="text" id="search_input" class="form-control form-control-solid w-250px ps-15" value="{{ $search }}" placeholder="Cari aktifitas user">
                    </div>
                </div>
                <div class="card-toolbar">
                    <div class="d-flex justify-content-end" data-kt-customer-table-toolbar="base">
                        <button type="button" class="btn btn-light-primary me-3" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                            <span class="svg-icon svg-icon-2">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                    <path d="M19.0759 3H4.72777C3.95892 3 3.47768 3.83148 3.86067 4.49814L8.56967 12.6949C9.17923 13.7559 9.5 14.9582 9.5 16.1819V19.5072C9.5 20.2189 10.2223 20.7028 10.8805 20.432L13.8805 19.1977C14.2553 19.0435 14.5 18.6783 14.5 18.273V13.8372C14.5 12.8089 14.8171 11.8056 15.408 10.964L19.8943 4.57465C20.3596 3.912 19.8856 3 19.0759 3Z" fill="black" />
                                </svg>
                            </span>
                            Filter
                        </button>
                        <div class="menu menu-sub menu-sub-dropdown w-300px w-md-325px" data-kt-menu="true" id="kt-toolbar-filter">
                            <div class="px-7 py-5">
                                <div class="fs-4 text-dark fw-bolder">Filter Options</div>
                            </div>
                            <div class="separator border-gray-200"></div>
                            <div class="px-7 py-5">
                                <div class="mb-10">
                                    <label class="form-label fs-5 fw-bold mb-3">Tanggal:</label>
                                    <div class="row g-3">
                                        <div class="col">
                                            <input class="form-control form-control-solid" placeholder="Mulai" id="start_date" name="start_date" value="{{ $startDate }}" autocomplete="off" />
                                        </div>
                                        <div class="col">
                                            <input class="form-control form-control-solid" placeholder="Selesai" id="end_date" name="end_date" value="{{ $endDate }}" autocomplete="off" />
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-10">
                                    <label class="form-label fs-5 fw-bold mb-3">Pengguna:</label>
                                    @if ($canFilterUsers)
                                        <select class="form-select form-select-solid fw-bolder" data-kt-select2="true" data-control="select2" data-placeholder="Semua Pengguna" id="user_filter" name="user_id" data-dropdown-parent="#kt-toolbar-filter">
                                            <option value="">Semua Pengguna</option>
                                            @foreach ($users as $user)
                                                <option value="{{ $user->id }}" @selected((string) $selectedUserId === (string) $user->id)>
                                                    {{ $user->name }}{{ $user->email ? ' - ' . $user->email : '' }}
                                                </option>
                                            @endforeach
                                        </select>
                                    @else
                                        <div class="form-control form-control-solid">
                                            {{ $defaultUserText ?? 'Pengguna Aktif' }}
                                        </div>
                                        <input type="hidden" id="user_filter" name="user_id" value="{{ $defaultUserId ?? $selectedUserId }}">
                                    @endif
                                </div>
                                <div class="d-flex justify-content-end">
                                    <button type="button" class="btn btn-light btn-active-light-primary me-2" id="reset_filter" data-kt-menu-dismiss="true">Reset</button>
                                    <button type="button" class="btn btn-primary" id="apply_filter" data-kt-menu-dismiss="true">Terapkan</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body pt-0">
                <div class="text-center mb-5">
                    <h3 class="mb-0">Daftar Aktifitas User</h3>
                    <small id="filter-info" class="text-muted"></small>
                </div>
                <div class="dataTables_wrapper dt-bootstrap4 no-footer">
                    <div class="table-responsive min-h-500px">
                        <table class="table align-middle table-row-dashed fs-6 gy-5 dataTable no-footer" id="user-activity-table">
                            <thead>
                                <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                    <th class="min-w-50px">No</th>
                                    <th class="min-w-150px">Waktu</th>
                                    <th class="min-w-125px">Nama</th>
                                    <th class="min-w-150px">Email</th>
                                    <th class="min-w-125px">Aktivitas</th>
                                    <th class="min-w-125px">Menu</th>
                                    <th class="min-w-200px">Deskripsi</th>
                                    <th class="min-w-125px">IP Address</th>
                                    <th class="min-w-200px">User Agent</th>
                                </tr>
                            </thead>
                            <tbody class="fw-bold text-gray-600"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const canFilterUsers = @json($canFilterUsers);
            const defaultUserId = @json($defaultUserId);
            const defaultUserText = @json($defaultUserText);

            const $searchInput = $('#search_input');
            const $startDate = $('#start_date');
            const $endDate = $('#end_date');
            const $userFilter = $('#user_filter');
            const $filterMenu = $('#kt-toolbar-filter');

            if (window.flatpickr) {
                flatpickr('#start_date', {
                    dateFormat: 'Y-m-d',
                    allowInput: true,
                    defaultDate: $startDate.val() || null,
                });

                flatpickr('#end_date', {
                    dateFormat: 'Y-m-d',
                    allowInput: true,
                    defaultDate: $endDate.val() || null,
                });
            }

            if (window.jQuery && canFilterUsers && $userFilter.length && $userFilter.is('select')) {
                $userFilter.select2({
                    placeholder: $userFilter.data('placeholder') || 'Semua Pengguna',
                    allowClear: true,
                    width: '100%',
                    dropdownParent: $filterMenu,
                });
            }

            const table = $('#user-activity-table').DataTable({
                processing: true,
                serverSide: true,
                searching: false,
                ajax: {
                    url: "{{ route('admin.user-activities.index') }}",
                    data: function (d) {
                        d.search = d.search || {};
                        d.search.value = $searchInput.val();
                        d.start_date = $startDate.val();
                        d.end_date = $endDate.val();
                        d.user_id = canFilterUsers ? $userFilter.val() : defaultUserId;
                    },
                },
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                order: [[1, 'desc']],
                columns: [
                    { data: null, name: 'rownum', orderable: false, searchable: false },
                    { data: 'created_at_display', name: 'created_at' },
                    { data: 'user_name', name: 'user.name' },
                    { data: 'user_email', name: 'user.email' },
                    { data: 'activity', name: 'activity' },
                    { data: 'menu', name: 'menu' },
                    { data: 'description', name: 'description' },
                    { data: 'ip_address', name: 'ip_address' },
                    { data: 'user_agent_short', name: 'user_agent' },
                ],
                columnDefs: [
                    {
                        targets: 0,
                        render: function (data, type, row, meta) {
                            return meta.row + meta.settings._iDisplayStart + 1;
                        },
                    },
                    {
                        targets: 8,
                        render: function (data, type, row) {
                            if (!row.user_agent) {
                                return '-';
                            }
                            const shortText = $('<div>').text(row.user_agent_short).html();
                            const fullText = $('<div>').text(row.user_agent).html();
                            return `<span title="${fullText}">${shortText}</span>`;
                        },
                    },
                ],
                drawCallback: function () {
                    updateFilterInfo();
                },
            });

            const debounce = (callback, wait = 400) => {
                let timeoutId = null;
                return (...args) => {
                    window.clearTimeout(timeoutId);
                    timeoutId = window.setTimeout(() => callback.apply(null, args), wait);
                };
            };

            $searchInput.on('keyup', debounce(function () {
                table.ajax.reload();
            }));

            $('#apply_filter').on('click', function () {
                table.ajax.reload();
                updateFilterInfo();
            });

            $('#reset_filter').on('click', function () {
                $startDate.val('');
                $endDate.val('');

                if (canFilterUsers && $userFilter.length && $userFilter.is('select')) {
                    $userFilter.val('').trigger('change');
                } else {
                    $userFilter.val(defaultUserId || '');
                }

                table.ajax.reload();
                updateFilterInfo();
            });

            function updateFilterInfo() {
                const start = $startDate.val();
                const end = $endDate.val();
                let dateText = 'Semua Tanggal';

                if (start && end) {
                    dateText = `${start} s/d ${end}`;
                } else if (start || end) {
                    dateText = start || end;
                }

                let userText = 'Semua Pengguna';
                if (canFilterUsers && $userFilter.length && $userFilter.is('select')) {
                    const selectedOption = $userFilter.find('option:selected');
                    if (selectedOption.length && selectedOption.val()) {
                        userText = selectedOption.text();
                    }
                } else {
                    userText = defaultUserText || 'Pengguna Aktif';
                }

                const searchVal = $searchInput.val();
                const searchText = searchVal ? ` | Pencarian: "${searchVal}"` : '';

                $('#filter-info').text(`Tanggal: ${dateText} | Pengguna: ${userText}${searchText}`);
            }

            updateFilterInfo();
        });
    </script>
@endpush

