@extends('layouts.app')

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Laporan Stok Menipis',
        'breadcrumbs' => ['Admin', 'Laporan', 'Stok Menipis'],
    ])
@endpush

@section('content')
    <div class="content flex-row-fluid" id="kt_content">
        <div class="card">
            <div class="card-header border-0 pt-6">
                <div class="card-title">
                    <form id="filter_form" method="GET" action="{{ route('admin.laporan.laporanstok.menipis') }}" class="d-flex align-items-center position-relative my-1">
                        <div class="d-flex align-items-center position-relative my-1">
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
                            <input type="text" name="search" class="form-control form-control-solid w-250px ps-15"
                                placeholder="Cari Stok by SKU/Nama" value="{{ request('search') }}">
                        </div>
                    </form>
                </div>
                <div class="card-toolbar">
                    <button type="button" class="btn btn-light-primary me-3" data-kt-menu-trigger="click"
                        data-kt-menu-placement="bottom-end">
                        <span class="svg-icon svg-icon-2">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none">
                                <path
                                    d="M19.0759 3H4.72777C3.95892 3 3.47768 3.83148 3.86067 4.49814L8.56967 12.6949C9.17923 13.7559 9.5 14.9582 9.5 16.1819V19.5072C9.5 20.2189 10.2223 20.7028 10.8805 20.432L13.8805 19.1977C14.2553 19.0435 14.5 18.6783 14.5 18.273V13.8372C14.5 12.8089 14.8171 11.8056 15.408 10.964L19.8943 4.57465C20.3596 3.912 19.8856 3 19.0759 3Z"
                                    fill="black" />
                            </svg>
                        </span>
                        Filter
                    </button>
                    <div class="menu menu-sub menu-sub-dropdown w-300px w-md-325px" data-kt-menu="true">
                        <div class="px-7 py-5">
                            <div class="fs-4 text-dark fw-bolder">Filter Options</div>
                        </div>
                        <div class="separator border-gray-200"></div>
                        <div class="px-7 py-5">
                            @if (!auth()->user()->warehouse_id)
                                <div class="mb-10">
                                    <label class="form-label fs-5 fw-bold mb-3">Gudang:</label>
                                    <select class="form-select form-select-solid fw-bolder" data-kt-select2="true" name="warehouse_filter" form="filter_form">
                                        <option value="">Semua Gudang</option>
                                        @foreach ($warehouses as $warehouse)
                                            <option value="{{ $warehouse->id }}" {{ request('warehouse_filter') == $warehouse->id ? 'selected' : '' }}>{{ $warehouse->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                            <div class="d-flex justify-content-end">
                                <a href="{{ route('admin.laporan.laporanstok.menipis') }}" class="btn btn-light btn-active-light-primary me-2">Reset</a>
                                <button type="submit" class="btn btn-primary" form="filter_form">Apply</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body pt-4">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-5">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                <th>Gudang</th>
                                <th>SKU</th>
                                <th>Nama Item</th>
                                <th>Kategori</th>
                                <th>Stok</th>
                                <th>UOM</th>
                            </tr>
                        </thead>
                        <tbody class="fw-bold text-gray-600">
                            @forelse($inventories as $inventory)
                                <tr>
                                    <td>{{ $inventory->warehouse->name ?? '-' }}</td>
                                    <td>{{ $inventory->item->sku ?? '-' }}</td>
                                    <td>{{ $inventory->item->nama_barang ?? '-' }}</td>
                                    <td>{{ $inventory->item->itemCategory->name ?? '-' }}</td>
                                    <td><span class="badge badge-light-danger">{{ number_format($inventory->quantity, 2, ',', '.') }}</span></td>
                                    <td>{{ $inventory->item->uom->name ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center">Tidak ada data stok menipis</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <!--begin::Pagination-->
                <div class="mt-4">
                    {{ $inventories->appends(request()->query())->links() }}
                </div>
                <!--end::Pagination-->
            </div>
        </div>
    </div>
@endsection
