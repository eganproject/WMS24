<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Kurir;
use App\Models\QcResiScan;
use App\Models\Resi;
use App\Models\ShipmentScanOut;
use App\Support\QcTransitStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ScanOutController extends Controller
{
    public function index()
    {
        return view('mobile.scan-out', [
            'routes' => [
                'dashboard' => route('mobile.dashboard'),
                'scan' => route('mobile.scan-out.scan'),
                'history' => route('mobile.scan-out.history'),
                'desktop' => route('admin.outbound.scan-out.index'),
                'logout' => route('logout'),
            ],
            'packers' => $this->packerOptions(),
        ]);
    }

    public function history()
    {
        return view('mobile.scan-out-history', [
            'routes' => [
                'dashboard' => route('mobile.dashboard'),
                'scanOut' => route('mobile.scan-out.index'),
                'data' => route('mobile.scan-out.history-data'),
                'logout' => route('logout'),
            ],
            'today' => now()->toDateString(),
        ]);
    }

    public function historyData(Request $request)
    {
        $query = ShipmentScanOut::query()
            ->with(['resi', 'packedEmployee'])
            ->orderByDesc('scanned_at')
            ->orderByDesc('id');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('scan_code', 'like', "%{$search}%")
                    ->orWhereHas('resi', function ($resiQ) use ($search) {
                        $resiQ->where('id_pesanan', 'like', "%{$search}%")
                            ->orWhere('no_resi', 'like', "%{$search}%");
                    });
            });
        }

        $date = $request->input('date') ?: now()->toDateString();
        try {
            $query->whereDate('scan_date', $date);
        } catch (\Throwable) {
            // ignore invalid date
        }

        $items = $query->get()->map(function ($row) {
            return [
                'id_pesanan' => $row->resi?->id_pesanan ?? '-',
                'no_resi' => $row->resi?->no_resi ?? '-',
                'scan_type' => $row->scan_type ?? '-',
                'scan_code' => $row->scan_code ?? '-',
                'scanned_at' => $row->scanned_at?->format('Y-m-d H:i') ?? '-',
                'packed_by' => $row->packedEmployee?->name ?? '-',
            ];
        });

        return response()->json([
            'items' => $items,
        ]);
    }

    public function scan(Request $request)
    {
        $validated = $request->validate([
            'type' => ['required', 'in:id_pesanan,no_resi'],
            'code' => ['required', 'string'],
            'packed_employee_id' => ['nullable', 'integer'],
        ]);

        $type = $validated['type'];
        $code = trim((string) $validated['code']);
        if ($code === '') {
            return response()->json([
                'message' => 'Kode tidak boleh kosong.',
            ], 422);
        }

        try {
            $packedEmployeeId = $this->validatePackerEmployeeId($validated['packed_employee_id'] ?? null);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }

        $resiQuery = Resi::query();
        if ($type === 'no_resi') {
            $resiQuery->where('no_resi', $code);
        } else {
            $resiQuery->where('id_pesanan', $code);
        }

        $resi = $resiQuery->first();
        if (!$resi) {
            return response()->json([
                'message' => 'Resi tidak ditemukan.',
            ], 422);
        }
        if (($resi->status ?? 'active') === 'canceled') {
            return response()->json([
                'message' => 'Resi sudah dibatalkan.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Lock QcResiScan first — this record always exists after QC started
            $qc = QcResiScan::query()
                ->where('resi_id', $resi->id)
                ->lockForUpdate()
                ->first();

            if (!$qc || $qc->status !== QcTransitStatus::PASSED) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Resi belum lolos QC dan belum siap scan out.',
                ], 422);
            }

            $existingScan = ShipmentScanOut::where('resi_id', $resi->id)
                ->lockForUpdate()
                ->first();
            if ($existingScan) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Resi sudah discan keluar.',
                ], 422);
            }

            $kurirId = $resi->kurir_id;
            if (!$kurirId) {
                $kurirId = Kurir::where('name', 'Tidak ditemukan kurir')->value('id');
                if (!$kurirId) {
                    $kurirId = Kurir::create(['name' => 'Tidak ditemukan kurir'])->id;
                }
            }

            ShipmentScanOut::create([
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
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Resi sudah discan keluar.',
            ], 422);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal memproses scan out.',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Scan out berhasil.',
            'scan_out' => [
                'packed_by' => $packedEmployeeId ? Employee::query()->whereKey($packedEmployeeId)->value('name') : '-',
            ],
            'resi' => [
                'id_pesanan' => $resi->id_pesanan,
                'no_resi' => $resi->no_resi,
            ],
        ]);
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
}
