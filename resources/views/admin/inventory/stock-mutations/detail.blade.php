@extends('layouts.admin')

@section('title', 'Detail Mutasi Stok')
@section('page_title', 'Detail Mutasi Stok')

@push('styles')
<style>
    .mut-shell {
        background: #f5f7fb;
        padding: 24px 0 40px;
    }
    .mut-paper {
        width: min(100%, 920px);
        margin: 0 auto;
        background: #fff;
        color: #111827;
        border: 1px solid #d1d5db;
        box-shadow: 0 14px 40px rgba(15, 23, 42, 0.08);
        padding: 36px 42px;
        font-family: Arial, Helvetica, sans-serif;
    }
    .mut-topbar {
        display: flex;
        justify-content: space-between;
        gap: 24px;
        border-bottom: 3px solid #111827;
        padding-bottom: 16px;
        margin-bottom: 22px;
    }
    .mut-company-name {
        font-size: 22px;
        font-weight: 800;
        text-transform: uppercase;
    }
    .mut-company-meta {
        color: #4b5563;
        font-size: 12px;
        line-height: 1.55;
    }
    .mut-title {
        text-align: right;
        min-width: 260px;
    }
    .mut-title h1 {
        margin: 0 0 8px;
        font-size: 26px;
        font-weight: 800;
    }
    .mut-number {
        display: inline-block;
        border: 1px solid #111827;
        padding: 6px 10px;
        font-weight: 700;
        font-size: 14px;
    }
    .mut-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 18px;
        margin-bottom: 22px;
    }
    .mut-box {
        border: 1px solid #d1d5db;
        min-height: 118px;
    }
    .mut-box-title {
        background: #f3f4f6;
        border-bottom: 1px solid #d1d5db;
        padding: 8px 10px;
        font-size: 12px;
        font-weight: 800;
        text-transform: uppercase;
    }
    .mut-box-body {
        padding: 10px;
        font-size: 13px;
        line-height: 1.65;
    }
    .mut-meta-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 22px;
        font-size: 13px;
    }
    .mut-meta-table td {
        border: 1px solid #d1d5db;
        padding: 8px 10px;
        vertical-align: top;
    }
    .mut-meta-table td:first-child,
    .mut-meta-table td:nth-child(3) {
        width: 150px;
        background: #f9fafb;
        font-weight: 700;
    }
    .mut-items {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }
    .mut-items th,
    .mut-items td {
        border: 1px solid #9ca3af;
        padding: 9px 10px;
        vertical-align: top;
    }
    .mut-items th {
        background: #111827;
        color: #fff;
        text-align: left;
        font-size: 12px;
        text-transform: uppercase;
    }
    .mut-num {
        width: 42px;
        text-align: center;
    }
    .mut-qty {
        width: 98px;
        text-align: right;
    }
    .mut-direction {
        display: inline-block;
        min-width: 54px;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 800;
        text-align: center;
    }
    .mut-in {
        color: #065f46;
        background: #d1fae5;
        border: 1px solid #6ee7b7;
    }
    .mut-out {
        color: #991b1b;
        background: #fee2e2;
        border: 1px solid #fecaca;
    }
    .mut-note {
        margin-top: 18px;
        border: 1px solid #d1d5db;
        padding: 10px 12px;
        min-height: 54px;
        font-size: 13px;
    }
    .mut-section-title {
        margin: 24px 0 10px;
        font-size: 14px;
        font-weight: 800;
        text-transform: uppercase;
    }
    .mut-actions {
        width: min(100%, 920px);
        margin: 0 auto 16px;
        display: flex;
        justify-content: flex-end;
        gap: 8px;
    }
    @media print {
        @page { size: A4; margin: 12mm; }
        body { background: #fff !important; }
        .header, #kt_header, #kt_footer, .mut-actions, .page-title, .breadcrumb, .card-toolbar { display: none !important; }
        .content, .container-xxl, .mut-shell { padding: 0 !important; margin: 0 !important; max-width: none !important; background: #fff !important; }
        .mut-paper {
            width: 100%;
            margin: 0;
            border: 0;
            box-shadow: none;
            padding: 0;
        }
        .mut-topbar { margin-top: 0; }
    }
</style>
@endpush

@section('content')
@php
    $isIn = $mutation->direction === 'in';
    $directionClass = $isIn ? 'mut-in' : 'mut-out';
    $directionText = $isIn ? 'MASUK' : 'KELUAR';
    $signedQty = ($isIn ? '+' : '-').number_format((int) $mutation->qty, 0, ',', '.');
    $stockBefore = $mutation->stock_before !== null ? number_format((int) $mutation->stock_before, 0, ',', '.') : '-';
    $stockAfter = $mutation->stock_after !== null ? number_format((int) $mutation->stock_after, 0, ',', '.') : '-';
@endphp

<div class="mut-shell">
    <div class="mut-actions">
        <a href="{{ $backUrl }}" class="btn btn-light">Kembali</a>
        <button type="button" class="btn btn-success" onclick="window.print()">Cetak</button>
    </div>

    <div class="mut-paper">
        <div class="mut-topbar">
            <div>
                <div class="mut-company-name">{{ config('app.name', 'WMS') }}</div>
                <div class="mut-company-meta">
                    Dokumen operasional gudang<br>
                    Dicetak: {{ now()->format('Y-m-d H:i') }}
                </div>
            </div>
            <div class="mut-title">
                <h1>BUKTI<br>MUTASI STOK</h1>
                <div class="mut-number">{{ $mutation->source_code ?: 'MUT-'.$mutation->id }}</div>
            </div>
        </div>

        <div class="mut-grid">
            <div class="mut-box">
                <div class="mut-box-title">Informasi Transaksi</div>
                <div class="mut-box-body">
                    <strong>{{ $sourceLabel ?: '-' }}</strong><br>
                    Tanggal Mutasi: {{ $mutation->occurred_at?->format('Y-m-d H:i') ?? '-' }}<br>
                    Nomor Mutasi: MUT-{{ $mutation->id }}
                </div>
            </div>
            <div class="mut-box">
                <div class="mut-box-title">Informasi Gudang</div>
                <div class="mut-box-body">
                    <strong>{{ $mutation->warehouse?->name ?? '-' }}</strong><br>
                    Kode: {{ $mutation->warehouse?->code ?? '-' }}<br>
                    Dibuat oleh: {{ $mutation->creator?->name ?? '-' }}
                </div>
            </div>
        </div>

        <table class="mut-meta-table">
            <tr>
                <td>Kode Sumber</td>
                <td>{{ $mutation->source_code ?: '-' }}</td>
                <td>Referensi</td>
                <td>{{ $sourceSummary['ref'] ?? '-' }}</td>
            </tr>
            <tr>
                <td>Catatan Mutasi</td>
                <td>{{ $mutation->note ?: '-' }}</td>
                <td>Tanggal Sumber</td>
                <td>{{ $sourceSummary['date'] ?? '-' }}</td>
            </tr>
        </table>

        <table class="mut-items">
            <thead>
                <tr>
                    <th class="mut-num">No</th>
                    <th>SKU</th>
                    <th>Nama Barang</th>
                    <th class="mut-qty">Arah</th>
                    <th class="mut-qty">Qty</th>
                    <th class="mut-qty">Stok Sebelum</th>
                    <th class="mut-qty">Stok Sesudah</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="mut-num">1</td>
                    <td>{{ $mutation->item?->sku ?? '-' }}</td>
                    <td>
                        {{ $mutation->item?->name ?? '-' }}
                        @if(!empty($mutation->reference_sku) && strcasecmp((string) $mutation->reference_sku, (string) ($mutation->item?->sku ?? '')) !== 0)
                            <div class="text-muted fs-8">Referensi bundle: {{ $mutation->reference_sku }}</div>
                        @endif
                    </td>
                    <td class="mut-qty"><span class="mut-direction {{ $directionClass }}">{{ $directionText }}</span></td>
                    <td class="mut-qty">{{ $signedQty }}</td>
                    <td class="mut-qty">{{ $stockBefore }}</td>
                    <td class="mut-qty">{{ $stockAfter }}</td>
                </tr>
            </tbody>
        </table>

        <div class="mut-note">
            <strong>Catatan:</strong><br>
            {{ $mutation->note ?: 'Mutasi stok tercatat otomatis dari dokumen sumber operasional.' }}
        </div>

        <div class="mut-section-title">Rincian Dokumen Sumber</div>
        @if($sourceSummary)
            <table class="mut-meta-table">
                <tr>
                    <td>Jenis Dokumen</td>
                    <td>{{ $sourceSummary['label'] ?? '-' }}</td>
                    <td>Kode Dokumen</td>
                    <td>{{ $sourceSummary['code'] ?? '-' }}</td>
                </tr>
                <tr>
                    <td>Referensi</td>
                    <td>{{ $sourceSummary['ref'] ?? '-' }}</td>
                    <td>Catatan</td>
                    <td>{{ $sourceSummary['note'] ?? '-' }}</td>
                </tr>
            </table>

            <table class="mut-items">
                <thead>
                    <tr>
                        <th class="mut-num">No</th>
                        <th>Item</th>
                        <th class="mut-qty">Qty</th>
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sourceItems as $row)
                        <tr>
                            <td class="mut-num">{{ $loop->iteration }}</td>
                            <td>
                                {{ $row['label'] ?? '-' }}
                                @if(!empty($row['meta']))
                                    <div class="text-muted fs-8">{{ $row['meta'] }}</div>
                                @endif
                            </td>
                            <td class="mut-qty">{{ isset($row['qty']) ? number_format((int) $row['qty'], 0, ',', '.') : '-' }}</td>
                            <td>{{ $row['note'] ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted">Tidak ada item.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        @else
            <div class="mut-note">Data sumber tidak ditemukan.</div>
        @endif
    </div>
</div>
@endsection
