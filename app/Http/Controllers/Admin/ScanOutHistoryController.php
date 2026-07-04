<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ScanOutFailedAttempt;
use App\Models\ShipmentScanOut;
use Illuminate\Http\Request;

class ScanOutHistoryController extends Controller
{
    public function index()
    {
        return view('admin.outbound.scan-out-history.index', [
            'dataUrl' => route('admin.outbound.scan-out-history.data'),
            'failedDataUrl' => route('admin.outbound.scan-out-history.failed-attempts.data'),
            'today' => now()->toDateString(),
        ]);
    }

    public function data(Request $request)
    {
        $baseQuery = ShipmentScanOut::query()
            ->with(['resi', 'scanner', 'packedEmployee'])
            ->orderByDesc('scanned_at');

        $this->applyDateFilter($baseQuery, $request);

        $query = clone $baseQuery;

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $exact = $this->isExactSearch($request);
            $query->where(function ($q) use ($search, $exact) {
                $this->applyTextSearch($q, 'scan_code', $search, $exact);
                $this->applyTextSearch($q, 'scan_type', $search, $exact, 'or');
                $q->orWhereHas('resi', function ($resiQ) use ($search, $exact) {
                    $this->applyTextSearch($resiQ, 'id_pesanan', $search, $exact);
                    $this->applyTextSearch($resiQ, 'no_resi', $search, $exact, 'or');
                })->orWhereHas('scanner', function ($userQ) use ($search, $exact) {
                    $this->applyTextSearch($userQ, 'name', $search, $exact);
                    $this->applyTextSearch($userQ, 'email', $search, $exact, 'or');
                })->orWhereHas('packedEmployee', function ($employeeQ) use ($search, $exact) {
                    $this->applyTextSearch($employeeQ, 'name', $search, $exact);
                    $this->applyTextSearch($employeeQ, 'employee_code', $search, $exact, 'or');
                });
            });
        }

        $recordsTotal = (clone $baseQuery)->count();
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $data = $query->get()->map(function ($row) {
            return [
                'id' => $row->id,
                'scan_date' => $row->scan_date?->format('Y-m-d') ?? '-',
                'scanned_at' => $row->scanned_at?->format('Y-m-d H:i') ?? '-',
                'scanner' => $row->scanner?->name ?? '-',
                'packed_by' => $row->packedEmployee?->name ?? '-',
                'packed_at' => $row->packed_at?->format('Y-m-d H:i') ?? '-',
                'scan_type' => $row->scan_type ?? '-',
                'scan_code' => $row->scan_code ?? '-',
                'id_pesanan' => $row->resi?->id_pesanan ?? '-',
                'no_resi' => $row->resi?->no_resi ?? '-',
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function failedAttemptsData(Request $request)
    {
        $baseQuery = ScanOutFailedAttempt::query()
            ->with(['resi', 'user', 'scanOut'])
            ->orderByDesc('attempted_at');

        $this->applyAttemptDateFilter($baseQuery, $request);

        $reasonCode = trim((string) $request->input('reason_code', ''));
        if ($reasonCode !== '') {
            $baseQuery->where('reason_code', $reasonCode);
        }

        $query = clone $baseQuery;

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $exact = $this->isExactSearch($request);
            $query->where(function ($q) use ($search, $exact) {
                $this->applyTextSearch($q, 'scan_code', $search, $exact);
                $this->applyTextSearch($q, 'scan_type', $search, $exact, 'or');
                $this->applyTextSearch($q, 'reason_code', $search, $exact, 'or');
                $this->applyTextSearch($q, 'message', $search, $exact, 'or');
                $q->orWhereHas('resi', function ($resiQ) use ($search, $exact) {
                    $this->applyTextSearch($resiQ, 'id_pesanan', $search, $exact);
                    $this->applyTextSearch($resiQ, 'no_resi', $search, $exact, 'or');
                })->orWhereHas('user', function ($userQ) use ($search, $exact) {
                    $this->applyTextSearch($userQ, 'name', $search, $exact);
                    $this->applyTextSearch($userQ, 'email', $search, $exact, 'or');
                });
            });
        }

        $recordsTotal = (clone $baseQuery)->count();
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $data = $query->get()->map(function (ScanOutFailedAttempt $row) {
            return [
                'id' => $row->id,
                'attempted_at' => $row->attempted_at?->format('Y-m-d H:i') ?? '-',
                'operator' => $row->user?->name ?? '-',
                'reason_code' => $row->reason_code ?? '-',
                'reason_label' => $this->failedReasonLabel((string) $row->reason_code),
                'message' => $row->message ?? '-',
                'scan_type' => $row->scan_type ?? '-',
                'scan_code' => $row->scan_code ?? '-',
                'id_pesanan' => $row->resi?->id_pesanan ?? '-',
                'no_resi' => $row->resi?->no_resi ?? '-',
                'resi_status' => $row->resi_status ?? '-',
                'qc_status' => $row->qc_status ?? '-',
                'existing_scanned_at' => $row->existing_scanned_at?->format('Y-m-d H:i') ?? '-',
                'ip_address' => $row->ip_address ?? '-',
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'summary' => [
                'total' => (clone $baseQuery)->count(),
                'duplicate' => (clone $baseQuery)->where('reason_code', 'duplicate_scan_out')->count(),
                'qc_not_ready' => (clone $baseQuery)->whereIn('reason_code', ['qc_not_started', 'qc_not_passed'])->count(),
                'invalid' => (clone $baseQuery)->whereIn('reason_code', ['invalid_request', 'resi_not_found', 'resi_canceled', 'invalid_packer'])->count(),
            ],
            'data' => $data,
        ]);
    }

    private function applyDateFilter($query, Request $request): void
    {
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        if (!$dateFrom && !$dateTo) {
            $today = now()->toDateString();
            $query->whereDate('scan_date', $today);
            return;
        }

        if ($dateFrom) {
            $query->whereDate('scan_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('scan_date', '<=', $dateTo);
        }
    }

    private function applyAttemptDateFilter($query, Request $request): void
    {
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        if (!$dateFrom && !$dateTo) {
            $today = now()->toDateString();
            $query->whereDate('attempted_at', $today);
            return;
        }

        if ($dateFrom) {
            $query->whereDate('attempted_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('attempted_at', '<=', $dateTo);
        }
    }

    private function failedReasonLabel(string $reason): string
    {
        return match ($reason) {
            'qc_not_started' => 'QC Belum Dimulai',
            'qc_not_passed' => 'QC Belum Selesai',
            'duplicate_scan_out' => 'Duplikat Scan Out',
            'resi_not_found' => 'Resi Tidak Ditemukan',
            'resi_canceled' => 'Resi Cancel',
            'invalid_packer' => 'Packer Tidak Valid',
            'invalid_request' => 'Input Tidak Valid',
            'server_error' => 'Error Sistem',
            default => $reason ?: '-',
        };
    }
}
