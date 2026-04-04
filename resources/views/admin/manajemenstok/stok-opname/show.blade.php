@extends('layouts.app')

@push('styles')
<style>
    .doc-paper { background:#fff; border:1px solid #E5E7EB; border-radius:10px; padding:32px 36px; max-width: 1000px; margin:0 auto; box-shadow: 0 10px 30px rgba(0,0,0,0.06); position:relative; }
    .doc-header { border-bottom:2px solid #EEF2F7; padding-bottom:18px; margin-bottom:22px; }
    .doc-title { font-size:20px; font-weight:800; text-transform:uppercase; letter-spacing:.06em; color:#111827; }
    .meta-table th { color:#6B7280; font-weight:600; padding-right:10px; white-space:nowrap; }
    .meta-table td { color:#111827; font-weight:700; }
    .doc-watermark { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; pointer-events:none; }
    .doc-watermark span { font-size:100px; font-weight:800; color:rgba(17,24,39,0.05); transform:rotate(-20deg); text-transform:uppercase; }
    .signature-line { border-top:2px dashed #CBD5E1; height:48px; margin-top:42px; }
    .signature-caption { color:#6B7280; font-size:12px; margin-top:6px; }
    .notes-box { background:#F9FAFB; border:1px solid #EEF2F7; border-radius:8px; padding:14px 16px; }
    .stat-box { background:#F9FAFB; border:1px solid #EEF2F7; border-radius:8px; padding:14px 16px; }
    @page { size:A4; margin:16mm; }
    @media print { body *{visibility:hidden;} #kt_content, #kt_content *{visibility:visible;} #kt_content{position:absolute; left:0; top:0; width:100%;} .card,.card-body{border:none!important; box-shadow:none!important;} .doc-paper{border:none; box-shadow:none; padding:0; max-width:100%;} .no-print{display:none!important;} }
</style>
@endpush

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Detail Stock Opname',
        'breadcrumbs' => ['Admin', 'Manajemen Stok', 'Stock Opname', 'Detail'],
    ])
@endpush

@section('content')
<div class="content flex-row-fluid" id="kt_content">
    @php
        $status = $stok_opname->status ?? (empty($stok_opname->completed_by) ? 'in_progress' : 'completed');
        $ribbonBg = $status === 'completed' ? 'success' : 'warning';
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
                            <div class="doc-title">Dokumen Stock Opname</div>
                            <div class="text-gray-600 fw-semibold">Kode: {{ $stok_opname->code }}</div>
                        </div>
                    </div>
                    <div class="text-end">
                        <span class="badge badge-light-{{ $ribbonBg }} fw-bold">{{ strtoupper(str_replace('_',' ',$status)) }}</span>
                        <div class="fs-8 text-gray-500">Dicetak: {{ now()->format('d M Y H:i') }}</div>
                    </div>
                </div>

                <div class="row g-6 mb-6">
                    <div class="col-md-7">
                        <table class="meta-table">
                            <tr><th>Gudang</th><td>: {{ $stok_opname->warehouse->name ?? '-' }}</td></tr>
                            <tr><th>Tanggal Mulai</th><td>: {{ \Carbon\Carbon::parse($stok_opname->start_date)->format('d M Y') }}</td></tr>
                            <tr><th>Tanggal Selesai</th><td>: {{ $stok_opname->completed_date ? \Carbon\Carbon::parse($stok_opname->completed_date)->format('d M Y') : '-' }}</td></tr>
                        </table>
                    </div>
                    <div class="col-md-5">
                        <table class="meta-table float-sm-end">
                            <tr><th>Disusun Oleh</th><td>: {{ $stok_opname->startedBy->name ?? '-' }}</td></tr>
                            <tr><th>Diselesaikan Oleh</th><td>: {{ $stok_opname->completedBy->name ?? '-' }}</td></tr>
                            <tr><th>Total Item</th><td>: {{ number_format($totals['count_items'], 0, ',', '.') }}</td></tr>
                        </table>
                    </div>
                </div>

                <div class="row g-4 mb-6">
                    <div class="col-sm-3"><div class="stat-box"><div class="text-gray-600">System Qty</div><div class="fw-bold fs-5">{{ number_format($totals['system_qty'], 2, ',', '.') }}</div></div></div>
                    <div class="col-sm-3"><div class="stat-box"><div class="text-gray-600">System Koli</div><div class="fw-bold fs-5">{{ number_format($totals['system_koli'], 2, ',', '.') }}</div></div></div>
                    <div class="col-sm-3"><div class="stat-box"><div class="text-gray-600">Physical Qty</div><div class="fw-bold fs-5">{{ number_format($totals['physical_qty'], 2, ',', '.') }}</div></div></div>
                    <div class="col-sm-3"><div class="stat-box"><div class="text-gray-600">Physical Koli</div><div class="fw-bold fs-5">{{ number_format($totals['physical_koli'], 2, ',', '.') }}</div></div></div>
                </div>

                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle">
                        <thead>
                            <tr class="border-bottom fs-7 fw-bolder text-gray-700 text-uppercase">
                                <th class="w-50px">No</th>
                                <th class="minw-110">SKU</th>
                                <th class="minw-160">Item</th>
                                <th class="minw-110 text-end">Qty Sistem</th>
                                <th class="minw-110 text-end">Koli Sistem</th>
                                <th class="minw-110 text-end">Qty Fisik</th>
                                <th class="minw-110 text-end">Koli Fisik</th>
                                <th class="minw-110 text-end text-warning">Selisih Qty</th>
                                <th class="minw-110 text-end text-warning">Selisih Koli</th>
                                <th class="minw-160">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($stok_opname->items as $i => $row)
                                <tr class="border-bottom border-bottom-dashed">
                                    <td>{{ $i + 1 }}</td>
                                    <td>{{ $row->item->sku ?? '-' }}</td>
                                    <td>
                                        <div class="fw-bold">{{ $row->item->nama_barang ?? '-' }}</div>
                                        <div class="text-muted fs-7">{{ $row->item->description ?? '' }}</div>
                                    </td>
                                    <td class="text-end">{{ number_format($row->system_quantity, 2, ',', '.') }}</td>
                                    <td class="text-end">{{ number_format($row->system_koli, 2, ',', '.') }}</td>
                                    <td class="text-end">{{ number_format($row->physical_quantity, 2, ',', '.') }}</td>
                                    <td class="text-end">{{ number_format($row->physical_koli, 2, ',', '.') }}</td>
                                    @php $dq = $row->discrepancy_quantity; $dk = $row->discrepancy_koli; @endphp
                                    <td class="text-end {{ $dq>0 ? 'text-success' : ($dq<0 ? 'text-danger' : '') }}">{{ ($dq>0 ? '+' : '') . number_format($dq, 2, ',', '.') }}</td>
                                    <td class="text-end {{ $dk>0 ? 'text-success' : ($dk<0 ? 'text-danger' : '') }}">{{ ($dk>0 ? '+' : '') . number_format($dk, 2, ',', '.') }}</td>
                                    <td>{{ $row->description ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="fw-bolder">
                                <th colspan="3" class="text-end">Total</th>
                                <th class="text-end">{{ number_format($totals['system_qty'], 2, ',', '.') }}</th>
                                <th class="text-end">{{ number_format($totals['system_koli'], 2, ',', '.') }}</th>
                                <th class="text-end">{{ number_format($totals['physical_qty'], 2, ',', '.') }}</th>
                                <th class="text-end">{{ number_format($totals['physical_koli'], 2, ',', '.') }}</th>
                                <th class="text-end {{ $totals['disc_qty']>0 ? 'text-success' : ($totals['disc_qty']<0 ? 'text-danger' : '') }}">{{ ($totals['disc_qty']>0 ? '+' : '') . number_format($totals['disc_qty'], 2, ',', '.') }}</th>
                                <th class="text-end {{ $totals['disc_koli']>0 ? 'text-success' : ($totals['disc_koli']<0 ? 'text-danger' : '') }}">{{ ($totals['disc_koli']>0 ? '+' : '') . number_format($totals['disc_koli'], 2, ',', '.') }}</th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                @if(!empty($stok_opname->description))
                    <div class="mt-6">
                        <div class="fw-bolder fs-7 text-gray-600 mb-2">Catatan</div>
                        <div class="notes-box fw-semibold text-gray-800">{{ $stok_opname->description }}</div>
                    </div>
                @endif

                <div class="row g-10 mt-10">
                    <div class="col-md-4 text-center">
                        <div class="fw-bold text-gray-700">Penyusun</div>
                        <div class="signature-line"></div>
                        <div class="signature-caption">Nama & Tanda Tangan</div>
                    </div>
                    <div class="col-md-4 text-center">
                        <div class="fw-bold text-gray-700">Pengecek</div>
                        <div class="signature-line"></div>
                        <div class="signature-caption">Nama & Tanda Tangan</div>
                    </div>
                    <div class="col-md-4 text-center">
                        <div class="fw-bold text-gray-700">Pengesah</div>
                        <div class="signature-line"></div>
                        <div class="signature-caption">Nama & Tanda Tangan</div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-10 d-print-none">
                    <a href="{{ route('admin.manajemenstok.stok-opname.index') }}" class="btn btn-secondary">Kembali</a>
                    <button type="button" class="btn btn-success" onclick="window.print()">Print</button>
                </div>

                <div class="text-center mt-8">
                    <div class="fs-8 text-gray-500">Dokumen dihasilkan oleh sistem</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
