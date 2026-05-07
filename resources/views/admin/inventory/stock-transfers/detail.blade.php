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
            $formatKoli = function ($qty, $qtyPerKoli) {
                $qty = (int) $qty;
                $qtyPerKoli = (int) $qtyPerKoli;
                if ($qty <= 0 || $qtyPerKoli <= 0) {
                    return '';
                }
                $koli = intdiv($qty, $qtyPerKoli);
                $sisa = $qty % $qtyPerKoli;
                return $koli.' koli'.($sisa > 0 ? ' + '.$sisa.' pcs' : '').' x '.$qtyPerKoli;
            };
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
                <div>
                    @php
                        $status = $transfer->status ?? 'qc_pending';
                        $statusBadge = match ($status) {
                            'completed' => 'badge-light-success',
                            'canceled' => 'badge-light-danger',
                            default => 'badge-light-warning',
                        };
                        $statusLabel = match ($status) {
                            'completed' => 'Selesai',
                            'canceled' => 'Dibatalkan',
                            default => 'Menunggu QC',
                        };
                    @endphp
                    <span class="badge {{ $statusBadge }}">{{ $statusLabel }}</span>
                </div>
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
        @if($transfer->traceability_mode)
            <div class="row mb-6">
                <div class="col-md-4">
                    <div class="fw-bold text-gray-600">Traceability</div>
                    @if($transfer->traceability_mode === 'legacy')
                        <span class="badge badge-light-warning">Legacy No QR</span>
                    @else
                        <span class="badge badge-light-success">QR Inbound</span>
                    @endif
                </div>
                <div class="col-md-8">
                    <div class="fw-bold text-gray-600">Alasan Legacy</div>
                    <div>{{ $transfer->legacy_reason ?: '-' }}</div>
                </div>
            </div>
        @endif

        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                        <th>Item</th>
                        <th>Qty</th>
                        <th>Koli</th>
                        <th>Qty OK</th>
                        <th>Koli OK</th>
                        <th>Qty Reject</th>
                        <th>Koli Reject</th>
                        <th>Qty Kurang</th>
                        <th>Koli Kurang</th>
                        <th>Catatan</th>
                        <th>Catatan QC</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($transfer->items as $row)
                        <tr>
                            <td>{{ $row->item?->sku }} - {{ $row->item?->name }}</td>
                            <td>{{ $row->qty }}</td>
                            <td>{{ $formatKoli($row->qty, $row->item?->koli_qty) ?: '-' }}</td>
                            <td>{{ $row->qty_ok }}</td>
                            <td>{{ $formatKoli($row->qty_ok, $row->item?->koli_qty) ?: '-' }}</td>
                            <td>{{ $row->qty_reject }}</td>
                            <td>{{ $formatKoli($row->qty_reject, $row->item?->koli_qty) ?: '-' }}</td>
                            <td>{{ (int) ($row->qty_short ?? 0) }}</td>
                            <td>{{ $formatKoli($row->qty_short ?? 0, $row->item?->koli_qty) ?: '-' }}</td>
                            <td>{{ $row->note ?? '-' }}</td>
                            <td>{{ $row->qc_note ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @php
            $koliScans = $transfer->items->flatMap(fn ($item) => $item->koliScans ?? collect());
        @endphp
        @if($koliScans->isNotEmpty())
            <div class="separator my-8"></div>
            <div class="fw-bolder fs-5 mb-4">Jejak QR Dus Inbound</div>
            <div class="table-responsive">
                <table class="table align-middle table-row-dashed fs-7 gy-4">
                    <thead>
                        <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                            <th>Item</th>
                            <th>QR Dus</th>
                            <th>Inbound Asal</th>
                            <th class="text-end">Qty Dus</th>
                            <th class="text-end">OK</th>
                            <th class="text-end">Reject</th>
                            <th class="text-end">Kurang</th>
                            <th>Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($koliScans as $scan)
                            <tr>
                                <td>{{ $scan->koliUnit?->sku ?? $scan->item?->sku ?? '-' }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $scan->koliUnit?->code ?? '-' }}</div>
                                    <div class="text-muted">Koli {{ $scan->koliUnit?->koli_no ?? '-' }}</div>
                                </td>
                                <td>{{ $scan->koliUnit?->transaction?->code ?? '-' }}</td>
                                <td class="text-end">{{ (int) $scan->qty }}</td>
                                <td class="text-end">{{ (int) $scan->qty_ok }}</td>
                                <td class="text-end">{{ (int) $scan->qty_reject }}</td>
                                <td class="text-end">{{ (int) $scan->qty_short }}</td>
                                <td>{{ $scan->qc_note ?: '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
