<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShipmentScanOut;
use Illuminate\Http\Request;

class ScanOutHistoryController extends Controller
{
    public function index()
    {
        return view('admin.outbound.scan-out-history.index', [
            'dataUrl' => route('admin.outbound.scan-out-history.data'),
            'today' => now()->toDateString(),
        ]);
    }

    public function data(Request $request)
    {
        $baseQuery = ShipmentScanOut::query()
            ->with(['resi', 'scanner'])
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
}
