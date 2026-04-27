<?php

namespace App\Http\Controllers;

use App\Models\AttendanceDevice;
use App\Support\AttendanceLateNotifier;
use App\Support\AttendanceProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AttendanceFingerprintWebhookController extends Controller
{
    public function __invoke(Request $request, AttendanceProcessor $processor, AttendanceLateNotifier $lateNotifier)
    {
        $secret = (string) config('services.attendance.webhook_secret');
        if ($secret !== '' && !hash_equals($secret, (string) $request->header('X-Attendance-Webhook-Secret'))) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'attendance_device_id' => ['nullable', 'integer', 'exists:attendance_devices,id'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'device_user_id' => ['required', 'string', 'max:100'],
            'scan_at' => ['required', 'date'],
            'verify_type' => ['nullable', 'string', 'max:50'],
            'state' => ['nullable', 'string', 'max:50'],
            'raw_payload' => ['nullable', 'array'],
        ]);

        $device = $this->resolveDevice($validated);
        if (!$device) {
            return response()->json(['message' => 'Device absensi tidak ditemukan'], 422);
        }

        $result = $processor->recordFingerprintScanWithResult(
            $device,
            $validated['device_user_id'],
            Carbon::parse($validated['scan_at']),
            $validated['verify_type'] ?? null,
            $validated['state'] ?? null,
            $validated['raw_payload'] ?? $request->all()
        );
        $rawLog = $result['raw_log'];
        $attendance = $result['attendance']?->loadMissing(['employee', 'shift']);
        $isLateCheckIn = $lateNotifier->shouldNotify($attendance, $rawLog);
        if ($isLateCheckIn) {
            $lateNotifier->notifyTelegramIfLate($attendance, $rawLog);
        }

        return response()->json([
            'ok' => true,
            'raw_log_id' => $rawLog->id,
            'employee_id' => $rawLog->employee_id,
            'attendance_status' => $attendance?->status,
            'late_minutes' => (int) ($attendance?->late_minutes ?? 0),
            'late_notification_sent' => $isLateCheckIn,
        ]);
    }

    private function resolveDevice(array $payload): ?AttendanceDevice
    {
        if (!empty($payload['attendance_device_id'])) {
            return AttendanceDevice::find((int) $payload['attendance_device_id']);
        }

        if (!empty($payload['serial_number'])) {
            return AttendanceDevice::where('serial_number', $payload['serial_number'])->first();
        }

        return null;
    }
}
