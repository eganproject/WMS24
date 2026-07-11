<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeLeave;
use App\Models\EmployeeSchedule;
use App\Models\Holiday;
use App\Support\AttendanceDailyStatusResolver;
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
            ->with('shift:id,name,start_time,end_time,late_tolerance_minutes')
            ->where('employee_id', $employee->id)
            ->whereBetween('attendance_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('attendance_date')
            ->get();
        $schedules = EmployeeSchedule::query()
            ->with('shift:id,name,start_time,end_time,late_tolerance_minutes')
            ->where('employee_id', $employee->id)
            ->whereBetween('schedule_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('schedule_date')
            ->get();
        $leaves = EmployeeLeave::query()
            ->where('employee_id', $employee->id)
            ->where('status', EmployeeLeave::STATUS_APPROVED)
            ->whereDate('start_date', '<=', $end)
            ->whereDate('end_date', '>=', $start)
            ->get();
        $holidaysByDate = Holiday::query()
            ->whereDate('holiday_date', '>=', $start)
            ->whereDate('holiday_date', '<=', $end)
            ->get()
            ->keyBy(fn (Holiday $holiday) => $holiday->holiday_date?->toDateString());

        $recordsByDate = $records->keyBy(fn (Attendance $row) => $row->attendance_date?->toDateString());
        $schedulesByDate = $schedules->keyBy(fn (EmployeeSchedule $row) => $row->schedule_date?->toDateString());
        $leavesByDate = $this->leavesByDate($leaves, $start, $end);
        $days = $this->calendarDays($employee, $start, $end, $effectiveEnd, $recordsByDate, $schedulesByDate, $leavesByDate, $holidaysByDate);
        $effectiveStatuses = collect($days)
            ->filter(fn (array $day) => $day['date'] <= $effectiveEnd->toDateString())
            ->pluck('status');

        $counts = [
            'present' => $effectiveStatuses->filter(fn ($status) => $status === Attendance::STATUS_PRESENT)->count(),
            'late' => $effectiveStatuses->filter(fn ($status) => $status === Attendance::STATUS_LATE)->count(),
            'absent' => $effectiveStatuses->filter(fn ($status) => $status === Attendance::STATUS_ABSENT)->count(),
            'incomplete' => $effectiveStatuses->filter(fn ($status) => $status === Attendance::STATUS_INCOMPLETE)->count(),
            'not_checked_in' => $effectiveStatuses->filter(fn ($status) => $status === AttendanceDailyStatusResolver::STATUS_NOT_CHECKED_IN)->count(),
            'leave' => $effectiveStatuses->filter(fn ($status) => $status === Attendance::STATUS_LEAVE)->count(),
            'holiday' => $effectiveStatuses->filter(fn ($status) => $status === Attendance::STATUS_HOLIDAY)->count(),
            'day_off' => $effectiveStatuses->filter(fn ($status) => $status === Attendance::STATUS_DAY_OFF)->count(),
        ];

        $scheduledDays = collect($days)
            ->filter(fn (array $day) => $day['date'] <= $effectiveEnd->toDateString() && $day['is_work_day'])
            ->count();
        $attendedDays = $counts['present'] + $counts['late'];
        $attendanceRate = $scheduledDays > 0 ? round(($attendedDays / $scheduledDays) * 100) : 0;
        $onTimeRate = $scheduledDays > 0 ? round(($counts['present'] / $scheduledDays) * 100) : 0;
        $effectiveRecords = $records->filter(
            fn (Attendance $record) => $record->attendance_date?->toDateString() <= $effectiveEnd->toDateString()
        );
        $historyDays = collect($days)
            ->filter(fn (array $day) => $day['date'] <= $effectiveEnd->toDateString())
            ->reject(fn (array $day) => $day['status'] === AttendanceDailyStatusResolver::STATUS_UNSCHEDULED)
            ->sortByDesc('date')
<<<<<<< HEAD
            ->map(function (array $day): array {
                $date = Carbon::parse($day['date']);
                $workMinutes = (int) $day['work_minutes'];
                $overtimeMinutes = (int) $day['approved_overtime_minutes'];

                return array_merge($day, [
                    'date_day' => $date->format('d'),
                    'date_month' => $date->translatedFormat('M'),
                    'shift_name' => $day['shift']?->name ?? '-',
                    'check_in_label' => $day['check_in_at']?->format('H:i') ?? '-',
                    'check_out_label' => $day['check_out_at']?->format('H:i') ?? '-',
                    'work_label' => intdiv($workMinutes, 60).'j '.($workMinutes % 60).'m',
                    'late_label' => $day['late_minutes'] > 0 ? ' · Telat '.$day['late_minutes'].'m' : '',
                    'overtime_label' => $overtimeMinutes > 0
                        ? ' · Lembur '.intdiv($overtimeMinutes, 60).'j '.($overtimeMinutes % 60).'m'
                        : '',
                ]);
            })
            ->values();
        $score = $this->performanceScore($scheduledDays, $counts, (int) $effectiveRecords->sum('early_leave_minutes'));

=======
            ->values();
        $score = $this->performanceScore($scheduledDays, $counts, (int) $effectiveRecords->sum('early_leave_minutes'));

>>>>>>> c8f6cc8376c2c2adb462f692693f6cb9fc42c4bc
        $totalWorkMinutes = (int) $effectiveRecords->sum('work_minutes');
        $avgWorkMinutes = $attendedDays > 0 ? (int) round($totalWorkMinutes / $attendedDays) : 0;
        $approvedOvertimeMinutes = (int) $effectiveRecords->sum('approved_overtime_minutes');
        $totalLateMinutes = (int) $effectiveRecords->sum('late_minutes');
        $totalEarlyLeaveMinutes = (int) $effectiveRecords->sum('early_leave_minutes');

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
                'avg_check_in' => $this->averageTime($effectiveRecords->filter(fn (Attendance $row) => $row->check_in_at !== null), 'check_in_at'),
                'avg_check_out' => $this->averageTime($effectiveRecords->filter(fn (Attendance $row) => $row->check_out_at !== null), 'check_out_at'),
            ],
            'counts' => $counts,
            'weekStats' => $this->weekStats($days),
            'statusLabels' => $this->statusLabels(),
            'historyDays' => $historyDays,
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

    private function calendarDays(Employee $employee, Carbon $start, Carbon $end, Carbon $effectiveEnd, $recordsByDate, $schedulesByDate, array $leavesByDate, $holidaysByDate): array
    {
        $days = [];
        $cursor = $start->copy();
        $resolver = app(AttendanceDailyStatusResolver::class);

        while ($cursor->lte($end)) {
            $date = $cursor->toDateString();
            $record = $recordsByDate->get($date);
            $schedule = $schedulesByDate->get($date);
            $daily = $resolver->resolve(
                $employee,
                $cursor,
                $schedule,
                $record,
                $leavesByDate[$date] ?? null,
                $holidaysByDate->get($date)
            );

            $status = $daily['status'];
            if ($cursor->gt($effectiveEnd) && !$record && !in_array($status, [
                Attendance::STATUS_LEAVE,
                Attendance::STATUS_HOLIDAY,
                Attendance::STATUS_DAY_OFF,
            ], true)) {
                $status = 'future';
            }

            $days[] = [
                'date' => $date,
                'day' => $cursor->day,
                'weekday' => $cursor->translatedFormat('D'),
                'week' => $cursor->weekOfMonth,
                'status' => $status,
                'is_work_day' => $daily['is_work_day'],
                'record' => $record,
                'schedule' => $schedule,
                'shift' => $daily['shift'],
                'check_in_at' => $record?->check_in_at,
                'check_out_at' => $record?->check_out_at,
                'late_minutes' => (int) ($record?->late_minutes ?? 0),
                'work_minutes' => (int) ($record?->work_minutes ?? 0),
                'approved_overtime_minutes' => (int) ($record?->approved_overtime_minutes ?? 0),
                'note' => $daily['note'],
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
                $workRows = $rows->filter(fn ($row) => $row['is_work_day'] && $row['status'] !== 'future');
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
            Attendance::STATUS_HOLIDAY => 'Libur nasional/perusahaan',
            Attendance::STATUS_DAY_OFF => 'Libur',
            AttendanceDailyStatusResolver::STATUS_NOT_CHECKED_IN => 'Belum Check-in',
            AttendanceDailyStatusResolver::STATUS_UNSCHEDULED => 'Tidak Ada Jadwal',
            'future' => 'Akan datang',
        ];
    }

    private function leavesByDate($leaves, Carbon $start, Carbon $end): array
    {
        $leavesByDate = [];
        $leaves->each(function (EmployeeLeave $leave) use (&$leavesByDate, $start, $end) {
            $current = $leave->start_date->copy()->max($start);
            $until = $leave->end_date->copy()->min($end);

            while ($current->lte($until)) {
                $leavesByDate[$current->toDateString()] = $leave;
                $current->addDay();
            }
        });

        return $leavesByDate;
    }
}
