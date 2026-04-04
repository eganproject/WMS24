@extends('layouts.app')

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => '403 - Unauthorized',
        'breadcrumbs' => ['Error', '403'],
    ])
@endpush

@section('content')
    <div class="content flex-row-fluid" id="kt_content">
        <div class="d-flex flex-column flex-center flex-column-fluid py-10">
            <div class="card shadow-sm w-100 w-lg-600px">
                <div class="card-body p-10 p-lg-15 text-center">
                    <span class="svg-icon svg-icon-5tx svg-icon-primary mb-5">
                        <i class="fas fa-lock"></i>
                    </span>
                    <h1 class="fw-bolder text-gray-900 mb-3">Akses Ditolak</h1>
                    <p class="fs-5 fw-semibold text-gray-600 mb-10">
                        Maaf, Anda tidak memiliki izin untuk melakukan aksi ini. Silakan hubungi administrator jika Anda merasa ini sebuah kesalahan.
                    </p>
                    @php($backUrl = url()->previous() ?? route('admin.dashboard'))
                    <a href="{{ $backUrl }}" class="btn btn-primary">
                        <i class="fas fa-arrow-left me-2"></i> Kembali
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection
