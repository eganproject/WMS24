<?php

namespace App\Http\Controllers\Picker;

use App\Http\Controllers\Controller;
use App\Models\PackerResiScan;
use App\Models\PackerTransitHistory;
use App\Models\QcResiScan;
use App\Models\QcResiScanItem;
use App\Models\Resi;
use App\Support\PickerTransitAllocator;
use App\Support\QcTransitStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PackerScanController extends Controller
{
    public function index()
    {
        return view('picker.packer-scan', [
            'routes' => [
                'dashboard' => route('picker.dashboard'),
                'scan' => route('picker.packer.scan'),
                'logout' => route('logout'),
            ],
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
            return response()->json([
                'message' => 'Kode resi tidak boleh kosong.',
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

        $qc = QcResiScan::where('resi_id', $resi->id)->first();
        if (!$qc || ($qc->status ?? '') !== QcTransitStatus::PASSED) {
            return response()->json([
                'message' => 'Resi belum QC selesai.',
            ], 422);
        }

        $scanDate = now()->toDateString();

        DB::beginTransaction();
        try {
            $qc = QcResiScan::where('id', $qc->id)
                ->where('status', QcTransitStatus::PASSED)
                ->lockForUpdate()
                ->first();

            if (!$qc) {
                DB::rollBack();
                return response()->json([
                    'message' => 'QC resi belum selesai.',
                ], 422);
            }

            $existingScan = PackerResiScan::where('resi_id', $resi->id)
                ->lockForUpdate()
                ->first();

            if ($existingScan) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Resi sudah pernah discan.',
                ], 422);
            }

            $details = QcResiScanItem::where('qc_resi_scan_id', $qc->id)
                ->lockForUpdate()
                ->get(['sku', 'expected_qty']);

            if ($details->isEmpty()) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Snapshot QC tidak ditemukan.',
                ], 422);
            }

            [$skuTotals, $excludedTotals] = PickerTransitAllocator::splitSkuTotals($details, 'expected_qty');

            if (empty($skuTotals) && empty($excludedTotals)) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Snapshot QC tidak valid.',
                ], 422);
            }

            PackerResiScan::create([
                'resi_id' => $resi->id,
                'scan_type' => $type,
                'scan_code' => $code,
                'scan_date' => $scanDate,
                'scanned_at' => now(),
                'scanned_by' => auth()->id(),
            ]);

            $transitHistory = PackerTransitHistory::where('resi_id', $resi->id)
                ->lockForUpdate()
                ->first();
            if (!$transitHistory) {
                PackerTransitHistory::create([
                    'resi_id' => $resi->id,
                    'id_pesanan' => $resi->id_pesanan,
                    'no_resi' => $resi->no_resi,
                    'status' => 'menunggu scan out',
                ]);
            } elseif (empty($transitHistory->no_resi) && !empty($resi->no_resi)) {
                $transitHistory->no_resi = $resi->no_resi;
                $transitHistory->save();
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Gagal memproses resi.',
                'error' => $e->getMessage(),
            ], 500);
        }

        $itemsPayload = [];
        foreach ($skuTotals as $sku => $qty) {
            $itemsPayload[] = [
                'sku' => $sku,
                'qty' => $qty,
                'excluded' => false,
            ];
        }
        foreach ($excludedTotals as $sku => $qty) {
            $itemsPayload[] = [
                'sku' => $sku,
                'qty' => $qty,
                'excluded' => true,
            ];
        }

        return response()->json([
            'message' => 'Resi berhasil diproses.',
            'resi' => [
                'id_pesanan' => $resi->id_pesanan,
                'no_resi' => $resi->no_resi,
                'tanggal_pesanan' => $resi->tanggal_pesanan?->format('Y-m-d'),
            ],
            'items' => $itemsPayload,
        ]);
    }
}
