<section>
    <div class="mb-6">
        <h3 class="fw-bolder text-dark mb-1">Informasi Akun</h3>
        <div class="text-muted">Perbarui nama login dan email akun Anda.</div>
    </div>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}">
        @csrf
        @method('patch')

        <div class="mb-5">
            <label for="name" class="form-label required">Nama Login</label>
            <input
                id="name"
                name="name"
                type="text"
                class="form-control form-control-solid @error('name') is-invalid @enderror"
                value="{{ old('name', $user->name) }}"
                required
                autocomplete="name"
            >
            @error('name')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-5">
            <label for="email" class="form-label required">Email</label>
            <input
                id="email"
                name="email"
                type="email"
                class="form-control form-control-solid @error('email') is-invalid @enderror"
                value="{{ old('email', $user->email) }}"
                required
                autocomplete="username"
            >
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div class="notice d-flex bg-light-warning rounded border-warning border border-dashed p-4 mt-4">
                    <div class="text-warning">
                        Email Anda belum diverifikasi.
                        <button form="send-verification" class="btn btn-link p-0 align-baseline">
                            Kirim ulang email verifikasi.
                        </button>
                    </div>
                </div>

                @if (session('status') === 'verification-link-sent')
                    <div class="text-success fw-semibold mt-3">
                        Link verifikasi baru sudah dikirim ke email Anda.
                    </div>
                @endif
            @endif
        </div>

        <div class="d-flex align-items-center gap-4">
            <button type="submit" class="btn btn-primary">Simpan</button>

            @if (session('status') === 'profile-updated')
                <span class="text-muted">Tersimpan.</span>
            @endif
        </div>
    </form>
</section>
