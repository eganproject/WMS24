@extends('layouts.admin')

@section('title', $pageTitle)
@section('page_title', $pageTitle)

@section('content')
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div class="d-flex flex-column">
                <div class="fw-bolder fs-5">{{ $transaction->code }}</div>
                <div class="text-muted fs-7">{{ $transaction->transacted_at?->format('Y-m-d H:i') }}</div>
            </div>
        </div>
        <div class="card-toolbar">
            <a href="{{ $backUrl }}" class="btn btn-light">Kembali</a>
        </div>
    </div>
    <div class="card-body py-6">
        <div class="row mb-6">
            <div class="col-md-4">
                <div class="fw-bold text-gray-600">Ref No</div>
                <div>{{ $transaction->ref_no ?? '-' }}</div>
            </div>
            @if(!empty($showSupplierField ?? false))
                <div class="col-md-4">
                    <div class="fw-bold text-gray-600">Supplier</div>
                    <div>{{ $transaction->supplier?->name ?? '-' }}</div>
                </div>
            @endif
            @if(isset($transaction->surat_jalan_no) || isset($transaction->surat_jalan_at))
                <div class="col-md-4">
                    <div class="fw-bold text-gray-600">Surat Jalan</div>
                    <div>
                        {{ $transaction->surat_jalan_no ?? '-' }}
                        @if(!empty($transaction->surat_jalan_at))
                            <div class="text-muted fs-7">{{ $transaction->surat_jalan_at?->format('Y-m-d') }}</div>
                        @endif
                    </div>
                </div>
            @endif
            <div class="col-md-4">
                <div class="fw-bold text-gray-600">Catatan</div>
                <div>{{ $transaction->note ?? '-' }}</div>
            </div>
            @if(!empty($statusLabel ?? null))
                <div class="col-md-4">
                    <div class="fw-bold text-gray-600">Status</div>
                    @php
                        $statusClass = ($transaction->status ?? '') === 'completed'
                            ? 'badge-light-success'
                            : (($transaction->status ?? '') === 'scanning' ? 'badge-light-primary' : 'badge-light-warning');
                    @endphp
                    <div><span class="badge {{ $statusClass }}">{{ $statusLabel }}</span></div>
                </div>
            @endif
            @if(!empty($warehouseLabel ?? null))
                <div class="col-md-4">
                    <div class="fw-bold text-gray-600">Gudang</div>
                    @php
                        $defaultWarehouseId = \App\Support\WarehouseService::defaultWarehouseId();
                        $displayWarehouseId = \App\Support\WarehouseService::displayWarehouseId();
                        $currentWarehouseId = $transaction->warehouse_id ?? $defaultWarehouseId;
                        $warehouseBadge = 'badge-light-secondary';
                        if ($currentWarehouseId == $displayWarehouseId) {
                            $warehouseBadge = 'badge-light-success';
                        } elseif ($currentWarehouseId == $defaultWarehouseId) {
                            $warehouseBadge = 'badge-light-primary';
                        }
                    @endphp
                    <div><span class="badge {{ $warehouseBadge }}">{{ $warehouseLabel }}</span></div>
                </div>
            @endif
            <div class="col-md-4">
                <div class="fw-bold text-gray-600">Total Qty</div>
                <div>{{ $totalQty }}</div>
            </div>
            @if(!empty($showKoli ?? false))
                <div class="col-md-4">
                    <div class="fw-bold text-gray-600">Total Koli</div>
                    <div>{{ ($totalKoli ?? 0) > 0 ? $totalKoli : '-' }}</div>
                </div>
            @endif
            @if(!empty($scanSession ?? null))
                <div class="col-md-4">
                    <div class="fw-bold text-gray-600">Progress Scan</div>
                    <div>
                        Qty {{ $scanSummary['scanned_qty'] ?? 0 }}/{{ $scanSummary['expected_qty'] ?? 0 }}
                        <div class="text-muted fs-7">Koli {{ $scanSummary['scanned_koli'] ?? 0 }}/{{ $scanSummary['expected_koli'] ?? 0 }}</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="fw-bold text-gray-600">Audit Scan</div>
                    <div class="text-muted fs-7">
                        Mulai: {{ $scanSession->started_at?->format('Y-m-d H:i') ?? '-' }} / {{ $scanSession->starter?->name ?? '-' }}
                    </div>
                    <div class="text-muted fs-7">
                        Selesai: {{ $scanSession->completed_at?->format('Y-m-d H:i') ?? '-' }} / {{ $scanSession->completer?->name ?? '-' }}
                    </div>
                </div>
            @endif
        </div>

        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                        <th>Item</th>
                        @if(!empty($showKoli ?? false))
                            <th>Koli</th>
                        @endif
                        <th>Qty</th>
                        @if(!empty($scanSession ?? null))
                            <th>Scan Koli</th>
                            <th>Scan Qty</th>
                        @endif
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($transaction->items as $row)
                        @php
                            $scanItem = !empty($scanSession ?? null)
                                ? $scanSession->items->firstWhere('item_id', $row->item_id)
                                : null;
                        @endphp
                        <tr>
                            <td>{{ $row->item?->sku }} - {{ $row->item?->name }}</td>
                            @if(!empty($showKoli ?? false))
                                <td>{{ $row->koli ?? '-' }}</td>
                            @endif
                            <td>{{ $row->qty }}</td>
                            @if(!empty($scanSession ?? null))
                                <td>{{ $scanItem?->scanned_koli ?? '-' }}</td>
                                <td>{{ $scanItem?->scanned_qty ?? '-' }}</td>
                            @endif
                            <td>{{ $row->note ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
