@extends('layouts.app')

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Edit Stock Opname',
        'breadcrumbs' => ['Admin', 'Manajemen Stok', 'Stock Opname', 'Edit'],
    ])
@endpush

@section('content')
<div class="content flex-row-fluid" id="kt_content">
    <form id="opname-form" action="{{ route('admin.manajemenstok.stok-opname.update', $stok_opname->id) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="card">
            <div class="card-header border-0 py-5">
                <h3 class="card-title fw-bolder mb-0">Edit Stock Opname</h3>
                <div class="card-toolbar">
                    <a href="{{ route('admin.manajemenstok.stok-opname.index') }}" class="btn btn-light me-2">Batal</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Simpan Perubahan
                    </button>
                </div>
            </div>
            <div class="card-body pt-0">
                @if ($errors->any())
                    <div class="alert alert-danger d-flex align-items-center p-4 mb-6">
                        <i class="fas fa-exclamation-triangle me-3"></i>
                        <div>
                            <div class="fw-bold mb-2">Terdapat kesalahan pada input Anda:</div>
                            <ul class="mb-0 ps-6">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif
                <div class="row g-5">
                    <div class="col-xl-4">
                        <div class="fv-row">
                            <label class="required form-label">Kode</label>
                            <input type="text" name="code" class="form-control form-control-white" value="{{ old('code', $stok_opname->code) }}" readonly>
                            <div class="form-text">Kode dokumen tidak dapat diubah.</div>
                        </div>
                    </div>
                    <div class="col-xl-4">
                        <div class="fv-row">
                            <label class="required form-label">Tanggal Mulai</label>
                            <input type="text" name="start_date" class="form-control form-control-solid flatpickr-input @error('start_date') is-invalid @enderror" value="{{ old('start_date', $stok_opname->start_date) }}" required>
                            @error('start_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-xl-4">
                        <div class="fv-row">
                            <label class="required form-label">Gudang</label>
                            @if(auth()->user()->warehouse_id)
                                <input type="hidden" name="warehouse_id" value="{{ auth()->user()->warehouse_id }}">
                                <input type="text" class="form-control form-control-solid" value="{{ auth()->user()->warehouse->name }}" readonly>
                            @else
                                <select name="warehouse_id" class="form-select form-select-solid @error('warehouse_id') is-invalid @enderror" data-control="select2" required>
                                    @foreach($warehouses as $w)
                                        <option value="{{ $w->id }}" {{ old('warehouse_id', $stok_opname->warehouse_id)==$w->id ? 'selected' : '' }}>{{ $w->name }}</option>
                                    @endforeach
                                </select>
                                @error('warehouse_id')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            @endif
                        </div>
                    </div>
                </div>

                <div class="row g-5 mt-1">
                    <div class="col-12">
                        <label class="form-label">Deskripsi</label>
                        <textarea name="description" class="form-control form-control-solid" rows="2" placeholder="Catatan tambahan (opsional)">{{ old('description', $stok_opname->description) }}</textarea>
                    </div>
                </div>

                <div class="separator my-7"></div>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="mb-0">Daftar Item</h4>
                    <button type="button" class="btn btn-light-primary" id="add-item-btn">
                        <i class="fas fa-plus me-1"></i> Tambah Item
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-4" id="items-table">
                        <thead>
                            <tr class="text-start text-gray-500 fw-bolder fs-7 text-uppercase gs-0">
                                <th class="min-w-250px">Item</th>
                                <th class="min-w-120px">Qty Sistem</th>
                                <th class="min-w-120px">Koli Sistem</th>
                                <th class="min-w-120px">Qty Fisik</th>
                                <th class="min-w-120px">Koli Fisik</th>
                                <th class="min-w-120px">Selisih Qty</th>
                                <th class="min-w-120px">Selisih Koli</th>
                                <th class="min-w-200px">Keterangan</th>
                                <th class="w-50px text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($stok_opname->items as $i => $it)
                            <tr data-index="{{ $i }}">
                                <td>
                                    <select name="items[{{ $i }}][item_id]" class="form-select form-select-solid item-select" data-control="select2" required>
                                        @foreach($items as $item)
                                            <option value="{{ $item->id }}" {{ $item->id == $it->item_id ? 'selected' : '' }}>{{ $item->sku ? $item->sku.' - ' : '' }}{{ $item->nama_barang }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><input type="number" step="any" name="items[{{ $i }}][system_quantity]" class="form-control form-control-white sys-qty" value="{{ $it->system_quantity }}" readonly></td>
                                <td><input type="number" step="any" name="items[{{ $i }}][system_koli]" class="form-control form-control-white sys-koli" value="{{ $it->system_koli }}" readonly></td>
                                <td><input type="number" step="any" name="items[{{ $i }}][physical_quantity]" class="form-control form-control-solid phy-qty" value="{{ $it->physical_quantity }}"></td>
                                <td><input type="number" step="any" name="items[{{ $i }}][physical_koli]" class="form-control form-control-solid phy-koli" value="{{ $it->physical_koli }}"></td>
                                <td><input type="number" step="any" class="form-control form-control-white disc-qty" value="{{ $it->discrepancy_quantity }}" readonly></td>
                                <td><input type="number" step="any" class="form-control form-control-white disc-koli" value="{{ $it->discrepancy_koli }}" readonly></td>
                                <td><input type="text" name="items[{{ $i }}][description]" class="form-control form-control-solid" value="{{ $it->description }}"/></td>
                                <td class="text-center"><button type="button" class="btn btn-sm btn-icon btn-light-danger remove-row" title="Hapus"><i class="fas fa-times"></i></button></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-end py-4">
                <a href="{{ route('admin.manajemenstok.stok-opname.index') }}" class="btn btn-light me-3">Batal</a>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Simpan Perubahan</button>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
const itemsData = @json($items);
let rowIndex = {{ count($stok_opname->items) }};

function addRow(){
    const row = `
    <tr data-index="${rowIndex}">
        <td>
            <select name="items[${rowIndex}][item_id]" class="form-select form-select-solid item-select" data-control="select2" required></select>
        </td>
        <td><input type="number" step="any" name="items[${rowIndex}][system_quantity]" class="form-control form-control-solid sys-qty" value="0"></td>
        <td><input type="number" step="any" name="items[${rowIndex}][system_koli]" class="form-control form-control-solid sys-koli" value="0"></td>
        <td><input type="number" step="any" name="items[${rowIndex}][physical_quantity]" class="form-control form-control-solid phy-qty" value="0"></td>
        <td><input type="number" step="any" name="items[${rowIndex}][physical_koli]" class="form-control form-control-solid phy-koli" value="0"></td>
        <td><input type="number" step="any" class="form-control form-control-solid disc-qty" value="0" readonly></td>
        <td><input type="number" step="any" class="form-control form-control-solid disc-koli" value="0" readonly></td>
        <td><input type="text" name="items[${rowIndex}][description]" class="form-control form-control-solid"/></td>
        <td><button type="button" class="btn btn-sm btn-danger remove-row">X</button></td>
    </tr>`;
    $('#items-table tbody').append(row);

    const sel = $(`tr[data-index='${rowIndex}'] .item-select`);
    sel.append(new Option('', '', true, true));
    itemsData.forEach(function(it){
        const text = it.sku ? `${it.sku} - ${it.nama_barang}` : it.nama_barang;
        sel.append(new Option(text, it.id));
    });
    if ($.fn.select2) {
        sel.select2({ placeholder: 'Pilih Item', width: '100%' });
    }
    sel.val(null).trigger('change');
    refreshItemSelectOptions();
    rowIndex++;
}

// Prevent-duplicate helpers (global scope)
function getSelectedItemIds(){
    const ids = [];
    $('#items-table tbody .item-select').each(function(){ const v = $(this).val(); if (v) ids.push(String(v)); });
    return ids;
}
function refreshItemSelectOptions(){
    const used = new Set(getSelectedItemIds());
    $('#items-table tbody .item-select').each(function(){
        const current = $(this).val();
        $(this).find('option').each(function(){
            const val = $(this).attr('value'); if(!val) return;
            const disable = used.has(String(val)) && String(val) !== String(current);
            $(this).prop('disabled', disable);
        });
        $(this).trigger('change.select2');
    });
}
function ensureUniqueSelection(selectEl){
    const val = $(selectEl).val(); if(!val) return;
    let cnt = 0; $('#items-table tbody .item-select').each(function(){ if(String($(this).val())===String(val)) cnt++; });
    if (cnt>1){ if(window.toastr) toastr.error('Item sudah dipilih pada baris lain.'); $(selectEl).val(null).trigger('change'); }
}

function recalc($tr){
    const sq = parseFloat($tr.find('.sys-qty').val()) || 0;
    const pq = parseFloat($tr.find('.phy-qty').val()) || 0;
    const sk = parseFloat($tr.find('.sys-koli').val()) || 0;
    const pk = parseFloat($tr.find('.phy-koli').val()) || 0;
    $tr.find('.disc-qty').val((pq - sq).toFixed(2));
    $tr.find('.disc-koli').val((pk - sk).toFixed(2));
}

function getProductKoliForRow($tr){
    const itemId = parseInt($tr.find('.item-select').val() || 0);
    if(!itemId) return 1;
    const it = itemsData.find(x => parseInt(x.id) === itemId);
    const k = it && it.koli ? parseFloat(it.koli) : 1;
    return (isFinite(k) && k > 0) ? k : 1;
}

$(document).ready(function(){
    $('.flatpickr-input').flatpickr({ dateFormat: 'Y-m-d' });
    if ($.fn.select2) {
        if($('select[name="warehouse_id"]').length) {
            $('select[name="warehouse_id"]').select2({ placeholder: 'Pilih Gudang', width: '100%' });
        }
        $('.item-select').select2({ placeholder: 'Pilih Item', width: '100%' });
    }
    // Initialize duplicate options state for existing rows
    refreshItemSelectOptions();
    // Toastr notifications for session and validation
    @if (session('success'))
        if (window.toastr) toastr.success("{{ session('success') }}");
    @endif
    @if (session('error'))
        if (window.toastr) toastr.error("{{ session('error') }}");
    @endif
    @if ($errors->any())
        if (window.toastr) {
            toastr.error(`{!! implode('<br>', $errors->all()) !!}`, 'Validasi Gagal');
        }
    @endif

    // SweetAlert confirm on submit
    $('#opname-form').on('submit', function(e){
        const form = this;
        if ($(form).data('confirmed')) { return; }
        e.preventDefault();
        Swal.fire({
            text: 'Apakah Anda yakin ingin menyimpan perubahan stock opname ini?',
            icon: 'question',
            showCancelButton: true,
            buttonsStyling: false,
            confirmButtonText: 'Ya, Simpan!',
            cancelButtonText: 'Tidak, Batalkan',
            customClass: {
                confirmButton: 'btn fw-bold btn-primary',
                cancelButton: 'btn fw-bold btn-active-light-primary'
            }
        }).then(function(result){
            if (result.value) {
                $(form).data('confirmed', true);
                form.submit();
            }
        });
    });
    $('#add-item-btn').on('click', addRow);
    $('#items-table').on('click','.remove-row', function(){ $(this).closest('tr').remove(); refreshItemSelectOptions(); });
    $('#items-table').on('input','.sys-qty,.sys-koli', function(){ recalc($(this).closest('tr')); });
    // Auto update counterpart for physical qty/koli
    $('#items-table').on('input','.phy-qty', function(){
        const $tr = $(this).closest('tr');
        const pq = parseFloat($(this).val()) || 0;
        const per = getProductKoliForRow($tr);
        const pk = per > 0 ? (pq / per) : 0;
        $tr.find('.phy-koli').val(pk.toFixed(2));
        recalc($tr);
    });
    $('#items-table').on('input','.phy-koli', function(){
        const $tr = $(this).closest('tr');
        const pk = parseFloat($(this).val()) || 0;
        const per = getProductKoliForRow($tr);
        const pq = pk * per;
        $tr.find('.phy-qty').val(pq);
        recalc($tr);
    });

    // Auto fill system qty/koli when item selected
    $('#items-table').on('change', '.item-select', function(){
        const $tr = $(this).closest('tr');
        const itemId = $(this).val();
        const warehouseId = $('[name="warehouse_id"]').val();
        ensureUniqueSelection(this); refreshItemSelectOptions();
        if (!itemId || !warehouseId) return;
        $.get(`{{ route('admin.manajemenstok.stok-opname.system-stock') }}`,
            { item_id: itemId, warehouse_id: warehouseId },
            function(res){
                $tr.find('.sys-qty').val(res.quantity);
                $tr.find('.sys-koli').val(res.koli);
                recalc($tr);
            }
        );
    });

    // When warehouse changes, refresh system stock for selected items
    $('[name="warehouse_id"]').on('change', function(){
        const warehouseId = $(this).val();
        $('#items-table tbody tr').each(function(){
            const $tr = $(this);
            const itemId = $tr.find('.item-select').val();
            if (!itemId) return;
            $.get(`{{ route('admin.manajemenstok.stok-opname.system-stock') }}`,
                { item_id: itemId, warehouse_id: warehouseId },
                function(res){
                    $tr.find('.sys-qty').val(res.quantity);
                    $tr.find('.sys-koli').val(res.koli);
                    recalc($tr);
                }
            );
        });
        refreshItemSelectOptions();
    });

    // Final duplicate check on submit
    $('#opname-form').on('submit', function(e){
        const seen = new Set(); let dup = false;
        $('#items-table tbody .item-select').each(function(){ const v = $(this).val(); if(!v) return; if(seen.has(String(v))) dup=true; else seen.add(String(v)); });
        if (dup){ e.preventDefault(); if(window.toastr) toastr.error('Tidak boleh memilih item yang sama pada lebih dari satu baris.', 'Validasi Gagal'); return false; }
    });
});
</script>
@endpush
