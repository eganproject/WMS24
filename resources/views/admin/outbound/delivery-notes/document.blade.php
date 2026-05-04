@extends('layouts.admin')

@section('title', 'Surat Jalan '.$transaction->surat_jalan_no)
@section('page_title', 'Surat Jalan')

@push('styles')
<style>
    .delivery-note-shell {
        background: #f5f7fb;
        padding: 24px 0 40px;
    }
    .delivery-note-paper {
        width: min(100%, 920px);
        margin: 0 auto;
        background: #fff;
        color: #111827;
        border: 1px solid #d1d5db;
        box-shadow: 0 14px 40px rgba(15, 23, 42, 0.08);
        padding: 36px 42px;
        font-family: Arial, Helvetica, sans-serif;
    }
    .dn-topbar {
        display: flex;
        justify-content: space-between;
        gap: 24px;
        border-bottom: 3px solid #111827;
        padding-bottom: 16px;
        margin-bottom: 22px;
    }
    .dn-company-name {
        font-size: 22px;
        font-weight: 800;
        letter-spacing: 0;
        text-transform: uppercase;
    }
    .dn-company-meta,
    .dn-muted {
        color: #4b5563;
        font-size: 12px;
        line-height: 1.55;
    }
    .dn-title {
        text-align: right;
        min-width: 260px;
    }
    .dn-title h1 {
        margin: 0 0 8px;
        font-size: 28px;
        font-weight: 800;
        letter-spacing: 0;
    }
    .dn-number {
        display: inline-block;
        border: 1px solid #111827;
        padding: 6px 10px;
        font-weight: 700;
        font-size: 14px;
    }
    .dn-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 18px;
        margin-bottom: 22px;
    }
    .dn-box {
        border: 1px solid #d1d5db;
        min-height: 118px;
    }
    .dn-box-title {
        background: #f3f4f6;
        border-bottom: 1px solid #d1d5db;
        padding: 8px 10px;
        font-size: 12px;
        font-weight: 800;
        text-transform: uppercase;
    }
    .dn-box-body {
        padding: 10px;
        font-size: 13px;
        line-height: 1.65;
    }
    .dn-meta-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 22px;
        font-size: 13px;
    }
    .dn-meta-table td {
        border: 1px solid #d1d5db;
        padding: 8px 10px;
        vertical-align: top;
    }
    .dn-meta-table td:first-child,
    .dn-meta-table td:nth-child(3) {
        width: 150px;
        background: #f9fafb;
        font-weight: 700;
    }
    .dn-items {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }
    .dn-items th,
    .dn-items td {
        border: 1px solid #9ca3af;
        padding: 9px 10px;
        vertical-align: top;
    }
    .dn-items th {
        background: #111827;
        color: #fff;
        text-align: left;
        font-size: 12px;
        text-transform: uppercase;
    }
    .dn-items .dn-num {
        width: 42px;
        text-align: center;
    }
    .dn-items .dn-qty {
        width: 90px;
        text-align: right;
    }
    .dn-items tfoot td {
        font-weight: 800;
        background: #f9fafb;
    }
    .dn-note {
        margin-top: 18px;
        border: 1px solid #d1d5db;
        padding: 10px 12px;
        min-height: 54px;
        font-size: 13px;
    }
    .dn-signatures {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 22px;
        margin-top: 44px;
        text-align: center;
        font-size: 13px;
    }
    .dn-signature-box {
        min-height: 132px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    .dn-sign-line {
        border-top: 1px solid #111827;
        padding-top: 6px;
        font-weight: 700;
    }
    .dn-actions {
        width: min(100%, 920px);
        margin: 0 auto 16px;
        display: flex;
        justify-content: flex-end;
        gap: 8px;
    }
    @media print {
        @page { size: A4; margin: 12mm; }
        body { background: #fff !important; }
        .header, #kt_header, #kt_footer, .dn-actions, .page-title, .breadcrumb, .card-toolbar { display: none !important; }
        .content, .container-xxl, .delivery-note-shell { padding: 0 !important; margin: 0 !important; max-width: none !important; background: #fff !important; }
        .delivery-note-paper {
            width: 100%;
            margin: 0;
            border: 0;
            box-shadow: none;
            padding: 0;
        }
        .dn-topbar { margin-top: 0; }
    }
</style>
@endpush

@section('content')
@php
    $typeLabel = $transaction->type === 'return' ? 'Retur Outbound' : 'Outbound Manual';
    $recipientTitle = $transaction->type === 'return' ? 'Kepada Supplier' : 'Tujuan Pengiriman';
    $recipientName = $transaction->type === 'return'
        ? ($transaction->supplier?->name ?? '-')
        : ($transaction->ref_no ?: 'Tujuan Manual');
    $warehouseName = $transaction->warehouse?->name ?? '-';
    $docDate = $transaction->surat_jalan_at ?: $transaction->transacted_at;
@endphp

<div class="delivery-note-shell">
    <div class="dn-actions">
        <a href="{{ $backUrl }}" class="btn btn-light">Kembali</a>
        <a href="{{ $printUrl }}" target="_blank" rel="noopener" class="btn btn-primary">Buka Mode Cetak</a>
        <button type="button" class="btn btn-success" onclick="window.print()">Cetak</button>
    </div>

    <div class="delivery-note-paper">
        <div class="dn-topbar">
            <div>
                <div class="dn-company-name">{{ config('app.name', 'WMS') }}</div>
                <div class="dn-company-meta">
                    Dokumen operasional gudang<br>
                    Dicetak: {{ now()->format('Y-m-d H:i') }}
                </div>
            </div>
            <div class="dn-title">
                <h1>SURAT JALAN</h1>
                <div class="dn-number">{{ $transaction->surat_jalan_no }}</div>
            </div>
        </div>

        <div class="dn-grid">
            <div class="dn-box">
                <div class="dn-box-title">{{ $recipientTitle }}</div>
                <div class="dn-box-body">
                    <strong>{{ $recipientName }}</strong><br>
                    Ref: {{ $transaction->ref_no ?: '-' }}<br>
                    Jenis: {{ $typeLabel }}
                </div>
            </div>
            <div class="dn-box">
                <div class="dn-box-title">Informasi Gudang</div>
                <div class="dn-box-body">
                    Gudang: <strong>{{ $warehouseName }}</strong><br>
                    Kode Outbound: {{ $transaction->code }}<br>
                    @if($transaction->damagedAllocation)
                        Alokasi Barang Rusak: {{ $transaction->damagedAllocation->code }}<br>
                    @endif
                    Status: {{ strtoupper(str_replace('_', ' ', $transaction->status ?? 'pending')) }}
                </div>
            </div>
        </div>

        <table class="dn-meta-table">
            <tr>
                <td>Tanggal SJ</td>
                <td>{{ $docDate?->format('Y-m-d') ?: '-' }}</td>
                <td>Tanggal Transaksi</td>
                <td>{{ $transaction->transacted_at?->format('Y-m-d H:i') ?: '-' }}</td>
            </tr>
            <tr>
                <td>Dibuat Oleh</td>
                <td>{{ $transaction->creator?->name ?? '-' }}</td>
                <td>Disetujui Oleh</td>
                <td>{{ $transaction->approver?->name ?? '-' }}</td>
            </tr>
        </table>

        <table class="dn-items">
            <thead>
                <tr>
                    <th class="dn-num">No</th>
                    <th>SKU</th>
                    <th>Nama Barang</th>
                    <th class="dn-qty">Koli</th>
                    <th class="dn-qty">Qty</th>
                    <th>Catatan</th>
                </tr>
            </thead>
            <tbody>
                @foreach($transaction->items as $row)
                    @php
                        $qtyPerKoli = (int) ($row->item?->koli_qty ?? 0);
                        $koli = $qtyPerKoli > 0 ? intdiv((int) $row->qty, $qtyPerKoli) : 0;
                    @endphp
                    <tr>
                        <td class="dn-num">{{ $loop->iteration }}</td>
                        <td>{{ $row->item?->sku ?? '-' }}</td>
                        <td>{{ $row->item?->name ?? '-' }}</td>
                        <td class="dn-qty">{{ $koli > 0 ? $koli : '-' }}</td>
                        <td class="dn-qty">{{ number_format((int) $row->qty, 0, ',', '.') }}</td>
                        <td>{{ $row->note ?: '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3">TOTAL</td>
                    <td class="dn-qty">{{ ($totalKoli ?? 0) > 0 ? number_format($totalKoli, 0, ',', '.') : '-' }}</td>
                    <td class="dn-qty">{{ number_format($totalQty, 0, ',', '.') }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>

        <div class="dn-note">
            <strong>Catatan:</strong><br>
            {{ $transaction->note ?: 'Barang telah diterima/diserahkan sesuai daftar item di atas.' }}
        </div>

        <div class="dn-signatures">
            <div class="dn-signature-box">
                <div>Dibuat Oleh,</div>
                <div class="dn-sign-line">{{ $transaction->creator?->name ?? 'Admin Gudang' }}</div>
            </div>
            <div class="dn-signature-box">
                <div>Pengirim,</div>
                <div class="dn-sign-line">Gudang</div>
            </div>
            <div class="dn-signature-box">
                <div>Penerima,</div>
                <div class="dn-sign-line">{{ $transaction->type === 'return' ? 'Supplier' : 'Penerima' }}</div>
            </div>
        </div>
    </div>
</div>

@if(!empty($printMode))
    @push('scripts')
    <script>
        window.addEventListener('load', () => window.setTimeout(() => window.print(), 250));
    </script>
    @endpush
@endif
@endsection
