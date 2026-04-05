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
                <option value="20" selected>20 per halaman</option>
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
        btnPrev: document.getElementById('btn_prev'),
        btnNext: document.getElementById('btn_next'),
        pageInfo: document.getElementById('page_info'),
    };

    const fetchJson = async (url) => {
        const res = await fetch(url, {
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            }
        });
        const text = await res.text();
        let json = null;
        try { json = JSON.parse(text); } catch (err) { json = null; }
        if (!res.ok) {
            throw new Error(json?.message || 'Gagal mengambil data');
        }
        return json;
    };

    const renderList = (items, meta) => {
        const total = meta?.total ?? items.length;
        el.total.textContent = `${total} item`;
        if (!items.length) {
            el.empty.style.display = 'block';
            el.list.innerHTML = '';
            return;
        }
        el.empty.style.display = 'none';
        el.list.innerHTML = items.map((row) => {
            const qty = row.qty ?? 0;
            const remaining = row.remaining_qty ?? 0;
            return `
                <div class="list-row">
                    <div>
                        <strong>${row.sku || '-'} • ${row.name || '-'}</strong>
                        <small>Total: ${qty} | Sisa: ${remaining}</small>
                    </div>
                    <div class="qty-badge">${remaining}</div>
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
        const perPage = Number(el.perPage?.value || 20);
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

    if (el.date && !el.date.value && todayStr) {
        el.date.value = todayStr;
    }
    loadData();
</script>
@endsection
