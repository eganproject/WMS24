<?php

namespace App\Http\Controllers;

use App\Models\AttendanceDevice;
use App\Models\AttendanceRawLog;
use App\Models\AttendanceWebhookLog;
use App\Support\AttendanceLateNotifier;
use App\Support\AttendanceProcessor;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Handles the ZKTeco/Solution ADMS push protocol.
 *
 * Endpoints called by the machine:
 *   GET  /iclock/cdata?SN=xxx&options=all   → initialization / heartbeat
 *   POST /iclock/cdata?SN=xxx&table=ATTLOG  → attendance data push
 *   GET  /iclock/getrequest?SN=xxx          → device polls for server commands
 *   POST /iclock/devicecmd?SN=xxx           → device returns command result
 */
class AttendanceAdmsController extends Controller
{
    /**
     * GET  /iclock/cdata  — device initialization or heartbeat
     * POST /iclock/cdata  — device pushes attendance / operation data
     */
    public function cdata(Request $request, AttendanceProcessor $processor, AttendanceLateNotifier $lateNotifier): Response
    {
        $sn = (string) $request->query('SN', '');

        if ($request->isMethod('GET')) {
            return $this->handleInit($request, $sn);
        }

        // POST
        $table = strtoupper((string) $request->query('table', ''));

        return match ($table) {
            'ATTLOG' => $this->handleAttLog($request, $sn, $processor, $lateNotifier),
            default  => $this->handleUnsupportedTable($request, $sn, $table),
        };
    }

    /**
     * GET /iclock/getrequest — device polls for pending server commands.
     * Return "OK" to indicate no pending commands.
     */
    public function getrequest(Request $request): Response
    {
        $sn = (string) $request->query('SN', '');
        $device = AttendanceDevice::where('serial_number', $sn)->first();

        $this->logAdms($request, $device, [
            'source' => 'adms',
            'type' => 'getrequest',
            'method' => $request->method(),
            'path' => $request->path(),
            'sn' => $sn,
            'query' => $request->query(),
        ], 200, 'command_poll');

        return $this->plainOk();
    }

    /**
     * POST /iclock/devicecmd — device reports command execution result.
     */
    public function devicecmd(Request $request): Response
    {
        $sn = (string) $request->query('SN', '');
        $device = AttendanceDevice::where('serial_number', $sn)->first();

        $this->logAdms($request, $device, [
            'source' => 'adms',
            'type' => 'devicecmd',
            'method' => $request->method(),
            'path' => $request->path(),
            'sn' => $sn,
            'query' => $request->query(),
            'raw_body' => $request->getContent(),
        ], 200, 'device_command');

        return $this->plainOk();
    }

    // -------------------------------------------------------------------------

    private function handleInit(Request $request, string $sn): Response
    {
        $device = AttendanceDevice::where('serial_number', $sn)->first();

        // Only log initialization (not every heartbeat poll) when device is identified
        // or when an unknown device tries to connect (for debugging).
        $this->logAdms($request, $device, [
            'source' => 'adms',
            'type' => 'init',
            'method' => $request->method(),
            'path' => $request->path(),
            'sn' => $sn,
            'query' => $request->query(),
        ], 200, 'heartbeat');

        // Honour the stamp the machine already carries; fall back to our DB stamp.
        // Never return 'None' if we have a stamp — that resets the machine and causes it
        // to re-send every stored log, which rebuilds all historical attendance records.
        $machineStamp = $request->query('ATTLOGStamp');
        if ($machineStamp && $machineStamp !== 'None' && is_numeric($machineStamp) && (int) $machineStamp > 0) {
            $attlogStamp = (int) $machineStamp;
        } else {
            $lastScanAt = $device
                ? AttendanceRawLog::where('attendance_device_id', $device->id)
                    ->latest('scan_at')
                    ->value('scan_at')
                : null;
            $attlogStamp = $lastScanAt ? Carbon::parse($lastScanAt)->timestamp : 'None';
        }

        $config = implode("\r\n", [
            "GET OPTION FROM: {$sn}",
            "ATTLOGStamp={$attlogStamp}",
            'OPERLOGStamp=9999',
            'ATTPHOTOStamp=None',
            'ErrorDelay=30',
            'Delay=10',
            'TransTimes=00:00;06:00;12:00;18:00',
            'TransInterval=1',
            'TransFlag=TransData AttLog',
            'TimeZone=7',
            'Realtime=1',
            'Encrypt=None',
        ]) . "\r\n";

        return response($config, 200)
            ->header('Content-Type', 'text/plain')
            ->header('Connection', 'close');
    }

    private function handleAttLog(
        Request $request,
        string $sn,
        AttendanceProcessor $processor,
        AttendanceLateNotifier $lateNotifier
    ): Response {
        $device = AttendanceDevice::where('serial_number', $sn)->first();

        if (!$device) {
            $this->logAdms($request, null, [
                'source' => 'adms',
                'type' => 'attlog',
                'method' => $request->method(),
                'path' => $request->path(),
                'sn' => $sn,
                'query' => $request->query(),
                'raw_body' => $request->getContent(),
            ], 200, 'device_not_found');

            // ADMS mesin mengharapkan HTTP 200 meskipun gagal; error di body
            return response("ERROR: Device SN={$sn} tidak terdaftar di sistem\r\n", 200)
                ->header('Content-Type', 'text/plain');
        }

        $body  = $request->getContent();
        $lines = array_filter(array_map('trim', explode("\n", $body)));

        $basePayload = [
            'source' => 'adms',
            'type' => 'attlog',
            'method' => $request->method(),
            'path' => $request->path(),
            'sn' => $sn,
            'query' => $request->query(),
            'raw_body' => $body,
            'line_count' => count($lines),
        ];

        $lastScanAt       = null;
        $processedCount   = 0;

        if (!count($lines)) {
            $this->logAdms($request, $device, $basePayload, 200, 'empty_payload', null, [
                'message' => 'ATTLOG kosong',
            ]);

            return response("OK\r\n", 200)->header('Content-Type', 'text/plain');
        }

        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }

            // Format: PIN \t DateTime \t Status \t Verify \t WorkCode \t Reserved
            $parts  = explode("\t", $line);
            $pin    = trim($parts[0] ?? '');
            $dt     = trim($parts[1] ?? '');
            $status = (int) trim($parts[2] ?? '0');
            $verify = (int) trim($parts[3] ?? '1');

            if ($pin === '' || $dt === '') {
                $this->logAdms($request, $device, array_merge($basePayload, [
                    'raw_line' => $line,
                    'parsed_parts' => $parts,
                ]), 200, 'validation_error', null, [
                    'message' => 'Baris ATTLOG tidak lengkap',
                ]);
                continue;
            }

            $stateLabel  = $this->statusLabel($status);
            $verifyLabel = $this->verifyLabel($verify);

            $reqPayload = array_merge($basePayload, [
                'pin' => $pin,
                'datetime' => $dt,
                'status_code'  => $status,
                'status_label' => $stateLabel,
                'verify_code'  => $verify,
                'verify_label' => $verifyLabel,
                'raw_line'     => $line,
                'parsed_parts' => $parts,
            ]);

            try {
                $result = $processor->recordFingerprintScanWithResult(
                    $device,
                    $pin,
                    Carbon::parse($dt),
                    $verifyLabel,
                    $stateLabel,
                    $reqPayload
                );

                $rawLog    = $result['raw_log'];
                $attendance = $result['attendance']?->loadMissing(['employee', 'shift']);

                $isLate = $lateNotifier->shouldNotify($attendance, $rawLog);
                if ($isLate) {
                    $lateNotifier->notifyTelegramIfLate($attendance, $rawLog);
                }

                $this->logAdms($request, $device, $reqPayload, 200, 'success', $rawLog->id, [
                    'raw_log_id'        => $rawLog->id,
                    'employee_id'       => $rawLog->employee_id,
                    'attendance_status' => $attendance?->status,
                    'late_minutes'      => (int) ($attendance?->late_minutes ?? 0),
                ], $pin);

                $lastScanAt = $dt;
                $processedCount++;

            } catch (Throwable $e) {
                $this->logAdms($request, $device, $reqPayload, 500, 'error', null, [
                    'error' => $e->getMessage(),
                ], $pin);
            }
        }

        $stamp = $lastScanAt ? Carbon::parse($lastScanAt)->timestamp : now()->timestamp;

        $this->logAdms($request, $device, $basePayload, 200, 'attlog_ack', null, [
            'processed_count' => $processedCount,
            'attlog_stamp' => $stamp,
        ]);

        // Mesin ADMS/ZKTeco/Solution butuh ACK singkat. Jika body balasan tidak
        // dikenali, mesin akan menganggap push gagal dan mengirim ulang ATTLOG.
        return response("OK: {$processedCount}\r\n", 200)
            ->header('Content-Type', 'text/plain')
            ->header('Connection', 'close');
    }

    private function handleUnsupportedTable(Request $request, string $sn, string $table): Response
    {
        $device = AttendanceDevice::where('serial_number', $sn)->first();

        $this->logAdms($request, $device, [
            'source' => 'adms',
            'type' => 'unsupported_table',
            'method' => $request->method(),
            'path' => $request->path(),
            'sn' => $sn,
            'table' => $table,
            'query' => $request->query(),
            'raw_body' => $request->getContent(),
        ], 200, 'unsupported_table');

        return $this->plainOk();
    }

    private function logAdms(
        Request $request,
        ?AttendanceDevice $device,
        ?array $requestPayload,
        int $httpStatus,
        string $status,
        ?int $rawLogId = null,
        ?array $responsePayload = null,
        ?string $deviceUserId = null
    ): void {
        try {
            AttendanceWebhookLog::create([
                'ip_address'           => $request->ip(),
                'serial_number'        => $request->query('SN'),
                'attendance_device_id' => $device?->id,
                'device_user_id'       => $deviceUserId,
                'request_payload'      => $requestPayload,
                'http_status'          => $httpStatus,
                'response_payload'     => $responsePayload,
                'status'               => $status,
                'raw_log_id'           => $rawLogId,
            ]);
        } catch (Throwable) {
            // Jangan biarkan kegagalan logging menghentikan proses utama
        }
    }

    private function statusLabel(int $code): string
    {
        return match ($code) {
            0 => 'check_in',
            1 => 'check_out',
            2 => 'break_out',
            3 => 'break_in',
            4 => 'overtime_in',
            5 => 'overtime_out',
            default => 'unknown',
        };
    }

    private function verifyLabel(int $code): string
    {
        return match ($code) {
            0  => 'password',
            1  => 'fingerprint',
            2  => 'card',
            4  => 'rf_card',
            5  => 'finger_vein',
            15 => 'face',
            25 => 'palm',
            default => 'other',
        };
    }

    private function plainOk(): Response
    {
        return response("OK\r\n", 200)->header('Content-Type', 'text/plain');
    }
}
