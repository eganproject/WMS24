@if(count($warehouseOptions ?? []) > 0)
    <div class="fv-row mb-7" id="flow_warehouse_row" style="display:none;">
        <label class="required fs-6 fw-bold form-label mb-2">Gudang Tujuan</label>
        <select class="form-select form-select-solid" name="warehouse_id" id="flow_warehouse_id">
            <option value="">Pilih Gudang</option>
            @foreach($warehouseOptions as $wh)
                <option value="{{ $wh['id'] }}">{{ $wh['name'] }}</option>
            @endforeach
        </select>
        <div class="invalid-feedback" id="error_warehouse_id"></div>
    </div>
@endif
<div class="fv-row mb-7">
    <label class="fs-6 fw-bold form-label mb-2">Tanggal</label>
    <input type="text" class="form-control form-control-solid" name="transacted_at" id="flow_transacted_at" placeholder="YYYY-MM-DD HH:mm" />
    <div class="invalid-feedback" id="error_transacted_at"></div>
</div>
<div class="fv-row mb-7">
    <label class="fs-6 fw-bold form-label mb-2">Ref No</label>
    <input type="text" class="form-control form-control-solid" name="ref_no" id="flow_ref_no" />
    <div class="invalid-feedback" id="error_ref_no"></div>
</div>
@if(!empty($showRecipientFields ?? false))
    <div id="flow_recipient_fields">
        <div class="row g-4 mb-7">
            <div class="col-md-6">
                <label class="fs-6 fw-bold form-label mb-2">Nama Penerima</label>
                <input type="text" class="form-control form-control-solid" name="recipient_name" id="flow_recipient_name" />
                <div class="invalid-feedback" id="error_recipient_name"></div>
            </div>
            <div class="col-md-6">
                <label class="fs-6 fw-bold form-label mb-2">Telepon/Kontak Penerima</label>
                <input type="text" class="form-control form-control-solid" name="recipient_phone" id="flow_recipient_phone" />
                <div class="invalid-feedback" id="error_recipient_phone"></div>
            </div>
        </div>
        <div class="fv-row mb-7">
            <label class="fs-6 fw-bold form-label mb-2">Alamat Penerima</label>
            <textarea class="form-control form-control-solid" name="recipient_address" id="flow_recipient_address" rows="3"></textarea>
            <div class="invalid-feedback" id="error_recipient_address"></div>
        </div>
    </div>
@endif
@if(isset($supplierFlowTypes) && count($supplierFlowTypes) > 0)
    <div class="fv-row mb-7" id="flow_supplier_row" style="display:none;">
        <label class="fs-6 fw-bold form-label mb-2">Supplier</label>
        <select class="form-select form-select-solid" name="supplier_id" id="flow_supplier_id">
            <option value="">Pilih Supplier</option>
            @foreach(($suppliers ?? []) as $supplier)
                <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
            @endforeach
        </select>
        <div class="invalid-feedback" id="error_supplier_id"></div>
    </div>
@endif
@if(!empty($showDeliveryNoteFields ?? false))
    <div class="fv-row mb-7">
        <label class="fs-6 fw-bold form-label mb-2">{{ $deliveryNoteNoLabel ?? 'No Surat Jalan' }}</label>
        <input type="text" class="form-control form-control-solid" name="surat_jalan_no" id="flow_surat_jalan_no" />
        <div class="invalid-feedback" id="error_surat_jalan_no"></div>
    </div>
    <div class="fv-row mb-7">
        <label class="fs-6 fw-bold form-label mb-2">{{ $deliveryNoteDateLabel ?? 'Tanggal Surat Jalan' }}</label>
        <input type="text" class="form-control form-control-solid" name="surat_jalan_at" id="flow_surat_jalan_at" placeholder="YYYY-MM-DD" />
        <div class="invalid-feedback" id="error_surat_jalan_at"></div>
    </div>
    <div class="fv-row mb-7">
        <label class="fs-6 fw-bold form-label mb-2">{{ $deliveryNoteImageLabel ?? 'Gambar Surat Jalan' }}</label>
        <input type="file" class="form-control form-control-solid" name="surat_jalan_image" id="flow_surat_jalan_image" accept="image/jpeg,image/png,image/webp" />
        <div class="form-text">Opsional. Format JPG, PNG, atau WEBP. Maksimal 2 MB.</div>
        <div class="invalid-feedback d-block" id="error_surat_jalan_image"></div>
        <div class="mt-3" id="flow_surat_jalan_image_preview" style="display:none;">
            <a href="#" target="_blank" rel="noopener" class="btn btn-light-primary btn-sm" id="flow_surat_jalan_image_link">{{ $deliveryNoteImageLinkLabel ?? 'Lihat gambar saat ini' }}</a>
            <label class="form-check form-check-sm form-check-custom form-check-solid mt-3">
                <input class="form-check-input" type="checkbox" name="remove_surat_jalan_image" id="flow_remove_surat_jalan_image" value="1" />
                <span class="form-check-label">Hapus gambar saat ini</span>
            </label>
        </div>
    </div>
@endif
<div class="fv-row mb-7">
    <label class="fs-6 fw-bold form-label mb-2">Catatan</label>
    <textarea class="form-control form-control-solid" name="note" id="flow_note" rows="3"></textarea>
    <div class="invalid-feedback" id="error_note"></div>
</div>
