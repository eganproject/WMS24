@extends('layouts.admin')

@section('title', 'Transfer Gudang')
@section('page_title', 'Transfer Gudang')

@php
    use App\Support\Permission as Perm;
    $canCreate = Perm::can(auth()->user(), 'admin.inventory.stock-transfers.index', 'create');
    $canUpdate = Perm::can(auth()->user(), 'admin.inventory.stock-transfers.index', 'update');
@endphp

@section('content')
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div class="d-flex align-items-center position-relative my-1">
                <span class="svg-icon svg-icon-1 position-absolute ms-6">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <rect opacity="0.5" x="17.0365" y="15.1223" width="8.15546" height="2" rx="1" transform="rotate(45 17.0365 15.1223)" fill="black" />
                        <path d="M11 19C6.55556 19 3 15.4444 3 11C3 6.55556 6.55556 3 11 3C15.4444 3 19 6.55556 19 11C19 15.4444 15.4444 19 11 19ZM11 5C7.53333 5 5 7.53333 5 11C5 14.4667 7.53333 17 11 17C14.4667 17 17 11 17 11C17 7.53333 14.4667 5 11 5Z" fill="black" />
                    </svg>
                </span>
                <input type="text" class="form-control form-control-solid w-250px ps-14" placeholder="Search" data-kt-filter="search" />
            </div>
        </div>
        <div class="card-toolbar">
            <div class="d-flex align-items-center gap-2 me-4">
                <input type="text" class="form-control form-control-solid w-150px" id="filter_date_from" placeholder="Dari" />
                <input type="text" class="form-control form-control-solid w-150px" id="filter_date_to" placeholder="Sampai" />
                <button type="button" class="btn btn-light" id="filter_apply">Filter</button>
                <button type="button" class="btn btn-light" id="filter_reset">Reset</button>
            </div>
            @if($canCreate)
                <button type="button" class="btn btn-primary" id="btn_open_transfer" data-bs-toggle="modal" data-bs-target="#modal_stock_transfer">Tambah</button>
            @endif
        </div>
    </div>
    <div class="card-body py-6">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5" id="stock_transfers_table">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                        <th>ID</th>
                        <th>Kode</th>
                        <th>Dari</th>
                        <th>Ke</th>
                        <th>Status</th>
                        <th>Tanggal</th>
                        <th>Submit By</th>
                        <th>Item</th>
                        <th>Qty</th>
                        <th>Catatan</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modal_stock_transfer" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-900px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bolder" id="transfer_modal_title">Tambah Transfer</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <span class="svg-icon svg-icon-1">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black" />
                            <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black" />
                        </svg>
                    </span>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <form class="form" id="stock_transfer_form">
                    @csrf
                    <div class="row g-3 mb-7">
                        <div class="col-md-6">
                            <label class="required fs-6 fw-bold form-label mb-2">Dari Gudang</label>
                            <select class="form-select form-select-solid" name="from_warehouse_id" id="transfer_from" required>
                                @foreach($warehouses as $wh)
                                    <option value="{{ $wh->id }}" @selected($wh->id == $defaultFrom)>{{ $wh->name }} ({{ $wh->code }})</option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback" id="error_from_warehouse_id"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="required fs-6 fw-bold form-label mb-2">Ke Gudang</label>
                            <select class="form-select form-select-solid" name="to_warehouse_id" id="transfer_to" required>
                                @foreach($warehouses as $wh)
                                    @if($wh->id != ($defaultWarehouseId ?? 0))
                                        <option value="{{ $wh->id }}" @selected($wh->id == $defaultTo)>{{ $wh->name }} ({{ $wh->code }})</option>
                                    @endif
                                @endforeach
                            </select>
                            <div class="invalid-feedback" id="error_to_warehouse_id"></div>
                        </div>
                    </div>

                    <div id="transfer_items_container"></div>
                    <div class="mb-7">
                        <button type="button" class="btn btn-light" id="btn_add_transfer_item">Tambah Item</button>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="required fs-6 fw-bold form-label mb-2">Tanggal</label>
                        <input type="text" class="form-control form-control-solid" name="transacted_at" id="transfer_transacted_at" placeholder="YYYY-MM-DD HH:mm" />
                        <div class="invalid-feedback" id="error_transacted_at"></div>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fs-6 fw-bold form-label mb-2">Catatan</label>
                        <textarea class="form-control form-control-solid" name="note" id="transfer_note" rows="3"></textarea>
                        <div class="invalid-feedback" id="error_note"></div>
                    </div>
                    <div class="text-end pt-3">
                        <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">
                            <span class="indicator-label">Simpan</span>
                            <span class="indicator-progress">Please wait...
                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal_qc_transfer" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-900px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bolder" id="qc_modal_title">QC Transfer</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <span class="svg-icon svg-icon-1">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black" />
                            <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black" />
                        </svg>
                    </span>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <form class="form" id="qc_transfer_form">
                    @csrf
                    <input type="hidden" id="qc_transfer_id" />
                    <div id="qc_items_container"></div>
                    <div class="text-end pt-3">
                        <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success">
                            <span class="indicator-label">Simpan QC</span>
                            <span class="indicator-progress">Please wait...
                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const dataUrl = '{{ $dataUrl }}';
    const storeUrl = '{{ $storeUrl }}';
    const showUrlTpl = '{{ $showUrlTpl }}';
    const detailUrlTpl = '{{ $detailUrlTpl }}';
    const scanKoliUrlTpl = '{{ $scanKoliUrlTpl }}';
    const qcUrlTpl = '{{ $qcUrlTpl }}';
    const cancelUrlTpl = '{{ $cancelUrlTpl }}';
    const csrfToken = '{{ csrf_token() }}';
    const canUpdate = {{ $canUpdate ? 'true' : 'false' }};
    const defaultWarehouseId = {{ (int) ($defaultWarehouseId ?? 0) }};
    const displayWarehouseId = {{ (int) ($displayWarehouseId ?? 0) }};
    const itemOptionsHtml = `@foreach($items as $item)<option value="{{ $item->id }}" data-sku="{{ $item->sku }}" data-name="{{ $item->name }}" data-koli-qty="{{ (int) ($item->koli_qty ?? 0) }}">{{ $item->sku }} - {{ $item->name }}</option>@endforeach`;

    document.addEventListener('DOMContentLoaded', () => {
        const tableEl = $('#stock_transfers_table');
        const searchInput = document.querySelector('[data-kt-filter="search"]');
        const form = document.getElementById('stock_transfer_form');
        const modalEl = document.getElementById('modal_stock_transfer');
        const modal = modalEl ? new bootstrap.Modal(modalEl) : null;
        const itemsContainer = document.getElementById('transfer_items_container');
        const addItemBtn = document.getElementById('btn_add_transfer_item');
        const openBtn = document.getElementById('btn_open_transfer');
        const modalTitle = document.getElementById('transfer_modal_title');
        const transactedAtEl = document.getElementById('transfer_transacted_at');
        const fromWarehouseEl = document.getElementById('transfer_from');
        const toWarehouseEl = document.getElementById('transfer_to');
        const noteEl = document.getElementById('transfer_note');
        const dateFromEl = document.getElementById('filter_date_from');
        const dateToEl = document.getElementById('filter_date_to');
        const filterApplyBtn = document.getElementById('filter_apply');
        const filterResetBtn = document.getElementById('filter_reset');
        const qcModalEl = document.getElementById('modal_qc_transfer');
        const qcModal = qcModalEl ? new bootstrap.Modal(qcModalEl) : null;
        const qcForm = document.getElementById('qc_transfer_form');
        const qcItemsContainer = document.getElementById('qc_items_container');
        const qcTransferIdEl = document.getElementById('qc_transfer_id');
        const qcTitleEl = document.getElementById('qc_modal_title');
        let fpFrom = null;
        let fpTo = null;
        let fpTransacted = null;
        let currentQcData = null;
        let isScanningKoli = false;

        const formatDateTime = (date) => {
            const pad = (n) => String(n).padStart(2, '0');
            return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`;
        };

        const getJakartaNow = () => {
            const jkt = new Date(new Date().toLocaleString('en-US', { timeZone: 'Asia/Jakarta' }));
            return formatDateTime(jkt);
        };

        const statusLabel = (status) => {
            if (status === 'completed') return '<span class="badge badge-light-success">Selesai</span>';
            if (status === 'canceled') return '<span class="badge badge-light-danger">Dibatalkan</span>';
            return '<span class="badge badge-light-warning">Menunggu QC</span>';
        };

        const escapeHtml = (value) => String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');

        const clearErrors = () => {
            ['error_from_warehouse_id','error_to_warehouse_id','error_transacted_at','error_note'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.textContent = '';
            });
            itemsContainer?.querySelectorAll('[data-error-for]')?.forEach(el => { el.textContent = ''; });
            itemsContainer?.querySelectorAll('.transfer-item-select.is-invalid')?.forEach(el => { el.classList.remove('is-invalid'); });
        };

        const validateUniqueItems = () => {
            if (!itemsContainer) return true;
            const rows = Array.from(itemsContainer.querySelectorAll('.transfer-item-row'));
            const counts = {};
            rows.forEach((row) => {
                const selectEl = row.querySelector('.transfer-item-select');
                const val = selectEl?.value;
                if (val) {
                    counts[val] = (counts[val] || 0) + 1;
                }
            });
            let hasDuplicate = false;
            rows.forEach((row) => {
                const selectEl = row.querySelector('.transfer-item-select');
                const val = selectEl?.value;
                const errEl = row.querySelector('[data-error-for="item_id"]');
                if (selectEl && val && counts[val] > 1) {
                    hasDuplicate = true;
                    if (errEl) errEl.textContent = 'Item tidak boleh duplikat';
                    selectEl.classList.add('is-invalid');
                } else {
                    if (errEl && errEl.textContent === 'Item tidak boleh duplikat') {
                        errEl.textContent = '';
                    }
                    selectEl?.classList.remove('is-invalid');
                }
            });
            return !hasDuplicate;
        };

        const requiresKoliTransfer = () => Number(fromWarehouseEl?.value || 0) === Number(defaultWarehouseId)
            && Number(toWarehouseEl?.value || 0) === Number(displayWarehouseId);

        const selectedKoliQty = (row) => {
            const selectEl = row?.querySelector('.transfer-item-select');
            const option = selectEl?.selectedOptions?.[0];
            const koliQty = parseInt(option?.getAttribute('data-koli-qty') || '0', 10);
            return Number.isFinite(koliQty) ? koliQty : 0;
        };

        const koliSubtext = (label) => label ? `<div class="text-muted fs-8">${label}</div>` : '<div class="text-muted fs-8">Isi/koli belum diset</div>';

        const syncRowKoliMode = (row) => {
            if (!row) return;
            const showKoli = requiresKoliTransfer();
            const koliWrap = row.querySelector('[data-role="koli-wrap"]');
            const koliEl = row.querySelector('input[data-name="koli"]');
            const qtyEl = row.querySelector('input[data-name="qty"]');
            const hintEl = row.querySelector('[data-role="koli-hint"]');
            const qtyPerKoli = selectedKoliQty(row);

            if (koliWrap) koliWrap.style.display = showKoli ? '' : 'none';
            if (qtyEl) qtyEl.readOnly = showKoli;
            if (hintEl) {
                hintEl.textContent = showKoli
                    ? (qtyPerKoli > 0 ? `Isi/koli: ${qtyPerKoli}. Qty otomatis dihitung dari koli.` : 'Isi/koli item belum diset.')
                    : '';
            }
            if (!showKoli) {
                if (koliEl) koliEl.value = '';
                return;
            }
            const koli = parseInt(koliEl?.value || '0', 10);
            if (qtyEl) qtyEl.value = qtyPerKoli > 0 && Number.isFinite(koli) && koli > 0 ? String(koli * qtyPerKoli) : '';
        };

        const syncAllKoliMode = () => {
            itemsContainer?.querySelectorAll('.transfer-item-row').forEach(syncRowKoliMode);
        };

        const syncQcAmounts = (row, changedField = 'ok') => {
            if (!row) return;
            const qtyTransfer = parseInt(row.getAttribute('data-qty-transfer') || '0', 10);
            const qtyPerKoli = parseInt(row.getAttribute('data-qty-per-koli') || '0', 10);
            const okEl = row.querySelector('[data-qc="ok"]');
            const rejectEl = row.querySelector('[data-qc="reject"]');
            const shortEl = row.querySelector('[data-qc="short"]');
            if (!okEl || !rejectEl || !shortEl) return;
            let okVal = parseInt(okEl.value || '0', 10);
            let rejectVal = parseInt(rejectEl.value || '0', 10);
            if (!Number.isFinite(okVal)) okVal = 0;
            if (!Number.isFinite(rejectVal)) rejectVal = 0;
            if (okVal < 0) okVal = 0;
            if (rejectVal < 0) rejectVal = 0;
            if (okVal > qtyTransfer) okVal = qtyTransfer;
            if (rejectVal > qtyTransfer) rejectVal = qtyTransfer;
            if (changedField === 'reject' && okVal + rejectVal > qtyTransfer) {
                okVal = Math.max(0, qtyTransfer - rejectVal);
            } else if (okVal + rejectVal > qtyTransfer) {
                rejectVal = Math.max(0, qtyTransfer - okVal);
            }
            okEl.value = okVal;
            rejectEl.value = rejectVal;
            const shortVal = Math.max(0, qtyTransfer - okVal - rejectVal);
            shortEl.value = shortVal;
            const formatKoli = (qty) => {
                if (!Number.isFinite(qtyPerKoli) || qtyPerKoli <= 0 || qty <= 0) return '';
                const koli = Math.floor(qty / qtyPerKoli);
                const sisa = qty % qtyPerKoli;
                return `${koli} koli${sisa > 0 ? ` + ${sisa} pcs` : ''} x ${qtyPerKoli}`;
            };
            const okKoliEl = row.querySelector('[data-role="qc-ok-koli"]');
            const rejectKoliEl = row.querySelector('[data-role="qc-reject-koli"]');
            const shortKoliEl = row.querySelector('[data-role="qc-short-koli"]');
            if (okKoliEl) okKoliEl.textContent = formatKoli(okVal);
            if (rejectKoliEl) rejectKoliEl.textContent = formatKoli(rejectVal);
            if (shortKoliEl) shortKoliEl.textContent = formatKoli(shortVal);
        };

        const syncScanAmounts = (row, changedField = 'ok') => {
            if (!row) return;
            const qty = parseInt(row.getAttribute('data-scan-qty') || '0', 10);
            const okEl = row.querySelector('[data-scan-qc="ok"]');
            const rejectEl = row.querySelector('[data-scan-qc="reject"]');
            const shortEl = row.querySelector('[data-scan-qc="short"]');
            if (!okEl || !rejectEl || !shortEl) return;
            let okVal = parseInt(okEl.value || '0', 10);
            let rejectVal = parseInt(rejectEl.value || '0', 10);
            if (!Number.isFinite(okVal)) okVal = 0;
            if (!Number.isFinite(rejectVal)) rejectVal = 0;
            okVal = Math.max(0, Math.min(qty, okVal));
            rejectVal = Math.max(0, Math.min(qty, rejectVal));
            if (changedField === 'reject' && okVal + rejectVal > qty) {
                okVal = Math.max(0, qty - rejectVal);
            } else if (okVal + rejectVal > qty) {
                rejectVal = Math.max(0, qty - okVal);
            }
            const shortVal = Math.max(0, qty - okVal - rejectVal);
            okEl.value = okVal;
            rejectEl.value = rejectVal;
            shortEl.value = shortVal;
            refreshScanAggregates();
        };

        const refreshScanAggregates = () => {
            const hidden = document.getElementById('qc_hidden_items');
            if (!hidden || !currentQcData) return;
            const aggregates = {};
            (currentQcData.items || []).forEach((item) => {
                aggregates[item.item_id] = { item_id: item.item_id, ok: 0, reject: 0, short: 0, note: '' };
            });
            qcItemsContainer?.querySelectorAll('[data-scan-row]').forEach((row) => {
                const itemId = row.getAttribute('data-item-id');
                if (!aggregates[itemId]) return;
                const ok = parseInt(row.querySelector('[data-scan-qc="ok"]')?.value || '0', 10);
                const reject = parseInt(row.querySelector('[data-scan-qc="reject"]')?.value || '0', 10);
                const short = parseInt(row.querySelector('[data-scan-qc="short"]')?.value || '0', 10);
                const note = row.querySelector('[data-scan-qc="note"]')?.value || '';
                aggregates[itemId].ok += Number.isFinite(ok) ? ok : 0;
                aggregates[itemId].reject += Number.isFinite(reject) ? reject : 0;
                aggregates[itemId].short += Number.isFinite(short) ? short : 0;
                if (!aggregates[itemId].note && note.trim()) aggregates[itemId].note = note.trim();
            });
            hidden.innerHTML = Object.values(aggregates).map((row, idx) => `
                <input type="hidden" name="items[${idx}][item_id]" value="${row.item_id}" />
                <input type="hidden" name="items[${idx}][qty_ok]" value="${row.ok}" />
                <input type="hidden" name="items[${idx}][qty_reject]" value="${row.reject}" />
                <input type="hidden" name="items[${idx}][qty_short]" value="${row.short}" />
                <input type="hidden" name="items[${idx}][qc_note]" value="${escapeHtml(row.note)}" />
            `).join('');
        };

        const currentScanFormValues = () => {
            const values = {};
            qcItemsContainer?.querySelectorAll('[data-scan-row]').forEach((row) => {
                const id = row.querySelector('input[name$="[id]"]')?.value;
                if (!id) return;
                values[id] = {
                    qty_ok: row.querySelector('[data-scan-qc="ok"]')?.value ?? '',
                    qty_reject: row.querySelector('[data-scan-qc="reject"]')?.value ?? '',
                    qty_short: row.querySelector('[data-scan-qc="short"]')?.value ?? '',
                    qc_note: row.querySelector('[data-scan-qc="note"]')?.value ?? '',
                };
            });
            return values;
        };

        const applyScanFormValues = (json, values) => {
            if (!json?.items || !values) return json;
            json.items.forEach((item) => {
                (item.scans || []).forEach((scan) => {
                    const saved = values[String(scan.id)];
                    if (!saved) return;
                    scan.qty_ok = saved.qty_ok;
                    scan.qty_reject = saved.qty_reject;
                    scan.qty_short = saved.qty_short;
                    scan.qc_note = saved.qc_note;
                });
            });
            return json;
        };

        const setPanelDisabled = (panel, disabled) => {
            panel?.querySelectorAll('input, textarea, select, button').forEach((el) => {
                el.disabled = disabled;
            });
        };

        const syncTraceabilityMode = () => {
            const mode = qcForm?.querySelector('input[name="traceability_mode"]:checked')?.value || 'qr';
            const qrPanel = document.getElementById('qc_qr_mode_panel');
            const legacyPanel = document.getElementById('qc_legacy_mode_panel');
            if (!qrPanel || !legacyPanel) return;
            const isLegacy = mode === 'legacy';
            qrPanel.style.display = isLegacy ? 'none' : '';
            legacyPanel.style.display = isLegacy ? '' : 'none';
            setPanelDisabled(qrPanel, isLegacy);
            setPanelDisabled(legacyPanel, !isLegacy);
            if (isLegacy) {
                document.getElementById('qc_legacy_reason')?.focus();
            } else {
                document.getElementById('qc_koli_scan_code')?.focus();
            }
        };

        const renderQcForm = (json) => {
            currentQcData = json;
            qcTransferIdEl.value = json.id || '';
            if (qcTitleEl) qcTitleEl.textContent = `QC Transfer ${json.code || ''}`.trim();
            qcItemsContainer.innerHTML = '';

            if (json.requires_koli_scan) {
                qcItemsContainer.innerHTML = `
                    <div class="mb-6 p-4 border rounded bg-light">
                        <label class="required fs-6 fw-bold form-label mb-3">Mode Traceability</label>
                        <div class="d-flex flex-wrap gap-4">
                            <label class="form-check form-check-custom form-check-solid">
                                <input class="form-check-input" type="radio" name="traceability_mode" value="qr" checked />
                                <span class="form-check-label fw-semibold">Scan QR Dus</span>
                            </label>
                            <label class="form-check form-check-custom form-check-solid">
                                <input class="form-check-input" type="radio" name="traceability_mode" value="legacy" />
                                <span class="form-check-label fw-semibold">Tanpa QR / Stok Lama</span>
                            </label>
                        </div>
                        <div class="form-text text-muted">Pilih Tanpa QR hanya untuk stok lama yang belum ditempel QR inbound.</div>
                    </div>
                    <div id="qc_qr_mode_panel">
                        <div class="mb-6 p-4 border rounded bg-light">
                            <label class="required fs-6 fw-bold form-label mb-2">Scan QR Dus Inbound</label>
                            <div class="d-flex gap-2">
                                <input type="text" class="form-control form-control-solid" id="qc_koli_scan_code" placeholder="Scan / input kode QR dus inbound" autocomplete="off" />
                                <button type="button" class="btn btn-primary" id="btn_qc_scan_koli">Scan</button>
                            </div>
                            <div class="form-text text-muted">Semua dus wajib discan sebelum QC disimpan.</div>
                        </div>
                        <div id="qc_hidden_items"></div>
                        <div id="qc_scan_items"></div>
                    </div>
                    <div id="qc_legacy_mode_panel" style="display:none;">
                        <div class="alert alert-warning">
                            Stok lama tanpa QR tidak bisa ditelusuri ke inbound asal. Alasan wajib diisi agar audit tetap jelas.
                        </div>
                        <div class="mb-6">
                            <label class="required fs-6 fw-bold form-label mb-2">Alasan Tanpa QR</label>
                            <textarea class="form-control form-control-solid" name="legacy_reason" id="qc_legacy_reason" rows="2" placeholder="Contoh: Stok lama sebelum QR inbound diterapkan"></textarea>
                        </div>
                        <div id="qc_legacy_items"></div>
                    </div>
                `;
                const scanItemsEl = document.getElementById('qc_scan_items');
                let scanIndex = 0;
                (json.items || []).forEach((item) => {
                    const scans = item.scans || [];
                    const scannedQty = scans.reduce((sum, scan) => sum + Number(scan.qty || 0), 0);
                    const remainingQty = Math.max(0, Number(item.qty || 0) - scannedQty);
                    const itemBlock = document.createElement('div');
                    itemBlock.className = 'mb-7';
                    itemBlock.innerHTML = `
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <div class="fw-bold">${escapeHtml(item.label || '-')}</div>
                                <div class="text-muted fs-8">Transfer ${item.qty} pcs${item.koli_label ? ` | ${escapeHtml(item.koli_label)}` : ''}</div>
                            </div>
                            <span class="badge ${remainingQty === 0 ? 'badge-light-success' : 'badge-light-warning'}">Scan ${scannedQty}/${item.qty}</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-row-dashed align-middle fs-7 gy-3">
                                <thead>
                                    <tr class="text-muted fw-bold">
                                        <th>QR Dus</th>
                                        <th>Inbound</th>
                                        <th class="text-end">Qty Dus</th>
                                        <th class="text-end">OK</th>
                                        <th class="text-end">Reject</th>
                                        <th class="text-end">Kurang</th>
                                        <th>Catatan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${scans.length ? scans.map((scan) => {
                                        const idx = scanIndex++;
                                        return `
                                            <tr data-scan-row data-scan-qty="${scan.qty}" data-item-id="${item.item_id}">
                                                <td>
                                                    <div class="fw-semibold">${escapeHtml(scan.code)}</div>
                                                    <div class="text-muted fs-8">Koli ${scan.koli_no || '-'}</div>
                                                    <input type="hidden" name="scans[${idx}][id]" value="${scan.id}" />
                                                </td>
                                                <td>${escapeHtml(scan.inbound_code || '-')}</td>
                                                <td class="text-end">${scan.qty}</td>
                                                <td><input type="number" min="0" class="form-control form-control-solid form-control-sm text-end" data-scan-qc="ok" name="scans[${idx}][qty_ok]" value="${scan.qty_ok ?? scan.qty}" /></td>
                                                <td><input type="number" min="0" class="form-control form-control-solid form-control-sm text-end" data-scan-qc="reject" name="scans[${idx}][qty_reject]" value="${scan.qty_reject ?? 0}" /></td>
                                                <td><input type="number" min="0" class="form-control form-control-solid form-control-sm text-end" data-scan-qc="short" name="scans[${idx}][qty_short]" value="${scan.qty_short ?? 0}" readonly /></td>
                                                <td><input type="text" class="form-control form-control-solid form-control-sm" data-scan-qc="note" name="scans[${idx}][qc_note]" value="${escapeHtml(scan.qc_note || '')}" /></td>
                                            </tr>
                                        `;
                                    }).join('') : '<tr><td colspan="7" class="text-muted text-center py-6">Belum ada QR dus yang discan untuk SKU ini.</td></tr>'}
                                </tbody>
                            </table>
                        </div>
                    `;
                    scanItemsEl?.appendChild(itemBlock);
                });
                const legacyItemsEl = document.getElementById('qc_legacy_items');
                (json.items || []).forEach((item, idx) => {
                    const row = document.createElement('div');
                    row.className = 'row g-3 align-items-end mb-4';
                    row.setAttribute('data-qty-transfer', String(item.qty ?? 0));
                    row.setAttribute('data-qty-per-koli', String(item.qty_per_koli ?? 0));
                    row.innerHTML = `
                        <div class="col-md-3">
                            <label class="fs-6 fw-bold form-label mb-2">Item</label>
                            <div class="form-control form-control-solid">${escapeHtml(item.label || '-')}</div>
                            <input type="hidden" name="items[${idx}][item_id]" value="${item.item_id}" />
                        </div>
                        <div class="col-md-2">
                            <label class="fs-6 fw-bold form-label mb-2">Qty Transfer</label>
                            <div class="form-control form-control-solid">${item.qty}${koliSubtext(item.koli_label)}</div>
                        </div>
                        <div class="col-md-2">
                            <label class="required fs-6 fw-bold form-label mb-2">Qty OK</label>
                            <input type="number" min="0" class="form-control form-control-solid" data-qc="ok" name="items[${idx}][qty_ok]" value="${(item.qty_ok && item.qty_ok > 0) ? item.qty_ok : item.qty}" />
                            <div class="form-text text-muted" data-role="qc-ok-koli">${item.qty_ok_koli_label || item.koli_label || ''}</div>
                        </div>
                        <div class="col-md-2">
                            <label class="required fs-6 fw-bold form-label mb-2">Qty Reject</label>
                            <input type="number" min="0" class="form-control form-control-solid" data-qc="reject" name="items[${idx}][qty_reject]" value="${item.qty_reject ?? 0}" />
                            <div class="form-text text-muted" data-role="qc-reject-koli">${item.qty_reject_koli_label || ''}</div>
                        </div>
                        <div class="col-md-2">
                            <label class="required fs-6 fw-bold form-label mb-2">Qty Kurang</label>
                            <input type="number" min="0" class="form-control form-control-solid" data-qc="short" name="items[${idx}][qty_short]" value="${item.qty_short ?? 0}" readonly />
                            <div class="form-text text-muted" data-role="qc-short-koli">${item.qty_short_koli_label || ''}</div>
                        </div>
                        <div class="col-md-1">
                            <label class="fs-6 fw-bold form-label mb-2">Catatan</label>
                            <input type="text" class="form-control form-control-solid" name="items[${idx}][qc_note]" value="${escapeHtml(item.qc_note ?? '')}" />
                        </div>
                    `;
                    legacyItemsEl?.appendChild(row);
                    syncQcAmounts(row);
                });
                qcItemsContainer.querySelectorAll('[data-scan-row]').forEach((row) => syncScanAmounts(row));
                refreshScanAggregates();
                syncTraceabilityMode();
                document.getElementById('qc_koli_scan_code')?.focus();
                return;
            }

            (json.items || []).forEach((item, idx) => {
                const row = document.createElement('div');
                row.className = 'row g-3 align-items-end mb-4';
                row.setAttribute('data-qty-transfer', String(item.qty ?? 0));
                row.setAttribute('data-qty-per-koli', String(item.qty_per_koli ?? 0));
                row.innerHTML = `
                    <div class="col-md-3">
                        <label class="fs-6 fw-bold form-label mb-2">Item</label>
                        <div class="form-control form-control-solid">${escapeHtml(item.label || '-')}</div>
                        <input type="hidden" name="items[${idx}][item_id]" value="${item.item_id}" />
                    </div>
                    <div class="col-md-2">
                        <label class="fs-6 fw-bold form-label mb-2">Qty Transfer</label>
                        <div class="form-control form-control-solid">${item.qty}${koliSubtext(item.koli_label)}</div>
                    </div>
                    <div class="col-md-2">
                        <label class="required fs-6 fw-bold form-label mb-2">Qty OK</label>
                        <input type="number" min="0" class="form-control form-control-solid" data-qc="ok" name="items[${idx}][qty_ok]" value="${(item.qty_ok && item.qty_ok > 0) ? item.qty_ok : item.qty}" />
                        <div class="form-text text-muted" data-role="qc-ok-koli">${item.qty_ok_koli_label || item.koli_label || ''}</div>
                    </div>
                    <div class="col-md-2">
                        <label class="required fs-6 fw-bold form-label mb-2">Qty Reject</label>
                        <input type="number" min="0" class="form-control form-control-solid" data-qc="reject" name="items[${idx}][qty_reject]" value="${item.qty_reject ?? 0}" />
                        <div class="form-text text-muted" data-role="qc-reject-koli">${item.qty_reject_koli_label || ''}</div>
                    </div>
                    <div class="col-md-2">
                        <label class="required fs-6 fw-bold form-label mb-2">Qty Kurang</label>
                        <input type="number" min="0" class="form-control form-control-solid" data-qc="short" name="items[${idx}][qty_short]" value="${item.qty_short ?? 0}" readonly />
                        <div class="form-text text-muted" data-role="qc-short-koli">${item.qty_short_koli_label || ''}</div>
                    </div>
                    <div class="col-md-1">
                        <label class="fs-6 fw-bold form-label mb-2">Catatan</label>
                        <input type="text" class="form-control form-control-solid" name="items[${idx}][qc_note]" value="${escapeHtml(item.qc_note ?? '')}" />
                    </div>
                `;
                qcItemsContainer.appendChild(row);
                syncQcAmounts(row);
            });
        };

        const initSelect2 = (selectEl) => {
            if (selectEl && typeof $ !== 'undefined' && $.fn.select2) {
                $(selectEl).select2({
                    placeholder: 'Pilih item',
                    allowClear: true,
                    width: '100%',
                    dropdownParent: modalEl,
                    minimumResultsForSearch: 0,
                })
                    .on('select2:opening select2:closing select2:close', function(e){ e.stopPropagation(); });
            }
        };

        if (typeof flatpickr !== 'undefined') {
            if (dateFromEl) {
                fpFrom = flatpickr(dateFromEl, { dateFormat: 'Y-m-d', allowInput: true });
            }
            if (dateToEl) {
                fpTo = flatpickr(dateToEl, { dateFormat: 'Y-m-d', allowInput: true });
            }
            if (transactedAtEl) {
                fpTransacted = flatpickr(transactedAtEl, { enableTime: true, dateFormat: 'Y-m-d H:i', allowInput: true });
            }
        }

        const renumberRows = () => {
            const rows = itemsContainer.querySelectorAll('.transfer-item-row');
            rows.forEach((row, idx) => {
                row.querySelectorAll('[data-name]')?.forEach((el) => {
                    const key = el.getAttribute('data-name');
                    el.name = `items[${idx}][${key}]`;
                });
            });
        };

        const createItemRow = (data = {}) => {
            const row = document.createElement('div');
            row.className = 'row g-3 align-items-end mb-4 transfer-item-row';
            row.innerHTML = `
                <div class="col-md-5">
                    <label class="required fs-6 fw-bold form-label mb-2">Item</label>
                    <select class="form-select form-select-solid transfer-item-select" data-name="item_id" required>
                        <option value=""></option>
                        ${itemOptionsHtml}
                    </select>
                    <div class="invalid-feedback" data-error-for="item_id"></div>
                </div>
                <div class="col-md-2" data-role="koli-wrap" style="display:none;">
                    <label class="required fs-6 fw-bold form-label mb-2">Koli</label>
                    <input type="number" min="1" step="1" class="form-control form-control-solid" data-name="koli" />
                    <div class="form-text text-muted" data-role="koli-hint"></div>
                    <div class="invalid-feedback" data-error-for="koli"></div>
                </div>
                <div class="col-md-2">
                    <label class="required fs-6 fw-bold form-label mb-2">Qty</label>
                    <input type="number" min="1" class="form-control form-control-solid" data-name="qty" required />
                    <div class="invalid-feedback" data-error-for="qty"></div>
                </div>
                <div class="col-md-3">
                    <label class="fs-6 fw-bold form-label mb-2">Catatan Item</label>
                    <input type="text" class="form-control form-control-solid" data-name="note" />
                </div>
                <div class="col-md-1 text-end">
                    <button type="button" class="btn btn-light btn-sm btn-remove-item">Hapus</button>
                </div>
            `;
            itemsContainer.appendChild(row);

            const selectEl = row.querySelector('.transfer-item-select');
            const qtyEl = row.querySelector('input[data-name="qty"]');
            if (qtyEl) qtyEl.value = data.qty ?? '';
            const koliEl = row.querySelector('input[data-name="koli"]');
            if (koliEl) koliEl.value = data.koli ?? '';
            const noteEl = row.querySelector('input[data-name="note"]');
            if (noteEl) noteEl.value = data.note ?? '';

            initSelect2(selectEl);
            if (data.item_id) {
                if (typeof $ !== 'undefined' && $.fn.select2) {
                    $(selectEl).val(String(data.item_id)).trigger('change');
                } else {
                    selectEl.value = String(data.item_id);
                }
            }
            renumberRows();
            syncRowKoliMode(row);
            validateUniqueItems();
        };

        const resetForm = () => {
            form?.reset();
            if (modalTitle) modalTitle.textContent = 'Tambah Transfer';
            const nowJkt = getJakartaNow();
            if (fpTransacted) {
                fpTransacted.setDate(nowJkt, true, 'Y-m-d H:i');
            } else if (transactedAtEl) {
                transactedAtEl.value = nowJkt;
            }
            itemsContainer.innerHTML = '';
            createItemRow();
            clearErrors();
            validateUniqueItems();
            syncAllKoliMode();
        };

        const openPrefill = (prefill) => {
            form?.reset();
            if (modalTitle) modalTitle.textContent = 'Tambah Transfer';
            const nowJkt = getJakartaNow();
            if (fpTransacted) {
                fpTransacted.setDate(nowJkt, true, 'Y-m-d H:i');
            } else if (transactedAtEl) {
                transactedAtEl.value = nowJkt;
            }
            if (fromWarehouseEl && prefill?.from_warehouse_id) {
                fromWarehouseEl.value = String(prefill.from_warehouse_id);
            }
            if (toWarehouseEl && prefill?.to_warehouse_id) {
                toWarehouseEl.value = String(prefill.to_warehouse_id);
            }
            if (noteEl && prefill?.note) {
                noteEl.value = prefill.note;
            }
            itemsContainer.innerHTML = '';
            createItemRow({
                item_id: prefill?.item_id || '',
                qty: prefill?.qty ?? '',
            });
            clearErrors();
            validateUniqueItems();
            syncAllKoliMode();
            modal?.show();
        };

        addItemBtn?.addEventListener('click', () => createItemRow());
        openBtn?.addEventListener('click', resetForm);

        const params = new URLSearchParams(window.location.search);
        const prefillItemId = params.get('item_id');
        if (prefillItemId) {
            const qty = parseInt(params.get('qty') || '0', 10);
            openPrefill({
                item_id: parseInt(prefillItemId, 10),
                qty: Number.isFinite(qty) && qty > 0 ? qty : '',
                from_warehouse_id: params.get('from') || '',
                to_warehouse_id: params.get('to') || '',
                note: params.get('note') || '',
            });
        }

        itemsContainer?.addEventListener('change', (e) => {
            if (e.target.matches('.transfer-item-select')) {
                syncRowKoliMode(e.target.closest('.transfer-item-row'));
                validateUniqueItems();
            }
        });
        itemsContainer?.addEventListener('input', (e) => {
            if (e.target.matches('input[data-name="koli"]')) {
                syncRowKoliMode(e.target.closest('.transfer-item-row'));
            }
        });
        fromWarehouseEl?.addEventListener('change', syncAllKoliMode);
        toWarehouseEl?.addEventListener('change', syncAllKoliMode);

        itemsContainer?.addEventListener('click', (e) => {
            const btn = e.target.closest('.btn-remove-item');
            if (!btn) return;
            const row = btn.closest('.transfer-item-row');
            if (row) row.remove();
            if (itemsContainer.querySelectorAll('.transfer-item-row').length === 0) {
                createItemRow();
            } else {
                renumberRows();
            }
            validateUniqueItems();
        });

        if (!tableEl.length || !$.fn.DataTable) {
            console.error('DataTables unavailable');
            return;
        }

        const refreshMenus = () => { if (window.KTMenu) KTMenu.createInstances(); };

        const dt = tableEl.DataTable({
            processing: true,
            serverSide: true,
            dom: 'rtip',
            order: [[0, 'desc']],
            ajax: {
                url: dataUrl,
                dataSrc: 'data',
                data: function(params) {
                    params.q = searchInput?.value || '';
                    if (dateFromEl?.value) params.date_from = dateFromEl.value;
                    if (dateToEl?.value) params.date_to = dateToEl.value;
                }
            },
            columns: [
                { data: 'id' },
                { data: 'code' },
                { data: 'from' },
                { data: 'to' },
                { data: 'status', orderable:false, searchable:false, render: (data) => statusLabel(data) },
                { data: 'transacted_at' },
                { data: 'submit_by' },
                { data: 'item' },
                { data: 'qty' },
                { data: 'note' },
                { data: 'id', orderable:false, searchable:false, className:'text-end', render: (data, type, row)=>{
                    const detailItem = `<div class="menu-item px-3"><a href="${detailUrlTpl.replace(':id', data)}" class="menu-link px-3">Detail</a></div>`;
                    const qcItem = (row?.status === 'qc_pending' && canUpdate)
                        ? `<div class="menu-item px-3"><a href="#" class="menu-link px-3 text-success btn-qc" data-id="${data}">QC</a></div>`
                        : '';
                    const cancelItem = (row?.status === 'qc_pending' && canUpdate)
                        ? `<div class="menu-item px-3"><a href="#" class="menu-link px-3 text-danger btn-cancel-transfer" data-id="${data}" data-code="${row.code}">Batalkan</a></div>`
                        : '';
                    const actions = `${detailItem}${qcItem}${cancelItem}`;
                    if (!actions) return '';
                    return `
                        <div class="text-end">
                            <a href="#" class="btn btn-sm btn-light btn-active-light-primary" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                Actions
                                <span class="svg-icon svg-icon-5 m-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                        <path d="M11.4343 12.7344L7.25 8.55005C6.83579 8.13583 6.16421 8.13584 5.75 8.55005C5.33579 8.96426 5.33579 9.63583 5.75 10.05L11.2929 15.5929C11.6834 15.9835 12.3166 15.9835 12.7071 15.5929L18.25 10.05C18.6642 9.63584 18.6642 8.96426 18.25 8.55005C17.8358 8.13584 17.1642 8.13584 16.75 8.55005L12.5657 12.7344C12.2533 13.0468 11.7467 13.0468 11.4343 12.7344Z" fill="black"></path>
                                    </svg>
                                </span>
                            </a>
                            <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-600 menu-state-bg-light-primary fw-bold fs-7 w-175px py-3" data-kt-menu="true">
                                ${actions}
                            </div>
                        </div>
                    `;
                }}
            ]
        });
        refreshMenus();
        dt.on('draw', refreshMenus);

        const reloadTable = () => dt.ajax.reload();
        searchInput?.addEventListener('keyup', reloadTable);
        filterApplyBtn?.addEventListener('click', reloadTable);
        filterResetBtn?.addEventListener('click', () => {
            if (fpFrom) fpFrom.clear(); else if (dateFromEl) dateFromEl.value = '';
            if (fpTo) fpTo.clear(); else if (dateToEl) dateToEl.value = '';
            reloadTable();
        });

        form?.addEventListener('submit', async (e) => {
            e.preventDefault();
            clearErrors();
            if (!validateUniqueItems()) {
                if (typeof Swal !== 'undefined') Swal.fire('Error', 'Item tidak boleh duplikat', 'error');
                return;
            }
            const formData = new FormData(form);
            try {
                const res = await fetch(storeUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: formData,
                });
                const text = await res.text();
                let json;
                try { json = JSON.parse(text); } catch (err) {
                    if (typeof Swal !== 'undefined') Swal.fire('Error', 'Respons server tidak valid', 'error');
                    return;
                }
                if (!res.ok) {
                    if (json?.errors) {
                        const unhandled = [];
                        Object.entries(json.errors).forEach(([key, msgs]) => {
                            if (key.startsWith('items.')) {
                                const parts = key.split('.');
                                const idx = parseInt(parts[1], 10);
                                const field = parts[2];
                                const row = itemsContainer.querySelectorAll('.transfer-item-row')[idx];
                                const errEl = row ? row.querySelector(`[data-error-for="${field}"]`) : null;
                                if (errEl) errEl.textContent = msgs.join(', ');
                                else unhandled.push(msgs.join(', '));
                            } else {
                                const errEl = document.getElementById(`error_${key}`);
                                if (errEl) errEl.textContent = msgs.join(', ');
                                else unhandled.push(msgs.join(', '));
                            }
                        });
                        if (unhandled.length && typeof Swal !== 'undefined') {
                            Swal.fire('Error', unhandled.join(', '), 'error');
                        }
                    } else if (typeof Swal !== 'undefined') {
                        Swal.fire('Error', json.message || 'Gagal menyimpan', 'error');
                    }
                    return;
                }
                if (typeof Swal !== 'undefined') Swal.fire('Berhasil', json.message || 'Berhasil', 'success');
                modal?.hide();
                reloadTable();
            } catch (err) {
                if (typeof Swal !== 'undefined') Swal.fire('Error', 'Gagal menyimpan', 'error');
            }
        });

        tableEl.on('click', '.btn-qc', async function(e) {
            e.preventDefault();
            const id = this.getAttribute('data-id');
            if (!id) return;
            try {
                const res = await fetch(showUrlTpl.replace(':id', id), { headers: { 'Accept': 'application/json' }});
                const json = await res.json();
                if (!res.ok) {
                    if (typeof Swal !== 'undefined') Swal.fire('Error', json.message || 'Gagal memuat data', 'error');
                    return;
                }
                renderQcForm(json);
                if (typeof Swal !== 'undefined') Swal.close();
                qcModal?.show();
            } catch (err) {
                if (typeof Swal !== 'undefined') Swal.fire('Error', 'Gagal memuat data', 'error');
            }
        });

        tableEl.on('click', '.btn-cancel-transfer', async function(e) {
            e.preventDefault();
            const id = this.getAttribute('data-id');
            const code = this.getAttribute('data-code') || '';
            if (!id) return;

            let reason = '';
            let confirmed = true;

            if (typeof Swal !== 'undefined') {
                const result = await Swal.fire({
                    title: `Batalkan transfer ${code || ''}`.trim(),
                    text: 'Transfer akan dikembalikan ke gudang asal dan tidak bisa di-QC.',
                    input: 'textarea',
                    inputLabel: 'Alasan pembatalan',
                    inputPlaceholder: 'Opsional',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, batalkan',
                    cancelButtonText: 'Batal',
                    buttonsStyling: false,
                    customClass: {
                        confirmButton: 'btn btn-danger',
                        cancelButton: 'btn btn-light'
                    }
                });
                confirmed = result.isConfirmed;
                reason = (result.value || '').trim();
            }

            if (!confirmed) return;

            const formData = new FormData();
            if (reason !== '') {
                formData.append('reason', reason);
            }

            try {
                const res = await fetch(cancelUrlTpl.replace(':id', id), {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: formData,
                });
                const text = await res.text();
                let json;
                try { json = JSON.parse(text); } catch (err) {
                    if (typeof Swal !== 'undefined') Swal.fire('Error', 'Respons server tidak valid', 'error');
                    return;
                }

                if (!res.ok) {
                    if (typeof Swal !== 'undefined') Swal.fire('Error', json.message || 'Gagal membatalkan transfer', 'error');
                    return;
                }

                if (typeof Swal !== 'undefined') Swal.fire('Berhasil', json.message || 'Transfer dibatalkan', 'success');
                reloadTable();
            } catch (err) {
                if (typeof Swal !== 'undefined') Swal.fire('Error', 'Gagal membatalkan transfer', 'error');
            }
        });

        qcForm?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const id = qcTransferIdEl.value;
            if (!id) return;
            const traceabilityMode = qcForm?.querySelector('input[name="traceability_mode"]:checked')?.value || 'qr';
            if (currentQcData?.requires_koli_scan && traceabilityMode === 'qr') {
                const incomplete = (currentQcData.items || []).some((item) => {
                    const scannedQty = (item.scans || []).reduce((sum, scan) => sum + Number(scan.qty || 0), 0);
                    return scannedQty !== Number(item.qty || 0);
                });
                if (incomplete) {
                    if (typeof Swal !== 'undefined') Swal.fire('Error', 'Semua QR dus wajib discan sebelum QC disimpan.', 'error');
                    return;
                }
                refreshScanAggregates();
            }
            if (currentQcData?.requires_koli_scan && traceabilityMode === 'legacy') {
                const reason = (document.getElementById('qc_legacy_reason')?.value || '').trim();
                if (!reason) {
                    if (typeof Swal !== 'undefined') Swal.fire('Error', 'Alasan wajib diisi untuk QC tanpa QR inbound.', 'error');
                    document.getElementById('qc_legacy_reason')?.focus();
                    return;
                }
            }
            const formData = new FormData(qcForm);
            try {
                const res = await fetch(qcUrlTpl.replace(':id', id), {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: formData,
                });
                const text = await res.text();
                let json;
                try { json = JSON.parse(text); } catch (err) {
                    if (typeof Swal !== 'undefined') Swal.fire('Error', 'Respons server tidak valid', 'error');
                    return;
                }
                if (!res.ok) {
                    const details = json?.errors
                        ? Object.values(json.errors).flat().join('<br>')
                        : '';
                    const message = details || json.message || json.error || 'Gagal QC';
                    if (typeof Swal !== 'undefined') Swal.fire('Error', message, 'error');
                    return;
                }
                if (typeof Swal !== 'undefined') Swal.fire('Berhasil', json.message || 'Berhasil', 'success');
                qcModal?.hide();
                reloadTable();
            } catch (err) {
                if (typeof Swal !== 'undefined') Swal.fire('Error', 'Gagal QC', 'error');
            }
        });

        qcItemsContainer?.addEventListener('input', (e) => {
            const target = e.target;
            if (!(target instanceof HTMLElement)) return;
            if (target.matches('[data-qc="ok"], [data-qc="reject"]')) {
                const row = target.closest('[data-qty-transfer]');
                syncQcAmounts(row, target.getAttribute('data-qc') || 'ok');
            }
            if (target.matches('[data-scan-qc="ok"], [data-scan-qc="reject"], [data-scan-qc="note"]')) {
                const row = target.closest('[data-scan-row]');
                if (target.matches('[data-scan-qc="note"]')) {
                    refreshScanAggregates();
                } else {
                    syncScanAmounts(row, target.getAttribute('data-scan-qc') || 'ok');
                }
            }
        });

        qcItemsContainer?.addEventListener('change', (e) => {
            const target = e.target;
            if (!(target instanceof HTMLElement)) return;
            if (target.matches('input[name="traceability_mode"]')) {
                syncTraceabilityMode();
            }
        });

        qcItemsContainer?.addEventListener('keydown', (e) => {
            const target = e.target;
            if (!(target instanceof HTMLElement)) return;
            if (target.id === 'qc_koli_scan_code' && e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('btn_qc_scan_koli')?.click();
            }
        });

        qcItemsContainer?.addEventListener('click', async (e) => {
            const btn = e.target.closest('#btn_qc_scan_koli');
            if (!btn || isScanningKoli) return;
            const id = qcTransferIdEl.value;
            const input = document.getElementById('qc_koli_scan_code');
            const code = (input?.value || '').trim();
            if (!id || !code) return;
            isScanningKoli = true;
            btn.setAttribute('disabled', 'disabled');
            try {
                const previousValues = currentScanFormValues();
                const formData = new FormData();
                formData.append('code', code);
                const res = await fetch(scanKoliUrlTpl.replace(':id', id), {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: formData,
                });
                const json = await res.json();
                if (!res.ok) {
                    if (typeof Swal !== 'undefined') Swal.fire('Error', json.message || 'Gagal scan QR dus', 'error');
                    return;
                }
                const detailRes = await fetch(showUrlTpl.replace(':id', id), { headers: { 'Accept': 'application/json' }});
                const detailJson = await detailRes.json();
                if (!detailRes.ok) {
                    if (typeof Swal !== 'undefined') Swal.fire('Error', detailJson.message || 'Gagal memuat ulang data QC', 'error');
                    return;
                }
                renderQcForm(applyScanFormValues(detailJson, previousValues));
                if (typeof Swal !== 'undefined') Swal.fire({ title: 'Berhasil', text: json.message || 'QR dus discan', icon: 'success', timer: 900, showConfirmButton: false });
            } catch (err) {
                if (typeof Swal !== 'undefined') Swal.fire('Error', 'Gagal scan QR dus', 'error');
            } finally {
                isScanningKoli = false;
                btn.removeAttribute('disabled');
                document.getElementById('qc_koli_scan_code')?.focus();
            }
        });
    });
</script>
@endpush
