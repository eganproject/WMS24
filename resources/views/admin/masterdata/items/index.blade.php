@extends('layouts.admin')

@section('title', 'Items')
@section('page_title', 'Items')

@php
    use App\Support\Permission as Perm;
    $canCreate = Perm::can(auth()->user(), 'admin.masterdata.items.index', 'create');
    $canUpdate = Perm::can(auth()->user(), 'admin.masterdata.items.index', 'update');
    $canDelete = Perm::can(auth()->user(), 'admin.masterdata.items.index', 'delete');
@endphp

@section('content')
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div class="d-flex align-items-center position-relative my-1">
                <span class="svg-icon svg-icon-1 position-absolute ms-6">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <rect opacity="0.5" x="17.0365" y="15.1223" width="8.15546" height="2" rx="1" transform="rotate(45 17.0365 15.1223)" fill="black" />
                        <path d="M11 19C6.55556 19 3 15.4444 3 11C3 6.55556 6.55556 3 11 3C15.4444 3 19 6.55556 19 11C19 15.4444 15.4444 19 11 19ZM11 5C7.53333 5 5 7.53333 5 11C5 14.4667 7.53333 17 11 17C14.4667 17 17 14.4667 17 11C17 7.53333 14.4667 5 11 5Z" fill="black" />
                    </svg>
                </span>
                <input type="text" class="form-control form-control-solid w-250px ps-14" placeholder="Search items" data-kt-filter="search" />
            </div>
        </div>
        <div class="card-toolbar">
            <div class="d-flex flex-wrap justify-content-end align-items-center gap-2" data-kt-user-table-toolbar="base">
                <select class="form-select form-select-solid w-100px" id="filter_items_limit" aria-label="Limit">
                    <option value="10" selected>10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                <button type="button" class="btn btn-light-primary me-3" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                    <span class="svg-icon svg-icon-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <path d="M19.0759 3H4.72777C3.95892 3 3.47768 3.83148 3.86067 4.49814L8.56967 12.6949C9.17923 13.7559 9.5 14.9582 9.5 16.1819V19.5072C9.5 20.2189 10.2223 20.7028 10.8805 20.432L13.8805 19.1977C14.2553 19.0435 14.5 18.6783 14.5 18.273V13.8372C14.5 12.8089 14.8171 11.8056 15.408 10.964L19.8943 4.57465C20.3596 3.912 19.8856 3 19.0759 3Z" fill="black" />
                        </svg>
                    </span>
                    Filter
                </button>
                <div class="menu menu-sub menu-sub-dropdown w-300px w-md-325px" data-kt-menu="true" data-kt-menu-dismiss="false">
                    <div class="px-7 py-5">
                        <div class="fs-5 text-dark fw-bolder">Filter Options</div>
                    </div>
                    <div class="separator border-gray-200"></div>
                    <div class="px-7 py-5">
                        <div class="mb-10">
                            <label class="form-label fs-6 fw-bold">Category:</label>
                            <select id="filter_item_category" class="form-select form-select-solid fw-bolder" data-placeholder="Select option" data-allow-clear="true">
                                <option value="">Semua</option>
                                <option value="0">Tanpa Kategori</option>
                                @foreach($categories as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-10">
                            <label class="form-label fs-6 fw-bold">Status:</label>
                            <select id="filter_item_status" class="form-select form-select-solid fw-bolder">
                                <option value="active" selected>Aktif</option>
                                <option value="inactive">Nonaktif</option>
                                <option value="all">Semua</option>
                            </select>
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="button" class="btn btn-light btn-active-light-primary me-2" id="filter_items_reset">Reset</button>
                            <button type="button" class="btn btn-primary" id="filter_items_apply">Apply</button>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn btn-light-success me-3" id="btn_export_items">
                    <i class="fas fa-file-excel me-2"></i>Export Excel
                </button>
                @if($canUpdate)
                    <button type="button" class="btn btn-light-dark me-3" id="btn_update_items_excel" data-bs-toggle="modal" data-bs-target="#modal_update_items_excel">Update Data Items</button>
                @endif
                @if($canCreate)
                    <button type="button" class="btn btn-light-primary me-3" id="btn_import_items" data-bs-toggle="modal" data-bs-target="#modal_import_items">Import Items</button>
                    <button type="button" class="btn btn-light-success me-3" id="btn_import_barcodes" data-bs-toggle="modal" data-bs-target="#modal_import_barcodes">Import Barcode Alias</button>
                    <button type="button" class="btn btn-light-warning me-3" id="btn_import_bundle" data-bs-toggle="modal" data-bs-target="#modal_import_bundle">Import Bundle</button>
                    <button type="button" class="btn btn-light-info me-3" id="btn_open_barcode_misses" data-bs-toggle="modal" data-bs-target="#modal_barcode_misses">Barcode Tidak Dikenal</button>
                    <a href="{{ route('admin.masterdata.items.create') }}" class="btn btn-primary" id="btn_open_create_item">
                        Add Item
                    </a>
                @endif
            </div>
        </div>
    </div>
    <div class="card-body py-6">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5" id="items_table">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                        <th>No</th>
                        <th>SKU</th>
                        <th>Nama</th>
                        <th>Tipe</th>
                        <th>Status</th>
                        <th>Kategori</th>
                        <th>Lokasi Display</th>
                        <th>Lokasi Gd. Besar</th>
                        <th>Deskripsi</th>
                        <th>Bundle</th>
                        <th class="text-end">Isi/Koli</th>
                        <th class="text-end">Stok Pengaman</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<!--begin::Import Modal-->
<div class="modal fade" id="modal_import_items" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bolder">Import Items (Excel)</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <span class="svg-icon svg-icon-1">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black" />
                            <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black" />
                        </svg>
                    </span>
                </div>
            </div>
            <div class="modal-body scroll-y px-10 py-10">
                <div class="mb-7">
                    <p class="fw-semibold mb-3">Pastikan file Excel memiliki header dan kolom berikut:</p>
                    <div class="alert alert-light-warning border border-warning border-dashed mb-5">
                        Import Excel master item hanya untuk <strong>item single / stok fisik</strong>.<br>
                        <strong>Bundle tidak didukung</strong> lewat import dan harus dibuat dari form master item agar BOM komponen tetap rapi.
                    </div>
                    <ul class="ms-5 mb-4">
                        <li><strong>sku</strong> (wajib, unik)</li>
                        <li><strong>name</strong> (wajib)</li>
                        <li><strong>status</strong> (opsional: <code>active</code>/<code>inactive</code>, default <code>active</code>)</li>
                        <li><strong>parent_category</strong> (opsional, parent kategori; akan dibuat jika belum ada)</li>
                        <li><strong>category</strong> (opsional, anak kategori; jika kosong akan dimasukkan ke kategori default "Tanpa Kategori")</li>
                        <li><strong>stock_gudang_besar</strong> / <strong>stok_gudang_besar</strong> (opsional, stok awal gudang besar)</li>
                        <li><strong>stock_gudang_display</strong> / <strong>stok_gudang_display</strong> (opsional, stok awal gudang display)</li>
                        <li><strong>stock</strong> / <strong>stok</strong> / <strong>qty</strong> (opsional, stok awal default gudang besar)</li>
                        <li><strong>safety_stock_gudang_besar</strong> / <strong>stok_pengaman_gudang_besar</strong> (opsional, safety stock gudang besar)</li>
                        <li><strong>safety_stock_gudang_display</strong> / <strong>stok_pengaman_gudang_display</strong> (opsional, safety stock gudang display)</li>
                        <li><strong>safety_stock</strong> / <strong>stok_pengaman</strong> (opsional, jumlah stok pengaman)</li>
                        <li><strong>koli_qty</strong> / <strong>isi_koli</strong> (opsional, isi per koli/pcs)</li>
                        <li><strong>address</strong> (opsional, bisa diisi <code>KAB</code> atau alamat detail seperti <code>KAB-A-03-05</code>)</li>
                        <li><strong>area</strong> (opsional, boleh berdiri sendiri sebagai alamat sementara)</li>
                        <li><strong>area</strong> + <strong>rack</strong> + <strong>column</strong> + <strong>row</strong> (opsional, jika ingin alamat detail; jika salah satu detail diisi maka semuanya wajib)</li>
                        <li><strong>main_address</strong> (opsional, bin location gudang besar dengan format lengkap <code>AREA-RACK-KOLOM-BARIS</code>, misalnya <code>GB1-A-01-02</code>; area harus area gudang besar)</li>
                        <li><strong>address_2</strong> dan <strong>main_address_2</strong> (opsional, alamat ke-2 di gudang display dan gudang besar, format lengkap <code>AREA-RACK-KOLOM-BARIS</code>; hanya informasi)</li>
                        <li><strong>description</strong> (opsional)</li>
                    </ul>
                    <p class="text-muted small mb-1">Jika Anda menambahkan kolom <code>item_type</code>, nilainya hanya boleh <code>single</code>. Nilai <code>bundle</code> akan ditolak.</p>
                    <p class="text-muted small mb-1">Tambahkan kolom <code>external_barcodes</code> atau <code>barcode</code> jika ingin mengisi alias barcode/QR eksternal. Pisahkan banyak barcode dengan koma, titik koma, atau baris baru.</p>
                    <p class="text-muted small mb-1">Contoh header: <code>sku,name,item_type,status,parent_category,category,stock_gudang_besar,stock_gudang_display,stock,safety_stock_gudang_besar,safety_stock_gudang_display,safety_stock,koli_qty,external_barcodes,area,rack,column,row,main_address,address_2,main_address_2,description</code></p>
                    <p class="text-muted small mb-1">Gunakan format Excel (.xlsx/.xls) dengan header di baris pertama.</p>
                    <p class="text-muted small mb-1">Jika kolom category dikosongkan, item otomatis dimasukkan ke kategori "Tanpa Kategori".</p>
                    <p class="text-muted small mb-0">Catatan: gunakan <code>area</code> saja jika baru tahu area umum. Jika ingin alamat detail, lengkapi <code>area</code>, <code>rack</code>, <code>column</code>, dan <code>row</code>.</p>
                    <div class="mt-4">
                        <a href="{{ route('admin.masterdata.items.template') }}" class="btn btn-light-primary">
                            Download Template Header
                        </a>
                    </div>
                </div>
                <div class="mb-10">
                    <label class="required fs-6 fw-bold form-label mb-2">File Excel</label>
                    <input type="file" class="form-control form-control-solid" id="import_items_file" accept=".xlsx,.xls" />
                    <div class="invalid-feedback d-block" id="error_import_file"></div>
                </div>
                <div class="text-end">
                    <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary" id="btn_import_items_submit">Import</button>
                </div>
            </div>
        </div>
    </div>
</div>
<!--end::Import Modal-->

<!--begin::Update Items Excel Modal-->
<div class="modal fade" id="modal_update_items_excel" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-900px">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 class="fw-bolder mb-1">Update Data Items via Excel</h2>
                    <div class="text-muted fs-7">Pilih field, unduh template yang sesuai, lalu unggah hasil perubahannya.</div>
                </div>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <span class="svg-icon svg-icon-1">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black" />
                            <rect x="7.41422" y="6.34315" width="16" height="2" rx="1" transform="rotate(45 7.41422 6.34315)" fill="black" />
                        </svg>
                    </span>
                </div>
            </div>
            <div class="modal-body scroll-y px-10 py-8">
                <div class="alert alert-light-danger border border-danger border-dashed d-flex align-items-start p-5 mb-8">
                    <i class="fas fa-lock text-danger fs-2 me-4 mt-1"></i>
                    <div>
                        <div class="fw-bold text-gray-900 mb-1">Field yang dikunci</div>
                        <div class="text-gray-700 fs-7">SKU hanya dipakai untuk mencari item. SKU, tipe item, isi koli, data bundle, barcode, dan jumlah stok tidak dapat diubah dari fitur ini.</div>
                    </div>
                </div>

                <div class="d-flex align-items-center mb-4">
                    <span class="badge badge-circle badge-primary me-3">1</span>
                    <div>
                        <div class="fw-bold text-gray-900">Pilih field yang akan diperbarui</div>
                        <div class="text-muted fs-7">Import hanya akan menyentuh field yang dicentang.</div>
                    </div>
                </div>
                <div class="row g-4 mb-5" id="item_update_fields">
                    @foreach($itemUpdateFields as $field => $definition)
                        <div class="col-md-6">
                            <label class="border border-gray-300 border-dashed rounded-3 p-4 d-flex align-items-start h-100 item-update-field-card" for="item_update_field_{{ $field }}">
                                <span class="form-check form-check-custom form-check-solid me-4 mt-1">
                                    <input
                                        class="form-check-input item-update-field"
                                        type="checkbox"
                                        value="{{ $field }}"
                                        data-label="{{ $definition['label'] }}"
                                        id="item_update_field_{{ $field }}"
                                        @checked($field === 'name')
                                    />
                                </span>
                                <span>
                                    <span class="fw-bold text-gray-900 d-block mb-1">{{ $definition['label'] }}</span>
                                    <span class="text-muted fs-7">{{ $definition['description'] }}</span>
                                </span>
                            </label>
                        </div>
                    @endforeach
                </div>
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 bg-light-primary rounded-3 p-4 mb-8">
                    <div>
                        <div class="text-muted fs-8 text-uppercase fw-bold mb-2">Kolom template</div>
                        <div class="d-flex flex-wrap gap-2" id="item_update_field_summary"></div>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-sm btn-light" id="btn_clear_item_update_fields">Kosongkan</button>
                        <button type="button" class="btn btn-sm btn-light-primary" id="btn_select_all_item_update_fields">Pilih Semua</button>
                        <button type="button" class="btn btn-primary" id="btn_download_item_update_template">
                            <i class="fas fa-file-excel me-2"></i>Download Template
                        </button>
                    </div>
                </div>

                <div class="separator separator-dashed mb-8"></div>

                <div class="d-flex align-items-center mb-4">
                    <span class="badge badge-circle badge-success me-3">2</span>
                    <div>
                        <div class="fw-bold text-gray-900">Unggah template yang sudah diedit</div>
                        <div class="text-muted fs-7">Jangan mengubah header atau menambahkan kolom di luar template.</div>
                    </div>
                </div>
                <div class="alert alert-light-info py-4 mb-5 fs-7">
                    Sel kosong akan mengosongkan kategori, alamat, dan deskripsi. Untuk stok pengaman, sel kosong menjadi <strong>0</strong>. Nama dan status tidak boleh kosong.
                </div>
                <div class="mb-7">
                    <label class="required fs-6 fw-bold form-label mb-2">File Excel</label>
                    <input type="file" class="form-control form-control-solid" id="item_update_file" accept=".xlsx,.xls" />
                    <div class="invalid-feedback d-block" id="item_update_error"></div>
                </div>
                <div class="text-end">
                    <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-success" id="btn_submit_item_update">
                        <i class="fas fa-upload me-2"></i>Proses Update
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
<!--end::Update Items Excel Modal-->

<!--begin::Import Barcode Modal-->
<div class="modal fade" id="modal_import_barcodes" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bolder">Import Barcode Alias (Excel)</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <span class="svg-icon svg-icon-1">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black" />
                            <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black" />
                        </svg>
                    </span>
                </div>
            </div>
            <div class="modal-body scroll-y px-10 py-10">
                <div class="mb-7">
                    <p class="fw-semibold mb-3">Gunakan import ini untuk menambah atau memperbarui barcode/QR eksternal tanpa mengubah master item.</p>
                    <ul class="ms-5 mb-4">
                        <li><strong>sku</strong> (wajib, harus item single/stok fisik)</li>
                        <li><strong>barcode</strong> (wajib, unik)</li>
                        <li><strong>source_name</strong> (opsional)</li>
                        <li><strong>note</strong> (opsional)</li>
                    </ul>
                    <a href="{{ route('admin.masterdata.items.barcode-template') }}" class="btn btn-light-primary">
                        Download Template Barcode Alias
                    </a>
                </div>
                <div class="mb-10">
                    <label class="required fs-6 fw-bold form-label mb-2">File Excel</label>
                    <input type="file" class="form-control form-control-solid" id="import_barcodes_file" accept=".xlsx,.xls" />
                    <div class="invalid-feedback d-block" id="error_import_barcodes_file"></div>
                </div>
                <div class="text-end">
                    <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-success" id="btn_import_barcodes_submit">Import</button>
                </div>
            </div>
        </div>
    </div>
</div>
<!--end::Import Barcode Modal-->

<!--begin::Import Bundle Modal-->
<div class="modal fade" id="modal_import_bundle" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bolder">Import Komponen Bundle (Excel)</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <span class="svg-icon svg-icon-1">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black" />
                            <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black" />
                        </svg>
                    </span>
                </div>
            </div>
            <div class="modal-body scroll-y px-10 py-10">
                <div class="mb-7">
                    <p class="fw-semibold mb-3">Import ini digunakan untuk membuat SKU bundle dan mengatur komponen (BOM) dalam satu file.</p>
                    <div class="alert alert-light-warning border border-warning border-dashed mb-5">
                        <strong>Perhatian:</strong> Import ini akan <strong>mengganti seluruh komponen</strong> bundle yang ada di file. Komponen lama akan dihapus dan diganti dengan data dari file.<br>
                        Jika <code>bundle_sku</code> belum ada di master item, sistem akan otomatis membuatnya sebagai item bundle.
                    </div>
                    <ul class="ms-5 mb-4">
                        <li><strong>bundle_sku</strong> (wajib) — SKU item bundle; akan dibuat otomatis jika belum ada</li>
                        <li><strong>bundle_name</strong> (opsional) — nama item bundle baru; jika kosong akan memakai nilai <code>bundle_sku</code></li>
                        <li><strong>parent_category</strong> (opsional) — parent kategori; akan dibuat jika belum ada</li>
                        <li><strong>category</strong> / <strong>category_id</strong> (opsional) — kategori bundle; jika kosong, kategori bundle lama tidak diubah</li>
                        <li><strong>component_sku</strong> (wajib) — SKU item komponen (item_type = single)</li>
                        <li><strong>required_qty</strong> (wajib) — jumlah komponen yang dibutuhkan per satu bundle, harus angka bulat minimal 1</li>
                    </ul>
                    <p class="text-muted small mb-1">Satu bundle bisa memiliki banyak baris komponen. Kelompokkan semua komponen bundle di bawah <code>bundle_sku</code> yang sama.</p>
                    <p class="text-muted small mb-1">Jika SKU sudah ada sebagai item single, import akan ditolak supaya SKU tidak berubah makna.</p>
                    <p class="text-muted small mb-1">Alias yang didukung: <code>sku_bundle</code> / <code>bundle</code> untuk bundle; <code>nama_bundle</code> / <code>name</code> / <code>nama</code> untuk nama bundle; <code>parent_kategori</code> untuk parent kategori; <code>kategori</code> / <code>kategori_id</code> untuk kategori; <code>sku_komponen</code> / <code>komponen</code> untuk komponen; <code>qty</code> / <code>jumlah</code> untuk jumlah.</p>
                    <p class="text-muted small mb-0">Gunakan format Excel (.xlsx/.xls) dengan header di baris pertama.</p>
                    <div class="mt-4">
                        <a href="{{ route('admin.masterdata.items.bundle-template') }}" class="btn btn-light-primary">
                            Download Template Bundle
                        </a>
                    </div>
                </div>
                <div class="mb-10">
                    <label class="required fs-6 fw-bold form-label mb-2">File Excel</label>
                    <input type="file" class="form-control form-control-solid" id="import_bundle_file" accept=".xlsx,.xls" />
                    <div class="invalid-feedback d-block" id="error_import_bundle_file"></div>
                </div>
                <div class="text-end">
                    <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-warning" id="btn_import_bundle_submit">Import</button>
                </div>
            </div>
        </div>
    </div>
</div>
<!--end::Import Bundle Modal-->

<div class="modal fade" id="modal_barcode_misses" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-1000px">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 class="fw-bolder mb-1">Barcode Tidak Dikenal</h2>
                    <div class="text-muted fs-7">Hubungkan kode scan gagal ke item agar menjadi barcode alias.</div>
                </div>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <span class="svg-icon svg-icon-1">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black" />
                            <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black" />
                        </svg>
                    </span>
                </div>
            </div>
            <div class="modal-body px-10 py-10">
                <div class="d-flex align-items-center justify-content-between mb-5">
                    <input type="text" class="form-control form-control-solid w-250px" id="barcode_miss_search" placeholder="Cari kode/context" />
                    <select class="form-select form-select-solid w-175px" id="barcode_miss_status">
                        <option value="open" selected>Belum selesai</option>
                        <option value="resolved">Sudah selesai</option>
                        <option value="all">Semua</option>
                    </select>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-4" id="barcode_misses_table">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                <th>Kode Scan</th>
                                <th>Context</th>
                                <th>Sumber</th>
                                <th>Jumlah</th>
                                <th>Terakhir</th>
                                <th>Resolved</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal_item_qr" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-550px">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 class="fw-bolder mb-1">QR Code Item</h2>
                    <div class="text-muted fs-7" id="item_qr_label">-</div>
                </div>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <span class="svg-icon svg-icon-1">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black" />
                            <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black" />
                        </svg>
                    </span>
                </div>
            </div>
            <div class="modal-body px-10 py-10">
                <div class="border border-gray-300 border-dashed rounded-3 bg-light d-flex align-items-center justify-content-center p-6 mb-6 min-h-420px">
                    <img src="" alt="QR Code Item" id="item_qr_image" class="mw-100 mh-400px" />
                </div>
                <div class="text-muted fs-7 mb-6">QR code dibuat langsung dari SKU dalam format PNG, lengkap dengan label SKU di bagian bawah.</div>
                <div class="text-end">
                    <a href="#" class="btn btn-light-primary" id="item_qr_download" download>Download QR</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const csrfToken = '{{ csrf_token() }}';
    const dataUrl   = '{{ route('admin.masterdata.items.data') }}';
    const showTpl   = '{{ route('admin.masterdata.items.show', ':id') }}';
    const editTpl   = '{{ route('admin.masterdata.items.edit', ':id') }}';
    const deleteTpl = '{{ route('admin.masterdata.items.destroy', ':id') }}';
    const importUrl = '{{ route('admin.masterdata.items.import') }}';
    const itemUpdateTemplateUrl = '{{ route('admin.masterdata.items.update-template') }}';
    const itemsExportUrl = '{{ route('admin.masterdata.items.export') }}';
    const itemUpdateImportUrl = '{{ route('admin.masterdata.items.update-import') }}';
    const barcodeImportUrl = '{{ route('admin.masterdata.items.barcode-import') }}';
    const barcodeMissesDataUrl = '{{ route('admin.masterdata.items.barcode-misses.data') }}';
    const barcodeMissResolveTpl = '{{ route('admin.masterdata.items.barcode-misses.resolve', ':id') }}';
    const bundleImportUrl = '{{ route('admin.masterdata.items.bundle-import') }}';
    const qrCodeTpl = '{{ route('admin.masterdata.items.qr-code', ':id') }}';
    const canUpdate = {{ $canUpdate ? 'true' : 'false' }};
    const canDelete = {{ $canDelete ? 'true' : 'false' }};
    const componentItemOptionsHtml = `@foreach($componentItems as $componentItem)<option value="{{ $componentItem->id }}" data-sku="{{ $componentItem->sku }}" data-name="{{ $componentItem->name }}">{{ $componentItem->sku }} - {{ $componentItem->name }}</option>@endforeach`;

    document.addEventListener('DOMContentLoaded', () => {
        const tableEl = $('#items_table');
        const searchInput = document.querySelector('[data-kt-filter="search"]');
        const applyBtn = document.getElementById('filter_items_apply');
        const resetBtn = document.getElementById('filter_items_reset');
        const limitSelect = document.getElementById('filter_items_limit');
        const categoryFilter = document.getElementById('filter_item_category');
        const importBtn = document.getElementById('btn_import_items');
        const importModalEl = document.getElementById('modal_import_items');
        const importModal = importModalEl ? new bootstrap.Modal(importModalEl) : null;
        const importInput = document.getElementById('import_items_file');
        const importError = document.getElementById('error_import_file');
        const importSubmit = document.getElementById('btn_import_items_submit');
        const itemUpdateModalEl = document.getElementById('modal_update_items_excel');
        const itemUpdateModal = itemUpdateModalEl ? new bootstrap.Modal(itemUpdateModalEl) : null;
        const itemUpdateFile = document.getElementById('item_update_file');
        const itemUpdateError = document.getElementById('item_update_error');
        const itemUpdateSubmit = document.getElementById('btn_submit_item_update');
        const itemUpdateDownload = document.getElementById('btn_download_item_update_template');
        const itemUpdateFieldEls = Array.from(document.querySelectorAll('.item-update-field'));
        const barcodeImportModalEl = document.getElementById('modal_import_barcodes');
        const barcodeImportModal = barcodeImportModalEl ? new bootstrap.Modal(barcodeImportModalEl) : null;
        const barcodeImportInput = document.getElementById('import_barcodes_file');
        const barcodeImportError = document.getElementById('error_import_barcodes_file');
        const barcodeImportSubmit = document.getElementById('btn_import_barcodes_submit');
        const barcodeMissesModalEl = document.getElementById('modal_barcode_misses');
        const barcodeMissesModal = barcodeMissesModalEl ? new bootstrap.Modal(barcodeMissesModalEl) : null;
        const barcodeMissSearch = document.getElementById('barcode_miss_search');
        const barcodeMissStatus = document.getElementById('barcode_miss_status');
        const barcodeMissesTableEl = $('#barcode_misses_table');
        const bundleImportModalEl = document.getElementById('modal_import_bundle');
        const bundleImportModal = bundleImportModalEl ? new bootstrap.Modal(bundleImportModalEl) : null;
        const bundleImportInput = document.getElementById('import_bundle_file');
        const bundleImportError = document.getElementById('error_import_bundle_file');
        const bundleImportSubmit = document.getElementById('btn_import_bundle_submit');
        const qrModalEl = document.getElementById('modal_item_qr');
        const qrModal = qrModalEl ? new bootstrap.Modal(qrModalEl) : null;
        const qrLabelEl = document.getElementById('item_qr_label');
        const qrImageEl = document.getElementById('item_qr_image');
        const qrDownloadEl = document.getElementById('item_qr_download');
        let barcodeMissesDt = null;
        const notifyError = (message, title = 'Error') => {
            if (window.AppSwal?.error) {
                return window.AppSwal.error(message, title);
            }
            if (typeof Swal !== 'undefined') {
                return Swal.fire(title, message || 'Terjadi kesalahan', 'error');
            }
            alert(message || 'Terjadi kesalahan');
        };
        const notifySuccess = (message, title = 'Berhasil') => {
            if (typeof Swal !== 'undefined') {
                return Swal.fire(title, message || 'Berhasil', 'success');
            }
            alert(message || 'Berhasil');
        };
        const confirmAction = async () => {
            if (typeof Swal === 'undefined') {
                return true;
            }
            const result = await Swal.fire({
                title: 'Apakah Anda yakin?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Ya, lanjutkan',
                cancelButtonText: 'Batal',
                allowOutsideClick: false,
                allowEscapeKey: false,
                allowEnterKey: false,
                focusConfirm: false,
            });
            if (!result.isConfirmed) {
                return false;
            }
            Swal.fire({
                title: 'Memproses...',
                allowOutsideClick: false,
                allowEscapeKey: false,
                allowEnterKey: false,
                didOpen: () => {
                    Swal.showLoading();
                },
            });
            return true;
        };
        const closeSwal = () => {
            if (typeof Swal !== 'undefined') {
                Swal.close();
            }
        };

        const escapeHtml = (value) => String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');

        const selectedItemUpdateFields = () => itemUpdateFieldEls
            .filter((input) => input.checked)
            .map((input) => input.value);

        const refreshItemUpdateFieldState = () => {
            const selected = selectedItemUpdateFields();
            itemUpdateFieldEls.forEach((input) => {
                const card = input.closest('.item-update-field-card');
                card?.classList.toggle('border-primary', input.checked);
                card?.classList.toggle('bg-light-primary', input.checked);
            });

            const summary = document.getElementById('item_update_field_summary');
            if (summary) {
                const fieldBadges = itemUpdateFieldEls
                    .filter((input) => input.checked)
                    .map((input) => `<span class="badge badge-light-primary">${escapeHtml(input.dataset.label || input.value)}</span>`)
                    .join('');
                summary.innerHTML = `<span class="badge badge-light-dark"><i class="fas fa-lock me-1"></i>SKU</span>${fieldBadges || '<span class="text-danger fs-7">Pilih minimal satu field</span>'}`;
            }

            if (itemUpdateDownload) itemUpdateDownload.disabled = selected.length === 0;
            if (itemUpdateSubmit) itemUpdateSubmit.disabled = selected.length === 0;
        };

        const itemUpdateUrlWithFields = (baseUrl, fields) => {
            const params = new URLSearchParams();
            fields.forEach((field) => params.append('fields[]', field));
            return `${baseUrl}?${params.toString()}`;
        };

        if (!tableEl.length || !$.fn.DataTable) {
            console.error('DataTables unavailable');
            return;
        }

        if (typeof $ !== 'undefined' && $.fn.select2) {
            $(categoryFilter).select2({
                placeholder: 'Semua',
                allowClear: true,
                width: '100%'
            }).on('select2:opening select2:closing select2:close', function(e){ e.stopPropagation(); });
            $('#filter_item_status').select2({
                placeholder: 'Status',
                allowClear: false,
                width: '100%'
            }).on('select2:opening select2:closing select2:close', function(e){ e.stopPropagation(); });
        }

        const refreshMenus = () => {
            if (window.KTMenu) {
                KTMenu.createInstances();
            }
        };

        const qrUrl = (itemId, download = false) => {
            const baseUrl = qrCodeTpl.replace(':id', String(itemId));
            return download ? `${baseUrl}?download=1` : baseUrl;
        };

        const qrFilename = (sku) => {
            const normalized = String(sku || '')
                .trim()
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '');

            return `qr-${normalized || 'item'}.png`;
        };

        const dt = tableEl.DataTable({
            processing: true,
            serverSide: true,
            dom: 'rtip',
            order: [[0, 'desc']],
            pageLength: Number(limitSelect?.value || 10),
            ajax: {
                url: dataUrl,
                dataSrc: 'data',
                data: function(params) {
                    params.q = searchInput?.value || '';
                    params.category_id = categoryFilter?.value || '';
                    params.status = document.getElementById('filter_item_status')?.value || 'active';
                }
            },
            columns: [
                { data: null, orderable: false, searchable: false, render: (data, type, row, meta) => meta.row + meta.settings._iDisplayStart + 1 },
                { data: 'sku', render: (data, type, row) => type === 'display' ? `<a href="${showTpl.replace(':id', row.id)}" class="text-gray-800 text-hover-primary fw-bold">${escapeHtml(data)}</a>` : data },
                { data: 'name' },
                { data: 'type_label', render: (data, type, row) => row.item_type === 'bundle' ? '<span class="badge badge-light-primary">Bundle</span>' : '<span class="badge badge-light-success">Single</span>' },
                { data: 'status_label', render: (data, type, row) => row.status === 'inactive' ? '<span class="badge badge-light-secondary">Nonaktif</span>' : '<span class="badge badge-light-success">Aktif</span>' },
                { data: 'category' },
                { data: 'display_address_label', render: (data) => data ? escapeHtml(data) : '-' },
                { data: 'main_address_label', render: (data) => data ? escapeHtml(data) : '-' },
                { data: 'description' },
                { data: 'bundle_summary', render: (data)=> data || '-' },
                { data: 'koli_qty', className:'text-end', render: (data, type, row)=> row.item_type === 'bundle' ? '-' : ((data === null || data === undefined || data === '') ? '-' : data) },
                { data: 'safety_stock', className:'text-end', render: (data, type, row)=> row.item_type === 'bundle' ? '-' : (data ?? 0) },
                { data: 'id', orderable:false, searchable:false, className:'text-end', render: (data, type, row)=>{
                    const detailItem = `<div class="menu-item px-3"><a href="${showTpl.replace(':id', data)}" class="menu-link px-3">Detail</a></div>`;
                    const qrItem = `<div class="menu-item px-3"><a href="#" class="menu-link px-3 btn-qr" data-id="${data}" data-sku="${row.sku}" data-name="${row.name}">QR Code</a></div>`;
                    const editItem = canUpdate ? `<div class="menu-item px-3"><a href="${editTpl.replace(':id', data)}" class="menu-link px-3">Edit</a></div>` : '';
                    const delItem = canDelete ? `<div class="menu-item px-3"><a href="#" class="menu-link px-3 text-danger btn-delete" data-id="${data}">Hapus</a></div>` : '';
                    const actions = `${detailItem}${qrItem}${editItem}${delItem}`;
                    if (!actions) return '';
                    return `
                        <div class="text-end">
                            <a href="#" class="btn btn-sm btn-light btn-active-light-primary" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                Actions
                                <span class="svg-icon svg-icon-5 m-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                        <path d="M11.4343 12.7344L7.25 8.55005C6.83579 8.13583 6.16421 8.13584 5.75 8.55005C5.33579 8.96426 5.33579 9.63583 5.75 10.05L11.2929 15.5929C11.6834 15.9835 12.3166 15.9835 12.7071 15.5929L18.25 10.05C18.6642 9.63584 18.6642 8.96426 18.25 8.55005C17.8358 8.13584 17.1642 8.13584 16.75 8.55005L12.5657 12.7344C12.2533 13.0468 11.7467 13.0468 11.4343 12.7344Z" fill="black"></path>
                                    </svg>
                                </span>
                            </a>
                            <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-600 menu-state-bg-light-primary fw-bold fs-7 w-175px py-3" data-kt-menu="true">
                                ${actions}
                            </div>
                        </div>
                    `;
                }}
            ]
        });
        refreshMenus();
        dt.on('draw', refreshMenus);

        const reloadTable = (keepPage = false) => dt.ajax.reload(null, keepPage ? false : true);

        searchInput?.addEventListener('keyup', reloadTable);
        document.getElementById('btn_export_items')?.addEventListener('click', () => {
            const params = new URLSearchParams();
            const q = searchInput?.value?.trim() || '';
            if (q) params.set('q', q);
            params.set('search_mode', window.resolveTableSearchMode ? window.resolveTableSearchMode(tableEl[0] || null) : 'contains');
            const categoryId = categoryFilter?.value || '';
            if (categoryId !== '') params.set('category_id', categoryId);
            params.set('status', document.getElementById('filter_item_status')?.value || 'active');
            window.location.href = `${itemsExportUrl}?${params.toString()}`;
        });
        applyBtn?.addEventListener('click', reloadTable);
        categoryFilter?.addEventListener('change', reloadTable);
        document.getElementById('filter_item_status')?.addEventListener('change', reloadTable);
        limitSelect?.addEventListener('change', () => {
            const val = Number(limitSelect.value || 10);
            dt.page.len(val).draw();
        });
        resetBtn?.addEventListener('click', () => {
            if (categoryFilter) {
                categoryFilter.value = '';
                if (typeof $ !== 'undefined' && $(categoryFilter).data('select2')) {
                    $(categoryFilter).val('').trigger('change.select2');
                }
            }
            const statusFilter = document.getElementById('filter_item_status');
            if (statusFilter) {
                statusFilter.value = 'active';
                if (typeof $ !== 'undefined' && $(statusFilter).data('select2')) {
                    $(statusFilter).val('active').trigger('change.select2');
                }
            }
            if (limitSelect) {
                limitSelect.value = '10';
                dt.page.len(10).draw();
            }
            reloadTable();
        });

        importBtn?.addEventListener('click', () => {
            if (importInput) importInput.value = '';
            if (importError) importError.textContent = '';
        });

        document.getElementById('btn_update_items_excel')?.addEventListener('click', () => {
            if (itemUpdateFile) itemUpdateFile.value = '';
            if (itemUpdateError) itemUpdateError.textContent = '';
            refreshItemUpdateFieldState();
        });

        itemUpdateFieldEls.forEach((input) => input.addEventListener('change', () => {
            if (itemUpdateError) itemUpdateError.textContent = '';
            refreshItemUpdateFieldState();
        }));

        document.getElementById('btn_select_all_item_update_fields')?.addEventListener('click', () => {
            itemUpdateFieldEls.forEach((input) => { input.checked = true; });
            refreshItemUpdateFieldState();
        });

        document.getElementById('btn_clear_item_update_fields')?.addEventListener('click', () => {
            itemUpdateFieldEls.forEach((input) => { input.checked = false; });
            refreshItemUpdateFieldState();
        });

        itemUpdateDownload?.addEventListener('click', () => {
            const fields = selectedItemUpdateFields();
            if (!fields.length) {
                if (itemUpdateError) itemUpdateError.textContent = 'Pilih minimal satu field terlebih dahulu.';
                return;
            }
            window.location.assign(itemUpdateUrlWithFields(itemUpdateTemplateUrl, fields));
        });

        itemUpdateSubmit?.addEventListener('click', async () => {
            if (itemUpdateError) itemUpdateError.textContent = '';
            const fields = selectedItemUpdateFields();
            const file = itemUpdateFile?.files?.[0];
            if (!fields.length) {
                if (itemUpdateError) itemUpdateError.textContent = 'Pilih minimal satu field yang akan diperbarui.';
                return;
            }
            if (!file) {
                if (itemUpdateError) itemUpdateError.textContent = 'Pilih file Excel hasil template terlebih dahulu.';
                return;
            }

            const confirmed = await confirmAction();
            if (!confirmed) return;

            const formData = new FormData();
            formData.append('file', file);
            fields.forEach((field) => formData.append('fields[]', field));

            try {
                const res = await fetch(itemUpdateImportUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: formData,
                });
                const text = await res.text();
                let json;
                try {
                    json = JSON.parse(text);
                } catch (error) {
                    console.error('Invalid JSON', text);
                    closeSwal();
                    notifyError('Respons server tidak valid.');
                    return;
                }

                closeSwal();
                if (!res.ok) {
                    const message = json?.errors
                        ? Object.values(json.errors).flat().join(', ')
                        : json?.message;
                    if (itemUpdateError) itemUpdateError.textContent = message || 'Gagal update data items.';
                    notifyError(message || 'Gagal update data items.');
                    return;
                }

                notifySuccess(`${json.message || 'Update selesai'} Diproses: ${json.processed}, berubah: ${json.updated}, tetap: ${json.unchanged}.`);
                if (itemUpdateFile) itemUpdateFile.value = '';
                itemUpdateModal?.hide();
                reloadTable();
            } catch (error) {
                console.error(error);
                closeSwal();
                notifyError('Gagal update data items.');
            }
        });

        refreshItemUpdateFieldState();

        document.getElementById('btn_import_barcodes')?.addEventListener('click', () => {
            if (barcodeImportInput) barcodeImportInput.value = '';
            if (barcodeImportError) barcodeImportError.textContent = '';
        });

        barcodeImportSubmit?.addEventListener('click', async () => {
            if (barcodeImportError) barcodeImportError.textContent = '';
            const file = barcodeImportInput?.files?.[0];
            if (!file) {
                if (barcodeImportError) barcodeImportError.textContent = 'Pilih file Excel terlebih dahulu.';
                return;
            }
            const confirmed = await confirmAction();
            if (!confirmed) return;
            const formData = new FormData();
            formData.append('file', file);
            try {
                const res = await fetch(barcodeImportUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: formData,
                });
                const text = await res.text();
                let json;
                try { json = JSON.parse(text); } catch (e) {
                    closeSwal();
                    notifyError('Respons server tidak valid');
                    return;
                }
                closeSwal();
                if (!res.ok) {
                    if (json?.errors) {
                        const msg = Object.values(json.errors).flat().join(', ');
                        notifyError(msg || 'Gagal import barcode alias');
                    } else {
                        notifyError(json.message || 'Gagal import barcode alias');
                    }
                    return;
                }
                notifySuccess(`${json.message || 'Import selesai'} (created: ${json.created}, updated: ${json.updated})`);
                if (barcodeImportInput) barcodeImportInput.value = '';
                barcodeImportModal?.hide();
                reloadTable(true);
            } catch (err) {
                console.error(err);
                closeSwal();
                notifyError('Gagal import barcode alias');
            }
        });

        const initBarcodeMissesTable = () => {
            if (barcodeMissesDt || !barcodeMissesTableEl.length || !$.fn.DataTable) return;
            barcodeMissesDt = barcodeMissesTableEl.DataTable({
                processing: true,
                serverSide: true,
                dom: 'rtip',
                order: [[4, 'desc']],
                pageLength: 10,
                ajax: {
                    url: barcodeMissesDataUrl,
                    dataSrc: 'data',
                    data: function(params) {
                        params.q = barcodeMissSearch?.value || '';
                        params.status = barcodeMissStatus?.value || 'open';
                    }
                },
                columns: [
                    { data: 'scan_code' },
                    { data: 'context' },
                    { data: 'source_code' },
                    { data: 'scan_count', className: 'text-end' },
                    { data: 'last_scanned_at' },
                    { data: 'resolved_item' },
                    { data: 'id', orderable: false, searchable: false, className: 'text-end', render: (data, type, row) => {
                        if (row.is_resolved || !canUpdate) return '';
                        return `
                            <div class="d-flex gap-2 justify-content-end align-items-center">
                                <select class="form-select form-select-sm form-select-solid w-250px barcode-miss-item">
                                    <option value="">Pilih item</option>
                                    ${componentItemOptionsHtml}
                                </select>
                                <button type="button" class="btn btn-sm btn-primary btn-resolve-barcode-miss" data-id="${data}">Hubungkan</button>
                            </div>
                        `;
                    }},
                ],
            });
            barcodeMissesDt.on('draw', refreshMenus);
        };

        document.getElementById('btn_open_barcode_misses')?.addEventListener('click', () => {
            initBarcodeMissesTable();
            barcodeMissesDt?.ajax.reload();
        });
        barcodeMissSearch?.addEventListener('keyup', () => barcodeMissesDt?.ajax.reload());
        barcodeMissStatus?.addEventListener('change', () => barcodeMissesDt?.ajax.reload());

        barcodeMissesTableEl.on('click', '.btn-resolve-barcode-miss', async function(e) {
            e.preventDefault();
            const id = this.getAttribute('data-id');
            const row = this.closest('tr');
            const select = row?.querySelector('.barcode-miss-item');
            const itemId = select?.value || '';
            if (!itemId) {
                notifyError('Pilih item tujuan terlebih dahulu.');
                return;
            }
            const confirmed = await confirmAction();
            if (!confirmed) return;
            try {
                const res = await fetch(barcodeMissResolveTpl.replace(':id', id), {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({ item_id: itemId }),
                });
                const text = await res.text();
                let json;
                try { json = JSON.parse(text); } catch (e) {
                    closeSwal();
                    notifyError('Respons server tidak valid');
                    return;
                }
                closeSwal();
                if (!res.ok) {
                    if (json?.errors) {
                        notifyError(Object.values(json.errors).flat().join(', '));
                    } else {
                        notifyError(json.message || 'Gagal menghubungkan barcode');
                    }
                    return;
                }
                notifySuccess(json.message || 'Barcode berhasil dihubungkan.');
                barcodeMissesDt?.ajax.reload(null, false);
                reloadTable(true);
            } catch (err) {
                console.error(err);
                closeSwal();
                notifyError('Gagal menghubungkan barcode');
            }
        });

        document.getElementById('btn_import_bundle')?.addEventListener('click', () => {
            if (bundleImportInput) bundleImportInput.value = '';
            if (bundleImportError) bundleImportError.textContent = '';
        });

        bundleImportSubmit?.addEventListener('click', async () => {
            if (bundleImportError) bundleImportError.textContent = '';
            const file = bundleImportInput?.files?.[0];
            if (!file) {
                if (bundleImportError) bundleImportError.textContent = 'Pilih file Excel terlebih dahulu.';
                return;
            }
            const confirmed = await confirmAction();
            if (!confirmed) return;
            const formData = new FormData();
            formData.append('file', file);
            try {
                const res = await fetch(bundleImportUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: formData,
                });
                const text = await res.text();
                let json;
                try { json = JSON.parse(text); } catch (e) {
                    closeSwal();
                    notifyError('Respons server tidak valid');
                    return;
                }
                closeSwal();
                if (!res.ok) {
                    if (json?.errors) {
                        const msg = Object.values(json.errors).flat().join(', ');
                        notifyError(msg || 'Gagal import bundle');
                    } else {
                        notifyError(json.message || 'Gagal import bundle');
                    }
                    return;
                }
                notifySuccess(`${json.message || 'Import selesai'} (${json.processed} bundle diproses)`);
                if (bundleImportInput) bundleImportInput.value = '';
                bundleImportModal?.hide();
                reloadTable();
            } catch (err) {
                console.error(err);
                closeSwal();
                notifyError('Gagal import bundle');
            }
        });

        importSubmit?.addEventListener('click', async ()=> {
            if (importError) importError.textContent = '';
            const file = importInput?.files?.[0];
                if (!file) {
                    if (importError) importError.textContent = 'Pilih file Excel terlebih dahulu.';
                    return;
                }
            const confirmed = await confirmAction();
            if (!confirmed) {
                return;
            }
            const formData = new FormData();
            formData.append('file', file);
            try {
                const res = await fetch(importUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: formData,
                });
                const text = await res.text();
                let json;
                try { json = JSON.parse(text); } catch (e) {
                    console.error('Invalid JSON', text);
                    closeSwal();
                    notifyError('Respons server tidak valid');
                    return;
                }
                closeSwal();
                if (!res.ok) {
                    if (json?.errors) {
                        const msg = Object.values(json.errors).flat().join(', ');
                        notifyError(msg || 'Gagal import');
                    } else {
                        notifyError(json.message || 'Gagal import');
                    }
                    return;
                }
                notifySuccess(`${json.message || 'Import selesai'} (created: ${json.created}, updated: ${json.updated})`);
                if (importInput) importInput.value = '';
                importModal?.hide();
                reloadTable();
            } catch (err) {
                console.error(err);
                closeSwal();
                notifyError('Gagal import');
            }
        });

        tableEl.on('click', '.btn-qr', function(e) {
            e.preventDefault();
            const id = this.getAttribute('data-id');
            const sku = this.getAttribute('data-sku') || '-';
            const name = this.getAttribute('data-name') || '';
            if (!id || !qrImageEl || !qrDownloadEl) return;

            const previewUrl = qrUrl(id);
            const downloadUrl = qrUrl(id, true);

            if (qrLabelEl) {
                qrLabelEl.textContent = `${sku}${name ? ' - ' + name : ''}`;
            }

            qrImageEl.src = `${previewUrl}${previewUrl.includes('?') ? '&' : '?'}v=${Date.now()}`;
            qrDownloadEl.href = downloadUrl;
            qrDownloadEl.setAttribute('download', qrFilename(sku));
            qrModal?.show();
        });

        tableEl.on('click', '.btn-delete', async function(e) {
            e.preventDefault();
            const id = this.getAttribute('data-id');
            let confirmed = true;
            if (typeof Swal !== 'undefined') {
                const res = await Swal.fire({
                    title: 'Apakah Anda yakin?',
                    text: 'Item akan dihapus',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Hapus',
                    cancelButtonText: 'Batal',
                    buttonsStyling: false,
                    customClass: {
                        confirmButton: 'btn btn-danger',
                        cancelButton: 'btn btn-light'
                    }
                });
                confirmed = res.isConfirmed;
            }
            if (!confirmed) return;
            try {
                const res = await fetch(deleteTpl.replace(':id', id), {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'Accept': 'application/json',
                    },
                    body: new URLSearchParams({ _method: 'DELETE' }),
                });
                const text = await res.text();
                let json;
                try { json = JSON.parse(text); } catch (parseErr) {
                    console.error('Invalid JSON', text);
                    notifyError('Respons server tidak valid');
                    return;
                }
                if (!res.ok) {
                    notifyError(json.message || 'Gagal menghapus item');
                    return;
                }
                notifySuccess(json.message || 'Berhasil');
                reloadTable();
            } catch (err) {
                console.error(err);
                notifyError('Gagal menghapus item');
            }
        });

    });
</script>
@endpush

@include('layouts.partials.form-submit-confirmation')
