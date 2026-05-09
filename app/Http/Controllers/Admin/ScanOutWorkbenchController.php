<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kurir;
use App\Models\QcResiScan;
use App\Models\Resi;
use App\Models\ShipmentScanOut;
use App\Support\QcTransitStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        ]);
    }

    public function recent(Request $request)
    {
        $limit = min(max((int) $request->input('limit', 12), 1), 50);

        $rows = ShipmentScanOut::query()
            ->with(['resi.kurir', 'scanner:id,name'])
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
        $validated = $request->validate([
            'type' => ['required', 'in:id_pesanan,no_resi'],
            'code' => ['required', 'string'],
        ]);

        $type = $validated['type'];
        $code = trim((string) $validated['code']);

        if ($code === '') {
            return response()->json(['message' => 'Kode tidak boleh kosong.'], 422);
        }

        $resi = Resi::query()
            ->with('kurir')
            ->when($type === 'no_resi', fn ($query) => $query->where('no_resi', $code))
            ->when($type === 'id_pesanan', fn ($query) => $query->where('id_pesanan', $code))
            ->first();

        if (!$resi) {
            return response()->json(['message' => 'Resi tidak ditemukan.'], 422);
        }

        if (($resi->status ?? 'active') === 'canceled') {
            return response()->json(['message' => 'Resi sudah dibatalkan.'], 422);
        }

        DB::beginTransaction();
        try {
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

            $existingScan = ShipmentScanOut::query()
                ->where('resi_id', $resi->id)
                ->lockForUpdate()
                ->first();

            if ($existingScan) {
                DB::rollBack();

                return response()->json([
                    'message' => 'Resi sudah discan keluar.',
                    'scan_out' => $this->formatScanOut($existingScan->loadMissing(['resi.kurir', 'scanner:id,name'])),
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
            ]);

            DB::commit();
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            DB::rollBack();

            return response()->json(['message' => 'Resi sudah discan keluar.'], 422);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Gagal memproses scan out.',
                'error' => $e->getMessage(),
            ], 500);
        }

        $scanOut->loadMissing(['resi.kurir', 'scanner:id,name']);

        return response()->json([
            'message' => 'Scan out berhasil.',
            'scan_out' => $this->formatScanOut($scanOut),
            'resi' => [
                'id_pesanan' => $resi->id_pesanan,
                'no_resi' => $resi->no_resi,
                'kurir' => $resi->kurir?->name,
            ],
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
        ];
    }
}
