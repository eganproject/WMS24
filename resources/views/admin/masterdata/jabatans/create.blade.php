@extends('layouts.app')
@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Jabatans',
        'breadcrumbs' => ['Admin', 'Masterdata', 'Jabatans', 'Tambah Jabatan'],
    ])
@endpush
@section('content')
    <div class="content flex-row-fluid" id="kt_content">

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Tambah Jabatan</h3>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.masterdata.jabatans.store') }}" method="POST">
                    @csrf
                    <div class="fv-row mb-3 ">
                        <label for="name" class="form-label required">Name</label>
                        <input type="text" class="form-control form-control-solid" id="name" name="name"
                            required>
                    </div>
                    <div class="fv-row mb-3 ">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control form-control-solid" id="description" name="description"></textarea>
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
