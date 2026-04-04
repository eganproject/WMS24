@extends('layouts.app')
@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Menus',
        'breadcrumbs' => ['Admin', 'Menus', 'Ubah Menu'],
    ])
@endpush
@section('content')
    <div class="content flex-row-fluid" id="kt_content">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Ubah Menu: {{ $menu->name }}</h3>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.masterdata.menus.update', $menu->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="fv-row mb-3">
                        <label for="name" class="form-label required">Nama Menu</label>
                        <input type="text" class="form-control form-control-solid" id="name" name="name"
                            value="{{ old('name', $menu->name) }}" required>
                    </div>
                    <div class="fv-row mb-3">
                        <label for="url" class="form-label">URL</label>
                        <input type="text" class="form-control form-control-solid" id="url" name="url"
                            value="{{ old('url', $menu->url) }}">
                    </div>
                    <div class="fv-row mb-3">
                        <label for="icon" class="form-label">Ikon</label>
                        <input type="text" class="form-control form-control-solid" id="icon" name="icon"
                            value="{{ old('icon', $menu->icon) }}">
                    </div>
                    <div class="fv-row mb-3">
                        <label for="parent_id" class="form-label">Menu Induk</label>
                        <select class="form-select form-select-solid fw-bolder select2-hidden-accessible" id="parent_id"
                            name="parent_id" data-control="select2" data-placeholder="Pilih Menu Induk">
                            <option value="">Pilih Menu Induk</option>
                            @foreach ($parentMenus as $parentMenu)
                                <option value="{{ $parentMenu->id }}"
                                    {{ old('parent_id', $menu->parent_id) == $parentMenu->id ? 'selected' : '' }}>
                                    {{ $parentMenu->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="fv-row mb-3">
                        <label for="order" class="form-label required">Urutan</label>
                        <input type="number" class="form-control form-control-solid" id="order" name="order"
                            value="{{ old('order', $menu->order) }}" required>
                    </div>
                    <div class="fv-row mb-3 form-check form-check-custom form-check-solid">
                        <input class="form-check-input" type="checkbox" value="1" id="is_active" name="is_active"
                            {{ old('is_active', $menu->is_active) ? 'checked' : '' }} />
                        <label class="form-check-label" for="is_active">
                            Aktif
                        </label>
                    </div>
                    <button type="submit" class="btn btn-primary mt-3">Perbarui</button>
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
