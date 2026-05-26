<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class EmployeeAttendancePerformanceController extends Controller
{
    public function index(Request $request)
    {
        $employee = $request->user()?->employee()
            ->with(['area:id,code,name', 'positionRelation:id,name'])
            ->first();

        $month = $this->resolveMonth($request);

        if (!$employee) {
            return view('employee.attendance-performance', [
                'employee' => null,
                'month' => $month,
                'monthLabel' => $month->translatedFormat('F Y'),
            ]);
        }

        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();
        $today = now('Asia/Jakarta')->startOfDay();
        $effectiveEnd = $end->greaterThan($today) ? $today : $end;

        $records = Attendance::query()
            ->with('shift:id,name,start_time,end_time')
            ->where('employee_id', $employee->id)
            ->whereBetween('attendance_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('attendance_date')
            ->get();

        $recordsByDate = $records->keyBy(fn (Attendance $row) => $row->attendance_date?->toDateString());
        $days = $this->calendarDays($start, $end, $effectiveEnd, $recordsByDate);
        $workRecords = $records->whereIn('status', [
            Attendance::STATUS_PRESENT,
            Attendance::STATUS_LATE,
            Attendance::STATUS_ABSENT,
            Attendance::STATUS_INCOMPLETE,
            Attendance::STATUS_LEAVE,
        ]);

        $counts = [
            'present' => $records->where('status', Attendance::STATUS_PRESENT)->count(),
            'late' => $records->where('status', Attendance::STATUS_LATE)->count(),
            'absent' => $records->where('status', Attendance::STATUS_ABSENT)->count(),
            'incomplete' => $records->where('status', Attendance::STATUS_INCOMPLETE)->count(),
            'leave' => $records->where('status', Attendance::STATUS_LEAVE)->count(),
            'holiday' => $records->where('status', Attendance::STATUS_HOLIDAY)->count(),
            'day_off' => $records->where('status', Attendance::STATUS_DAY_OFF)->count(),
        ];

        $scheduledDays = max(0, $workRecords->count());
        $attendedDays = $counts['present'] + $counts['late'];
        $attendanceRate = $scheduledDays > 0 ? round(($attendedDays / $scheduledDays) * 100) : 0;
        $onTimeRate = $attendedDays > 0 ? round(($counts['present'] / $attendedDays) * 100) : 0;
        $score = $this->performanceScore($scheduledDays, $counts, (int) $records->sum('early_leave_minutes'));

        $totalWorkMinutes = (int) $records->sum('work_minutes');
        $avgWorkMinutes = $attendedDays > 0 ? (int) round($totalWorkMinutes / $attendedDays) : 0;
        $approvedOvertimeMinutes = (int) $records->sum('approved_overtime_minutes');
        $totalLateMinutes = (int) $records->sum('late_minutes');
        $totalEarlyLeaveMinutes = (int) $records->sum('early_leave_minutes');

        return view('employee.attendance-performance', [
            'employee' => $employee,
            'month' => $month,
            'monthLabel' => $month->translatedFormat('F Y'),
            'prevMonth' => $month->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $month->copy()->addMonth()->format('Y-m'),
            'days' => $days,
            'records' => $records->sortByDesc('attendance_date')->values(),
            'summary' => [
                'score' => $score,
                'scheduled_days' => $scheduledDays,
                'attended_days' => $attendedDays,
                'attendance_rate' => $attendanceRate,
                'on_time_rate' => $onTimeRate,
                'total_work' => $this->minutesLabel($totalWorkMinutes),
                'avg_work' => $this->minutesLabel($avgWorkMinutes),
                'approved_overtime' => $this->minutesLabel($approvedOvertimeMinutes),
                'late_minutes' => $this->minutesLabel($totalLateMinutes),
                'early_leave_minutes' => $this->minutesLabel($totalEarlyLeaveMinutes),
                'avg_check_in' => $this->averageTime($records->filter(fn (Attendance $row) => $row->check_in_at !== null), 'check_in_at'),
                'avg_check_out' => $this->averageTime($records->filter(fn (Attendance $row) => $row->check_out_at !== null), 'check_out_at'),
            ],
            'counts' => $counts,
            'weekStats' => $this->weekStats($days),
            'statusLabels' => $this->statusLabels(),
        ]);
    }

    private function resolveMonth(Request $request): Carbon
    {
        $raw = trim((string) $request->input('month', ''));

        if (preg_match('/^\d{4}-\d{2}$/', $raw)) {
            return Carbon::createFromFormat('Y-m-d', $raw.'-01', 'Asia/Jakarta')->startOfMonth();
        }

        return now('Asia/Jakarta')->startOfMonth();
    }

    private function calendarDays(Carbon $start, Carbon $end, Carbon $effectiveEnd, $recordsByDate): array
    {
        $days = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $date = $cursor->toDateString();
            $record = $recordsByDate->get($date);
            $status = $record?->status ?: ($cursor->lte($effectiveEnd) ? 'no_record' : 'future');

            $days[] = [
                'date' => $date,
                'day' => $cursor->day,
                'weekday' => $cursor->translatedFormat('D'),
                'week' => $cursor->weekOfMonth,
                'status' => $status,
                'record' => $record,
            ];

            $cursor->addDay();
        }

        return $days;
    }

    private function weekStats(array $days): array
    {
        return collect($days)
            ->groupBy('week')
            ->map(function ($rows, $week) {
                $workRows = $rows->filter(fn ($row) => in_array($row['status'], [
                    Attendance::STATUS_PRESENT,
                    Attendance::STATUS_LATE,
                    Attendance::STATUS_ABSENT,
                    Attendance::STATUS_INCOMPLETE,
                    Attendance::STATUS_LEAVE,
                ], true));
                $attended = $workRows->filter(fn ($row) => in_array($row['status'], [
                    Attendance::STATUS_PRESENT,
                    Attendance::STATUS_LATE,
                ], true))->count();
                $total = $workRows->count();

                return [
                    'label' => 'Minggu '.$week,
                    'total' => $total,
                    'attended' => $attended,
                    'rate' => $total > 0 ? round(($attended / $total) * 100) : 0,
                ];
            })
            ->values()
            ->all();
    }

    private function performanceScore(int $scheduledDays, array $counts, int $earlyLeaveMinutes): int
    {
        if ($scheduledDays <= 0) {
            return 0;
        }

        $penalty = ($counts['late'] * 2)
            + ($counts['absent'] * 8)
            + ($counts['incomplete'] * 5)
            + (int) floor($earlyLeaveMinutes / 30);

        return max(0, min(100, 100 - $penalty));
    }

    private function averageTime($records, string $field): string
    {
        $seconds = $records
            ->map(function (Attendance $row) use ($field) {
                $value = $row->{$field};
                if (!$value) {
                    return null;
                }

                return ((int) $value->format('H') * 3600) + ((int) $value->format('i') * 60);
            })
            ->filter(fn ($value) => $value !== null);

        if ($seconds->isEmpty()) {
            return '-';
        }

        $avg = (int) round($seconds->avg());

        return sprintf('%02d:%02d', intdiv($avg, 3600), intdiv($avg % 3600, 60));
    }

    private function minutesLabel(int $minutes): string
    {
        if ($minutes <= 0) {
            return '0j 0m';
        }

        return intdiv($minutes, 60).'j '.($minutes % 60).'m';
    }

    private function statusLabels(): array
    {
        return [
            Attendance::STATUS_PRESENT => 'Tepat waktu',
            Attendance::STATUS_LATE => 'Terlambat',
            Attendance::STATUS_ABSENT => 'Alpha',
            Attendance::STATUS_INCOMPLETE => 'Belum lengkap',
            Attendance::STATUS_LEAVE => 'Cuti/Izin',
            Attendance::STATUS_HOLIDAY => 'Libur perusahaan',
            Attendance::STATUS_DAY_OFF => 'Libur',
            'no_record' => 'Belum ada data',
            'future' => 'Akan datang',
        ];
    }
}
