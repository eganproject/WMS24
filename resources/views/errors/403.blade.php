<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>403 — Akses Ditolak</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: linear-gradient(135deg, #f4f5f7 0%, #d7d9dd 50%, #c9cdd1 100%);
        }
        .card {
            width: 100%;
            max-width: 420px;
            background: rgba(255,255,255,0.82);
            border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.5);
            box-shadow: 0 20px 60px rgba(0,0,0,.14);
            padding: 40px 36px 36px;
            text-align: center;
        }
        .code {
            font-size: 72px;
            font-weight: 800;
            line-height: 1;
            color: #0f172a;
            letter-spacing: -2px;
            margin-bottom: 12px;
        }
        .title {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 10px;
        }
        .message {
            font-size: 14px;
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 32px;
        }
        .actions {
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 11px 20px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: transform .15s ease, opacity .15s ease;
        }
        .btn:hover { transform: translateY(-1px); opacity: .9; }
        .btn-primary {
            background: linear-gradient(135deg, #0f766e, #14b8a6);
            color: #fff;
            box-shadow: 0 8px 20px rgba(15,118,110,.2);
        }
        .btn-ghost {
            background: #fff;
            color: #0f172a;
            border: 1px solid #cbd5e1;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="code">403</div>
        <div class="title">Akses Ditolak</div>
        <div class="message">{{ $exception->getMessage() ?: 'Anda tidak memiliki izin untuk mengakses halaman ini.' }}</div>
        <div class="actions">
            <button type="button" class="btn btn-ghost" onclick="history.back()">Kembali</button>
            @auth
                <a href="{{ route('dashboard') }}" class="btn btn-primary">Dashboard</a>
            @else
                <a href="{{ route('login') }}" class="btn btn-primary">Login</a>
            @endauth
        </div>
    </div>
</body>
</html>
