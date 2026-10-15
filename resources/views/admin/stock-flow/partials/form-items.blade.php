<div class="alert alert-primary p-5 mb-7">
    <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap mb-2">
        <div class="fw-bold">Input Cepat Item</div>
        <button type="button" class="btn btn-sm btn-light" id="btn_toggle_quick_items">Sembunyikan Form</button>
    </div>
    <div id="flow_quick_items_body">
        <div class="text-muted fs-7 mb-3" id="flow_quick_item_hint">
            Tulis satu item per baris. Format: SKU koli/qty catatan.
        </div>
        @if(!empty($enableInputUnitSelect ?? false))
            <div class="w-200px mb-3">
                <label class="form-label fw-bold mb-1">Satuan Input Cepat</label>
                <select class="form-select form-select-solid" id="flow_quick_input_unit">
                    <option value="koli" selected>Koli</option>
                    <option value="pcs">PCS</option>
                </select>
                <div class="form-text">PCS hanya untuk Gudang Display atau Gudang Rusak.</div>
            </div>
        @endif
        <textarea class="form-control form-control-solid mb-3" id="flow_quick_items" rows="4" placeholder="SKU-001 2&#10;SKU-002 5 Catatan item"></textarea>
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <button type="button" class="btn btn-light-primary" id="btn_add_quick_items">Tambahkan dari Teks</button>
            <div class="text-danger fs-7" id="flow_quick_items_error"></div>
        </div>
    </div>
</div>
<div id="flow_items_container"></div>
<div class="mb-7 d-flex flex-wrap gap-3">
    <button type="button" class="btn btn-light" id="btn_add_flow_item">Tambah Item</button>
    @if(!empty($itemImportUrl ?? null))
        <button type="button" class="btn btn-light-primary" id="btn_open_item_import">
            Import Item Excel
        </button>
    @endif
</div>
