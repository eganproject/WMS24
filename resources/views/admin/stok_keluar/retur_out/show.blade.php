@extends('layouts.app')

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Detail Retur Out',
        'breadcrumbs' => ['Admin', 'Stok Keluar', 'Retur Out', 'Detail'],
    ])
@endpush

@section('content')
<div class="content flex-row-fluid" id="kt_content">
  <div class="card">
    <div class="card-body">
      <div class="row mb-6">
        <div class="col-md-3"><div class="fw-bold text-muted">Kode</div><div class="fw-bolder">{{ $ri->code }}</div></div>
        <div class="col-md-3"><div class="fw-bold text-muted">Tanggal</div><div class="fw-bolder">{{ optional($ri->return_date)->format('Y-m-d') }}</div></div>
        <div class="col-md-3"><div class="fw-bold text-muted">Gudang Asal</div><div class="fw-bolder">{{ $ri->warehouse->name ?? '-' }}</div></div>
        <div class="col-md-3"><div class="fw-bold text-muted">Gudang Tujuan</div><div class="fw-bolder">{{ $ri->destinationWarehouse->name ?? '-' }}</div></div>
      </div>
      <div class="row mb-6">
        <div class="col-md-3"><div class="fw-bold text-muted">Status</div><div class="fw-bolder"><span class="badge {{ $ri->status==='completed'?'badge-light-success':'badge-light-secondary' }}">{{ $ri->status }}</span></div></div>
        <div class="col-md-9"><div class="fw-bold text-muted">Deskripsi</div><div class="fw-bolder">{{ $ri->description ?? '-' }}</div></div>
      </div>

      <div class="table-responsive">
        <table class="table table-row-dashed align-middle">
          <thead><tr><th>SKU</th><th>Nama</th><th class="text-end">Qty</th><th class="text-end">Koli</th><th>Catatan</th></tr></thead>
          <tbody>
            @foreach($ri->details as $d)
              <tr>
                <td>{{ $d->item->sku ?? '-' }}</td>
                <td>{{ $d->item->nama_barang ?? '-' }}</td>
                <td class="text-end">{{ number_format($d->quantity ?? 0, 0, ',', '.') }}</td>
                <td class="text-end">{{ number_format($d->koli ?? 0, 2, ',', '.') }}</td>
                <td>{{ $d->notes ?? '-' }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      <div class="mt-6">
        <a href="{{ route('admin.stok-keluar.retur-out.index') }}" class="btn btn-light">Kembali</a>
      </div>
    </div>
  </div>
</div>
@endsection
