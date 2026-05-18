<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="shortcut icon" href="{{ asset('metronic/media/logos/favicon.png') }}" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Gudang 24') }} | Login</title>
    <style>
        :root {
            --page: #f4f7fb;
            --ink: #172033;
            --muted: #667085;
            --line: #d9e2ef;
            --panel: #ffffff;
            --panel-soft: #eef4fb;
            --brand: #0f766e;
            --brand-dark: #115e59;
            --blue: #2563eb;
            --amber: #f59e0b;
            --danger: #dc2626;
            --shadow: 0 24px 70px rgba(16, 24, 40, 0.14);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100svh;
            color: var(--ink);
            font-family: "Inter", "Segoe UI", Arial, sans-serif;
            background:
                linear-gradient(90deg, rgba(15, 118, 110, 0.08) 0 1px, transparent 1px 100%),
                linear-gradient(0deg, rgba(37, 99, 235, 0.06) 0 1px, transparent 1px 100%),
                var(--page);
            background-size: 42px 42px;
        }

        .login-shell {
            min-height: 100svh;
            display: grid;
            grid-template-columns: minmax(0, 1.15fr) minmax(390px, 0.85fr);
        }

        .operations-panel {
            position: relative;
            min-height: 100svh;
            padding: 42px;
            overflow: hidden;
            background:
                linear-gradient(135deg, rgba(15, 118, 110, 0.12), transparent 42%),
                linear-gradient(315deg, rgba(245, 158, 11, 0.18), transparent 38%),
                #eaf1f8;
        }

        .operations-header {
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .mark {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: grid;
            place-items: center;
            background: #ffffff;
            border: 1px solid rgba(15, 118, 110, 0.15);
            box-shadow: 0 10px 24px rgba(15, 118, 110, 0.14);
        }

        .mark img {
            width: 30px;
            height: 30px;
            object-fit: contain;
        }

        .brand-name {
            margin: 0;
            font-size: 18px;
            font-weight: 800;
            letter-spacing: 0;
        }

        .brand-subtitle {
            margin: 3px 0 0;
            color: var(--muted);
            font-size: 13px;
        }

        .hero-copy {
            position: relative;
            z-index: 2;
            max-width: 620px;
            margin-top: 74px;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 11px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.78);
            color: var(--brand-dark);
            border: 1px solid rgba(15, 118, 110, 0.16);
            font-size: 12px;
            font-weight: 800;
        }

        .hero-copy h1 {
            margin: 22px 0 14px;
            max-width: 600px;
            font-size: clamp(36px, 5vw, 66px);
            line-height: 0.98;
            letter-spacing: 0;
        }

        .hero-copy p {
            max-width: 540px;
            margin: 0;
            color: #475467;
            font-size: 16px;
            line-height: 1.7;
        }

        .metrics {
            position: relative;
            z-index: 2;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            max-width: 680px;
            margin-top: 32px;
        }

        .metric {
            min-height: 92px;
            padding: 16px;
            border-radius: 8px;
            border: 1px solid rgba(15, 23, 42, 0.08);
            background: rgba(255, 255, 255, 0.76);
            backdrop-filter: blur(10px);
            box-shadow: 0 14px 34px rgba(16, 24, 40, 0.08);
        }

        .metric svg {
            width: 22px;
            height: 22px;
            color: var(--brand);
        }

        .metric strong {
            display: block;
            margin-top: 10px;
            font-size: 18px;
        }

        .metric span {
            display: block;
            margin-top: 3px;
            color: var(--muted);
            font-size: 12px;
        }

        .warehouse-scene {
            position: absolute;
            inset-inline: 42px;
            bottom: 30px;
            height: min(36vh, 310px);
            min-height: 230px;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid rgba(15, 23, 42, 0.08);
            background:
                linear-gradient(180deg, rgba(255,255,255,0.88), rgba(232,239,248,0.82)),
                linear-gradient(90deg, rgba(15,118,110,0.08), rgba(37,99,235,0.06));
            box-shadow: 0 26px 70px rgba(16, 24, 40, 0.14);
        }

        .shelf {
            position: absolute;
            left: 36px;
            bottom: 82px;
            width: min(42%, 340px);
            height: 154px;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            padding: 12px;
            border: 5px solid #334155;
            border-radius: 6px;
            background: rgba(51, 65, 85, 0.08);
        }

        .shelf::before,
        .shelf::after {
            content: "";
            position: absolute;
            left: 0;
            right: 0;
            height: 5px;
            background: #334155;
        }

        .shelf::before { top: 50%; }
        .shelf::after { bottom: -28px; height: 28px; width: 5px; left: 18px; right: auto; box-shadow: 280px 0 #334155; }

        .box {
            border-radius: 5px;
            background: linear-gradient(135deg, #d97706, #fbbf24);
            border: 1px solid rgba(146, 64, 14, 0.28);
            position: relative;
        }

        .box:nth-child(2n) { background: linear-gradient(135deg, #0f766e, #5eead4); }
        .box:nth-child(3n) { background: linear-gradient(135deg, #2563eb, #93c5fd); }

        .box::after {
            content: "";
            position: absolute;
            top: 8px;
            left: 0;
            right: 0;
            height: 1px;
            background: rgba(255,255,255,0.45);
        }

        .conveyor {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 40px;
            height: 42px;
            background:
                repeating-linear-gradient(90deg, #475467 0 28px, #344054 28px 56px);
            border-top: 5px solid #1f2937;
            border-bottom: 5px solid #1f2937;
            animation: belt 1.7s linear infinite;
        }

        .moving-package {
            position: absolute;
            right: 12%;
            bottom: 94px;
            width: 72px;
            height: 58px;
            border-radius: 6px;
            background: linear-gradient(135deg, #f59e0b, #fcd34d);
            border: 2px solid rgba(146, 64, 14, 0.35);
            box-shadow: 0 12px 24px rgba(146, 64, 14, 0.18);
            animation: packageMove 5.8s ease-in-out infinite;
        }

        .moving-package::before {
            content: "";
            position: absolute;
            top: 0;
            bottom: 0;
            left: 50%;
            width: 10px;
            transform: translateX(-50%);
            background: rgba(255, 255, 255, 0.24);
        }

        .scanner {
            position: absolute;
            right: 68px;
            bottom: 110px;
            width: 90px;
            height: 120px;
            border-radius: 8px 8px 4px 4px;
            background: #172033;
            box-shadow: 0 12px 32px rgba(23, 32, 51, 0.25);
        }

        .scanner::before {
            content: "";
            position: absolute;
            left: 18px;
            right: 18px;
            top: 18px;
            height: 44px;
            border-radius: 4px;
            background: linear-gradient(180deg, rgba(34, 197, 94, 0.9), rgba(15, 118, 110, 0.6));
            box-shadow: 0 0 0 0 rgba(15, 118, 110, 0.35);
            animation: scanPulse 1.8s ease-out infinite;
        }

        .scanner::after {
            content: "";
            position: absolute;
            left: 24px;
            right: 24px;
            bottom: 18px;
            height: 10px;
            border-radius: 999px;
            background: #f59e0b;
        }

        .login-panel {
            min-height: 100svh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 34px;
            background: rgba(255, 255, 255, 0.82);
            border-left: 1px solid rgba(15, 23, 42, 0.08);
        }

        .login-card {
            width: min(430px, 100%);
            padding: 30px;
            border-radius: 8px;
            background: var(--panel);
            border: 1px solid rgba(15, 23, 42, 0.08);
            box-shadow: var(--shadow);
            animation: enter 0.5s ease-out both;
        }

        .mobile-mark {
            display: none;
            align-items: center;
            gap: 12px;
            margin-bottom: 26px;
        }

        .login-title {
            margin: 0 0 8px;
            font-size: 28px;
            line-height: 1.15;
            letter-spacing: 0;
        }

        .login-copy {
            margin: 0 0 24px;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.6;
        }

        .alert {
            display: flex;
            gap: 9px;
            align-items: flex-start;
            margin-bottom: 18px;
            padding: 11px 12px;
            border-radius: 8px;
            color: #075985;
            background: #e0f2fe;
            border: 1px solid #bae6fd;
            font-size: 13px;
        }

        .form-row {
            margin-bottom: 16px;
        }

        .form-row label {
            display: block;
            margin-bottom: 8px;
            color: #344054;
            font-size: 13px;
            font-weight: 800;
        }

        .input-wrap {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 13px;
            top: 50%;
            width: 18px;
            height: 18px;
            transform: translateY(-50%);
            color: #667085;
            pointer-events: none;
        }

        .input {
            width: 100%;
            min-height: 46px;
            padding: 12px 14px 12px 42px;
            border-radius: 8px;
            border: 1px solid var(--line);
            background: #f8fafc;
            color: var(--ink);
            outline: none;
            font-size: 14px;
            transition: border-color 0.18s ease, box-shadow 0.18s ease, background-color 0.18s ease;
        }

        .input:focus {
            border-color: var(--brand);
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(15, 118, 110, 0.12);
        }

        .input.is-invalid {
            border-color: var(--danger);
            box-shadow: 0 0 0 4px rgba(220, 38, 38, 0.1);
        }

        .error-text {
            margin-top: 6px;
            color: var(--danger);
            font-size: 12px;
            line-height: 1.4;
        }

        .row-between {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 2px 0 20px;
        }

        .checkbox {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--muted);
            font-size: 13px;
            font-weight: 700;
            user-select: none;
        }

        .checkbox input {
            width: 17px;
            height: 17px;
            accent-color: var(--brand);
        }

        .submit-btn {
            width: 100%;
            min-height: 48px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            border: 0;
            border-radius: 8px;
            color: #ffffff;
            background: linear-gradient(135deg, var(--brand), var(--brand-dark));
            box-shadow: 0 14px 24px rgba(15, 118, 110, 0.24);
            cursor: pointer;
            font-size: 14px;
            font-weight: 800;
            transition: transform 0.18s ease, box-shadow 0.18s ease, filter 0.18s ease;
        }

        .submit-btn:hover {
            transform: translateY(-1px);
            filter: saturate(1.06);
            box-shadow: 0 18px 30px rgba(15, 118, 110, 0.28);
        }

        .submit-btn:active {
            transform: translateY(0);
            box-shadow: 0 10px 18px rgba(15, 118, 110, 0.22);
        }

        .submit-btn svg {
            width: 18px;
            height: 18px;
        }

        .secure-note {
            display: flex;
            align-items: center;
            gap: 9px;
            margin-top: 18px;
            padding: 12px;
            border-radius: 8px;
            background: #f8fafc;
            color: var(--muted);
            border: 1px solid var(--line);
            font-size: 12px;
            line-height: 1.45;
        }

        .secure-note svg {
            flex: 0 0 auto;
            width: 18px;
            height: 18px;
            color: var(--blue);
        }

        @keyframes enter {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes belt {
            from { background-position-x: 0; }
            to { background-position-x: 56px; }
        }

        @keyframes packageMove {
            0%, 100% { transform: translateX(0) translateY(0); }
            45% { transform: translateX(-115px) translateY(-3px); }
            60% { transform: translateX(-115px) translateY(-3px); }
        }

        @keyframes scanPulse {
            0% { box-shadow: 0 0 0 0 rgba(15, 118, 110, 0.42); }
            100% { box-shadow: 0 0 0 18px rgba(15, 118, 110, 0); }
        }

        @media (max-width: 980px) {
            .login-shell {
                grid-template-columns: 1fr;
            }

            .operations-panel {
                display: none;
            }

            .login-panel {
                padding: 22px;
                border-left: 0;
                background:
                    linear-gradient(135deg, rgba(15, 118, 110, 0.12), transparent 40%),
                    var(--page);
            }

            .mobile-mark {
                display: flex;
            }
        }

        @media (max-width: 520px) {
            .login-panel {
                align-items: stretch;
                padding: 16px;
            }

            .login-card {
                align-self: center;
                padding: 22px;
            }

            .login-title {
                font-size: 24px;
            }
        }

        @media (max-height: 760px) and (min-width: 981px) {
            .hero-copy {
                margin-top: 46px;
            }

            .metrics {
                grid-template-columns: repeat(3, minmax(0, 160px));
            }

            .warehouse-scene {
                height: 230px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .login-card,
            .conveyor,
            .moving-package,
            .scanner::before {
                animation: none;
            }
        }
    </style>
</head>
<body>
<div class="login-shell">
    <section class="operations-panel" aria-label="Warehouse Management System">
        <header class="operations-header">
            <div class="mark">
                <img src="{{ asset('metronic/media/logos/favicon.png') }}" alt="{{ config('app.name', 'Gudang 24') }}">
            </div>
            <div>
                <p class="brand-name">{{ config('app.name', 'Gudang 24') }}</p>
                <p class="brand-subtitle">Warehouse Management System</p>
            </div>
        </header>

        <div class="hero-copy">
            <span class="eyebrow">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/>
                    <path d="m3.3 7 8.7 5 8.7-5"/>
                    <path d="M12 22V12"/>
                </svg>
                Gudang, scan, dan stok dalam satu kendali
            </span>
            <h1>Operasional gudang lebih rapi sejak login pertama.</h1>
            <p>Kelola inbound, outbound, stok, QC, dan absensi tim gudang dengan akses yang aman dan terpusat.</p>
        </div>

        <div class="metrics" aria-hidden="true">
            <div class="metric">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 7h18"/>
                    <path d="M5 7v12h14V7"/>
                    <path d="M8 7V5h8v2"/>
                    <path d="M8 11h8"/>
                </svg>
                <strong>Inbound</strong>
                <span>Receipt, retur, dan manual flow</span>
            </div>
            <div class="metric">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M4 4h16v16H4z"/>
                    <path d="M8 4v16"/>
                    <path d="M16 4v16"/>
                    <path d="M4 12h16"/>
                </svg>
                <strong>Inventory</strong>
                <span>Mutasi, opname, dan transfer</span>
            </div>
            <div class="metric">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 11 12 14 22 4"/>
                    <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                </svg>
                <strong>QC</strong>
                <span>Scan, validasi, dan exception</span>
            </div>
        </div>

        <div class="warehouse-scene" aria-hidden="true">
            <div class="shelf">
                <span class="box"></span><span class="box"></span><span class="box"></span>
                <span class="box"></span><span class="box"></span><span class="box"></span>
            </div>
            <div class="moving-package"></div>
            <div class="scanner"></div>
            <div class="conveyor"></div>
        </div>
    </section>

    <section class="login-panel">
        <main class="login-card">
            <div class="mobile-mark">
                <div class="mark">
                    <img src="{{ asset('metronic/media/logos/favicon.png') }}" alt="{{ config('app.name', 'Gudang 24') }}">
                </div>
                <div>
                    <p class="brand-name">{{ config('app.name', 'Gudang 24') }}</p>
                    <p class="brand-subtitle">Warehouse Management System</p>
                </div>
            </div>

            <h2 class="login-title">Masuk ke dashboard</h2>
            <p class="login-copy">Gunakan akun yang sudah diberikan administrator untuk mengakses sistem WMS.</p>

            @if (session('status'))
                <div class="alert">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M20 6 9 17l-5-5"/>
                    </svg>
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf
                <div class="form-row">
                    <label for="email">Email</label>
                    <div class="input-wrap">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M4 4h16v16H4z"/>
                            <path d="m22 6-10 7L2 6"/>
                        </svg>
                        <input
                            id="email"
                            class="input @error('email') is-invalid @enderror"
                            type="email"
                            name="email"
                            value="{{ old('email') }}"
                            required
                            autofocus
                            autocomplete="username"
                            placeholder="nama@perusahaan.com"
                        >
                    </div>
                    @error('email')
                        <div class="error-text">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-row">
                    <label for="password">Password</label>
                    <div class="input-wrap">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <rect x="3" y="11" width="18" height="11" rx="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                        <input
                            id="password"
                            class="input @error('password') is-invalid @enderror"
                            type="password"
                            name="password"
                            required
                            autocomplete="current-password"
                            placeholder="Masukkan password"
                        >
                    </div>
                    @error('password')
                        <div class="error-text">{{ $message }}</div>
                    @enderror
                </div>

                <div class="row-between">
                    <label class="checkbox">
                        <input type="checkbox" name="remember">
                        Ingat sesi perangkat ini
                    </label>
                </div>

                <button type="submit" class="submit-btn">
                    Masuk
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M5 12h14"/>
                        <path d="m13 6 6 6-6 6"/>
                    </svg>
                </button>
            </form>

            <div class="secure-note">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67 0C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.15 1.15 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/>
                    <path d="m9 12 2 2 4-4"/>
                </svg>
                <span>Akses akun dan pembuatan user dikelola oleh administrator sistem.</span>
            </div>
        </main>
    </section>
</div>
</body>
</html>
