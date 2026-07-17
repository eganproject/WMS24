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
    .opn-summary { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 18px; }
    .opn-summary-card { border: 1px solid #d1d5db; border-radius: 6px; padding: 12px 14px; background: #fff; }
    .opn-summary-card.is-success { border-color: #86efac; background: #f0fdf4; }
    .opn-summary-card.is-danger { border-color: #fca5a5; background: #fef2f2; }
    .opn-summary-card.is-primary { border-color: #93c5fd; background: #eff6ff; }
    .opn-summary-label { color: #6b7280; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .35px; }
    .opn-summary-value { margin-top: 3px; font-size: 24px; line-height: 1.15; font-weight: 800; }
    .opn-summary-help { margin-top: 3px; color: #6b7280; font-size: 10px; }
    .opn-table-wrap { overflow-x: auto; }
    .opn-section-heading { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 8px; padding-bottom: 7px; border-bottom: 2px solid #d1d5db; }
    .opn-section-heading h2 { margin: 0; font-size: 16px; font-weight: 800; }
    .opn-section-heading.is-danger { border-bottom-color: #dc2626; color: #991b1b; }
    .opn-section-heading.is-success { border-bottom-color: #16a34a; color: #166534; }
    .opn-section-count { border-radius: 999px; padding: 3px 9px; color: #fff; font-size: 11px; font-weight: 800; }
    .opn-section-heading.is-danger .opn-section-count { background: #991b1b; }
    .opn-section-heading.is-success .opn-section-count { background: #166534; }
    .opn-row-diff { background: #fff7f7; }
    .opn-row-match { background: #f7fdf9; }
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
    .opn-mode-badge {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 4px;
        font-weight: 700;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }
    .opn-mode-koli {
        background: #dbeafe;
        color: #1e40af;
        border: 1px solid #93c5fd;
    }
    .opn-mode-pcs {
        background: #f3f4f6;
        color: #374151;
        border: 1px solid #d1d5db;
    }
    .opn-mode-info {
        margin-bottom: 16px;
        padding: 10px 12px;
        border-left: 4px solid #1e40af;
        background: #eff6ff;
        font-size: 12px;
        line-height: 1.55;
        color: #1e3a8a;
    }
    .opn-mode-info.is-pcs {
        border-left-color: #6b7280;
        background: #f9fafb;
        color: #374151;
    }
    .opn-cell-sub {
        display: block;
        font-size: 11px;
        color: #6b7280;
        font-weight: 400;
        margin-top: 2px;
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
        .opn-summary-card, .opn-items tr { break-inside: avoid; }
    }
    @media (max-width: 767px) {
        .opn-paper { padding: 22px 16px; }
        .opn-summary { grid-template-columns: repeat(2, 1fr); }
        .opn-topbar { flex-direction: column; }
        .opn-title { min-width: 0; text-align: left; }
        .opn-grid { grid-template-columns: 1fr; }
        .opn-items { min-width: 760px; }
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
    $modeLabel = $isDefaultWarehouse ? 'Mode Kolian' : 'Mode Qty (Pcs)';
    $modeClass = $isDefaultWarehouse ? 'opn-mode-koli' : 'opn-mode-pcs';
    $totalSystem = (int) $opname->items->sum('system_qty');
    $totalCounted = (int) $opname->items->sum('counted_qty');
    $totalAdjustment = (int) $opname->items->sum('adjustment');
    $totalKoli = (int) $opname->items->sum(fn ($row) => (int) ($row->koli ?? 0));
    $totalSku = $opname->items->count();
    $differentItems = $opname->items->filter(fn ($row) => (int) $row->adjustment !== 0)->values();
    $matchingItems = $opname->items->filter(fn ($row) => (int) $row->adjustment === 0)->values();
    $matchingSku = $matchingItems->count();
    $differentSku = $differentItems->count();
    $accuracy = $totalSku > 0 ? ($matchingSku / $totalSku) * 100 : 0;
    $accuracyLabel = number_format($accuracy, 2, ',', '.').'%';
    $exportUrl = route('admin.inventory.stock-opname.export', $opname->id);

    $formatKoliBreakdown = function ($qty, $qtyPerKoli) {
        $qty = (int) $qty;
        $qtyPerKoli = (int) $qtyPerKoli;
        if ($qty === 0 || $qtyPerKoli <= 0) {
            return '';
        }
        $absQty = abs($qty);
        $koli = intdiv($absQty, $qtyPerKoli);
        $sisa = $absQty % $qtyPerKoli;
        $sign = $qty < 0 ? '-' : '';
        $parts = [];
        if ($koli > 0) {
            $parts[] = $sign.$koli.' koli';
        }
        if ($sisa > 0) {
            $parts[] = ($koli > 0 ? '' : $sign).$sisa.' pcs';
        }
        return $parts ? '≈ '.implode(' + ', $parts) : '';
    };
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
                    <strong>{{ $opname->warehouse?->name ?? '-' }}</strong>
                    <span class="opn-mode-badge {{ $modeClass }} ms-1">{{ $modeLabel }}</span><br>
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

        <div class="opn-mode-info {{ $isDefaultWarehouse ? '' : 'is-pcs' }}">
            @if($isDefaultWarehouse)
                <strong>Cara baca:</strong> di <em>Gudang Besar</em>, hitungan fisik diinput per <strong>kolian</strong>.
                Qty pcs dihitung otomatis = <em>kolian × isi/koli</em> per item.
                Kolom "Hitungan Fisik" menampilkan jumlah koli, dengan rincian total pcs di bawahnya.
            @else
                <strong>Cara baca:</strong> di gudang ini, hitungan fisik diinput langsung dalam satuan <strong>pcs</strong>.
                Kolom "Hitungan Fisik" menampilkan jumlah pcs.
            @endif
        </div>

        <div class="opn-summary">
            <div class="opn-summary-card">
                <div class="opn-summary-label">Total SKU Dicek</div>
                <div class="opn-summary-value">{{ number_format($totalSku, 0, ',', '.') }}</div>
                <div class="opn-summary-help">Seluruh SKU dalam opname</div>
            </div>
            <div class="opn-summary-card is-success">
                <div class="opn-summary-label">SKU Sesuai</div>
                <div class="opn-summary-value">{{ number_format($matchingSku, 0, ',', '.') }}</div>
                <div class="opn-summary-help">Fisik sama dengan sistem</div>
            </div>
            <div class="opn-summary-card is-danger">
                <div class="opn-summary-label">SKU Selisih</div>
                <div class="opn-summary-value">{{ number_format($differentSku, 0, ',', '.') }}</div>
                <div class="opn-summary-help">Perlu ditinjau</div>
            </div>
            <div class="opn-summary-card is-primary">
                <div class="opn-summary-label">Akurasi Stok Opname</div>
                <div class="opn-summary-value">{{ $accuracyLabel }}</div>
                <div class="opn-summary-help">SKU sesuai / total SKU</div>
            </div>
        </div>

        <div class="opn-table-wrap">
        <table class="opn-items">
            <thead>
                <tr>
                    <th class="opn-num">No</th>
                    <th>SKU</th>
                    <th>Nama Barang</th>
                    @if($isDefaultWarehouse)
                        <th class="opn-qty">Isi/Koli</th>
                    @endif
                    <th class="opn-qty">Stok Sistem</th>
                    <th class="opn-qty">Hitungan Fisik</th>
                    <th class="opn-qty">Selisih</th>
                    <th>Catatan</th>
                </tr>
            </thead>
            <tbody>
                @foreach([
                    ['items' => $differentItems, 'label' => 'Ada Selisih', 'class' => 'is-danger', 'row_class' => 'opn-row-diff'],
                    ['items' => $matchingItems, 'label' => 'Sesuai', 'class' => 'is-success', 'row_class' => 'opn-row-match'],
                ] as $group)
                    <tr>
                        <td colspan="{{ $isDefaultWarehouse ? 8 : 7 }}" style="padding: 0; border: 0;">
                            <div class="opn-section-heading {{ $group['class'] }}" style="margin-top: 16px;">
                                <h2>{{ $group['label'] }}</h2>
                                <span class="opn-section-count">{{ $group['items']->count() }} SKU</span>
                            </div>
                        </td>
                    </tr>
                @forelse($group['items'] as $row)
                    @php
                        $adj = (int) $row->adjustment;
                        $adjClass = $adj > 0 ? 'opn-adj-pos' : ($adj < 0 ? 'opn-adj-neg' : '');
                        $adjStr = ($adj > 0 ? '+' : '').number_format($adj, 0, ',', '.').' pcs';
                        $koliVal = $row->koli !== null ? (int) $row->koli : null;
                        $itemKoliQty = (int) ($row->item?->koli_qty ?? 0);
                        $systemQty = (int) $row->system_qty;
                        $countedQty = (int) $row->counted_qty;
                        $systemKoli = $formatKoliBreakdown($systemQty, $itemKoliQty);
                        $adjKoli = $formatKoliBreakdown($adj, $itemKoliQty);
                    @endphp
                    <tr class="{{ $group['row_class'] }}">
                        <td class="opn-num">{{ $loop->iteration }}</td>
                        <td>{{ $row->item?->sku ?? '-' }}</td>
                        <td>{{ $row->item?->name ?? '-' }}</td>
                        @if($isDefaultWarehouse)
                            <td class="opn-qty">
                                {{ $itemKoliQty > 0 ? number_format($itemKoliQty, 0, ',', '.').' pcs' : '-' }}
                            </td>
                        @endif
                        <td class="opn-qty">
                            {{ number_format($systemQty, 0, ',', '.') }} pcs
                            @if($isDefaultWarehouse && $systemKoli)
                                <span class="opn-cell-sub">{{ $systemKoli }}</span>
                            @endif
                        </td>
                        <td class="opn-qty">
                            @if($isDefaultWarehouse)
                                @if($koliVal !== null)
                                    {{ number_format($koliVal, 0, ',', '.') }} koli
                                    <span class="opn-cell-sub">
                                        @if($itemKoliQty > 0)
                                            = {{ $koliVal }} × {{ $itemKoliQty }} = {{ number_format($countedQty, 0, ',', '.') }} pcs
                                        @else
                                            {{ number_format($countedQty, 0, ',', '.') }} pcs
                                        @endif
                                    </span>
                                @else
                                    {{ number_format($countedQty, 0, ',', '.') }} pcs
                                    <span class="opn-cell-sub">kolian belum dicatat</span>
                                @endif
                            @else
                                {{ number_format($countedQty, 0, ',', '.') }} pcs
                            @endif
                        </td>
                        <td class="opn-qty {{ $adjClass }}">
                            {{ $adjStr }}
                            @if($isDefaultWarehouse && $adjKoli)
                                <span class="opn-cell-sub">{{ $adjKoli }}</span>
                            @endif
                        </td>
                        <td>{{ $row->note ?: '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $isDefaultWarehouse ? 8 : 7 }}" class="text-center text-muted">
                            Tidak ada SKU dalam kategori {{ strtolower($group['label']) }}.
                        </td>
                    </tr>
                @endforelse
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="{{ $isDefaultWarehouse ? 4 : 3 }}">TOTAL</td>
                    <td class="opn-qty">{{ number_format($totalSystem, 0, ',', '.') }} pcs</td>
                    <td class="opn-qty">
                        @if($isDefaultWarehouse)
                            {{ $totalKoli > 0 ? number_format($totalKoli, 0, ',', '.').' koli' : '-' }}
                            <span class="opn-cell-sub">= {{ number_format($totalCounted, 0, ',', '.') }} pcs</span>
                        @else
                            {{ number_format($totalCounted, 0, ',', '.') }} pcs
                        @endif
                    </td>
                    <td class="opn-qty {{ $totalAdjustment > 0 ? 'opn-adj-pos' : ($totalAdjustment < 0 ? 'opn-adj-neg' : '') }}">
                        {{ ($totalAdjustment > 0 ? '+' : '').number_format($totalAdjustment, 0, ',', '.') }} pcs
                    </td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
        </div>

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
