@extends('layouts.app')

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Edit Pengadaan',
        'breadcrumbs' => ['Admin', 'Stok Masuk', 'Edit Pengadaan'],
    ])
@endpush

@section('content')
    <div class="content flex-row-fluid" id="kt_content">
        <div class="card">
            <div class="card-body">
                <form id="pengadaan-form"
                    action="{{ route('admin.stok-masuk.pengadaan.update', $stockInOrder->id) }}"
                    method="POST">
                    @csrf
                    @method('PUT')
                    <div class="row">
                        <div class="col-md-6 mb-5">
                            <label class="form-label fs-6 fw-bolder text-dark">Kode Dokumen</label>
                            <input type="text" name="code" class="form-control form-control-solid"
                                value="{{ old('code', $stockInOrder->code) }}" readonly>
                        </div>
                        <div class="col-md-6 mb-5">
                            <label class="form-label fs-6 fw-bolder text-dark">Tanggal</label>
                            <input type="text" name="date"
                                class="form-control form-control-solid flatpickr-input @error('date') is-invalid @enderror"
                                value="{{ old('date', $stockInOrder->date) }}" required>
                            @error('date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-5">
                            <label class="form-label fs-6 fw-bolder text-dark">Tipe</label>
                            <select name="type" id="order-type" class="form-select form-select-solid @error('type') is-invalid @enderror" data-control="select2" data-placeholder="Pilih Tipe" required>
                                <option></option>
                                <option value="import" {{ old('type', $stockInOrder->type ?? 'import') == 'import' ? 'selected' : '' }}>Import</option>
                                <option value="produksi" {{ old('type', $stockInOrder->type) == 'produksi' ? 'selected' : '' }}>Produksi</option>
                                <option value="lainnya" {{ old('type', $stockInOrder->type) == 'lainnya' ? 'selected' : '' }}>Lainnya</option>
                            </select>
                            @error('type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-5">
                            @if (auth()->user()->warehouse_id)
                                <input type="hidden" name="warehouse_id" value="{{ auth()->user()->warehouse_id }}">
                                <label class="form-label fs-6 fw-bolder text-dark">Gudang</label>
                                <input type="text" class="form-control form-control-solid"
                                    value="{{ auth()->user()->warehouse->name }}" readonly>
                            @else
                                <label class="form-label fs-6 fw-bolder text-dark">Gudang</label>
                                <select name="warehouse_id"
                                    class="form-select form-select-solid @error('warehouse_id') is-invalid @enderror"
                                    data-control="select2" data-placeholder="Pilih Gudang" required>
                                    <option></option>
                                    @foreach ($warehouses as $warehouse)
                                        <option value="{{ $warehouse->id }}"
                                            {{ old('warehouse_id', $stockInOrder->warehouse_id) == $warehouse->id ? 'selected' : '' }}>
                                            {{ $warehouse->name }}</option>
                                    @endforeach
                                </select>
                                @error('warehouse_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            @endif
                        </div>
                        <div class="col-md-6 mb-5">
                            <label class="form-label fs-6 fw-bolder text-dark">Deskripsi</label>
                            <textarea name="description" class="form-control form-control-solid">{{ old('description', $stockInOrder->description) }}</textarea>
                        </div>
                    </div>

                    <div class="row" id="from-warehouse-row" style="display:none;">
                        <div class="col-md-6 mb-5">
                            <label class="form-label fs-6 fw-bolder text-dark">Dari Gudang</label>
                            <select name="from_warehouse_id" id="from-warehouse-id" class="form-select form-select-solid @error('from_warehouse_id') is-invalid @enderror" data-control="select2" data-placeholder="Pilih Gudang Asal">
                                <option></option>
                                @foreach ($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}" {{ old('from_warehouse_id', $stockInOrder->from_warehouse_id) == $warehouse->id ? 'selected' : '' }}>
                                        {{ $warehouse->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('from_warehouse_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <h3 class="mt-5">Item</h3>
                    <table class="table table-bordered" id="items-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th width="150px">Quantity</th>
                                <th width="150px">Koli</th>
                                <th width="50px">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($stockInOrder->items as $index => $existingItem)
                                <tr data-index="{{ $index }}">
                                    <td>
                                        <select name="items[{{ $index }}][item_id]" class="form-select form-select-solid item-select"
                                            data-control="select2" required>
                                            <option></option>
                                            @foreach ($items as $item)
                                                <option value="{{ $item->id }}" data-koli="{{ $item->koli ?? 1 }}"
                                                    {{ $existingItem->item_id == $item->id ? 'selected' : '' }}>
                                                    {{ $item->sku . ' - ' . $item->nama_barang }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td><input type="number" name="items[{{ $index }}][quantity]"
                                            class="form-control form-control-solid quantity-input" value="{{ (int) $existingItem->quantity }}"
                                            min="1" step="1" required></td>
                                    <td><input type="number" name="items[{{ $index }}][koli]"
                                            class="form-control form-control-solid koli-input" value="{{ $existingItem->koli }}"
                                            min="0" step="any"></td>
                                    <td><button type="button" class="btn btn-danger btn-sm remove-item-btn">X</button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <button type="button" class="btn btn-primary btn-sm" id="add-item-btn">Tambah Item</button>

                    <div class="d-flex justify-content-end mt-10">
                        <a href="{{ route('admin.stok-masuk.pengadaan.index') }}"
                            class="btn btn-light me-3">Batal</a>
                        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        var itemsData = @json($items);
        $(document).ready(function() {
            $(".flatpickr-input").flatpickr({
                dateFormat: "Y-m-d",
            });

            let itemIndex = {{ $stockInOrder->items->count() }};

            function getSelectedItemIds() {
                const ids = [];
                $('#items-table .item-select').each(function() {
                    const v = $(this).val();
                    if (v) ids.push(v.toString());
                });
                return ids;
            }

            function toggleFromWarehouse() {
                const type = $('#order-type').val();
                const show = (type === 'produksi' || type === 'lainnya');
                if (show) {
                    $('#from-warehouse-row').show();
                    $('#from-warehouse-id').prop('required', true);
                } else {
                    $('#from-warehouse-row').hide();
                    $('#from-warehouse-id').prop('required', false).val(null).trigger('change');
                }
            }

            // Initialize visibility and bind
            toggleFromWarehouse();
            $('#order-type').on('change', toggleFromWarehouse);

            function initializeSelect2(selector) {
                selector.select2({
                    placeholder: "Pilih Item",
                });
            }

            // Inisialisasi select2 untuk baris yang sudah ada
            $('#items-table .item-select').each(function() {
                initializeSelect2($(this));
            });

            function addNewRow() {
                const selectedIds = getSelectedItemIds();
                const available = itemsData.filter(i => !selectedIds.includes(String(i.id)));
                if (available.length === 0) {
                    Swal.fire({
                        text: "Semua item sudah dipilih. Tidak bisa menambah baris.",
                        icon: "info",
                        buttonsStyling: false,
                        confirmButtonText: "Ok",
                        customClass: { confirmButton: "btn btn-primary" }
                    });
                    return;
                }

                let newRow = `
                    <tr data-index="${itemIndex}">
                        <td>
                            <select name="items[${itemIndex}][item_id]" class="form-select form-control-select form-select-solid item-select" data-control="select2" required></select>
                        </td>
                        <td><input type="number" name="items[${itemIndex}][quantity]" class="form-control form-control-solid quantity-input" value="1" min="1" step="1" required></td>
                        <td><input type="number" name="items[${itemIndex}][koli]" class="form-control form-control-solid koli-input" value="0" min="0" step="any"></td>
                        <td><button type="button" class="btn btn-danger btn-sm remove-item-btn">X</button></td>
                    </tr>`;
                $('#items-table tbody').append(newRow);

                let newSelect = $('tr[data-index="' + itemIndex + '"] .item-select');
                newSelect.append(new Option('', '', true, true));
                available.forEach(function(item) {
                    let option = new Option(item.sku + ' - ' + item.nama_barang, item.id, false, false);
                    $(option).attr('data-koli', item.koli || 1);
                    newSelect.append(option);
                });
                newSelect.val(null).trigger('change');
                initializeSelect2(newSelect);
                itemIndex++;
            }

            $('#add-item-btn').click(function() {
                addNewRow();
            });

            $('#items-table').on('click', '.remove-item-btn', function() {
                $(this).closest('tr').remove();
            });

            // Kalkulasi otomatis Quantity -> Koli (paksa quantity integer)
            $('#items-table').on('input', '.quantity-input', function() {
                let row = $(this).closest('tr');
                let quantity = parseFloat($(this).val()) || 0;
                quantity = Math.max(0, Math.round(quantity));
                $(this).val(quantity);
                let productKoli = parseFloat(row.find('.item-select option:selected').data('koli')) || 1;

                if (productKoli > 0) {
                    let calculatedKoli = quantity / productKoli;
                    row.find('.koli-input').val(calculatedKoli.toFixed(2));
                }
            });

            // Kalkulasi otomatis Koli -> Quantity (dibulatkan ke integer)
            $('#items-table').on('input', '.koli-input', function() {
                let row = $(this).closest('tr');
                let koli = parseFloat($(this).val()) || 0;
                let productKoli = parseFloat(row.find('.item-select option:selected').data('koli')) || 1;

                let calculatedQuantity = Math.round(koli * productKoli);
                row.find('.quantity-input').val(calculatedQuantity);
            });

            // Cek duplikasi saat memilih item
            $('#items-table').on('change', '.item-select', function() {
                const current = $(this);
                const val = current.val();
                if (!val) return;

                let duplicateFound = false;
                $('#items-table .item-select').not(current).each(function() {
                    if ($(this).val() && $(this).val().toString() === val.toString()) {
                        duplicateFound = true;
                        return false;
                    }
                });

                if (duplicateFound) {
                    Swal.fire({
                        text: "Item sudah dipilih pada baris lain. Pilih item lain.",
                        icon: "error",
                        buttonsStyling: false,
                        confirmButtonText: "Ok",
                        customClass: { confirmButton: "btn btn-primary" }
                    });
                    current.val(null).trigger('change');
                } else {
                    current.trigger('input');
                }
            });

            // SweetAlert for form submission
            $('#pengadaan-form').on('submit', function(e) {
                e.preventDefault(); // Prevent default form submission

                // Pre-check duplicate items on client-side
                const selected = getSelectedItemIds();
                const uniqueCount = new Set(selected).size;
                if (selected.length !== uniqueCount) {
                    Swal.fire({
                        text: "Tidak boleh ada item yang sama pada daftar. Periksa kembali pilihan item.",
                        icon: "error",
                        buttonsStyling: false,
                        confirmButtonText: "Ok",
                        customClass: { confirmButton: "btn btn-primary" }
                    });
                    return;
                }

                var form = $(this);
                var url = form.attr('action');
                var method = form.attr('method');
                var data = form.serialize();

                // Clear previous errors
                $('.is-invalid').removeClass('is-invalid');
                $('.invalid-feedback').remove();

                Swal.fire({
                    text: "Apakah Anda yakin ingin mengubah data Pengadaan ini?",
                    icon: "question",
                    showCancelButton: true,
                    buttonsStyling: false,
                    confirmButtonText: "Ya, Ubah!",
                    cancelButtonText: "Tidak, Batalkan",
                    customClass: {
                        confirmButton: "btn fw-bold btn-primary",
                        cancelButton: "btn fw-bold btn-active-light-primary"
                    }
                }).then(function(result) {
                    if (result.value) {
                        $.ajax({
                            url: url,
                            type: 'POST', // Using POST for AJAX, with _method='PUT' in data
                            data: data,
                            success: function(response) {
                                Swal.fire({
                                    text: "Data berhasil diubah!",
                                    icon: "success",
                                    buttonsStyling: false,
                                    confirmButtonText: "Lanjutkan",
                                    customClass: {
                                        confirmButton: "btn btn-primary"
                                    }
                                }).then(function (result) {
                                    if (result.isConfirmed) {
                                        let redirectUrl = "{{ route('admin.stok-masuk.pengadaan.index') }}";
                                        if (response && response.redirect_url) {
                                            redirectUrl = response.redirect_url;
                                        }
                                        window.location.href = redirectUrl;
                                    }
                                });
                            },
                            error: function(xhr) {
                                if (xhr.status === 422) {
                                    var errors = xhr.responseJSON.errors || {};
                                    let items = '';
                                    $.each(errors, function(key, value) {
                                        let field = $('[name="' + key + '"]');
                                        if(key.includes('.')) {
                                            const parts = key.split('.');
                                            field = $('[name="items['+parts[1]+']['+parts[2]+']"]');
                                        }
                                        if (field.length) {
                                            field.addClass('is-invalid');
                                            field.after('<div class=\"invalid-feedback\">' + value[0] + '</div>');
                                        }
                                        items += `<li>${value[0]}</li>`;
                                    });
                                    Swal.fire({
                                        title: "Validasi Gagal",
                                        html: `<ul style=\"text-align:left;margin:0 0 0 1rem;\">${items}</ul>`,
                                        icon: "error",
                                        buttonsStyling: false,
                                        confirmButtonText: "Ok",
                                        customClass: { confirmButton: "btn btn-primary" }
                                    });
                                } else {
                                    Swal.fire({
                                        text: "Terjadi kesalahan pada server. Silakan coba lagi.",
                                        icon: "error",
                                        buttonsStyling: false,
                                        confirmButtonText: "Ok",
                                        customClass: { confirmButton: "btn btn-primary" }
                                    });
                                }
                            }
                        });
                    }
                });
            });
        });
    </script>
@endpush
