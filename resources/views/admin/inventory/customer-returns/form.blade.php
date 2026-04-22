@extends('layouts.admin')

@section('title', $pageTitle)
@section('page_title', $pageHeading)

@php
    $existingItems = $customerReturn?->items
        ? $customerReturn->items->map(function ($row) {
            return [
                'item_id' => $row->item_id,
                'expected_qty' => (int) $row->expected_qty,
                'received_qty' => (int) $row->received_qty,
                'good_qty' => (int) $row->good_qty,
                'damaged_qty' => (int) $row->damaged_qty,
                'note' => $row->note,
            ];
        })->values()->all()
        : [];

    $initialItems = old('items', !empty($existingItems) ? $existingItems : [[]]);
    if (empty($initialItems)) {
        $initialItems = [[]];
    }

    $resiIdValue = old('resi_id', $customerReturn?->resi_id);
    $resiNoValue = old('resi_no', $customerReturn?->resi_no ?? '');
    $orderRefValue = old('order_ref', $customerReturn?->order_ref ?? '');
    $receivedAtValue = old('received_at', $customerReturn?->received_at?->format('Y-m-d H:i') ?? '');
    $noteValue = old('note', $customerReturn?->note ?? '');
    $readOnlyMode = (bool) ($readOnly ?? false);
    $isEditMode = !$readOnlyMode && $customerReturn;
@endphp

@push('styles')
<style>
    .customer-return-form-shell {
        display: grid;
        grid-template-columns: minmax(0, 1.4fr) minmax(320px, 0.85fr);
        gap: 1rem;
    }

    .customer-return-page-card {
        background: #fff;
        border: 1px solid #eff2f5;
        border-radius: 1rem;
        padding: 1.25rem;
        box-shadow: 0 8px 30px rgba(15, 23, 42, 0.04);
    }

    .customer-return-page-card-muted {
        background: linear-gradient(180deg, #fbfdff 0%, #f5f8ff 100%);
        border-color: #dfe9ff;
    }

    .customer-return-page-card-title {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .customer-return-page-card-title h3,
    .customer-return-page-card-title h4 {
        margin-bottom: 0.25rem;
    }

    .customer-return-form-shell .select2-container {
        width: 100% !important;
    }

    .customer-return-quick-notes {
        display: grid;
        gap: 0.75rem;
        margin-top: 1rem;
    }

    .customer-return-note-chip {
        display: flex;
        gap: 0.75rem;
        align-items: flex-start;
        padding: 0.85rem 1rem;
        border-radius: 0.85rem;
        background: rgba(255, 255, 255, 0.9);
        border: 1px solid #eef3f7;
    }

    .customer-return-note-chip .badge {
        flex: 0 0 auto;
        margin-top: 0.1rem;
    }

    .customer-return-summary-box {
        background: #f8fbff;
        border: 1px solid #e1efff;
        border-radius: 0.85rem;
        padding: 1rem 1.25rem;
    }

    .customer-return-summary-badges {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .customer-return-item-row {
        background: #fff;
        border-radius: 0.85rem;
    }

    .customer-return-item-state {
        background: #f8f9fa;
        border: 1px solid #eff2f5;
        border-radius: 0.85rem;
        padding: 0.85rem 1rem;
    }

    .customer-return-form-footer {
        position: sticky;
        bottom: 1rem;
        z-index: 2;
        margin-top: 1.5rem;
    }

    .customer-return-form-footer .customer-return-page-card {
        padding-top: 1rem;
        padding-bottom: 1rem;
    }

    @media (max-width: 991.98px) {
        .customer-return-form-shell {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 767.98px) {
        .customer-return-page-card,
        .customer-return-summary-box,
        .customer-return-item-row {
            padding: 1rem !important;
        }

        .customer-return-form-header-actions .btn,
        .customer-return-form-footer .btn,
        #btn_lookup_resi,
        #btn_add_customer_return_item {
            width: 100%;
        }

        .customer-return-resi-group .input-group {
            flex-direction: column;
        }

        .customer-return-resi-group .input-group > .form-control,
        .customer-return-resi-group .input-group > .btn {
            width: 100%;
            border-radius: 0.85rem !important;
        }

        .customer-return-resi-group .input-group > .btn {
            margin-top: 0.75rem;
        }
    }
</style>
@endpush

@section('content')
<div class="content d-flex flex-column flex-column-fluid" id="kt_content">
    <div class="container-fluid" id="kt_content_container">
        @if($errors->has('customer_return'))
            <div class="alert alert-danger mb-6">{{ $errors->first('customer_return') }}</div>
        @endif

        <form method="POST" action="{{ $readOnlyMode ? '#' : $formAction }}" class="form" id="customer_return_form">
            @csrf
            @if($isEditMode)
                @method('PUT')
            @endif

            <input type="hidden" name="resi_id" id="customer_return_resi_id" value="{{ $resiIdValue }}" />

            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-6 customer-return-form-header-actions">
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <span class="badge badge-light-success">Masuk ke {{ $displayWarehouseLabel }}</span>
                    <span class="badge badge-light-danger">Rusak ke {{ $damagedWarehouseLabel }}</span>
                    @if($customerReturn?->code)
                        <span class="badge badge-light-dark">Dokumen {{ $customerReturn->code }}</span>
                    @endif
                    @if($customerReturn?->damagedGood?->code)
                        <span class="badge badge-light-warning">Barang Rusak {{ $customerReturn->damagedGood->code }}</span>
                    @endif
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ $backUrl }}" class="btn btn-light">Kembali</a>
                    @if($readOnlyMode && $customerReturn && !$customerReturn->isCompleted())
                        <a href="{{ route('admin.inventory.customer-returns.edit', $customerReturn->id) }}" class="btn btn-light-primary">Edit</a>
                    @endif
                </div>
            </div>

            <div class="customer-return-form-shell mb-6">
                <div class="customer-return-page-card customer-return-resi-group">
                    <div class="customer-return-page-card-title">
                        <div>
                            <h3 class="fs-4 fw-bold text-gray-900">Scan Resi</h3>
                            <div class="text-muted fs-7">Tarik data SKU dari resi marketplace lebih dulu sebagai acuan pemeriksaan paket.</div>
                        </div>
                        <span class="badge badge-light-primary">Langkah 1</span>
                    </div>

                    <div class="mb-5">
                        <label class="required fs-6 fw-bold form-label mb-2">Nomor Resi</label>
                        <div class="input-group">
                            <input
                                type="text"
                                class="form-control form-control-solid @error('resi_no') is-invalid @enderror"
                                name="resi_no"
                                id="customer_return_resi_no"
                                value="{{ $resiNoValue }}"
                                placeholder="Scan atau input nomor resi"
                                @disabled($readOnlyMode)
                                required
                            />
                            <button type="button" class="btn btn-light-primary" id="btn_lookup_resi" @disabled($readOnlyMode)>Cari Resi</button>
                        </div>
                        @error('resi_no')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        <div class="form-text">Jika resi ditemukan, daftar SKU dari resi akan terisi otomatis.</div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="fs-6 fw-bold form-label mb-2">Order Ref</label>
                            <input
                                type="text"
                                class="form-control form-control-solid @error('order_ref') is-invalid @enderror"
                                name="order_ref"
                                id="customer_return_order_ref"
                                value="{{ $orderRefValue }}"
                                placeholder="ID pesanan / referensi order"
                                @disabled($readOnlyMode)
                            />
                            @error('order_ref')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="required fs-6 fw-bold form-label mb-2">Tanggal Terima</label>
                            <input
                                type="text"
                                class="form-control form-control-solid @error('received_at') is-invalid @enderror"
                                name="received_at"
                                id="customer_return_received_at"
                                value="{{ $receivedAtValue }}"
                                placeholder="YYYY-MM-DD HH:mm"
                                @disabled($readOnlyMode)
                                required
                            />
                            @error('received_at')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                <div class="customer-return-page-card customer-return-page-card-muted">
                    <div class="customer-return-page-card-title">
                        <div>
                            <h4 class="fs-5 fw-bold text-gray-900 mb-1">Status Lookup</h4>
                            <div class="text-muted fs-7">Ringkasan hasil pencarian resi dan panduan jika isi paket berbeda dengan data resi.</div>
                        </div>
                        <span class="badge badge-light-secondary" id="lookup_status_badge">
                            @if($resiIdValue)
                                Resi Ditemukan
                            @elseif($resiNoValue !== '')
                                Input Manual
                            @else
                                Belum cari resi
                            @endif
                        </span>
                    </div>

                    <div class="text-gray-700 fw-semibold mb-3" id="lookup_status_text">
                        @if($resiIdValue)
                            Retur ini terhubung ke data resi.
                        @elseif($resiNoValue !== '')
                            Retur ini disimpan manual tanpa data resi yang cocok.
                        @else
                            Input nomor resi lalu klik Cari Resi.
                        @endif
                    </div>

                    <div class="alert alert-light-warning d-none mb-0" id="lookup_warning_box"></div>

                    <div class="customer-return-quick-notes">
                        <div class="customer-return-note-chip">
                            <span class="badge badge-light-danger">0</span>
                            <div class="text-muted fs-7">Jika SKU dari data resi ternyata tidak ada di paket, biarkan <strong>Qty Diterima = 0</strong>.</div>
                        </div>
                        <div class="customer-return-note-chip">
                            <span class="badge badge-light-primary">+</span>
                            <div class="text-muted fs-7">Jika ada SKU tambahan di fisik paket, klik <strong>Tambah Item Manual</strong>.</div>
                        </div>
                        <div class="customer-return-note-chip">
                            <span class="badge badge-light-success">OK</span>
                            <div class="text-muted fs-7">Selalu isi hasil inspeksi sesuai barang fisik yang benar, tidak perlu dipaksa sama dengan data resi.</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="customer-return-page-card mb-6">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
                    <div>
                        <h3 class="mb-1">Inspeksi Item</h3>
                        <div class="text-muted fs-7">Setiap barang dihitung per pcs. Jumlah barang bagus ditambah barang rusak harus sama dengan jumlah yang benar-benar diterima.</div>
                    </div>
                    @unless($readOnlyMode)
                        <button type="button" class="btn btn-light" id="btn_add_customer_return_item">Tambah Item Manual</button>
                    @endunless
                </div>

                    <div class="customer-return-summary-box mb-5">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                        <div>
                            <div class="fw-bold text-gray-800">Jika Isi Paket Tidak Sama dengan Resi</div>
                            <div class="text-muted fs-7">Jika isi paket tidak sama dengan data resi, inspeksi tetap bisa lanjut. Catat jumlah fisik yang benar.</div>
                        </div>
                        <div class="customer-return-summary-badges" id="customer_return_summary_badges">
                            <span class="badge badge-light-secondary">Belum ada inspeksi</span>
                        </div>
                    </div>
                    <div class="text-muted fs-7 mt-3">
                        SKU dari data resi yang tidak ada di paket: biarkan <strong>Qty Diterima = 0</strong>. Jika ada SKU tambahan di paket: klik <strong>Tambah Item Manual</strong>. Jika jumlah fisik kurang atau lebih: isi <strong>Qty Diterima</strong> sesuai barang nyata.
                    </div>
                </div>

                <div id="customer_return_items_container">
                    @foreach($initialItems as $index => $formItem)
                        <div class="row g-3 align-items-end mb-4 customer-return-item-row border border-dashed border-gray-300 rounded p-4">
                            <div class="col-12">
                                <div class="customer-return-item-state d-flex flex-wrap justify-content-between align-items-start gap-3">
                                    <div>
                                        <div class="fw-bold text-gray-800">Status inspeksi item</div>
                                        <div class="text-muted fs-7" data-role="variance-hint">Isi jumlah diterima sesuai barang yang benar-benar ada di paket.</div>
                                    </div>
                                    <div class="d-flex flex-wrap gap-2">
                                        <span class="badge badge-light-primary" data-role="expected-badge">Qty Resi {{ (int) ($formItem['expected_qty'] ?? 0) }}</span>
                                        <span class="badge badge-light-secondary" data-role="variance-badge">Belum diinspeksi</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-4 col-lg-5 col-md-12">
                                <label class="required fs-6 fw-bold form-label mb-2">Item</label>
                                <select
                                    class="form-select form-select-solid customer-return-item-select @error("items.$index.item_id") is-invalid @enderror"
                                    data-name="item_id"
                                    name="items[{{ $index }}][item_id]"
                                    @disabled($readOnlyMode)
                                    required
                                >
                                    <option value=""></option>
                                    @foreach($items as $item)
                                        <option value="{{ $item->id }}" @selected((string) old("items.$index.item_id", $formItem['item_id'] ?? '') === (string) $item->id)>{{ $item->sku }} - {{ $item->name }}</option>
                                    @endforeach
                                </select>
                                <div class="invalid-feedback d-block" data-error-for="item_id">{{ $errors->first("items.$index.item_id") }}</div>
                            </div>
                            <div class="col-xl-2 col-lg-2 col-md-4 col-sm-6 col-6">
                                <label class="fs-6 fw-bold form-label mb-2">Qty Resi</label>
                                <input type="number" min="0" class="form-control form-control-solid @error("items.$index.expected_qty") is-invalid @enderror" data-name="expected_qty" name="items[{{ $index }}][expected_qty]" value="{{ old("items.$index.expected_qty", $formItem['expected_qty'] ?? 0) }}" @disabled($readOnlyMode) />
                                <div class="invalid-feedback d-block" data-error-for="expected_qty">{{ $errors->first("items.$index.expected_qty") }}</div>
                            </div>
                            <div class="col-xl-2 col-lg-2 col-md-4 col-sm-6 col-6">
                                <label class="required fs-6 fw-bold form-label mb-2">Diterima</label>
                                <input type="number" min="0" class="form-control form-control-solid @error("items.$index.received_qty") is-invalid @enderror" data-name="received_qty" name="items[{{ $index }}][received_qty]" value="{{ old("items.$index.received_qty", $formItem['received_qty'] ?? 0) }}" @disabled($readOnlyMode) required />
                                <div class="invalid-feedback d-block" data-error-for="received_qty">{{ $errors->first("items.$index.received_qty") }}</div>
                            </div>
                            <div class="col-xl-2 col-lg-1 col-md-4 col-sm-6 col-6">
                                <label class="required fs-6 fw-bold form-label mb-2">Bagus</label>
                                <input type="number" min="0" class="form-control form-control-solid @error("items.$index.good_qty") is-invalid @enderror" data-name="good_qty" name="items[{{ $index }}][good_qty]" value="{{ old("items.$index.good_qty", $formItem['good_qty'] ?? 0) }}" @disabled($readOnlyMode) required />
                                <div class="invalid-feedback d-block" data-error-for="good_qty">{{ $errors->first("items.$index.good_qty") }}</div>
                            </div>
                            <div class="col-xl-2 col-lg-2 col-md-4 col-sm-6 col-6">
                                <label class="required fs-6 fw-bold form-label mb-2">Rusak</label>
                                <input type="number" min="0" class="form-control form-control-solid @error("items.$index.damaged_qty") is-invalid @enderror" data-name="damaged_qty" name="items[{{ $index }}][damaged_qty]" value="{{ old("items.$index.damaged_qty", $formItem['damaged_qty'] ?? 0) }}" @disabled($readOnlyMode) required />
                                <div class="invalid-feedback d-block" data-error-for="damaged_qty">{{ $errors->first("items.$index.damaged_qty") }}</div>
                            </div>
                            <div class="col-xl-10 col-lg-9 col-md-8 col-12">
                                <label class="fs-6 fw-bold form-label mb-2">Catatan Item</label>
                                <input type="text" class="form-control form-control-solid @error("items.$index.note") is-invalid @enderror" data-name="note" name="items[{{ $index }}][note]" value="{{ old("items.$index.note", $formItem['note'] ?? '') }}" placeholder="Contoh: box rusak, segel terbuka, atau minus aksesoris" @disabled($readOnlyMode) />
                                <div class="invalid-feedback d-block" data-error-for="note">{{ $errors->first("items.$index.note") }}</div>
                            </div>
                            <div class="col-xl-2 col-lg-3 col-md-4 col-12 text-end">
                                @unless($readOnlyMode)
                                    <button type="button" class="btn btn-light-danger btn_remove_customer_return_item w-100">Hapus</button>
                                @endunless
                            </div>
                        </div>
                    @endforeach
                </div>

                @error('items')<div class="invalid-feedback d-block mb-4" id="error_items">{{ $message }}</div>@else<div class="invalid-feedback d-block mb-4" id="error_items"></div>@enderror

                <div class="fv-row mt-6">
                    <label class="fs-6 fw-bold form-label mb-2">Catatan</label>
                    <textarea class="form-control form-control-solid @error('note') is-invalid @enderror" name="note" id="customer_return_note" rows="3" placeholder="Catatan inspeksi, kondisi paket, atau mismatch resi" @disabled($readOnlyMode)>{{ $noteValue }}</textarea>
                    @error('note')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="customer-return-form-footer">
                <div class="customer-return-page-card">
                    <div class="d-flex justify-content-end flex-column flex-sm-row gap-3">
                        <a href="{{ $backUrl }}" class="btn btn-light">Kembali</a>
                        @unless($readOnlyMode)
                            <button type="submit" class="btn btn-primary" id="customer_return_submit_btn">{{ $submitLabel }}</button>
                        @endunless
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const customerReturnLookupUrl = @json($lookupUrl);
    const customerReturnItemOptionsHtml = `@foreach($items as $item)<option value="{{ $item->id }}">{{ $item->sku }} - {{ $item->name }}</option>@endforeach`;
    const customerReturnReadOnly = {{ $readOnlyMode ? 'true' : 'false' }};

    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('customer_return_form');
        const itemsContainer = document.getElementById('customer_return_items_container');
        const addItemBtn = document.getElementById('btn_add_customer_return_item');
        const lookupBtn = document.getElementById('btn_lookup_resi');
        const resiNoEl = document.getElementById('customer_return_resi_no');
        const resiIdEl = document.getElementById('customer_return_resi_id');
        const orderRefEl = document.getElementById('customer_return_order_ref');
        const receivedAtEl = document.getElementById('customer_return_received_at');
        const lookupStatusBadge = document.getElementById('lookup_status_badge');
        const lookupStatusText = document.getElementById('lookup_status_text');
        const lookupWarningBox = document.getElementById('lookup_warning_box');
        const summaryBadgesEl = document.getElementById('customer_return_summary_badges');
        let fpReceivedAt = null;

        const getJakartaNow = () => {
            const now = new Date(new Date().toLocaleString('en-US', { timeZone: 'Asia/Jakarta' }));
            const pad = (num) => String(num).padStart(2, '0');
            return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())} ${pad(now.getHours())}:${pad(now.getMinutes())}`;
        };

        const showToast = (type, message) => {
            if (typeof Swal !== 'undefined' && Swal.fire) {
                Swal.fire({
                    text: message,
                    icon: type,
                    buttonsStyling: false,
                    confirmButtonText: 'OK',
                    customClass: { confirmButton: 'btn btn-primary' }
                });
                return;
            }

            alert(message);
        };

        const setLookupState = (state, text, noticeHtml = '', noticeType = 'warning') => {
            lookupStatusBadge.className = 'badge';
            lookupWarningBox.classList.add('d-none');
            lookupWarningBox.innerHTML = '';
            lookupWarningBox.className = 'alert d-none mb-0';

            if (state === 'matched') {
                lookupStatusBadge.classList.add('badge-light-primary');
                lookupStatusBadge.textContent = 'Resi Ditemukan';
            } else if (state === 'manual') {
                lookupStatusBadge.classList.add('badge-light-warning');
                lookupStatusBadge.textContent = 'Input Manual';
            } else {
                lookupStatusBadge.classList.add('badge-light-secondary');
                lookupStatusBadge.textContent = 'Belum cari resi';
            }

            lookupStatusText.textContent = text;
            if (noticeHtml) {
                lookupWarningBox.classList.remove('d-none');
                lookupWarningBox.classList.add(noticeType === 'info' ? 'alert-light-info' : 'alert-light-warning');
                lookupWarningBox.innerHTML = noticeHtml;
            }
        };

        const escapeAttr = (value) => String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');

        const initSelect2 = (selectEl) => {
            if (selectEl && typeof $ !== 'undefined' && $.fn.select2) {
                $(selectEl).select2({
                    placeholder: 'Pilih item',
                    allowClear: true,
                    width: '100%',
                    minimumResultsForSearch: 0,
                });
            }
        };

        const destroySelect2 = (row) => {
            row.querySelectorAll('select').forEach((selectEl) => {
                if (typeof $ !== 'undefined' && $.fn.select2 && $(selectEl).data('select2')) {
                    $(selectEl).select2('destroy');
                }
            });
        };

        const renumberRows = () => {
            itemsContainer.querySelectorAll('.customer-return-item-row').forEach((row, index) => {
                row.querySelectorAll('[data-name]').forEach((el) => {
                    el.name = `items[${index}][${el.getAttribute('data-name')}]`;
                });
            });
        };

        const buildVarianceState = ({ expected, received }) => {
            if (expected <= 0 && received <= 0) {
                return {
                    badgeClass: 'badge-light-secondary',
                    badgeText: 'Belum diinspeksi',
                    hint: 'Isi jumlah diterima sesuai barang yang benar-benar ada di paket.',
                };
            }

            if (expected <= 0 && received > 0) {
                return {
                    badgeClass: 'badge-light-warning',
                    badgeText: 'SKU tambahan',
                    hint: 'SKU ini tidak ada di data resi, tetapi ditemukan di paket.',
                };
            }

            if (received === 0 && expected > 0) {
                return {
                    badgeClass: 'badge-light-danger',
                    badgeText: 'Ada di resi, tidak ada di paket',
                    hint: 'SKU ini tercatat di resi, tetapi tidak ditemukan di paket fisik.',
                };
            }

            if (received < expected) {
                return {
                    badgeClass: 'badge-light-warning',
                    badgeText: `Kurang ${expected - received}`,
                    hint: 'Jumlah fisik lebih sedikit dari data resi.',
                };
            }

            if (received > expected) {
                return {
                    badgeClass: 'badge-light-primary',
                    badgeText: `Lebih ${received - expected}`,
                    hint: 'Jumlah fisik lebih banyak dari data resi.',
                };
            }

            return {
                badgeClass: 'badge-light-success',
                badgeText: 'Sesuai resi',
                hint: 'Jumlah fisik sesuai dengan data resi.',
            };
        };

        const updateSummaryBadges = () => {
            const rows = Array.from(itemsContainer.querySelectorAll('.customer-return-item-row'));
            if (!summaryBadgesEl) {
                return;
            }

            if (!rows.length) {
                summaryBadgesEl.innerHTML = '<span class="badge badge-light-secondary">Belum ada inspeksi</span>';
                return;
            }

            const summary = rows.reduce((carry, row) => {
                const expected = Number(row.querySelector('[data-name="expected_qty"]')?.value || 0);
                const received = Number(row.querySelector('[data-name="received_qty"]')?.value || 0);
                const variance = buildVarianceState({ expected, received });
                carry.total += 1;
                if (variance.badgeText === 'Sesuai resi') carry.match += 1;
                if (variance.badgeText.startsWith('Kurang')) carry.less += 1;
                if (variance.badgeText.startsWith('Lebih')) carry.more += 1;
                if (variance.badgeText === 'SKU tambahan') carry.extra += 1;
                if (variance.badgeText === 'Ada di resi, tidak ada di paket') carry.missing += 1;
                return carry;
            }, { total: 0, match: 0, less: 0, more: 0, extra: 0, missing: 0 });

            const badges = [
                `<span class="badge badge-light-dark">Baris ${summary.total}</span>`,
                `<span class="badge badge-light-success">Sesuai ${summary.match}</span>`,
            ];
            if (summary.less > 0) badges.push(`<span class="badge badge-light-warning">Kurang ${summary.less}</span>`);
            if (summary.more > 0) badges.push(`<span class="badge badge-light-primary">Lebih ${summary.more}</span>`);
            if (summary.extra > 0) badges.push(`<span class="badge badge-light-info">SKU Tambahan ${summary.extra}</span>`);
            if (summary.missing > 0) badges.push(`<span class="badge badge-light-danger">Belum Diterima ${summary.missing}</span>`);

            summaryBadgesEl.innerHTML = badges.join('');
        };

        const updateRowValidation = (row) => {
            const received = Number(row.querySelector('[data-name="received_qty"]')?.value || 0);
            const good = Number(row.querySelector('[data-name="good_qty"]')?.value || 0);
            const damaged = Number(row.querySelector('[data-name="damaged_qty"]')?.value || 0);
            const expected = Number(row.querySelector('[data-name="expected_qty"]')?.value || 0);
            const errorEl = row.querySelector('[data-error-for="received_qty"]');
            const receivedEl = row.querySelector('[data-name="received_qty"]');
            const goodEl = row.querySelector('[data-name="good_qty"]');
            const damagedEl = row.querySelector('[data-name="damaged_qty"]');
            const expectedBadgeEl = row.querySelector('[data-role="expected-badge"]');
            const varianceBadgeEl = row.querySelector('[data-role="variance-badge"]');
            const varianceHintEl = row.querySelector('[data-role="variance-hint"]');
            const invalid = (good + damaged) !== received;
            const variance = buildVarianceState({ expected, received });

            [receivedEl, goodEl, damagedEl].forEach((el) => {
                if (!el) return;
                if (invalid) {
                    el.classList.add('is-invalid');
                } else if (!el.dataset.serverError) {
                    el.classList.remove('is-invalid');
                }
            });

            if (errorEl && !errorEl.dataset.serverError) {
                errorEl.textContent = invalid ? 'Jumlah bagus + jumlah rusak harus sama dengan jumlah diterima.' : '';
            }

            if (expectedBadgeEl) {
                expectedBadgeEl.textContent = `Qty Resi ${expected}`;
            }
            if (varianceBadgeEl) {
                varianceBadgeEl.className = `badge ${variance.badgeClass}`;
                varianceBadgeEl.textContent = variance.badgeText;
            }
            if (varianceHintEl) {
                varianceHintEl.textContent = variance.hint;
            }

            updateSummaryBadges();
            return !invalid;
        };

        const validateUniqueItems = () => {
            const rows = Array.from(itemsContainer.querySelectorAll('.customer-return-item-row'));
            const counts = {};
            rows.forEach((row) => {
                const value = row.querySelector('[data-name="item_id"]')?.value;
                if (value) counts[value] = (counts[value] || 0) + 1;
            });

            let valid = true;
            rows.forEach((row) => {
                const selectEl = row.querySelector('[data-name="item_id"]');
                const errorEl = row.querySelector('[data-error-for="item_id"]');
                const value = selectEl?.value;
                if (selectEl && value && counts[value] > 1) {
                    selectEl.classList.add('is-invalid');
                    if (errorEl) errorEl.textContent = 'Item tidak boleh duplikat.';
                    valid = false;
                } else if (errorEl && !errorEl.dataset.serverError) {
                    selectEl?.classList.remove('is-invalid');
                    errorEl.textContent = '';
                }
            });

            return valid;
        };

        const clearClientErrors = () => {
            itemsContainer.querySelectorAll('.customer-return-item-row').forEach((row) => {
                row.querySelectorAll('[data-error-for]').forEach((errorEl) => {
                    if (!errorEl.dataset.serverError) {
                        errorEl.textContent = '';
                    }
                });
                row.querySelectorAll('.is-invalid').forEach((field) => {
                    if (!field.dataset.serverError) {
                        field.classList.remove('is-invalid');
                    }
                });
            });
        };

        const bindRow = (row) => {
            const selectEl = row.querySelector('.customer-return-item-select');
            initSelect2(selectEl);

            row.querySelectorAll('[data-name="expected_qty"], [data-name="received_qty"], [data-name="good_qty"], [data-name="damaged_qty"]').forEach((input) => {
                input.addEventListener('input', () => {
                    if (input.dataset.serverError) {
                        input.dataset.serverError = '';
                    }
                    updateRowValidation(row);
                });
            });

            row.querySelector('.btn_remove_customer_return_item')?.addEventListener('click', () => {
                destroySelect2(row);
                row.remove();
                renumberRows();
                if (!itemsContainer.querySelector('.customer-return-item-row')) {
                    createItemRow();
                }
                updateSummaryBadges();
            });

            updateRowValidation(row);
        };

        const createItemRow = (data = {}) => {
            const row = document.createElement('div');
            row.className = 'row g-3 align-items-end mb-4 customer-return-item-row border border-dashed border-gray-300 rounded p-4';
            row.innerHTML = `
                <div class="col-12">
                    <div class="customer-return-item-state d-flex flex-wrap justify-content-between align-items-start gap-3">
                        <div>
                            <div class="fw-bold text-gray-800">Status inspeksi item</div>
                            <div class="text-muted fs-7" data-role="variance-hint">Isi jumlah diterima sesuai barang yang benar-benar ada di paket.</div>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge badge-light-primary" data-role="expected-badge">Qty Resi ${Number(data.expected_qty || 0)}</span>
                            <span class="badge badge-light-secondary" data-role="variance-badge">Belum diinspeksi</span>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-lg-5 col-md-12">
                    <label class="required fs-6 fw-bold form-label mb-2">Item</label>
                    <select class="form-select form-select-solid customer-return-item-select" data-name="item_id" required>
                        <option value=""></option>
                        ${customerReturnItemOptionsHtml}
                    </select>
                    <div class="invalid-feedback d-block" data-error-for="item_id"></div>
                </div>
                <div class="col-xl-2 col-lg-2 col-md-4 col-sm-6 col-6">
                    <label class="fs-6 fw-bold form-label mb-2">Qty Resi</label>
                    <input type="number" min="0" class="form-control form-control-solid" data-name="expected_qty" value="${Number(data.expected_qty || 0)}" />
                    <div class="invalid-feedback d-block" data-error-for="expected_qty"></div>
                </div>
                <div class="col-xl-2 col-lg-2 col-md-4 col-sm-6 col-6">
                    <label class="required fs-6 fw-bold form-label mb-2">Diterima</label>
                    <input type="number" min="0" class="form-control form-control-solid" data-name="received_qty" value="${Number(data.received_qty || 0)}" required />
                    <div class="invalid-feedback d-block" data-error-for="received_qty"></div>
                </div>
                <div class="col-xl-2 col-lg-1 col-md-4 col-sm-6 col-6">
                    <label class="required fs-6 fw-bold form-label mb-2">Bagus</label>
                    <input type="number" min="0" class="form-control form-control-solid" data-name="good_qty" value="${Number(data.good_qty || 0)}" required />
                    <div class="invalid-feedback d-block" data-error-for="good_qty"></div>
                </div>
                <div class="col-xl-2 col-lg-2 col-md-4 col-sm-6 col-6">
                    <label class="required fs-6 fw-bold form-label mb-2">Rusak</label>
                    <input type="number" min="0" class="form-control form-control-solid" data-name="damaged_qty" value="${Number(data.damaged_qty || 0)}" required />
                    <div class="invalid-feedback d-block" data-error-for="damaged_qty"></div>
                </div>
                <div class="col-xl-10 col-lg-9 col-md-8 col-12">
                    <label class="fs-6 fw-bold form-label mb-2">Catatan Item</label>
                    <input type="text" class="form-control form-control-solid" data-name="note" value="${escapeAttr(data.note || '')}" placeholder="Contoh: box rusak, segel terbuka, atau minus aksesoris" />
                    <div class="invalid-feedback d-block" data-error-for="note"></div>
                </div>
                <div class="col-xl-2 col-lg-3 col-md-4 col-12 text-end">
                    <button type="button" class="btn btn-light-danger btn_remove_customer_return_item w-100">Hapus</button>
                </div>
            `;

            itemsContainer.appendChild(row);
            renumberRows();
            const selectEl = row.querySelector('.customer-return-item-select');
            if (data.item_id) {
                selectEl.value = String(data.item_id);
            }
            bindRow(row);
            if (data.item_id && typeof $ !== 'undefined' && $.fn.select2) {
                $(selectEl).trigger('change');
            }
        };

        const applyLookupResult = (payload) => {
            resiIdEl.value = payload?.resi?.id || '';
            if (payload?.resi?.order_ref) {
                orderRefEl.value = payload.resi.order_ref;
            }

            itemsContainer.innerHTML = '';
            (payload.items || []).forEach((row) => createItemRow(row));
            if (!(payload.items || []).length) {
                createItemRow();
            }

            const guidanceLines = [
                '<strong>Jika isi paket tidak sama dengan data resi:</strong>',
                'Isi <strong>Qty Diterima</strong> sesuai barang fisik yang benar.',
                'Biarkan <strong>Qty Diterima = 0</strong> bila SKU dari resi ternyata tidak ada di paket.',
                'Gunakan <strong>Tambah Item Manual</strong> bila ada SKU tambahan di fisik paket.',
            ];
            let noticeHtml = guidanceLines.join('<br>');
            let noticeType = 'info';
            if ((payload.missing_skus || []).length) {
                const parts = payload.missing_skus.map((row) => `${row.sku} (${row.expected_qty})`);
                noticeHtml += `<hr class="my-3">SKU pada resi belum ada di master item: <strong>${parts.join(', ')}</strong>. Tambahkan item manual jika barang fisiknya memang diterima.`;
                noticeType = 'warning';
            }

            setLookupState('matched', `Resi ditemukan. Order ref: ${payload?.resi?.order_ref || '-'}. Lanjut isi jumlah diterima, bagus, dan rusak per item.`, noticeHtml, noticeType);
        };

        if (typeof flatpickr !== 'undefined' && receivedAtEl && !customerReturnReadOnly) {
            fpReceivedAt = flatpickr(receivedAtEl, {
                enableTime: true,
                dateFormat: 'Y-m-d H:i',
                allowInput: true,
                defaultDate: receivedAtEl.value || getJakartaNow(),
            });
        } else if (receivedAtEl && !receivedAtEl.value) {
            receivedAtEl.value = getJakartaNow();
        }

        itemsContainer.querySelectorAll('.customer-return-item-row').forEach((row) => {
            row.querySelectorAll('.is-invalid').forEach((field) => {
                field.dataset.serverError = '1';
            });
            row.querySelectorAll('[data-error-for]').forEach((errorEl) => {
                if ((errorEl.textContent || '').trim() !== '') {
                    errorEl.dataset.serverError = '1';
                }
            });
            bindRow(row);
        });

        if (customerReturnReadOnly) {
            updateSummaryBadges();
            return;
        }

        addItemBtn?.addEventListener('click', () => createItemRow());

        lookupBtn?.addEventListener('click', () => {
            const resiNo = (resiNoEl.value || '').trim();
            if (!resiNo) {
                setLookupState('manual', 'Nomor resi masih kosong.');
                resiNoEl.focus();
                return;
            }

            $.getJSON(customerReturnLookupUrl, { resi_no: resiNo })
                .done((response) => {
                    if (!response.matched) {
                        resiIdEl.value = '';
                        setLookupState('manual', 'Resi tidak ditemukan di database. Anda tetap bisa input inspeksi manual.');
                        if (!itemsContainer.querySelector('.customer-return-item-row')) {
                            createItemRow();
                        }
                        return;
                    }

                    applyLookupResult(response);
                })
                .fail((xhr) => {
                    const message = xhr.responseJSON?.message || xhr.responseJSON?.errors?.resi_no?.[0] || 'Gagal mencari data resi.';
                    setLookupState('manual', message);
                });
        });

        form?.addEventListener('submit', (event) => {
            clearClientErrors();
            const rows = Array.from(itemsContainer.querySelectorAll('.customer-return-item-row'));
            const rowsValid = rows.every((row) => updateRowValidation(row));
            const uniqueValid = validateUniqueItems();

            if (!rowsValid || !uniqueValid) {
                event.preventDefault();
                showToast('warning', 'Periksa kembali item retur. Jumlah bagus + jumlah rusak harus sama dengan jumlah diterima dan item tidak boleh duplikat.');
            }
        });

        if (resiIdEl.value) {
            setLookupState('matched', 'Retur ini terhubung ke data resi.');
        } else if ((resiNoEl.value || '').trim() !== '') {
            setLookupState('manual', 'Retur ini disimpan manual tanpa data resi yang cocok.');
        } else {
            setLookupState('idle', 'Input nomor resi lalu klik Cari Resi.');
        }
    });
</script>
@endpush
