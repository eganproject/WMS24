@extends('layouts.app')

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Detail Penerimaan Retur',
        'breadcrumbs' => ['Admin', 'Stok Masuk', 'Penerimaan Retur', 'Detail'],
    ])
@endpush

@section('content')
<div class="content flex-row-fluid" id="kt_content">
  <div class="card mb-5">
    <div class="card-body py-5">
      <div class="row g-5">
        <div class="col-md-4"><div class="fs-7 text-muted">Kode</div><div class="fs-5 fw-bolder">{{ $rr->code }}</div></div>
        <div class="col-md-4"><div class="fs-7 text-muted">Tanggal</div><div class="fs-5 fw-bolder">{{ optional($rr->return_date)->format('Y-m-d') }}</div></div>
        <div class="col-md-4"><div class="fs-7 text-muted">Status</div><div class="fs-5 fw-bolder">
            @php($badge = $rr->status === \App\Models\ReturnReceipt::STATUS_COMPLETED ? 'badge-light-success' : 'badge-light-secondary')
            <span class="badge {{ $badge }}">{{ $rr->status }}</span>
        </div></div>
      </div>
      <div class="row g-5 mt-3">
        <div class="col-md-4"><div class="fs-7 text-muted">Gudang</div><div class="fs-5 fw-bolder">{{ $rr->warehouse->name ?? '-' }}</div></div>
        <div class="col-md-4"><div class="fs-7 text-muted">Penerima</div><div class="fs-5 fw-bolder">{{ $rr->receiver->name ?? '-' }}</div></div>
        <div class="col-md-4"><div class="fs-7 text-muted">Pengesah</div><div class="fs-5 fw-bolder">{{ $rr->verifier->name ?? '-' }}</div></div>
      </div>
      <div class="row g-5 mt-3"><div class="col-12"><div class="fs-7 text-muted">Deskripsi</div><div class="fs-5 fw-bolder">{{ $rr->description ?? '-' }}</div></div></div>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><h3 class="card-title">Detail Item</h3></div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table align-middle table-row-dashed fs-6 gy-5">
          <thead>
            <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
              <th>Item</th>
              <th class="text-end">Qty</th>
              <th class="text-end">Koli</th>
              <th>Catatan</th>
            </tr>
          </thead>
          <tbody class="fw-bold text-gray-800">
            @foreach($rr->details as $d)
              <tr>
                <td>{{ $d->item?->sku }} - {{ $d->item?->nama_barang }}</td>
                <td class="text-end">{{ number_format((float) $d->quantity, 2, ',', '.') }}</td>
                <td class="text-end">{{ number_format((float) $d->koli, 2, ',', '.') }}</td>
                <td>{{ $d->notes ?? '-' }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection
