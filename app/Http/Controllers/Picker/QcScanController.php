<?php

namespace App\Http\Controllers\Picker;

use App\Http\Controllers\Controller;
use App\Models\PackerResiScan;
use App\Models\PackerScanOut;
use App\Models\QcResiScan;
use App\Models\QcResiScanItem;
use App\Models\Resi;
use App\Models\ResiDetail;
use App\Support\PickerTransitAllocator;
use App\Support\QcTransitStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QcScanController extends Controller
{
    public function index()
    {
        return view('picker.qc-scan', [
            'routes' => [
                'dashboard' => route('picker.dashboard'),
                'scanResi' => route('picker.qc.scan'),
                'scanSku' => route('picker.qc.scan-sku'),
                'hold' => route('picker.qc.hold'),
                'complete' => route('picker.qc.complete'),
                'reset' => route('picker.qc.reset'),
                'logout' => route('logout'),
            ],
        ]);
    }

    public function scanResi(Request $request)
    {
        $validated = $request->validate([
            'type' => ['required', 'in:id_pesanan,no_resi'],
            'code' => ['required', 'string'],
        ]);

        $type = $validated['type'];
        $code = trim((string) $validated['code']);
        if ($code === '') {
            return response()->json([
                'message' => 'Kode tidak boleh kosong.',
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
        if (PackerScanOut::where('resi_id', $resi->id)->exists()) {
            return response()->json([
                'message' => 'Resi sudah scan out.',
            ], 422);
        }
        if (PackerResiScan::where('resi_id', $resi->id)->exists()) {
            return response()->json([
                'message' => 'Resi sudah dipacking.',
            ], 422);
        }

        $details = ResiDetail::where('resi_id', $resi->id)
            ->get(['sku', 'qty']);

        if ($details->isEmpty()) {
            return response()->json([
                'message' => 'Detail resi belum tersedia.',
            ], 422);
        }

        $grouped = [];
        foreach ($details as $detail) {
            $sku = trim((string) $detail->sku);
            $qty = (int) $detail->qty;
            if ($sku === '' || $qty <= 0) {
                continue;
            }
            $grouped[$sku] = ($grouped[$sku] ?? 0) + $qty;
        }

        if (empty($grouped)) {
            return response()->json([
                'message' => 'Detail resi tidak valid.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $qc = QcResiScan::where('resi_id', $resi->id)
                ->lockForUpdate()
                ->first();

            if ($qc && ($qc->status ?? '') === QcTransitStatus::PASSED) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Resi sudah QC selesai.',
                ], 422);
            }

            if (!$qc) {
                $qc = QcResiScan::create([
                    'resi_id' => $resi->id,
                    'scan_type' => $type,
                    'scan_code' => $code,
                    'status' => QcTransitStatus::DRAFT,
                    'started_at' => now(),
                    'scanned_by' => auth()->id(),
                    'last_scanned_by' => auth()->id(),
                    'last_scanned_at' => now(),
                ]);

                foreach ($grouped as $sku => $qty) {
                    QcResiScanItem::create([
                        'qc_resi_scan_id' => $qc->id,
                        'sku' => $sku,
                        'expected_qty' => $qty,
                        'scanned_qty' => 0,
                    ]);
                }
            } else {
                $hasItems = QcResiScanItem::where('qc_resi_scan_id', $qc->id)->exists();
                if (!$hasItems) {
                    foreach ($grouped as $sku => $qty) {
                        QcResiScanItem::create([
                            'qc_resi_scan_id' => $qc->id,
                            'sku' => $sku,
                            'expected_qty' => $qty,
                            'scanned_qty' => 0,
                        ]);
                    }
                }

                $qc->last_scanned_by = auth()->id();
                $qc->last_scanned_at = now();
                $qc->save();
            }

            $this->loadQcRelations($qc);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal memulai QC.',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'QC resi siap diproses.',
            'qc' => $this->serializeQc($qc),
            'resi' => [
                'id_pesanan' => $resi->id_pesanan,
                'no_resi' => $resi->no_resi,
                'tanggal_pesanan' => $resi->tanggal_pesanan?->format('Y-m-d'),
            ],
        ]);
    }

    public function scanSku(Request $request)
    {
        $validated = $request->validate([
            'qc_id' => ['required', 'integer', 'exists:qc_resi_scans,id'],
            'code' => ['required', 'string'],
            'qty' => ['nullable', 'integer', 'min:1'],
        ]);

        $code = trim((string) $validated['code']);
        $qty = (int) ($validated['qty'] ?? 1);
        if ($code === '') {
            return response()->json([
                'message' => 'SKU tidak boleh kosong.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $qc = QcResiScan::where('id', (int) $validated['qc_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if (($qc->status ?? '') === QcTransitStatus::PASSED) {
                DB::rollBack();
                return response()->json([
                    'message' => 'QC sudah selesai, tidak bisa scan ulang.',
                ], 422);
            }

            $items = QcResiScanItem::where('qc_resi_scan_id', $qc->id)
                ->lockForUpdate()
                ->get();

            $target = $items->first(function ($row) use ($code) {
                return strtolower((string) $row->sku) === strtolower($code);
            });

            if (!$target) {
                DB::rollBack();
                return response()->json([
                    'message' => 'SKU tidak sesuai resi.',
                ], 422);
            }

            $expected = (int) $target->expected_qty;
            $scanned = (int) $target->scanned_qty;
            if ($scanned + $qty > $expected) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Qty scan melebihi kebutuhan.',
                    'details' => [[
                        'sku' => $target->sku,
                        'required' => $expected,
                        'scanned' => $scanned,
                        'attempt' => $qty,
                    ]],
                ], 422);
            }

            if (!PickerTransitAllocator::isExceptionSku((string) $target->sku)) {
                $availability = PickerTransitAllocator::availableQtyForAdditionalScan(
                    (string) $target->sku,
                    now()->toDateString()
                );

                if (($availability['available'] ?? 0) < $qty) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Sisa transit picker tidak mencukupi untuk scan SKU ini.',
                        'details' => [[
                            'sku' => $target->sku,
                            'required' => $qty,
                            'available' => (int) ($availability['available'] ?? 0),
                            'reason' => $availability['reason'] ?? 'Sisa transit tidak mencukupi',
                        ]],
                    ], 422);
                }
            }

            $target->scanned_qty = $scanned + $qty;
            $target->save();

            if (($qc->status ?? '') === QcTransitStatus::HOLD) {
                $qc->status = QcTransitStatus::DRAFT;
            }
            $qc->last_scanned_by = auth()->id();
            $qc->last_scanned_at = now();
            $qc->save();

            $items = QcResiScanItem::where('qc_resi_scan_id', $qc->id)->get();

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal memproses scan SKU.',
                'error' => $e->getMessage(),
            ], 500);
        }

        $qc->setRelation('items', $items);
        $this->loadQcRelations($qc);

        return response()->json([
            'message' => 'SKU berhasil discan.',
            'qc' => $this->serializeQc($qc),
        ]);
    }

    public function hold(Request $request)
    {
        $validated = $request->validate([
            'qc_id' => ['required', 'integer', 'exists:qc_resi_scans,id'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $reason = trim((string) $validated['reason']);
        if ($reason === '') {
            return response()->json([
                'message' => 'Alasan simpan & lewatkan wajib diisi.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $qc = QcResiScan::where('id', (int) $validated['qc_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if (($qc->status ?? '') === QcTransitStatus::PASSED) {
                DB::rollBack();
                return response()->json([
                    'message' => 'QC sudah selesai, tidak bisa dilewatkan.',
                ], 422);
            }

            $qc->status = QcTransitStatus::HOLD;
            $qc->hold_by = auth()->id();
            $qc->hold_at = now();
            $qc->hold_reason = $reason;
            $qc->last_scanned_by = auth()->id();
            $qc->last_scanned_at = now();
            $qc->save();

            $this->loadQcRelations($qc);

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menyimpan QC untuk dilewatkan.',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'QC disimpan untuk dilewatkan.',
            'qc' => $this->serializeQc($qc),
        ]);
    }

    public function complete(Request $request)
    {
        $validated = $request->validate([
            'qc_id' => ['required', 'integer', 'exists:qc_resi_scans,id'],
        ]);

        DB::beginTransaction();
        try {
            $qc = QcResiScan::where('id', (int) $validated['qc_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if (($qc->status ?? '') === QcTransitStatus::PASSED) {
                DB::rollBack();
                return response()->json([
                    'message' => 'QC sudah selesai sebelumnya.',
                ]);
            }

            $items = QcResiScanItem::where('qc_resi_scan_id', $qc->id)->get();

            $missing = [];
            foreach ($items as $row) {
                $expected = (int) $row->expected_qty;
                $scanned = (int) $row->scanned_qty;
                if ($scanned !== $expected) {
                    $missing[] = [
                        'sku' => $row->sku,
                        'required' => $expected,
                        'scanned' => $scanned,
                    ];
                }
            }

            if (!empty($missing)) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Masih ada SKU yang belum sesuai.',
                    'details' => $missing,
                ], 422);
            }

            $completedAt = now();
            [$skuTotals] = PickerTransitAllocator::splitSkuTotals($items, 'expected_qty');
            $allocation = PickerTransitAllocator::prepareAllocations($skuTotals, $completedAt->toDateString());

            if (!empty($allocation['issues'])) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Masih ada SKU dengan sisa transit picker tidak mencukupi.',
                    'details' => $allocation['issues'],
                ], 422);
            }

            PickerTransitAllocator::applyAllocations($allocation['updates']);

            $qc->status = QcTransitStatus::PASSED;
            $qc->completed_at = $completedAt;
            $qc->completed_by = auth()->id();
            $qc->save();

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menyelesaikan QC.',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'QC selesai dan resi siap dipacking.',
            'qc' => $this->serializeQc($this->loadQcRelations($qc->fresh())),
        ]);
    }

    public function reset(Request $request)
    {
        $validated = $request->validate([
            'qc_id' => ['required', 'integer', 'exists:qc_resi_scans,id'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $reason = trim((string) $validated['reason']);
        if ($reason === '') {
            return response()->json([
                'message' => 'Alasan reset wajib diisi.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $qc = QcResiScan::where('id', (int) $validated['qc_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if (($qc->status ?? '') === QcTransitStatus::PASSED) {
                DB::rollBack();
                return response()->json([
                    'message' => 'QC sudah selesai, tidak bisa direset.',
                ], 422);
            }

            QcResiScanItem::where('qc_resi_scan_id', $qc->id)
                ->update(['scanned_qty' => 0]);

            $qc->status = QcTransitStatus::DRAFT;
            $qc->reset_count = (int) ($qc->reset_count ?? 0) + 1;
            $qc->reset_by = auth()->id();
            $qc->reset_at = now();
            $qc->reset_reason = $reason;
            $qc->save();

            $this->loadQcRelations($qc);

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal reset QC.',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'QC direset.',
            'qc' => $this->serializeQc($qc),
        ]);
    }

    private function loadQcRelations(QcResiScan $qc): QcResiScan
    {
        return $qc->load([
            'items',
            'scanner:id,name',
            'completer:id,name',
            'lastScanner:id,name',
            'resetter:id,name',
            'holder:id,name',
        ]);
    }

    private function serializeQc(QcResiScan $qc): array
    {
        $items = $qc->items ?? collect();
        $totalExpected = 0;
        $totalScanned = 0;
        $rows = $items->map(function ($row) use (&$totalExpected, &$totalScanned) {
            $expected = (int) $row->expected_qty;
            $scanned = (int) $row->scanned_qty;
            $totalExpected += $expected;
            $totalScanned += $scanned;
            return [
                'sku' => $row->sku,
                'expected_qty' => $expected,
                'scanned_qty' => $scanned,
            ];
        })->values();

        return [
            'id' => $qc->id,
            'status' => $qc->status,
            'started_at' => $qc->started_at?->format('Y-m-d H:i'),
            'completed_at' => $qc->completed_at?->format('Y-m-d H:i'),
            'items' => $rows,
            'summary' => [
                'total_expected' => $totalExpected,
                'total_scanned' => $totalScanned,
                'remaining' => max(0, $totalExpected - $totalScanned),
            ],
            'audit' => [
                'started_by' => $qc->scanner?->name ?? '-',
                'completed_by' => $qc->completer?->name ?? '-',
                'last_scanned_by' => $qc->lastScanner?->name ?? '-',
                'last_scanned_at' => $qc->last_scanned_at?->format('Y-m-d H:i'),
                'reset_count' => (int) ($qc->reset_count ?? 0),
                'reset_by' => $qc->resetter?->name ?? '-',
                'reset_at' => $qc->reset_at?->format('Y-m-d H:i'),
                'reset_reason' => $qc->reset_reason,
                'hold_by' => $qc->holder?->name ?? '-',
                'hold_at' => $qc->hold_at?->format('Y-m-d H:i'),
                'hold_reason' => $qc->hold_reason,
            ],
        ];
    }
}
