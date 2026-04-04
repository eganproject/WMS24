@extends('layouts.app')

@push('styles')
    <style>
        .select2-container .select2-selection--single {
            height: 2.65rem !important;
        }
        /* Modern table + UX improvements */
        #items-table thead th {
            position: sticky;
            top: 0;
            background: #fff;
            z-index: 1;
        }
        /* Remove zebra to avoid clash with control backgrounds */
        #items-table tbody tr { background: transparent; border-top: 1px solid #eef2f7; }
        #items-table tbody tr:first-child { border-top: none; }
        #items-table tbody tr:hover { background: #f9fbff; }
        /* Make form controls inside table clearly white for contrast */
        #items-table input[type="number"] { text-align: right; }
        .items-toolbar {
            display: flex;
            gap: .75rem;
            align-items: center;
            justify-content: space-between;
            margin-bottom: .75rem;
            flex-wrap: wrap;
        }
        .items-search { flex: 1 1 320px; max-width: 520px; }
        /* Spacing for conversion helper text */
        .koli-info { margin-top: .25rem; }
    </style>
@endpush

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Buat Request Transfer',
        'breadcrumbs' => ['Admin', 'Stok Masuk', 'Request Transfer'],
    ])
@endpush

@section('content')
    <div class="content flex-row-fluid" id="kt_content">
        <div class="card">
            <div class="card-body">
                <form id="transfer-request-form" action="{{ route('admin.stok-masuk.request-transfer.store') }}"
                    method="POST">
                    @csrf
                    <div class="row">
                        <div class="col-md-4 mb-5">
                            <label class="form-label">Kode Request</label>
                            <input type="text" name="code"
                                class="form-control form-control-solid  @error('code') is-invalid @enderror"
                                value="{{ $code }}" readonly />
                        </div>
                        <div class="col-md-4 mb-5">
                            <label class="form-label required">Tanggal Request</label>
                            <input type="text" name="date" id="date"
                                class="form-control form-control-solid @error('date') is-invalid @enderror"
                                value="{{ old('date', date('Y-m-d')) }}">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-5">
                            <label class="form-label required">Gudang Asal</label>
                            <select name="from_warehouse_id" id="from_warehouse_id"
                                class="form-select form-select-solid @error('from_warehouse_id') is-invalid @enderror"
                                data-control="select2" data-placeholder="Pilih gudang asal">
                                <option></option>
                                @foreach ($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}"
                                        {{ old('from_warehouse_id') == $warehouse->id ? 'selected' : '' }}>
                                        {{ $warehouse->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-5">
                            <label class="form-label required">Gudang Tujuan</label>
                            @if (auth()->user()->warehouse_id)
                                <input type="hidden" name="to_warehouse_id" value="{{ auth()->user()->warehouse_id }}">
                                <input type="text" class="form-control form-control-solid"
                                    value="{{ auth()->user()->warehouse->name }}" readonly>
                            @else
                                <select name="to_warehouse_id" id="to_warehouse_id"
                                    class="form-select form-select-solid @error('to_warehouse_id') is-invalid @enderror"
                                    data-control="select2" data-placeholder="Pilih gudang tujuan">
                                    <option></option>
                                    @foreach ($warehouses as $warehouse)
                                        <option value="{{ $warehouse->id }}"
                                            {{ old('to_warehouse_id') == $warehouse->id ? 'selected' : '' }}>
                                            {{ $warehouse->name }}</option>
                                    @endforeach
                                </select>
                            @endif
                        </div>
                    </div>

                    <div class="mb-5">
                        <label class="form-label">Deskripsi Request</label>
                        <textarea name="description" class="form-control form-control-solid @error('description') is-invalid @enderror"
                            rows="3">{{ old('description') }}</textarea>
                    </div>

                    <h4 class="mt-10">Daftar Item</h4>

                    <div class="items-toolbar">
                        <div class="input-group items-search">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" id="items-search" class="form-control form-control-solid" placeholder="Cari item (nama, SKU, deskripsi)">
                        </div>
                        <div class="d-flex align-items-center" style="gap:.5rem;">
                            <span class="badge bg-light text-dark" id="items-visible-count">0 baris</span>
                            <button type="button" class="btn btn-primary" id="add-item-btn"><i class="bi bi-plus-lg me-1"></i>Tambah Item</button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4" id="items-table">
                            <thead>
                                <tr class="fw-bolder text-muted">
                                    <th class="min-w-250px">Item</th>
                                    <th class="min-w-125px">Stok (Qty)</th>
                                    <th class="min-w-125px">Stok (Koli)</th>
                                    <th class="min-w-125px">Jumlah</th>
                                    <th class="min-w-125px">Koli</th>
                                    <th class="min-w-200px">Deskripsi Item</th>
                                    <th class="min-w-50px text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Item rows will be added here -->
                            </tbody>
                            
                        </table>
                    </div>

                    <div class="mt-10">
                        <button type="submit" class="btn btn-primary">Simpan</button>
                        <a href="{{ route('admin.stok-masuk.request-transfer.index') }}"
                            class="btn btn-light">Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Template for new item row -->
    <template id="item-row-template">
        <tr data-index="__INDEX__">
            <td>
                <select name="items[__INDEX__][item_id]" class="form-select form-select-solid item-select"
                    data-placeholder="Pilih item" required>
                    <option></option>
                    @foreach ($items as $item)
                        <option value="{{ $item->id }}" data-koli-per-unit="{{ $item->koli ?? 1 }}">
                            {{ $item->nama_barang }} (SKU: {{ $item->sku }})</option>
                    @endforeach
                </select>
                <div class="form-text text-muted koli-info"></div>
            </td>
            <td>
                <input type="number" class="form-control form-control-solid available-stock" readonly>
            </td>
            <td>
                <input type="number" class="form-control form-control-solid available-koli-stock" readonly>
            </td>
            <td>
                <input type="number" name="items[__INDEX__][quantity]"
                    class="form-control form-control-solid quantity-input" min="1" step="1" value="1"
                    required>
            </td>
            <td>
                <input type="number" name="items[__INDEX__][koli]" class="form-control form-control-solid koli-input"
                    min="0" step="any" value="0">
            </td>
            <td>
                <input type="text" name="items[__INDEX__][description]" class="form-control form-control-solid">
            </td>
            <td class="text-end">
                <button type="button" class="btn btn-icon btn-sm btn-danger remove-item-btn"><i
                        class="bi bi-trash"></i></button>
            </td>
        </tr>
    </template>
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            $("#date").flatpickr({
                dateFormat: "Y-m-d",
                defaultDate: "{{ old('date', date('Y-m-d')) }}"
            });
            let itemIndex = 0;
            let availableItems = [];

            // Normalize locale number strings (supports both comma and dot decimals)
            function parseLocaleNumber(val) {
                if (typeof val === 'number') return val;
                if (val === undefined || val === null) return 0;
                let s = val.toString().trim();
                if (!s) return 0;
                if (s.includes(',') && s.includes('.')) {
                    s = s.replace(/\./g, '').replace(',', '.');
                } else if (s.includes(',') && !s.includes('.')) {
                    s = s.replace(',', '.');
                }
                const n = parseFloat(s);
                return isNaN(n) ? 0 : n;
            }

            function initializeSelect2(element) {
                $(element).select2({
                    width: '100%',
                    placeholder: "Pilih item",
                });
            }

            function formatInt(value) {
                const n = parseLocaleNumber(value);
                if (isNaN(n)) return '';
                return Math.round(n).toString();
            }

            // Debounce utility for smooth search
            function debounce(fn, delay = 200) {
                let t;
                return function(...args) {
                    clearTimeout(t);
                    t = setTimeout(() => fn.apply(this, args), delay);
                };
            }

            function updateItemSelectOptions(selectElement) {
                const currentVal = $(selectElement).val();
                $(selectElement).empty().append($('<option></option>'));
                availableItems.forEach(function(inventoryItem) {
                    let option = new Option(
                        `${inventoryItem.item.nama_barang} (SKU: ${inventoryItem.item.sku})`,
                        inventoryItem.item_id,
                        false,
                        false
                    );
                    // quantity = stok qty, koliStock = stok koli di inventory, koliPerUnit = konversi item koli per unit
                    $(option).attr('data-quantity', inventoryItem.quantity);
                    $(option).attr('data-koli-stock', inventoryItem.koli || 0);
                    $(option).attr('data-koli-per-unit', inventoryItem.item.koli || 1);
                    $(selectElement).append(option);
                });
                $(selectElement).val(currentVal).trigger('change');
            }

            function addNewRow() {
                const template = document.getElementById('item-row-template').innerHTML;
                const newRowHtml = template.replace(/__INDEX__/g, itemIndex);
                const newRow = $(newRowHtml);
                $('#items-table tbody').append(newRow);

                const select = newRow.find('.item-select');
                updateItemSelectOptions(select);
                initializeSelect2(select);

                itemIndex++;
                updateVisibleCount();
            }

            $('#add-item-btn').on('click', function() {
                if (!$('#from_warehouse_id').val()) {
                    Swal.fire(
                        'Peringatan',
                        'Silakan pilih Gudang Asal terlebih dahulu.',
                        'warning'
                    );
                    return;
                }
                addNewRow();
            });

            $('#items-table').on('click', '.remove-item-btn', function() {
                $(this).closest('tr').remove();
                updateVisibleCount();
            });

            function checkWarehouseSelection() {
                const fromWarehouse = $('#from_warehouse_id').val();
                const toWarehouse = $('[name="to_warehouse_id"]').val();

                if (fromWarehouse && toWarehouse && fromWarehouse === toWarehouse) {
                    Swal.fire(
                        'Gudang Tidak Valid',
                        'Gudang Asal dan Gudang Tujuan tidak boleh sama.',
                        'warning'
                    );
                    // Clear the destination warehouse selection
                    if ($('#to_warehouse_id').is('select')) {
                        $('#to_warehouse_id').val(null).trigger('change');
                    }
                }
            }

            $('#from_warehouse_id, #to_warehouse_id').on('change', checkWarehouseSelection);

            $('#from_warehouse_id').on('change', function() {
                const warehouseId = $(this).val();
                $('#items-table tbody').empty();
                itemIndex = 0;
                availableItems = [];

                if (!warehouseId) {
                    return;
                }

                $.ajax({
                    url: `{{ url('admin/stok-masuk/request-transfer/get-items-by-warehouse') }}/${warehouseId}`,
                    type: 'GET',
                    success: function(items) {
                        availableItems = items;
                        addNewRow(); // Add a default row after fetching items
                    },
                    error: function(xhr) {
                        console.error('Error fetching items:', xhr);
                        toastr.error('Gagal mengambil data item dari gudang.', 'Error');
                    }
                });
            });

            // Store previous value on focus
            $('#items-table').on('focus', '.item-select', function() {
                $(this).data('previous-value', $(this).val());
            });

            $('#items-table').on('change', '.item-select', function() {
                const currentSelect = this;
                const selectedItemId = $(currentSelect).val();

                // Check for duplicates
                if (selectedItemId) {
                    let isDuplicate = false;
                    $('.item-select').not(currentSelect).each(function() {
                        if ($(this).val() === selectedItemId) {
                            isDuplicate = true;
                            return false; // Exit loop
                        }
                    });

                    if (isDuplicate) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Item Sudah Dipilih',
                            text: 'Item ini sudah ada di daftar. Silakan pilih item lain.',
                            confirmButtonText: 'OK'
                        });

                        // Revert to the previous value
                        const previousValue = $(currentSelect).data('previous-value');
                        $(currentSelect).val(previousValue).trigger('change.select2');
                        return; // Stop further execution
                    }
                }

                $(currentSelect).data('previous-value', selectedItemId);


                const selectedOption = $(this).find('option:selected');
                const quantity = selectedOption.data('quantity');
                const koliStock = selectedOption.data('koliStock');
                const row = $(this).closest('tr');
                const stockQtyInput = row.find('.available-stock');
                const stockKoliInput = row.find('.available-koli-stock');
                const quantityInput = row.find('.quantity-input');
                const koliInput = row.find('.koli-input');
                const itemKoli = parseFloat(selectedOption.data('koliPerUnit')) || 0;
                const koliInfo = row.find('.koli-info');

                if (quantity !== undefined) {
                    stockQtyInput.val(formatInt(quantity));
                } else {
                    stockQtyInput.val('');
                }

                if (koliStock !== undefined) {
                    stockKoliInput.val(koliStock);
                } else {
                    stockKoliInput.val('');
                }

                // Sesuaikan qty dan/atau koli sesuai item yang baru dipilih
                const currentQty = parseLocaleNumber(quantityInput.val()) || 0;
                const currentKoli = parseLocaleNumber(koliInput.val()) || 0;

                if (itemKoli > 0) {
                    if (currentKoli > 0) {
                        // Jika user sebelumnya mengisi koli, jadikan koli sebagai acuan
                        const newQty = currentKoli * itemKoli;
                        quantityInput.val(Number(newQty).toFixed(0));
                    } else {
                        // Jika tidak, jadikan qty sebagai acuan dan hitung koli
                        const newKoli = currentQty / itemKoli;
                        koliInput.val(newKoli.toFixed(2));
                    }
                    koliInfo.text(`Konversi: 1 koli = ${itemKoli} qty`);
                } else {
                    koliInfo.text('');
                }

                // Jalankan validasi otomatis setelah penyesuaian
                validateQuantity(quantityInput);
                // Pastikan tampilan qty tanpa desimal
                quantityInput.val(formatInt(quantityInput.val()));
                applyTableFilter();
            });

            function validateQuantity(inputElement) {
                const row = $(inputElement).closest('tr');
                const selectedOption = row.find('.item-select option:selected');
                const availableQuantity = parseFloat(selectedOption.data('quantity'));
                const enteredQuantity = parseLocaleNumber($(inputElement).val());

                if (isNaN(availableQuantity)) return;

                if (enteredQuantity > availableQuantity) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Kuantitas Melebihi Stok',
                        text: `Stok yang tersedia hanya ${availableQuantity}. Kuantitas yang Anda masukkan telah disesuaikan.`,
                        confirmButtonText: 'OK'
                    });
                    $(inputElement).val(Number(availableQuantity).toFixed(0));
                    calculateItemValues(row, 'quantity');
                }
            }

            $('#items-table').on('input', '.quantity-input', function() {
                validateQuantity(this);
                calculateItemValues($(this).closest('tr'), 'quantity');
                // Pastikan tampilan qty tanpa desimal
                const row = $(this).closest('tr');
                const quantityInput = row.find('.quantity-input');
                quantityInput.val(formatInt(quantityInput.val()));
                applyTableFilter();
            });

            $('#items-table').on('input', '.koli-input', function() {
                calculateItemValues($(this).closest('tr'), 'koli');
                // After koli changes quantity, re-validate
                const row = $(this).closest('tr');
                const quantityInput = row.find('.quantity-input');
                validateQuantity(quantityInput);
                applyTableFilter();
            });

            function calculateItemValues(row, changedField) {
                const select = row.find('.item-select');
                const selectedOption = select.find('option:selected');
                const itemKoli = parseFloat(selectedOption.data('koliPerUnit')) || 0; // konversi koli per unit item
                let quantityInput = row.find('.quantity-input');
                let koliInput = row.find('.koli-input');

                if (!select.val() || itemKoli <= 0) return;

                if (changedField === 'quantity') {
                    const qty = parseLocaleNumber(quantityInput.val()) || 0;
                    const koli = qty / itemKoli;
                    koliInput.val(koli.toFixed(2));
                } else if (changedField === 'koli') {
                    const koli = parseLocaleNumber(koliInput.val()) || 0;
                    const qty = koli * itemKoli;
                    quantityInput.val(Number(qty).toFixed(0));
                }
            }

            // Initialize select2 for main warehouse selects
            $(`[data-control='select2']`).select2();

            // Trigger change on page load if warehouse is pre-selected (e.g., from old input)
            if ($('#from_warehouse_id').val()) {
                $('#from_warehouse_id').trigger('change');
            }

            // Search + visible count helpers
            function updateVisibleCount() {
                const visible = $('#items-table tbody tr:visible').length;
                $('#items-visible-count').text(`${visible} baris`);
            }

            function applyTableFilter() {
                const q = ($('#items-search').val() || '').toString().toLowerCase().trim();
                if (!q) {
                    $('#items-table tbody tr').show();
                    updateVisibleCount();
                    return;
                }
                $('#items-table tbody tr').each(function() {
                    const row = $(this);
                    const selectedText = row.find('.item-select option:selected').text().toLowerCase();
                    const desc = (row.find('input[name$="[description]"]').val() || '').toString().toLowerCase();
                    const hay = `${selectedText} ${desc}`;
                    if (hay.indexOf(q) !== -1) {
                        row.show();
                    } else {
                        row.hide();
                    }
                });
                updateVisibleCount();
            }

            $('#items-search').on('input', debounce(applyTableFilter, 150));

            // Form submission logic remains the same
            $('#transfer-request-form').on('submit', function(e) {
                e.preventDefault();
                var form = $(this);

                Swal.fire({
                    text: "Apakah Anda yakin ingin menyimpan request ini?",
                    icon: "question",
                    showCancelButton: true,
                    buttonsStyling: false,
                    confirmButtonText: "Ya, Simpan!",
                    cancelButtonText: "Tidak, Batalkan",
                    customClass: {
                        confirmButton: "btn fw-bold btn-primary",
                        cancelButton: "btn fw-bold btn-active-light-primary"
                    }
                }).then(function(result) {
                    if (result.value) {
                        // Before submitting, ensure no quantity is over stock one last time
                        let allQuantitiesValid = true;
                        $('.quantity-input').each(function() {
                            const row = $(this).closest('tr');
                            const selectedOption = row.find('.item-select option:selected');
                            const availableQuantity = parseFloat(selectedOption.data(
                                'quantity'));
                            const enteredQuantity = parseLocaleNumber($(this).val());
                            if (!isNaN(availableQuantity) && enteredQuantity >
                                availableQuantity) {
                                allQuantitiesValid = false;
                            }
                        });

                        if (!allQuantitiesValid) {
                            Swal.fire('Error',
                                'Satu atau lebih item memiliki kuantitas melebihi stok. Silakan perbaiki.',
                                'error');
                            return;
                        }

                        $.ajax({
                            url: form.attr('action'),
                            type: 'POST',
                            data: form.serialize(),
                            success: function(response) {
                                Swal.fire({
                                    text: "Data berhasil disimpan!",
                                    icon: "success",
                                    buttonsStyling: false,
                                    confirmButtonText: "Lanjutkan",
                                    customClass: {
                                        confirmButton: "btn btn-primary"
                                    }
                                }).then(function(result) {
                                    if (result.isConfirmed) {
                                        window.location.href =
                                            "{{ route('admin.stok-masuk.request-transfer.index') }}";
                                    }
                                });
                            },
                            error: function(xhr) {
                                $('.is-invalid').removeClass('is-invalid');
                                $('.invalid-feedback').remove();
                                if (xhr.status === 422) {
                                    var errors = xhr.responseJSON.errors;
                                    $.each(errors, function(key, value) {
                                        let field = $('[name="' + key + '"]');
                                        if (key.includes('.')) {
                                            const parts = key.split('.');
                                            field = $('[name="items[' + parts[
                                                    1] + '][' + parts[2] +
                                                ']"]'
                                            );
                                        }
                                        field.addClass('is-invalid').after(
                                            '<div class="invalid-feedback">' +
                                            value[0] + '</div>');
                                    });
                                    toastr.error(
                                        'Silakan perbaiki error validasi yang ada.',
                                        'Validasi Gagal');
                                } else {
                                    toastr.error('Terjadi kesalahan pada server.',
                                        'Error');
                                }
                            }
                        });
                    }
                });
            });
        });
    </script>
@endpush
