@extends('layouts.app')

@push('styles')
    <style>
        :root { --doc-text:#1f2937; --doc-muted:#6b7280; --doc-border:#d1d5db; }
        .doc-wrapper { background:#fff; border:1px solid var(--doc-border); border-radius:.5rem; padding:28px; }
        .doc-header { display:grid; grid-template-columns: 1fr 220px; gap:16px; align-items:center; margin-bottom:18px; }
        .doc-title { text-align:left; font-weight:700; color:var(--doc-text); letter-spacing:.06em; }
        .doc-meta { border:1px solid var(--doc-border); border-radius:.375rem; padding:12px; font-size:.925rem; }
        .meta-row{ display:flex; justify-content:space-between; margin-bottom:6px; }
        .meta-label{ color:var(--doc-muted); }
        .meta-value{ color:var(--doc-text); font-weight:600; }
        .section-title{ font-weight:700; color:var(--doc-text); margin-bottom:8px; }
        .section-box{ border:1px solid var(--doc-border); border-radius:.375rem; padding:12px; }
        .table-doc{ width:100%; border-collapse:collapse; font-size:.925rem; }
        .table-doc th,.table-doc td{ border:1px solid var(--doc-border); padding:10px 12px; vertical-align:top; word-break:break-word; }
        .table-doc thead th{ background:#f9fafb; color:var(--doc-text); font-weight:700; }
        .table-doc tfoot th,.table-doc tfoot td{ background:#fcfcfd; font-weight:700; }
        .doc-ribbon-top{ width:100%; padding:10px 14px; font-weight:700; letter-spacing:.03em; text-transform:uppercase; color:#111827; border-bottom:1px solid var(--doc-border); border-top-left-radius:.5rem; border-top-right-radius:.5rem; background:#f3f4f6; }
        .no-print{ display:inline-flex; }
        @media print{
            @page { size:A4 portrait; margin:12mm; }
            body *{ visibility:hidden; }
            #kt_content, #kt_content *{ visibility:visible; }
            #kt_content{ position:absolute; left:0; top:0; width:100%; }
            .no-print, .ribbon-label, .doc-ribbon-top{ display:none !important; }
            .card-body{ padding:0 !important; }
            .doc-wrapper{ border:none; padding:0; }
            .shipments-print-break{ page-break-before: always; }
            .table-doc th,.table-doc td{ padding:6px 8px; font-size:.9rem; }
            .section-box, .doc-meta{ page-break-inside: avoid; }
            table{ page-break-inside:auto; }
            tr{ page-break-inside:avoid; page-break-after:auto; }
        }
    </style>
@endpush

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Surat Jalan',
        'breadcrumbs' => ['Admin', 'Riwayat Pengiriman', 'Surat Jalan'],
    ])
@endpush

@section('content')
    <div class="content flex-row-fluid" id="kt_content">
        @php
            $status = $shipment->status;
            $ribbonBg = 'primary';
            if (strtolower((string)$status) === 'selesai' || strtolower((string)$status) === 'completed') { $ribbonBg = 'success'; }
            elseif (strtolower((string)$status) === 'dalam perjalanan' || strtolower((string)$status) === 'on_progress') { $ribbonBg = 'warning'; }
        @endphp
        <div class="card">
            <div class="doc-ribbon-top d-print-none">
                Status: <span class="badge badge-light-{{ $ribbonBg }}">{{ $status ? ucfirst($status) : '-' }}</span>
            </div>
            <div class="card-body p-lg-12">
                <div class="doc-wrapper">
                    <div class="doc-header">
                        <div>
                            <div class="doc-title fs-2">SURAT JALAN</div>
                            <div style="color:var(--doc-muted); font-size:.95rem;">Dokumen Pengiriman Barang</div>
                        </div>
                        <div class="doc-meta">
                            <div class="meta-row"><span class="meta-label">Kode SJ</span><span class="meta-value">{{ $shipment->code ?? '-' }}</span></div>
                            <div class="meta-row"><span class="meta-label">Tanggal Kirim</span><span class="meta-value">{{ $shipment->shipping_date ? \Carbon\Carbon::parse($shipment->shipping_date)->format('d M Y') : '-' }}</span></div>
                            <div class="meta-row"><span class="meta-label">Referensi</span><span class="meta-value">{{ $referenceCode ?? '-' }}</span></div>
                            <div class="meta-row"><span class="meta-label">Status</span><span class="meta-value"><span class="badge badge-light-{{ $ribbonBg }}">{{ $status ? ucfirst($status) : '-' }}</span></span></div>
                        </div>
                    </div>

                    <div class="row g-6 mb-6">
                        <div class="col-md-6">
                            <div class="section-title">Gudang Asal</div>
                            <div class="section-box">
                                <div class="meta-value">{{ $fromWarehouse ?? '-' }}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="section-title">Gudang Tujuan</div>
                            <div class="section-box">
                                <div class="meta-value">{{ $toWarehouse ?? '-' }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-6 mb-8">
                        <div class="col-md-6">
                            <div class="section-title">Kendaraan</div>
                            <div class="section-box">
                                <div class="meta-row"><span class="meta-label">Jenis</span><span class="meta-value">{{ $shipment->vehicle_type ?? '-' }}</span></div>
                                <div class="meta-row"><span class="meta-label">No. Polisi</span><span class="meta-value">{{ $shipment->license_plate ?? '-' }}</span></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="section-title">Pengemudi</div>
                            <div class="section-box">
                                <div class="meta-row"><span class="meta-label">Nama</span><span class="meta-value">{{ $shipment->driver_name ?? '-' }}</span></div>
                                <div class="meta-row"><span class="meta-label">Kontak</span><span class="meta-value">{{ $shipment->driver_contact ?? '-' }}</span></div>
                            </div>
                        </div>
                    </div>

                    @php
                        $totQty = (float) ($shipment->itemDetails->sum('quantity_shipped') ?? 0);
                        $totKoli = (float) ($shipment->itemDetails->sum('koli_shipped') ?? 0);
                    @endphp
                    <div class="section-title">Daftar Barang</div>
                    <div class="table-responsive">
                        <table class="table-doc">
                            <thead>
                                <tr>
                                    <th style="width:48px">No</th>
                                    <th style="width:140px">SKU</th>
                                    <th>Nama Item</th>
                                    <th style="width:140px; text-align:right;">Qty</th>
                                    <th style="width:140px; text-align:right;">Koli</th>
                                    <th>Keterangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($shipment->itemDetails as $i => $d)
                                    <tr>
                                        <td>{{ $i + 1 }}</td>
                                        <td>{{ optional($d->item)->sku ?? '-' }}</td>
                                        <td>{{ optional($d->item)->nama_barang ?? ($d->item->name ?? '-') }}</td>
                                        <td style="text-align:right;">{{ number_format($d->quantity_shipped ?? 0, 0, ',', '.') }}</td>
                                        <td style="text-align:right;">{{ number_format($d->koli_shipped ?? 0, 2, ',', '.') }}</td>
                                        <td>{{ $d->description ?? '-' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center" style="color:var(--doc-muted)">Tidak ada detail</td></tr>
                                @endforelse
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="3" style="text-align:right;">Total</th>
                                    <th style="text-align:right;">{{ number_format($totQty, 0, ',', '.') }}</th>
                                    <th style="text-align:right;">{{ number_format($totKoli, 2, ',', '.') }}</th>
                                    <th></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    

                    <div class="d-flex justify-content-between mt-8 no-print">
                        <a href="{{ route('admin.riwayat-pengiriman.index') }}" class="btn btn-light">Kembali</a>
                        <button type="button" class="btn btn-success" onclick="window.print()">Cetak</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
