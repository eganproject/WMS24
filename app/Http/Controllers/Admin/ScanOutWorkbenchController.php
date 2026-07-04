<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Kurir;
use App\Models\QcResiScan;
use App\Models\Resi;
use App\Models\ScanOutFailedAttempt;
use App\Models\ShipmentScanOut;
use App\Support\QcTransitStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ScanOutWorkbenchController extends Controller
{
    public function index()
    {
        return view('admin.outbound.scan-out.index', [
            'routes' => [
                'scan' => route('admin.outbound.scan-out.scan'),
                'recent' => route('admin.outbound.scan-out.recent'),
                'history' => route('admin.outbound.scan-out-history.index'),
                'transitQc' => route('admin.outbound.transit-qc.index'),
            ],
            'today' => now()->toDateString(),
            'packers' => $this->packerOptions(),
        ]);
    }

    public function recent(Request $request)
    {
        $limit = min(max((int) $request->input('limit', 12), 1), 50);

        $rows = ShipmentScanOut::query()
            ->with(['resi.kurir', 'scanner:id,name', 'packedEmployee:id,employee_code,name'])
            ->whereDate('scan_date', now()->toDateString())
            ->orderByDesc('scanned_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return response()->json([
            'summary' => [
                'today' => ShipmentScanOut::query()->whereDate('scan_date', now()->toDateString())->count(),
                'last_scan_at' => optional($rows->first()?->scanned_at)->format('H:i:s'),
            ],
            'items' => $rows->map(fn (ShipmentScanOut $scan) => $this->formatScanOut($scan))->values(),
        ]);
    }

    public function scan(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => ['required', 'in:id_pesanan,no_resi'],
            'code' => ['required', 'string'],
            'packed_employee_id' => ['nullable', 'integer'],
        ]);

        if ($validator->fails()) {
            $this->recordFailedAttempt($request, [
                'scan_type' => $request->input('type'),
                'scan_code' => $request->input('code'),
                'reason_code' => 'invalid_request',
                'message' => $validator->errors()->first(),
            ]);

            return response()->json([
                'message' => $validator->errors()->first() ?: 'Input scan out tidak valid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $type = $validated['type'];
        $code = trim((string) $validated['code']);

        if ($code === '') {
            return response()->json(['message' => 'Kode tidak boleh kosong.'], 422);
        }

        try {
            $packedEmployeeId = $this->validatePackerEmployeeId($validated['packed_employee_id'] ?? null);
        } catch (ValidationException $e) {
            $this->recordFailedAttempt($request, [
                'scan_type' => $type,
                'scan_code' => $code,
                'reason_code' => 'invalid_packer',
                'message' => collect($e->errors())->flatten()->first() ?: $e->getMessage(),
            ]);

            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }

        $resi = Resi::query()
            ->with('kurir')
            ->when($type === 'no_resi', fn ($query) => $query->where('no_resi', $code))
            ->when($type === 'id_pesanan', fn ($query) => $query->where('id_pesanan', $code))
            ->first();

        if (!$resi) {
            $this->recordFailedAttempt($request, [
                'scan_type' => $type,
                'scan_code' => $code,
                'reason_code' => 'resi_not_found',
                'message' => 'Resi tidak ditemukan.',
            ]);

            return response()->json(['message' => 'Resi tidak ditemukan.'], 422);
        }

        if (($resi->status ?? 'active') === 'canceled') {
            $this->recordFailedAttempt($request, [
                'resi' => $resi,
                'scan_type' => $type,
                'scan_code' => $code,
                'reason_code' => 'resi_canceled',
                'message' => 'Resi sudah dibatalkan.',
            ]);

            return response()->json(['message' => 'Resi sudah dibatalkan.'], 422);
        }

        DB::beginTransaction();
        try {
            $qc = QcResiScan::query()
                ->where('resi_id', $resi->id)
                ->lockForUpdate()
                ->first();

            if (!$qc) {
                DB::rollBack();

                $this->recordFailedAttempt($request, [
                    'resi' => $resi,
                    'scan_type' => $type,
                    'scan_code' => $code,
                    'reason_code' => 'qc_not_started',
                    'message' => 'Resi belum pernah diproses QC. Scan out ditolak.',
                ]);

                return response()->json([
                    'message' => 'Resi belum pernah diproses QC. Scan out ditolak.',
                    'reason_code' => 'qc_not_started',
                    'detail' => 'Lakukan QC scan untuk resi ini sampai selesai sebelum scan out.',
                    'resi' => $this->formatResi($resi),
                ], 422);
            }

            if ($qc->status !== QcTransitStatus::PASSED) {
                DB::rollBack();

                $this->recordFailedAttempt($request, [
                    'resi' => $resi,
                    'qc' => $qc,
                    'scan_type' => $type,
                    'scan_code' => $code,
                    'reason_code' => 'qc_not_passed',
                    'message' => 'QC resi belum selesai. Scan out ditolak.',
                ]);

                return response()->json([
                    'message' => 'QC resi belum selesai. Scan out ditolak.',
                    'reason_code' => 'qc_not_passed',
                    'detail' => 'Status QC saat ini: '.QcTransitStatus::scanStatusLabel($qc->status).'. Selesaikan QC sampai status Lolos QC sebelum scan out.',
                    'resi' => $this->formatResi($resi),
                    'qc' => $this->formatQc($qc),
                ], 422);
            }

            $existingScan = ShipmentScanOut::query()
                ->where('resi_id', $resi->id)
                ->lockForUpdate()
                ->first();

            if ($existingScan) {
                DB::rollBack();

                $this->recordFailedAttempt($request, [
                    'resi' => $resi,
                    'qc' => $qc,
                    'scan_out' => $existingScan,
                    'scan_type' => $type,
                    'scan_code' => $code,
                    'reason_code' => 'duplicate_scan_out',
                    'message' => 'Resi sudah discan keluar.',
                ]);

                return response()->json([
                    'message' => 'Resi sudah discan keluar.',
                    'scan_out' => $this->formatScanOut($existingScan->loadMissing(['resi.kurir', 'scanner:id,name', 'packedEmployee:id,employee_code,name'])),
                ], 422);
            }

            $kurirId = $resi->kurir_id ?: $this->fallbackKurirId();
            $scanOut = ShipmentScanOut::create([
                'resi_id' => $resi->id,
                'kurir_id' => $kurirId,
                'scan_type' => $type,
                'scan_code' => $code,
                'scan_date' => now()->toDateString(),
                'scanned_at' => now(),
                'scanned_by' => auth()->id(),
                'packed_employee_id' => $packedEmployeeId,
                'packed_at' => $packedEmployeeId ? now() : null,
                'packing_confirmed_by' => $packedEmployeeId ? auth()->id() : null,
            ]);

            DB::commit();
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            DB::rollBack();

            $this->recordFailedAttempt($request, [
                'resi' => $resi ?? null,
                'scan_type' => $type,
                'scan_code' => $code,
                'reason_code' => 'duplicate_scan_out',
                'message' => 'Resi sudah discan keluar.',
            ]);

            return response()->json(['message' => 'Resi sudah discan keluar.'], 422);
        } catch (\Throwable $e) {
            DB::rollBack();

            $this->recordFailedAttempt($request, [
                'resi' => $resi ?? null,
                'qc' => $qc ?? null,
                'scan_type' => $type,
                'scan_code' => $code,
                'reason_code' => 'server_error',
                'message' => 'Gagal memproses scan out.',
            ]);

            return response()->json([
                'message' => 'Gagal memproses scan out.',
                'error' => $e->getMessage(),
            ], 500);
        }

        $scanOut->loadMissing(['resi.kurir', 'scanner:id,name', 'packedEmployee:id,employee_code,name']);

        return response()->json([
            'message' => 'Scan out berhasil.',
            'scan_out' => $this->formatScanOut($scanOut),
            'resi' => $this->formatResi($resi),
        ]);
    }

    private function fallbackKurirId(): int
    {
        return Kurir::query()->firstOrCreate(['name' => 'Tidak ditemukan kurir'])->id;
    }

    private function formatScanOut(ShipmentScanOut $scan): array
    {
        return [
            'id' => $scan->id,
            'id_pesanan' => $scan->resi?->id_pesanan ?? '-',
            'no_resi' => $scan->resi?->no_resi ?? '-',
            'kurir' => $scan->resi?->kurir?->name ?? $scan->kurir?->name ?? '-',
            'scan_type' => $scan->scan_type,
            'scan_code' => $scan->scan_code,
            'scanned_at' => $scan->scanned_at?->format('Y-m-d H:i:s') ?? '-',
            'scanned_time' => $scan->scanned_at?->format('H:i:s') ?? '-',
            'scanner' => $scan->scanner?->name ?? '-',
            'packed_by' => $scan->packedEmployee?->name ?? '-',
            'packed_employee_code' => $scan->packedEmployee?->employee_code ?? '-',
            'packed_at' => $scan->packed_at?->format('Y-m-d H:i:s') ?? '-',
        ];
    }

    private function packerOptions()
    {
        return Employee::query()
            ->active()
            ->with('positionRelation:id,name')
            ->where(function ($query) {
                $query->whereHas('positionRelation', function ($positionQuery) {
                    $positionQuery->whereRaw('LOWER(name) LIKE ?', ['%packer%'])
                        ->orWhereRaw('LOWER(name) LIKE ?', ['%packing%']);
                })->orWhereRaw('LOWER(COALESCE(position, "")) LIKE ?', ['%packer%'])
                    ->orWhereRaw('LOWER(COALESCE(position, "")) LIKE ?', ['%packing%']);
            })
            ->orderBy('name')
            ->get(['id', 'employee_code', 'name', 'position', 'position_id']);
    }

    private function validatePackerEmployeeId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $exists = Employee::query()
            ->active()
            ->whereKey((int) $value)
            ->where(function ($query) {
                $query->whereHas('positionRelation', function ($positionQuery) {
                    $positionQuery->whereRaw('LOWER(name) LIKE ?', ['%packer%'])
                        ->orWhereRaw('LOWER(name) LIKE ?', ['%packing%']);
                })->orWhereRaw('LOWER(COALESCE(position, "")) LIKE ?', ['%packer%'])
                    ->orWhereRaw('LOWER(COALESCE(position, "")) LIKE ?', ['%packing%']);
            })
            ->exists();

        if (!$exists) {
            throw ValidationException::withMessages([
                'packed_employee_id' => 'Packer tidak valid atau jabatan karyawan bukan packer/packing.',
            ]);
        }

        return (int) $value;
    }

    private function formatResi(Resi $resi): array
    {
        return [
            'id_pesanan' => $resi->id_pesanan ?? '-',
            'no_resi' => $resi->no_resi ?? '-',
            'kurir' => $resi->kurir?->name ?? '-',
            'status' => $resi->status ?? '-',
        ];
    }

    private function formatQc(QcResiScan $qc): array
    {
        return [
            'id' => $qc->id,
            'status' => $qc->status,
            'status_label' => QcTransitStatus::scanStatusLabel($qc->status),
            'started_at' => $qc->started_at?->format('Y-m-d H:i:s'),
            'completed_at' => $qc->completed_at?->format('Y-m-d H:i:s'),
        ];
    }

    private function recordFailedAttempt(Request $request, array $payload): void
    {
        try {
            $resi = $payload['resi'] ?? null;
            $qc = $payload['qc'] ?? null;
            $scanOut = $payload['scan_out'] ?? null;

            ScanOutFailedAttempt::create([
                'resi_id' => $resi?->id,
                'qc_resi_scan_id' => $qc?->id,
                'shipment_scan_out_id' => $scanOut?->id,
                'scan_type' => $payload['scan_type'] ?? null,
                'scan_code' => $payload['scan_code'] ?? null,
                'reason_code' => $payload['reason_code'] ?? 'unknown',
                'message' => $payload['message'] ?? null,
                'resi_status' => $resi?->status,
                'qc_status' => $qc?->status,
                'qc_completed_at' => $qc?->completed_at,
                'existing_scanned_at' => $scanOut?->scanned_at,
                'attempted_by' => auth()->id(),
                'attempted_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 2000),
            ]);
        } catch (\Throwable) {
            // Logging failure must not block the operator flow.
        }
    }
}
