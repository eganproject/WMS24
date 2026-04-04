@extends('layouts.app')

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Edit Retur Out',
        'breadcrumbs' => ['Admin', 'Stok Keluar', 'Retur Out', 'Edit'],
    ])
@endpush

@section('content')
<div class="content flex-row-fluid" id="kt_content">
  <div class="card">
    <div class="card-body">
      <form id="ri-form" action="{{ route('admin.stok-keluar.retur-out.update', $ri->id) }}" method="POST">@csrf @method('PUT')
        <div class="row">
          <div class="col-md-4 mb-5">
            <label class="form-label required">Kode</label>
            <input type="text" name="code" class="form-control form-control-solid" value="{{ $ri->code }}" readonly>
          </div>
          <div class="col-md-4 mb-5">
            <label class="form-label required">Tanggal Retur</label>
            <input type="text" name="return_date" id="return_date" class="form-control form-control-solid" value="{{ optional($ri->return_date)->format('Y-m-d') }}">
          </div>
          <div class="col-md-4 mb-5">
            <label class="form-label required">Gudang</label>
            @if(auth()->user()->warehouse_id)
              <input type="text" class="form-control form-control-solid" value="{{ auth()->user()->warehouse->name }}" readonly />
              <input type="hidden" name="warehouse_id" id="warehouse_id" value="{{ auth()->user()->warehouse_id }}" />
            @else
              <select name="warehouse_id" id="warehouse_id" class="form-select form-select-solid" data-control="select2" data-placeholder="Pilih gudang">
                <option></option>
                @foreach($warehouses as $w)
                  <option value="{{ $w->id }}" @selected($ri->warehouse_id==$w->id)>{{ $w->name }}</option>
                @endforeach
              </select>
            @endif
          </div>
        </div>

        <div class="mb-5">
          <label class="form-label">Deskripsi</label>
          <input type="text" name="description" class="form-control form-control-solid" value="{{ $ri->description }}" />
        </div>

        <h4 class="mt-10">Detail Barang</h4>
        <div class="table-responsive">
          <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4" id="detail-table">
            <thead>
              <tr class="fw-bolder text-muted">
                <th class="min-w-350px">Item</th>
                <th class="min-w-150px">Stok Tersedia</th>
                <th class="min-w-150px">Qty</th>
                <th class="min-w-150px">Koli</th>
                <th class="text-end min-w-100px">Aksi</th>
              </tr>
            </thead>
            <tbody></tbody>
            <tfoot>
              <tr>
                <td colspan="5"><button type="button" id="add-row" class="btn btn-light-primary">+ Tambah Baris</button></td>
              </tr>
            </tfoot>
          </table>
        </div>

        <div class="mt-10">
          <button type="submit" class="btn btn-primary">Simpan</button>
          <a href="{{ route('admin.stok-keluar.retur-out.index') }}" class="btn btn-light">Batal</a>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
  $(function(){
    const inventory = @json($inventory ?? []);
    let idx = 0;

    function rowHtml(i){
      return `
      <tr data-index="${i}">
        <td>
          <select name="details[${i}][item_id]" class="form-select form-select-solid item-select" data-placeholder="Pilih item"></select>
          <div class="invalid-feedback d-block item-error" style="display:none"></div>
        </td>
        <td><span class="available-stock text-muted">-</span></td>
        <td><input type="number" name="details[${i}][quantity]" class="form-control form-control-solid quantity-input" min="0" step="any" value="0"></td>
        <td><input type="number" name="details[${i}][koli]" class="form-control form-control-solid koli-input" min="0" step="any" value="0"></td>
        <td class="text-end"><button type="button" class="btn btn-icon btn-sm btn-danger remove-row"><i class="bi bi-trash"></i></button></td>
      </tr>`;
    }

    function updateSelectOptions($sel, warehouseId){
      const selected = $sel.val();
      $sel.empty().append($('<option>'));
      const items = (inventory[warehouseId] || []);
      if (!items.length) {
        const opt = new Option('Tidak ada item di gudang', '', false, false);
        $(opt).prop('disabled', true);
        $(opt).attr('data-quantity', 0);
        $(opt).attr('data-item-koli', 0);
        $sel.append(opt);
      } else {
        items.forEach(function(inv){
          const opt = new Option(inv.item.nama_barang + ' (SKU: ' + inv.item.sku + ')', inv.item_id, false, false);
          $(opt).attr('data-quantity', inv.quantity);
          $(opt).attr('data-item-koli', inv.item.koli ?? 1);
          $(opt).attr('data-koli', inv.koli ?? 0);
          $sel.append(opt);
        });
      }
      $sel.val(selected).trigger('change');
    }

    function addRow(prefill){
      const warehouseId = $('#warehouse_id').val();
      if (!warehouseId) { Swal.fire('Peringatan','Pilih gudang terlebih dahulu','warning'); return; }
      const $row = $(rowHtml(idx));
      $('#detail-table tbody').append($row);
      const $sel = $row.find('.item-select');
      updateSelectOptions($sel, warehouseId);
      $sel.select2({ width: '100%', placeholder: 'Pilih item' });
      if (prefill) {
        $sel.val(prefill.item_id).trigger('change');
        $row.find('.quantity-input').val(prefill.quantity || 0);
        $row.find('.koli-input').val(prefill.koli || 0);
      }
      idx++;
    }

    $('#add-row').on('click', function(){ addRow(); });
    $('#detail-table').on('click', '.remove-row', function(){ $(this).closest('tr').remove(); });

    $('#warehouse_id').on('change', function(){ $('#detail-table tbody').empty(); idx=0; if($(this).val()) addRow(); });

    $('#detail-table').on('change', '.item-select', function(){
      const currentSelect = this;
      const selectedItemId = $(currentSelect).val();

      // Duplicate check
      if (selectedItemId) {
          let isDuplicate = false;
          $('.item-select').not(currentSelect).each(function() {
              if ($(this).val() === selectedItemId) {
                  isDuplicate = true;
                  return false;
              }
          });

          if (isDuplicate) {
              Swal.fire('Peringatan', 'Item ini sudah dipilih di baris lain.', 'warning');
              $(currentSelect).val(null).trigger('change'); // Reset select2
              $(currentSelect).closest('tr').find('.available-stock').text('-');
              $(currentSelect).closest('tr').find('.quantity-input').val(0);
              $(currentSelect).closest('tr').find('.koli-input').val(0);
              return;
          }
      }

      const $row = $(this).closest('tr');
      const qtyInput = $row.find('.quantity-input');
      const selected = $(this).find('option:selected');
      const avail = parseFloat(selected.data('quantity')) || 0;
      const availKoli = parseFloat(selected.data('koli')) || 0;

      if (!selectedItemId) { // If no item is selected, just show '-'
          $row.find('.available-stock').text('-');
      } else if (avail <= 0 && availKoli <= 0) {
        $row.find('.available-stock').text('tidak ada item digudang');
      } else {
        $row.find('.available-stock').text(avail + ' pcs / ' + availKoli + ' koli');
      }
      
      // Only trigger input event if an item is actually selected
      if (selectedItemId) {
        if (parseFloat(qtyInput.val()) <= 0) {
          qtyInput.val(1).trigger('input');
        } else {
          qtyInput.trigger('input');
        }
      }
    });

    $('#detail-table').on('input', '.quantity-input', function(){
      const $row = $(this).closest('tr');
      const selectedOption = $row.find('.item-select option:selected');
      const itemKoli = parseFloat(selectedOption.data('item-koli')) || 1;
      let quantity = parseFloat($(this).val()) || 0;
      const koliInput = $row.find('.koli-input');
      const availableQuantity = parseFloat(selectedOption.data('quantity')) || 0;

      if (quantity < 0) {
          quantity = 0;
          $(this).val(0);
      }

      if (!isNaN(availableQuantity) && quantity > availableQuantity) {
          Swal.fire({
              icon: 'warning',
              title: 'Stok Tidak Cukup',
              text: `Stok yang tersedia hanya ${availableQuantity}. Kuantitas telah disesuaikan.`
          });
          $(this).val(availableQuantity);
          quantity = availableQuantity;
      }

      if (!isNaN(quantity) && !isNaN(itemKoli) && itemKoli > 0) {
          const koliValue = quantity / itemKoli;
          koliInput.val(koliValue.toFixed(2));
      } else {
          koliInput.val(0);
      }
    });

    $('#detail-table').on('input', '.koli-input', function(){
      const $row = $(this).closest('tr');
      const selectedOption = $row.find('.item-select option:selected');
      const itemKoli = parseFloat(selectedOption.data('item-koli')) || 1;
      const availKoli = parseFloat(selectedOption.data('koli')) || 0;
      let koli = parseFloat($(this).val()) || 0;
      const quantityInput = $row.find('.quantity-input');
      const availableQuantity = parseFloat(selectedOption.data('quantity')) || 0;

      if (koli < 0) {
          koli = 0;
          $(this).val(0);
      }

      if (!isNaN(koli) && !isNaN(itemKoli) && itemKoli > 0) {
          let newQuantity = Math.ceil(koli * itemKoli);

          if (!isNaN(availableQuantity) && newQuantity > availableQuantity) {
              const maxKoli = Math.floor(availableQuantity / itemKoli);
              // Use the smaller of the calculated max koli or the koli from inventory data
              const effectiveMaxKoli = Math.min(maxKoli, availKoli);
              
              Swal.fire({
                  icon: 'warning',
                  title: 'Stok Tidak Cukup',
                  text: `Stok yang tersedia hanya ${availableQuantity} (${effectiveMaxKoli.toFixed(2)} koli). Jumlah koli telah disesuaikan.`
              });
              
              const cappedQty = Math.ceil(effectiveMaxKoli * itemKoli);
              quantityInput.val(cappedQty > availableQuantity ? availableQuantity : cappedQty);
              $(this).val(effectiveMaxKoli.toFixed(2));

          } else {
              quantityInput.val(newQuantity);
          }
      } else {
          quantityInput.val(0);
      }
    });

    // Prefill existing details
    const details = @json($ri->details->map(fn($d)=>['item_id'=>$d->item_id,'quantity'=>$d->quantity,'koli'=>$d->koli]));
    if ($('#warehouse_id').val()) {
      if (details.length) {
        details.forEach(function(d){ addRow(d); });
      } else { addRow(); }
    }

    $('#ri-form').on('submit', function(e) {
      e.preventDefault(); // Prevent default submission
      let isValid = true;
      const form = this;

      if ($('#detail-table tbody tr').length === 0) {
          Swal.fire('Peringatan', 'Tambahkan minimal 1 item', 'warning');
          return;
      }

      $('.item-select').each(function() {
          const $td = $(this).closest('td');
          const $errorDiv = $td.find('.item-error');

          if (!$(this).val()) {
              isValid = false;
              $errorDiv.show().text('Item harus dipilih');
          } else {
              $errorDiv.hide().text('');
          }
      });

      $('.quantity-input').each(function() {
          const v = parseFloat($(this).val()) || 0;
          if (v <= 0) {
              isValid = false;
              $(this).addClass('is-invalid');
          } else {
              $(this).removeClass('is-invalid');
          }
      });

      if (isValid) {
          Swal.fire({
              text: "Apakah Anda yakin ingin menyimpan data ini?",
              icon: "question",
              showCancelButton: true,
              buttonsStyling: false,
              confirmButtonText: "Ya, simpan!",
              cancelButtonText: "Tidak, batalkan",
              customClass: {
                  confirmButton: "btn fw-bold btn-primary",
                  cancelButton: "btn fw-bold btn-active-light-primary"
              }
          }).then(function (result) {
              if (result.isConfirmed) {
                  form.submit(); // Submit programmatically
              }
          });
      } else {
          Swal.fire('Peringatan', 'Perbaiki isian sebelum menyimpan. Pastikan semua item dan kuantitas valid.', 'warning');
      }
    });
  });
</script>
@endpush
