@extends('layouts.app')

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Distribusi Pengadaan',
        'breadcrumbs' => ['Admin', 'Stok Masuk', 'Pengadaan', 'Distribusi'],
    ])
@endpush

@section('content')
    <div class="content flex-row-fluid" id="kt_content">
        <div class="card">
            <div class="card-body p-lg-10">
                <div class="d-flex justify-content-between align-items-start mb-6">
                    <div>
                        <div class="fs-2hx fw-bolder">Distribusi Pengadaan</div>
                        <div class="text-muted">Kode: {{ $stockInOrder->code }} | Tanggal: {{ \Carbon\Carbon::parse($stockInOrder->date)->format('d M Y') }}</div>
                    </div>
                    <div class="text-end">
                        @php
                            $badgeClassTop = 'primary';
                            if ($stockInOrder->status === 'completed') { $badgeClassTop = 'success'; }
                            elseif ($stockInOrder->status === 'rejected') { $badgeClassTop = 'danger'; }
                            elseif ($stockInOrder->status === 'on_progress') { $badgeClassTop = 'warning'; }
                        @endphp
                        <span class="badge badge-light-{{ $badgeClassTop }}">Status: {{ ucwords(str_replace('_',' ', $stockInOrder->status)) }}</span>
                    </div>
                </div>

                <div class="row g-5 mb-8">
                    <div class="col-md-6">
                        <div class="p-5 border rounded">
                            <div class="fw-bold mb-2">Informasi Dokumen</div>
                            <div class="row mb-1"><div class="col-5 text-muted">Tipe</div><div class="col-7 fw-bold">{{ ucfirst($stockInOrder->type) }}</div></div>
                            <div class="row mb-1"><div class="col-5 text-muted">Catatan</div><div class="col-7 fw-bold">{{ $stockInOrder->description ?? '-' }}</div></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-5 border rounded">
                            <div class="fw-bold mb-2">Referensi & Waktu</div>
                            <div class="row mb-1"><div class="col-5 text-muted">Gudang Tujuan</div><div class="col-7 fw-bold">{{ $stockInOrder->warehouse->name ?? '-' }}</div></div>
                            @if(strtolower($stockInOrder->type) !== 'import')
                                <div class="row mb-1"><div class="col-5 text-muted">Dari Gudang</div><div class="col-7 fw-bold">{{ $stockInOrder->fromWarehouse->name ?? '-' }}</div></div>
                            @endif
                            <div class="row mb-1"><div class="col-5 text-muted">Diminta Oleh</div><div class="col-7 fw-bold">{{ $stockInOrder->requestedBy->name ?? '-' }}</div></div>
                            <div class="row mb-1"><div class="col-5 text-muted">Diminta Pada</div><div class="col-7 fw-bold">{{ $stockInOrder->requested_at ? \Carbon\Carbon::parse($stockInOrder->requested_at)->format('d M Y') : '-' }}</div></div>
                            <div class="row mb-1"><div class="col-5 text-muted">Terkirim Pada</div><div class="col-7 fw-bold">{{ $stockInOrder->shipping_at ? \Carbon\Carbon::parse($stockInOrder->shipping_at)->format('d M Y') : '-' }}</div></div>
                            <div class="row mb-1"><div class="col-5 text-muted">Selesai Pada</div><div class="col-7 fw-bold">{{ $stockInOrder->completed_at ? \Carbon\Carbon::parse($stockInOrder->completed_at)->format('d M Y') : '-' }}</div></div>
                        </div>
                    </div>
                </div>

                @php
                    $sumQty = 0; $sumKoli = 0;
                    foreach ($stockInOrder->items as $it) {
                        foreach ($it->distributions as $dist) {
                            $sumQty += (float) ($dist->quantity ?? 0);
                            $sumKoli += (float) ($dist->koli ?? 0);
                        }
                    }
                @endphp
                <!-- Toolbar: totals + search -->
                <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-5 gap-3">
                    <div class="d-flex flex-wrap align-items-center gap-3">
                        <div class="fw-bold text-gray-700">Ringkasan:</div>
                        <span class="badge badge-light-primary">
                            Total Qty: {{ number_format($sumQty, 0, ',', '.') }}
                        </span>
                        <span class="badge badge-light-info">
                            Total Koli: {{ number_format($sumKoli, 2, ',', '.') }}
                        </span>
                    </div>
                    <div class="w-100 w-md-300px">
                        <div class="position-relative">
                            <span class="svg-icon svg-icon-2 position-absolute top-50 translate-middle-y ms-4">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                    <rect opacity="0.5" x="17.0365" y="15.1223" width="8.15546" height="2" rx="1" transform="rotate(45 17.0365 15.1223)" fill="currentColor"/>
                                    <path d="M11 19C6.58172 19 3 15.4183 3 11C3 6.58172 6.58172 3 11 3C15.4183 3 19 6.58172 19 11C19 15.4183 15.4183 19 11 19ZM11 5C7.68629 5 5 7.68629 5 11C5 14.3137 7.68629 17 11 17C14.3137 17 17 14.3137 17 11C17 7.68629 14.3137 5 11 5Z" fill="currentColor"/>
                                </svg>
                            </span>
                            <input id="dist-search" type="text" class="form-control form-control-solid ps-12" placeholder="Cari SKU/Item/Gudang/Status/Catatan..." />
                            <button id="dist-search-clear" type="button" class="btn btn-sm btn-icon btn-light position-absolute top-50 translate-middle-y me-2 end-0" aria-label="Bersihkan">
                                <span class="svg-icon svg-icon-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                        <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="currentColor"/>
                                        <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="currentColor"/>
                                    </svg>
                                </span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table id="dist-table" class="table table-row-dashed table-hover align-middle gs-0 gy-3">
                        <thead>
                            <tr class="text-gray-700 text-uppercase">
                                <th class="min-w-130px">Tanggal</th>
                                <th class="min-w-100px">SKU</th>
                                <th class="min-w-250px">Item</th>
                                <th class="min-w-150px">Gudang Tujuan</th>
                                <th class="text-end min-w-100px">Qty</th>
                                <th class="text-end min-w-100px">Koli</th>
                                <th class="min-w-120px">Status</th>
                                <th class="min-w-200px">Catatan</th>
                                <th class="min-w-120px">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $hasAny = false; @endphp
                            @foreach ($stockInOrder->items as $it)
                                @foreach ($it->distributions as $dist)
                                    @php $hasAny = true; @endphp
                                    <tr>
                                        <td>{{ $dist->date ? \Carbon\Carbon::parse($dist->date)->format('d M Y') : '-' }}</td>
                                        <td>{{ $it->item->sku ?? '-' }}</td>
                                        <td>{{ $it->item->nama_barang ?? $it->item->name ?? '-' }}</td>
                                        <td>{{ $dist->toWarehouse->name ?? '-' }}</td>
                                        <td class="text-end">{{ number_format($dist->quantity ?? 0, 0, ',', '.') }}</td>
                                        <td class="text-end">{{ number_format($dist->koli ?? 0, 2, ',', '.') }}</td>
                                        <td>
                                            @php
                                                $st = $dist->status ?? 'draft';
                                                $cls = $st === 'completed' ? 'success' : 'warning';
                                            @endphp
                                            <span class="badge badge-light-{{ $cls }}">{{ ucfirst($st) }}</span>
                                        </td>
                                        <td>{{ $dist->note ?? '-' }}</td>
                                        <td>
                                            @if(($dist->status ?? 'draft') === 'draft')
                                                <a href="#" class="btn btn-sm btn-light btn-active-light-primary" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">Actions
                                                    <span class="svg-icon svg-icon-5 m-0">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                                            <path d="M11.4343 12.7344L7.25 8.55005C6.83579 8.13583 6.16421 8.13584 5.75 8.55005C5.33579 8.96426 5.33579 9.63583 5.75 10.05L11.2929 15.5929C11.6834 15.9835 12.3166 15.9835 12.7071 15.5929L18.25 10.05C18.6642 9.63584 18.6642 8.96426 18.25 8.55005C17.8358 8.13584 17.1642 8.13584 16.75 8.55005L12.5657 12.7344C12.2533 13.0468 11.7467 13.0468 11.4343 12.7344Z" fill="black"></path>
                                                        </svg>
                                                    </span>
                                                </a>
                                                <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-600 menu-state-bg-light-primary fw-bold fs-7 w-150px py-4" data-kt-menu="true">
                                                    <div class="menu-item px-3">
                                                        <button type="button" class="menu-link px-3 bg-transparent border-0 btn-approve-dist" data-dist-id="{{ $dist->id }}">Approve</button>
                                                    </div>
                                                    <div class="menu-item px-3">
                                                        <button type="button" class="menu-link px-3 bg-transparent border-0 btn-edit-dist"
                                                            data-dist-id="{{ $dist->id }}"
                                                            data-date="{{ $dist->date }}"
                                                            data-qty="{{ (float)($dist->quantity ?? 0) }}"
                                                            data-koli="{{ (float)($dist->koli ?? 0) }}"
                                                            data-note="{{ $dist->note }}"
                                                            data-to-warehouse-id="{{ (int) $dist->to_warehouse_id }}"
                                                            data-item-name="{{ $it->item->nama_barang ?? $it->item->name ?? '-' }}"
                                                            data-sku="{{ $it->item->sku ?? '-' }}"
                                                            data-koli-ratio="{{ (float) (optional($it->item)->koli ?? 1) }}"
                                                            data-rem-qty="{{ (float) ($it->remaining_quantity ?? 0) }}"
                                                            data-rem-koli="{{ (float) ($it->remaining_koli ?? 0) }}"
                                                        >Edit</button>
                                                    </div>
                                                </div>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            @endforeach
                            @if(!$hasAny)
                                <tr><td colspan="9" class="text-center text-muted">Belum ada distribusi</td></tr>
                            @endif
                            @if($hasAny)
                                <tr id="dist-no-match" style="display:none;">
                                    <td colspan="9" class="text-center text-muted">Tidak ada hasil yang cocok</td>
                                </tr>
                            @endif
                        </tbody>
                        @if($hasAny)
                        <tfoot>
                            <tr class="border-top">
                                <th colspan="4" class="text-end">Total</th>
                                <th class="text-end">{{ number_format($sumQty, 0, ',', '.') }}</th>
                                <th class="text-end">{{ number_format($sumKoli, 2, ',', '.') }}</th>
                                <th colspan="3"></th>
                            </tr>
                        </tfoot>
                        @endif
                    </table>
                </div>

                <div class="d-flex justify-content-end mt-8">
                    <a href="{{ route('admin.stok-masuk.pengadaan.index') }}" class="btn btn-secondary">Kembali</a>
                </div>
            </div>
        </div>
    </div>
@endsection

<!-- Inline modal (avoid relying on @stack('modals') availability) -->
<div class="modal fade" id="editDistModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Edit Distribusi</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="edit-dist-form">
          <input type="hidden" name="_token" value="{{ csrf_token() }}" />
          <input type="hidden" name="distribution_id" />
          <div class="mb-3">
            <div class="fw-bold" id="edit-dist-item"></div>
            <div class="text-muted" id="edit-dist-sku"></div>
          </div>
          <div class="mb-3">
            <label class="form-label">Gudang Tujuan</label>
            <select name="to_warehouse_id" class="form-select form-select-solid" data-control="select2" data-placeholder="Pilih Gudang">
              <option value="">- Pilih Gudang -</option>
              @foreach(($warehouses ?? []) as $wh)
                <option value="{{ $wh->id }}">{{ $wh->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Tanggal</label>
            <input type="date" class="form-control form-control-solid" name="date" />
          </div>
          <div class="row g-3">
            <div class="col">
              <label class="form-label">Qty</label>
              <input type="number" class="form-control form-control-solid text-end" name="quantity" min="0" step="1" />
            </div>
            <div class="col">
              <label class="form-label">Koli</label>
              <input type="number" class="form-control form-control-solid text-end" name="koli" min="0" step="0.01" />
            </div>
          </div>
          <div class="row g-3 mt-2">
            <div class="col">
              <div class="text-muted">Sisa Qty: <span id="edit-rem-qty">0</span></div>
            </div>
            <div class="col">
              <div class="text-muted">Sisa Koli: <span id="edit-rem-koli">0</span></div>
            </div>
          </div>
          <div class="mt-3">
            <label class="form-label">Catatan</label>
            <input type="text" class="form-control form-control-solid" name="note" />
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-primary" id="btn-save-edit-dist">Simpan</button>
      </div>
    </div>
  </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function(){
    const csrfToken = "{{ csrf_token() }}";
    if (window.KTMenu && typeof KTMenu.createInstances === 'function') { KTMenu.createInstances(); }
    document.querySelectorAll('.btn-approve-dist').forEach(function(btn){
        btn.addEventListener('click', function(){
            const id = this.getAttribute('data-dist-id');
            Swal.fire({
                title: 'Approve distribusi?',
                text: 'Aksi ini akan mengurangi sisa pada dokumen.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, approve',
                cancelButtonText: 'Batal'
            }).then(function(r){
                if(!r.isConfirmed) return;
                fetch("{{ route('admin.stok-masuk.pengadaan.distributions.approve', ':id') }}".replace(':id', id), {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ _token: csrfToken })
                }).then(res=>res.json()).then(function(resp){
                    if (resp.success) {
                        Swal.fire({ icon: 'success', text: resp.message || 'Berhasil disetujui' }).then(()=> window.location.reload());
                    } else {
                        Swal.fire({ icon: 'error', text: resp.message || 'Gagal menyetujui' });
                    }
                }).catch(function(){
                    Swal.fire({ icon: 'error', text: 'Gagal menyetujui' });
                });
            });
        });
    });
    // Open edit modal
    document.querySelectorAll('.btn-edit-dist').forEach(function(btn){
        btn.addEventListener('click', function(){
            const id = this.getAttribute('data-dist-id');
            const itemName = this.getAttribute('data-item-name') || '-';
            const sku = this.getAttribute('data-sku') || '-';
            const date = this.getAttribute('data-date') || '';
            const qty = this.getAttribute('data-qty') || '0';
            const koli = this.getAttribute('data-koli') || '0';
            const note = this.getAttribute('data-note') || '';
            const toWarehouseId = this.getAttribute('data-to-warehouse-id') || '';
            const ratio = parseFloat(this.getAttribute('data-koli-ratio')) || 1;
            const remQty = parseFloat(this.getAttribute('data-rem-qty')) || 0;
            const remKoli = parseFloat(this.getAttribute('data-rem-koli')) || 0;

            const form = document.getElementById('edit-dist-form');
            form.querySelector('[name="distribution_id"]').value = id;
            form.querySelector('[name="date"]').value = date;
            form.querySelector('[name="quantity"]').value = qty;
            form.querySelector('[name="koli"]').value = koli;
            form.querySelector('[name="note"]').value = note;
            // preselect warehouse if available
            const whSelect = form.querySelector('[name="to_warehouse_id"]');
            if (whSelect) {
                whSelect.value = toWarehouseId ? String(toWarehouseId) : '';
                // trigger change for select2 if present
                if (typeof $ !== 'undefined' && $(whSelect).data('select2')) {
                    $(whSelect).trigger('change');
                }
            }
            document.getElementById('edit-dist-item').textContent = itemName;
            document.getElementById('edit-dist-sku').textContent = sku;
            // simpan sisa ke dataset form untuk validasi
            form.dataset.remQty = remQty;
            form.dataset.remKoli = remKoli;
            // tampilkan info sisa
            try {
                document.getElementById('edit-rem-qty').textContent = (parseFloat(remQty)||0).toLocaleString('id-ID');
                document.getElementById('edit-rem-koli').textContent = (parseFloat(remKoli)||0).toLocaleString('id-ID', {minimumFractionDigits:2, maximumFractionDigits:2});
            } catch (e) {
                document.getElementById('edit-rem-qty').textContent = remQty;
                document.getElementById('edit-rem-koli').textContent = remKoli;
            }

            // attach kalkulasi otomatis seperti create.blade
            const qtyInput = form.querySelector('[name="quantity"]');
            const koliInput = form.querySelector('[name="koli"]');
            // set HTML5 constraints to help browsers enforce limits
            if (!isNaN(remQty)) qtyInput.setAttribute('max', remQty);
            if (!isNaN(remKoli)) koliInput.setAttribute('max', remKoli);
            qtyInput.oninput = function(){
                let q = parseFloat(qtyInput.value);
                if (isNaN(q)) q = 0;
                q = Math.round(q);
                if (q > remQty) q = Math.round(remQty);
                if (q < 0) q = 0;
                qtyInput.value = q;
                let k = ratio > 0 ? q / ratio : 0;
                let kRounded = parseFloat(k.toFixed(2));
                if (!isNaN(remKoli) && Math.abs(remKoli - kRounded) <= 0.01) { kRounded = remKoli; }
                if (!isNaN(remKoli) && kRounded > remKoli) { kRounded = remKoli; }
                koliInput.value = kRounded.toFixed(2);
            };
            koliInput.oninput = function(){
                // Saat Koli diketik: clamp ke rentang [0, sisaKoli], hitung Qty
                let k = parseFloat(koliInput.value);
                if (isNaN(k)) k = 0;
                if (k < 0) k = 0;
                if (!isNaN(remKoli) && k > remKoli) k = remKoli;
                // tulis balik nilai ter-clamp segera agar tidak bisa melebihi sisa
                koliInput.value = k;
                let q = Math.round(k * ratio);
                if (!isNaN(remQty) && q > remQty) q = Math.round(remQty);
                if (q < 0) q = 0;
                qtyInput.value = q;
            };
            // On blur, hard clamp and format to 2 decimals for Koli; also clamp Qty
            koliInput.onblur = function(){
                let k = parseFloat(koliInput.value);
                if (isNaN(k)) k = 0;
                if (!isNaN(remKoli) && k > remKoli) k = remKoli;
                if (k < 0) k = 0;
                koliInput.value = parseFloat(k).toFixed(2);
                // recalc qty with final k
                let q = Math.round(k * ratio);
                if (!isNaN(remQty) && q > remQty) q = Math.round(remQty);
                if (q < 0) q = 0;
                qtyInput.value = q;
            };
            qtyInput.onblur = function(){
                let q = parseFloat(qtyInput.value);
                if (isNaN(q)) q = 0;
                q = Math.round(q);
                if (!isNaN(remQty) && q > remQty) q = Math.round(remQty);
                if (q < 0) q = 0;
                qtyInput.value = q;
                // recalc and clamp koli
                let k = ratio > 0 ? q / ratio : 0;
                let kRounded = parseFloat(k.toFixed(2));
                if (!isNaN(remKoli) && kRounded > remKoli) kRounded = remKoli;
                if (kRounded < 0) kRounded = 0;
                koliInput.value = kRounded.toFixed(2);
            };

            const modal = new bootstrap.Modal(document.getElementById('editDistModal'));
            modal.show();
        });
    });

    // Save edit
    document.getElementById('btn-save-edit-dist').addEventListener('click', function(){
        const form = document.getElementById('edit-dist-form');
        const id = form.querySelector('[name="distribution_id"]').value;
        const qVal = parseFloat(form.querySelector('[name="quantity"]').value || '0');
        const kVal = parseFloat(form.querySelector('[name="koli"]').value || '0');
        const remQty = parseFloat(form.dataset.remQty || '0');
        const remKoli = parseFloat(form.dataset.remKoli || '0');

        // Validasi: tidak boleh melebihi sisa
        if (!isNaN(remQty) && qVal > remQty) {
            Swal.fire({ icon:'error', text: 'Qty tidak boleh melebihi sisa Qty.' });
            return;
        }
        if (!isNaN(remKoli) && (kVal - remKoli) > 0.0001) {
            Swal.fire({ icon:'error', text: 'Koli tidak boleh melebihi sisa Koli.' });
            return;
        }

        const body = new URLSearchParams(new FormData(form));
        Swal.fire({
            title: 'Simpan perubahan?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Ya, simpan',
            cancelButtonText: 'Batal'
        }).then(function(r){
            if(!r.isConfirmed) return;
            fetch("{{ route('admin.stok-masuk.pengadaan.distributions.update', ':id') }}".replace(':id', id), {
                method: 'POST',
                headers: { 'X-Requested-With':'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
                body
            }).then(res => res.json())
              .then(function(resp){
                if (resp.success) {
                    Swal.fire({ icon:'success', text: resp.message || 'Berhasil disimpan' })
                        .then(() => window.location.reload());
                } else {
                    Swal.fire({ icon:'error', text: resp.message || 'Gagal menyimpan' });
                }
            }).catch(() => Swal.fire({ icon:'error', text:'Gagal menyimpan' }));
        });
    });

    // Client-side search filter for distributions table
    (function(){
        const input = document.getElementById('dist-search');
        const clearBtn = document.getElementById('dist-search-clear');
        const table = document.getElementById('dist-table');
        const tbody = table ? table.querySelector('tbody') : null;
        const noMatchRow = document.getElementById('dist-no-match');
        if (!input || !tbody) return;

        let timer;
        const normalize = (s) => (s || '').toString().toLowerCase().normalize('NFKD');
        const matches = (rowText, q) => rowText.indexOf(q) !== -1;

        const filter = () => {
            const q = normalize(input.value.trim());
            let visible = 0;
            tbody.querySelectorAll('tr').forEach(tr => {
                // Skip the static empty row and no-match row
                if (tr.id === 'dist-no-match') return;
                const text = normalize(tr.textContent);
                const show = !q || matches(text, q);
                tr.style.display = show ? '' : 'none';
                if (show) visible++;
            });
            if (noMatchRow) noMatchRow.style.display = (q && visible === 0) ? '' : 'none';
        };

        const debounced = () => { clearTimeout(timer); timer = setTimeout(filter, 150); };
        input.addEventListener('input', debounced);
        clearBtn && clearBtn.addEventListener('click', function(){
            input.value = '';
            filter();
            input.focus();
        });
    })();
});
</script>
@endpush
