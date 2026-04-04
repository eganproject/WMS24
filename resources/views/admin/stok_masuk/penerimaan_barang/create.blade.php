@extends('layouts.app')

@push('styles')
    <style>
        .section-title { font-size: 1.15rem; font-weight: 700; color: #111827; }
        .help-muted { font-size: .85rem; color: #6B7280; }
        .info-box { border: 1px solid #EFF2F5; border-radius: .475rem; padding: 1rem; }
        #items-toolbar { display: flex; gap: .75rem; align-items: center; justify-content: space-between; margin-bottom: .75rem; }
        #items-toolbar .summary { font-weight: 600; color: #334155; }
        #detail-table thead th { position: sticky; top: 0; background: #fff; z-index: 1; }
        #detail-table tbody tr td { vertical-align: middle; }
    </style>
@endpush

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Tambah Penerimaan Barang',
        'breadcrumbs' => ['Admin', 'Stok Masuk', 'Penerimaan Barang', 'Tambah'],
    ])
@endpush

@section('content')
    <div class="content flex-row-fluid" id="kt_content">
        <div class="card">
            <div class="card-header border-0 pt-6 d-flex justify-content-between align-items-center">
                <h3 class="card-title fw-bolder">Form Penerimaan Barang</h3>
            </div>
            <div class="card-body pt-0">
                @if ($errors->any())
                    <div class="alert alert-danger mb-6">
                        <div class="d-flex flex-column">
                            <h4 class="mb-2">Terjadi kesalahan:</h4>
                            <ul class="mb-0 ps-5">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif

                <form action="{{ route('admin.stok-masuk.penerimaan-barang.store') }}" method="POST" id="goods-receipt-form">
                    @csrf
                    <div class="row mb-7">
                        <div class="col-md-4">
                            <label class="form-label required">Nomor Dokumen</label>
                            <input type="text" name="code" class="form-control form-control-solid" value="{{ old('code', $newCode) }}" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label required">Tanggal Penerimaan</label>
                            <input type="text" id="receipt_date" name="receipt_date" class="form-control form-control-solid flatpickr-date" value="{{ old('receipt_date', now()->toDateString()) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label required">Gudang Tujuan</label>
                            @php($forcedWarehouseId = optional(auth()->user())->warehouse_id)
                            <select name="warehouse_id" id="warehouse_id" class="form-select form-select-solid" data-control="select2" data-placeholder="Pilih Gudang" @if($forcedWarehouseId) disabled @endif>
                                <option value="">Pilih Gudang</option>
                                @foreach ($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}" @selected(old('warehouse_id', $forcedWarehouseId) == $warehouse->id)>
                                        {{ $warehouse->name }}
                                    </option>
                                @endforeach
                            </select>
                            @if($forcedWarehouseId)
                                <input type="hidden" name="warehouse_id" value="{{ $forcedWarehouseId }}">
                            @endif
                        </div>
                    </div>

                    <div class="row mb-7">
                        <div class="col-md-4">
                            <label class="form-label required">Tipe</label>
                            <select name="type" id="gr_type" class="form-select form-select-solid" data-control="select2" data-placeholder="Pilih Tipe" required>
                                <option value="">Pilih Tipe</option>
                                <option value="transfer" @selected(old('type','transfer')==='transfer')>Transfer</option>
                                <option value="pengadaan" @selected(old('type')==='pengadaan')>Pengadaan</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <div id="shipment-select-col" style="display:none;">
                                <label class="form-label required">Nomor Pengiriman</label>
                                <select name="shipment_id" id="shipment_id" class="form-select form-select-solid" data-control="select2" data-placeholder="Pilih Pengiriman">
                                    <option value="">Pilih Pengiriman</option>
                                </select>
                            </div>
                            <div id="sio-select-col" style="display:none;">
                                <label class="form-label required">Dokumen Pengadaan</label>
                                <select name="stock_in_order_id" id="stock_in_order_id" class="form-select form-select-solid" data-control="select2" data-placeholder="Pilih Dokumen Pengadaan">
                                    <option value="">Pilih Dokumen</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div id="shipment-manual-container" style="display:none;">
                        <h4 class="mb-5">Input Data Pengiriman (Pengadaan)</h4>
                        <div class="row g-5 mb-7">
                            <div class="col-md-4">
                                <label class="form-label required">Tanggal Pengiriman</label>
                                <input type="text" id="manual_shipping_date" name="manual_shipment[shipping_date]" class="form-control form-control-solid flatpickr-date" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label required">Tipe Kendaraan</label>
                                <input type="text" id="manual_vehicle_type" name="manual_shipment[vehicle_type]" class="form-control form-control-solid" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label required">Plat Nomor</label>
                                <input type="text" id="manual_license_plate" name="manual_shipment[license_plate]" class="form-control form-control-solid" required>
                            </div>
                        </div>
                        <div class="row g-5 mb-7">
                            <div class="col-md-4">
                                <label class="form-label">Nama Pengemudi</label>
                                <input type="text" name="manual_shipment[driver_name]" class="form-control form-control-solid">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Kontak Pengemudi</label>
                                <input type="text" name="manual_shipment[driver_contact]" class="form-control form-control-solid">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Catatan</label>
                                <input type="text" name="manual_shipment[description]" class="form-control form-control-solid">
                            </div>
                        </div>
                    </div>

                    <div class="mb-7">
                        <label class="form-label">Deskripsi</label>
                        <textarea name="description" class="form-control form-control-solid" rows="3" placeholder="Catatan atau deskripsi tambahan">{{ old('description') }}</textarea>
                    </div>

                    <div id="shipment-details-container" style="display: none;">
                        <h4 class="mb-5">Detail Pengiriman</h4>
                        <div class="row g-5 mb-7">
                            <div class="col-md-6">
                                <div class="info-box">
                                    <div class="section-title mb-2">Ringkasan</div>
                                    <div class="row mb-1"><div class="col-5 help-muted">Nomor Pengiriman</div><div class="col-7 fw-bold" id="sd_code">-</div></div>
                                    <div class="row mb-1"><div class="col-5 help-muted">Tanggal Pengiriman</div><div class="col-7 fw-bold" id="sd_shipping_date">-</div></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-box">
                                    <div class="section-title mb-2">Transportasi</div>
                                    <div class="row mb-1"><div class="col-5 help-muted">Tipe Kendaraan</div><div class="col-7 fw-bold" id="sd_vehicle_type">-</div></div>
                                    <div class="row mb-1"><div class="col-5 help-muted">Plat Nomor</div><div class="col-7 fw-bold" id="sd_license_plate">-</div></div>
                                </div>
                            </div>
                        </div>
                        <div class="row g-5 mb-7">
                            <div class="col-md-6">
                                <div class="info-box">
                                    <div class="section-title mb-2">Pengemudi</div>
                                    <div class="row mb-1"><div class="col-5 help-muted">Nama</div><div class="col-7 fw-bold" id="sd_driver_name">-</div></div>
                                    <div class="row mb-1"><div class="col-5 help-muted">Kontak</div><div class="col-7 fw-bold" id="sd_driver_contact">-</div></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-box">
                                    <div class="section-title mb-2">Catatan</div>
                                    <div id="sd_description" class="help-muted">-</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="details-placeholder" class="text-center text-muted py-10">
                        <p>Pilih tipe terlebih dahulu. Untuk transfer: pilih nomor pengiriman. Untuk pengadaan: pilih dokumen pengadaan dan isi data pengiriman.</p>
                    </div>

                    <div id="details-container" style="display: none;">
                        <div id="items-toolbar">
                            <div class="w-50">
                                <div class="position-relative">
                                    <i class="fas fa-search text-gray-400 position-absolute ms-3 mt-2"></i>
                                    <input type="text" id="item-search" class="form-control form-control-sm ps-10" placeholder="Cari item (SKU/Nama)">
                                </div>
                                <div class="help-muted mt-1">Gunakan kolom ini untuk memfilter baris item.</div>
                            </div>
                            <div class="summary" id="items-summary">0 item • Total: 0 Qty | 0 Koli</div>
                        </div>
                        <div class="table-responsive mb-7">
                            <table class="table align-middle table-row-dashed" id="detail-table">
                                <thead class="text-muted">
                                    <tr>
                                        <th class="min-w-200px">Item</th>
                                        <th id="col-ordered-qty" class="min-w-110px text-end">Qty Dikirim</th>
                                        <th id="col-ordered-koli" class="min-w-110px text-end">Koli Dikirim</th>
                                        <th class="min-w-110px text-end">Qty Diterima</th>
                                        <th class="min-w-110px text-end">Koli Diterima</th>
                                        <th class="min-w-200px">Catatan</th>
                                        <th class="min-w-75px"></th>
                                    </tr>
                                </thead>
                                <tbody id="detail-rows">
                                    <!-- Rows will be added dynamically -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-7">
                        <a href="{{ route('admin.stok-masuk.penerimaan-barang.index') }}" class="btn btn-light me-3">Kembali</a>
                        <button type="submit" class="btn btn-primary">Simpan Penerimaan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            flatpickr(".flatpickr-date", {
                dateFormat: "Y-m-d",
            });
            const forcedWarehouseId = @json(optional(auth()->user())->warehouse_id);
            let detailIndex = 0;
            const $detailsContainer = $('#details-container');
            const $detailsPlaceholder = $('#details-placeholder');
            const $shipmentSelect = $('#shipment_id');
            const $sioSelect = $('#stock_in_order_id');
            const $shipmentDetailsContainer = $('#shipment-details-container');

            const shipmentFields = {
                code: $('#sd_code'),
                shipping_date: $('#sd_shipping_date'),
                vehicle_type: $('#sd_vehicle_type'),
                license_plate: $('#sd_license_plate'),
                driver_name: $('#sd_driver_name'),
                driver_contact: $('#sd_driver_contact'),
                description: $('#sd_description'),
            };

            function addNewDetailRow(detail = null) {
                const index = detailIndex++;
                const koliRatio = detail ? (detail.item_koli_ratio || 1) : 1;
                const remainingQty = detail ? (detail.remaining_quantity || 0) : 0;
                const remainingKoli = detail ? (detail.remaining_koli || 0) : 0;

                const safeLabel = detail ? (detail.item_label || '-') : '-';
                const newRow = `
                    <tr data-name="${(safeLabel || '').toString().toLowerCase()}" data-koli-ratio="${koliRatio}" data-remaining-qty="${remainingQty}" data-remaining-koli="${remainingKoli}">
                        <td>
                            <input type="text" class="form-control form-control-solid" value="${safeLabel}" readonly>
                            <input type="hidden" name="details[${index}][item_id]" value="${detail ? detail.item_id : ''}">
                            <input type="hidden" name="details[${index}][shipment_item_id]" value="${detail ? detail.shipment_item_id : ''}">
                            <input type="hidden" name="details[${index}][order_item_id]" value="${detail && detail.order_item_id ? detail.order_item_id : ''}">
                        </td>
                        <td><input type="number" step="1" min="0" name="details[${index}][ordered_quantity]" class="form-control form-control-solid text-end" value="${Math.round(remainingQty)}" readonly></td>
                        <td><input type="number" step="0.01" min="0" name="details[${index}][ordered_koli]" class="form-control form-control-solid text-end" value="${remainingKoli}" readonly></td>
                        <td><input type="number" step="1" min="0" max="${Math.round(remainingQty)}" name="details[${index}][received_quantity]" class="form-control form-control-solid text-end received-quantity" value="0"></td>
                        <td><input type="number" step="0.01" min="0" max="${remainingKoli}" name="details[${index}][received_koli]" class="form-control form-control-solid text-end received-koli" value="0"></td>
                        <td><input type="text" name="details[${index}][notes]" class="form-control form-control-solid" placeholder="Catatan"></td>
                        <td class="text-center">
                            <button type="button" class="btn btn-icon btn-light-danger remove-row" disabled>
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>`;
                
                const detailRowsContainer = $('#detail-rows');
                const newRowEl = $(newRow);
                detailRowsContainer.append(newRowEl);
                newRowEl.find('[data-control="select2"]').select2({ width: '100%' });
            }

            

            function clearShipmentFields() {
                for (const key in shipmentFields) {
                    shipmentFields[key].text('-');
                }
            }

            function populateShipmentFields(data) {
                if (data) {
                    for (const key in shipmentFields) {
                        if (data[key]) {
                            shipmentFields[key].text(data[key]);
                        } else {
                            shipmentFields[key].text('-');
                        }
                    }
                } else {
                    clearShipmentFields();
                }
            }

            function handleShipmentFormVisibility() {
                const type = $('#gr_type').val();
                const shipmentId = $shipmentSelect.val();

                // Hide all blocks by default
                $('#shipment-select-col').hide();
                $('#sio-select-col').hide();
                $('#shipment-manual-container').hide();
                $shipmentDetailsContainer.hide();

                if (!type) {
                    // reset required flags
                    $('#shipment_id').prop('required', false);
                    $('#manual_shipping_date').prop('required', false);
                    $('#manual_vehicle_type').prop('required', false);
                    $('#manual_license_plate').prop('required', false);
                    clearShipmentFields();
                    updateDetailHeadersByType();
                    return;
                }

                if (type === 'transfer') {
                    $('#shipment-select-col').show();
                    $('#shipment_id').prop('required', true);
                    $('#manual_shipping_date').prop('required', false);
                    $('#manual_vehicle_type').prop('required', false);
                    $('#manual_license_plate').prop('required', false);
                    if (shipmentId) {
                        $shipmentDetailsContainer.show();
                    }
                } else if (type === 'pengadaan') {
                    $('#sio-select-col').show();
                    $('#shipment-manual-container').show();
                    $('#shipment_id').prop('required', false);
                    $('#manual_shipping_date').prop('required', true);
                    $('#manual_vehicle_type').prop('required', true);
                    $('#manual_license_plate').prop('required', true);
                    clearShipmentFields(); // no fetched details for manual
                }
                updateDetailHeadersByType();
            }

            // Update column headers based on type selection
            function updateDetailHeadersByType() {
                const type = $('#gr_type').val();
                if (type === 'pengadaan') {
                    $('#col-ordered-qty').text('Sisa Qty Order');
                    $('#col-ordered-koli').text('Sisa Koli Order');
                } else if (type === 'transfer') {
                    $('#col-ordered-qty').text('Qty Dikirim');
                    $('#col-ordered-koli').text('Koli Dikirim');
                } else {
                    // default fallback
                    $('#col-ordered-qty').text('Qty');
                    $('#col-ordered-koli').text('Koli');
                }
            }

            function fetchShipmentDetails() {
                const shipmentId = $shipmentSelect.val();
                if (shipmentId) {
                    const url = `{{ route('admin.stok-masuk.penerimaan-barang.get-shipment-details') }}?shipment_id=${shipmentId}`;
                    fetch(url)
                        .then(response => response.json())
                        .then(data => populateShipmentFields(data))
                        .catch(() => clearShipmentFields());
                } else {
                    clearShipmentFields();
                }
            }

            function fetchShipments(selectedId = null) {
                const warehouseId = $('#warehouse_id').val();
                $shipmentSelect.empty().append('<option value="">Pilih Pengiriman</option>').trigger('change');
                if (!warehouseId) { $shipmentSelect.prop('disabled', true); return; }
                $shipmentSelect.prop('disabled', false);
                const url = `{{ route('admin.stok-masuk.penerimaan-barang.get-shipments') }}?warehouse_id=${warehouseId}`;
                fetch(url)
                    .then(r => r.json())
                    .then(data => {
                        data.forEach(opt => {
                            const isSel = String(selectedId) === String(opt.id);
                            const newOption = new Option(opt.label, opt.id, isSel, isSel);
                            $shipmentSelect.append(newOption);
                        });
                        if (selectedId) $shipmentSelect.trigger('change');
                    })
                    .catch(() => {});
            }

            function fetchStockInOrders(selectedId = null) {
                const warehouseId = $('#warehouse_id').val();
                const $sioSelect = $('#stock_in_order_id');
                $sioSelect.empty().append('<option value="">Pilih Dokumen</option>').trigger('change');
                if (!warehouseId) { $sioSelect.prop('disabled', true); return; }
                $sioSelect.prop('disabled', false);
                const url = `{{ route('admin.stok-masuk.penerimaan-barang.get-stock-in-orders') }}?warehouse_id=${warehouseId}`;
                fetch(url)
                    .then(r => r.json())
                    .then(data => {
                        data.forEach(opt => {
                            const isSel = String(selectedId) === String(opt.id);
                            const newOption = new Option(opt.label, opt.id, isSel, isSel);
                            $sioSelect.append(newOption);
                        });
                        if (selectedId) $sioSelect.trigger('change');
                    })
                    .catch(() => {});
            }

            function fetchReferenceDetails() {
                const shipmentId = $shipmentSelect.val();
                const detailRows = $('#detail-rows');

                detailRows.empty();
                detailIndex = 0;

                if (!shipmentId) return;

                const url = `{{ route('admin.stok-masuk.penerimaan-barang.get-reference-details') }}?shipment_id=${shipmentId}`;

                fetch(url)
                    .then(response => response.json())
                    .then(data => {
                        if (data.length > 0) {
                            data.forEach(detail => addNewDetailRow(detail));
                        } else {
                            $detailsContainer.hide();
                            $detailsPlaceholder.show().find('p').text('Referensi yang dipilih tidak memiliki item.');
                        }
                    })
                    .catch(error => console.error('Error fetching reference details:', error));
            }

            function fetchSioDetails() {
                const sioId = $sioSelect.val();
                const detailRows = $('#detail-rows');

                detailRows.empty();
                detailIndex = 0;

                if (!sioId) return;

                const typeParam = encodeURIComponent('{{ \App\Models\GoodsReceipt::REFERENCE_TYPE_STOCK_IN_ORDER }}');
                const url = `{{ route('admin.stok-masuk.penerimaan-barang.get-reference-details') }}?type=${typeParam}&id=${sioId}`;

                fetch(url)
                    .then(response => response.json())
                    .then(data => {
                        if (Array.isArray(data) && data.length > 0) {
                            $detailsContainer.show();
                            $detailsPlaceholder.hide();
                            data.forEach(detail => addNewDetailRow(detail));
                        } else {
                            $detailsContainer.hide();
                            $detailsPlaceholder.show().find('p').text('Dokumen pengadaan yang dipilih tidak memiliki item yang perlu diterima.');
                        }
                    })
                    .catch(error => console.error('Error fetching SIO details:', error));
            }

            $(function () {
                $('[data-control="select2"]').select2({ width: '100%' });

                // If user has fixed warehouse, preselect and lock the field
                if (forcedWarehouseId !== null) {
                    $('#warehouse_id').val(String(forcedWarehouseId)).trigger('change');
                    $('#warehouse_id').prop('disabled', true);
                }

                $('#warehouse_id').on('change', function () {
                    const type = $('#gr_type').val();
                    if (type === 'transfer') {
                        fetchShipments();
                    } else if (type === 'pengadaan') {
                        fetchStockInOrders();
                    }
                    handleShipmentFormVisibility();
                });

                $('#gr_type').on('change', function() {
                    const type = $(this).val();
                    // Reset shipment selection when switching type
                    $shipmentSelect.val(null).trigger('change');
                    if (type === 'transfer') {
                        if ($('#warehouse_id').val()) {
                            fetchShipments();
                        }
                        $detailsContainer.hide();
                        $detailsPlaceholder.show().find('p').text('Pilih nomor pengiriman untuk melanjutkan.');
                    } else if (type === 'pengadaan') {
                        if ($('#warehouse_id').val()) {
                            fetchStockInOrders();
                        }
                        $detailsContainer.hide();
                        $detailsPlaceholder.show().find('p').text('Pilih dokumen pengadaan dan isi data pengiriman.');
                    } else {
                        $detailsContainer.hide();
                        $detailsPlaceholder.show().find('p').text('Pilih tipe terlebih dahulu.');
                    }
                    handleShipmentFormVisibility();
                });

                $shipmentSelect.on('change', function () {
                    const id = $(this).val();
                    if (id) {
                        $detailsContainer.show();
                        $detailsPlaceholder.hide();
                        fetchReferenceDetails();
                        fetchShipmentDetails();
                    } else {
                        $detailsContainer.hide();
                        $detailsPlaceholder.show().find('p').text('Pilih nomor pengiriman untuk melanjutkan.');
                        clearShipmentFields();
                    }
                    handleShipmentFormVisibility();
                });

                $sioSelect.on('change', function () {
                    const id = $(this).val();
                    if (id) {
                        $detailsContainer.show();
                        $detailsPlaceholder.hide();
                        fetchSioDetails();
                    } else {
                        $detailsContainer.hide();
                        $detailsPlaceholder.show().find('p').text('Pilih dokumen pengadaan untuk melanjutkan.');
                    }
                    handleShipmentFormVisibility();
                });

                function updateSummary() {
                    let totalQty = 0, totalKoli = 0, itemCount = 0;
                    $('#detail-rows').find('tr:visible').each(function() {
                        const qty = parseFloat($(this).find('.received-quantity').val()) || 0;
                        const koli = parseFloat($(this).find('.received-koli').val()) || 0;
                        totalQty += qty; totalKoli += koli; itemCount += 1;
                    });
                    $('#items-summary').text(`${itemCount} item • Total: ${totalQty} Qty | ${totalKoli} Koli`);
                }

                $('#item-search').on('input', function(){
                    const q = ($(this).val() || '').toString().toLowerCase();
                    $('#detail-rows').find('tr').each(function(){
                        const name = ($(this).data('name') || '').toString();
                        $(this).toggle(!q || name.includes(q));
                    });
                    updateSummary();
                });

                $('#detail-rows').on('input', '.received-quantity', function() {
                    const $row = $(this).closest('tr');
                    const koliRatio = parseFloat($row.data('koli-ratio'));
                    const remainingQty = parseFloat($row.data('remaining-qty'));
                    const remainingKoli = parseFloat($row.data('remaining-koli'));
                    let qty = parseFloat($(this).val());

                    if (isNaN(qty)) qty = 0;

                    // Enforce integer and clamp to remaining
                    qty = Math.round(qty);
                    if (qty > remainingQty) qty = Math.round(remainingQty);
                    if (qty < 0) qty = 0;
                    $(this).val(qty);

                    let koli = koliRatio > 0 ? qty / koliRatio : 0;
                    // Round to 2 decimals
                    let koliRounded = parseFloat(koli.toFixed(2));
                    // Snap to remaining if within 0.01 tolerance
                    if (!isNaN(remainingKoli) && Math.abs(remainingKoli - koliRounded) <= 0.01) {
                        koliRounded = remainingKoli;
                    }
                    // Do not exceed remaining
                    if (!isNaN(remainingKoli) && koliRounded > remainingKoli) {
                        koliRounded = remainingKoli;
                    }
                    $row.find('.received-koli').val(koliRounded.toFixed(2));
                    updateSummary();
                });

                $('#detail-rows').on('input', '.received-koli', function() {
                    const $row = $(this).closest('tr');
                    const koliRatio = parseFloat($row.data('koli-ratio'));
                    const remainingKoli = parseFloat($row.data('remaining-koli'));
                    let koli = parseFloat($(this).val());

                    if (isNaN(koli)) koli = 0;

                    if (koli > remainingKoli) {
                        koli = remainingKoli;
                        $(this).val(koli);
                    }

                    // Snap to remaining if within 0.01 tolerance
                    if (!isNaN(remainingKoli) && Math.abs(remainingKoli - koli) <= 0.01) {
                        koli = remainingKoli;
                        $(this).val(koli.toFixed(2));
                    }

                    // Calculate quantity, round to integer and clamp against remaining-qty
                    let qty = Math.round(koli * koliRatio);
                    const remainingQty = parseFloat($row.data('remaining-qty'));
                    if (!isNaN(remainingQty) && qty > remainingQty) qty = Math.round(remainingQty);
                    if (qty < 0) qty = 0;
                    $row.find('.received-quantity').val(qty);
                    updateSummary();
                });

                // Initial page state
                function initializePage() {
                    const oldShipmentId = {{ json_encode(old('shipment_id')) }};
                    const oldType = {{ json_encode(old('type')) }};
                    const oldSioId = {{ json_encode(old('stock_in_order_id')) }};
                    $detailsContainer.hide();
                    $detailsPlaceholder.show();
                    $shipmentDetailsContainer.hide();
                    handleShipmentFormVisibility();
                    updateDetailHeadersByType();
                    if (oldType === 'transfer' && $('#warehouse_id').val()) {
                        fetchShipments(oldShipmentId);
                    }
                    if (oldType === 'pengadaan' && $('#warehouse_id').val()) {
                        fetchStockInOrders(oldSioId);
                        if (oldSioId) {
                            setTimeout(fetchSioDetails, 300);
                        }
                    }
                }

                $('#goods-receipt-form').on('submit', function(e) {
                    e.preventDefault(); // Prevent default form submission

                    const form = this;
                    const type = $('#gr_type').val();
                    const shipmentId = $('#shipment_id').val();
                    const sioId = $('#stock_in_order_id').val();
                    const manualShipDate = $('#manual_shipping_date').val();
                    const manualVehicle = $('#manual_vehicle_type').val();
                    const manualPlate = $('#manual_license_plate').val();

                    // Clear previous invalid states
                    $('.is-invalid').removeClass('is-invalid');

                    let errors = [];
                    if (!type) {
                        errors.push('Pilih tipe terlebih dahulu.');
                        $('#gr_type').addClass('is-invalid');
                    } else if (type === 'transfer') {
                        if (!shipmentId) {
                            errors.push('Nomor pengiriman wajib dipilih untuk tipe transfer.');
                            $('#shipment_id').addClass('is-invalid');
                        }
                    } else if (type === 'pengadaan') {
                        if (!sioId) {
                            errors.push('Dokumen pengadaan wajib dipilih untuk tipe pengadaan.');
                            $('#stock_in_order_id').addClass('is-invalid');
                        }
                        if (!manualShipDate) {
                            errors.push('Tanggal pengiriman wajib diisi untuk tipe pengadaan.');
                            $('#manual_shipping_date').addClass('is-invalid');
                        }
                        if (!manualVehicle) {
                            errors.push('Tipe kendaraan wajib diisi untuk tipe pengadaan.');
                            $('#manual_vehicle_type').addClass('is-invalid');
                        }
                        if (!manualPlate) {
                            errors.push('Plat nomor wajib diisi untuk tipe pengadaan.');
                            $('#manual_license_plate').addClass('is-invalid');
                        }
                    }

                    if (errors.length) {
                        toastr.error(errors.join('<br>'), 'Validasi Gagal');
                        return; // Stop submit
                    }

                    Swal.fire({
                        title: 'Apakah Anda yakin?',
                        text: "Pastikan semua data yang dimasukkan sudah benar.",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Ya, simpan!',
                        cancelButtonText: 'Batal'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            form.submit(); // Submit the form if confirmed
                        }
                    });
                });

                initializePage();
                // Initialize summary on load
                $('#items-summary').text('0 item • Total: 0 Qty | 0 Koli');
            });
        });
    </script>
@endpush
