@extends('layouts.app')

@push('styles')
    <style>
        @media print {
            body * { visibility: hidden; }
            #kt_content, #kt_content * { visibility: visible; }
            #kt_content { position: absolute; left: 0; top: 0; width: 100%; }
            .no-print { display: none !important; }
        }
        .sj-header { border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; }
        .sj-title { font-size: 24px; font-weight: 700; text-transform: uppercase; }
        .sj-meta td { padding: 2px 0; }
        .sj-box { border: 1px solid #e5e5e5; border-radius: .475rem; padding: 1rem; }
        .signature-box { height: 80px; border-top: 1px dashed #ccc; margin-top: 40px; text-align: center; padding-top: 10px; }
    </style>
@endpush

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Surat Jalan Pengadaan',
        'breadcrumbs' => ['Admin', 'Stok Masuk', 'Pengadaan', 'Surat Jalan', $shipment->code],
    ])
@endpush

@section('content')
    <div class="content flex-row-fluid" id="kt_content">
        <div class="card">
            <div class="card-body p-lg-10">
                <div class="d-flex justify-content-between align-items-start sj-header">
                    <div>
                        <div class="sj-title">Surat Jalan</div>
                        <div>Kode: {{ $shipment->code }}</div>
                        <div>Tanggal Kirim: {{ \Carbon\Carbon::parse($shipment->shipping_date)->format('d M Y') }}</div>
                    </div>
                    <div class="text-end">
                        <div class="fw-bold">Status: {{ ucfirst($shipment->status ?? 'dalam perjalanan') }}</div>
                    </div>
                </div>

                <div class="row mb-8">
                    <div class="col-12">
                        <div class="sj-box">
                            <div class="fw-bold mb-2">Informasi Pengadaan</div>
                            <table class="sj-meta w-100">
                                <tr>
                                    <td class="w-200px">Kode Pengadaan</td>
                                    <td class="px-2">:</td>
                                    <td>{{ $stockInOrder->code ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td>Tanggal Pengadaan</td>
                                    <td class="px-2">:</td>
                                    <td>{{ isset($stockInOrder->date) ? \Carbon\Carbon::parse($stockInOrder->date)->format('d M Y') : '-' }}</td>
                                </tr>
                                <tr>
                                    <td>Gudang Tujuan</td>
                                    <td class="px-2">:</td>
                                    <td>{{ $warehouseName ?? '-' }}</td>
                                </tr>
                                @if(!empty($stockInOrder?->description))
                                <tr>
                                    <td>Catatan</td>
                                    <td class="px-2">:</td>
                                    <td>{{ $stockInOrder->description }}</td>
                                </tr>
                                @endif
                            </table>
                        </div>
                    </div>
                </div>

                <div class="row mb-8">
                    <div class="col-md-6">
                        <div class="sj-box">
                            <div class="fw-bold mb-2">Pengirim</div>
                            <div>Gudang: -</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="sj-box">
                            <div class="fw-bold mb-2">Penerima</div>
                            <div>Gudang: {{ $warehouseName ?? '-' }}</div>
                        </div>
                    </div>
                </div>

                <div class="row mb-8">
                    <div class="col-md-6">
                        <div class="sj-box">
                            <div class="fw-bold mb-2">Informasi Kendaraan</div>
                            <table class="sj-meta w-100">
                                <tr><td>Jenis Kendaraan</td><td class="px-2">:</td><td>{{ $shipment->vehicle_type ?? '-' }}</td></tr>
                                <tr><td>Plat Nomor</td><td class="px-2">:</td><td>{{ $shipment->license_plate ?? '-' }}</td></tr>
                            </table>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="sj-box">
                            <div class="fw-bold mb-2">Informasi Pengemudi</div>
                            <table class="sj-meta w-100">
                                <tr><td>Nama</td><td class="px-2">:</td><td>{{ $shipment->driver_name ?? '-' }}</td></tr>
                                <tr><td>Kontak</td><td class="px-2">:</td><td>{{ $shipment->driver_contact ?? '-' }}</td></tr>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="mb-8">
                    <div class="fw-bold mb-2">Daftar Barang</div>
                    <div class="table-responsive">
                        <table class="table table-row-dashed align-middle">
                            <thead>
                                <tr class="text-start text-gray-700 fw-bolder fs-7 text-uppercase gs-0">
                                    <th class="w-40px">No</th>
                                    <th>Nama Barang</th>
                                    <th class="w-150px">SKU</th>
                                    <th class="w-120px text-end">Qty</th>
                                    <th class="w-120px text-end">Koli</th>
                                    <th>Keterangan</th>
                                </tr>
                            </thead>
                            <tbody class="fw-bold text-gray-800">
                                @php $totalQty = 0; $totalKoli = 0; @endphp
                                @foreach ($shipment->itemDetails as $i => $d)
                                    @php $totalQty += (float) $d->quantity_shipped; $totalKoli += (float) $d->koli_shipped; @endphp
                                    <tr>
                                        <td>{{ $i + 1 }}</td>
                                        <td>{{ $d->item->nama_barang ?? $d->item->name ?? '-' }}</td>
                                        <td>{{ $d->item->sku ?? '-' }}</td>
                                        <td class="text-end">{{ number_format($d->quantity_shipped, 2, ',', '.') }}</td>
                                        <td class="text-end">{{ number_format($d->koli_shipped, 2, ',', '.') }}</td>
                                        <td>{{ $d->description ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="fw-bolder">
                                    <td colspan="3" class="text-end">Total</td>
                                    <td class="text-end">{{ number_format($totalQty, 2, ',', '.') }}</td>
                                    <td class="text-end">{{ number_format($totalKoli, 2, ',', '.') }}</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                @if(!empty($shipment->description))
                    <div class="mb-8">
                        <div class="fw-bold mb-2">Catatan</div>
                        <div class="sj-box">{{ $shipment->description }}</div>
                    </div>
                @endif

                <div class="row mt-12">
                    <div class="col-md-4 text-center">
                        <div class="fw-bold mb-10">Pengirim</div>
                        <div class="signature-box">( Tanda Tangan & Nama Jelas )</div>
                    </div>
                    <div class="col-md-4 text-center">
                        <div class="fw-bold mb-10">Pengemudi</div>
                        <div class="signature-box">( Tanda Tangan & Nama Jelas )</div>
                    </div>
                    <div class="col-md-4 text-center">
                        <div class="fw-bold mb-10">Penerima</div>
                        <div class="signature-box">( Tanda Tangan & Nama Jelas )</div>
                    </div>
                </div>

                <div class="mt-10 d-flex gap-3 no-print">
                    <a href="{{ route('admin.stok-masuk.pengadaan.index') }}" class="btn btn-secondary">Kembali</a>
                    <button onclick="window.print()" class="btn btn-primary">Cetak</button>
                </div>
            </div>
        </div>
    </div>
@endsection
