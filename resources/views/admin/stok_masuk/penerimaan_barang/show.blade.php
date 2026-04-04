@extends('layouts.app')

@push('styles')
    <style>
        :root{
            --ink:#111827; --muted:#6B7280; --line:#E5E7EB; --chip:#F3F4F6; --chip-ink:#374151; --chip-success:#ECFDF5; --chip-success-ink:#047857; --chip-warning:#FEF3C7; --chip-warning-ink:#92400E; --chip-danger:#FEE2E2; --chip-danger-ink:#991B1B;
        }
        @media print {
            body * { visibility: hidden; }
            #kt_content, #kt_content * { visibility: visible; }
            #kt_content { position: absolute; left: 0; top: 0; width: 100%; }
            .no-print { display: none !important; }
            .card { box-shadow: none !important; }
        }
        .doc-header { border-bottom: 2px solid var(--line); padding-bottom: 16px; margin-bottom: 22px; }
        .brand { display:flex; align-items:center; gap:12px; }
        .brand .logo { width:40px; height:40px; }
        .brand .name { font-weight:800; color:var(--ink); font-size:1.05rem; letter-spacing:.4px; }
        .doc-title { font-size: 26px; font-weight: 800; letter-spacing:.6px; text-transform: uppercase; color:var(--ink); }
        .doc-sub { color: var(--muted); }
        .chip { display:inline-block; padding:.35rem .65rem; border-radius: .5rem; font-weight:700; font-size:.825rem; }
        .chip-default{ background:var(--chip); color:var(--chip-ink); }
        .chip-success{ background:var(--chip-success); color:var(--chip-success-ink); }
        .chip-warning{ background:var(--chip-warning); color:var(--chip-warning-ink); }
        .chip-danger{ background:var(--chip-danger); color:var(--chip-danger-ink); }
        .meta-label { color: var(--muted); font-weight: 600; font-size: .85rem; }
        .meta-value { font-weight: 700; color: var(--ink); }
        .info-box { border: 1px solid #EFF2F5; border-radius: .475rem; padding: 1rem; background:#fff; }
        .section-title { font-weight:700; color:var(--ink); margin-bottom:.5rem; }
        .table-modern thead th{ background:#F9FAFB; border-bottom:1px solid var(--line); text-transform:uppercase; letter-spacing:.4px; font-size:.75rem; }
        .table-modern tbody tr{ border-bottom:1px dashed var(--line); }
        .table-modern tfoot td{ border-top:2px solid var(--line); font-weight:800; }
        .signature-box { height: 80px; border-top: 1px dashed #ccc; margin-top: 40px; text-align: center; padding-top: 10px; }
    </style>
@endpush

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Detail Penerimaan Barang',
        'breadcrumbs' => ['Admin', 'Stok Masuk', 'Penerimaan Barang', 'Detail'],
    ])
@endpush

@section('content')
    <div class="content flex-row-fluid" id="kt_content">
        <div class="card">
            <div class="card-body p-lg-10">
                <div class="doc-header d-flex justify-content-between align-items-center">
                    <div class="brand">
                        <img class="logo" src="{{ asset('metronic/assets/media/svg/brand-logos/code-lab.svg') }}" alt="Logo">
                        <div>
                            <div class="doc-title">Goods Receipt</div>
                            <div class="doc-sub">Dokumen Penerimaan Barang</div>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="meta-label">Kode Dokumen</div>
                        <div class="meta-value">{{ $goodsReceipt->code }}</div>
                        <div class="meta-label mt-2">Tanggal</div>
                        <div class="meta-value">{{ optional($goodsReceipt->receipt_date)->format('d M Y') }}</div>
                        @php
                            $st = $goodsReceipt->status;
                            $chipClass = 'chip-default';
                            if ($st === \App\Models\GoodsReceipt::STATUS_PARTIAL) $chipClass = 'chip-warning';
                            elseif ($st === \App\Models\GoodsReceipt::STATUS_COMPLETED) $chipClass = 'chip-success';
                        @endphp
                        <div class="mt-3"><span class="chip {{ $chipClass }}">Status: {{ ucwords(str_replace('_',' ',$st)) }}</span></div>
                    </div>
                </div>

                <div class="row mb-8">
                    <div class="col-md-5">
                        <div class="info-box h-100">
                            <div class="section-title">Informasi Tujuan</div>
                            <div class="row gy-2">
                                <div class="col-5 meta-label">Gudang Tujuan</div>
                                <div class="col-7 meta-value">{{ $goodsReceipt->warehouse->name ?? '-' }}</div>
                                <div class="col-5 meta-label">Diterima Oleh</div>
                                <div class="col-7 meta-value">{{ $goodsReceipt->receiver->name ?? '-' }}</div>
                                <div class="col-5 meta-label">Diverifikasi</div>
                                <div class="col-7 meta-value">{{ $goodsReceipt->verifier->name ?? '-' }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-7">
                        <div class="info-box h-100">
                            <div class="section-title">Informasi Pengiriman</div>
                            <div class="row gy-2">
                                <div class="col-5 meta-label">No. Pengiriman</div>
                                <div class="col-7 meta-value">{{ $goodsReceipt->shipment->code ?? '-' }}</div>
                                <div class="col-5 meta-label">Tanggal Kirim</div>
                                <div class="col-7 meta-value">{{ $goodsReceipt->shipment->shipping_date ?? '-' }}</div>
                                <div class="col-5 meta-label">Kendaraan</div>
                                <div class="col-7 meta-value">{{ trim(($goodsReceipt->shipment->vehicle_type ?? '').' '.($goodsReceipt->shipment->license_plate ?? '')) ?: '-' }}</div>
                                <div class="col-5 meta-label">Supir</div>
                                <div class="col-7 meta-value">{{ $goodsReceipt->shipment->driver_name ?? '-' }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-8">
                    <div class="section-title">Daftar Barang</div>
                    <div class="table-responsive">
                        <table class="table table-row-dashed align-middle table-modern">
                            <thead>
                                <tr class="text-start text-gray-700 fw-bolder fs-7 text-uppercase gs-0">
                                    <th class="w-40px">No</th>
                                    <th>Item</th>
                                    <th class="w-120px text-end">
                                        @if(($goodsReceipt->type ?? 'transfer') === 'pengadaan')
                                            Sisa Qty Order
                                        @else
                                            Qty Dikirim
                                        @endif
                                    </th>
                                    <th class="w-120px text-end">
                                        @if(($goodsReceipt->type ?? 'transfer') === 'pengadaan')
                                            Sisa Koli Order
                                        @else
                                            Koli Dikirim
                                        @endif
                                    </th>
                                    <th class="w-120px text-end">Qty Diterima</th>
                                    <th class="w-120px text-end">Koli Diterima</th>
                                    <th>Catatan</th>
                                </tr>
                            </thead>
                            <tbody class="fw-bold text-gray-800">
                                @php $totOrd=0; $totRecv=0; $totOrdKoli=0.0; $totRecvKoli=0.0; @endphp
                                @foreach ($goodsReceipt->details as $i => $detail)
                                    @php
                                        $totOrd += (float) ($detail->ordered_quantity ?? 0);
                                        $totRecv += (float) ($detail->received_quantity ?? 0);
                                        $totOrdKoli += (float) ($detail->ordered_koli ?? 0);
                                        $totRecvKoli += (float) ($detail->received_koli ?? 0);
                                    @endphp
                                    <tr>
                                        <td>{{ $i + 1 }}</td>
                                        <td>
                                            <div class="d-flex flex-column">
                                                <span class="meta-label">{{ $detail->item->sku ?? '-' }}</span>
                                                <span class="meta-value">{{ $detail->item->nama_barang ?? $detail->item->name ?? '-' }}</span>
                                            </div>
                                        </td>
                                        <td class="text-end">{{ number_format((float) ($detail->ordered_quantity ?? 0), 2, ',', '.') }}</td>
                                        <td class="text-end">{{ number_format((float) ($detail->ordered_koli ?? 0), 2, ',', '.') }}</td>
                                        <td class="text-end">{{ number_format((float) ($detail->received_quantity ?? 0), 2, ',', '.') }}</td>
                                        <td class="text-end">{{ number_format((float) ($detail->received_koli ?? 0), 2, ',', '.') }}</td>
                                        <td>{{ $detail->notes ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="fw-bolder">
                                    <td colspan="2" class="text-end">Total</td>
                                    <td class="text-end">{{ number_format($totOrd, 2, ',', '.') }}</td>
                                    <td class="text-end">{{ number_format($totOrdKoli, 2, ',', '.') }}</td>
                                    <td class="text-end">{{ number_format($totRecv, 2, ',', '.') }}</td>
                                    <td class="text-end">{{ number_format($totRecvKoli, 2, ',', '.') }}</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                @if(!empty($goodsReceipt->description))
                    <div class="mb-8">
                        <div class="fw-bold mb-2">Catatan</div>
                        <div class="info-box">{{ $goodsReceipt->description }}</div>
                    </div>
                @endif

                <div class="row mt-12 no-print">
                    <div class="col-12 d-flex justify-content-end gap-2">
                        @if($goodsReceipt->status === \App\Models\GoodsReceipt::STATUS_COMPLETED)
                            <a href="{{ route('admin.stok-masuk.penerimaan-barang.bukti', $goodsReceipt->id) }}" target="_blank" class="btn btn-primary">Cetak Bukti</a>
                        @endif
                        <a href="{{ route('admin.stok-masuk.penerimaan-barang.index') }}" class="btn btn-light">Kembali</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
