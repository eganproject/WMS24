@extends('layouts.admin')

@section('title', $pageTitle)
@section('page_title', $pageHeading)

@php
    $items = $customerReturn->items ?? collect();
    $totalExpected = (int) $items->sum('expected_qty');
    $totalReceived = (int) $items->sum('received_qty');
    $totalGood = (int) $items->sum('good_qty');
    $totalDamaged = (int) $items->sum('damaged_qty');
    $isMatched = (bool) $customerReturn->resi_id;
    $statusLabel = $customerReturn->isCompleted() ? 'Selesai' : 'Belum Finalisasi';
    $printedAt = now()->format('Y-m-d H:i');
@endphp

@push('styles')
<style>
    .customer-return-document-page {
        max-width: 210mm;
        margin: 0 auto;
    }

    .customer-return-document-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1.25rem;
    }

    .customer-return-document-sheet {
        background: #fff;
        border: 1px solid #e8edf3;
        border-radius: 1.25rem;
        box-shadow: 0 20px 45px rgba(15, 23, 42, 0.07);
        overflow: hidden;
    }

    .customer-return-document-header {
        padding: 1.75rem 2rem 1.35rem;
        background: linear-gradient(180deg, #f8fbff 0%, #ffffff 100%);
        border-bottom: 1px solid #eef2f7;
    }

    .customer-return-document-title {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        margin-bottom: 1.25rem;
    }

    .customer-return-document-badges {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        justify-content: flex-end;
    }

    .customer-return-document-code {
        font-size: 1.5rem;
        line-height: 1.2;
        font-weight: 700;
        color: #111827;
        margin-bottom: 0.35rem;
    }

    .customer-return-document-subtitle {
        color: #6b7280;
        font-size: 0.95rem;
    }

    .customer-return-document-meta {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.9rem 1.25rem;
    }

    .customer-return-document-meta-item {
        padding: 0.85rem 1rem;
        border: 1px solid #eef2f7;
        border-radius: 0.95rem;
        background: #fff;
    }

    .customer-return-document-meta-label {
        color: #7e8299;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-size: 0.72rem;
        font-weight: 700;
        margin-bottom: 0.3rem;
    }

    .customer-return-document-meta-value {
        color: #111827;
        font-size: 0.95rem;
        font-weight: 600;
        line-height: 1.35;
        word-break: break-word;
    }

    .customer-return-document-body {
        padding: 1.5rem 2rem 2rem;
    }

    .customer-return-document-summary {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 0.85rem;
        margin-bottom: 1.5rem;
    }

    .customer-return-document-summary-card {
        border-radius: 1rem;
        padding: 0.95rem 1rem;
        border: 1px solid #eef2f7;
        background: #f9fafb;
    }

    .customer-return-document-summary-card.is-good {
        background: #eefbf3;
        border-color: #d8f3e0;
    }

    .customer-return-document-summary-card.is-damaged {
        background: #fff5f8;
        border-color: #ffd8e3;
    }

    .customer-return-document-summary-card.is-received {
        background: #f5f8ff;
        border-color: #dfebff;
    }

    .customer-return-document-summary-label {
        color: #7e8299;
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-weight: 700;
        margin-bottom: 0.35rem;
    }

    .customer-return-document-summary-value {
        color: #111827;
        font-size: 1.35rem;
        line-height: 1;
        font-weight: 700;
    }

    .customer-return-document-section {
        margin-top: 1.5rem;
    }

    .customer-return-document-section-title {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        margin-bottom: 0.85rem;
    }

    .customer-return-document-section-title h3 {
        margin: 0;
        font-size: 1rem;
        font-weight: 700;
        color: #111827;
    }

    .customer-return-document-table-wrap {
        border: 1px solid #e8edf3;
        border-radius: 1rem;
        overflow: hidden;
    }

    .customer-return-document-table {
        width: 100%;
        border-collapse: collapse;
    }

    .customer-return-document-table th,
    .customer-return-document-table td {
        border-bottom: 1px solid #eef2f7;
        padding: 0.8rem 0.9rem;
        font-size: 0.9rem;
        vertical-align: top;
    }

    .customer-return-document-table th {
        background: #f9fafb;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-size: 0.72rem;
        font-weight: 700;
        white-space: nowrap;
    }

    .customer-return-document-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .customer-return-document-table td.text-end,
    .customer-return-document-table th.text-end {
        text-align: right;
    }

    .customer-return-document-item-name {
        color: #111827;
        font-weight: 600;
        line-height: 1.35;
    }

    .customer-return-document-item-sku {
        color: #6b7280;
        font-size: 0.82rem;
        margin-top: 0.15rem;
    }

    .customer-return-document-item-note {
        display: inline-block;
        margin-top: 0.25rem;
        color: #6b7280;
        font-size: 0.8rem;
        line-height: 1.4;
    }

    .customer-return-document-note-box {
        margin-top: 1rem;
        padding: 1rem 1.1rem;
        border-radius: 1rem;
        background: #fffbeb;
        border: 1px solid #fef3c7;
        color: #5b5f72;
        font-size: 0.9rem;
        line-height: 1.55;
    }

    .customer-return-document-footer {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        gap: 1rem;
        margin-top: 1.5rem;
        padding-top: 1rem;
        border-top: 1px dashed #dfe3ea;
        color: #6b7280;
        font-size: 0.82rem;
    }

    .customer-return-document-signoff {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1rem;
        margin-top: 1.5rem;
    }

    .customer-return-document-signoff-box {
        border: 1px dashed #d1d5db;
        border-radius: 1rem;
        min-height: 92px;
        padding: 0.9rem 1rem;
    }

    .customer-return-document-signoff-label {
        font-size: 0.78rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #7e8299;
        font-weight: 700;
        margin-bottom: 2.5rem;
    }

    .customer-return-document-signoff-name {
        color: #111827;
        font-weight: 600;
    }

    @media (max-width: 991.98px) {
        .customer-return-document-actions,
        .customer-return-document-title,
        .customer-return-document-section-title,
        .customer-return-document-footer {
            flex-direction: column;
            align-items: stretch;
        }

        .customer-return-document-meta,
        .customer-return-document-summary,
        .customer-return-document-signoff {
            grid-template-columns: 1fr;
        }

        .customer-return-document-header,
        .customer-return-document-body {
            padding-left: 1.25rem;
            padding-right: 1.25rem;
        }
    }

    @media print {
        @page {
            size: A4 portrait;
            margin: 12mm;
        }

        body {
            background: #fff !important;
        }

        #kt_header,
        #kt_aside,
        #kt_toolbar,
        #kt_footer,
        .app-header,
        .app-sidebar,
        .app-toolbar,
        .app-footer,
        .customer-return-document-actions {
            display: none !important;
        }

        .wrapper,
        .app-wrapper,
        .content,
        .container-fluid,
        .container-xxl {
            padding: 0 !important;
            margin: 0 !important;
            max-width: none !important;
            width: 100% !important;
        }

        .card,
        .customer-return-document-sheet {
            box-shadow: none !important;
            border: 0 !important;
            border-radius: 0 !important;
        }

        .customer-return-document-page {
            max-width: none;
        }

        .customer-return-document-header,
        .customer-return-document-body {
            padding-left: 0;
            padding-right: 0;
        }

        .customer-return-document-table-wrap,
        .customer-return-document-meta-item,
        .customer-return-document-summary-card,
        .customer-return-document-note-box,
        .customer-return-document-signoff-box {
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .customer-return-document-table th,
        .customer-return-document-table td {
            padding: 7px 8px;
            font-size: 11px;
        }

        .customer-return-document-table th {
            font-size: 10px;
        }

        a {
            text-decoration: none !important;
            color: inherit !important;
        }
    }
</style>
@endpush

@section('content')
<div class="content d-flex flex-column flex-column-fluid" id="kt_content">
    <div class="container-fluid" id="kt_content_container">
        <div class="customer-return-document-page">
            <div class="customer-return-document-actions">
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ $backUrl }}" class="btn btn-light">Kembali</a>
                    @if(!$customerReturn->isCompleted())
                        <a href="{{ route('admin.inventory.customer-returns.edit', $customerReturn->id) }}" class="btn btn-light-primary">Edit</a>
                    @endif
                </div>
                <button type="button" class="btn btn-primary" onclick="window.print()">Print Dokumen</button>
            </div>

            <div class="customer-return-document-sheet">
                <div class="customer-return-document-header">
                    <div class="customer-return-document-title">
                        <div>
                            <div class="customer-return-document-code">{{ $customerReturn->code }}</div>
                            <div class="customer-return-document-subtitle">Dokumen retur customer untuk inspeksi dan finalisasi stok.</div>
                        </div>
                        <div class="customer-return-document-badges">
                            <span class="badge badge-light-{{ $customerReturn->isCompleted() ? 'success' : 'warning' }}">{{ $statusLabel }}</span>
                            <span class="badge badge-light-{{ $isMatched ? 'primary' : 'warning' }}">{{ $isMatched ? 'Resi Ditemukan' : 'Input Manual' }}</span>
                            <span class="badge badge-light-success">Bagus ke {{ $displayWarehouseLabel }}</span>
                            <span class="badge badge-light-danger">Rusak ke {{ $damagedWarehouseLabel }}</span>
                            @if($customerReturn->damagedGood?->code)
                                <span class="badge badge-light-danger">Dok. Rusak {{ $customerReturn->damagedGood->code }}</span>
                            @endif
                        </div>
                    </div>

                    <div class="customer-return-document-meta">
                        <div class="customer-return-document-meta-item">
                            <div class="customer-return-document-meta-label">Nomor Resi</div>
                            <div class="customer-return-document-meta-value">{{ $customerReturn->resi_no }}</div>
                        </div>
                        <div class="customer-return-document-meta-item">
                            <div class="customer-return-document-meta-label">Order Ref</div>
                            <div class="customer-return-document-meta-value">{{ $customerReturn->order_ref ?: '-' }}</div>
                        </div>
                        <div class="customer-return-document-meta-item">
                            <div class="customer-return-document-meta-label">Tanggal Terima</div>
                            <div class="customer-return-document-meta-value">{{ optional($customerReturn->received_at)->format('Y-m-d H:i') ?: '-' }}</div>
                        </div>
                        <div class="customer-return-document-meta-item">
                            <div class="customer-return-document-meta-label">Tanggal Inspeksi</div>
                            <div class="customer-return-document-meta-value">{{ optional($customerReturn->inspected_at)->format('Y-m-d H:i') ?: '-' }}</div>
                        </div>
                        <div class="customer-return-document-meta-item">
                            <div class="customer-return-document-meta-label">Dicatat Oleh</div>
                            <div class="customer-return-document-meta-value">{{ $customerReturn->creator?->name ?: '-' }}</div>
                        </div>
                        <div class="customer-return-document-meta-item">
                            <div class="customer-return-document-meta-label">Inspector</div>
                            <div class="customer-return-document-meta-value">{{ $customerReturn->inspector?->name ?: '-' }}</div>
                        </div>
                        <div class="customer-return-document-meta-item">
                            <div class="customer-return-document-meta-label">Finalisasi</div>
                            <div class="customer-return-document-meta-value">{{ optional($customerReturn->finalized_at)->format('Y-m-d H:i') ?: '-' }}</div>
                        </div>
                        <div class="customer-return-document-meta-item">
                            <div class="customer-return-document-meta-label">PIC Finalisasi</div>
                            <div class="customer-return-document-meta-value">{{ $customerReturn->finalizer?->name ?: '-' }}</div>
                        </div>
                    </div>
                </div>

                <div class="customer-return-document-body">
                    <div class="customer-return-document-summary">
                        <div class="customer-return-document-summary-card">
                            <div class="customer-return-document-summary-label">Qty Resi</div>
                            <div class="customer-return-document-summary-value">{{ $totalExpected }}</div>
                        </div>
                        <div class="customer-return-document-summary-card is-received">
                            <div class="customer-return-document-summary-label">Qty Diterima</div>
                            <div class="customer-return-document-summary-value">{{ $totalReceived }}</div>
                        </div>
                        <div class="customer-return-document-summary-card is-good">
                            <div class="customer-return-document-summary-label">Qty Bagus</div>
                            <div class="customer-return-document-summary-value">{{ $totalGood }}</div>
                        </div>
                        <div class="customer-return-document-summary-card is-damaged">
                            <div class="customer-return-document-summary-label">Qty Rusak</div>
                            <div class="customer-return-document-summary-value">{{ $totalDamaged }}</div>
                        </div>
                    </div>

                    <div class="customer-return-document-section">
                        <div class="customer-return-document-section-title">
                            <h3>Rincian Item Retur</h3>
                            <div class="text-muted fs-7">Per item ditampilkan qty dari resi, qty fisik diterima, hasil bagus, dan hasil rusak.</div>
                        </div>

                        <div class="customer-return-document-table-wrap">
                            <table class="customer-return-document-table">
                                <thead>
                                    <tr>
                                        <th style="width: 44px;">No</th>
                                        <th>Item</th>
                                        <th class="text-end">Qty Resi</th>
                                        <th class="text-end">Diterima</th>
                                        <th class="text-end">Bagus</th>
                                        <th class="text-end">Rusak</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($items as $index => $row)
                                        <tr>
                                            <td>{{ $index + 1 }}</td>
                                            <td>
                                                <div class="customer-return-document-item-name">{{ $row->item?->name ?: '-' }}</div>
                                                <div class="customer-return-document-item-sku">{{ $row->item?->sku ?: '-' }}</div>
                                                @if($row->note)
                                                    <span class="customer-return-document-item-note">Catatan item: {{ $row->note }}</span>
                                                @endif
                                            </td>
                                            <td class="text-end">{{ (int) $row->expected_qty }}</td>
                                            <td class="text-end">{{ (int) $row->received_qty }}</td>
                                            <td class="text-end">{{ (int) $row->good_qty }}</td>
                                            <td class="text-end">{{ (int) $row->damaged_qty }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6">Tidak ada item retur.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    @if($customerReturn->note)
                        <div class="customer-return-document-note-box">
                            <strong>Catatan Umum:</strong><br>
                            {{ $customerReturn->note }}
                        </div>
                    @endif

                    <div class="customer-return-document-signoff">
                        <div class="customer-return-document-signoff-box">
                            <div class="customer-return-document-signoff-label">Inspector</div>
                            <div class="customer-return-document-signoff-name">{{ $customerReturn->inspector?->name ?: '-' }}</div>
                        </div>
                        <div class="customer-return-document-signoff-box">
                            <div class="customer-return-document-signoff-label">PIC Finalisasi</div>
                            <div class="customer-return-document-signoff-name">{{ $customerReturn->finalizer?->name ?: '-' }}</div>
                        </div>
                    </div>

                    <div class="customer-return-document-footer">
                        <div>
                            <div class="fw-bold text-gray-800">Retur Customer</div>
                            <div>Nomor resi: {{ $customerReturn->resi_no }}</div>
                        </div>
                        <div class="text-end">
                            <div>Dicetak pada {{ $printedAt }}</div>
                            <div>Status dokumen: {{ $statusLabel }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
