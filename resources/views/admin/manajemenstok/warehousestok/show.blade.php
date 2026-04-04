@extends('layouts.app')

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Detail Stok Produk',
        'breadcrumbs' => ['Admin', 'Manajemen Stok', 'Stok Gudang', 'Detail'],
    ])
@endpush

@section('content')
    <div class="content flex-row-fluid" id="kt_content">
        <div class="row g-5 g-xl-8">
            <div class="col-xl-4">
                <!-- Info Card -->
                <div class="card card-xl-stretch mb-5 mb-xl-8">
                    <div class="card-body p-6">
                        <div class="d-flex align-items-center">
                            <div class="symbol symbol-45px me-5">
                                <div class="symbol-label bg-light-primary">
                                    <i class="fas fa-box text-primary"></i>
                                </div>
                            </div>
                            <div>
                                <div class="fs-4 fw-bolder text-gray-800">{{ $item->nama_barang }}</div>
                                <div class="text-gray-600">SKU: <span class="fw-bold">{{ $item->sku }}</span></div>
                                <div class="text-gray-600">Warehouse: <span class="fw-bold">{{ $warehouse->name }}</span></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filter card removed (filters available on summary toolbar) -->
            </div>
            <div class="col-xl-8">
                <div class="card card-xl-stretch mb-5 mb-xl-8">
                    <div class="card-header border-0 align-items-center">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bolder text-dark">Ringkasan Periode</span>
                            <span class="text-muted mt-1 fw-bold fs-7">Ikhtisar stok dalam rentang terpilih</span>
                        </h3>
                        <div class="card-toolbar d-flex flex-wrap gap-3">
                            <a href="{{ route('admin.manajemenstok.warehousestok.index') }}" class="btn btn-light me-2">
                                <i class="fas fa-arrow-left me-2"></i> Kembali
                            </a>
                            <input type="text" id="date_range_top" class="form-control form-control-solid w-200px" placeholder="Rentang tanggal" />
                            <select id="type_filter_top" class="form-select form-select-solid w-175px">
                                <option value="all">Semua</option>
                                <option value="transfer_in">Transfer In</option>
                                <option value="transfer_out">Transfer Out</option>
                                <option value="stock_out">Stock Out</option>
                                <option value="adjustment">Adjustment</option>
                            </select>
                            <button id="apply_filters_top" class="btn btn-light-primary">
                                <i class="fas fa-filter me-2"></i> Terapkan
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-6">
                        <div id="summary_cards" class="row g-6">
                            <!-- stats injected by JS -->
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-5 g-xl-8 mt-1">
            <div class="col-xl-4">
                <div class="card card-flush h-xl-100">
                    <div class="card-header border-0">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bolder text-dark">Snapshot Harian</span>
                            <span class="text-muted mt-1 fw-bold fs-7">Perubahan stok per hari</span>
                        </h3>
                    </div>
                    <div class="card-body pt-0">
                        <div class="table-responsive">
                            <table class="table align-middle" id="daily_table">
                                <thead><tr><th>Tanggal</th><th class="text-end">Perubahan</th><th class="text-end">Stok</th></tr></thead>
                                <tbody></tbody>
                            </table>
                        </div>
                        <div class="mt-4 d-flex justify-content-between align-items-center" id="daily_pagination">
                            <button class="btn btn-light" id="prev_daily">Sebelumnya</button>
                            <span id="daily_page_info" class="text-muted"></span>
                            <button class="btn btn-light" id="next_daily">Berikutnya</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-8">
                <div class="card card-flush h-xl-100">
                    <div class="card-header border-0">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bolder text-dark">Pergerakan Stok</span>
                            <span class="text-muted mt-1 fw-bold fs-7">Urut dari terbaru</span>
                        </h3>
                    </div>
                    <div class="card-body pt-0">
                        <div class="table-responsive">
                            <table class="table align-middle table-row-dashed table-striped" id="movement_table">
                                <thead>
                                    <tr>
                                        <th class="w-40px">No</th>
                                        <th>Tanggal</th>
                                        <th>Jenis</th>
                                        <th>Deskripsi</th>
                                        <th class="text-end">Qty</th>
                                        <th class="text-end">Koli</th>
                                        <th class="text-end">Stok Setelah</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                        <div class="mt-4 d-flex justify-content-between align-items-center" id="pagination">
                            <button class="btn btn-light" id="prev_page">Sebelumnya</button>
                            <span id="page_info" class="text-muted"></span>
                            <button class="btn btn-light" id="next_page">Berikutnya</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        /* Keep table headers visible and avoid overlap */
        #daily_table thead th, #movement_table thead th{ position: sticky; top: 0; background: #fff; z-index: 2; }
        #movement_table tbody td{ vertical-align: middle; }
        .card.card-flush, .card.card-xl-stretch{ position: relative; z-index: 2; }
        /* Ensure card bodies clip their content */
        .card .card-body{ overflow: hidden; }
    </style>
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        (function(){
            const warehouseId = {{ $warehouse->id }};
            const itemId = {{ $item->id }};
            let currentPage = 1;
            const perPage = 20;

            const drTop = document.getElementById('date_range_top');
            flatpickr(drTop, { mode: 'range', dateFormat: 'Y-m-d', defaultDate: [new Date(new Date().setDate(new Date().getDate()-14)), new Date()] });

            function parseRange(){
                const val = (drTop.value || '');
                const parts = val.split(' to ');
                return { from: parts[0] || '', to: parts[1] || '' };
            }

            function fmt(n){ return (n ?? 0).toLocaleString('id-ID'); }
            function fmt2(n){ return (n ?? 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

            function summaryCard(icon, color, label, value){
                return `
                    <div class="col-md-4">
                        <div class="d-flex align-items-center p-6 bg-light-${color} rounded border-dashed border-${color}">
                            <div class="symbol symbol-45px me-5">
                                <div class="symbol-label bg-${color} bg-opacity-10">
                                    <i class="fas ${icon} text-${color}"></i>
                                </div>
                            </div>
                            <div>
                                <div class="fs-2 fw-bolder text-gray-800">${value}</div>
                                <div class="text-gray-600 fw-bold">${label}</div>
                            </div>
                        </div>
                    </div>`;
            }
            function renderSummary(s){
                const html = [
                    summaryCard('fa-arrow-down', 'success', 'Masuk', fmt(s.in)),
                    summaryCard('fa-arrow-up', 'danger', 'Keluar', fmt(s.out)),
                    summaryCard('fa-warehouse', 'primary', 'Stok Saat Ini', fmt(s.last_stock))
                ].join('');
                document.getElementById('summary_cards').innerHTML = html + `<div class="col-12 text-muted mt-2">Rentang: ${s.date_from} s/d ${s.date_to}</div>`;
            }

            function renderDaily(p){
                const tb = document.querySelector('#daily_table tbody');
                const rows = p.data || [];
                if(!rows.length){ tb.innerHTML = `<tr><td colspan=\"3\" class=\"text-muted text-center\">Tidak ada data</td></tr>`; }
                else tb.innerHTML = rows.map(r => {
                    const cls = (parseFloat(r.delta) >= 0) ? 'text-success' : 'text-danger';
                    const d = new Date(r.d);
                    const ds = d.toLocaleDateString('id-ID', { day:'2-digit', month:'short', year:'numeric' });
                    return `<tr><td>${ds}</td><td class=\"text-end ${cls}\">${fmt(r.delta)}</td><td class=\"text-end\">${fmt(r.stock)}</td></tr>`;
                }).join('');
                document.getElementById('daily_page_info').textContent = `Hal ${p.current_page} / ${p.last_page}`;
                document.getElementById('prev_daily').disabled = (p.current_page <= 1);
                document.getElementById('next_daily').disabled = (p.current_page >= p.last_page);
            }

            function typeBadge(type){
                const map = {
                    transfer_in: 'badge-light-success',
                    transfer_out: 'badge-light-warning',
                    stock_out: 'badge-light-danger',
                    adjustment: 'badge-light-info'
                };
                const label = (type || '').replace('_',' ');
                const cls = map[type] || 'badge-light-primary';
                return `<span class="badge ${cls}">${label}</span>`;
            }

            function renderMovements(p){
                const tb = document.querySelector('#movement_table tbody');
                if(!p.data.length){ tb.innerHTML = `<tr><td colspan="7" class="text-muted text-center">Belum ada pergerakan</td></tr>`; }
                else {
                    tb.innerHTML = p.data.map((m, idx) => {
                        const d = new Date(m.date);
                        const ds = d.toLocaleDateString('id-ID', { day:'2-digit', month:'short', year:'numeric' });
                        const cls = (parseFloat(m.quantity) >= 0) ? 'text-success' : 'text-danger';
                        const icon = parseFloat(m.quantity) >= 0 ? 'fa-arrow-down text-success' : 'fa-arrow-up text-danger';
                        return `<tr>
                            <td>${(p.current_page-1)*p.per_page + (idx+1)}</td>
                            <td>${ds}</td>
                            <td>${typeBadge(m.type)}</td>
                            <td>${(m.description ?? '-')}</td>
                            <td class="text-end ${cls}">${fmt(m.quantity)}</td>
                            <td class="text-end">${fmt2(m.koli)}</td>
                            <td class="text-end">${fmt(m.stock_after)}</td>
                        </tr>`;
                    }).join('');
                }
                document.getElementById('page_info').textContent = `Hal ${p.current_page} / ${p.last_page}`;
                document.getElementById('prev_page').disabled = (p.current_page <= 1);
                document.getElementById('next_page').disabled = (p.current_page >= p.last_page);
            }

            let currentDailyPage = 1;
            const dailyPerPage = 10;

            async function load(page=1){
                currentPage = page;
                const range = parseRange();
                const type = document.getElementById('type_filter_top').value;
                const params = new URLSearchParams({
                    date_from: range.from,
                    date_to: range.to,
                    type,
                    page: currentPage,
                    per_page: perPage,
                    daily_page: currentDailyPage,
                    daily_per_page: dailyPerPage
                });
                const url = `{{ route('admin.manajemenstok.warehousestok.data', [$warehouse->id, $item->id]) }}?${params.toString()}`;
                const res = await fetch(url);
                const json = await res.json();
                renderSummary(json.summary);
                renderDaily(json.daily || {data:[], current_page:1, last_page:1});
                renderMovements(json.movements);
            }

            document.getElementById('apply_filters_top').addEventListener('click', () => {
                currentDailyPage = 1;
                load(1);
            });
            document.getElementById('prev_page').addEventListener('click', () => load(currentPage-1));
            document.getElementById('next_page').addEventListener('click', () => load(currentPage+1));
            document.getElementById('prev_daily').addEventListener('click', () => { currentDailyPage = Math.max(1, currentDailyPage-1); load(currentPage); });
            document.getElementById('next_daily').addEventListener('click', () => { currentDailyPage = currentDailyPage+1; load(currentPage); });

            load(1);
        })();
    </script>
@endpush
