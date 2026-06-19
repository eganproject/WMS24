<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Performa Absensi</title>
    <link rel="shortcut icon" href="{{ asset('metronic/media/logos/favicon.png') }}">
    <link rel="stylesheet" href="{{ asset('metronic/plugins/global/plugins.bundle.css') }}">
    <style>
        :root {
            --bg: #f4f7f6;
            --surface: #ffffff;
            --surface-2: #eef6f4;
            --text: #17202a;
            --muted: #667085;
            --border: #d8e2df;
            --green: #0f8f72;
            --teal: #0b7285;
            --amber: #c77700;
            --red: #c23b3b;
            --ink: #22313a;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            background: var(--bg);
            color: var(--text);
            font-family: "Inter", "Segoe UI", Arial, sans-serif;
        }

        .page {
            margin: 0 auto;
            max-width: 1180px;
            padding: 24px 18px 48px;
        }

        .topbar,
        .panel,
        .metric,
        .day,
        .record-row {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
        }

        .topbar {
            align-items: center;
            display: flex;
            gap: 16px;
            justify-content: space-between;
            margin-bottom: 16px;
            padding: 14px 16px;
        }

        .brand {
            align-items: center;
            display: flex;
            gap: 12px;
            min-width: 0;
        }

        .avatar {
            align-items: center;
            background: var(--green);
            border-radius: 8px;
            color: #fff;
            display: inline-flex;
            flex: 0 0 42px;
            font-weight: 800;
            height: 42px;
            justify-content: center;
            width: 42px;
        }

        .title {
            font-size: 18px;
            font-weight: 800;
            line-height: 1.2;
            margin: 0;
        }

        .subtitle,
        .muted {
            color: var(--muted);
            font-size: 12px;
        }

        .actions {
            align-items: center;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: flex-end;
        }

        .btn,
        .month-input {
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text);
            font-size: 13px;
            font-weight: 700;
            min-height: 40px;
            padding: 9px 12px;
            text-decoration: none;
        }

        .btn {
            background: #fff;
            display: inline-flex;
            gap: 8px;
            align-items: center;
        }

        .btn-primary {
            background: var(--green);
            border-color: var(--green);
            color: #fff;
        }

        .month-input {
            background: #fff;
            width: 154px;
        }

        .hero {
            background: linear-gradient(135deg, #0f8f72 0%, #0b7285 100%);
            border-radius: 8px;
            color: #fff;
            display: grid;
            gap: 18px;
            grid-template-columns: minmax(0, 1.25fr) minmax(280px, .75fr);
            margin-bottom: 16px;
            padding: 24px;
        }

        .hero h1 {
            color: #fff;
            font-size: 30px;
            font-weight: 850;
            letter-spacing: 0;
            line-height: 1.12;
            margin: 0 0 10px;
        }

        .hero-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 18px;
        }

        .hero-chip {
            background: rgba(255, 255, 255, .16);
            border: 1px solid rgba(255, 255, 255, .26);
            border-radius: 8px;
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            padding: 8px 10px;
        }

        .score-box {
            align-self: stretch;
            background: rgba(255, 255, 255, .14);
            border: 1px solid rgba(255, 255, 255, .26);
            border-radius: 8px;
            display: grid;
            place-items: center;
            min-height: 170px;
            padding: 16px;
            text-align: center;
        }

        .score {
            font-size: 60px;
            font-weight: 900;
            line-height: .95;
        }

        .score-label {
            color: rgba(255, 255, 255, .82);
            font-size: 12px;
            font-weight: 800;
            margin-top: 8px;
            text-transform: uppercase;
        }

        .grid {
            display: grid;
            gap: 16px;
        }

        .metrics {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .metric {
            padding: 15px;
        }

        .metric .label {
            color: var(--muted);
            font-size: 12px;
            font-weight: 700;
        }

        .metric .value {
            color: var(--ink);
            font-size: 24px;
            font-weight: 850;
            margin-top: 7px;
        }

        .metric .meta {
            color: var(--muted);
            font-size: 12px;
            margin-top: 4px;
        }

        .main-grid {
            grid-template-columns: minmax(0, 1.1fr) minmax(340px, .9fr);
            margin-top: 16px;
        }

        .panel {
            padding: 18px;
        }

        .panel-head {
            align-items: center;
            display: flex;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 14px;
        }

        .panel-title {
            font-size: 15px;
            font-weight: 850;
            margin: 0;
        }

        .calendar-grid {
            display: grid;
            gap: 8px;
            grid-template-columns: repeat(7, minmax(0, 1fr));
        }

        .weekday {
            color: var(--muted);
            font-size: 11px;
            font-weight: 800;
            text-align: center;
            text-transform: uppercase;
        }

        .day {
            aspect-ratio: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-width: 0;
            padding: 8px;
        }

        .day-number {
            font-size: 13px;
            font-weight: 850;
        }

        .day-status {
            font-size: 10px;
            font-weight: 800;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .status-present { background: #e7f7f1; border-color: #a7dcca; color: #0a6d55; }
        .status-late { background: #fff6df; border-color: #ffd88a; color: #9a5a00; }
        .status-absent { background: #ffecec; border-color: #f4aaaa; color: #a32626; }
        .status-incomplete { background: #eef3f8; border-color: #c8d5e2; color: #34495e; }
        .status-leave { background: #e8f6fa; border-color: #adddea; color: #0b7285; }
        .status-holiday,
        .status-day-off { background: #f2f4f7; border-color: #d0d5dd; color: #667085; }
        .status-not-checked-in { background: #fff7e6; border-color: #ffd591; color: #ad6800; }
        .status-no-record { background: #fff; color: #98a2b3; }
        .status-future { background: #f8fafc; color: #b0b7c3; }

        .legend {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 14px;
        }

        .legend-item {
            align-items: center;
            color: var(--muted);
            display: inline-flex;
            font-size: 11px;
            font-weight: 700;
            gap: 6px;
        }

        .dot {
            border-radius: 50%;
            height: 9px;
            width: 9px;
        }

        .bar-row {
            display: grid;
            gap: 10px;
            grid-template-columns: 86px minmax(0, 1fr) 46px;
            align-items: center;
            margin-bottom: 12px;
        }

        .bar-label,
        .bar-value {
            color: var(--muted);
            font-size: 12px;
            font-weight: 800;
        }

        .bar-track {
            background: #eef2f1;
            border-radius: 999px;
            height: 10px;
            overflow: hidden;
        }

        .bar-fill {
            background: var(--green);
            border-radius: inherit;
            height: 100%;
        }

        .record-list {
            display: grid;
            gap: 10px;
            max-height: 620px;
            overflow: auto;
            padding-right: 2px;
        }

        .record-row {
            display: grid;
            gap: 12px;
            grid-template-columns: 58px minmax(0, 1fr) auto;
            padding: 12px;
        }

        .datebox {
            align-items: center;
            background: var(--surface-2);
            border-radius: 8px;
            display: flex;
            flex-direction: column;
            font-weight: 850;
            justify-content: center;
            min-height: 54px;
        }

        .datebox span {
            color: var(--muted);
            font-size: 10px;
            text-transform: uppercase;
        }

        .record-title {
            font-size: 13px;
            font-weight: 850;
            margin-bottom: 4px;
        }

        .record-meta {
            color: var(--muted);
            font-size: 12px;
            line-height: 1.6;
        }

        .pill {
            border-radius: 999px;
            font-size: 11px;
            font-weight: 850;
            padding: 7px 9px;
            white-space: nowrap;
        }

        .empty {
            border: 1px dashed var(--border);
            border-radius: 8px;
            color: var(--muted);
            padding: 26px;
            text-align: center;
        }

        @media (max-width: 920px) {
            .hero,
            .main-grid,
            .metrics {
                grid-template-columns: 1fr;
            }

            .hero h1 {
                font-size: 26px;
            }
        }

        @media (max-width: 620px) {
            .page {
                padding: 14px 12px 32px;
            }

            .topbar,
            .actions {
                align-items: stretch;
                flex-direction: column;
            }

            .actions,
            .btn,
            .month-input {
                width: 100%;
            }

            .calendar-grid {
                gap: 5px;
            }

            .day {
                padding: 6px;
            }

            .day-status {
                display: none;
            }

            .record-row {
                grid-template-columns: 50px minmax(0, 1fr);
            }

            .record-row .pill {
                grid-column: 1 / -1;
                justify-self: start;
            }
        }
    </style>
</head>
<body>
@php
    $initial = $employee ? mb_substr($employee->name, 0, 1) : '?';
    $statusClassCss = fn ($status) => 'status-'.str_replace('_', '-', $status);
@endphp
<main class="page">
    <div class="topbar">
        <div class="brand">
            <div class="avatar">{{ strtoupper($initial) }}</div>
            <div>
                <p class="title">Performa Absensi</p>
                <div class="subtitle">{{ auth()->user()->name }} · {{ $monthLabel }}</div>
            </div>
        </div>
        <form class="actions" method="GET" action="{{ route('employee.attendance-performance') }}">
            <a class="btn" href="{{ route('mobile.dashboard') }}"><i class="fas fa-arrow-left"></i>Dashboard</a>
            <a class="btn" href="{{ route('employee.attendance-performance', ['month' => $prevMonth ?? $month->copy()->subMonth()->format('Y-m')]) }}"><i class="fas fa-chevron-left"></i></a>
            <input class="month-input" type="month" name="month" value="{{ $month->format('Y-m') }}">
            <a class="btn" href="{{ route('employee.attendance-performance', ['month' => $nextMonth ?? $month->copy()->addMonth()->format('Y-m')]) }}"><i class="fas fa-chevron-right"></i></a>
            <button class="btn btn-primary" type="submit"><i class="fas fa-filter"></i>Terapkan</button>
        </form>
    </div>

    @if(!$employee)
        <section class="panel">
            <div class="empty">
                Akun login belum terhubung dengan data karyawan absensi.
            </div>
        </section>
    @else
        <section class="hero">
            <div>
                <h1>{{ $employee->name }}</h1>
                <div>{{ $employee->employee_code }} · {{ $employee->positionRelation?->name ?? $employee->position ?? 'Karyawan' }}</div>
                <div class="hero-meta">
                    <span class="hero-chip"><i class="fas fa-building me-1"></i>{{ $employee->area?->name ?? 'Area belum diset' }}</span>
                    <span class="hero-chip"><i class="fas fa-calendar me-1"></i>{{ $monthLabel }}</span>
                    <span class="hero-chip"><i class="fas fa-user-check me-1"></i>{{ $summary['attended_days'] }}/{{ $summary['scheduled_days'] }} hari hadir</span>
                </div>
            </div>
            <div class="score-box">
                <div>
                    <div class="score">{{ $summary['score'] }}</div>
                    <div class="score-label">Skor Performa</div>
                </div>
            </div>
        </section>

        <section class="grid metrics">
            <div class="metric">
                <div class="label">Kehadiran</div>
                <div class="value">{{ $summary['attendance_rate'] }}%</div>
                <div class="meta">{{ $summary['attended_days'] }} dari {{ $summary['scheduled_days'] }} hari kerja</div>
            </div>
            <div class="metric">
                <div class="label">Tepat Waktu</div>
                <div class="value">{{ $summary['on_time_rate'] }}%</div>
                <div class="meta">{{ $counts['present'] }} tepat waktu · {{ $counts['late'] }} terlambat</div>
            </div>
            <div class="metric">
                <div class="label">Jam Kerja</div>
                <div class="value">{{ $summary['total_work'] }}</div>
                <div class="meta">Rata-rata {{ $summary['avg_work'] }} per hari hadir</div>
            </div>
            <div class="metric">
                <div class="label">Lembur Approved</div>
                <div class="value">{{ $summary['approved_overtime'] }}</div>
                <div class="meta">Terlambat {{ $summary['late_minutes'] }}</div>
            </div>
        </section>

        <section class="grid main-grid">
            <div class="panel">
                <div class="panel-head">
                    <h2 class="panel-title">Kalender Bulanan</h2>
                    <span class="muted">Masuk rata-rata {{ $summary['avg_check_in'] }} · Pulang {{ $summary['avg_check_out'] }}</span>
                </div>
                <div class="calendar-grid">
                    @foreach(['Sen','Sel','Rab','Kam','Jum','Sab','Min'] as $dayName)
                        <div class="weekday">{{ $dayName }}</div>
                    @endforeach
                    @for($i = 1; $i < $month->copy()->startOfMonth()->dayOfWeekIso; $i++)
                        <div></div>
                    @endfor
                    @foreach($days as $day)
                        @php($record = $day['record'])
                        <div class="day {{ $statusClassCss($day['status']) }}" title="{{ $day['date'] }} · {{ $statusLabels[$day['status']] ?? $day['status'] }}">
                            <div class="day-number">{{ $day['day'] }}</div>
                            <div class="day-status">{{ $statusLabels[$day['status']] ?? $day['status'] }}</div>
                            @if($record?->late_minutes)
                                <div class="day-status">+{{ $record->late_minutes }}m</div>
                            @endif
                        </div>
                    @endforeach
                </div>
                <div class="legend">
                    <span class="legend-item"><span class="dot" style="background:#0f8f72"></span>Tepat waktu</span>
                    <span class="legend-item"><span class="dot" style="background:#c77700"></span>Terlambat</span>
                    <span class="legend-item"><span class="dot" style="background:#c23b3b"></span>Alpha</span>
                    <span class="legend-item"><span class="dot" style="background:#ad6800"></span>Belum Check-in</span>
                    <span class="legend-item"><span class="dot" style="background:#0b7285"></span>Cuti/Izin</span>
                    <span class="legend-item"><span class="dot" style="background:#98a2b3"></span>Libur</span>
                </div>
            </div>

            <div class="panel">
                <div class="panel-head">
                    <h2 class="panel-title">Ringkasan Mingguan</h2>
                    <span class="muted">{{ $counts['absent'] }} alpha · {{ $counts['incomplete'] }} belum lengkap</span>
                </div>
                @forelse($weekStats as $week)
                    <div class="bar-row">
                        <div class="bar-label">{{ $week['label'] }}</div>
                        <div class="bar-track"><div class="bar-fill" style="width: {{ $week['rate'] }}%"></div></div>
                        <div class="bar-value">{{ $week['rate'] }}%</div>
                    </div>
                @empty
                    <div class="empty">Belum ada ringkasan mingguan.</div>
                @endforelse
                <div class="grid metrics" style="grid-template-columns: repeat(2, minmax(0, 1fr)); margin-top: 14px;">
                    <div class="metric">
                        <div class="label">Cuti/Izin</div>
                        <div class="value">{{ $counts['leave'] }}</div>
                        <div class="meta">hari</div>
                    </div>
                    <div class="metric">
                        <div class="label">Pulang Cepat</div>
                        <div class="value">{{ $summary['early_leave_minutes'] }}</div>
                        <div class="meta">akumulasi</div>
                    </div>
                </div>
            </div>
        </section>

        <section class="panel" style="margin-top: 16px;">
            <div class="panel-head">
                <h2 class="panel-title">Riwayat Harian</h2>
                <span class="muted">{{ $records->count() }} catatan</span>
            </div>
            <div class="record-list">
                @forelse($records as $record)
                    @php($status = $record->status ?? 'no_record')
                    <div class="record-row">
                        <div class="datebox">
                            {{ $record->attendance_date?->format('d') }}
                            <span>{{ $record->attendance_date?->translatedFormat('M') }}</span>
                        </div>
                        <div>
                            <div class="record-title">{{ $record->shift?->name ?? 'Tanpa shift' }}</div>
                            <div class="record-meta">
                                Masuk {{ $record->check_in_at?->format('H:i') ?? '-' }} · Pulang {{ $record->check_out_at?->format('H:i') ?? '-' }}
                                <br>
                                Kerja {{ intdiv((int) $record->work_minutes, 60) }}j {{ ((int) $record->work_minutes % 60) }}m
                                @if((int) $record->late_minutes > 0)
                                    · Telat {{ $record->late_minutes }}m
                                @endif
                                @if((int) $record->approved_overtime_minutes > 0)
                                    · Lembur {{ intdiv((int) $record->approved_overtime_minutes, 60) }}j {{ ((int) $record->approved_overtime_minutes % 60) }}m
                                @endif
                            </div>
                        </div>
                        <div class="pill {{ $statusClassCss($status) }}">{{ $statusLabels[$status] ?? $status }}</div>
                    </div>
                @empty
                    <div class="empty">Belum ada catatan absensi pada bulan ini.</div>
                @endforelse
            </div>
        </section>
    @endif
</main>
</body>
</html>
