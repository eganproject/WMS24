@extends('layouts.admin')

@section('title', 'QC Manual Outbound')
@section('page_title', 'QC Manual Outbound')

@section('content')
<style>
    .manual-qc-layout {
        display: grid;
        grid-template-columns: 380px minmax(0, 1fr);
        gap: 18px;
        align-items: start;
    }
    .manual-qc-card {
        background: #fff;
        border: 1px solid #e4e6ef;
        border-radius: 8px;
        box-shadow: 0 4px 16px rgba(15, 23, 42, 0.04);
    }
    .manual-qc-card-header {
        padding: 18px 20px;
        border-bottom: 1px solid #eff2f5;
        display: flex;
        gap: 12px;
        justify-content: space-between;
        align-items: center;
    }
    .manual-qc-card-title {
        margin: 0;
        font-size: 16px;
        font-weight: 700;
        color: #181c32;
    }
    .manual-qc-card-body {
        padding: 20px;
    }
    .manual-qc-search {
        display: grid;
        gap: 10px;
        margin-bottom: 16px;
    }
    .manual-qc-list {
        display: grid;
        gap: 10px;
        max-height: 620px;
        overflow: auto;
        padding-right: 4px;
    }
    .manual-qc-row {
        width: 100%;
        border: 1px solid #e4e6ef;
        background: #fff;
        border-radius: 8px;
        padding: 12px;
        text-align: left;
        transition: border-color .15s ease, background-color .15s ease;
    }
    .manual-qc-row:hover,
    .manual-qc-row.is-active {
        border-color: #009ef7;
        background: #f1faff;
    }
    .manual-qc-code {
        font-weight: 800;
        color: #181c32;
        margin-bottom: 4px;
    }
    .manual-qc-meta {
        display: grid;
        gap: 2px;
        color: #7e8299;
        font-size: 12px;
    }
    .manual-qc-scan-box {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 110px 120px;
        gap: 10px;
        align-items: stretch;
    }
    .manual-qc-sku {
        font-size: 24px;
        font-weight: 800;
        height: 58px;
    }
    .manual-qc-qty {
        font-size: 20px;
        font-weight: 800;
        height: 58px;
        text-align: center;
    }
    .manual-qc-summary {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
        margin-bottom: 18px;
    }
    .manual-qc-stat {
        border: 1px solid #e4e6ef;
        border-radius: 8px;
        padding: 14px;
        background: #fafafa;
    }
    .manual-qc-stat-label {
        color: #7e8299;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        margin-bottom: 4px;
    }
    .manual-qc-stat-value {
        font-size: 24px;
        font-weight: 800;
        color: #181c32;
    }
    .manual-qc-progress {
        height: 10px;
        background: #f1f1f4;
        border-radius: 999px;
        overflow: hidden;
        margin: 10px 0 18px;
    }
    .manual-qc-progress-bar {
        height: 100%;
        width: 0;
        background: #50cd89;
        transition: width .2s ease;
    }
    .manual-qc-empty {
        border: 1px dashed #d8dbe6;
        border-radius: 8px;
        padding: 28px;
        color: #7e8299;
        text-align: center;
    }
    .manual-qc-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        justify-content: flex-end;
        margin-top: 18px;
    }
    .manual-qc-status {
        min-height: 24px;
        color: #7e8299;
        font-weight: 600;
        margin-top: 10px;
    }
    .manual-qc-status.is-error {
        color: #f1416c;
    }
    .manual-qc-status.is-success {
        color: #50cd89;
    }
    .manual-qc-table-wrap {
        overflow-x: auto;
    }
    @media (max-width: 991px) {
        .manual-qc-layout {
            grid-template-columns: 1fr;
        }
        .manual-qc-scan-box {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="manual-qc-layout">
    <div class="manual-qc-card">
        <div class="manual-qc-card-header">
            <h3 class="manual-qc-card-title">Antrian QC</h3>
            <button type="button" class="btn btn-sm btn-light" id="btn_refresh_transactions">Refresh</button>
        </div>
        <div class="manual-qc-card-body">
            <div class="manual-qc-search">
                <input type="text" class="form-control form-control-solid" id="transaction_search" placeholder="Cari kode / ref no">
                <button type="button" class="btn btn-light-primary" id="btn_search_transactions">Cari</button>
            </div>
            <div class="manual-qc-list" id="transaction_list"></div>
        </div>
    </div>

    <div class="manual-qc-card">
        <div class="manual-qc-card-header">
            <h3 class="manual-qc-card-title" id="detail_title">Belum ada transaksi dipilih</h3>
            <span class="badge badge-light" id="detail_status">-</span>
        </div>
        <div class="manual-qc-card-body">
            <div id="empty_state" class="manual-qc-empty">Pilih transaksi outbound manual dari antrian QC.</div>

            <div id="workbench" style="display:none;">
                <div class="manual-qc-summary">
                    <div class="manual-qc-stat">
                        <div class="manual-qc-stat-label">Target</div>
                        <div class="manual-qc-stat-value" id="summary_expected">0</div>
                    </div>
                    <div class="manual-qc-stat">
                        <div class="manual-qc-stat-label">Scan</div>
                        <div class="manual-qc-stat-value" id="summary_scanned">0</div>
                    </div>
                    <div class="manual-qc-stat">
                        <div class="manual-qc-stat-label">Sisa</div>
                        <div class="manual-qc-stat-value" id="summary_remaining">0</div>
                    </div>
                </div>

                <div class="manual-qc-progress">
                    <div class="manual-qc-progress-bar" id="summary_progress"></div>
                </div>

                <form id="scan_form" class="mb-5">
                    <div class="manual-qc-scan-box">
                        <input type="text" class="form-control form-control-solid manual-qc-sku" id="sku_input" autocomplete="off" placeholder="SKU">
                        <input type="number" class="form-control form-control-solid manual-qc-qty" id="qty_input" min="1" value="1">
                        <button type="submit" class="btn btn-primary">Scan</button>
                    </div>
                    <div class="manual-qc-status" id="scan_status"></div>
                </form>

                <div class="manual-qc-table-wrap">
                    <table class="table align-middle table-row-dashed fs-6 gy-4">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                <th>SKU</th>
                                <th>Item</th>
                                <th class="text-end">Target</th>
                                <th class="text-end">Scan</th>
                                <th class="text-end">Sisa</th>
                            </tr>
                        </thead>
                        <tbody id="item_rows"></tbody>
                    </table>
                </div>

                <div class="manual-qc-actions">
                    <button type="button" class="btn btn-light-danger" id="btn_reset_qc">Reset</button>
                    <button type="button" class="btn btn-success" id="btn_complete_qc">Complete QC</button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const routes = @json($routes);
    const csrfToken = '{{ csrf_token() }}';
    const listEl = document.getElementById('transaction_list');
    const searchEl = document.getElementById('transaction_search');
    const detailTitle = document.getElementById('detail_title');
    const detailStatus = document.getElementById('detail_status');
    const emptyState = document.getElementById('empty_state');
    const workbench = document.getElementById('workbench');
    const scanForm = document.getElementById('scan_form');
    const skuInput = document.getElementById('sku_input');
    const qtyInput = document.getElementById('qty_input');
    const scanStatus = document.getElementById('scan_status');
    const itemRows = document.getElementById('item_rows');
    const summaryExpected = document.getElementById('summary_expected');
    const summaryScanned = document.getElementById('summary_scanned');
    const summaryRemaining = document.getElementById('summary_remaining');
    const summaryProgress = document.getElementById('summary_progress');
    let currentTransaction = null;

    const labels = {
        pending_qc: 'Menunggu QC',
        qc_scanning: 'Sedang QC',
        approved: 'Selesai',
        pending: 'Menunggu Approval',
    };

    const badgeClass = (status) => {
        if (status === 'approved') return 'badge-light-success';
        if (status === 'qc_scanning') return 'badge-light-primary';
        if (status === 'pending_qc') return 'badge-light-warning';
        return 'badge-light';
    };

    const setStatus = (message, type = '') => {
        scanStatus.textContent = message || '';
        scanStatus.classList.remove('is-error', 'is-success');
        if (type) scanStatus.classList.add(type === 'error' ? 'is-error' : 'is-success');
    };

    const requestJson = async (url, options = {}) => {
        const response = await fetch(url, {
            ...options,
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                ...(options.headers || {}),
            },
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            const message = data.message || 'Request gagal.';
            const detail = Array.isArray(data.details) && data.details.length
                ? ' ' + data.details.map((row) => `${row.sku}: ${row.scanned}/${row.required}`).join(', ')
                : '';
            throw new Error(message + detail);
        }
        return data;
    };

    const loadTransactions = async () => {
        listEl.innerHTML = '<div class="text-muted py-4 text-center">Loading...</div>';
        const url = new URL(routes.transactions, window.location.origin);
        const query = (searchEl.value || '').trim();
        if (query) url.searchParams.set('query', query);
        try {
            const data = await requestJson(url.toString(), { method: 'GET', headers: { 'Content-Type': 'application/json' } });
            const rows = data.transactions || [];
            if (!rows.length) {
                listEl.innerHTML = '<div class="text-muted py-4 text-center">Tidak ada transaksi.</div>';
                return;
            }
            listEl.innerHTML = rows.map((row) => `
                <button type="button" class="manual-qc-row" data-id="${row.id}">
                    <div class="manual-qc-code">${escapeHtml(row.code)}</div>
                    <div class="manual-qc-meta">
                        <span>${escapeHtml(row.ref_no || '-')} | ${escapeHtml(row.warehouse || '-')}</span>
                        <span>${escapeHtml(row.transacted_at || '-')}</span>
                        <span>Qty ${row.summary?.scanned_qty ?? 0}/${row.summary?.expected_qty ?? 0}</span>
                    </div>
                </button>
            `).join('');
        } catch (error) {
            listEl.innerHTML = `<div class="text-danger py-4 text-center">${escapeHtml(error.message)}</div>`;
        }
    };

    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const openTransaction = async (id) => {
        setStatus('');
        try {
            const data = await requestJson(routes.open, {
                method: 'POST',
                body: JSON.stringify({ transaction_id: id }),
            });
            currentTransaction = data.transaction;
            renderTransaction(currentTransaction);
            setStatus(data.message || 'Transaksi dibuka.', 'success');
            loadTransactions();
            window.setTimeout(() => skuInput?.focus(), 50);
        } catch (error) {
            setStatus(error.message, 'error');
        }
    };

    const renderTransaction = (transaction) => {
        emptyState.style.display = 'none';
        workbench.style.display = '';
        detailTitle.textContent = `${transaction.code} | ${transaction.ref_no || '-'}`;
        detailStatus.textContent = labels[transaction.status] || transaction.status || '-';
        detailStatus.className = `badge ${badgeClass(transaction.status)}`;

        const summary = transaction.summary || {};
        const expected = Number(summary.expected_qty || 0);
        const scanned = Number(summary.scanned_qty || 0);
        const remaining = Number(summary.remaining_qty || 0);
        summaryExpected.textContent = expected.toLocaleString('id-ID');
        summaryScanned.textContent = scanned.toLocaleString('id-ID');
        summaryRemaining.textContent = remaining.toLocaleString('id-ID');
        summaryProgress.style.width = expected > 0 ? `${Math.min(100, Math.round((scanned / expected) * 100))}%` : '0%';

        itemRows.innerHTML = (transaction.items || []).map((row) => {
            const target = Number(row.expected_qty || 0);
            const done = Number(row.scanned_qty || 0);
            const left = Math.max(0, target - done);
            const rowClass = left === 0 ? 'text-success' : '';
            return `
                <tr class="${rowClass}">
                    <td class="fw-bolder">${escapeHtml(row.sku || '-')}</td>
                    <td>${escapeHtml(row.item_name || '-')}</td>
                    <td class="text-end">${target.toLocaleString('id-ID')}</td>
                    <td class="text-end">${done.toLocaleString('id-ID')}</td>
                    <td class="text-end">${left.toLocaleString('id-ID')}</td>
                </tr>
            `;
        }).join('');

        const completed = transaction.status === 'approved';
        skuInput.disabled = completed;
        qtyInput.disabled = completed;
        document.getElementById('btn_complete_qc').disabled = completed;
        document.getElementById('btn_reset_qc').disabled = completed;
    };

    scanForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!currentTransaction?.session?.id) {
            setStatus('Pilih transaksi terlebih dahulu.', 'error');
            return;
        }
        const code = (skuInput.value || '').trim();
        const qty = Number(qtyInput.value || 1);
        if (!code) {
            setStatus('SKU tidak boleh kosong.', 'error');
            return;
        }
        try {
            const data = await requestJson(routes.scanSku, {
                method: 'POST',
                body: JSON.stringify({ session_id: currentTransaction.session.id, code, qty }),
            });
            currentTransaction = data.transaction;
            renderTransaction(currentTransaction);
            skuInput.value = '';
            qtyInput.value = '1';
            setStatus(data.message || 'SKU berhasil discan.', 'success');
            skuInput.focus();
        } catch (error) {
            setStatus(error.message, 'error');
            skuInput.select();
        }
    });

    document.getElementById('btn_complete_qc').addEventListener('click', async () => {
        if (!currentTransaction?.session?.id) return;
        const confirmed = await AppSwal.confirm('Selesaikan QC outbound manual ini?', {
            confirmButtonText: 'Complete QC',
        });
        if (!confirmed) return;

        try {
            const data = await requestJson(routes.complete, {
                method: 'POST',
                body: JSON.stringify({ session_id: currentTransaction.session.id }),
            });
            currentTransaction = data.transaction;
            renderTransaction(currentTransaction);
            setStatus(data.message || 'QC selesai.', 'success');
            loadTransactions();
        } catch (error) {
            setStatus(error.message, 'error');
        }
    });

    document.getElementById('btn_reset_qc').addEventListener('click', async () => {
        if (!currentTransaction?.session?.id) return;
        let reason = '';
        if (window.Swal) {
            const result = await window.Swal.fire({
                title: 'Alasan reset QC',
                input: 'textarea',
                inputPlaceholder: 'Masukkan alasan reset',
                showCancelButton: true,
                confirmButtonText: 'Reset',
                cancelButtonText: 'Batal',
                buttonsStyling: false,
                customClass: {
                    confirmButton: 'btn btn-danger',
                    cancelButton: 'btn btn-light',
                },
                inputValidator: (value) => !String(value || '').trim() ? 'Alasan reset wajib diisi.' : undefined,
            });
            if (!result.isConfirmed) return;
            reason = (result.value || '').trim();
        } else {
            reason = (window.prompt('Alasan reset QC') || '').trim();
        }
        if (!reason) return;

        try {
            const data = await requestJson(routes.reset, {
                method: 'POST',
                body: JSON.stringify({ session_id: currentTransaction.session.id, reason }),
            });
            currentTransaction = data.transaction;
            renderTransaction(currentTransaction);
            setStatus(data.message || 'QC direset.', 'success');
            skuInput.focus();
        } catch (error) {
            setStatus(error.message, 'error');
        }
    });

    listEl.addEventListener('click', (event) => {
        const row = event.target.closest('.manual-qc-row');
        if (!row) return;
        listEl.querySelectorAll('.manual-qc-row').forEach((el) => el.classList.remove('is-active'));
        row.classList.add('is-active');
        openTransaction(row.dataset.id);
    });

    document.getElementById('btn_refresh_transactions').addEventListener('click', loadTransactions);
    document.getElementById('btn_search_transactions').addEventListener('click', loadTransactions);
    searchEl.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            loadTransactions();
        }
    });

    loadTransactions();
});
</script>
@endpush
