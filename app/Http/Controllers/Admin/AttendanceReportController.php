<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Exports\AttendanceReportExport;
use App\Models\Area;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeLeave;
use App\Models\EmployeePosition;
use App\Models\EmployeeSchedule;
use App\Models\Holiday;
use App\Support\AttendanceDailyStatusResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class AttendanceReportController extends Controller
{
    public function index()
    {
        return view('admin.reports.attendance.index', [
            'dataUrl' => route('admin.reports.attendance.data'),
            'exportUrl' => route('admin.reports.attendance.export'),
            'areas' => Area::query()->orderBy('code')->get(['id', 'code', 'name']),
            'positions' => EmployeePosition::query()->orderBy('name')->get(['id', 'name']),
            'employees' => Employee::query()->orderBy('name')->get(['id', 'employee_code', 'name', 'employment_status']),
        ]);
    }

    public function data(Request $request)
    {
        $request->validate([
            'employment_status' => ['nullable', Rule::in([
                Employee::STATUS_ACTIVE,
                Employee::STATUS_INACTIVE,
                'all',
            ])],
        ]);

        $report = $this->buildReport($request);
        $rows = $report['rows'];
        $summary = $report['summary'];
        $period = $report['period'];
        $recordsTotal = $report['records_total'];

        $start = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', 10);
        $data = $length > 0 ? $rows->slice($start, $length)->values() : $rows;

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $rows->count(),
            'period' => $period,
            'summary' => $summary,
            'data' => $data,
        ]);
    }

    public function export(Request $request)
    {
        $request->validate([
            'employment_status' => ['nullable', Rule::in([
                Employee::STATUS_ACTIVE,
                Employee::STATUS_INACTIVE,
                'all',
            ])],
        ]);

        $report = $this->buildReport($request);
        $filename = 'laporan_absensi_'.$report['period']['from'].'_'.$report['period']['to'].'_'.now()->format('His').'.xlsx';

        return Excel::download(
            new AttendanceReportExport($report['rows'], $report['summary'], $report['period']),
            $filename
        );
    }

    private function buildReport(Request $request): array
    {
        [$from, $to] = $this->dateRange($request);
        $employees = $this->employeeQuery($request)
            ->with([
                'area:id,code,name',
                'positionRelation:id,name',
                'schedules' => fn ($query) => $query
                    ->with('shift:id,name')
                    ->whereDate('schedule_date', '>=', $from)
                    ->whereDate('schedule_date', '<=', $to),
                'attendances' => fn ($query) => $query
                    ->with('shift:id,name')
                    ->whereDate('attendance_date', '>=', $from)
                    ->whereDate('attendance_date', '<=', $to)
                    ->orderBy('attendance_date'),
            ])
            ->get();

        $holidaysByDate = Holiday::query()
            ->whereDate('holiday_date', '>=', $from)
            ->whereDate('holiday_date', '<=', $to)
            ->get()
            ->keyBy(fn (Holiday $holiday) => $holiday->holiday_date?->toDateString());
        $leavesByEmployeeDate = $this->approvedLeavesByEmployeeDate($employees->pluck('id')->all(), $from, $to);

        $rows = $employees
            ->map(fn (Employee $employee) => $this->serializeEmployee($employee, $from, $to, $leavesByEmployeeDate, $holidaysByDate))
            ->filter(fn (array $row) => $this->passesReportStatus($row, (string) $request->input('report_status', '')))
            ->values();

        $summary = [
            'employees' => $rows->count(),
            'scheduled_work_days' => (int) $rows->sum('scheduled_work_days'),
            'present_days' => (int) $rows->sum('present_days'),
            'late_days' => (int) $rows->sum('late_days'),
            'absent_days' => (int) $rows->sum('absent_days'),
            'incomplete_days' => (int) $rows->sum('incomplete_days'),
            'not_checked_in_days' => (int) $rows->sum('not_checked_in_days'),
            'leave_days' => (int) $rows->sum('leave_days'),
            'holiday_days' => (int) $rows->sum('holiday_days'),
            'day_off_days' => (int) $rows->sum('day_off_days'),
            'non_work_days' => (int) $rows->sum('non_work_days'),
            'approved_overtime_minutes' => (int) $rows->sum('approved_overtime_minutes'),
            'pending_overtime_minutes' => (int) $rows->sum('pending_overtime_minutes'),
            'attendance_rate' => $this->rate(
                (int) $rows->sum('present_days') + (int) $rows->sum('late_days'),
                (int) $rows->sum('scheduled_work_days')
            ),
        ];

        return [
            'rows' => $rows,
            'summary' => $summary,
            'records_total' => $employees->count(),
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
        ];
    }

    private function employeeQuery(Request $request)
    {
        $employmentStatus = $request->input('employment_status', Employee::STATUS_ACTIVE);
        $query = Employee::query()
            ->when($request->input('employee_id'), fn ($q, $id) => $q->where('id', $id))
            ->when($request->input('area_id'), fn ($q, $id) => $q->where('area_id', $id))
            ->when($request->input('position_id'), fn ($q, $id) => $q->where('position_id', $id))
            ->when($employmentStatus !== 'all', fn ($q) => $q->where('employment_status', $employmentStatus))
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

    private function serializeEmployee(Employee $employee, Carbon $from, Carbon $to, array $leavesByEmployeeDate, $holidaysByDate): array
    {
        $schedulesByDate = $employee->schedules->keyBy(fn (EmployeeSchedule $schedule) => $schedule->schedule_date?->toDateString());
        $attendancesByDate = $employee->attendances->keyBy(fn (Attendance $attendance) => $attendance->attendance_date?->toDateString());
        $resolver = app(AttendanceDailyStatusResolver::class);

        $scheduledWorkDays = 0;
        $presentDays = 0;
        $lateDays = 0;
        $absentDays = 0;
        $incompleteDays = 0;
        $notCheckedInDays = 0;
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
            $daily = $resolver->resolve(
                $employee,
                $current,
                $schedule,
                $attendance,
                $leavesByEmployeeDate[$employee->id][$dateKey] ?? null,
                $holidaysByDate->get($dateKey)
            );
            $effectiveScheduleType = $daily['schedule_type'];
            $reportStatus = $daily['status'];

            if ($daily['is_work_day']) {
                $scheduledWorkDays++;
            }

            if ($effectiveScheduleType === EmployeeSchedule::TYPE_LEAVE) {
                $leaveDays++;
            } elseif ($effectiveScheduleType === EmployeeSchedule::TYPE_HOLIDAY) {
                $holidayDays++;
            } elseif ($effectiveScheduleType === EmployeeSchedule::TYPE_DAY_OFF) {
                $dayOffDays++;
            }

            if ($reportStatus === Attendance::STATUS_PRESENT) {
                $presentDays++;
            } elseif ($reportStatus === Attendance::STATUS_LATE) {
                $lateDays++;
            } elseif ($reportStatus === Attendance::STATUS_ABSENT) {
                $absentDays++;
            } elseif ($reportStatus === Attendance::STATUS_INCOMPLETE) {
                $incompleteDays++;
            } elseif ($reportStatus === AttendanceDailyStatusResolver::STATUS_NOT_CHECKED_IN) {
                $notCheckedInDays++;
            }

            $workMinutes += (int) ($attendance?->work_minutes ?? 0);
            $calculatedOvertimeMinutes += (int) ($attendance?->calculated_overtime_minutes ?? 0);
            $approvedOvertimeMinutes += (int) ($attendance?->approved_overtime_minutes ?? 0);
            if ($attendance?->overtime_status === Attendance::OVERTIME_PENDING) {
                $pendingOvertimeMinutes += (int) $attendance->calculated_overtime_minutes;
            }

            if ($daily['schedule_type'] !== AttendanceDailyStatusResolver::STATUS_UNSCHEDULED || $attendance) {
                $detailRows[] = [
                    'date' => $dateKey,
                    'schedule_type' => $effectiveScheduleType,
                    'schedule_label' => $daily['schedule_label'],
                    'shift' => $daily['shift']?->name ?? '-',
                    'check_in_at' => $attendance?->check_in_at?->format('H:i') ?? '-',
                    'check_out_at' => $attendance?->check_out_at?->format('H:i') ?? '-',
                    'status' => $reportStatus,
                    'status_label' => $daily['status_label'],
                    'holiday_type' => $daily['holiday_type'],
                    'late_minutes' => (int) ($attendance?->late_minutes ?? 0),
                    'early_leave_minutes' => (int) ($attendance?->early_leave_minutes ?? 0),
                    'work_minutes' => (int) ($attendance?->work_minutes ?? 0),
                    'calculated_overtime_minutes' => (int) ($attendance?->calculated_overtime_minutes ?? 0),
                    'approved_overtime_minutes' => (int) ($attendance?->approved_overtime_minutes ?? 0),
                    'overtime_status' => $attendance?->overtime_status ?? Attendance::OVERTIME_NONE,
                    'note' => $daily['note'],
                ];
            }

            $current->addDay();
        }

        $attendedDays = $presentDays + $lateDays;
        $problemDays = $absentDays + $incompleteDays;
        $nonWorkDays = $holidayDays + $dayOffDays;

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
            'not_checked_in_days' => $notCheckedInDays,
            'leave_days' => $leaveDays,
            'holiday_days' => $holidayDays,
            'day_off_days' => $dayOffDays,
            'non_work_days' => $nonWorkDays,
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

    private function approvedLeavesByEmployeeDate(array $employeeIds, Carbon $from, Carbon $to): array
    {
        if (empty($employeeIds)) {
            return [];
        }

        $leavesByEmployeeDate = [];
        EmployeeLeave::query()
            ->whereIn('employee_id', $employeeIds)
            ->where('status', EmployeeLeave::STATUS_APPROVED)
            ->whereDate('start_date', '<=', $to)
            ->whereDate('end_date', '>=', $from)
            ->get()
            ->each(function (EmployeeLeave $leave) use (&$leavesByEmployeeDate, $from, $to) {
                $current = $leave->start_date->copy()->max($from);
                $until = $leave->end_date->copy()->min($to);

                while ($current->lte($until)) {
                    $leavesByEmployeeDate[$leave->employee_id][$current->toDateString()] = $leave;
                    $current->addDay();
                }
            });

        return $leavesByEmployeeDate;
    }

    private function dateRange(Request $request): array
    {
        $from = $request->filled('date_from')
            ? Carbon::parse($request->input('date_from'))->startOfDay()
            : now()->startOfMonth();
        $to = $request->filled('date_to')
            ? Carbon::parse($request->input('date_to'))->startOfDay()
            : now()->startOfDay();

        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
    }
}
