@extends('layouts.app')

@push('styles')
    <style>
        @media print {

            body * {
                visibility: hidden;
            }

            #kt_content,
            #kt_content * {
                visibility: visible;
            }

            #kt_content {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }

            .card-body {
                padding: 0 !important;
            }

            .d-flex.flex-stack.pb-10,
            #print_button {
                display: none !important;
            }

            /* Expand all collapses on print */
            .accordion-collapse { display: block !important; height: auto !important; }
            .accordion-button::after { display: none; }
            .d-none-print { display: none !important; }
            .print-controls { display: none !important; }
        }

        .section-title { font-size: 1.25rem; font-weight: 700; }
        .meta-label { color: #6B7280; font-weight: 600; font-size: .875rem; }
        .meta-value { font-weight: 700; color: #111827; }
        .info-box { border: 1px solid #EFF2F5; border-radius: .475rem; padding: 1rem; }
        .doc-header { border-bottom: 2px solid #111827; padding-bottom: 12px; margin-bottom: 18px; }
        .doc-title { font-size: 22px; font-weight: 800; text-transform: uppercase; letter-spacing: .5px; }
        .doc-sub { color: #475569; }
        .section-header { background: #F8FAFC; border: 1px solid #E5E7EB; border-radius: .5rem; }
        .section-header .accordion-button { font-weight: 700; color: #0F172A; background: transparent; }
        .section-header .accordion-button:not(.collapsed) { background: #EEF2FF; }
        .badge-status { font-size: .825rem; }
    </style>
@endpush

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Detail Pengadaan',
        'breadcrumbs' => ['Admin', 'Stok Masuk', 'Pengadaan', 'Detail'],
    ])
@endpush

@section('content')
    <div class="content flex-row-fluid" id="kt_content">
        @php
            $status = $stockInOrder->status;
            $ribbonBg = 'primary';
            if ($status === 'completed') {
                $ribbonBg = 'success';
            } elseif ($status === 'rejected') {
                $ribbonBg = 'danger';
            } elseif ($status === 'on_progress') {
                $ribbonBg = 'warning';
            }
            $totalQuantity = $stockInOrder->items->sum('quantity');
            $totalRemainingQuantity = $stockInOrder->items->sum('remaining_quantity');
            $totalKoli = $stockInOrder->items->sum('koli');
            $totalRemainingKoli = $stockInOrder->items->sum('remaining_koli');
        @endphp
        <div class="card ribbon ribbon-end ribbon-clip">
            <div class="ribbon-label d-print-none">
                {{ ucwords(str_replace('_', ' ', $status)) }}
                <span class="ribbon-inner bg-{{ $ribbonBg }}"></span>
            </div>
            <div class="card-body p-lg-20">
                <!--begin::Layout-->
                <div class="d-flex flex-column flex-xl-row">
                    <!--begin::Content-->
                    <div class="flex-lg-row-fluid me-xl-18 mb-10 mb-xl-0">
                        <!--begin::Invoice 2 content-->
                        <div class="mt-n1">
                            <!--begin::Document Header-->
                            <div class="d-flex justify-content-between align-items-start doc-header">
                                <div>
                                    <div class="doc-title">Dokumen Pengadaan</div>
                                    <div class="doc-sub">Kode: {{ $stockInOrder->code }}</div>
                                    <div class="doc-sub">Tanggal: {{ \Carbon\Carbon::parse($stockInOrder->date)->format('d M Y') }}</div>
                                </div>
                                @php
                                    $badgeClassTop = 'primary';
                                    if ($stockInOrder->status === 'completed') { $badgeClassTop = 'success'; }
                                    elseif ($stockInOrder->status === 'rejected') { $badgeClassTop = 'danger'; }
                                    elseif ($stockInOrder->status === 'on_progress') { $badgeClassTop = 'warning'; }
                                @endphp
                                <div class="text-end">
                                    <span class="badge badge-light-{{ $badgeClassTop }} badge-status">Status: {{ ucwords(str_replace('_',' ', $stockInOrder->status)) }}</span>
                                </div>
                            </div>
                            <!--end::Document Header-->
                            <!--begin::Wrapper-->
                            <div class="m-0">
                                <!--begin::Sections-->
                                <div class="accordion" id="sectionsAccordion">
                                    <div class="accordion-item mb-5 section-header print-block" id="summary-block">
                                        <h2 class="accordion-header" id="sec-summary-h">
                                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#sec-summary" aria-expanded="true" aria-controls="sec-summary">
                                                Ringkasan Pengadaan
                                            </button>
                                        </h2>
                                        <div id="sec-summary" class="accordion-collapse collapse show" aria-labelledby="sec-summary-h" data-bs-parent="#sectionsAccordion">
                                            <div class="accordion-body">
                                                <div class="d-flex justify-content-end print-controls mb-3">
                                                    <button type="button" class="btn btn-sm btn-light-primary" onclick="printSection('summary-block')">Cetak Ringkasan</button>
                                                </div>
                                                <!--begin::Summary Info-->
                                <div class="row g-5 mb-8">
                                    <div class="col-md-6">
                                        <div class="info-box">
                                            <div class="section-title mb-2">Informasi Dokumen</div>
                                            <div class="row mb-1"><div class="col-5 meta-label">Tipe</div><div class="col-7 meta-value">{{ ucfirst($stockInOrder->type) }}</div></div>
                                            <div class="row mb-1"><div class="col-5 meta-label">Catatan</div><div class="col-7 meta-value">{{ $stockInOrder->description ?? '-' }}</div></div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="info-box">
                                            <div class="section-title mb-2">Referensi & Waktu</div>
                                            <div class="row mb-1"><div class="col-5 meta-label">Gudang Tujuan</div><div class="col-7 meta-value">{{ $stockInOrder->warehouse->name }}</div></div>
                                            @if(strtolower($stockInOrder->type) !== 'import')
                                                <div class="row mb-1"><div class="col-5 meta-label">Dari Gudang</div><div class="col-7 meta-value">{{ $stockInOrder->fromWarehouse->name ?? '-' }}</div></div>
                                            @endif
                                            <div class="row mb-1"><div class="col-5 meta-label">Diminta Oleh</div><div class="col-7 meta-value">{{ $stockInOrder->requestedBy->name ?? '-' }}</div></div>
                                            <div class="row mb-1"><div class="col-5 meta-label">Diminta Pada</div><div class="col-7 meta-value">{{ $stockInOrder->requested_at ? \Carbon\Carbon::parse($stockInOrder->requested_at)->format('d M Y') : '-' }}</div></div>
                                            <div class="row mb-1"><div class="col-5 meta-label">Terkirim Pada</div><div class="col-7 meta-value">{{ $stockInOrder->shipping_at ? \Carbon\Carbon::parse($stockInOrder->shipping_at)->format('d M Y') : '-' }}</div></div>
                                            <div class="row mb-1"><div class="col-5 meta-label">Selesai Pada</div><div class="col-7 meta-value">{{ $stockInOrder->completed_at ? \Carbon\Carbon::parse($stockInOrder->completed_at)->format('d M Y') : '-' }}</div></div>
                                        </div>
                                    </div>
                                </div>
                                <!--end::Summary Info-->
                                <!--begin::Table-->
                                @php
                                    $totalQuantity = $stockInOrder->items->sum('quantity');
                                    $totalKoli = $stockInOrder->items->sum('koli');
                                @endphp
                                <div class="table-responsive mb-10">
                                    <table class="table g-5 gs-0 mb-0 fw-bolder text-gray-700">
                                        <!--begin::Table head-->
                                        <thead>
                                            <tr class="border-bottom fs-7 fw-bolder text-gray-700 text-uppercase">
                                                <th class="min-w-200px w-200px">SKU</th>
                                                <th class="min-w-250px w-250px">Item</th>
                                                <th class="min-w-100px w-150px text-end">Kuantitas</th>
                                                <th class="min-w-100px w-150px text-end">Koli</th>
                                                <th class="min-w-100px w-150px text-center">Status Item</th>
                                            </tr>
                                        </thead>
                                        <!--end::Table head-->
                                        <!--begin::Table body-->
                                        <tbody>
                                            @foreach ($stockInOrder->items as $item)
                                                <tr class="border-bottom border-bottom-dashed">
                                                    <td class="pe-7">{{ $item->item->sku }}</td>
                                                    <td class="pe-7">
                                                        <div class="d-flex align-items-center">
                                                            <div class="d-flex flex-column">
                                                                <a href="#" class="text-gray-800 text-hover-primary fs-6 fw-bolder">{{ $item->item->nama_barang }}</a>
                                                                <span class="text-gray-600 fw-bold">{{ $item->item->description }}</span>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="text-end">{{ number_format($item->quantity, 0, ',', '.') }} {{ $item->item->uom->code }}</td>
                                                    <td class="text-end">{{ number_format($item->koli, 2, ',', '.') }}</td>
                                                    <td class="text-center">{{ ucwords(str_replace('_', ' ', $item->status)) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                        <!--end::Table body-->
                                        <!--begin::Table foot-->
                                        <tfoot>
                                            <tr class="border-top-2 border-top-dashed fs-6 fw-bolder text-gray-700">
                                                <th colspan="2" class="text-end">Total</th>
                                                <th class="text-end">{{ number_format($totalQuantity, 0, ',', '.') }}</th>
                                                <th class="text-end">{{ number_format($totalKoli, 2, ',', '.') }}</th>
                                                <th></th>
                                            </tr>
                                        </tfoot>
                                        <!--end::Table foot-->
                                    </table>
                                </div>
                                <!--end::Table-->
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!--end::Sections-->
                                @if(strtolower($stockInOrder->type) === 'import')
                                <!--begin::Distributions Section-->
                                <div class="accordion mb-5 section-header print-block" id="distSection">
                                    <div class="accordion-item">
                                        <h2 class="accordion-header" id="sec-dist-h">
                                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#sec-dist" aria-expanded="false" aria-controls="sec-dist">
                                                Distribusi ke Gudang
                                            </button>
                                        </h2>
                                        <div id="sec-dist" class="accordion-collapse collapse" aria-labelledby="sec-dist-h" data-bs-parent="#distSection">
                                            <div class="accordion-body">
                                                <div class="d-flex justify-content-end print-controls mb-3">
                                                    <button type="button" class="btn btn-sm btn-light-primary" onclick="printSection('distSection')">Cetak Distribusi</button>
                                                </div>
                                    @php
                                        $allDists = [];
                                        $sumDistQty = 0; $sumDistKoli = 0;
                                    @endphp
                                    <div class="table-responsive">
                                        <table class="table g-5 gs-0 mb-0 fw-bolder text-gray-700">
                                            <thead>
                                                <tr class="border-bottom fs-7 fw-bolder text-gray-700 text-uppercase">
                                                    <th class="min-w-130px">Tanggal</th>
                                                    <th class="min-w-100px">SKU</th>
                                                    <th class="min-w-250px">Item</th>
                                                    <th class="min-w-180px">Gudang Tujuan</th>
                                                    <th class="text-end min-w-120px">Qty</th>
                                                    <th class="text-end min-w-120px">Koli</th>
                                                    <th class="min-w-200px">Catatan</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @php $hasAnyDist = false; @endphp
                                                @foreach ($stockInOrder->items as $it)
                                                    @foreach ($it->distributions as $dist)
                                                        @php
                                                            $hasAnyDist = true;
                                                            $sumDistQty += (float) ($dist->quantity ?? 0);
                                                            $sumDistKoli += (float) ($dist->koli ?? 0);
                                                        @endphp
                                                        <tr class="border-bottom border-bottom-dashed">
                                                            <td>{{ $dist->date ? \Carbon\Carbon::parse($dist->date)->format('d M Y') : '-' }}</td>
                                                            <td>{{ $it->item->sku ?? '-' }}</td>
                                                            <td>{{ $it->item->nama_barang ?? '-' }}</td>
                                                            <td>{{ $dist->toWarehouse->name ?? '-' }}</td>
                                                            <td class="text-end">{{ number_format($dist->quantity ?? 0, 0, ',', '.') }}</td>
                                                            <td class="text-end">{{ number_format($dist->koli ?? 0, 2, ',', '.') }}</td>
                                                            <td>{{ $dist->note ?? '-' }}</td>
                                                        </tr>
                                                    @endforeach
                                                @endforeach
                                                @if(!$hasAnyDist)
                                                    <tr><td colspan="7" class="text-center text-muted">Belum ada distribusi</td></tr>
                                                @endif
                                            </tbody>
                                            @if($hasAnyDist)
                                            <tfoot>
                                                <tr class="border-top-2 border-top-dashed fs-6 fw-bolder text-gray-700">
                                                    <th colspan="4" class="text-end">Total</th>
                                                    <th class="text-end">{{ number_format($sumDistQty, 0, ',', '.') }}</th>
                                                    <th class="text-end">{{ number_format($sumDistKoli, 2, ',', '.') }}</th>
                                                    <th></th>
                                                </tr>
                                            </tfoot>
                                            @endif
                                        </table>
                                    </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!--end::Distributions Section-->
                                @endif
                                
                            </div>
                            <!--end::Wrapper-->
                        </div>
                        @if(isset($shipments) && $shipments->count())
                            <div class="mt-10 accordion section-header print-block" id="shipmentsSection">
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="sec-ship-h">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#sec-ship" aria-expanded="false" aria-controls="sec-ship">
                                            Pengiriman ({{ $shipments->count() }})
                                        </button>
                                    </h2>
                                    <div id="sec-ship" class="accordion-collapse collapse" aria-labelledby="sec-ship-h" data-bs-parent="#shipmentsSection">
                                        <div class="accordion-body">
                                            <div class="d-flex justify-content-end print-controls mb-3">
                                                <button type="button" class="btn btn-sm btn-light-primary" onclick="printSection('shipmentsSection')">Cetak Pengiriman</button>
                                            </div>
                                <div class="accordion" id="shipmentsAccordion">
                                @foreach ($shipments as $idx => $shipment)
                                    @php
                                        $shipQty = $shipment->itemDetails->sum('quantity_shipped');
                                        $shipKoli = $shipment->itemDetails->sum('koli_shipped');
                                        $collapseId = 'shipment-'.$shipment->id;
                                    @endphp
                                    <div class="accordion-item mb-4">
                                        <h2 class="accordion-header" id="heading-{{ $shipment->id }}">
                                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $collapseId }}" aria-expanded="false" aria-controls="{{ $collapseId }}">
                                                <div class="d-flex w-100 justify-content-between align-items-center">
                                                    <div>
                                                        <div class="fw-bold">{{ $shipment->code }}</div>
                                                        <div class="text-muted small">Tanggal: {{ \Carbon\Carbon::parse($shipment->shipping_date)->format('d M Y') }}</div>
                                                        @php
                                                            $st = $shipment->status ?? '-';
                                                            $badge = 'primary';
                                                            if (in_array(strtolower($st), ['completed', 'selesai'])) { $badge = 'success'; }
                                                            elseif (in_array(strtolower($st), ['on_progress', 'shipped'])) { $badge = 'warning'; }
                                                            elseif (in_array(strtolower($st), ['rejected'])) { $badge = 'danger'; }
                                                        @endphp
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
                                                <div class="d-flex justify-content-end mb-4">
                                                    <a href="{{ route('admin.riwayat-pengiriman.show', ['shipment' => $shipment->id]) }}" class="btn btn-sm btn-light-primary">
                                                        Lihat Detail Pengiriman
                                                    </a>
                                                </div>
                                                <div class="row g-5 mb-6">
                                                    <div class="col-md-6">
                                                        <div class="info-box">
                                                            <div class="section-title mb-2">Informasi Transportasi</div>
                                                            <div class="row mb-1"><div class="col-5 meta-label">Kendaraan</div><div class="col-7 meta-value">{{ $shipment->vehicle_type ?? '-' }}</div></div>
                                                            <div class="row mb-1"><div class="col-5 meta-label">Plat Nomor</div><div class="col-7 meta-value">{{ $shipment->license_plate ?? '-' }}</div></div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="info-box">
                                                            <div class="section-title mb-2">Informasi Pengemudi</div>
                                                            <div class="row mb-1"><div class="col-5 meta-label">Nama</div><div class="col-7 meta-value">{{ $shipment->driver_name ?? '-' }}</div></div>
                                                            <div class="row mb-1"><div class="col-5 meta-label">Kontak</div><div class="col-7 meta-value">{{ $shipment->driver_contact ?? '-' }}</div></div>
                                                        </div>
                                                    </div>
                                                </div>

                                                @if(!empty($shipment->description))
                                                    <div class="mb-6">
                                                        <div class="section-title mb-2">Catatan</div>
                                                        <div class="info-box">{{ $shipment->description }}</div>
                                                    </div>
                                                @endif

                                                <div class="table-responsive">
                                                    <table class="table g-5 gs-0 mb-0 fw-bolder text-gray-700">
                                                        <thead>
                                                            <tr class="border-bottom fs-7 fw-bolder text-gray-700 text-uppercase">
                                                                <th class="min-w-100px w-100px">SKU</th>
                                                                <th class="min-w-300px w-475px">Item</th>
                                                                <th class="min-w-100px w-150px text-end">Qty Dikirim</th>
                                                                <th class="min-w-100px w-150px text-end">Koli Dikirim</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @foreach ($shipment->itemDetails as $detail)
                                                                <tr class="border-bottom border-bottom-dashed">
                                                                    <td class="pe-7">{{ $detail->item->sku ?? '-' }}</td>
                                                                    <td class="pe-7">{{ $detail->item->nama_barang ?? '-' }}</td>
                                                                    <td class="text-end">{{ number_format($detail->quantity_shipped, 0, ',', '.') }}</td>
                                                                    <td class="text-end">{{ number_format($detail->koli_shipped, 2, ',', '.') }}</td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                        <tfoot>
                                                            <tr class="border-top-2 border-top-dashed fs-6 fw-bolder text-gray-700">
                                                                <th colspan="2" class="text-end">Total</th>
                                                                <th class="text-end">{{ number_format($shipQty, 0, ',', '.') }}</th>
                                                                <th class="text-end">{{ number_format($shipKoli, 2, ',', '.') }}</th>
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
                                    </div>
                                </div>
                            </div>
                        @endif
                        @if(isset($receipts) && $receipts->count())
                            <div class="mt-10 accordion section-header print-block" id="receiptsSection">
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="sec-rec-h">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#sec-rec" aria-expanded="false" aria-controls="sec-rec">
                                            Penerimaan Terkait ({{ $receipts->count() }})
                                        </button>
                                    </h2>
                                    <div id="sec-rec" class="accordion-collapse collapse" aria-labelledby="sec-rec-h" data-bs-parent="#receiptsSection">
                                        <div class="accordion-body">
                                            <div class="d-flex justify-content-end print-controls mb-3">
                                                <button type="button" class="btn btn-sm btn-light-primary" onclick="printSection('receiptsSection')">Cetak Penerimaan</button>
                                            </div>
                                <div class="table-responsive">
                                    <table class="table g-5 gs-0 mb-0 fw-bolder text-gray-700">
                                        <thead>
                                            <tr class="border-bottom fs-7 fw-bolder text-gray-700 text-uppercase">
                                                <th>Kode</th>
                                                <th class="min-w-150px">Tanggal</th>
                                                <th>Gudang</th>
                                                <th class="text-end min-w-120px">Total Qty</th>
                                                <th class="text-end min-w-120px">Total Koli</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @php $totGRQty=0; $totGRKoli=0; @endphp
                                            @foreach($receipts as $gr)
                                                @php
                                                    $sumQty = (float) $gr->details->sum('received_quantity');
                                                    $sumKoli = (float) $gr->details->sum('received_koli');
                                                    $totGRQty += $sumQty; $totGRKoli += $sumKoli;
                                                @endphp
                                                <tr class="border-bottom border-bottom-dashed">
                                                    <td>{{ $gr->code }}</td>
                                                    <td>{{ optional($gr->receipt_date)->format('d M Y') }}</td>
                                                    <td>{{ $gr->warehouse->name ?? '-' }}</td>
                                                    <td class="text-end">{{ number_format($sumQty, 0, ',', '.') }}</td>
                                                    <td class="text-end">{{ number_format($sumKoli, 2, ',', '.') }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                        <tfoot>
                                            <tr class="border-top-2 border-top-dashed fs-6 fw-bolder text-gray-700">
                                                <th colspan="3" class="text-end">Total</th>
                                                <th class="text-end">{{ number_format($totGRQty, 0, ',', '.') }}</th>
                                                <th class="text-end">{{ number_format($totGRKoli, 2, ',', '.') }}</th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                        <div class="d-flex flex-stack flex-wrap mt-lg-20 pt-13 d-print-none">
                            <!-- begin::Actions-->
                            <div class="my-1 me-5">
                                <!-- begin::Pint-->
                                <a href="{{ route('admin.stok-masuk.pengadaan.index') }}" class="btn btn-secondary my-1 me-12">Kembali</a>
                                    
                                <!-- end::Pint-->
                            </div>
                            <!-- end::Actions-->
                            <!-- begin::Action-->
                             <button type="button" class="btn btn-success my-1 me-12" id="print_button">Print</button>
                            <!-- end::Action-->
                        </div>
                        <!--end::Invoice 2 content-->
                    </div>
                    <!--end::Content-->
                </div>
                <!--end::Layout-->
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $('#print_button').click(function() {
            window.print();
        });

        function printSection(sectionId) {
            try {
                const blocks = document.querySelectorAll('.print-block');
                blocks.forEach(b => b.classList.add('d-none-print'));
                const target = document.getElementById(sectionId);
                if (target) target.classList.remove('d-none-print');
                window.print();
                setTimeout(() => {
                    blocks.forEach(b => b.classList.remove('d-none-print'));
                }, 300);
            } catch (e) {
                window.print();
            }
        }
    </script>
@endpush
