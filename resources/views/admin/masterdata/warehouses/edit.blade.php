@extends('layouts.app')
@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Warehouses',
        'breadcrumbs' => ['Admin', 'Masterdata', 'Warehouses', 'Ubah Warehouse'],
    ])
@endpush
@section('content')
    <div class="content flex-row-fluid" id="kt_content">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Edit Warehouse</h3>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.masterdata.warehouses.update', $warehouse->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="mb-3">
                        <label for="name" class="form-label required">Nama Warehouse</label>
                        <input type="text" class="form-control form-control-solid" id="name" name="name" value="{{ $warehouse->name }}"
                            required>
                    </div>
                    <div class="mb-3">
                        <label for="address" class="form-label">Alamat</label>
                        <textarea class="form-control form-control-solid" id="address" name="address" rows="3">{{ $warehouse->address }}</textarea>
                    </div>
                    <button type="submit" class="btn btn-primary mt-3">Submit</button>
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
