<section>
    <div class="mb-6">
        <h3 class="fw-bolder text-dark mb-1">Ubah Password</h3>
        <div class="text-muted">Gunakan password yang kuat agar akun tetap aman.</div>
    </div>

    <form method="post" action="{{ route('password.update') }}">
        @csrf
        @method('put')

        <div class="mb-5">
            <label for="update_password_current_password" class="form-label">Password Saat Ini</label>
            <input
                id="update_password_current_password"
                name="current_password"
                type="password"
                class="form-control form-control-solid @error('current_password', 'updatePassword') is-invalid @enderror"
                autocomplete="current-password"
            >
            @error('current_password', 'updatePassword')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-5">
            <label for="update_password_password" class="form-label">Password Baru</label>
            <input
                id="update_password_password"
                name="password"
                type="password"
                class="form-control form-control-solid @error('password', 'updatePassword') is-invalid @enderror"
                autocomplete="new-password"
            >
            @error('password', 'updatePassword')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-5">
            <label for="update_password_password_confirmation" class="form-label">Konfirmasi Password</label>
            <input
                id="update_password_password_confirmation"
                name="password_confirmation"
                type="password"
                class="form-control form-control-solid @error('password_confirmation', 'updatePassword') is-invalid @enderror"
                autocomplete="new-password"
            >
            @error('password_confirmation', 'updatePassword')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="d-flex align-items-center gap-4">
            <button type="submit" class="btn btn-primary">Simpan</button>

            @if (session('status') === 'password-updated')
                <span class="text-muted">Tersimpan.</span>
            @endif
        </div>
    </form>
</section>
