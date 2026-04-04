@extends('layouts.app')

@push('styles')
    <style>
        /* Document container */
        .doc-paper {
            background: #fff;
            border: 1px solid #E5E7EB;
            border-radius: 10px;
            padding: 32px 36px;
            max-width: 900px;
            margin: 0 auto;
            box-shadow: 0 10px 30px rgba(0,0,0,0.06);
            position: relative;
        }

        .doc-header {
            border-bottom: 2px solid #EEF2F7;
            padding-bottom: 18px;
            margin-bottom: 22px;
        }

        .doc-title {
            font-size: 20px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: #111827;
        }

        .meta-table th { color:#6B7280; font-weight:600; padding-right:10px; white-space:nowrap; }
        .meta-table td { color:#111827; font-weight:700; }

        .doc-watermark {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            pointer-events: none;
        }
        .doc-watermark span {
            font-size: 100px;
            font-weight: 800;
            color: rgba(17,24,39,0.05);
            transform: rotate(-20deg);
            text-transform: uppercase;
        }

        .signature-line {
            border-top: 2px dashed #CBD5E1;
            height: 48px;
            margin-top: 42px;
            position: relative;
        }
        .signature-caption { color:#6B7280; font-size: 12px; margin-top: 6px; }

        .notes-box { background:#F9FAFB; border:1px solid #EEF2F7; border-radius:8px; padding:14px 16px; }

        @page { size: A4; margin: 16mm; }

        @media print {
            body * { visibility: hidden; }
            #kt_content, #kt_content * { visibility: visible; }
            #kt_content { position: absolute; left: 0; top: 0; width: 100%; }
            .card, .card-body { border: none !important; box-shadow: none !important; }
            .doc-paper { border: none; box-shadow: none; padding: 0; max-width: 100%; }
            .toolbar-hide, #print_button { display: none !important; }
        }
    </style>
@endpush

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Detail Penyesuaian Stok',
        'breadcrumbs' => ['Admin', 'Manajemen Stok', 'Penyesuaian Stok', 'Detail'],
    ])
@endpush

@section('content')
    <div class="content flex-row-fluid" id="kt_content">
        @php
            $status = $adjustment->status;
            $ribbonBg = $status === 'completed' ? 'success' : ($status === 'pending' ? 'warning' : 'primary');
            $totalQuantity = $adjustment->adjustmentItems->sum('quantity');
            $totalKoli = $adjustment->adjustmentItems->sum('koli');
        @endphp
        <div class="card border-0 bg-transparent">
            <div class="card-body p-lg-10">
                <div class="doc-paper">
                    <div class="doc-watermark d-print-block d-none">
                        <span>{{ $status === 'completed' ? 'Selesai' : 'Draft' }}</span>
                    </div>
                    <div class="doc-header d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-4">
                            <img alt="Logo" src="{{ asset('metronic/assets/media/svg/brand-logos/code-lab.svg') }}" style="height:36px" />
                            <div>
                                <div class="doc-title">Dokumen Penyesuaian Stok</div>
                            </div>
                        </div>
                        <div class="text-end">
                            <span class="badge badge-light-{{ $ribbonBg }} fw-bold">{{ strtoupper($status) }}</span>
                            <div class="fs-8 text-gray-500">Dicetak: {{ now()->format('d M Y H:i') }}</div>
                        </div>
                    </div>

                    <div class="row g-6 mb-6">
                        <div class="col-sm-7">
                            <table class="meta-table">
                                <tr>
                                    <th>Kode Dokumen</th>
                                    <td>: {{ $adjustment->code }}</td>
                                </tr>
                                <tr>
                                    <th>Tanggal</th>
                                    <td>: {{ \Carbon\Carbon::parse($adjustment->adjustment_date)->format('d M Y') }}</td>
                                </tr>
                                <tr>
                                    <th>Gudang</th>
                                    <td>: {{ $adjustment->warehouse->name }}</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-sm-5">
                            <table class="meta-table float-sm-end">
                                <tr>
                                    <th>Dibuat Oleh</th>
                                    <td>: {{ $adjustment->user->name ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Status</th>
                                    <td>: {{ ucfirst($status) }}</td>
                                </tr>
                                <tr>
                                    <th>Total Item</th>
                                    <td>: {{ $adjustment->adjustmentItems->count() }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <div class="table-responsive mb-6">
                        <table class="table align-middle table-row-dashed">
                            <thead>
                                <tr class="border-bottom fs-7 fw-bolder text-gray-700 text-uppercase">
                                    <th class="min-w-120px">SKU</th>
                                    <th class="min-w-300px">Nama Item</th>
                                    <th class="min-w-120px text-end">Kuantitas</th>
                                    <th class="min-w-120px text-end">Koli</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($adjustment->adjustmentItems as $item)
                                    <tr class="border-bottom border-bottom-dashed">
                                        <td class="pe-7">{{ $item->item->sku }}</td>
                                        <td class="pe-7">
                                            <div class="d-flex flex-column">
                                                <div class="text-gray-900 fs-6 fw-bold">{{ $item->item->nama_barang }}</div>
                                                @if(!empty($item->item->description))
                                                    <span class="text-gray-600 fs-7">{{ $item->item->description }}</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="text-end">{{ number_format($item->quantity, 0, ',', '.') }} {{ $item->item->uom->code ?? '' }}</td>
                                        <td class="text-end">{{ number_format($item->koli ?? 0, 2, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="border-top-2 border-top-dashed fs-6 fw-bolder text-gray-700">
                                    <th colspan="2" class="text-end">Total</th>
                                    <th class="text-end">{{ number_format($totalQuantity, 0, ',', '.') }}</th>
                                    <th class="text-end">{{ number_format($totalKoli, 2, ',', '.') }}</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="mb-6">
                        <div class="fw-bolder fs-7 text-gray-600 mb-2">Catatan</div>
                        <div class="notes-box fw-semibold text-gray-800">{{ $adjustment->notes ?? '-' }}</div>
                    </div>

                    <div class="row g-10 mt-10">
                        <div class="col-sm-4 text-center">
                            <div class="fw-bold text-gray-700">Dibuat Oleh</div>
                            <div class="signature-line"></div>
                            <div class="signature-caption">Nama & Tanda Tangan</div>
                        </div>
                        <div class="col-sm-4 text-center">
                            <div class="fw-bold text-gray-700">Diperiksa Oleh</div>
                            <div class="signature-line"></div>
                            <div class="signature-caption">Nama & Tanda Tangan</div>
                        </div>
                        <div class="col-sm-4 text-center">
                            <div class="fw-bold text-gray-700">Disetujui Oleh</div>
                            <div class="signature-line"></div>
                            <div class="signature-caption">Nama & Tanda Tangan</div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-10 d-print-none">
                        <a href="{{ route('admin.manajemenstok.adjustment.index') }}" class="btn btn-secondary">Kembali</a>
                        <button type="button" class="btn btn-success" id="print_button">Print</button>
                    </div>

                    <div class="text-center mt-8">
                        <div class="fs-8 text-gray-500">Dokumen dihasilkan oleh sistem</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $('#print_button').click(function() {
            window.print();
        });
    </script>
@endpush
