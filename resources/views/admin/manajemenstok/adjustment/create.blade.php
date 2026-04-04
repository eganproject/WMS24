@extends('layouts.app')

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Tambah Penyesuaian Stok',
        'breadcrumbs' => ['Admin', 'Manajemen Stok', 'Penyesuaian Stok', 'Tambah'],
    ])
@endpush

@section('content')
    <div class="content flex-row-fluid" id="kt_content">
        <div class="card">
            <div class="card-body">
                <form id="adjustment-form" action="{{ route('admin.manajemenstok.adjustment.store') }}"
                    method="POST">
                    @csrf
                    <div class="row">
                        <div class="col-md-6 mb-5">
                            <label class="form-label fs-6 fw-bolder text-dark">Kode Dokumen</label>
                            <input type="text" name="code" class="form-control form-control-solid"
                                value="{{ $newCode }}" readonly>
                        </div>
                        <div class="col-md-6 mb-5">
                            <label class="form-label fs-6 fw-bolder text-dark">Tanggal</label>
                            <input type="text" name="adjustment_date"
                                class="form-control form-control-solid flatpickr-input @error('adjustment_date') is-invalid @enderror"
                                value="{{ old('adjustment_date', date('Y-m-d')) }}" required>
                            @error('adjustment_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-5">
                            <label class="form-label fs-6 fw-bolder text-dark">Gudang</label>
                            @if(auth()->user()->warehouse_id)
                                <input type="hidden" name="warehouse_id" value="{{ auth()->user()->warehouse_id }}">
                                <input type="text" class="form-control form-control-solid" value="{{ auth()->user()->warehouse->name }}" readonly>
                            @else
                                <select name="warehouse_id"
                                    class="form-select form-select-solid @error('warehouse_id') is-invalid @enderror"
                                    data-control="select2" data-placeholder="Pilih Gudang" required>
                                    <option></option>
                                    @foreach ($warehouses as $warehouse)
                                        <option value="{{ $warehouse->id }}"
                                            {{ old('warehouse_id') == $warehouse->id ? 'selected' : '' }}>{{ $warehouse->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('warehouse_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            @endif
                        </div>
                        <div class="col-md-6 mb-5">
                            <label class="form-label fs-6 fw-bolder text-dark">Catatan</label>
                            <textarea name="notes" class="form-control form-control-solid">{{ old('notes') }}</textarea>
                        </div>
                    </div>

                    <h3 class="mt-5">Item</h3>
                    <table class="table table-bordered" id="items-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Satuan</th>
                                <th width="150px">Stok</th>
                                <th width="150px">Quantity</th>
                                <th width="150px">Koli</th>
                                <th width="50px">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {{-- Baris item akan ditambahkan oleh javascript --}}
                        </tbody>
                    </table>
                    <button type="button" class="btn btn-primary btn-sm" id="add-item-btn">Tambah Item</button>

                    <div class="d-flex justify-content-end mt-10">
                        <a href="{{ route('admin.manajemenstok.adjustment.index') }}"
                            class="btn btn-light me-3">Batal</a>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        var itemsData = @json($items);
        var systemStockUrl = "{{ route('admin.manajemenstok.stok-opname.system-stock') }}";

        $(document).ready(function() {
            $(".flatpickr-input").flatpickr({
                dateFormat: "Y-m-d",
            });

            let itemIndex = 0;

            function initializeSelect2(selector) {
                selector.select2({
                    placeholder: "Pilih Item",
                });
            }

            function getSelectedItemIds() {
                let ids = [];
                $('#items-table tbody tr .item-select').each(function() {
                    const v = $(this).val();
                    if (v) ids.push(String(v));
                });
                return ids;
            }

            function refreshItemSelectOptions() {
                const used = new Set(getSelectedItemIds());
                $('#items-table tbody tr .item-select').each(function() {
                    const current = $(this).val();
                    $(this).find('option').each(function() {
                        const val = $(this).attr('value');
                        if (!val) return; // skip placeholder
                        const shouldDisable = used.has(String(val)) && String(val) !== String(current);
                        $(this).prop('disabled', shouldDisable);
                    });
                    // ask select2 to refresh its options visibility
                    $(this).trigger('change.select2');
                });
            }

            function ensureUniqueSelection(changedSelect) {
                const val = $(changedSelect).val();
                if (!val) return;
                let dupCount = 0;
                $('#items-table tbody tr .item-select').each(function() {
                    if (String($(this).val()) === String(val)) dupCount++;
                });
                if (dupCount > 1) {
                    toastr.error('Item sudah dipilih di baris lain. Pilih item berbeda.');
                    $(changedSelect).val(null).trigger('change');
                }
            }

            function addNewRow() {
                let newRowHtml = `
                    <tr data-index="${itemIndex}">
                        <td>
                            <select name="items[${itemIndex}][item_id]" class="form-select form-select-solid item-select" required></select>
                        </td>
                        <td>
                            <span class="uom-text">-</span>
                            <input type="hidden" name="items[${itemIndex}][uom_id]" class="uom-id-input">
                        </td>
                        <td>
                            <span class="badge bg-light text-dark available-stock-text">0</span>
                            <input type="hidden" class="available-stock-value" value="0">
                        </td>
                        <td><input type="number" name="items[${itemIndex}][quantity]" class="form-control form-control-solid quantity-input" value="1" step="any" required></td>
                        <td><input type="number" name="items[${itemIndex}][koli]" class="form-control form-control-solid koli-input" value="0" step="any"></td>
                        <td><button type="button" class="btn btn-danger btn-sm remove-item-btn">X</button></td>
                    </tr>`;

                $('#items-table tbody').append(newRowHtml);

                let newSelect = $('tr[data-index="' + itemIndex + '"] .item-select');

                newSelect.append(new Option('', '', true, true));

                itemsData.forEach(function(item) {
                    let optionText = item.sku ? `${item.sku} - ${item.nama_barang}` : item.nama_barang;
                    let option = new Option(optionText, item.id, false, false);
                    $(option).attr('data-koli', item.koli || 1);
                    if(item.uom) {
                        $(option).attr('data-uom-id', item.uom.id);
                        $(option).attr('data-uom-name', item.uom.name);
                    }
                    newSelect.append(option);
                });

                newSelect.val(null).trigger('change');

                initializeSelect2(newSelect);
                refreshItemSelectOptions();
                itemIndex++;
            }

            // Gate: hide table and disable add button until warehouse is selected
            function itemsSectionSetEnabled(enabled) {
                if (enabled) {
                    $('#items-table').closest('.card-body, .content, form').find('#items-table').show();
                    $('#add-item-btn').prop('disabled', false).show();
                } else {
                    $('#items-table tbody').empty();
                    $('#items-table').hide();
                    $('#add-item-btn').prop('disabled', true).hide();
                }
            }

            function getSelectedWarehouseId() {
                const fixed = $('input[name="warehouse_id"]').val();
                if (fixed) return fixed;
                return $('select[name="warehouse_id"]').val();
            }

            function initItemsGate() {
                const wh = getSelectedWarehouseId();
                if (wh) {
                    itemsSectionSetEnabled(true);
                    if ($('#items-table tbody tr').length === 0) {
                        addNewRow();
                    }
                } else {
                    itemsSectionSetEnabled(false);
                }
            }

            initItemsGate();

            // React to warehouse change
            $(document).on('change', 'select[name="warehouse_id"]', function() {
                initItemsGate();
                // Re-fetch stock for existing rows (if any)
                $('#items-table tbody tr').each(function() {
                    const row = $(this);
                    const itemId = row.find('.item-select').val();
                    if (itemId) {
                        fetchAndSetStock(row, itemId);
                    } else {
                        row.find('.available-stock-text').text('0');
                        row.find('.available-stock-value').val(0);
                    }
                });
            });

            $('#add-item-btn').click(function() {
                const wh = getSelectedWarehouseId();
                if (!wh) {
                    toastr.warning('Pilih gudang terlebih dahulu.');
                    return;
                }
                addNewRow();
            });

            $('#items-table').on('click', '.remove-item-btn', function() {
                $(this).closest('tr').remove();
                refreshItemSelectOptions();
            });

            function fetchAndSetStock(row, itemId) {
                const wh = getSelectedWarehouseId();
                if (!wh) {
                    row.find('.available-stock-text').text('0');
                    row.find('.available-stock-value').val(0);
                    return;
                }
                $.ajax({
                    url: systemStockUrl,
                    method: 'GET',
                    data: { warehouse_id: wh, item_id: itemId },
                    success: function(res) {
                        const qty = parseFloat(res.quantity || 0) || 0;
                        row.find('.available-stock-text').text(qty);
                        row.find('.available-stock-value').val(qty);
                        // If current qty is negative and exceeds stock, clamp it
                        const currentQty = parseFloat(row.find('.quantity-input').val()) || 0;
                        if (currentQty < 0 && Math.abs(currentQty) > qty) {
                            row.find('.quantity-input').val(-qty);
                            toastr.info('Qty minus disesuaikan ke maksimum stok.');
                        }
                    },
                    error: function() {
                        row.find('.available-stock-text').text('0');
                        row.find('.available-stock-value').val(0);
                    }
                });
            }

            $('#items-table').on('change', '.item-select', function() {
                let selectedOption = $(this).find('option:selected');
                let uomId = selectedOption.data('uom-id');
                let uomName = selectedOption.data('uom-name') || '-';
                let row = $(this).closest('tr');
                row.find('.uom-id-input').val(uomId);
                row.find('.uom-text').text(uomName);
                const itemId = $(this).val();
                ensureUniqueSelection(this);
                refreshItemSelectOptions();
                if (itemId) {
                    fetchAndSetStock(row, itemId);
                } else {
                    row.find('.available-stock-text').text('0');
                    row.find('.available-stock-value').val(0);
                }
            });

            // Kalkulasi otomatis Quantity -> Koli
            $('#items-table').on('input change', '.quantity-input, .item-select', function() {
                let row = $(this).closest('tr');
                let quantity = parseFloat(row.find('.quantity-input').val()) || 0;
                let productKoli = parseFloat(row.find('.item-select option:selected').data('koli')) || 1;
                // Enforce negative cannot exceed available stock
                if (quantity < 0) {
                    let available = parseFloat(row.find('.available-stock-value').val()) || 0;
                    if (Math.abs(quantity) > available) {
                        row.find('.quantity-input').val(-available);
                        quantity = -available;
                        toastr.warning('Qty minus tidak boleh melebihi stok.');
                    }
                }

                if (productKoli > 0) {
                    let calculatedKoli = quantity / productKoli;
                    row.find('.koli-input').val(calculatedKoli.toFixed(2));
                }
            });

            // Kalkulasi otomatis Koli -> Quantity
            $('#items-table').on('input', '.koli-input', function() {
                let row = $(this).closest('tr');
                let koli = parseFloat($(this).val()) || 0;
                let productKoli = parseFloat(row.find('.item-select option:selected').data('koli')) || 1;

                let calculatedQuantity = koli * productKoli;
                row.find('.quantity-input').val(calculatedQuantity);
            });

            // Initial rows only if warehouse is already selected (for fixed warehouse users)
            // initItemsGate() above will add one row when available

            $('#adjustment-form').on('submit', function(e) {
                e.preventDefault();

                var form = $(this);
                var url = form.attr('action');
                var method = form.attr('method');
                var data = form.serialize();

                $('.is-invalid').removeClass('is-invalid');
                $('.invalid-feedback').remove();

                // Client-side final check: negative qty must not exceed stock
                let violations = [];
                // Check duplicate items
                let seen = new Set();
                let duplicateFound = false;
                $('#items-table tbody tr').each(function(i) {
                    const row = $(this);
                    const itemId = String(row.find('.item-select').val() || '');
                    if (itemId) {
                        if (seen.has(itemId)) {
                            duplicateFound = true;
                        } else {
                            seen.add(itemId);
                        }
                    }
                    const qty = parseFloat(row.find('.quantity-input').val()) || 0;
                    const available = parseFloat(row.find('.available-stock-value').val()) || 0;
                    const label = row.find('.item-select option:selected').text() || `Item baris ${i+1}`;
                    if (qty < 0 && Math.abs(qty) > available) {
                        violations.push(`${label}: qty minus (${qty}) > stok (${available})`);
                    }
                });
                if (duplicateFound) {
                    toastr.error('Tidak boleh memilih item yang sama pada lebih dari satu baris.', 'Validasi Gagal');
                    return;
                }
                if (violations.length) {
                    toastr.error('<ul><li>' + violations.join('</li><li>') + '</li></ul>', 'Validasi Gagal');
                    return;
                }

                Swal.fire({
                    text: "Apakah Anda yakin ingin menyimpan data penyesuaian stok ini?",
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
                        $.ajax({
                            url: url,
                            type: method,
                            data: data,
                            success: function(response) {
                                Swal.fire({
                                    text: "Data berhasil disimpan!",
                                    icon: "success",
                                    buttonsStyling: false,
                                    confirmButtonText: "Lanjutkan",
                                    customClass: {
                                        confirmButton: "btn btn-primary"
                                    }
                                }).then(function (result) {
                                    if (result.isConfirmed) {
                                        window.location.href = "{{ route('admin.manajemenstok.adjustment.index') }}";
                                    }
                                });
                            },
                            error: function(xhr) {
                                if (xhr.status === 422) {
                                    var errors = xhr.responseJSON.errors;
                                    let errorMessages = '';
                                    $.each(errors, function(key, value) {
                                        let field = $('[name="' + key + '"]');
                                        if(key.includes('.')) {
                                            const parts = key.split('.');
                                            field = $('[name="items['+parts[1]+']['+parts[2]+']"]');
                                        }
                                        field.addClass('is-invalid');
                                        field.after('<div class="invalid-feedback">' + value[0] + '</div>');
                                        errorMessages += `<li>${value[0]}</li>`;
                                    });
                                    toastr.error('<ul>' + errorMessages + '</ul>', 'Validasi Gagal');
                                } else {
                                    toastr.error('Terjadi kesalahan pada server. Silakan coba lagi.', 'Error');
                                }
                            }
                        });
                    }
                });
            });
        });
    </script>
@endpush
