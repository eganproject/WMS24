@extends('layouts.app')

@push('styles')
    <style>
        @media print {
            body * { visibility: hidden; }
            #kt_content, #kt_content * { visibility: visible; }
            #kt_content { position: absolute; left: 0; top: 0; width: 100%; }
            .no-print { display: none !important; }
        }
        .doc-header { border-bottom: 2px solid #E5E7EB; padding-bottom: 16px; margin-bottom: 22px; }
        .brand { display:flex; align-items:center; gap:12px; }
        .brand .logo { width:40px; height:40px; }
        .doc-title { font-size: 26px; font-weight: 800; letter-spacing:.6px; text-transform: uppercase; color:#111827; }
        .doc-sub { color: #6B7280; }
        .chip { display:inline-block; padding:.35rem .65rem; border-radius: .5rem; font-weight:700; font-size:.825rem; }
        .chip-default{ background:#F3F4F6; color:#374151; }
        .chip-success{ background:#ECFDF5; color:#047857; }
        .chip-warning{ background:#FEF3C7; color:#92400E; }
        .meta td { padding: 2px 0; }
        .box { border: 1px solid #e5e5e5; border-radius: .475rem; padding: 1rem; }
        .signature-box { height: 80px; border-top: 1px dashed #ccc; margin-top: 40px; text-align: center; padding-top: 10px; }
        /* Match table style with show */
        .table-modern thead th{ background:#F9FAFB; border-bottom:1px solid #E5E7EB; text-transform:uppercase; letter-spacing:.4px; font-size:.75rem; }
        .table-modern tbody tr{ border-bottom:1px dashed #E5E7EB; }
        .table-modern tfoot td{ border-top:2px solid #E5E7EB; font-weight:800; }
        .meta-label { color: #6B7280; font-weight: 600; font-size: .85rem; }
        .meta-value { font-weight: 700; color: #111827; }
    </style>
@endpush

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Bukti Penerimaan Barang',
        'breadcrumbs' => ['Admin', 'Stok Masuk', 'Penerimaan Barang', 'Bukti', $goodsReceipt->code],
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
                            <div class="doc-title">Bukti Penerimaan Barang</div>
                            <div class="doc-sub">Dokumen resmi penerimaan barang</div>
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
                        <div class="box h-100">
                            <div class="fw-bold mb-2">Informasi Tujuan</div>
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
                        <div class="box h-100">
                            <div class="fw-bold mb-2">Informasi Pengiriman</div>
                            <div class="row gy-2">
                                <div class="col-5 meta-label">No. Pengiriman</div>
                                <div class="col-7 meta-value">{{ $goodsReceipt->shipment->code ?? '-' }}</div>
                                <div class="col-5 meta-label">Tanggal Kirim</div>
                                <div class="col-7 meta-value">{{ optional($goodsReceipt->shipment?->shipping_date)->format('d M Y') ?? '-' }}</div>
                                <div class="col-5 meta-label">Kendaraan</div>
                                <div class="col-7 meta-value">{{ trim(($goodsReceipt->shipment->vehicle_type ?? '').' '.($goodsReceipt->shipment->license_plate ?? '')) ?: '-' }}</div>
                                <div class="col-5 meta-label">Supir</div>
                                <div class="col-7 meta-value">{{ $goodsReceipt->shipment->driver_name ?? '-' }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-8">
                    <div class="fw-bold mb-2">Daftar Barang Diterima</div>
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
                                @php $totOrd=0; $totOrdKoli=0; $totRecv=0; $totRecvKoli=0; @endphp
                                @foreach ($goodsReceipt->details as $i => $d)
                                    @php
                                        $totOrd += (float) ($d->ordered_quantity ?? 0);
                                        $totOrdKoli += (float) ($d->ordered_koli ?? 0);
                                        $totRecv += (float) ($d->received_quantity ?? 0);
                                        $totRecvKoli += (float) ($d->received_koli ?? 0);
                                    @endphp
                                    <tr>
                                        <td>{{ $i + 1 }}</td>
                                        <td>
                                            <div class="d-flex flex-column">
                                                <span class="meta-label">{{ $d->item->sku ?? '-' }}</span>
                                                <span class="meta-value">{{ $d->item->nama_barang ?? $d->item->name ?? '-' }}</span>
                                            </div>
                                        </td>
                                        <td class="text-end">{{ number_format((float) ($d->ordered_quantity ?? 0), 2, ',', '.') }}</td>
                                        <td class="text-end">{{ number_format((float) ($d->ordered_koli ?? 0), 2, ',', '.') }}</td>
                                        <td class="text-end">{{ number_format((float) ($d->received_quantity ?? 0), 2, ',', '.') }}</td>
                                        <td class="text-end">{{ number_format((float) ($d->received_koli ?? 0), 2, ',', '.') }}</td>
                                        <td>{{ $d->notes ?? '-' }}</td>
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
                        <div class="box">{{ $goodsReceipt->description }}</div>
                    </div>
                @endif

                <div class="row mt-12">
                    <div class="col-md-4 text-center">
                        <div class="fw-bold mb-10">Diserahkan Oleh</div>
                        <div class="signature-box">( Tanda Tangan & Nama Jelas )</div>
                    </div>
                    <div class="col-md-4 text-center">
                        <div class="fw-bold mb-10">Diterima Oleh</div>
                        <div class="signature-box">( Tanda Tangan & Nama Jelas )</div>
                    </div>
                    <div class="col-md-4 text-center">
                        <div class="fw-bold mb-10">Mengetahui</div>
                        <div class="signature-box">( Tanda Tangan & Nama Jelas )</div>
                    </div>
                </div>

                <div class="mt-10 d-flex gap-3 no-print">
                    <a href="{{ route('admin.stok-masuk.penerimaan-barang.index') }}" class="btn btn-secondary">Kembali</a>
                    <button onclick="window.print()" class="btn btn-primary">Cetak</button>
                </div>
            </div>
        </div>
    </div>
@endsection
