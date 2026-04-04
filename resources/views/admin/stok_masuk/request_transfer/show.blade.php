@extends('layouts.app')

@push('styles')
    <style>
        :root {
            --doc-text: #1f2937;
            --doc-muted: #6b7280;
            --doc-border: #d1d5db;
        }

        .doc-wrapper {
            background: #fff;
            border: 1px solid var(--doc-border);
            border-radius: .5rem;
            padding: 28px;
        }

        .doc-header {
            display: grid;
            grid-template-columns: 160px 1fr 220px;
            gap: 16px;
            align-items: center;
            margin-bottom: 18px;
        }

        .doc-title {
            text-align: center;
            font-weight: 700;
            color: var(--doc-text);
            letter-spacing: .06em;
        }

        .doc-meta {
            border: 1px solid var(--doc-border);
            border-radius: .375rem;
            padding: 12px;
            font-size: .925rem;
        }

        .meta-row { display: flex; justify-content: space-between; margin-bottom: 6px; }
        .meta-label { color: var(--doc-muted); }
        .meta-value { color: var(--doc-text); font-weight: 600; }

        .section-title { font-weight: 700; color: var(--doc-text); margin-bottom: 8px; }
        .section-box { border: 1px solid var(--doc-border); border-radius: .375rem; padding: 12px; }

        .table-doc {
            width: 100%;
            border-collapse: collapse;
            font-size: .925rem;
        }
        .table-doc th, .table-doc td {
            border: 1px solid var(--doc-border);
            padding: 10px 12px;
            vertical-align: top;
        }
        .table-doc thead th { background: #f9fafb; color: var(--doc-text); font-weight: 700; }
        .table-doc tfoot th, .table-doc tfoot td { background: #fcfcfd; font-weight: 700; }

        .signatures { display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; margin-top: 36px; }
        .sign-box { text-align: center; }
        .sign-line { margin: 56px auto 8px; height: 1px; background: var(--doc-border); width: 80%; }
        .sign-role { color: var(--doc-muted); font-size: .9rem; }

        .no-print { display: inline-flex; }

        .doc-ribbon-top {
            width: 100%;
            padding: 10px 14px;
            font-weight: 700;
            letter-spacing: .03em;
            text-transform: uppercase;
            color: #111827;
            border-bottom: 1px solid var(--doc-border);
            border-top-left-radius: .5rem;
            border-top-right-radius: .5rem;
            background: #f3f4f6;
        }

        @media print {
            @page { size: A4; margin: 12mm; }

            body * { visibility: hidden; }
            #kt_content, #kt_content * { visibility: visible; }
            #kt_content { position: absolute; left: 0; top: 0; width: 100%; }

            .no-print, .ribbon-label, .doc-ribbon-top { display: none !important; }
            .card-body { padding: 0 !important; }
            .doc-wrapper { border: none; padding: 0; }
            .shipments-print-break { page-break-before: always; }
        }
    </style>
@endpush

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Detail Request Transfer',
        'breadcrumbs' => ['Admin', 'Stok Masuk', 'Request Transfer', 'Detail'],
    ])
@endpush

@section('content')
    <div class="content flex-row-fluid" id="kt_content">
        @php
            $status = $transferRequest->status;
            $ribbonBg = 'primary';
            if ($status === 'completed') { $ribbonBg = 'success'; }
            elseif ($status === 'rejected') { $ribbonBg = 'danger'; }
            elseif ($status === 'on_progress') { $ribbonBg = 'warning'; }
        @endphp
        <div class="card">
            <div class="doc-ribbon-top d-print-none">
                Status: <span class="badge badge-light-{{ $ribbonBg }}">{{ $status ? ucfirst($status) : '-' }}</span>
            </div>
            <div class="card-body p-lg-12">
                <div class="doc-wrapper">
                    <div class="doc-header">
                        <div></div>
                        <div>
                            <div class="doc-title fs-2">REQUEST TRANSFER</div>
                            <div class="text-center" style="color:var(--doc-muted); font-size:.95rem;">Dokumen Permintaan Perpindahan Stok</div>
                        </div>
                        <div class="doc-meta">
                            <div class="meta-row"><span class="meta-label">Kode</span><span class="meta-value">{{ $transferRequest->code ?? '-' }}</span></div>
                            <div class="meta-row"><span class="meta-label">Tanggal</span><span class="meta-value">{{ $transferRequest->date ? \Carbon\Carbon::parse($transferRequest->date)->format('d M Y') : '-' }}</span></div>
                            @php
                                $badgeClass = 'primary';
                                if ($transferRequest->status === 'completed') { $badgeClass = 'success'; }
                                elseif ($transferRequest->status === 'rejected') { $badgeClass = 'danger'; }
                                elseif ($transferRequest->status === 'on_progress') { $badgeClass = 'warning'; }
                            @endphp
                            <div class="meta-row"><span class="meta-label">Status</span><span class="meta-value"><span class="badge badge-light-{{ $badgeClass }}">{{ $transferRequest->status ? ucfirst($transferRequest->status) : '-' }}</span></span></div>
                        </div>
                    </div>

                    <div class="row g-6 mb-6">
                        <div class="col-md-6">
                            <div class="section-title">Gudang Asal</div>
                            <div class="section-box">
                                <div class="meta-value">{{ optional($transferRequest->fromWarehouse)->name ?? '-' }}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="section-title">Gudang Tujuan</div>
                            <div class="section-box">
                                <div class="meta-value">{{ optional($transferRequest->toWarehouse)->name ?? '-' }}</div>
                            </div>
                        </div>
                    </div>

                    

                    @php
                        $totalQuantity = $transferRequest->items->sum('quantity');
                        $totalKoli = $transferRequest->items->sum('koli');
                    @endphp

                    <div class="section-title">Daftar Item</div>
                    <div class="table-responsive">
                        <table class="table-doc">
                            <thead>
                                <tr>
                                    <th style="width:54px">No</th>
                                    <th style="width:160px">SKU</th>
                                    <th>Nama Item</th>
                                    <th style="width:140px; text-align:right;">Kuantitas</th>
                                    <th style="width:140px; text-align:right;">Koli</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($transferRequest->items as $idx => $item)
                                    <tr>
                                        <td>{{ $idx + 1 }}</td>
                                        <td>{{ optional($item->item)->sku ?? '-' }}</td>
                                        <td>
                                            <div class="meta-value">{{ optional($item->item)->nama_barang ?? '-' }}</div>
                                            @if(optional($item->item)->description)
                                                <div class="meta-label">{{ $item->item->description }}</div>
                                            @endif
                                        </td>
                                        <td style="text-align:right;">{{ number_format($item->quantity ?? 0, 0, ',', '.') }}</td>
                                        <td style="text-align:right;">{{ number_format($item->koli ?? 0, 2, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="3" style="text-align:right;">Total</th>
                                    <th style="text-align:right;">{{ number_format($totalQuantity, 0, ',', '.') }}</th>
                                    <th style="text-align:right;">{{ number_format($totalKoli, 2, ',', '.') }}</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="row g-6 mt-8">
                        <div class="col-12">
                            <div class="section-title">Catatan</div>
                            <div class="section-box">{{ $transferRequest->description ?? '-' }}</div>
                        </div>
                    </div>

                    

                    @if(isset($shipments) && $shipments->count())
                        <div class="shipments-print-break" style="margin-top:36px;">
                            <div class="section-title fs-4">Lampiran: Pengiriman</div>
                            <div class="accordion" id="shipmentsAccordion">
                                @foreach ($shipments as $shipment)
                                    @php
                                        $shipQty = $shipment->itemDetails->sum('quantity_shipped');
                                        $shipKoli = $shipment->itemDetails->sum('koli_shipped');
                                        $collapseId = 'shipment-'.$shipment->id;
                                        $st = $shipment->status ?? '-';
                                        $badge = 'primary';
                                        if (in_array(strtolower($st), ['completed', 'selesai'])) { $badge = 'success'; }
                                        elseif (in_array(strtolower($st), ['on_progress', 'dalam perjalanan', 'shipped'])) { $badge = 'warning'; }
                                    @endphp
                                    <div class="accordion-item mb-4">
                                        <h2 class="accordion-header" id="heading-{{ $shipment->id }}">
                                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $collapseId }}" aria-expanded="false" aria-controls="{{ $collapseId }}">
                                                <div class="d-flex w-100 justify-content-between align-items-center">
                                                    <div>
                                                        <div class="fw-bold">{{ $shipment->code ?? '-' }}</div>
                                                        <div class="text-muted small">Tanggal: {{ $shipment->shipping_date ? \Carbon\Carbon::parse($shipment->shipping_date)->format('d M Y') : '-' }}</div>
                                                        <div class="mt-1">
                                                            <span class="badge badge-light-{{ $badge }}">Status: {{ ucfirst($st) }}</span>
                                                        </div>
                                                    </div>
                                                    <div class="text-end">
                                                        <div class="meta-label">Total Dikirim</div>
                                                        <div class="meta-value">{{ number_format($shipQty, 0, ',', '.') }} Qty • {{ number_format($shipKoli, 2, ',', '.') }} Koli</div>
                                                    </div>
                                                </div>
                                            </button>
                                        </h2>
                                        <div id="{{ $collapseId }}" class="accordion-collapse collapse" aria-labelledby="heading-{{ $shipment->id }}" data-bs-parent="#shipmentsAccordion">
                                            <div class="accordion-body">
                                                <div class="row g-4 mb-4">
                                                    <div class="col-md-6">
                                                        <div class="section-title mb-2">Detail Pengiriman</div>
                                                        <div class="section-box">
                                                            <div class="row mb-1"><div class="col-5 meta-label">Jenis Kendaraan</div><div class="col-7 meta-value">{{ $shipment->vehicle_type ?? '-' }}</div></div>
                                                            <div class="row mb-1"><div class="col-5 meta-label">No. Polisi</div><div class="col-7 meta-value">{{ $shipment->license_plate ?? '-' }}</div></div>
                                                            <div class="row mb-1"><div class="col-5 meta-label">Keterangan</div><div class="col-7 meta-value">{{ $shipment->description ?? '-' }}</div></div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="section-box">
                                                            <div class="section-title mb-2">Informasi Pengemudi</div>
                                                            <div class="row mb-1"><div class="col-5 meta-label">Nama</div><div class="col-7 meta-value">{{ $shipment->driver_name ?? '-' }}</div></div>
                                                            <div class="row mb-1"><div class="col-5 meta-label">Kontak</div><div class="col-7 meta-value">{{ $shipment->driver_contact ?? '-' }}</div></div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="table-responsive">
                                                    <table class="table-doc">
                                                        <thead>
                                                            <tr>
                                                                <th style="width:160px">SKU</th>
                                                                <th>Item</th>
                                                                <th style="width:140px; text-align:right;">Qty Dikirim</th>
                                                                <th style="width:140px; text-align:right;">Koli Dikirim</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @foreach ($shipment->itemDetails as $detail)
                                                                <tr>
                                                                    <td>{{ optional($detail->item)->sku ?? '-' }}</td>
                                                                    <td>{{ optional($detail->item)->nama_barang ?? '-' }}</td>
                                                                    <td style="text-align:right;">{{ number_format($detail->quantity_shipped, 0, ',', '.') }}</td>
                                                                    <td style="text-align:right;">{{ number_format($detail->koli_shipped, 2, ',', '.') }}</td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                        <tfoot>
                                                            <tr>
                                                                <th colspan="2" style="text-align:right;">Total</th>
                                                                <th style="text-align:right;">{{ number_format($shipQty, 0, ',', '.') }}</th>
                                                                <th style="text-align:right;">{{ number_format($shipKoli, 2, ',', '.') }}</th>
                                                            </tr>
                                                        </tfoot>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="d-flex justify-content-between mt-8 no-print">
                        <a href="{{ route('admin.stok-masuk.request-transfer.index') }}" class="btn btn-light">Kembali</a>
                        <button type="button" class="btn btn-success" id="print_button">Print</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $('#print_button').on('click', function () { window.print(); });
    </script>
@endpush



