@extends('layouts.app')

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Edit Penerimaan Retur',
        'breadcrumbs' => ['Admin', 'Stok Masuk', 'Penerimaan Retur', 'Edit'],
    ])
@endpush

@push('styles')
    <style>
        .section-title { font-size: 1.15rem; font-weight: 700; color: #111827; }
        .help-muted { font-size: .85rem; color: #6B7280; }
        #items-toolbar { display: flex; gap: .75rem; align-items: center; justify-content: space-between; margin-bottom: .75rem; }
        #items-toolbar .summary { font-weight: 600; color: #334155; }
        #details-table thead th { position: sticky; top: 0; background: #fff; z-index: 1; }
        #details-table tbody tr td { vertical-align: middle; }
    </style>
@endpush

@section('content')
<div class="content flex-row-fluid" id="kt_content">
  <div class="card">
    <div class="card-body pt-6">
      @if ($errors->any())
        <div class="alert alert-danger mb-6"><ul class="mb-0 ps-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
      @endif
      <form method="POST" action="{{ route('admin.stok-masuk.penerimaan-retur.update', $rr->id) }}" id="rr-edit-form">@csrf @method('PUT')
        <div class="row mb-7">
          <div class="col-md-4"><label class="form-label required">Kode</label><input type="text" name="code" class="form-control form-control-solid" value="{{ $rr->code }}" readonly></div>
          <div class="col-md-4"><label class="form-label required">Tanggal</label><input type="date" name="return_date" class="form-control form-control-solid flatpickr-date" value="{{ optional($rr->return_date)->format('Y-m-d') }}"></div>
          <div class="col-md-4"><label class="form-label required">Gudang</label>
            @php($forcedWarehouseId = optional(auth()->user())->warehouse_id)
            <select name="warehouse_id" id="warehouse_id" class="form-select form-select-solid" data-control="select2" @if($forcedWarehouseId) disabled @endif>
              @foreach($warehouses as $w)
                <option value="{{ $w->id }}" @selected(($forcedWarehouseId ?? $rr->warehouse_id) == $w->id)>{{ $w->name }}</option>
              @endforeach
            </select>
            @if($forcedWarehouseId)
              <input type="hidden" name="warehouse_id" value="{{ $forcedWarehouseId }}">
            @endif
          </div>
        </div>
        <div class="mb-7"><label class="form-label">Deskripsi</label><textarea name="description" class="form-control form-control-solid" rows="3">{{ $rr->description }}</textarea></div>

        <div class="mb-5">
          <div id="items-toolbar">
            <div class="w-50">
              <div class="position-relative">
                <i class="fas fa-search text-gray-400 position-absolute ms-3 mt-2"></i>
                <input type="text" id="item-search" class="form-control form-control-sm form-control-solid ps-10" placeholder="Cari item (SKU/Nama)">
              </div>
              <div class="help-muted mt-1">Gunakan kolom ini untuk memfilter baris item.</div>
            </div>
            <div class="d-flex align-items-center gap-3">
              <div class="summary" id="items-summary">0 item • Total: 0 Qty | 0 Koli</div>
              <button type="button" class="btn btn-sm btn-light-primary" id="add-row">Tambah Baris</button>
            </div>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table align-middle" id="details-table">
            <thead><tr><th style="width:40%">Item</th><th class="text-end" style="width:15%">Qty</th><th class="text-end" style="width:15%">Koli</th><th style="width:25%">Catatan</th><th style="width:5%"></th></tr></thead>
            <tbody>
              @foreach($rr->details as $i => $d)
              <tr>
                <td>
                  <select name="details[{{ $i }}][item_id]" class="form-select form-select-solid" data-control="select2">
                    @foreach($items as $it)
                      <option value="{{ $it->id }}" @selected($it->id == $d->item_id)>{{ $it->sku }} - {{ $it->nama_barang }}</option>
                    @endforeach
                  </select>
                </td>
                <td><input type="number" step="1" min="0" name="details[{{ $i }}][quantity]" class="form-control form-control-solid text-end qty-input" value="{{ (int) $d->quantity }}"></td>
                <td><input type="number" step="0.01" min="0" name="details[{{ $i }}][koli]" class="form-control form-control-solid text-end koli-input" value="{{ number_format((float) $d->koli, 2, '.', '') }}"></td>
                <td><input type="text" name="details[{{ $i }}][notes]" class="form-control form-control-solid" value="{{ $d->notes }}"></td>
                <td class="text-center">
                  <button type="button" class="btn btn-icon btn-light-danger del-row"><i class="fas fa-trash"></i></button>
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        <div class="d-flex justify-content-end mt-7">
          <a href="{{ route('admin.stok-masuk.penerimaan-retur.index') }}" class="btn btn-light me-3">Kembali</a>
      <button type="submit" class="btn btn-primary">Update</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function(){
    flatpickr('.flatpickr-date', { dateFormat: 'Y-m-d' });
    $('[data-control="select2"]').select2({ width: '100%' });

    const items = @json($items->map(fn($i)=>[
      'id'=>$i->id,
      'label'=>($i->sku.' - '.$i->nama_barang),
      'koli_ratio'=>($i->koli ?? 1)
    ])->values());
    const itemMap = Object.fromEntries(items.map(i=>[String(i.id), i]));
    let idx = $('#details-table tbody tr').length;

    function addRow(){
      const options = items.map(i=>`<option value="${i.id}">${i.label}</option>`).join('');
      const tr = $(`
        <tr data-koli-ratio="1" data-name="">
          <td>
            <select name="details[${idx}][item_id]" class="form-select form-select-solid" data-control="select2">
              <option value="">Pilih Item</option>${options}
            </select>
          </td>
          <td><input type="number" step="0.01" min="0" name="details[${idx}][quantity]" class="form-control form-control-solid text-end qty-input" value="0"></td>
          <td><input type="number" step="0.01" min="0" name="details[${idx}][koli]" class="form-control form-control-solid text-end koli-input" value="0"></td>
          <td><input type="text" name="details[${idx}][notes]" class="form-control form-control-solid" /></td>
          <td class="text-center"><button type="button" class="btn btn-icon btn-light-danger del-row"><i class="fas fa-trash"></i></button></td>
        </tr>`);
      $('#details-table tbody').append(tr);
      idx++;
      tr.find('[data-control="select2"]').select2({ width: '100%' });
      updateSummary();
    }

    $('#add-row').on('click', addRow);
    $(document).on('click', '.del-row', function(){
      $(this).closest('tr').remove();
      updateSummary();
    });

    function updateSummary(){
      let totalQty=0, totalKoli=0, count=0;
      $('#details-table tbody tr:visible').each(function(){
        const q = parseFloat($(this).find('.qty-input').val())||0;
        const k = parseFloat($(this).find('.koli-input').val())||0;
        totalQty += q; totalKoli += k; count += 1;
      });
      $('#items-summary').text(`${count} item • Total: ${totalQty} Qty | ${totalKoli} Koli`);
    }

    // Search filter like create
    $('#item-search').on('input', function(){
      const q = ($(this).val()||'').toString().toLowerCase();
      $('#details-table tbody tr').each(function(){
        const name = ($(this).attr('data-name')||'').toString();
        $(this).toggle(!q || name.includes(q));
      });
      updateSummary();
    });

    // Initialize per row ratio based on selected item
    $('#details-table tbody tr').each(function(){
      const $row = $(this);
      const itemId = $row.find('select[name$="[item_id]"]').val();
      const meta = itemMap[String(itemId)];
      const r = meta ? (meta.koli_ratio || 1) : 1;
      $row.attr('data-koli-ratio', r);
      $row.data('koli-ratio', r);
      $row.attr('data-name', (meta?.label||'').toString().toLowerCase());
    });

    function isDuplicateSelection(val, currentSelect){
      let seen = 0; const v = String(val||'');
      $('#details-table tbody select[name$="[item_id]"]').each(function(){
        if (this === currentSelect) return; if (String($(this).val())===v) seen++;
      });
      return seen > 0;
    }

    $(document).on('change', 'select[name$="[item_id]"]', function(){
      const $row = $(this).closest('tr');
      const val = $(this).val();
      const meta = itemMap[String(val)];
      if (val && isDuplicateSelection(val, this)) {
        Swal.fire({ icon:'warning', text:'Item sudah dipilih pada baris lain.' });
        $(this).val('').trigger('change');
        return;
      }
      if (meta) {
        const r = meta.koli_ratio || 1;
        $row.attr('data-koli-ratio', r);
        $row.data('koli-ratio', r);
        $row.attr('data-name', (meta.label||'').toString().toLowerCase());
      } else {
        $row.attr('data-koli-ratio', 1);
        $row.data('koli-ratio', 1);
        $row.attr('data-name', '');
      }

      // Recalculate dependent field using rule: koli = qty / ratio, qty = koli * ratio
      const ratio = parseFloat($row.attr('data-koli-ratio')) || 1;
      const $qty = $row.find('.qty-input');
      const $koli = $row.find('.koli-input');
      let qty = parseFloat($qty.val());
      let koli = parseFloat($koli.val());
      if (!isNaN(qty) && qty > 0) {
        const newKoli = ratio > 0 ? qty / ratio : 0;
        $koli.val((Math.round(newKoli*100)/100).toFixed(2));
      } else if (!isNaN(koli) && koli > 0) {
        const newQty = koli * ratio;
        $qty.val((Math.round(newQty*100)/100).toFixed(2));
      }
      updateSummary();
    });

    $(document).on('input', '.qty-input', function(){
      const $row = $(this).closest('tr');
      const ratio = parseFloat($row.attr('data-koli-ratio')) || 1;
      let qty = parseFloat($(this).val()); if (isNaN(qty)||qty<0) qty = 0; qty = Math.round(qty); $(this).val(qty);
      const koli = ratio > 0 ? qty / ratio : 0;
      $row.find('.koli-input').val((Math.round(koli*100)/100).toFixed(2));
      updateSummary();
    });

    $(document).on('input', '.koli-input', function(){
      const $row = $(this).closest('tr');
      const ratio = parseFloat($row.attr('data-koli-ratio')) || 1;
      let koli = parseFloat($(this).val()); if (isNaN(koli)||koli<0) koli = 0;
      const qty = Math.round(koli * ratio);
      $row.find('.qty-input').val(qty);
      updateSummary();
    });

    // Confirm submit
    $('#rr-edit-form').on('submit', function(e){
      e.preventDefault();
      // Basic validation like create: at least 1 row, each row must have item and (qty>0 or koli>0)
      const rows = $('#details-table tbody tr');
      if (rows.length === 0) {
        Swal.fire({ icon:'warning', text:'Tambahkan minimal satu baris item.' });
        return;
      }
      let valid = true;
      rows.each(function(){
        const item = $(this).find('select[name$="[item_id]"]').val();
        const qty = parseFloat($(this).find('.qty-input').val())||0;
        const koli = parseFloat($(this).find('.koli-input').val())||0;
        if (!item || (qty<=0 && koli<=0)) { valid = false; return false; }
      });
      if (!valid) { Swal.fire({ icon:'warning', text:'Lengkapi item dan isi Qty atau Koli (>0).' }); return; }

      Swal.fire({ title:'Apakah Anda yakin?', text:'Pastikan semua data sudah benar.', icon:'warning', showCancelButton:true, confirmButtonText:'Ya, update!', cancelButtonText:'Batal' }).then((res)=>{ if(res.isConfirmed){ e.currentTarget.submit(); } });
    });

    // Initial summary
    updateSummary();
  });
</script>
@endpush
