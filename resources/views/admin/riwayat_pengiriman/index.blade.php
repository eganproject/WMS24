@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
@endpush

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Riwayat Pengiriman',
        'breadcrumbs' => ['Admin', 'Riwayat Pengiriman'],
    ])
@endpush

@php
    $permissionResolver = app(\App\Support\MenuPermissionResolver::class);
    $canEdit = $permissionResolver->userCan('edit');
    $canDelete = $permissionResolver->userCan('delete');
@endphp

@section('content')
    <div class="content flex-row-fluid" id="kt_content">
        <div class="row g-5 g-xl-8">
            <div class="col-12">
                <div class="card card-xl-stretch mb-xl-8">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between flex-wrap">
                            <div class="d-flex align-items-center mb-4 mb-md-0">
                                <div class="symbol symbol-50px me-5">
                                    <div class="symbol-label" style="background:#E6F0FF">
                                        <i class="fas fa-shipping-fast fs-2x" style="color:#3B82F6"></i>
                                    </div>
                                </div>
                                <div>
                                    <div class="text-dark fw-bolder fs-5 mb-1">Status Pengiriman</div>
                                    <div class="text-muted fw-bold">Dokumen Shipment</div>
                                </div>
                            </div>
                            <div class="status-metrics" style="display:flex;gap:1.5rem;align-items:center;flex-wrap:wrap;justify-content:flex-end;">
                                <div class="metric" style="text-align:center;min-width:88px;">
                                    <div class="chip chip-primary" id="status_in_transit" style="display:inline-block;padding:6px 12px;border-radius:10px;font-weight:700;background:#E6F0FF;color:#3B82F6;">0</div>
                                    <div class="text-muted fs-8 mt-1">Dalam Perjalanan</div>
                                </div>
                                <div class="metric" style="text-align:center;min-width:88px;">
                                    <div class="chip chip-success" id="status_completed" style="display:inline-block;padding:6px 12px;border-radius:10px;font-weight:700;background:#ECFDF5;color:#10B981;">0</div>
                                    <div class="text-muted fs-8 mt-1">Selesai</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header border-0 pt-6">
                <div class="card-title">
                    <div class="d-flex align-items-center position-relative my-1">
                        <span class="svg-icon svg-icon-1 position-absolute ms-6">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                <rect opacity="0.5" x="17.0365" y="15.1223" width="8.15546" height="2" rx="1" transform="rotate(45 17.0365 15.1223)" fill="black"></rect>
                                <path d="M11 19C6.55556 19 3 15.4444 3 11C3 6.55556 6.55556 3 11 3C15.4444 3 19 6.55556 19 11C19 15.4444 15.4444 19 11 19ZM11 5C7.53333 5 5 7.53333 5 11C5 14.4667 7.53333 17 11 17C14.4667 17 17 14.4667 17 11C17 7.53333 14.4667 5 11 5Z" fill="black" />
                            </svg>
                        </span>
                        <input type="text" id="search_input" class="form-control form-control-solid w-250px ps-15" placeholder="Cari kode/driver/gudang" />
                    </div>
                </div>
                <div class="card-toolbar">
                    <div class="d-flex justify-content-end" data-kt-user-table-toolbar="base">
                        <button type="button" class="btn btn-light-primary" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                            <i class="ki-duotone ki-filter fs-2"><span class="path1"></span><span class="path2"></span></i>
                            Filter
                        </button>
                        <div class="menu menu-sub menu-sub-dropdown w-300px w-md-325px" data-kt-menu="true">
                            <div class="px-7 py-5">
                                <div class="fs-5 text-dark fw-bolder">Filter Riwayat</div>
                            </div>
                            <div class="separator border-gray-200"></div>
                            <div class="px-7 py-5">
                                <div class="mb-10">
                                    <label class="form-label fs-6 fw-bold">Status</label>
                                    <select id="status_filter" class="form-select form-select-solid" data-kt-select2="true" data-placeholder="Pilih status">
                                        <option value="semua">Semua</option>
                                        <option value="dalam perjalanan">Dalam Perjalanan</option>
                                        <option value="selesai">Selesai</option>
                                    </select>
                                </div>
                                <div class="mb-10">
                                    <label class="form-label fs-6 fw-bold">Tipe Referensi</label>
                                    <select id="ref_type_filter" class="form-select form-select-solid">
                                        <option value="semua">Semua</option>
                                        <option value="transfer request">Transfer Request</option>
                                        <option value="stock in order">Pengadaan</option>
                                    </select>
                                </div>
                                <div class="mb-10">
                                    <label class="form-label fs-5 fw-bold mb-3">Tanggal Kirim:</label>
                                    <input class="form-control form-control-solid" placeholder="Pilih Tanggal" id="date_filter" />
                                </div>
                                <div class="d-flex justify-content-end">
                                    <button type="reset" class="btn btn-light btn-active-light-primary me-2" data-kt-menu-dismiss="true">Batal</button>
                                    <button type="button" class="btn btn-primary" id="apply_filter">Terapkan</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body pt-4">
                <div class="text-center mb-5">
                    <h3 class="mb-0">Daftar Riwayat Pengiriman</h3>
                    <small id="filter-info" class="text-muted"></small>
                </div>
                <div class="table-responsive min-h-500px">
                    <table class="table align-middle table-row-dashed fs-6 gy-5" id="table-on-page">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                <th class="min-w-125px sorting">Kode</th>
                                <th class="min-w-125px sorting">Tanggal</th>
                                <th class="min-w-150px sorting">Referensi</th>
                                <th class="min-w-150px sorting">Gudang</th>
                                <th class="min-w-125px sorting">Status</th>
                                <th class="min-w-150px sorting">Driver</th>
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
        $(function() {
            toastr.options = {"closeButton": true, "progressBar": true, "positionClass": "toast-top-center"};

            $('#date_filter').flatpickr({dateFormat: 'Y-m-d'});

            const debounce = (callback, wait = 400) => { let t; return (...args) => { clearTimeout(t); t = setTimeout(() => callback.apply(null, args), wait); }; };

            function renderStatus(s){
                const st = (s || '').toLowerCase();
                let badge = 'badge-light-primary';
                if (st === 'selesai') badge = 'badge-light-success';
                else if (st === 'dalam perjalanan') badge = 'badge-light-warning';
                return `<span class="badge ${badge}">${s || '-'}</span>`;
            }

            function loadStatusCounts(){
                const params = {
                    reference_type: $('#ref_type_filter').val(),
                    date: $('#date_filter').val()
                };
                $.get("{{ route('admin.riwayat-pengiriman.status-counts') }}", params, function(res){
                    $('#status_in_transit').text(res.in_transit || 0);
                    $('#status_completed').text(res.completed || 0);
                });
            }

            function reloadTable() {
                const params = {
                    'search[value]': $('#search_input').val(),
                    status: $('#status_filter').val(),
                    reference_type: $('#ref_type_filter').val(),
                    date: $('#date_filter').val(),
                    start: 0,
                    length: 25,
                    draw: 1,
                    'order[0][column]': 1,
                    'order[0][dir]': 'desc'
                };
                $.get("{{ route('admin.riwayat-pengiriman.index') }}", params, function(res) {
                    const tbody = $('#table-on-page tbody');
                    tbody.empty();
                    (res.data || []).forEach(function(row) {
                        const showUrl = "{{ route('admin.riwayat-pengiriman.show', ['shipment' => 'SHIPMENT_ID']) }}".replace('SHIPMENT_ID', row.id);
                        const suratJalanUrl = (row.reference_type === 'transfer request' && row.reference_id)
                            ? "{{ route('admin.transfergudang.surat-jalan.show', ':id') }}".replace(':id', row.reference_id)
                            : null;
                        const tr = `<tr>
                            <td>${row.code}</td>
                            <td>${row.shipping_date ?? '-'}</td>
                            <td><span class="badge badge-light">${row.reference_type}</span> ${row.reference_code ?? '-'}</td>
                            <td>${row.to_warehouse_name ?? '-'}</td>
                            <td>${renderStatus(row.status)}</td>
                            <td>${row.driver ?? '-'}</td>
                            <td class="text-center">
                                <a href="#" class="btn btn-sm btn-light btn-active-light-primary" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">Actions
                                    <span class="svg-icon svg-icon-5 m-0">
                                        <svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" viewBox=\"0 0 24 24\" fill=\"none\">
                                            <path d=\"M11.4343 12.7344L7.25 8.55005C6.83579 8.13583 6.16421 8.13584 5.75 8.55005C5.33579 8.96426 5.33579 9.63583 5.75 10.05L11.2929 15.5929C11.6834 15.9835 12.3166 15.9835 12.7071 15.5929L18.25 10.05C18.6642 9.63584 18.6642 8.96426 18.25 8.55005C17.8358 8.13584 17.1642 8.13584 16.75 8.55005L12.5657 12.7344C12.2533 13.0468 11.7467 13.0468 11.4343 12.7344Z\" fill=\"black\"></path>
                                        </svg>
                                    </span>
                                </a>
                                <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-600 menu-state-bg-light-primary fw-bold fs-7 w-150px py-4" data-kt-menu="true">
                                    <div class="menu-item px-3">
                                        <a href="${showUrl}" class="menu-link px-3">Detail</a>
                                    </div>
                                </div>
                            </td>
                        </tr>`;
                        tbody.append(tr);
                    });
                    const refText = $('#ref_type_filter option:selected').text();
                    const statusText = $('#status_filter option:selected').text();
                    const dateText = $('#date_filter').val() || 'Semua Tanggal';
                    $('#filter-info').text(`${res.recordsFiltered} pengiriman • Tanggal: ${dateText} • Tipe: ${refText} • Status: ${statusText}`);
                    // Re-init KT menu for newly injected action buttons
                    if (typeof KTMenu !== 'undefined' && KTMenu.createInstances) {
                        KTMenu.createInstances();
                    }
                }).done(loadStatusCounts);
            }

            $('#apply_filter').on('click', reloadTable);
            $('#search_input').on('input', debounce(reloadTable, 400));
            $('#status_filter, #ref_type_filter').on('change', reloadTable);

            reloadTable();
        });
    </script>
@endpush
