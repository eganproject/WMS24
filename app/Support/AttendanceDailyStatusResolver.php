<?php

namespace App\Support;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeLeave;
use App\Models\EmployeeSchedule;
use App\Models\Holiday;
use Illuminate\Support\Carbon;

class AttendanceDailyStatusResolver
{
    public const STATUS_NOT_CHECKED_IN = 'not_checked_in';
    public const STATUS_UNSCHEDULED = 'unscheduled';

    public function resolve(
        Employee $employee,
        Carbon|string $date,
        ?EmployeeSchedule $schedule = null,
        ?Attendance $attendance = null,
        ?EmployeeLeave $leave = null,
        ?Holiday $holiday = null
    ): array {
        $date = $date instanceof Carbon ? $date->copy()->startOfDay() : Carbon::parse($date)->startOfDay();
        $scheduleType = $this->scheduleType($schedule, $attendance, $leave, $holiday);
        $shift = $scheduleType === EmployeeSchedule::TYPE_WORK
            ? ($attendance?->shift ?? $schedule?->shift)
            : null;
        $status = $this->status($date, $scheduleType, $shift, $attendance, $leave, $holiday);

        return [
            'employee_id' => $employee->id,
            'date' => $date->toDateString(),
            'schedule_type' => $scheduleType,
            'schedule_label' => $this->scheduleLabel($scheduleType, $holiday),
            'status' => $status,
            'status_label' => $status === Attendance::STATUS_HOLIDAY
                ? $this->scheduleLabel($scheduleType, $holiday)
                : $this->statusLabel($status),
            'holiday_type' => $holiday?->type,
            'is_work_day' => in_array($status, [
                Attendance::STATUS_PRESENT,
                Attendance::STATUS_LATE,
                Attendance::STATUS_ABSENT,
                Attendance::STATUS_INCOMPLETE,
                self::STATUS_NOT_CHECKED_IN,
            ], true),
            'is_completed_work_status' => in_array($status, [
                Attendance::STATUS_PRESENT,
                Attendance::STATUS_LATE,
                Attendance::STATUS_ABSENT,
                Attendance::STATUS_INCOMPLETE,
            ], true),
            'is_present' => in_array($status, [
                Attendance::STATUS_PRESENT,
                Attendance::STATUS_LATE,
            ], true),
            'shift' => $shift,
            'attendance' => $attendance,
            'note' => $attendance?->note
                ?: ($leave ? 'Cuti/Izin: '.$leave->leave_type : null)
                ?: $holiday?->name
                ?: $schedule?->note,
        ];
    }

    private function scheduleType(
        ?EmployeeSchedule $schedule,
        ?Attendance $attendance,
        ?EmployeeLeave $leave,
        ?Holiday $holiday
    ): string {
        if ($leave) {
            return EmployeeSchedule::TYPE_LEAVE;
        }

        if ($holiday) {
            return EmployeeSchedule::TYPE_HOLIDAY;
        }

        return match ($attendance?->status) {
            Attendance::STATUS_LEAVE => EmployeeSchedule::TYPE_LEAVE,
            Attendance::STATUS_HOLIDAY => EmployeeSchedule::TYPE_HOLIDAY,
            Attendance::STATUS_DAY_OFF => EmployeeSchedule::TYPE_DAY_OFF,
            default => $schedule?->schedule_type
                ?? ($attendance ? EmployeeSchedule::TYPE_WORK : self::STATUS_UNSCHEDULED),
        };
    }

    private function status(
        Carbon $date,
        string $scheduleType,
        mixed $shift,
        ?Attendance $attendance,
        ?EmployeeLeave $leave,
        ?Holiday $holiday
    ): string {
        if ($leave) {
            return Attendance::STATUS_LEAVE;
        }

        if ($holiday) {
            return Attendance::STATUS_HOLIDAY;
        }

        if ($scheduleType === EmployeeSchedule::TYPE_DAY_OFF) {
            return Attendance::STATUS_DAY_OFF;
        }

        if ($scheduleType === EmployeeSchedule::TYPE_LEAVE) {
            return Attendance::STATUS_LEAVE;
        }

        if ($scheduleType === EmployeeSchedule::TYPE_HOLIDAY) {
            return Attendance::STATUS_HOLIDAY;
        }

        if ($attendance?->status) {
            return $attendance->status;
        }

        if ($scheduleType !== EmployeeSchedule::TYPE_WORK) {
            return self::STATUS_UNSCHEDULED;
        }

        if (!$shift) {
            return self::STATUS_NOT_CHECKED_IN;
        }

        $lateCutoff = $date->copy()
            ->setTimeFromTimeString((string) $shift->start_time)
            ->addMinutes((int) $shift->late_tolerance_minutes);

        if ($date->isFuture() || ($date->isToday() && now()->lessThanOrEqualTo($lateCutoff))) {
            return self::STATUS_NOT_CHECKED_IN;
        }

        return Attendance::STATUS_ABSENT;
    }

    public function scheduleLabel(string $scheduleType, ?Holiday $holiday = null): string
    {
        if ($scheduleType === EmployeeSchedule::TYPE_HOLIDAY && $holiday) {
            return $holiday->type === 'national' ? 'Libur Nasional' : 'Libur Perusahaan';
        }

        return match ($scheduleType) {
            EmployeeSchedule::TYPE_WORK => 'Masuk',
            EmployeeSchedule::TYPE_DAY_OFF => 'Libur',
            EmployeeSchedule::TYPE_HOLIDAY => 'Libur Perusahaan',
            EmployeeSchedule::TYPE_LEAVE => 'Cuti/Izin',
            self::STATUS_UNSCHEDULED => 'Tidak Ada Jadwal',
            default => $scheduleType,
        };
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            Attendance::STATUS_PRESENT => 'Hadir',
            Attendance::STATUS_LATE => 'Terlambat',
            Attendance::STATUS_ABSENT => 'Alfa',
            Attendance::STATUS_INCOMPLETE => 'Belum Check-out',
            Attendance::STATUS_LEAVE => 'Cuti/Izin',
            Attendance::STATUS_HOLIDAY => 'Libur Perusahaan',
            Attendance::STATUS_DAY_OFF => 'Libur',
            self::STATUS_NOT_CHECKED_IN => 'Belum Check-in',
            self::STATUS_UNSCHEDULED => 'Tidak Ada Jadwal',
            default => $status,
        };
    }
}
