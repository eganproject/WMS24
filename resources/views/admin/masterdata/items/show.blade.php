@extends('layouts.admin')

@php
    use App\Support\Permission as Perm;
    $canUpdate = Perm::can(auth()->user(), 'admin.masterdata.items.index', 'update');
    $isBundle = $item->isBundle();
    $location = $item->location;
@endphp

@section('title', 'Detail Item '.$item->sku)
@section('page_title', 'Detail Item')

@section('page_breadcrumbs')
    <li class="breadcrumb-item text-gray-600">
        <a href="{{ route('admin.dashboard') }}" class="text-gray-600 text-hover-primary">Admin</a>
    </li>
    <li class="breadcrumb-item text-gray-600">
        <a href="{{ route('admin.masterdata.items.index') }}" class="text-gray-600 text-hover-primary">Items</a>
    </li>
    <li class="breadcrumb-item text-gray-500">{{ $item->sku }}</li>
@endsection

@section('page_actions')
    <div class="d-flex gap-2">
        <a href="{{ route('admin.masterdata.items.index') }}" class="btn btn-sm btn-light">
            <i class="fas fa-arrow-left me-1"></i> Kembali
        </a>
        @if($canUpdate)
            <a href="{{ route('admin.masterdata.items.edit', $item->id) }}" class="btn btn-sm btn-primary">
                <i class="fas fa-pen me-1"></i> Edit Item
            </a>
        @endif
    </div>
@endsection

@section('content')
<div class="w-100">
    <div class="card mb-6">
        <div class="card-body py-7">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-5">
                <div>
                    <div class="text-muted fs-7 fw-bold text-uppercase mb-1">SKU</div>
                    <div class="fs-2 fw-bolder text-dark mb-1">{{ $item->sku }}</div>
                    <div class="fs-5 text-gray-700 mb-3">{{ $item->name }}</div>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge {{ $isBundle ? 'badge-light-primary' : 'badge-light-success' }}">{{ $isBundle ? 'Bundle' : 'Single' }}</span>
                        <span class="badge {{ $item->isActive() ? 'badge-light-success' : 'badge-light-secondary' }}">{{ $item->isActive() ? 'Aktif' : 'Nonaktif' }}</span>
                        <span class="badge badge-light-info">{{ $item->category?->name ?? 'Tanpa Kategori' }}</span>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-4">
                    <div class="border border-gray-300 border-dashed rounded py-3 px-5 min-w-125px">
                        <div class="fs-2 fw-bolder text-dark">{{ $isBundle ? '-' : ($item->koli_qty ?? '-') }}</div>
                        <div class="fw-bold text-muted fs-7">Isi per Koli</div>
                    </div>
                    <div class="border border-gray-300 border-dashed rounded py-3 px-5 min-w-125px">
                        <div class="fs-2 fw-bolder text-dark">{{ $isBundle ? '-' : number_format((int) ($item->safety_stock ?? 0), 0, ',', '.') }}</div>
                        <div class="fw-bold text-muted fs-7">Stok Pengaman</div>
                    </div>
                    <div class="border border-gray-300 border-dashed rounded py-3 px-5 min-w-125px">
                        <div class="fs-2 fw-bolder text-dark">{{ number_format((int) $warehouseStocks->sum('stock'), 0, ',', '.') }}</div>
                        <div class="fw-bold text-muted fs-7">{{ $isBundle ? 'Total Virtual Stock' : 'Total Stok' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-6">
        <div class="col-xl-8">
            <div class="card mb-6">
                <div class="card-header border-0 pt-6">
                    <div class="card-title"><h3 class="fw-bolder mb-0">Informasi Item</h3></div>
                </div>
                <div class="card-body pt-2">
                    <table class="table table-row-dashed align-middle fs-6 gy-4 mb-0">
                        <tbody>
                            <tr>
                                <td class="text-muted w-200px">Kategori</td>
                                <td class="fw-bold">{{ $item->category?->name ?? 'Tanpa Kategori' }}</td>
                            </tr>
                            @unless($isBundle)
                                <tr>
                                    <td class="text-muted">Area Display</td>
                                    <td class="fw-bold">{{ $area ? $area->code.' - '.$area->name : '-' }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Rack / Kolom / Baris</td>
                                    <td class="fw-bold">
                                        @if($location && $location->rack_code)
                                            {{ $location->rack_code }} / {{ $location->column_no }} / {{ $location->row_no }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Lokasi Gudang Display</td>
                                    <td class="fw-bold">{{ $address !== '' ? $address : '-' }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Lokasi Gudang Besar</td>
                                    <td class="fw-bold">{{ $item->mainLocation?->code ?? '-' }}</td>
                                </tr>
                            @endunless
                            <tr>
                                <td class="text-muted">Deskripsi</td>
                                <td>{!! $item->description ? nl2br(e($item->description)) : '<span class="text-muted">-</span>' !!}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Dibuat</td>
                                <td>{{ $item->created_at?->format('d/m/Y H:i') ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Terakhir diubah</td>
                                <td>{{ $item->updated_at?->format('d/m/Y H:i') ?? '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card mb-6">
                <div class="card-header border-0 pt-6">
                    <div class="card-title flex-column">
                        <h3 class="fw-bolder mb-1">Stok per Gudang</h3>
                        @if($isBundle)
                            <div class="text-muted fs-7">Virtual stock dihitung dari stok komponen bundle.</div>
                        @endif
                    </div>
                </div>
                <div class="card-body pt-2">
                    <div class="table-responsive">
                        <table class="table table-row-dashed align-middle fs-6 gy-4 mb-0">
                            <thead>
                                <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                    <th>Gudang</th>
                                    <th class="text-end">{{ $isBundle ? 'Virtual Stock' : 'Stok' }}</th>
                                    @unless($isBundle)
                                        <th class="text-end">Stok Pengaman Gudang</th>
                                    @endunless
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($warehouseStocks as $row)
                                    <tr>
                                        <td>
                                            <div class="fw-bold">{{ $row['name'] }}</div>
                                            <div class="text-muted fs-8">{{ $row['code'] }}</div>
                                        </td>
                                        <td class="text-end fw-bolder">{{ number_format((int) $row['stock'], 0, ',', '.') }}</td>
                                        @unless($isBundle)
                                            <td class="text-end">{{ $row['safety_stock'] !== null ? number_format((int) $row['safety_stock'], 0, ',', '.') : '-' }}</td>
                                        @endunless
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-center text-muted py-6">Belum ada gudang.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            @if($isBundle)
                <div class="card mb-6">
                    <div class="card-header border-0 pt-6">
                        <div class="card-title"><h3 class="fw-bolder mb-0">Komponen Bundle</h3></div>
                    </div>
                    <div class="card-body pt-2">
                        <table class="table table-row-dashed align-middle fs-6 gy-4 mb-0">
                            <thead>
                                <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                    <th>SKU</th>
                                    <th>Nama</th>
                                    <th class="text-end">Qty per Bundle</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($item->bundleComponents as $component)
                                    <tr>
                                        <td class="fw-bold">
                                            @if($component->component)
                                                <a href="{{ route('admin.masterdata.items.show', $component->component_item_id) }}">{{ $component->component->sku }}</a>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>{{ $component->component?->name ?? '-' }}</td>
                                        <td class="text-end fw-bolder">{{ (int) $component->required_qty }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-center text-muted py-6">Belum ada komponen.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                <div class="card mb-6">
                    <div class="card-header border-0 pt-6">
                        <div class="card-title flex-column">
                            <h3 class="fw-bolder mb-1">Barcode / QR Eksternal</h3>
                            <div class="text-muted fs-7">Alias yang dikenali saat QC Scan SKU dan Stock Opname.</div>
                        </div>
                    </div>
                    <div class="card-body pt-2">
                        <table class="table table-row-dashed align-middle fs-6 gy-4 mb-0">
                            <thead>
                                <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                    <th>Barcode</th>
                                    <th>Sumber</th>
                                    <th>Catatan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($item->barcodes as $barcode)
                                    <tr>
                                        <td class="fw-bold">{{ $barcode->barcode_value }}</td>
                                        <td>{{ $barcode->source_name ?: '-' }}</td>
                                        <td>{{ $barcode->note ?: '-' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-center text-muted py-6">Belum ada barcode eksternal.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                @if($item->bundleParents->isNotEmpty())
                    <div class="card mb-6">
                        <div class="card-header border-0 pt-6">
                            <div class="card-title"><h3 class="fw-bolder mb-0">Dipakai di Bundle</h3></div>
                        </div>
                        <div class="card-body pt-2">
                            <table class="table table-row-dashed align-middle fs-6 gy-4 mb-0">
                                <thead>
                                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                        <th>SKU Bundle</th>
                                        <th>Nama</th>
                                        <th class="text-end">Qty per Bundle</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($item->bundleParents as $parent)
                                        <tr>
                                            <td class="fw-bold">
                                                @if($parent->bundle)
                                                    <a href="{{ route('admin.masterdata.items.show', $parent->bundle_item_id) }}">{{ $parent->bundle->sku }}</a>
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td>{{ $parent->bundle?->name ?? '-' }}</td>
                                            <td class="text-end fw-bolder">{{ (int) $parent->required_qty }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            @endif
        </div>

        <div class="col-xl-4">
            <div class="card mb-6">
                <div class="card-header border-0 pt-6">
                    <div class="card-title"><h3 class="fw-bolder mb-0">QR Code</h3></div>
                </div>
                <div class="card-body pt-2 text-center">
                    <div class="border border-gray-300 border-dashed rounded-3 bg-light d-flex align-items-center justify-content-center p-5 mb-5">
                        <img src="{{ route('admin.masterdata.items.qr-code', $item->id) }}" alt="QR Code {{ $item->sku }}" class="mw-100 mh-300px" loading="lazy" />
                    </div>
                    <a href="{{ route('admin.masterdata.items.qr-code', ['item' => $item->id, 'download' => 1]) }}" class="btn btn-light-primary w-100" download>
                        <i class="fas fa-download me-1"></i> Download QR
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
