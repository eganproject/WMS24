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
        'title' => 'Edit Penerimaan Barang',
        'breadcrumbs' => ['Admin', 'Stok Masuk', 'Penerimaan Barang', 'Edit'],
    ])
@endpush

@section('content')
    <div class="content flex-row-fluid" id="kt_content">
        <div class="card">
            <div class="card-header border-0 pt-6 d-flex justify-content-between align-items-center">
                <h3 class="card-title fw-bolder">Form Edit Penerimaan Barang</h3>
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

                <form action="{{ route('admin.stok-masuk.penerimaan-barang.update', $goodsReceipt->id) }}" method="POST" id="goods-receipt-form">
                    @csrf
                    @method('PUT')
                    <div class="row g-5 mb-7">
                        <div class="col-md-4">
                            <label class="form-label required">Nomor Dokumen</label>
                            <input type="text" name="code" class="form-control form-control-solid" value="{{ old('code', $goodsReceipt->code) }}" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label required">Tanggal Penerimaan</label>
                            <input type="text" id="receipt_date" name="receipt_date" class="form-control form-control-solid flatpickr-date" value="{{ old('receipt_date', $goodsReceipt->receipt_date ? \Carbon\Carbon::parse($goodsReceipt->receipt_date)->toDateString() : '') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label required">Gudang Tujuan</label>
                            @php($forcedWarehouseId = optional(auth()->user())->warehouse_id)
                            <select name="warehouse_id" id="warehouse_id" class="form-select form-select-solid" data-control="select2" data-placeholder="Pilih Gudang" @if($forcedWarehouseId) disabled @endif>
                                <option value="">Pilih Gudang</option>
                                @foreach ($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}" @selected(old('warehouse_id', $forcedWarehouseId ?: $goodsReceipt->warehouse_id) == $warehouse->id)>
                                        {{ $warehouse->name }}
                                    </option>
                                @endforeach
                            </select>
                            @if($forcedWarehouseId)
                                <input type="hidden" name="warehouse_id" value="{{ $forcedWarehouseId }}">
                            @endif
                        </div>
                    </div>

                    <div class="mb-7">
                        <label class="form-label">Deskripsi</label>
                        <textarea name="description" class="form-control form-control-solid" rows="3" placeholder="Catatan atau deskripsi tambahan">{{ old('description', $goodsReceipt->description) }}</textarea>
                    </div>

                    <div class="row g-5 mb-7">
                        <div class="col-md-4">
                            <label class="form-label required">Tipe</label>
                            <select name="type" id="gr_type" class="form-select form-select-solid" data-control="select2" data-placeholder="Pilih Tipe" required>
                                <option value="transfer" @selected(old('type', $goodsReceipt->type)==='transfer')>Transfer</option>
                                <option value="pengadaan" @selected(old('type', $goodsReceipt->type)==='pengadaan')>Pengadaan</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <div id="shipment-select-col" style="display: {{ old('type', $goodsReceipt->type)==='transfer' ? 'block' : 'none' }};" class="mb-4">
                                <label class="form-label">Nomor Pengiriman</label>
                                <select id="shipment_id" name="shipment_id" class="form-select form-select-solid" data-control="select2" data-placeholder="Pilih Pengiriman">
                                    <option value="">Pilih Pengiriman</option>
                                </select>
                            </div>
                            <div id="sio-select-col" style="display: {{ old('type', $goodsReceipt->type)==='pengadaan' ? 'block' : 'none' }};" class="mb-4">
                                <label class="form-label">Dokumen Pengadaan</label>
                                <select id="stock_in_order_id" name="stock_in_order_id" class="form-select form-select-solid" data-control="select2" data-placeholder="Pilih Dokumen Pengadaan">
                                    <option value="">Pilih Dokumen</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div id="manual-shipment-row" style="display: {{ old('type', $goodsReceipt->type)==='pengadaan' ? 'block' : 'none' }};" class="mb-7">
                        <div class="row g-5 mb-5">
                            <div class="col-md-4">
                                <label class="form-label required">Tanggal Pengiriman</label>
                                <input type="text" id="manual_shipping_date" name="manual_shipment[shipping_date]" class="form-control form-control-solid flatpickr-date" value="{{ old('manual_shipment.shipping_date', optional($goodsReceipt->shipment)->shipping_date) }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label required">Tipe Kendaraan</label>
                                <input type="text" id="manual_vehicle_type" name="manual_shipment[vehicle_type]" class="form-control form-control-solid" value="{{ old('manual_shipment.vehicle_type', optional($goodsReceipt->shipment)->vehicle_type) }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label required">Plat Nomor</label>
                                <input type="text" id="manual_license_plate" name="manual_shipment[license_plate]" class="form-control form-control-solid" value="{{ old('manual_shipment.license_plate', optional($goodsReceipt->shipment)->license_plate) }}">
                            </div>
                        </div>
                        <div class="row g-5">
                            <div class="col-md-4">
                                <label class="form-label">Nama Pengemudi</label>
                                <input type="text" id="manual_driver_name" name="manual_shipment[driver_name]" class="form-control form-control-solid" value="{{ old('manual_shipment.driver_name', optional($goodsReceipt->shipment)->driver_name) }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Kontak Pengemudi</label>
                                <input type="text" id="manual_driver_contact" name="manual_shipment[driver_contact]" class="form-control form-control-solid" value="{{ old('manual_shipment.driver_contact', optional($goodsReceipt->shipment)->driver_contact) }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Catatan Pengiriman</label>
                                <input type="text" id="manual_description" name="manual_shipment[description]" class="form-control form-control-solid" value="{{ old('manual_shipment.description', optional($goodsReceipt->shipment)->description) }}">
                            </div>
                        </div>
                    </div>

                    <div id="shipment-details-container" class="mb-7" style="display: {{ old('type', $goodsReceipt->type)==='pengadaan' ? 'none' : 'block' }};">
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

                    <div id="details-placeholder" class="text-center text-muted py-10" style="display:none;">
                        <p>Pilih referensi sesuai tipe. Untuk transfer: pilih nomor pengiriman. Untuk pengadaan: pilih dokumen pengadaan dan isi data pengiriman.</p>
                    </div>

                    <div id="details-container">
                        <div class="mb-3" id="items-toolbar">
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
                                        <th id="col-ordered-qty" class="min-w-110px text-end">Qty Sisa</th>
                                        <th id="col-ordered-koli" class="min-w-110px text-end">Koli Sisa</th>
                                        <th class="min-w-110px text-end">Qty Diterima</th>
                                        <th class="min-w-110px text-end">Koli Diterima</th>
                                        <th class="min-w-200px">Catatan</th>
                                        <th class="min-w-75px"></th>
                                    </tr>
                                </thead>
                                <tbody id="detail-rows">
                                    @foreach ($goodsReceipt->details as $i => $d)
                                        <tr data-name="{{ strtolower(($d->item->sku ?? '-') . ' - ' . ($d->item->nama_barang ?? $d->item->name ?? '-')) }}" data-koli-ratio="{{ $d->item->koli ?? 1 }}" data-remaining-qty="0" data-remaining-koli="0">
                                            <td>
                                                <input type="text" class="form-control form-control-solid" value="{{ ($d->item->sku ?? '-') . ' - ' . ($d->item->nama_barang ?? $d->item->name ?? '-') }}" readonly>
                                                <input type="hidden" name="details[{{ $i }}][item_id]" value="{{ $d->item_id }}">
                                                <input type="hidden" name="details[{{ $i }}][shipment_item_id]" value="{{ $d->shipment_item_id }}">
                                            </td>
                                            <td><input type="number" step="0.01" min="0" name="details[{{ $i }}][ordered_quantity]" class="form-control form-control-solid text-end" value="0" readonly></td>
                                            <td><input type="number" step="0.01" min="0" name="details[{{ $i }}][ordered_koli]" class="form-control form-control-solid text-end" value="0" readonly></td>
                                            <td><input type="number" step="0.01" min="0" name="details[{{ $i }}][received_quantity]" class="form-control form-control-solid text-end received-quantity" value="{{ number_format($d->received_quantity ?? 0, 2, '.', '') }}" max=""></td>
                                            <td><input type="number" step="0.01" min="0" name="details[{{ $i }}][received_koli]" class="form-control form-control-solid text-end received-koli" value="{{ number_format($d->received_koli ?? 0, 2, '.', '') }}" max=""></td>
                                            <td><input type="text" name="details[{{ $i }}][notes]" class="form-control form-control-solid" value="{{ $d->notes }}" placeholder="Catatan"></td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-icon btn-light-danger remove-row" disabled>
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-7">
                        <a href="{{ route('admin.stok-masuk.penerimaan-barang.index') }}" class="btn btn-light me-3">Kembali</a>
                        <button type="submit" class="btn btn-primary">Update Penerimaan</button>
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

            $(function () {
                $('[data-control="select2"]').select2({ width: '100%' });
                const $shipmentSelect = $('#shipment_id');
                const $sioSelect = $('#stock_in_order_id');
                const $detailsContainer = $('#details-container');
                const $detailsPlaceholder = $('#details-placeholder');
                const $shipmentDetailsContainer = $('#shipment-details-container');
                const forcedWarehouseId = @json(optional(auth()->user())->warehouse_id);
                const existingByShipment = @json($goodsReceipt->details->mapWithKeys(function($d){return [$d->shipment_item_id => ['received_quantity'=>$d->received_quantity,'received_koli'=>$d->received_koli,'notes'=>$d->notes]];})->toArray());
                const existingByItem = @json($goodsReceipt->details->mapWithKeys(function($d){return [$d->item_id => ['received_quantity'=>$d->received_quantity,'received_koli'=>$d->received_koli,'notes'=>$d->notes]];})->toArray());
                const currentType = @json(old('type', $goodsReceipt->type));
                const currentSioId = @json(optional($goodsReceipt->shipment)->reference_type === (\App\Models\Shipment::REFERENCE_TYPE_STOCK_IN_ORDER ?? 'stock in order') ? optional($goodsReceipt->shipment)->reference_id : null);

                function fetchShipments(selectedId = null) {
                    const warehouseId = $('#warehouse_id').val();
                    $shipmentSelect.empty().append('<option value="">Pilih Pengiriman</option>').trigger('change');
                    if (!warehouseId) { $shipmentSelect.prop('disabled', true); return; }
                    $shipmentSelect.prop('disabled', false);
                    const url = `{{ route('admin.stok-masuk.penerimaan-barang.get-shipments') }}?warehouse_id=${warehouseId}`;
                    fetch(url).then(r=>r.json()).then(data=>{
                        data.forEach(opt=>{
                            const isSel = String(selectedId)===String(opt.id);
                            $shipmentSelect.append(new Option(opt.label, opt.id, isSel, isSel));
                        });
                        if (selectedId) $shipmentSelect.trigger('change');
                    }).catch(()=>{});
                }

                function fetchStockInOrders(selectedId = null) {
                    const warehouseId = $('#warehouse_id').val();
                    $sioSelect.empty().append('<option value="">Pilih Dokumen</option>').trigger('change');
                    if (!warehouseId) { $sioSelect.prop('disabled', true); return; }
                    $sioSelect.prop('disabled', false);
                    const url = `{{ route('admin.stok-masuk.penerimaan-barang.get-stock-in-orders') }}?warehouse_id=${warehouseId}`;
                    fetch(url).then(r=>r.json()).then(data=>{
                        data.forEach(opt=>{
                            const isSel = String(selectedId)===String(opt.id);
                            $sioSelect.append(new Option(opt.label, opt.id, isSel, isSel));
                        });
                        if (selectedId) $sioSelect.trigger('change');
                    }).catch(()=>{});
                }

                function clearRows(){ $('#detail-rows').empty(); updateSummary(); }

                function addNewDetailRow(detail){
                    const index = $('#detail-rows tr').length;
                    const label = (detail.item_label || '-');
                    const remQty = Math.round(detail.remaining_quantity || 0);
                    const remKoli = parseFloat(detail.remaining_koli || 0);
                    // preset from existing GR
                    const preset = detail.shipment_item_id ? (existingByShipment[detail.shipment_item_id] || null) : (existingByItem[detail.item_id] || null);
                    const presetQty = preset ? parseInt(preset.received_quantity || 0) : 0;
                    const presetKoli = preset ? parseFloat(preset.received_koli || 0) : 0;
                    const presetNotes = preset ? (preset.notes || '') : '';
                    const maxQty = remQty + presetQty;
                    const maxKoli = remKoli + presetKoli;
                    const row = `
                    <tr data-name="${(label||'').toString().toLowerCase()}" data-koli-ratio="${detail.item_koli_ratio||1}" data-remaining-qty="${remQty}" data-remaining-koli="${remKoli}">
                        <td>
                            <input type="text" class="form-control form-control-solid" value="${label}" readonly>
                            <input type="hidden" name="details[${index}][item_id]" value="${detail.item_id||''}">
                            <input type="hidden" name="details[${index}][shipment_item_id]" value="${detail.shipment_item_id||''}">
                            <input type="hidden" name="details[${index}][order_item_id]" value="${detail.order_item_id||''}">
                        </td>
                        <td><input type="number" step="1" min="0" name="details[${index}][ordered_quantity]" class="form-control form-control-solid text-end" value="${remQty}" readonly></td>
                        <td><input type="number" step="0.01" min="0" name="details[${index}][ordered_koli]" class="form-control form-control-solid text-end" value="${remKoli.toFixed(2)}" readonly></td>
                        <td><input type="number" step="1" min="0" max="${maxQty}" name="details[${index}][received_quantity]" class="form-control form-control-solid text-end received-quantity" value="${presetQty}"></td>
                        <td><input type="number" step="0.01" min="0" max="${maxKoli}" name="details[${index}][received_koli]" class="form-control form-control-solid text-end received-koli" value="${presetKoli}"></td>
                        <td><input type="text" name="details[${index}][notes]" class="form-control form-control-solid" value="${presetNotes}" placeholder="Catatan"></td>
                        <td class="text-center">
                            <button type="button" class="btn btn-icon btn-light-danger remove-row" disabled>
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>`;
                    $('#detail-rows').append(row);
                }

                function loadRowsFromShipment(id){
                    clearRows(); if (!id) return;
                    const url = `{{ route('admin.stok-masuk.penerimaan-barang.get-reference-details') }}?shipment_id=${id}&exclude_gr_id={{ $goodsReceipt->id }}`;
                    fetch(url).then(r=>r.json()).then(list=>{ (list||[]).forEach(d=> addNewDetailRow(d)); updateSummary(); }).catch(()=>{});
                }
                function loadRowsFromSio(id){
                    clearRows(); if (!id) return;
                    const typeParam = encodeURIComponent('{{ \App\Models\GoodsReceipt::REFERENCE_TYPE_STOCK_IN_ORDER }}');
                    const url = `{{ route('admin.stok-masuk.penerimaan-barang.get-reference-details') }}?type=${typeParam}&id=${id}`;
                    fetch(url).then(r=>r.json()).then(list=>{ (list||[]).forEach(d=> addNewDetailRow(d)); updateSummary(); }).catch(()=>{});
                }

                // Initial load
                (function initializePage(){
                    if (forcedWarehouseId !== null) {
                        $('#warehouse_id').val(String(forcedWarehouseId)).trigger('change');
                        $('#warehouse_id').prop('disabled', true);
                    }
                    if (currentType==='transfer'){
                        const existingShipmentId = @json($goodsReceipt->shipment_id);
                        fetchShipments(existingShipmentId);
                        if (existingShipmentId) { $shipmentSelect.val(String(existingShipmentId)).trigger('change'); }
                    } else {
                        fetchStockInOrders(currentSioId);
                        if (currentSioId) { $sioSelect.val(String(currentSioId)).trigger('change'); }
                    }
                })();

                // Toggle containers and reload on type change
                $('#gr_type').on('change', function(){
                    const val = $(this).val();
                    if (val==='transfer'){
                        $('#shipment-select-col').show();
                        $('#sio-select-col').hide();
                        $('#manual-shipment-row').hide();
                        fetchShipments();
                        clearRows();
                    } else {
                        $('#shipment-select-col').hide();
                        $('#sio-select-col').show();
                        $('#manual-shipment-row').show();
                        fetchStockInOrders();
                        clearRows();
                    }
                    // reset selects and required flags
                    $shipmentSelect.val(null).trigger('change');
                    $sioSelect.val(null).trigger('change');
                    toggleRequiredByType();
                });

                $shipmentSelect.on('change', function(){
                    const id = $(this).val();
                    if (id) {
                        $detailsContainer.show();
                        $detailsPlaceholder.hide();
                        loadRowsFromShipment(id);
                        fetchShipmentDetails();
                    } else {
                        $detailsContainer.hide();
                        $detailsPlaceholder.show().find('p').text('Pilih nomor pengiriman untuk melanjutkan.');
                    }
                    toggleRequiredByType();
                });
                $sioSelect.on('change', function(){
                    const id = $(this).val();
                    if (id) {
                        $detailsContainer.show();
                        $detailsPlaceholder.hide();
                        loadRowsFromSio(id);
                    } else {
                        $detailsContainer.hide();
                        $detailsPlaceholder.show().find('p').text('Pilih dokumen pengadaan untuk melanjutkan.');
                    }
                    toggleRequiredByType();
                });

                // Warehouse change should reload options like create page
                $('#warehouse_id').on('change', function(){
                    const t = $('#gr_type').val();
                    clearRows();
                    if (t==='transfer'){
                        fetchShipments();
                        $detailsContainer.hide();
                        $detailsPlaceholder.show().find('p').text('Pilih nomor pengiriman untuk melanjutkan.');
                    } else if (t==='pengadaan') {
                        fetchStockInOrders();
                        $detailsContainer.hide();
                        $detailsPlaceholder.show().find('p').text('Pilih dokumen pengadaan dan isi data pengiriman.');
                    } else {
                        $detailsContainer.hide();
                        $detailsPlaceholder.show().find('p').text('Pilih tipe terlebih dahulu.');
                    }
                });

                function setRequired($el, isReq){ $el.prop('required', !!isReq); }
                function toggleRequiredByType(){
                    const t = $('#gr_type').val();
                    if (t==='transfer'){
                        setRequired($shipmentSelect, true);
                        setRequired($('#manual_shipping_date'), false);
                        setRequired($('#manual_vehicle_type'), false);
                        setRequired($('#manual_license_plate'), false);
                    } else { // pengadaan
                        setRequired($shipmentSelect, false);
                        setRequired($('#manual_shipping_date'), true);
                        setRequired($('#manual_vehicle_type'), true);
                        setRequired($('#manual_license_plate'), true);
                    }
                }
                // initialize required flags on load
                toggleRequiredByType();
                $('#detail-rows').on('input', '.received-quantity', function() {
                    const $row = $(this).closest('tr');
                    const koliRatio = parseFloat($row.data('koli-ratio'));
                    const maxQtyAttr = parseFloat($(this).attr('max'));
                    const orderedQty = parseFloat($row.find('input[name$="[ordered_quantity]"]').val()) || 0;
                    const remainingKoli = parseFloat($row.data('remaining-koli'));
                    let qty = parseFloat($(this).val());

                    if (isNaN(qty)) qty = 0;
                    // Enforce integer and clamp to remaining
                    qty = Math.round(qty);
                    if (!isNaN(maxQtyAttr) && qty > maxQtyAttr) qty = Math.round(maxQtyAttr);
                    if (qty > orderedQty) qty = Math.round(orderedQty);
                    if (qty < 0) qty = 0;
                    $(this).val(qty);

                    let koli = koliRatio > 0 ? qty / koliRatio : 0;
                    let koliRounded = parseFloat(koli.toFixed(2));
                    if (!isNaN(remainingKoli) && Math.abs(remainingKoli - koliRounded) <= 0.01) {
                        koliRounded = remainingKoli;
                    }
                    if (!isNaN(remainingKoli) && koliRounded > remainingKoli) {
                        koliRounded = remainingKoli;
                    }
                    $row.find('.received-koli').val(koliRounded.toFixed(2));
                    updateSummary();
                });

                $('#detail-rows').on('input', '.received-koli', function() {
                    const $row = $(this).closest('tr');
                    const koliRatio = parseFloat($row.data('koli-ratio'));
                    const maxKoliAttr = parseFloat($(this).attr('max'));
                    const orderedKoli = parseFloat($row.find('input[name$="[ordered_koli]"]').val()) || 0;
                    const remainingKoli = parseFloat($row.data('remaining-koli'));
                    let koli = parseFloat($(this).val());

                    if (isNaN(koli)) koli = 0;
                    if (!isNaN(maxKoliAttr) && koli > maxKoliAttr) {
                        koli = maxKoliAttr;
                        $(this).val(koli);
                    }
                    if (koli > orderedKoli) {
                        koli = orderedKoli;
                        $(this).val(koli);
                    }

                    if (!isNaN(remainingKoli) && Math.abs(remainingKoli - koli) <= 0.01) {
                        koli = remainingKoli;
                        $(this).val(koli.toFixed(2));
                    }

                    let qty = Math.round(koli * koliRatio);
                    const orderedQty = parseFloat($row.find('input[name$="[ordered_quantity]"]').val()) || 0;
                    if (!isNaN(orderedQty) && qty > orderedQty) qty = Math.round(orderedQty);
                    if (qty < 0) qty = 0;
                    $row.find('.received-quantity').val(qty);
                    updateSummary();
                });

                // Populate shipment details and per-item limits (shipment-based)
                const shipmentFields = {
                    code: $('#sd_code'),
                    shipping_date: $('#sd_shipping_date'),
                    vehicle_type: $('#sd_vehicle_type'),
                    license_plate: $('#sd_license_plate'),
                    driver_name: $('#sd_driver_name'),
                    driver_contact: $('#sd_driver_contact'),
                    description: $('#sd_description'),
                };

                function populateShipmentFields(data) {
                    if (!data) return;
                    for (const key in shipmentFields) {
                        shipmentFields[key].text(data[key] ?? '-');
                    }
                }

                function fetchShipmentDetails() {
                    const shipmentId = $shipmentSelect.val();
                    if (!shipmentId) return;
                    const url = `{{ route('admin.stok-masuk.penerimaan-barang.get-shipment-details') }}?shipment_id=${shipmentId}`;
                    fetch(url)
                        .then(r => r.json())
                        .then(data => populateShipmentFields(data))
                        .catch(() => {});
                }

                function fetchReferenceDetailsAndApplyLimits() {
                    const shipmentId = $shipmentSelect.val();
                    if (!shipmentId) return;
                    const url = `{{ route('admin.stok-masuk.penerimaan-barang.get-reference-details') }}?shipment_id=${shipmentId}`;
                    fetch(url)
                        .then(r => r.json())
                        .then(list => {
                            const map = {};
                            (list || []).forEach(d => { map[String(d.item_id)] = d; });
                            $('#detail-rows tr').each(function(){
                                const $row = $(this);
                                const itemId = String($row.find('input[name$="[item_id]"]').val());
                                const recInput = $row.find('.received-quantity');
                                const koliInput = $row.find('.received-koli');
                                const orderedQtyInput = $row.find('input[name$="[ordered_quantity]"]');
                                const orderedKoliInput = $row.find('input[name$="[ordered_koli]"]');
                                const ref = map[itemId];
                                if (ref) {
                                    const remainingQty = parseFloat(ref.remaining_quantity ?? 0) || 0;
                                    const remainingKoli = parseFloat(ref.remaining_koli ?? 0) || 0;
                                    const currentReceivedQty = parseFloat(recInput.val()) || 0;
                                    const currentReceivedKoli = parseFloat(koliInput.val()) || 0;
                                    const allowedQty = Math.max(remainingQty, currentReceivedQty);
                                    const allowedKoli = Math.max(remainingKoli, currentReceivedKoli);

                                    $row.attr('data-remaining-qty', remainingQty);
                                    $row.attr('data-remaining-koli', remainingKoli);
                                    recInput.attr('max', allowedQty);
                                    koliInput.attr('max', allowedKoli);
                                    orderedQtyInput.val(remainingQty);
                                    orderedKoliInput.val(remainingKoli);
                                }
                            });
                            updateSummary();
                        })
                        .catch(() => {});
                }

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
                        $('#col-ordered-qty').text('Qty');
                        $('#col-ordered-koli').text('Koli');
                    }
                }

                // Call on init and on type change
                updateDetailHeadersByType();
                $('#gr_type').on('change', updateDetailHeadersByType);

                fetchShipmentDetails();
                fetchReferenceDetailsAndApplyLimits();
                updateSummary();

                $('#goods-receipt-form').on('submit', function(e) {
                    e.preventDefault();

                    const form = this;
                    const type = $('#gr_type').val();
                    const shipmentId = $('#shipment_id').val();
                    const sioId = $('#stock_in_order_id').val();
                    const manualShipDate = $('#manual_shipping_date').val();
                    const manualVehicle = $('#manual_vehicle_type').val();
                    const manualPlate = $('#manual_license_plate').val();

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
                        return;
                    }

                    Swal.fire({
                        title: 'Apakah Anda yakin?',
                        text: "Pastikan semua data yang dimasukkan sudah benar.",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Ya, update!',
                        cancelButtonText: 'Batal'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            form.submit();
                        }
                    });
                });
            });
        });
    </script>
@endpush
