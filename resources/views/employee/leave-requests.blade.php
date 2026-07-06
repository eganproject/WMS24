@extends('layouts.mobile')

@section('title', 'Pengajuan Cuti/Izin')

@section('content')
<style>
    .leave-header {
        background: #fff;
        border: 1px solid rgba(226, 232, 240, .7);
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        padding: 16px;
        margin-bottom: 16px;
    }
    .leave-title {
        font-size: 18px;
        font-weight: 800;
        margin-bottom: 4px;
    }
    .leave-form {
        display: grid;
        gap: 12px;
    }
    .field-label {
        display: block;
        font-size: 12px;
        font-weight: 700;
        margin-bottom: 6px;
    }
    .select,
    .textarea {
        width: 100%;
        padding: 12px 14px;
        border-radius: 12px;
        border: 1px solid var(--border);
        font-size: 14px;
        background: #fff;
    }
    .textarea {
        min-height: 92px;
        resize: vertical;
    }
    .alert {
        border-radius: 14px;
        padding: 12px;
        font-size: 13px;
        margin-bottom: 12px;
    }
    .alert-success {
        background: #ecfdf3;
        color: #166534;
        border: 1px solid #bbf7d0;
    }
    .alert-danger {
        background: #fef2f2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }
    .leave-list {
        display: grid;
        gap: 12px;
    }
    .leave-item {
        border: 1px solid var(--border);
        border-radius: 16px;
        padding: 12px;
        background: #fff;
    }
    .leave-item-head {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        align-items: flex-start;
        margin-bottom: 8px;
    }
    .status {
        display: inline-flex;
        padding: 5px 9px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 800;
        white-space: nowrap;
    }
    .status.pending { background: #fff8dd; color: #946200; }
    .status.approved { background: #e8fff3; color: #047857; }
    .status.rejected { background: #fff1f1; color: #b91c1c; }
    .proof-link {
        display: inline-flex;
        margin-top: 8px;
        color: var(--brand);
        font-weight: 700;
        font-size: 12px;
        text-decoration: none;
    }
    .back-link {
        display: inline-flex;
        color: var(--brand);
        font-size: 12px;
        font-weight: 700;
        text-decoration: none;
        margin-bottom: 12px;
    }
</style>

<div class="screen">
    <a href="{{ $dashboardUrl }}" class="back-link">Kembali ke dashboard</a>

    <div class="topbar">
        <div>
            <div class="brand">{{ config('app.name', 'Gudang 24') }}</div>
            <div class="subtitle">Self-service karyawan</div>
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="logout">Logout</button>
        </form>
    </div>

    <div class="leave-header">
        <div class="leave-title">Pengajuan Cuti/Izin</div>
        @if($employee)
            <div class="muted">{{ $employee->employee_code }} - {{ $employee->name }}</div>
            <div class="muted">{{ $employee->positionRelation?->name ?? $employee->position ?? 'Karyawan' }}</div>
        @else
            <div class="muted">Akun ini belum terhubung ke data karyawan.</div>
        @endif
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if(($errors ?? null)?->any())
        <div class="alert alert-danger">
            {{ $errors->first() }}
        </div>
    @endif

    @if(!$employee)
        <div class="card">
            <div class="leave-title">Tidak bisa membuat pengajuan</div>
            <div class="muted">Hubungi HR/Admin agar akun login Anda dihubungkan ke master karyawan.</div>
        </div>
    @else
        <div class="card">
            <form class="leave-form" method="POST" action="{{ $storeUrl }}" enctype="multipart/form-data">
                @csrf
                <div>
                    <label class="field-label">Tipe</label>
                    <select name="leave_type" class="select" required>
                        <option value="annual" @selected(old('leave_type') === 'annual')>Cuti tahunan</option>
                        <option value="sick" @selected(old('leave_type') === 'sick')>Sakit</option>
                        <option value="permission" @selected(old('leave_type') === 'permission')>Izin</option>
                        <option value="unpaid" @selected(old('leave_type') === 'unpaid')>Unpaid</option>
                    </select>
                </div>
                <div>
                    <label class="field-label">Tanggal Mulai</label>
                    <input type="date" name="start_date" class="input" value="{{ old('start_date') }}" required>
                </div>
                <div>
                    <label class="field-label">Tanggal Selesai</label>
                    <input type="date" name="end_date" class="input" value="{{ old('end_date') }}" required>
                </div>
                <div>
                    <label class="field-label">Alasan</label>
                    <textarea name="reason" class="textarea" placeholder="Tulis alasan cuti/izin" required>{{ old('reason') }}</textarea>
                </div>
                <div>
                    <label class="field-label">Bukti Gambar</label>
                    <input type="file" name="proof_image" class="input" accept="image/jpeg,image/png,image/webp">
                    <div class="muted">Opsional. Format JPG, PNG, WEBP. Maksimal 2 MB.</div>
                </div>
                <button type="submit" class="primary-btn">Kirim Pengajuan</button>
            </form>
        </div>
    @endif

    <div class="card">
        <div class="leave-title">Riwayat Pengajuan</div>
        <div class="muted" style="margin-bottom:12px;">Status pengajuan akan berubah setelah diproses HR/Admin.</div>
        <div class="leave-list">
            @forelse($leaves as $leave)
                <div class="leave-item">
                    <div class="leave-item-head">
                        <div>
                            <strong>{{ ucfirst($leave->leave_type) }}</strong>
                            <div class="muted">{{ $leave->start_date?->format('Y-m-d') }} s/d {{ $leave->end_date?->format('Y-m-d') }}</div>
                        </div>
                        <span class="status {{ $leave->status }}">{{ strtoupper($leave->status) }}</span>
                    </div>
                    <div class="muted">{{ $leave->reason ?: '-' }}</div>
                    @if($leave->proof_image_path)
                        <a class="proof-link" href="{{ route('employee.leave-requests.proof-image', $leave) }}" target="_blank" rel="noopener">Lihat bukti</a>
                    @endif
                    @if($leave->approved_at)
                        <div class="muted" style="margin-top:8px;">Diproses: {{ $leave->approved_at->format('Y-m-d H:i') }}</div>
                    @endif
                </div>
            @empty
                <div class="muted">Belum ada pengajuan.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
