@extends('layouts.admin')

@section('title', 'Detail Transfer Gudang')
@section('page_title', 'Detail Transfer Gudang')

@section('content')
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div class="d-flex flex-column">
                <div class="fw-bolder fs-5">{{ $transfer->code }}</div>
                <div class="text-muted fs-7">{{ $transfer->transacted_at?->format('Y-m-d H:i') }}</div>
            </div>
        </div>
        <div class="card-toolbar">
            <a href="{{ $backUrl }}" class="btn btn-light">Kembali</a>
        </div>
    </div>
    <div class="card-body py-6">
        @php
            $defaultWarehouseId = \App\Support\WarehouseService::defaultWarehouseId();
            $displayWarehouseId = \App\Support\WarehouseService::displayWarehouseId();
            $fromId = $transfer->from_warehouse_id ?? $defaultWarehouseId;
            $toId = $transfer->to_warehouse_id ?? $defaultWarehouseId;
            $fromBadge = 'badge-light-secondary';
            $toBadge = 'badge-light-secondary';
            if ($fromId == $displayWarehouseId) {
                $fromBadge = 'badge-light-success';
            } elseif ($fromId == $defaultWarehouseId) {
                $fromBadge = 'badge-light-primary';
            }
            if ($toId == $displayWarehouseId) {
                $toBadge = 'badge-light-success';
            } elseif ($toId == $defaultWarehouseId) {
                $toBadge = 'badge-light-primary';
            }
        @endphp
        <div class="row mb-6">
            <div class="col-md-4">
                <div class="fw-bold text-gray-600">Dari Gudang</div>
                <div><span class="badge {{ $fromBadge }}">{{ $transfer->fromWarehouse?->name ?? '-' }}</span></div>
            </div>
            <div class="col-md-4">
                <div class="fw-bold text-gray-600">Ke Gudang</div>
                <div><span class="badge {{ $toBadge }}">{{ $transfer->toWarehouse?->name ?? '-' }}</span></div>
            </div>
            <div class="col-md-4">
                <div class="fw-bold text-gray-600">Status</div>
                <div>{{ $transfer->status ?? '-' }}</div>
            </div>
        </div>
        <div class="row mb-6">
            <div class="col-md-4">
                <div class="fw-bold text-gray-600">Catatan</div>
                <div>{{ $transfer->note ?? '-' }}</div>
            </div>
            <div class="col-md-4">
                <div class="fw-bold text-gray-600">QC By</div>
                <div>{{ $transfer->qcBy?->name ?? '-' }}</div>
            </div>
            <div class="col-md-4">
                <div class="fw-bold text-gray-600">QC At</div>
                <div>{{ $transfer->qc_at?->format('Y-m-d H:i') ?? '-' }}</div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                        <th>Item</th>
                        <th>Qty</th>
                        <th>Qty OK</th>
                        <th>Qty Reject</th>
                        <th>Catatan</th>
                        <th>Catatan QC</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($transfer->items as $row)
                        <tr>
                            <td>{{ $row->item?->sku }} - {{ $row->item?->name }}</td>
                            <td>{{ $row->qty }}</td>
                            <td>{{ $row->qty_ok }}</td>
                            <td>{{ $row->qty_reject }}</td>
                            <td>{{ $row->note ?? '-' }}</td>
                            <td>{{ $row->qc_note ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
