@extends('layouts.app')

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Detail Assembly Recipe',
        'breadcrumbs' => ['Admin', 'Masterdata', 'Assembly Recipes', 'Detail'],
    ])
@endpush

@section('content')
    <div class="content flex-row-fluid" id="kt_content">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Detail Recipe: {{ $recipe->code }}</h3>
            </div>
            <div class="card-body">
                <div class="row g-6">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Kode</label>
                            <div class="form-control form-control-solid">{{ $recipe->code }}</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Deskripsi</label>
                            <div class="form-control form-control-solid">{{ $recipe->description ?? '-' }}</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Produk Jadi</label>
                            <div class="form-control form-control-solid">{{ $recipe->finishedItem->nama_barang ?? '-' }} ({{ $recipe->finishedItem->sku ?? '-' }})</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Output Quantity</label>
                            <div class="form-control form-control-solid">{{ number_format($recipe->output_quantity, 2, ',', '.') }}</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Status</label>
                            <div class="form-control form-control-solid">{{ $recipe->is_active ? 'Aktif' : 'Nonaktif' }}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Komponen</label>
                        <div class="table-responsive">
                            <table class="table align-middle table-row-dashed fs-6 gy-5">
                                <thead>
                                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                        <th>Item</th>
                                        <th class="text-end">Qty</th>
                                    </tr>
                                </thead>
                                <tbody class="fw-bold text-gray-600">
                                    @forelse($recipe->items as $ri)
                                        <tr>
                                            <td>{{ $ri->item->nama_barang ?? '-' }} ({{ $ri->item->sku ?? '-' }})</td>
                                            <td class="text-end">{{ number_format($ri->quantity, 2, ',', '.') }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="2" class="text-muted">Tidak ada komponen.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="mt-6">
                    <a href="{{ route('admin.masterdata.assemblyrecipes.index') }}" class="btn btn-primary">Kembali</a>
                </div>
            </div>
        </div>
    </div>
@endsection
