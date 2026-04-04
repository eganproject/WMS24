@extends('layouts.app')
@push('styles')
    <link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
@endpush
@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Users',
        'breadcrumbs' => ['Admin', 'Masterdata', 'Users', 'Ubah Users'],
    ])
@endpush
@section('content')
    <div class="content flex-row-fluid" id="kt_content">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Edit User</h3>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.masterdata.users.update', $user->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="mb-3">
                        <label for="name" class="form-label required">Name</label>
                        <input type="text" class="form-control form-control-solid" id="name" name="name" value="{{ $user->name }}"
                            required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label required">Email</label>
                        <input type="email" class="form-control form-control-solid" id="email" name="email" value="{{ $user->email }}"
                            required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password (Biarkan kosong jika tidak ingin mengganti password)</label>
                        <input type="password" class="form-control form-control-solid" id="password" name="password">
                    </div>
                    <div class="mb-3">
                        <label for="jabatan_id" class="form-label required">Jabatan</label>
                        <select class="form-select form-select-solid fw-bolder select2-hidden-accessible" id="jabatan_id" name="jabatan_id" data-control="select2" data-placeholder="Pilih opsi">
                            @foreach ($jabatans as $jabatan)
                                <option value="{{ $jabatan->id }}"
                                    {{ $user->jabatan_id == $jabatan->id ? 'selected' : '' }}>{{ $jabatan->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="warehouse_id" class="form-label">Gudang</label>
                        <select class="form-select form-select-solid fw-bolder select2-hidden-accessible" id="warehouse_id" name="warehouse_id" data-control="select2" data-placeholder="Pilih Gudang (opsional)">
                            <option value="">Semua / Tidak terikat</option>
                            @foreach ($warehouses as $w)
                                <option value="{{ $w->id }}" {{ old('warehouse_id', $user->warehouse_id) == $w->id ? 'selected' : '' }}>{{ $w->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="d-flex justify-content-end mt-3">
                        <a href="{{ route('admin.masterdata.users.index') }}" class="btn btn-light me-3">Kembali</a>
                        <button type="submit" class="btn btn-primary">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
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
        });
    </script>
@endpush
