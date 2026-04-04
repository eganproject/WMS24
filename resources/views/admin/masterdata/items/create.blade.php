@extends('layouts.app')
@push('styles')
    <link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
@endpush
@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Items',
        'breadcrumbs' => ['Admin', 'Masterdata', 'Items', 'Tambah Item'],
    ])
@endpush
@section('content')
    <div class="content flex-row-fluid" id="kt_content">

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Tambah Item</h3>
            </div>
            <div class="card-body">
                <form id="item-form" action="{{ route('admin.masterdata.items.store') }}" method="POST">
                    @csrf
                    <div class="row">
                        <div class="col-md-6">
                            <div class="fv-row mb-7">
                                <label for="product_code" class="form-label required">Product Code</label>
                                <input type="text" class="form-control form-control-solid" id="product_code" name="product_code"
                                    value="{{ old('product_code', $generatedProductCode) }}" readonly required>
                                @error('product_code')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="fv-row mb-7">
                                <label for="item_category_id" class="form-label required">Kategori Item</label>
                                <select class="form-select form-select-solid fw-bolder select2-hidden-accessible"
                                    id="item_category_id" name="item_category_id" data-control="select2"
                                    data-placeholder="Pilih Kategori Item">
                                    <option></option>
                                    @foreach ($itemcategories as $category)
                                        <option value="{{ $category->id }}"
                                            {{ old('item_category_id') == $category->id ? 'selected' : '' }}>
                                            {{ $category->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('item_category_id')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="fv-row mb-7">
                                <label for="sku" class="form-label required">SKU</label>
                                <input type="text" class="form-control form-control-solid" id="sku" name="sku"
                                    value="{{ old('sku') }}" required oninput="this.value = this.value.toUpperCase().replace(/\s/g, '');">
                                <div id="sku-feedback" class="mt-2"></div>
                                @error('sku')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="fv-row mb-7">
                                <label for="nama_barang" class="form-label required">Nama Barang</label>
                                <input type="text" class="form-control form-control-solid" id="nama_barang" name="nama_barang"
                                    value="{{ old('nama_barang') }}" required>
                                @error('nama_barang')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="fv-row mb-7">
                                <label for="uom_id" class="form-label required">UOM</label>
                                <select class="form-select form-select-solid fw-bolder select2-hidden-accessible" id="uom_id"
                                    name="uom_id" data-control="select2" data-placeholder="Pilih UOM">
                                    <option></option>
                                    @foreach ($uoms as $uom)
                                        <option value="{{ $uom->id }}" {{ old('uom_id') == $uom->id ? 'selected' : '' }}>
                                            {{ $uom->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('uom_id')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="fv-row mb-7">
                                <label for="koli" class="form-label required">Koli</label>
                                <input type="number" class="form-control form-control-solid" value="{{ old('koli') }}" id="koli" name="koli"
                                    required>
                            </div>
                        </div>
                    </div>

                    <div class="fv-row mb-7">
                        <label for="deskripsi" class="form-label">Deskripsi</label>
                        <textarea class="form-control form-control-solid" id="deskripsi" name="deskripsi" rows="3">{{ old('deskripsi') }}</textarea>
                        @error('deskripsi')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary me-2" id="submit-btn">Tambah Item</button>
                        <a href="{{ route('admin.masterdata.items.index') }}" class="btn btn-light">Kembali</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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

            var skuInput = $('#sku');
            var skuFeedback = $('#sku-feedback');
            var submitButton = $('#submit-btn'); // Changed to use ID

            function checkSkuUniqueness() {
                var sku = skuInput.val();
                if (sku.length === 0) {
                    skuFeedback.html('').removeClass('text-success text-danger');
                    submitButton.prop('disabled', false);
                    return;
                }

                $.ajax({
                    url: "{{ route('admin.masterdata.items.checkSkuUniqueness') }}",
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content'),
                        sku: sku
                    },
                    success: function(response) {
                        if (response.isUnique) {
                            skuFeedback.html('SKU tersedia.').removeClass('text-danger').addClass(
                                'text-success');
                            submitButton.prop('disabled', false);
                        } else {
                            skuFeedback.html('SKU sudah digunakan.').removeClass('text-success')
                                .addClass('text-danger');
                            submitButton.prop('disabled', true);
                        }
                    },
                    error: function() {
                        skuFeedback.html('Terjadi kesalahan saat memeriksa SKU.').removeClass(
                            'text-success').addClass('text-danger');
                        submitButton.prop('disabled', true);
                    }
                });
            }

            skuInput.on('keyup', checkSkuUniqueness);

            // Initial check if there's an old value
            checkSkuUniqueness();

            // SweetAlert confirmation for form submission
            $('#item-form').on('submit', function(e) { // Assuming the form has id="item-form"
                e.preventDefault(); // Prevent default form submission

                const form = this;

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
                        form.submit(); // Submit the form if confirmed
                    }
                });
            });
        });
    </script>
@endpush
