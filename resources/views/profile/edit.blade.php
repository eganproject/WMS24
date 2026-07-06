@extends('layouts.admin')

@section('title', 'My Profile')

@section('content')
@php
    $employeePosition = $employee?->positionRelation?->name ?? $employee?->position ?? '-';
    $employeeArea = $employee?->area?->name ?? $employee?->area?->code ?? '-';
    $userArea = $user->area?->name ?? $user->area?->code ?? '-';
    $roleNames = $user->roles->pluck('name')->filter()->values();
@endphp

<div class="card mb-6">
    <div class="card-body p-8">
        <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center gap-6">
            <div class="symbol symbol-90px">
                <img src="{{ $user->avatar_url }}" alt="Avatar {{ $user->name }}">
            </div>
            <div class="flex-grow-1">
                <div class="d-flex flex-column flex-lg-row align-items-start align-items-lg-center justify-content-between gap-4">
                    <div>
                        <h1 class="fs-2 fw-bolder text-dark mb-1">{{ $user->name }}</h1>
                        <div class="text-muted fw-semibold">{{ $user->email }}</div>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        @forelse($roleNames as $roleName)
                            <span class="badge badge-light-primary">{{ $roleName }}</span>
                        @empty
                            <span class="badge badge-light-warning">Role belum diatur</span>
                        @endforelse

                        @if($employee)
                            <span class="badge badge-light-success">Terhubung ke karyawan</span>
                        @else
                            <span class="badge badge-light-danger">Belum terhubung ke karyawan</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-6 mb-6">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <div class="card-title">
                    <h3 class="fw-bolder mb-0">Informasi Akun</h3>
                </div>
            </div>
            <div class="card-body">
                <div class="mb-5">
                    <div class="text-muted fs-7 mb-1">Nama Login</div>
                    <div class="fw-bold text-dark">{{ $user->name }}</div>
                </div>
                <div class="mb-5">
                    <div class="text-muted fs-7 mb-1">Email</div>
                    <div class="fw-bold text-dark">{{ $user->email }}</div>
                </div>
                <div>
                    <div class="text-muted fs-7 mb-1">Area User</div>
                    <div class="fw-bold text-dark">{{ $userArea }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <div class="card-title">
                    <h3 class="fw-bolder mb-0">Informasi Karyawan</h3>
                </div>
            </div>
            <div class="card-body">
                @if($employee)
                    <div class="row g-4">
                        <div class="col-sm-6">
                            <div class="text-muted fs-7 mb-1">Kode Karyawan</div>
                            <div class="fw-bold text-dark">{{ $employee->employee_code ?? '-' }}</div>
                        </div>
                        <div class="col-sm-6">
                            <div class="text-muted fs-7 mb-1">Nama Karyawan</div>
                            <div class="fw-bold text-dark">{{ $employee->name }}</div>
                        </div>
                        <div class="col-sm-6">
                            <div class="text-muted fs-7 mb-1">Jabatan</div>
                            <div class="fw-bold text-dark">{{ $employeePosition }}</div>
                        </div>
                        <div class="col-sm-6">
                            <div class="text-muted fs-7 mb-1">Area Karyawan</div>
                            <div class="fw-bold text-dark">{{ $employeeArea }}</div>
                        </div>
                        <div class="col-sm-6">
                            <div class="text-muted fs-7 mb-1">Status</div>
                            <div class="fw-bold text-dark">{{ ucfirst($employee->employment_status ?? '-') }}</div>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-3 mt-8">
                        <a href="{{ route('employee.attendance-performance') }}" class="btn btn-primary">
                            Performa Absensi
                        </a>
                        <a href="{{ route('employee.leave-requests.index') }}" class="btn btn-light-primary">
                            Ajukan Cuti/Izin
                        </a>
                    </div>
                @else
                    <div class="alert alert-warning mb-0">
                        Akun ini belum terhubung ke master karyawan. Fitur Performa Absensi dan Ajukan Cuti/Izin hanya bisa digunakan setelah user dikaitkan dengan data karyawan.
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="row g-6">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-body p-8">
                @include('profile.partials.update-profile-information-form')
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-body p-8">
                @include('profile.partials.update-password-form')
            </div>
        </div>
    </div>
</div>
@endsection
