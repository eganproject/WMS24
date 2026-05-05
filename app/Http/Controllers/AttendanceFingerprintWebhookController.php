<?php

namespace App\Http\Controllers;

use App\Models\AttendanceDevice;
use App\Models\AttendanceWebhookLog;
use App\Support\AttendanceLateNotifier;
use App\Support\AttendanceProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Throwable;

class AttendanceFingerprintWebhookController extends Controller
{
    public function __invoke(Request $request, AttendanceProcessor $processor, AttendanceLateNotifier $lateNotifier)
    {
        $secret = (string) config('services.attendance.webhook_secret');
        if ($secret !== '' && !hash_equals($secret, (string) $request->header('X-Attendance-Webhook-Secret'))) {
            $this->logWebhook($request, null, 401, ['message' => 'Unauthorized'], 'unauthorized');
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        try {
            $validated = $request->validate([
                'attendance_device_id' => ['nullable', 'integer', 'exists:attendance_devices,id'],
                'serial_number' => ['nullable', 'string', 'max:100'],
                'device_user_id' => ['required', 'string', 'max:100'],
                'scan_at' => ['required', 'date'],
                'verify_type' => ['nullable', 'string', 'max:50'],
                'state' => ['nullable', 'string', 'max:50'],
                'raw_payload' => ['nullable', 'array'],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $response = ['message' => 'The given data was invalid.', 'errors' => $e->errors()];
            $this->logWebhook($request, null, 422, $response, 'validation_error');
            return response()->json($response, 422);
        }

        $device = $this->resolveDevice($validated);
        if (!$device) {
            $response = ['message' => 'Device absensi tidak ditemukan'];
            $this->logWebhook($request, null, 422, $response, 'device_not_found');
            return response()->json($response, 422);
        }

        try {
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

            $responseBody = [
                'ok' => true,
                'raw_log_id' => $rawLog->id,
                'employee_id' => $rawLog->employee_id,
                'attendance_status' => $attendance?->status,
                'late_minutes' => (int) ($attendance?->late_minutes ?? 0),
                'late_notification_sent' => $isLateCheckIn,
            ];

            $this->logWebhook($request, $device->id, 200, $responseBody, 'success', $rawLog->id);

            return response()->json($responseBody);
        } catch (Throwable $e) {
            $response = ['message' => 'Terjadi kesalahan pada server: ' . $e->getMessage()];
            $this->logWebhook($request, $device->id, 500, $response, 'error');
            throw $e;
        }
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

    private function logWebhook(
        Request $request,
        ?int $deviceId,
        int $httpStatus,
        array $responsePayload,
        string $status,
        ?int $rawLogId = null
    ): void {
        try {
            AttendanceWebhookLog::create([
                'ip_address' => $request->ip(),
                'serial_number' => $request->input('serial_number'),
                'attendance_device_id' => $deviceId,
                'device_user_id' => $request->input('device_user_id'),
                'request_payload' => $request->all(),
                'http_status' => $httpStatus,
                'response_payload' => $responsePayload,
                'status' => $status,
                'raw_log_id' => $rawLogId,
            ]);
        } catch (Throwable) {
            // Jangan sampai gagal logging menghentikan proses utama
        }
    }
}
