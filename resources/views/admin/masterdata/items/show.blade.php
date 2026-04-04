@extends('layouts.app')

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Item Details',
        'breadcrumbs' => ['Admin', 'Masterdata', 'Items', 'Detail Item'],
    ])
@endpush

@section('content')
    <div class="content flex-row-fluid" id="kt_content">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Detail Item: {{ $item->sku }}</h3>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label fw-bold">Koli:</label>
                    <p class="form-control-static">{{ $item->koli }}</p>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">SKU:</label>
                    <p class="form-control-static">{{ $item->sku }}</p>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Nama Barang:</label>
                    <p class="form-control-static">{{ $item->nama_barang }}</p>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Deskripsi:</label>
                    <p class="form-control-static">{{ $item->deskripsi }}</p>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Product Code:</label>
                    <p class="form-control-static">{{ $item->product_code }}</p>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">UOM:</label>
                    <p class="form-control-static">{{ $item->uom->name ?? '' }}</p>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Tanggal Dibuat:</label>
                    <p class="form-control-static">{{ $item->created_at }}</p>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Terakhir Diperbarui:</label>
                    <p class="form-control-static">{{ $item->updated_at }}</p>
                </div>
                <a href="{{ route('admin.masterdata.items.index') }}" class="btn btn-primary">Kembali</a>
            </div>
        </div>
    </div>
@endsection
