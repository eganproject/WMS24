@extends('layouts.admin')

@section('title', $pageTitle)
@section('page_title', $pageTitle)

@section('content')
@if(!empty($documentMode ?? false))
    @php
        $statusClass = ($transaction->status ?? '') === 'completed'
            ? 'badge-light-success'
            : (($transaction->status ?? '') === 'scanning' ? 'badge-light-primary' : 'badge-light-warning');
        $defaultWarehouseId = \App\Support\WarehouseService::defaultWarehouseId();
        $displayWarehouseId = \App\Support\WarehouseService::displayWarehouseId();
        $currentWarehouseId = $transaction->warehouse_id ?? $defaultWarehouseId;
        $warehouseBadge = 'badge-light-secondary';
        if ($currentWarehouseId == $displayWarehouseId) {
            $warehouseBadge = 'badge-light-success';
        } elseif ($currentWarehouseId == $defaultWarehouseId) {
            $warehouseBadge = 'badge-light-primary';
        }
    @endphp

    <style>
        .stock-document-shell {
            background: #f5f7fb;
            border: 1px solid #e4e8f0;
            border-radius: 8px;
            padding: 18px;
        }

        .stock-document {
            background: #fff;
            border: 1px solid #d9dee8;
            border-radius: 4px;
            color: #1f2937;
            margin: 0 auto;
            max-width: 1120px;
            padding: 32px;
        }

        .stock-document-header {
            align-items: flex-start;
            border-bottom: 2px solid #1f2937;
            display: flex;
            gap: 20px;
            justify-content: space-between;
            padding-bottom: 18px;
        }

        .stock-document-brand {
            font-size: 12px;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .stock-document-title {
            font-size: 24px;
            font-weight: 800;
            letter-spacing: 0;
            margin: 4px 0 0;
            text-transform: uppercase;
        }

        .stock-document-code {
            border: 1px solid #cfd6e2;
            border-radius: 4px;
            min-width: 260px;
            padding: 12px 14px;
            text-align: right;
        }

        .stock-document-code .label,
        .stock-document-meta .label,
        .stock-document-summary .label {
            color: #6b7280;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .stock-document-code .value {
            font-size: 18px;
            font-weight: 800;
            margin-top: 4px;
        }

        .stock-document-meta {
            display: grid;
            gap: 0;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            margin-top: 20px;
            border: 1px solid #d9dee8;
            border-bottom: 0;
            border-right: 0;
        }

        .stock-document-meta .cell {
            border-bottom: 1px solid #d9dee8;
            border-right: 1px solid #d9dee8;
            min-height: 74px;
            padding: 12px 14px;
        }

        .stock-document-meta .value,
        .stock-document-summary .value {
            font-size: 14px;
            font-weight: 700;
            margin-top: 6px;
            word-break: break-word;
        }

        .stock-document-section-title {
            color: #111827;
            font-size: 13px;
            font-weight: 800;
            letter-spacing: .06em;
            margin: 24px 0 10px;
            text-transform: uppercase;
        }

        .stock-document-table {
            border: 1px solid #d9dee8;
            margin-bottom: 0;
        }

        .stock-document-table thead th {
            background: #f3f6fb;
            border-bottom: 1px solid #d9dee8;
            color: #374151;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .04em;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .stock-document-table tbody td {
            border-color: #e5e7eb;
            color: #1f2937;
            vertical-align: top;
        }

        .stock-document-summary {
            display: grid;
            gap: 10px;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            margin-top: 14px;
        }

        .stock-document-summary .box {
            border: 1px solid #d9dee8;
            border-radius: 4px;
            padding: 12px 14px;
        }

        .stock-document-note {
            border: 1px solid #d9dee8;
            border-radius: 4px;
            min-height: 76px;
            padding: 12px 14px;
        }

        .stock-document-signatures {
            display: grid;
            gap: 16px;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            margin-top: 28px;
        }

        .stock-document-signature {
            border: 1px solid #d9dee8;
            border-radius: 4px;
            min-height: 132px;
            padding: 12px;
            text-align: center;
        }

        .stock-document-signature .line {
            border-top: 1px solid #9ca3af;
            margin: 58px auto 8px;
            width: 78%;
        }

        @media (max-width: 991px) {
            .stock-document {
                padding: 22px;
            }

            .stock-document-header {
                flex-direction: column;
            }

            .stock-document-code {
                text-align: left;
                width: 100%;
            }

            .stock-document-meta,
            .stock-document-summary,
            .stock-document-signatures {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 575px) {
            .stock-document-shell {
                padding: 10px;
            }

            .stock-document {
                padding: 16px;
            }

            .stock-document-title {
                font-size: 20px;
            }

            .stock-document-meta,
            .stock-document-summary,
            .stock-document-signatures {
                grid-template-columns: 1fr;
            }
        }

        @media print {
            .app-header,
            .app-sidebar,
            .card-toolbar,
            .page-title,
            .toolbar,
            .stock-document-actions {
                display: none !important;
            }

            .stock-document-shell {
                background: #fff;
                border: 0;
                padding: 0;
            }

            .stock-document {
                border: 0;
                max-width: none;
                padding: 0;
            }
        }
    </style>

    <div class="card">
        <div class="card-header border-0 pt-6">
            <div class="card-title">
                <div class="d-flex flex-column">
                    <div class="fw-bolder fs-5">{{ $documentTitle ?? $pageTitle }}</div>
                    <div class="text-muted fs-7">{{ $transaction->code }} | {{ $transaction->transacted_at?->format('Y-m-d H:i') ?: '-' }}</div>
                </div>
            </div>
            <div class="card-toolbar stock-document-actions">
                <button type="button" class="btn btn-light-primary me-3" onclick="window.print()">Cetak</button>
                <a href="{{ $backUrl }}" class="btn btn-light">Kembali</a>
            </div>
        </div>
        <div class="card-body py-6">
            <div class="stock-document-shell">
                <div class="stock-document">
                    <div class="stock-document-header">
                        <div>
                            <div class="stock-document-brand">Warehouse Management System</div>
                            <h1 class="stock-document-title">{{ $documentTitle ?? $pageTitle }}</h1>
                            <div class="text-muted mt-2">Dokumen kontrol masuk barang berdasarkan transaksi inbound.</div>
                        </div>
                        <div class="stock-document-code">
                            <div class="label">{{ $documentCodeLabel ?? 'No. Dokumen' }}</div>
                            <div class="value">{{ $transaction->code }}</div>
                            <div class="mt-3">
                                <span class="badge {{ $statusClass }}">{{ $statusLabel ?? '-' }}</span>
                                <span class="badge {{ $warehouseBadge }} ms-1">{{ $warehouseLabel ?? '-' }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="stock-document-meta">
                        <div class="cell">
                            <div class="label">Tanggal Transaksi</div>
                            <div class="value">{{ $transaction->transacted_at?->format('d M Y H:i') ?: '-' }}</div>
                        </div>
                        <div class="cell">
                            <div class="label">Ref No</div>
                            <div class="value">{{ $transaction->ref_no ?: '-' }}</div>
                        </div>
                        <div class="cell">
                            <div class="label">No. Surat Jalan</div>
                            <div class="value">{{ $transaction->surat_jalan_no ?: '-' }}</div>
                        </div>
                        <div class="cell">
                            <div class="label">Tanggal Surat Jalan</div>
                            <div class="value">{{ $transaction->surat_jalan_at?->format('d M Y') ?: '-' }}</div>
                        </div>
                        <div class="cell">
                            <div class="label">Gudang Tujuan</div>
                            <div class="value">{{ $warehouseLabel ?? '-' }}</div>
                        </div>
                        <div class="cell">
                            <div class="label">Supplier</div>
                            <div class="value">{{ !empty($showSupplierField ?? false) ? ($transaction->supplier?->name ?? '-') : '-' }}</div>
                        </div>
                        <div class="cell">
                            <div class="label">Dibuat Oleh</div>
                            <div class="value">{{ $transaction->creator?->name ?? '-' }}</div>
                        </div>
                        <div class="cell">
                            <div class="label">Dibuat Pada</div>
                            <div class="value">{{ $transaction->created_at?->format('d M Y H:i') ?: '-' }}</div>
                        </div>
                    </div>

                    <div class="stock-document-section-title">Daftar Barang</div>
                    <div class="table-responsive">
                        <table class="table stock-document-table align-middle fs-6">
                            <thead>
                                <tr>
                                    <th class="text-center" style="width:54px;">No</th>
                                    <th>SKU</th>
                                    <th>Nama Barang</th>
                                    @if(!empty($showKoli ?? false))
                                        <th class="text-end">Koli</th>
                                    @endif
                                    <th class="text-end">Qty</th>
                                    @if(!empty($scanSession ?? null))
                                        <th class="text-end">Scan Koli</th>
                                        <th class="text-end">Scan Qty</th>
                                    @endif
                                    <th>Catatan Item</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($transaction->items as $row)
                                    @php
                                        $scanItem = !empty($scanSession ?? null)
                                            ? $scanSession->items->firstWhere('item_id', $row->item_id)
                                            : null;
                                    @endphp
                                    <tr>
                                        <td class="text-center">{{ $loop->iteration }}</td>
                                        <td class="fw-bold">{{ $row->item?->sku ?: '-' }}</td>
                                        <td>{{ $row->item?->name ?: '-' }}</td>
                                        @if(!empty($showKoli ?? false))
                                            <td class="text-end">{{ ($row->koli ?? 0) > 0 ? number_format((int) $row->koli, 0, ',', '.') : '-' }}</td>
                                        @endif
                                        <td class="text-end fw-bold">{{ number_format((int) $row->qty, 0, ',', '.') }}</td>
                                        @if(!empty($scanSession ?? null))
                                            <td class="text-end">{{ $scanItem ? number_format((int) $scanItem->scanned_koli, 0, ',', '.') : '-' }}</td>
                                            <td class="text-end">{{ $scanItem ? number_format((int) $scanItem->scanned_qty, 0, ',', '.') : '-' }}</td>
                                        @endif
                                        <td>{{ $row->note ?: '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ !empty($scanSession ?? null) ? 8 : 6 }}" class="text-center text-muted py-8">Tidak ada item.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="stock-document-summary">
                        <div class="box">
                            <div class="label">Total SKU</div>
                            <div class="value">{{ number_format($transaction->items->count(), 0, ',', '.') }}</div>
                        </div>
                        <div class="box">
                            <div class="label">Total Qty</div>
                            <div class="value">{{ number_format((int) $totalQty, 0, ',', '.') }}</div>
                        </div>
                        <div class="box">
                            <div class="label">Total Koli</div>
                            <div class="value">{{ ($totalKoli ?? 0) > 0 ? number_format((int) $totalKoli, 0, ',', '.') : '-' }}</div>
                        </div>
                    </div>

                    <div class="stock-document-section-title">Catatan</div>
                    <div class="stock-document-note">{{ $transaction->note ?: '-' }}</div>

                    @if(!empty($scanSession ?? null))
                        <div class="stock-document-section-title">Audit Scan Inbound</div>
                        <div class="stock-document-meta mt-0">
                            <div class="cell">
                                <div class="label">Progress Qty</div>
                                <div class="value">{{ number_format((int) ($scanSummary['scanned_qty'] ?? 0), 0, ',', '.') }} / {{ number_format((int) ($scanSummary['expected_qty'] ?? 0), 0, ',', '.') }}</div>
                            </div>
                            <div class="cell">
                                <div class="label">Progress Koli</div>
                                <div class="value">{{ number_format((int) ($scanSummary['scanned_koli'] ?? 0), 0, ',', '.') }} / {{ number_format((int) ($scanSummary['expected_koli'] ?? 0), 0, ',', '.') }}</div>
                            </div>
                            <div class="cell">
                                <div class="label">Mulai Scan</div>
                                <div class="value">{{ $scanSession->started_at?->format('d M Y H:i') ?? '-' }}</div>
                                <div class="text-muted fs-8 mt-1">{{ $scanSession->starter?->name ?? '-' }}</div>
                            </div>
                            <div class="cell">
                                <div class="label">Selesai Scan</div>
                                <div class="value">{{ $scanSession->completed_at?->format('d M Y H:i') ?? '-' }}</div>
                                <div class="text-muted fs-8 mt-1">{{ $scanSession->completer?->name ?? '-' }}</div>
                            </div>
                        </div>
                    @endif

                    <div class="stock-document-signatures">
                        <div class="stock-document-signature">
                            <div class="fw-bold">Dibuat Oleh</div>
                            <div class="line"></div>
                            <div>{{ $transaction->creator?->name ?? 'Admin' }}</div>
                        </div>
                        <div class="stock-document-signature">
                            <div class="fw-bold">Diperiksa Oleh</div>
                            <div class="line"></div>
                            <div class="text-muted">Warehouse</div>
                        </div>
                        <div class="stock-document-signature">
                            <div class="fw-bold">Diterima Oleh</div>
                            <div class="line"></div>
                            <div class="text-muted">Penerima Barang</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@else
    <div class="card">
        <div class="card-header border-0 pt-6">
            <div class="card-title">
                <div class="d-flex flex-column">
                    <div class="fw-bolder fs-5">{{ $transaction->code }}</div>
                    <div class="text-muted fs-7">{{ $transaction->transacted_at?->format('Y-m-d H:i') }}</div>
                </div>
            </div>
            <div class="card-toolbar">
                <a href="{{ $backUrl }}" class="btn btn-light">Kembali</a>
            </div>
        </div>
        <div class="card-body py-6">
            <div class="row mb-6">
                <div class="col-md-4">
                    <div class="fw-bold text-gray-600">Ref No</div>
                    <div>{{ $transaction->ref_no ?? '-' }}</div>
                </div>
                @if(!empty($showSupplierField ?? false))
                    <div class="col-md-4">
                        <div class="fw-bold text-gray-600">Supplier</div>
                        <div>{{ $transaction->supplier?->name ?? '-' }}</div>
                    </div>
                @endif
                @if(!empty($showDeliveryNoteFields ?? false) || !empty($transaction->surat_jalan_no) || !empty($transaction->surat_jalan_at))
                    <div class="col-md-4">
                        <div class="fw-bold text-gray-600">No. Surat Jalan</div>
                        <div>{{ $transaction->surat_jalan_no ?: '-' }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="fw-bold text-gray-600">Tgl. Surat Jalan</div>
                        <div>{{ $transaction->surat_jalan_at?->format('Y-m-d') ?: '-' }}</div>
                    </div>
                @endif
                <div class="col-md-4">
                    <div class="fw-bold text-gray-600">Catatan</div>
                    <div>{{ $transaction->note ?? '-' }}</div>
                </div>
                @if(!empty($statusLabel ?? null))
                    <div class="col-md-4">
                        <div class="fw-bold text-gray-600">Status</div>
                        @php
                            $statusClass = ($transaction->status ?? '') === 'completed'
                                ? 'badge-light-success'
                                : (($transaction->status ?? '') === 'scanning' ? 'badge-light-primary' : 'badge-light-warning');
                        @endphp
                        <div><span class="badge {{ $statusClass }}">{{ $statusLabel }}</span></div>
                    </div>
                @endif
                @if(!empty($warehouseLabel ?? null))
                    <div class="col-md-4">
                        <div class="fw-bold text-gray-600">Gudang</div>
                        @php
                            $defaultWarehouseId = \App\Support\WarehouseService::defaultWarehouseId();
                            $displayWarehouseId = \App\Support\WarehouseService::displayWarehouseId();
                            $currentWarehouseId = $transaction->warehouse_id ?? $defaultWarehouseId;
                            $warehouseBadge = 'badge-light-secondary';
                            if ($currentWarehouseId == $displayWarehouseId) {
                                $warehouseBadge = 'badge-light-success';
                            } elseif ($currentWarehouseId == $defaultWarehouseId) {
                                $warehouseBadge = 'badge-light-primary';
                            }
                        @endphp
                        <div><span class="badge {{ $warehouseBadge }}">{{ $warehouseLabel }}</span></div>
                    </div>
                @endif
                <div class="col-md-4">
                    <div class="fw-bold text-gray-600">Total Qty</div>
                    <div>{{ $totalQty }}</div>
                </div>
                @if(!empty($showKoli ?? false))
                    <div class="col-md-4">
                        <div class="fw-bold text-gray-600">Total Koli</div>
                        <div>{{ ($totalKoli ?? 0) > 0 ? $totalKoli : '-' }}</div>
                    </div>
                @endif
                @if(!empty($scanSession ?? null))
                    <div class="col-md-4">
                        <div class="fw-bold text-gray-600">Progress Scan</div>
                        <div>
                            Qty {{ $scanSummary['scanned_qty'] ?? 0 }}/{{ $scanSummary['expected_qty'] ?? 0 }}
                            <div class="text-muted fs-7">Koli {{ $scanSummary['scanned_koli'] ?? 0 }}/{{ $scanSummary['expected_koli'] ?? 0 }}</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="fw-bold text-gray-600">Audit Scan</div>
                        <div class="text-muted fs-7">
                            Mulai: {{ $scanSession->started_at?->format('Y-m-d H:i') ?? '-' }} / {{ $scanSession->starter?->name ?? '-' }}
                        </div>
                        <div class="text-muted fs-7">
                            Selesai: {{ $scanSession->completed_at?->format('Y-m-d H:i') ?? '-' }} / {{ $scanSession->completer?->name ?? '-' }}
                        </div>
                    </div>
                @endif
            </div>

            <div class="table-responsive">
                <table class="table align-middle table-row-dashed fs-6 gy-5">
                    <thead>
                        <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                            <th>Item</th>
                            @if(!empty($showKoli ?? false))
                                <th>Koli</th>
                            @endif
                            <th>Qty</th>
                            @if(!empty($scanSession ?? null))
                                <th>Scan Koli</th>
                                <th>Scan Qty</th>
                            @endif
                            <th>Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($transaction->items as $row)
                            @php
                                $scanItem = !empty($scanSession ?? null)
                                    ? $scanSession->items->firstWhere('item_id', $row->item_id)
                                    : null;
                            @endphp
                            <tr>
                                <td>{{ $row->item?->sku }} - {{ $row->item?->name }}</td>
                                @if(!empty($showKoli ?? false))
                                    <td>{{ $row->koli ?? '-' }}</td>
                                @endif
                                <td>{{ $row->qty }}</td>
                                @if(!empty($scanSession ?? null))
                                    <td>{{ $scanItem?->scanned_koli ?? '-' }}</td>
                                    <td>{{ $scanItem?->scanned_qty ?? '-' }}</td>
                                @endif
                                <td>{{ $row->note ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endif
@endsection
