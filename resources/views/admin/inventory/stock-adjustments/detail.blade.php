@extends('layouts.admin')

@section('title', 'Detail Penyesuaian Stok')
@section('page_title', 'Detail Penyesuaian Stok')

@push('styles')
<style>
    .adj-shell {
        background: #f5f7fb;
        padding: 24px 0 40px;
    }
    .adj-paper {
        width: min(100%, 920px);
        margin: 0 auto;
        background: #fff;
        color: #111827;
        border: 1px solid #d1d5db;
        box-shadow: 0 14px 40px rgba(15, 23, 42, 0.08);
        padding: 36px 42px;
        font-family: Arial, Helvetica, sans-serif;
    }
    .adj-topbar {
        display: flex;
        justify-content: space-between;
        gap: 24px;
        border-bottom: 3px solid #111827;
        padding-bottom: 16px;
        margin-bottom: 22px;
    }
    .adj-company-name {
        font-size: 22px;
        font-weight: 800;
        text-transform: uppercase;
    }
    .adj-company-meta {
        color: #4b5563;
        font-size: 12px;
        line-height: 1.55;
    }
    .adj-title {
        text-align: right;
        min-width: 260px;
    }
    .adj-title h1 {
        margin: 0 0 8px;
        font-size: 26px;
        font-weight: 800;
    }
    .adj-number {
        display: inline-block;
        border: 1px solid #111827;
        padding: 6px 10px;
        font-weight: 700;
        font-size: 14px;
    }
    .adj-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 18px;
        margin-bottom: 22px;
    }
    .adj-box {
        border: 1px solid #d1d5db;
        min-height: 118px;
    }
    .adj-box-title {
        background: #f3f4f6;
        border-bottom: 1px solid #d1d5db;
        padding: 8px 10px;
        font-size: 12px;
        font-weight: 800;
        text-transform: uppercase;
    }
    .adj-box-body {
        padding: 10px;
        font-size: 13px;
        line-height: 1.65;
    }
    .adj-meta-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 22px;
        font-size: 13px;
    }
    .adj-meta-table td {
        border: 1px solid #d1d5db;
        padding: 8px 10px;
        vertical-align: top;
    }
    .adj-meta-table td:first-child,
    .adj-meta-table td:nth-child(3) {
        width: 150px;
        background: #f9fafb;
        font-weight: 700;
    }
    .adj-items {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }
    .adj-items th,
    .adj-items td {
        border: 1px solid #9ca3af;
        padding: 9px 10px;
        vertical-align: top;
    }
    .adj-items th {
        background: #111827;
        color: #fff;
        text-align: left;
        font-size: 12px;
        text-transform: uppercase;
    }
    .adj-items .adj-num {
        width: 42px;
        text-align: center;
    }
    .adj-items .adj-qty {
        width: 90px;
        text-align: right;
    }
    .adj-items .adj-dir {
        width: 90px;
        text-align: center;
        font-weight: 700;
    }
    .adj-items .adj-dir-in {
        color: #15803d;
    }
    .adj-items .adj-dir-out {
        color: #b91c1c;
    }
    .adj-items tfoot td {
        font-weight: 800;
        background: #f9fafb;
    }
    .adj-note {
        margin-top: 18px;
        border: 1px solid #d1d5db;
        padding: 10px 12px;
        min-height: 54px;
        font-size: 13px;
    }
    .adj-status-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 4px;
        font-weight: 700;
        font-size: 12px;
        text-transform: uppercase;
    }
    .adj-status-pending {
        background: #fef3c7;
        color: #92400e;
        border: 1px solid #fcd34d;
    }
    .adj-status-approved {
        background: #d1fae5;
        color: #065f46;
        border: 1px solid #6ee7b7;
    }
    .adj-signatures {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 22px;
        margin-top: 44px;
        text-align: center;
        font-size: 13px;
    }
    .adj-signature-box {
        min-height: 132px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    .adj-sign-line {
        border-top: 1px solid #111827;
        padding-top: 6px;
        font-weight: 700;
    }
    .adj-actions {
        width: min(100%, 920px);
        margin: 0 auto 16px;
        display: flex;
        justify-content: flex-end;
        gap: 8px;
    }
    @media print {
        @page { size: A4; margin: 12mm; }
        body { background: #fff !important; }
        .header, #kt_header, #kt_footer, .adj-actions, .page-title, .breadcrumb, .card-toolbar { display: none !important; }
        .content, .container-xxl, .adj-shell { padding: 0 !important; margin: 0 !important; max-width: none !important; background: #fff !important; }
        .adj-paper {
            width: 100%;
            margin: 0;
            border: 0;
            box-shadow: none;
            padding: 0;
        }
        .adj-topbar { margin-top: 0; }
    }
</style>
@endpush

@section('content')
@php
    $status = $adjustment->status ?? 'pending';
    $statusClass = $status === 'approved' ? 'adj-status-approved' : 'adj-status-pending';
    $statusLabel = $status === 'approved' ? 'Disetujui' : 'Menunggu Approval';
    $totalIn = (int) $adjustment->items->where('direction', 'in')->sum('qty');
    $totalOut = (int) $adjustment->items->where('direction', 'out')->sum('qty');
    $totalKoli = (int) $adjustment->items->sum(fn ($row) => (int) ($row->koli ?? 0));
@endphp

<div class="adj-shell">
    <div class="adj-actions">
        <a href="{{ $backUrl }}" class="btn btn-light">Kembali</a>
        <button type="button" class="btn btn-success" onclick="window.print()">Cetak</button>
    </div>

    <div class="adj-paper">
        <div class="adj-topbar">
            <div>
                <div class="adj-company-name">{{ config('app.name', 'WMS') }}</div>
                <div class="adj-company-meta">
                    Dokumen operasional gudang<br>
                    Dicetak: {{ now()->format('Y-m-d H:i') }}
                </div>
            </div>
            <div class="adj-title">
                <h1>BERITA ACARA<br>PENYESUAIAN STOK</h1>
                <div class="adj-number">{{ $adjustment->code }}</div>
            </div>
        </div>

        <div class="adj-grid">
            <div class="adj-box">
                <div class="adj-box-title">Informasi Gudang</div>
                <div class="adj-box-body">
                    <strong>{{ $adjustment->warehouse?->name ?? '-' }}</strong><br>
                    Kode: {{ $adjustment->warehouse?->code ?? '-' }}<br>
                    Tanggal Transaksi: {{ $adjustment->transacted_at?->format('Y-m-d H:i') ?? '-' }}
                </div>
            </div>
            <div class="adj-box">
                <div class="adj-box-title">Status Dokumen</div>
                <div class="adj-box-body">
                    <span class="adj-status-badge {{ $statusClass }}">{{ $statusLabel }}</span><br>
                    @if($status === 'approved')
                        Disetujui: {{ $adjustment->approved_at?->format('Y-m-d H:i') ?? '-' }}<br>
                        Oleh: {{ $adjustment->approver?->name ?? '-' }}
                    @else
                        Menunggu persetujuan dari supervisor.
                    @endif
                </div>
            </div>
        </div>

        <table class="adj-meta-table">
            <tr>
                <td>Dibuat Oleh</td>
                <td>{{ $adjustment->creator?->name ?? '-' }}</td>
                <td>Tanggal Dibuat</td>
                <td>{{ $adjustment->created_at?->format('Y-m-d H:i') ?? '-' }}</td>
            </tr>
            <tr>
                <td>Disetujui Oleh</td>
                <td>{{ $adjustment->approver?->name ?? '-' }}</td>
                <td>Tanggal Disetujui</td>
                <td>{{ $adjustment->approved_at?->format('Y-m-d H:i') ?? '-' }}</td>
            </tr>
        </table>

        <table class="adj-items">
            <thead>
                <tr>
                    <th class="adj-num">No</th>
                    <th>SKU</th>
                    <th>Nama Barang</th>
                    <th class="adj-dir">Arah</th>
                    <th class="adj-qty">Kolian</th>
                    <th class="adj-qty">Qty</th>
                    <th>Catatan</th>
                </tr>
            </thead>
            <tbody>
                @foreach($adjustment->items as $row)
                    @php
                        $isIn = $row->direction === 'in';
                        $dirClass = $isIn ? 'adj-dir-in' : 'adj-dir-out';
                        $dirLabel = $isIn ? 'Tambah' : 'Kurangi';
                        $koliVal = $row->koli !== null ? (int) $row->koli : null;
                    @endphp
                    <tr>
                        <td class="adj-num">{{ $loop->iteration }}</td>
                        <td>{{ $row->item?->sku ?? '-' }}</td>
                        <td>{{ $row->item?->name ?? '-' }}</td>
                        <td class="adj-dir {{ $dirClass }}">{{ $dirLabel }}</td>
                        <td class="adj-qty">{{ $koliVal !== null && $koliVal > 0 ? number_format($koliVal, 0, ',', '.') : '-' }}</td>
                        <td class="adj-qty">{{ ($isIn ? '+' : '-').number_format((int) $row->qty, 0, ',', '.') }}</td>
                        <td>{{ $row->note ?: '-' }}</td>
                    </tr>
                @endforeach
                @if($adjustment->items->isEmpty())
                    <tr>
                        <td colspan="7" class="text-center text-muted">Tidak ada item.</td>
                    </tr>
                @endif
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4">TOTAL</td>
                    <td class="adj-qty">{{ $totalKoli > 0 ? number_format($totalKoli, 0, ',', '.') : '-' }}</td>
                    <td class="adj-qty">+{{ number_format($totalIn, 0, ',', '.') }} / -{{ number_format($totalOut, 0, ',', '.') }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>

        <div class="adj-note">
            <strong>Catatan:</strong><br>
            {{ $adjustment->note ?: 'Penyesuaian stok dilakukan untuk merekonsiliasi catatan stok dengan kondisi fisik.' }}
        </div>

        <div class="adj-signatures">
            <div class="adj-signature-box">
                <div>Dibuat Oleh,</div>
                <div class="adj-sign-line">{{ $adjustment->creator?->name ?? 'Admin Gudang' }}</div>
            </div>
            <div class="adj-signature-box">
                <div>Disetujui Oleh,</div>
                <div class="adj-sign-line">{{ $adjustment->approver?->name ?? 'Supervisor' }}</div>
            </div>
        </div>
    </div>
</div>
@endsection
