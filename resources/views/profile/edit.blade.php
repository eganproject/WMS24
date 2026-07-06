<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            My Profile
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @php
                $employeePosition = $employee?->positionRelation?->name ?? $employee?->position ?? '-';
                $employeeArea = $employee?->area?->name ?? $employee?->area?->code ?? '-';
                $userArea = $user->area?->name ?? $user->area?->code ?? '-';
                $roleNames = $user->roles->pluck('name')->filter()->values();
            @endphp

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-6 sm:p-8 border-b border-gray-100">
                    <div class="flex flex-col sm:flex-row sm:items-center gap-5">
                        <img
                            src="{{ $user->avatar_url }}"
                            alt="Avatar {{ $user->name }}"
                            class="h-20 w-20 rounded-full object-cover border border-gray-200"
                        >
                        <div class="flex-1 min-w-0">
                            <div class="text-2xl font-semibold text-gray-900">{{ $user->name }}</div>
                            <div class="mt-1 text-sm text-gray-500">{{ $user->email }}</div>
                            <div class="mt-3 flex flex-wrap gap-2">
                                @forelse($roleNames as $roleName)
                                    <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700">
                                        {{ $roleName }}
                                    </span>
                                @empty
                                    <span class="inline-flex items-center rounded-full bg-yellow-50 px-3 py-1 text-xs font-medium text-yellow-700">
                                        Role belum diatur
                                    </span>
                                @endforelse

                                @if($employee)
                                    <span class="inline-flex items-center rounded-full bg-green-50 px-3 py-1 text-xs font-medium text-green-700">
                                        Terhubung ke karyawan
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-red-50 px-3 py-1 text-xs font-medium text-red-700">
                                        Belum terhubung ke karyawan
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid gap-0 lg:grid-cols-2">
                    <div class="p-6 sm:p-8 border-b lg:border-b-0 lg:border-r border-gray-100">
                        <h3 class="text-base font-semibold text-gray-900">Informasi Akun</h3>
                        <dl class="mt-5 grid grid-cols-1 gap-4 text-sm">
                            <div>
                                <dt class="text-gray-500">Nama Login</dt>
                                <dd class="mt-1 font-medium text-gray-900">{{ $user->name }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500">Email</dt>
                                <dd class="mt-1 font-medium text-gray-900">{{ $user->email }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500">Area User</dt>
                                <dd class="mt-1 font-medium text-gray-900">{{ $userArea }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="p-6 sm:p-8">
                        <h3 class="text-base font-semibold text-gray-900">Informasi Karyawan</h3>

                        @if($employee)
                            <dl class="mt-5 grid grid-cols-1 gap-4 text-sm">
                                <div>
                                    <dt class="text-gray-500">Kode Karyawan</dt>
                                    <dd class="mt-1 font-medium text-gray-900">{{ $employee->employee_code ?? '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-gray-500">Nama Karyawan</dt>
                                    <dd class="mt-1 font-medium text-gray-900">{{ $employee->name }}</dd>
                                </div>
                                <div>
                                    <dt class="text-gray-500">Jabatan</dt>
                                    <dd class="mt-1 font-medium text-gray-900">{{ $employeePosition }}</dd>
                                </div>
                                <div>
                                    <dt class="text-gray-500">Area Karyawan</dt>
                                    <dd class="mt-1 font-medium text-gray-900">{{ $employeeArea }}</dd>
                                </div>
                                <div>
                                    <dt class="text-gray-500">Status</dt>
                                    <dd class="mt-1 font-medium text-gray-900">{{ ucfirst($employee->employment_status ?? '-') }}</dd>
                                </div>
                            </dl>

                            <div class="mt-6 flex flex-col sm:flex-row gap-3">
                                <a href="{{ route('employee.attendance-performance') }}" class="inline-flex justify-center rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-800">
                                    Performa Absensi
                                </a>
                                <a href="{{ route('employee.leave-requests.index') }}" class="inline-flex justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50">
                                    Ajukan Cuti/Izin
                                </a>
                            </div>
                        @else
                            <div class="mt-5 rounded-lg border border-yellow-200 bg-yellow-50 p-4 text-sm text-yellow-800">
                                Akun ini belum terhubung ke master karyawan. Fitur Performa Absensi dan Ajukan Cuti/Izin hanya bisa digunakan setelah user dikaitkan dengan data karyawan.
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                    <div class="max-w-xl">
                        @include('profile.partials.update-profile-information-form')
                    </div>
                </div>

                <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                    <div class="max-w-xl">
                        @include('profile.partials.update-password-form')
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
