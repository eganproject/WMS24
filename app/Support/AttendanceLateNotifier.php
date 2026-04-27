<?php

namespace App\Support;

use App\Models\Attendance;
use App\Models\AttendanceRawLog;
use Illuminate\Support\Carbon;

class AttendanceLateNotifier
{
    public function shouldNotify(?Attendance $attendance, AttendanceRawLog $rawLog): bool
    {
        if (!$attendance || (int) $attendance->late_minutes <= 0 || !$attendance->check_in_at) {
            return false;
        }

        return Carbon::parse($attendance->check_in_at)->equalTo(Carbon::parse($rawLog->scan_at));
    }

    public function message(Attendance $attendance): string
    {
        $employee = $attendance->employee;
        $shift = $attendance->shift;

        return implode("\n", [
            'Notifikasi Absensi Terlambat',
            'Karyawan: '.($employee ? "{$employee->employee_code} - {$employee->name}" : '-'),
            'Tanggal: '.$attendance->attendance_date?->format('Y-m-d'),
            'Jam masuk: '.$attendance->check_in_at?->format('H:i'),
            'Shift: '.($shift?->name ?? '-'),
            'Terlambat: '.(int) $attendance->late_minutes.' menit',
        ]);
    }

    public function notifyTelegramIfLate(?Attendance $attendance, AttendanceRawLog $rawLog): bool
    {
        if (!$this->shouldNotify($attendance, $rawLog)) {
            return false;
        }

        app(TelegramBotService::class)->notifyAllowedChats($this->message($attendance->loadMissing(['employee', 'shift'])));

        return true;
    }
}
