@extends('layouts.mobile')

@section('title', 'Picking List')

@section('content')
<style>
    .filter-grid {
        display: grid;
        gap: 10px;
    }
    .list-row {
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 12px;
        align-items: center;
        border: 1px solid var(--border);
        border-radius: 16px;
        padding: 12px;
        background: #fff;
    }
    .list-row strong {
        display: block;
        font-size: 13px;
    }
    .list-row small {
        color: var(--muted);
        font-size: 11px;
    }
    .address-line {
        display: flex;
        align-items: flex-start;
        gap: 6px;
        margin-top: 4px;
        font-size: 11px;
        color: var(--muted);
        line-height: 1.4;
    }
    .address-tag {
        display: inline-flex;
        align-items: center;
        padding: 2px 6px;
        border-radius: 999px;
        font-size: 10px;
        font-weight: 600;
        background: rgba(15, 118, 110, 0.12);
        color: var(--brand);
        white-space: nowrap;
    }
    .address-text {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .qty-badge {
        background: rgba(15, 118, 110, 0.12);
        color: var(--brand);
        font-weight: 700;
        padding: 6px 10px;
        border-radius: 999px;
        font-size: 12px;
        white-space: nowrap;
    }
    .topbar-actions {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .topbar-actions form {
        margin: 0;
    }
    .pagination-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-top: 12px;
    }
    .page-btn {
        width: auto;
        padding: 8px 12px;
        border-radius: 999px;
        border: 1px solid var(--border);
        background: #fff;
        font-size: 12px;
        font-weight: 700;
    }
    .page-info {
        font-size: 12px;
        color: var(--muted);
    }
    .row-actions {
        display: grid;
        justify-items: end;
        gap: 8px;
    }
    .pick-btn {
        width: auto;
        min-width: 76px;
        padding: 8px 12px;
        border-radius: 999px;
        border: none;
        background: var(--brand);
        color: #fff;
        font-size: 12px;
        font-weight: 700;
    }
    .pick-btn:disabled {
        opacity: 0.55;
        background: #cbd5e1;
        color: #475569;
    }
    .page-status {
        margin-top: 10px;
        font-size: 12px;
        color: var(--muted);
    }
</style>

<div class="screen">
    <div class="topbar">
        <div>
            <div class="brand">{{ config('app.name', 'Gudang 24') }}</div>
            <div class="subtitle">Picking List & Sisa Qty</div>
        </div>
        <div class="topbar-actions">
            <a href="{{ $routes['dashboard'] }}" class="logout">Dashboard</a>
            <form method="POST" action="{{ $routes['logout'] }}">
                @csrf
                <button type="submit" class="logout">Logout</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div style="font-weight: 700; margin-bottom: 8px;">Filter</div>
        <div class="filter-grid">
            <input type="date" class="input" id="filter_date" value="{{ $today ?? '' }}" />
            <select class="input" id="filter_lane">
                <option value="">Semua Lane</option>
                @foreach($lanes as $lane)
                    <option value="{{ $lane->id }}">{{ $lane->code }} - {{ $lane->name }}</option>
                @endforeach
            </select>
            <input type="text" class="input" id="filter_search" placeholder="Cari SKU atau nama" autocomplete="off" />
            <select class="input" id="filter_per_page">
                <option value="5" selected>5 per halaman</option>
                <option value="10">10 per halaman</option>
                <option value="20">20 per halaman</option>
                <option value="50">50 per halaman</option>
                <option value="100">100 per halaman</option>
            </select>
        </div>
    </div>

    <div class="card">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:8px;">
            <div style="font-weight:700;">Daftar Picking List</div>
            <div class="muted" id="total_items">0 item</div>
        </div>
        <div class="page-status" id="page_status">Pilih item untuk dipick langsung dari halaman ini.</div>
        <div class="muted" id="list_empty">Belum ada data.</div>
        <div class="items-list" id="list_items"></div>
        <div class="pagination-bar">
            <button type="button" class="page-btn" id="btn_prev">Prev</button>
            <div class="page-info" id="page_info">Page 1 / 1</div>
            <button type="button" class="page-btn" id="btn_next">Next</button>
        </div>
    </div>
</div>

<script>
    const routes = @json($routes);
    const todayStr = '{{ $today ?? '' }}';
    const csrfToken = '{{ csrf_token() }}';

    const el = {
        date: document.getElementById('filter_date'),
        lane: document.getElementById('filter_lane'),
        search: document.getElementById('filter_search'),
        perPage: document.getElementById('filter_per_page'),
        list: document.getElementById('list_items'),
        empty: document.getElementById('list_empty'),
        total: document.getElementById('total_items'),
        status: document.getElementById('page_status'),
        btnPrev: document.getElementById('btn_prev'),
        btnNext: document.getElementById('btn_next'),
        pageInfo: document.getElementById('page_info'),
    };

    const state = {
        pickBusy: false,
    };

    const setPageStatus = (text, tone = 'default') => {
        if (!el.status) return;
        el.status.textContent = text;
        if (tone === 'pending') {
            el.status.style.color = '#f97316';
            return;
        }
        if (tone === 'error') {
            el.status.style.color = '#dc2626';
            return;
        }
        el.status.style.color = '#6b7280';
    };

    const fetchJson = async (url, options = {}) => {
        const res = await fetch(url, {
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                ...(options.headers || {}),
            },
            credentials: 'same-origin',
            ...options,
        });
        const text = await res.text();
        let json = null;
        try { json = JSON.parse(text); } catch (err) { json = null; }
        if (!res.ok) {
            let message = json?.message || 'Terjadi kesalahan';
            if (json?.errors) {
                const first = Object.values(json.errors)[0];
                if (Array.isArray(first) && first.length) {
                    message = first[0];
                }
            }
            throw new Error(message);
        }
        return json;
    };

    const selectedDate = () => el.date?.value || todayStr;
    const canPickToday = () => selectedDate() === todayStr;

    const renderList = (items, meta) => {
        const total = meta?.total ?? items.length;
        const allowPick = canPickToday();
        el.total.textContent = `${total} item`;
        if (!state.pickBusy) {
            if (!allowPick) {
                setPageStatus('Tombol Pick hanya aktif untuk picking list hari ini.', 'default');
            } else {
                setPageStatus('Pilih item untuk dipick langsung dari halaman ini.', 'default');
            }
        }
        if (!items.length) {
            el.empty.style.display = 'block';
            el.list.innerHTML = '';
            return;
        }
        el.empty.style.display = 'none';
        el.list.innerHTML = items.map((row) => {
            const qty = row.qty ?? 0;
            const remaining = row.remaining_qty ?? 0;
            const address = row.address && row.address.trim() ? row.address : 'Belum diisi';
            const itemId = row.item_id ?? '';
            const canPickRow = allowPick && Number(remaining) > 0 && !!itemId && !state.pickBusy;
            const pickLabel = !allowPick
                ? 'Hari ini saja'
                : Number(remaining) > 0
                    ? 'Pick'
                    : 'Habis';
            return `
                <div class="list-row">
                    <div>
                        <strong>${row.sku || '-'} • ${row.name || '-'}</strong>
                        <div class="address-line">
                            <span class="address-tag">Lokasi</span>
                            <span class="address-text">${address}</span>
                        </div>
                        <small>Total: ${qty} | Sisa: ${remaining}</small>
                    </div>
                    <div class="row-actions">
                        <div class="qty-badge">${remaining}</div>
                        <button
                            type="button"
                            class="pick-btn"
                            data-action="pick"
                            data-item-id="${itemId}"
                            data-sku="${row.sku || ''}"
                            data-name="${row.name || ''}"
                            data-remaining="${remaining}"
                            ${canPickRow ? '' : 'disabled'}
                        >${pickLabel}</button>
                    </div>
                </div>
            `;
        }).join('');
    };

    let searchTimer = null;
    let currentPage = 1;
    let totalPages = 1;
    const loadData = async () => {
        const params = new URLSearchParams();
        const dateVal = el.date?.value || todayStr;
        if (dateVal) params.set('date', dateVal);
        const q = (el.search?.value || '').trim();
        if (q) params.set('q', q);
        const laneId = el.lane?.value || '';
        if (laneId) params.set('lane_id', laneId);
        const perPage = Number(el.perPage?.value || 5);
        params.set('per_page', perPage);
        params.set('page', currentPage);

        const url = `${routes.data}?${params.toString()}`;
        const data = await fetchJson(url);
        const items = Array.isArray(data.items) ? data.items : [];
        renderList(items, data);
        totalPages = Math.max(1, Number(data.total_pages || 1));
        if (el.pageInfo) {
            el.pageInfo.textContent = `Page ${data.page || 1} / ${totalPages}`;
        }
        if (el.btnPrev) el.btnPrev.disabled = (data.page || 1) <= 1;
        if (el.btnNext) el.btnNext.disabled = (data.page || 1) >= totalPages;
    };

    const promptPickQty = async (row) => {
        const maxQty = Number(row.remaining || 0);
        if (maxQty <= 0) {
            setPageStatus(`SKU ${row.sku || '-'} sudah tidak memiliki sisa picking.`, 'error');
            return null;
        }

        if (typeof Swal !== 'undefined') {
            const result = await Swal.fire({
                title: `Pick ${row.sku || '-'}`,
                text: row.name ? `${row.name} • Maksimal ${maxQty}` : `Maksimal ${maxQty}`,
                input: 'number',
                inputValue: 1,
                inputAttributes: {
                    min: '1',
                    max: String(maxQty),
                    step: '1',
                },
                showCancelButton: true,
                confirmButtonText: 'Simpan Pick',
                cancelButtonText: 'Batal',
                inputValidator: (value) => {
                    const qty = Number(value);
                    if (!Number.isInteger(qty) || qty < 1) {
                        return 'Qty minimal 1.';
                    }
                    if (qty > maxQty) {
                        return `Qty maksimal ${maxQty}.`;
                    }
                    return null;
                },
            });

            if (!result.isConfirmed) {
                return null;
            }

            return Number(result.value || 0);
        }

        const raw = window.prompt(`Masukkan qty pick untuk ${row.sku || '-'} (maks ${maxQty})`, '1');
        if (raw === null) {
            return null;
        }

        const qty = Number(raw);
        if (!Number.isInteger(qty) || qty < 1 || qty > maxQty) {
            setPageStatus(`Qty untuk ${row.sku || '-'} harus antara 1 sampai ${maxQty}.`, 'error');
            return null;
        }

        return qty;
    };

    const pickItem = async (row) => {
        if (state.pickBusy) return;
        if (!canPickToday()) {
            setPageStatus('Pick langsung hanya bisa dipakai untuk picking list hari ini.', 'error');
            return;
        }

        const qty = await promptPickQty(row);
        if (!qty) {
            return;
        }

        state.pickBusy = true;
        setPageStatus(`Menyimpan pick ${row.sku || '-'}...`, 'pending');

        let errorMessage = '';
        let successMessage = '';

        try {
            await loadData();

            const payload = new FormData();
            payload.append('item_id', row.itemId);
            payload.append('qty', qty);

            await fetchJson(routes.itemsStore, {
                method: 'POST',
                body: payload,
            });

            successMessage = `SKU ${row.sku || '-'} berhasil ditambahkan ke daftar barang dibawa.`;
        } catch (err) {
            errorMessage = err.message || 'Gagal menyimpan pick.';
            if (typeof Swal !== 'undefined') {
                Swal.fire('Error', errorMessage, 'error');
            }
        } finally {
            state.pickBusy = false;
            try {
                await loadData();
            } catch (reloadErr) {
                if (!errorMessage) {
                    errorMessage = reloadErr.message || 'Gagal memuat ulang daftar picking list.';
                }
            }
        }

        if (errorMessage) {
            setPageStatus(errorMessage, 'error');
            return;
        }

        setPageStatus(successMessage, 'default');
    };

    el.date?.addEventListener('change', () => {
        currentPage = 1;
        loadData();
    });
    el.lane?.addEventListener('change', () => {
        currentPage = 1;
        loadData();
    });
    el.perPage?.addEventListener('change', () => {
        currentPage = 1;
        loadData();
    });
    el.search?.addEventListener('input', () => {
        if (searchTimer) clearTimeout(searchTimer);
        searchTimer = setTimeout(loadData, 300);
    });
    el.btnPrev?.addEventListener('click', () => {
        if (currentPage > 1) {
            currentPage -= 1;
            loadData();
        }
    });
    el.btnNext?.addEventListener('click', () => {
        if (currentPage < totalPages) {
            currentPage += 1;
            loadData();
        }
    });
    el.list?.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-action="pick"]');
        if (!btn) return;
        const itemId = btn.getAttribute('data-item-id');
        const remaining = Number(btn.getAttribute('data-remaining') || 0);
        if (!itemId) {
            setPageStatus('Item master tidak ditemukan, tidak bisa dipick langsung.', 'error');
            return;
        }
        await pickItem({
            itemId,
            sku: btn.getAttribute('data-sku') || '',
            name: btn.getAttribute('data-name') || '',
            remaining,
        });
    });

    if (el.date && !el.date.value && todayStr) {
        el.date.value = todayStr;
    }
    loadData();
</script>
@endsection
