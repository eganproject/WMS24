@extends('layouts.app')

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Edit Assembly Recipe',
        'breadcrumbs' => ['Admin', 'Masterdata', 'Assembly Recipes', 'Edit'],
    ])
@endpush

@section('content')
    <div class="content flex-row-fluid" id="kt_content">
        <div class="card">
            <div class="card-body p-6">
                <form id="assembly-recipe-form" action="{{ route('admin.masterdata.assemblyrecipes.update', $recipe->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="row g-5">
                        <div class="col-md-6">
                            <div class="mb-5">
                                <label class="form-label">Kode</label>
                                <input type="text" name="code" class="form-control form-control-solid" value="{{ old('code', $recipe->code) }}" readonly>
                            </div>
                            <div class="mb-5">
                                <label class="form-label">Deskripsi</label>
                                <textarea name="description" class="form-control form-control-solid" rows="3" placeholder="Keterangan resep">{{ old('description', $recipe->description) }}</textarea>
                            </div>
                            <div class="mb-5">
                                <label class="form-label required">Produk Jadi</label>
                                <select name="finished_item_id" class="form-select form-select-solid" data-control="select2" data-placeholder="Pilih Item" required>
                                    <option value="">Pilih Item</option>
                                    @foreach($items as $item)
                                        <option value="{{ $item->id }}" {{ old('finished_item_id', $recipe->finished_item_id) == $item->id ? 'selected' : '' }}>{{ $item->nama_barang }} ({{ $item->sku }})</option>
                                    @endforeach
                                </select>
                                @error('finished_item_id')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="mb-5">
                                <label class="form-label required">Output Quantity</label>
                                <input type="number" step="0.01" name="output_quantity" class="form-control form-control-solid" value="{{ old('output_quantity', $recipe->output_quantity) }}" required>
                            </div>
                            <div class="mb-5 form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" {{ old('is_active', $recipe->is_active) ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_active">Aktif</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Komponen</label>
                            <div id="components">
                                @if(old('components.item_id'))
                                    @php
                                        $oldItemIds = old('components.item_id', []);
                                        $oldQtys = old('components.quantity', []);
                                    @endphp
                                    @foreach($oldItemIds as $idx => $oi)
                                        <div class="d-flex gap-3 mb-3 component-row">
                                            <select name="components[item_id][]" class="form-select form-select-solid" data-control="select2" data-placeholder="Pilih Item" required>
                                                <option value="">Pilih Item</option>
                                                @foreach($items as $item)
                                                    <option value="{{ $item->id }}" {{ $oi == $item->id ? 'selected' : '' }}>{{ $item->nama_barang }} ({{ $item->sku }})</option>
                                                @endforeach
                                            </select>
                                            <input type="number" step="0.01" name="components[quantity][]" class="form-control form-control-solid" placeholder="Qty" value="{{ $oldQtys[$idx] ?? '' }}" required>
                                            <button type="button" class="btn btn-light-danger remove-row">Hapus</button>
                                        </div>
                                    @endforeach
                                @else
                                    @foreach($recipe->items as $ri)
                                        <div class="d-flex gap-3 mb-3 component-row">
                                            <select name="components[item_id][]" class="form-select form-select-solid" required>
                                                <option value="">Pilih Item</option>
                                                @foreach($items as $item)
                                                    <option value="{{ $item->id }}" {{ $ri->item_id == $item->id ? 'selected' : '' }}>{{ $item->nama_barang }} ({{ $item->sku }})</option>
                                                @endforeach
                                            </select>
                                            <input type="number" step="0.01" name="components[quantity][]" class="form-control form-control-solid" placeholder="Qty" value="{{ $ri->quantity }}" required>
                                            <button type="button" class="btn btn-light-danger remove-row">Hapus</button>
                                        </div>
                                    @endforeach
                                @endif
                                @error('components')
                                    <div><span class="text-danger">{{ $message }}</span></div>
                                @enderror
                            </div>
                            <template id="component-row-template">
                                <div class="d-flex gap-3 mb-3 component-row">
                                    <select name="components[item_id][]" class="form-select form-select-solid" data-placeholder="Pilih Item" required>
                                        <option value="">Pilih Item</option>
                                        @foreach($items as $item)
                                            <option value="{{ $item->id }}">{{ $item->nama_barang }} ({{ $item->sku }})</option>
                                        @endforeach
                                    </select>
                                    <input type="number" step="0.01" name="components[quantity][]" class="form-control form-control-solid" placeholder="Qty" required>
                                    <button type="button" class="btn btn-light-danger remove-row">Hapus</button>
                                </div>
                            </template>
                            <button type="button" class="btn btn-light-primary" id="add_row">Tambah Komponen</button>
                        </div>
                    </div>

                    <div class="mt-6">
                        <button type="submit" class="btn btn-primary">Simpan</button>
                        <a href="{{ route('admin.masterdata.assemblyrecipes.index') }}" class="btn btn-light">Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function(){
            const btnAdd = document.getElementById('add_row');
            const container = document.getElementById('components');
            const tpl = document.getElementById('component-row-template');
            btnAdd.addEventListener('click', function(){
                const proto = document.createElement('div');
                const frag = tpl.content.cloneNode(true);
                container.appendChild(frag);
                const rows = container.querySelectorAll('.component-row');
                const last = rows[rows.length - 1];
                initSelect2In(last);
            });
            container.addEventListener('click', function(e){
                if(e.target.classList.contains('remove-row')){
                    const rows = container.querySelectorAll('.component-row');
                    if(rows.length > 1){ e.target.closest('.component-row').remove(); }
                }
            });

            function initSelect2In(el){
                const $sel = $(el).find('select[name="components[item_id][]"]');
                if ($sel.length && !$sel.hasClass('select2-hidden-accessible')) {
                    $sel.select2({ width: '100%', dropdownParent: $('#kt_content') });
                }
            }
            // init existing rows
            container.querySelectorAll('.component-row').forEach(function(row){ initSelect2In(row); });

            // SweetAlert confirmation on submit + basic validation
            const form = document.getElementById('assembly-recipe-form');
            form.addEventListener('submit', function(ev){
                ev.preventDefault();
                const submitForm = this;
                // basic validation
                const code = $('input[name="code"]').val().trim();
                const finished = $('select[name="finished_item_id"]').val();
                const output = parseFloat($('input[name="output_quantity"]').val());
                let validComponents = true;
                $('#components .component-row').each(function(){
                    const item = $(this).find('select[name="components[item_id][]"]').val();
                    const qty = parseFloat($(this).find('input[name="components[quantity][]"]').val());
                    if(!item || !qty || qty <= 0){ validComponents = false; }
                });
                if(!code || !finished || !output || output <= 0 || !validComponents){
                    Swal.fire({ icon:'error', title:'Validasi Gagal', text:'Lengkapi data wajib dan pastikan qty > 0.' });
                    return;
                }
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
                        submitForm.submit();
                    }
                });
            });
        });
    </script>
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            toastr.options = {
                "closeButton": true,
                "debug": false,
                "newestOnTop": false,
                "progressBar": true,
                "positionClass": "toast-top-center",
                "preventDuplicates": false,
                "showDuration": "300",
                "hideDuration": "1000",
                "timeOut": "5000",
                "extendedTimeOut": "1000",
                "showEasing": "swing",
                "hideEasing": "linear",
                "showMethod": "fadeIn",
                "hideMethod": "fadeOut"
            };

            @if (Session::has('success'))
                toastr.success("{{ session('success') }}");
            @endif

            @if (Session::has('error'))
                toastr.error("{{ session('error') }}");
            @endif
            @if ($errors->any())
                var laravelErrors = @json($errors->all());
                laravelErrors.forEach(function(msg){ toastr.error(msg); });
            @endif
        });
    </script>
@endpush
