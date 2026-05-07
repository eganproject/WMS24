@extends('layouts.admin')

@section('title', 'Detail Stock Opname')
@section('page_title', 'Detail Stock Opname')

@push('styles')
<style>
    .opn-shell {
        background: #f5f7fb;
        padding: 24px 0 40px;
    }
    .opn-paper {
        width: min(100%, 920px);
        margin: 0 auto;
        background: #fff;
        color: #111827;
        border: 1px solid #d1d5db;
        box-shadow: 0 14px 40px rgba(15, 23, 42, 0.08);
        padding: 36px 42px;
        font-family: Arial, Helvetica, sans-serif;
    }
    .opn-topbar {
        display: flex;
        justify-content: space-between;
        gap: 24px;
        border-bottom: 3px solid #111827;
        padding-bottom: 16px;
        margin-bottom: 22px;
    }
    .opn-company-name {
        font-size: 22px;
        font-weight: 800;
        text-transform: uppercase;
    }
    .opn-company-meta {
        color: #4b5563;
        font-size: 12px;
        line-height: 1.55;
    }
    .opn-title {
        text-align: right;
        min-width: 260px;
    }
    .opn-title h1 {
        margin: 0 0 8px;
        font-size: 26px;
        font-weight: 800;
    }
    .opn-number {
        display: inline-block;
        border: 1px solid #111827;
        padding: 6px 10px;
        font-weight: 700;
        font-size: 14px;
    }
    .opn-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 18px;
        margin-bottom: 22px;
    }
    .opn-box {
        border: 1px solid #d1d5db;
        min-height: 118px;
    }
    .opn-box-title {
        background: #f3f4f6;
        border-bottom: 1px solid #d1d5db;
        padding: 8px 10px;
        font-size: 12px;
        font-weight: 800;
        text-transform: uppercase;
    }
    .opn-box-body {
        padding: 10px;
        font-size: 13px;
        line-height: 1.65;
    }
    .opn-meta-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 22px;
        font-size: 13px;
    }
    .opn-meta-table td {
        border: 1px solid #d1d5db;
        padding: 8px 10px;
        vertical-align: top;
    }
    .opn-meta-table td:first-child,
    .opn-meta-table td:nth-child(3) {
        width: 150px;
        background: #f9fafb;
        font-weight: 700;
    }
    .opn-items {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }
    .opn-items th,
    .opn-items td {
        border: 1px solid #9ca3af;
        padding: 9px 10px;
        vertical-align: top;
    }
    .opn-items th {
        background: #111827;
        color: #fff;
        text-align: left;
        font-size: 12px;
        text-transform: uppercase;
    }
    .opn-items .opn-num {
        width: 42px;
        text-align: center;
    }
    .opn-items .opn-qty {
        width: 86px;
        text-align: right;
    }
    .opn-items .opn-adj-pos {
        color: #15803d;
        font-weight: 700;
    }
    .opn-items .opn-adj-neg {
        color: #b91c1c;
        font-weight: 700;
    }
    .opn-items tfoot td {
        font-weight: 800;
        background: #f9fafb;
    }
    .opn-note {
        margin-top: 18px;
        border: 1px solid #d1d5db;
        padding: 10px 12px;
        min-height: 54px;
        font-size: 13px;
    }
    .opn-status-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 4px;
        font-weight: 700;
        font-size: 12px;
        text-transform: uppercase;
    }
    .opn-status-open {
        background: #fef3c7;
        color: #92400e;
        border: 1px solid #fcd34d;
    }
    .opn-status-completed {
        background: #d1fae5;
        color: #065f46;
        border: 1px solid #6ee7b7;
    }
    .opn-signatures {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 22px;
        margin-top: 44px;
        text-align: center;
        font-size: 13px;
    }
    .opn-signature-box {
        min-height: 132px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    .opn-sign-line {
        border-top: 1px solid #111827;
        padding-top: 6px;
        font-weight: 700;
    }
    .opn-actions {
        width: min(100%, 920px);
        margin: 0 auto 16px;
        display: flex;
        justify-content: flex-end;
        gap: 8px;
    }
    @media print {
        @page { size: A4; margin: 12mm; }
        body { background: #fff !important; }
        .header, #kt_header, #kt_footer, .opn-actions, .page-title, .breadcrumb, .card-toolbar { display: none !important; }
        .content, .container-xxl, .opn-shell { padding: 0 !important; margin: 0 !important; max-width: none !important; background: #fff !important; }
        .opn-paper {
            width: 100%;
            margin: 0;
            border: 0;
            box-shadow: none;
            padding: 0;
        }
        .opn-topbar { margin-top: 0; }
    }
</style>
@endpush

@section('content')
@php
    use App\Support\WarehouseService;
    $status = $opname->status ?? 'open';
    $statusClass = $status === 'completed' ? 'opn-status-completed' : 'opn-status-open';
    $statusLabel = $status === 'completed' ? 'Selesai' : 'Berjalan';
    $isDefaultWarehouse = (int) ($opname->warehouse_id ?? 0) === WarehouseService::defaultWarehouseId();
    $totalSystem = (int) $opname->items->sum('system_qty');
    $totalCounted = (int) $opname->items->sum('counted_qty');
    $totalAdjustment = (int) $opname->items->sum('adjustment');
    $totalKoli = (int) $opname->items->sum(fn ($row) => (int) ($row->koli ?? 0));
    $exportUrl = route('admin.inventory.stock-opname.export', $opname->id);
@endphp

<div class="opn-shell">
    <div class="opn-actions">
        <a href="{{ $backUrl }}" class="btn btn-light">Kembali</a>
        <a href="{{ $exportUrl }}" class="btn btn-light-primary">Export Excel</a>
        <button type="button" class="btn btn-success" onclick="window.print()">Cetak</button>
    </div>

    <div class="opn-paper">
        <div class="opn-topbar">
            <div>
                <div class="opn-company-name">{{ config('app.name', 'WMS') }}</div>
                <div class="opn-company-meta">
                    Dokumen operasional gudang<br>
                    Dicetak: {{ now()->format('Y-m-d H:i') }}
                </div>
            </div>
            <div class="opn-title">
                <h1>BERITA ACARA<br>STOCK OPNAME</h1>
                <div class="opn-number">{{ $opname->code }}</div>
            </div>
        </div>

        <div class="opn-grid">
            <div class="opn-box">
                <div class="opn-box-title">Informasi Gudang</div>
                <div class="opn-box-body">
                    <strong>{{ $opname->warehouse?->name ?? '-' }}</strong><br>
                    Kode: {{ $opname->warehouse?->code ?? '-' }}<br>
                    Tanggal Opname: {{ $opname->transacted_at?->format('Y-m-d H:i') ?? '-' }}
                </div>
            </div>
            <div class="opn-box">
                <div class="opn-box-title">Status Dokumen</div>
                <div class="opn-box-body">
                    <span class="opn-status-badge {{ $statusClass }}">{{ $statusLabel }}</span><br>
                    @if($status === 'completed')
                        Diselesaikan: {{ $opname->completed_at?->format('Y-m-d H:i') ?? '-' }}<br>
                        Oleh: {{ $opname->completer?->name ?? '-' }}
                    @else
                        Menunggu approval untuk diselesaikan.
                    @endif
                </div>
            </div>
        </div>

        <table class="opn-meta-table">
            <tr>
                <td>Dibuat Oleh</td>
                <td>{{ $opname->creator?->name ?? '-' }}</td>
                <td>Tanggal Dibuat</td>
                <td>{{ $opname->created_at?->format('Y-m-d H:i') ?? '-' }}</td>
            </tr>
            <tr>
                <td>Diselesaikan Oleh</td>
                <td>{{ $opname->completer?->name ?? '-' }}</td>
                <td>Tanggal Selesai</td>
                <td>{{ $opname->completed_at?->format('Y-m-d H:i') ?? '-' }}</td>
            </tr>
        </table>

        <table class="opn-items">
            <thead>
                <tr>
                    <th class="opn-num">No</th>
                    <th>SKU</th>
                    <th>Nama Barang</th>
                    <th class="opn-qty">System</th>
                    @if($isDefaultWarehouse)
                        <th class="opn-qty">Kolian</th>
                    @endif
                    <th class="opn-qty">Counted</th>
                    <th class="opn-qty">Selisih</th>
                    <th>Catatan</th>
                </tr>
            </thead>
            <tbody>
                @foreach($opname->items as $row)
                    @php
                        $adj = (int) $row->adjustment;
                        $adjClass = $adj > 0 ? 'opn-adj-pos' : ($adj < 0 ? 'opn-adj-neg' : '');
                        $adjStr = $adj > 0 ? '+'.$adj : (string) $adj;
                        $koliVal = $row->koli !== null ? (int) $row->koli : null;
                    @endphp
                    <tr>
                        <td class="opn-num">{{ $loop->iteration }}</td>
                        <td>{{ $row->item?->sku ?? '-' }}</td>
                        <td>{{ $row->item?->name ?? '-' }}</td>
                        <td class="opn-qty">{{ number_format((int) $row->system_qty, 0, ',', '.') }}</td>
                        @if($isDefaultWarehouse)
                            <td class="opn-qty">{{ $koliVal !== null && $koliVal > 0 ? number_format($koliVal, 0, ',', '.') : '-' }}</td>
                        @endif
                        <td class="opn-qty">{{ number_format((int) $row->counted_qty, 0, ',', '.') }}</td>
                        <td class="opn-qty {{ $adjClass }}">{{ $adjStr }}</td>
                        <td>{{ $row->note ?: '-' }}</td>
                    </tr>
                @endforeach
                @if($opname->items->isEmpty())
                    <tr>
                        <td colspan="{{ $isDefaultWarehouse ? 8 : 7 }}" class="text-center text-muted">Tidak ada item.</td>
                    </tr>
                @endif
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3">TOTAL</td>
                    <td class="opn-qty">{{ number_format($totalSystem, 0, ',', '.') }}</td>
                    @if($isDefaultWarehouse)
                        <td class="opn-qty">{{ $totalKoli > 0 ? number_format($totalKoli, 0, ',', '.') : '-' }}</td>
                    @endif
                    <td class="opn-qty">{{ number_format($totalCounted, 0, ',', '.') }}</td>
                    <td class="opn-qty {{ $totalAdjustment > 0 ? 'opn-adj-pos' : ($totalAdjustment < 0 ? 'opn-adj-neg' : '') }}">{{ $totalAdjustment > 0 ? '+'.$totalAdjustment : (string) $totalAdjustment }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>

        <div class="opn-note">
            <strong>Catatan:</strong><br>
            {{ $opname->note ?: 'Stock opname dilakukan untuk merekonsiliasi catatan stok dengan kondisi fisik di gudang.' }}
        </div>

        <div class="opn-signatures">
            <div class="opn-signature-box">
                <div>Dibuat Oleh,</div>
                <div class="opn-sign-line">{{ $opname->creator?->name ?? 'Admin Gudang' }}</div>
            </div>
            <div class="opn-signature-box">
                <div>Diselesaikan Oleh,</div>
                <div class="opn-sign-line">{{ $opname->completer?->name ?? 'Supervisor' }}</div>
            </div>
        </div>
    </div>
</div>
@endsection
