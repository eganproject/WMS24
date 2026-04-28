<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeePosition;
use App\Models\EmployeeSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AttendanceReportController extends Controller
{
    public function index()
    {
        return view('admin.reports.attendance.index', [
            'dataUrl' => route('admin.reports.attendance.data'),
            'areas' => Area::query()->orderBy('code')->get(['id', 'code', 'name']),
            'positions' => EmployeePosition::query()->orderBy('name')->get(['id', 'name']),
            'employees' => Employee::query()->orderBy('name')->get(['id', 'employee_code', 'name']),
        ]);
    }

    public function data(Request $request)
    {
        [$from, $to] = $this->dateRange($request);
        $employees = $this->employeeQuery($request)
            ->with([
                'area:id,code,name',
                'positionRelation:id,name',
                'schedules' => fn ($query) => $query
                    ->whereDate('schedule_date', '>=', $from)
                    ->whereDate('schedule_date', '<=', $to),
                'attendances' => fn ($query) => $query
                    ->with('shift:id,name')
                    ->whereDate('attendance_date', '>=', $from)
                    ->whereDate('attendance_date', '<=', $to)
                    ->orderBy('attendance_date'),
            ])
            ->get();

        $rows = $employees
            ->map(fn (Employee $employee) => $this->serializeEmployee($employee, $from, $to))
            ->filter(fn (array $row) => $this->passesReportStatus($row, (string) $request->input('report_status', '')))
            ->values();

        $summary = [
            'employees' => $rows->count(),
            'scheduled_work_days' => (int) $rows->sum('scheduled_work_days'),
            'present_days' => (int) $rows->sum('present_days'),
            'late_days' => (int) $rows->sum('late_days'),
            'absent_days' => (int) $rows->sum('absent_days'),
            'incomplete_days' => (int) $rows->sum('incomplete_days'),
            'leave_days' => (int) $rows->sum('leave_days'),
            'approved_overtime_minutes' => (int) $rows->sum('approved_overtime_minutes'),
            'pending_overtime_minutes' => (int) $rows->sum('pending_overtime_minutes'),
            'attendance_rate' => $this->rate(
                (int) $rows->sum('present_days') + (int) $rows->sum('late_days'),
                (int) $rows->sum('scheduled_work_days')
            ),
        ];

        $start = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', 10);
        $data = $length > 0 ? $rows->slice($start, $length)->values() : $rows;

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $employees->count(),
            'recordsFiltered' => $rows->count(),
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'summary' => $summary,
            'data' => $data,
        ]);
    }

    private function employeeQuery(Request $request)
    {
        $query = Employee::query()
            ->when($request->input('employee_id'), fn ($q, $id) => $q->where('id', $id))
            ->when($request->input('area_id'), fn ($q, $id) => $q->where('area_id', $id))
            ->when($request->input('position_id'), fn ($q, $id) => $q->where('position_id', $id))
            ->when($request->input('employment_status'), fn ($q, $status) => $q->where('employment_status', $status))
            ->orderBy('name');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('employee_code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhereHas('area', fn ($area) => $area
                        ->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%"))
                    ->orWhereHas('positionRelation', fn ($position) => $position->where('name', 'like', "%{$search}%"));
            });
        }

        return $query;
    }

    private function serializeEmployee(Employee $employee, Carbon $from, Carbon $to): array
    {
        $schedulesByDate = $employee->schedules->keyBy(fn (EmployeeSchedule $schedule) => $schedule->schedule_date?->toDateString());
        $attendancesByDate = $employee->attendances->keyBy(fn (Attendance $attendance) => $attendance->attendance_date?->toDateString());

        $scheduledWorkDays = $schedulesByDate->where('schedule_type', EmployeeSchedule::TYPE_WORK)->count();
        $presentDays = 0;
        $lateDays = 0;
        $absentDays = 0;
        $incompleteDays = 0;
        $leaveDays = 0;
        $holidayDays = 0;
        $dayOffDays = 0;
        $workMinutes = 0;
        $calculatedOvertimeMinutes = 0;
        $approvedOvertimeMinutes = 0;
        $pendingOvertimeMinutes = 0;
        $detailRows = [];

        $current = $from->copy();
        while ($current->lte($to)) {
            $dateKey = $current->toDateString();
            $schedule = $schedulesByDate->get($dateKey);
            $attendance = $attendancesByDate->get($dateKey);
            $status = $attendance?->status;

            if ($status === Attendance::STATUS_PRESENT) {
                $presentDays++;
            } elseif ($status === Attendance::STATUS_LATE) {
                $lateDays++;
            } elseif ($status === Attendance::STATUS_ABSENT) {
                $absentDays++;
            } elseif ($status === Attendance::STATUS_INCOMPLETE) {
                $incompleteDays++;
            } elseif ($status === Attendance::STATUS_LEAVE) {
                $leaveDays++;
            } elseif ($status === Attendance::STATUS_HOLIDAY) {
                $holidayDays++;
            } elseif ($status === Attendance::STATUS_DAY_OFF) {
                $dayOffDays++;
            }

            $workMinutes += (int) ($attendance?->work_minutes ?? 0);
            $calculatedOvertimeMinutes += (int) ($attendance?->calculated_overtime_minutes ?? 0);
            $approvedOvertimeMinutes += (int) ($attendance?->approved_overtime_minutes ?? 0);
            if ($attendance?->overtime_status === Attendance::OVERTIME_PENDING) {
                $pendingOvertimeMinutes += (int) $attendance->calculated_overtime_minutes;
            }

            if ($schedule || $attendance) {
                $detailRows[] = [
                    'date' => $dateKey,
                    'schedule_type' => $schedule?->schedule_type ?? '-',
                    'shift' => $attendance?->shift?->name ?? '-',
                    'check_in_at' => $attendance?->check_in_at?->format('H:i') ?? '-',
                    'check_out_at' => $attendance?->check_out_at?->format('H:i') ?? '-',
                    'status' => $status ?? '-',
                    'late_minutes' => (int) ($attendance?->late_minutes ?? 0),
                    'early_leave_minutes' => (int) ($attendance?->early_leave_minutes ?? 0),
                    'work_minutes' => (int) ($attendance?->work_minutes ?? 0),
                    'calculated_overtime_minutes' => (int) ($attendance?->calculated_overtime_minutes ?? 0),
                    'approved_overtime_minutes' => (int) ($attendance?->approved_overtime_minutes ?? 0),
                    'overtime_status' => $attendance?->overtime_status ?? Attendance::OVERTIME_NONE,
                    'note' => $attendance?->note ?: $schedule?->note,
                ];
            }

            $current->addDay();
        }

        $attendedDays = $presentDays + $lateDays;
        $problemDays = $absentDays + $incompleteDays;

        return [
            'employee_id' => $employee->id,
            'employee_code' => $employee->employee_code,
            'employee_name' => $employee->name,
            'employee_label' => "{$employee->employee_code} - {$employee->name}",
            'area' => $employee->area ? "{$employee->area->code} - {$employee->area->name}" : '-',
            'position' => $employee->positionRelation?->name ?? $employee->position ?? '-',
            'employment_status' => $employee->employment_status,
            'scheduled_work_days' => $scheduledWorkDays,
            'present_days' => $presentDays,
            'late_days' => $lateDays,
            'absent_days' => $absentDays,
            'incomplete_days' => $incompleteDays,
            'leave_days' => $leaveDays,
            'holiday_days' => $holidayDays,
            'day_off_days' => $dayOffDays,
            'problem_days' => $problemDays,
            'attendance_rate' => $this->rate($attendedDays, $scheduledWorkDays),
            'punctual_rate' => $this->rate($presentDays, $scheduledWorkDays),
            'work_minutes' => $workMinutes,
            'work_hours' => round($workMinutes / 60, 2),
            'calculated_overtime_minutes' => $calculatedOvertimeMinutes,
            'approved_overtime_minutes' => $approvedOvertimeMinutes,
            'pending_overtime_minutes' => $pendingOvertimeMinutes,
            'detail_rows' => $detailRows,
        ];
    }

    private function passesReportStatus(array $row, string $status): bool
    {
        return match ($status) {
            'has_absent' => $row['absent_days'] > 0,
            'has_late' => $row['late_days'] > 0,
            'has_incomplete' => $row['incomplete_days'] > 0,
            'has_overtime_pending' => $row['pending_overtime_minutes'] > 0,
            'good_attendance' => $row['scheduled_work_days'] > 0 && $row['attendance_rate'] >= 95 && $row['problem_days'] === 0,
            default => true,
        };
    }

    private function rate(int $numerator, int $denominator): float
    {
        if ($denominator <= 0) {
            return 0.0;
        }

        return round(($numerator / $denominator) * 100, 2);
    }

    private function dateRange(Request $request): array
    {
        $from = $request->filled('date_from')
            ? Carbon::parse($request->input('date_from'))->startOfDay()
            : now()->startOfMonth();
        $to = $request->filled('date_to')
            ? Carbon::parse($request->input('date_to'))->startOfDay()
            : now()->endOfMonth()->startOfDay();

        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
    }
}
