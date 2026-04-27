<?php

namespace App\Support;

use App\Models\Attendance;
use App\Models\AttendanceDevice;
use App\Models\AttendanceRawLog;
use App\Models\Employee;
use App\Models\EmployeeFingerprint;
use App\Models\EmployeeLeave;
use App\Models\EmployeeSchedule;
use App\Models\EmployeeScheduleAssignment;
use App\Models\Holiday;
use App\Models\WeeklyScheduleTemplateDay;
use App\Models\WorkShift;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceProcessor
{
    public function recordFingerprintScan(
        AttendanceDevice $device,
        string $deviceUserId,
        Carbon|string $scanAt,
        ?string $verifyType = null,
        ?string $state = null,
        ?array $rawPayload = null
    ): AttendanceRawLog {
        return $this->recordFingerprintScanWithResult($device, $deviceUserId, $scanAt, $verifyType, $state, $rawPayload)['raw_log'];
    }

    public function recordFingerprintScanWithResult(
        AttendanceDevice $device,
        string $deviceUserId,
        Carbon|string $scanAt,
        ?string $verifyType = null,
        ?string $state = null,
        ?array $rawPayload = null
    ): array {
        $scanAt = $scanAt instanceof Carbon ? $scanAt : Carbon::parse($scanAt);
        $employeeId = $this->employeeIdForDeviceUser($device, $deviceUserId);

        return DB::transaction(function () use ($device, $deviceUserId, $scanAt, $verifyType, $state, $rawPayload, $employeeId) {
            $rawLog = AttendanceRawLog::query()->updateOrCreate(
                [
                    'attendance_device_id' => $device->id,
                    'device_user_id' => $deviceUserId,
                    'scan_at' => $scanAt->toDateTimeString(),
                ],
                [
                    'employee_id' => $employeeId,
                    'verify_type' => $verifyType,
                    'state' => $state,
                    'raw_payload' => $rawPayload,
                    'synced_at' => now(),
                ]
            );

            $device->forceFill(['last_synced_at' => now()])->save();

            $attendance = null;
            if ($employeeId) {
                $attendance = $this->rebuildDailyAttendance(Employee::findOrFail($employeeId), $scanAt->toDateString());
            }

            return [
                'raw_log' => $rawLog,
                'attendance' => $attendance,
            ];
        });
    }

    public function rebuildDailyAttendance(Employee $employee, Carbon|string $date): Attendance
    {
        $date = $date instanceof Carbon ? $date->copy()->startOfDay() : Carbon::parse($date)->startOfDay();
        $schedule = $this->resolveSchedule($employee, $date);

        if ($schedule['type'] !== EmployeeSchedule::TYPE_WORK || !$schedule['shift']) {
            return $this->saveDailyAttendance($employee, $date, [
                'work_shift_id' => null,
                'check_in_at' => null,
                'check_out_at' => null,
                'late_minutes' => 0,
                'early_leave_minutes' => 0,
                'work_minutes' => 0,
                'overtime_minutes' => 0,
                'status' => $schedule['status'],
                'note' => $schedule['note'],
                'source' => 'system',
            ]);
        }

        $shift = $schedule['shift'];
        [$windowStart, $windowEnd] = $this->scanWindow($date, $shift);
        $scans = AttendanceRawLog::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('scan_at', [$windowStart, $windowEnd])
            ->orderBy('scan_at')
            ->pluck('scan_at');

        $checkInAt = $scans->first();
        $checkOutAt = $scans->count() > 1 ? $scans->last() : null;
        $plannedStart = $this->shiftDateTime($date, $shift->start_time);
        $plannedEnd = $this->shiftDateTime($date, $shift->end_time, $shift->crosses_midnight);
        $lateCutoff = $plannedStart->copy()->addMinutes((int) $shift->late_tolerance_minutes);
        $checkoutCutoff = $plannedEnd->copy()->subMinutes((int) $shift->checkout_tolerance_minutes);

        $lateMinutes = $checkInAt ? max(0, Carbon::parse($checkInAt)->diffInMinutes($lateCutoff, false) * -1) : 0;
        $earlyLeaveMinutes = $checkOutAt ? max(0, Carbon::parse($checkOutAt)->diffInMinutes($checkoutCutoff, false)) : 0;
        $workMinutes = $checkInAt && $checkOutAt
            ? max(0, Carbon::parse($checkInAt)->diffInMinutes(Carbon::parse($checkOutAt)))
            : 0;
        $workMinutes = max(0, $workMinutes - $this->breakMinutes($shift));
        $overtimeMinutes = $checkOutAt ? max(0, $plannedEnd->diffInMinutes(Carbon::parse($checkOutAt), false)) : 0;

        $status = match (true) {
            !$checkInAt => Attendance::STATUS_ABSENT,
            !$checkOutAt => Attendance::STATUS_INCOMPLETE,
            $lateMinutes > 0 => Attendance::STATUS_LATE,
            default => Attendance::STATUS_PRESENT,
        };

        return $this->saveDailyAttendance($employee, $date, [
            'work_shift_id' => $shift->id,
            'check_in_at' => $checkInAt,
            'check_out_at' => $checkOutAt,
            'late_minutes' => $lateMinutes,
            'early_leave_minutes' => $earlyLeaveMinutes,
            'work_minutes' => $workMinutes,
            'overtime_minutes' => $overtimeMinutes,
            'status' => $status,
            'note' => $schedule['note'],
            'source' => 'fingerprint',
        ]);
    }

    public function resolveSchedule(Employee $employee, Carbon $date): array
    {
        $manualSchedule = EmployeeSchedule::query()
            ->with('shift')
            ->where('employee_id', $employee->id)
            ->whereDate('schedule_date', $date)
            ->first();

        if ($manualSchedule) {
            return [
                'type' => $manualSchedule->schedule_type,
                'status' => $this->statusForScheduleType($manualSchedule->schedule_type),
                'shift' => $manualSchedule->shift,
                'note' => $manualSchedule->note,
            ];
        }

        $leave = EmployeeLeave::query()
            ->where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->first();

        if ($leave) {
            return [
                'type' => EmployeeSchedule::TYPE_LEAVE,
                'status' => Attendance::STATUS_LEAVE,
                'shift' => null,
                'note' => 'Cuti/Izin: '.$leave->leave_type,
            ];
        }

        $holiday = Holiday::query()->whereDate('holiday_date', $date)->first();
        if ($holiday) {
            return [
                'type' => EmployeeSchedule::TYPE_HOLIDAY,
                'status' => Attendance::STATUS_HOLIDAY,
                'shift' => null,
                'note' => $holiday->name,
            ];
        }

        $assignment = EmployeeScheduleAssignment::query()
            ->where('employee_id', $employee->id)
            ->whereDate('effective_from', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->whereNull('effective_until')
                    ->orWhereDate('effective_until', '>=', $date);
            })
            ->latest('effective_from')
            ->first();

        if ($assignment) {
            $templateDay = WeeklyScheduleTemplateDay::query()
                ->with('shift')
                ->where('weekly_schedule_template_id', $assignment->weekly_schedule_template_id)
                ->where('day_of_week', (int) $date->dayOfWeekIso)
                ->first();

            if ($templateDay) {
                return [
                    'type' => $templateDay->schedule_type,
                    'status' => $this->statusForScheduleType($templateDay->schedule_type),
                    'shift' => $templateDay->shift,
                    'note' => null,
                ];
            }
        }

        return [
            'type' => EmployeeSchedule::TYPE_DAY_OFF,
            'status' => Attendance::STATUS_DAY_OFF,
            'shift' => null,
            'note' => 'Tidak ada jadwal kerja',
        ];
    }

    private function employeeIdForDeviceUser(AttendanceDevice $device, string $deviceUserId): ?int
    {
        return EmployeeFingerprint::query()
            ->where('device_user_id', $deviceUserId)
            ->where('is_active', true)
            ->where(function ($query) use ($device) {
                $query->where('attendance_device_id', $device->id)
                    ->orWhereNull('attendance_device_id');
            })
            ->orderByRaw('attendance_device_id is null')
            ->value('employee_id');
    }

    private function saveDailyAttendance(Employee $employee, Carbon $date, array $attributes): Attendance
    {
        $attendance = Attendance::query()
            ->where('employee_id', $employee->id)
            ->whereDate('attendance_date', $date)
            ->first();

        if (!$attendance) {
            $attendance = new Attendance([
                'employee_id' => $employee->id,
                'attendance_date' => $date->toDateString(),
            ]);
        }

        $attendance->fill($attributes);
        $attendance->save();

        return $attendance;
    }

    private function statusForScheduleType(string $scheduleType): string
    {
        return match ($scheduleType) {
            EmployeeSchedule::TYPE_HOLIDAY => Attendance::STATUS_HOLIDAY,
            EmployeeSchedule::TYPE_LEAVE => Attendance::STATUS_LEAVE,
            EmployeeSchedule::TYPE_DAY_OFF => Attendance::STATUS_DAY_OFF,
            default => Attendance::STATUS_ABSENT,
        };
    }

    private function scanWindow(Carbon $date, WorkShift $shift): array
    {
        $start = $this->shiftDateTime($date, $shift->start_time)->subHours(6);
        $end = $this->shiftDateTime($date, $shift->end_time, $shift->crosses_midnight)->addHours(6);

        return [$start, $end];
    }

    private function shiftDateTime(Carbon $date, string $time, bool $nextDay = false): Carbon
    {
        $value = $date->copy()->setTimeFromTimeString($time);
        return $nextDay ? $value->addDay() : $value;
    }

    private function breakMinutes(WorkShift $shift): int
    {
        if (!$shift->break_start_time || !$shift->break_end_time) {
            return 0;
        }

        $start = Carbon::parse($shift->break_start_time);
        $end = Carbon::parse($shift->break_end_time);

        return max(0, $start->diffInMinutes($end, false));
    }
}
