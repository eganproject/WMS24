<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\QcResiScan;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TransitQcController extends Controller
{
    public function index()
    {
        return view('admin.outbound.transit-qc.index', [
            'dataUrl' => route('admin.outbound.transit-qc.data'),
            'today'   => now()->toDateString(),
        ]);
    }

    public function data(Request $request)
    {
        $today    = now()->toDateString();
        $tab      = $request->input('tab', 'siap_scan_out'); // siap_scan_out | scan_out_selesai | semua
        $dateFrom = $request->input('date_from', $today);
        $dateTo   = $request->input('date_to', $today);

        $baseQuery = QcResiScan::query()
            ->with(['resi.kurir', 'completer', 'items'])
            ->leftJoin('shipment_scan_outs', 'shipment_scan_outs.resi_id', '=', 'qc_resi_scans.resi_id')
            ->leftJoin('users as scan_out_user', 'scan_out_user.id', '=', 'shipment_scan_outs.scanned_by')
            ->select('qc_resi_scans.*')
            ->selectRaw('shipment_scan_outs.scanned_at as scan_out_at')
            ->selectRaw('scan_out_user.name as scan_out_by_name')
            ->selectSub(function ($q) {
                $q->from('qc_resi_scan_items')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('qc_resi_scan_items.qc_resi_scan_id', 'qc_resi_scans.id');
            }, 'total_sku')
            ->selectSub(function ($q) {
                $q->from('qc_resi_scan_items')
                    ->selectRaw('COALESCE(SUM(scanned_qty), 0)')
                    ->whereColumn('qc_resi_scan_items.qc_resi_scan_id', 'qc_resi_scans.id');
            }, 'total_qty')
            ->where('qc_resi_scans.status', 'passed')
            ->orderByDesc('qc_resi_scans.completed_at')
            ->orderByDesc('qc_resi_scans.id');

        if ($dateFrom) {
            $baseQuery->whereDate('qc_resi_scans.completed_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $baseQuery->whereDate('qc_resi_scans.completed_at', '<=', $dateTo);
        }

        if ($tab === 'siap_scan_out') {
            $baseQuery->whereNull('shipment_scan_outs.id');
        } elseif ($tab === 'scan_out_selesai') {
            $baseQuery->whereNotNull('shipment_scan_outs.id');
        }

        $query  = clone $baseQuery;
        $search = trim((string) $request->input('q', ''));

        if ($search !== '') {
            $exact = $this->isExactSearch($request);
            $query->where(function ($q) use ($search, $exact) {
                $q->orWhereHas('resi', function ($resiQ) use ($search, $exact) {
                    $this->applyTextSearch($resiQ, 'id_pesanan', $search, $exact);
                    $this->applyTextSearch($resiQ, 'no_resi', $search, $exact, 'or');
                })->orWhereHas('resi.kurir', function ($kurirQ) use ($search, $exact) {
                    $this->applyTextSearch($kurirQ, 'name', $search, $exact);
                })->orWhereHas('items', function ($itemQ) use ($search, $exact) {
                    $this->applyTextSearch($itemQ, 'sku', $search, $exact);
                });
            });
        }

        $recordsTotal    = (clone $baseQuery)->count();
        $recordsFiltered = (clone $query)->count();

        // Stats selalu dihitung atas seluruh tanggal tanpa tab filter
        $statsBase = QcResiScan::query()
            ->leftJoin('shipment_scan_outs', 'shipment_scan_outs.resi_id', '=', 'qc_resi_scans.resi_id')
            ->where('qc_resi_scans.status', 'passed');
        if ($dateFrom) {
            $statsBase->whereDate('qc_resi_scans.completed_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $statsBase->whereDate('qc_resi_scans.completed_at', '<=', $dateTo);
        }

        $countSiap        = (clone $statsBase)->whereNull('shipment_scan_outs.id')->count();
        $countSelesai     = (clone $statsBase)->whereNotNull('shipment_scan_outs.id')->count();
        $totalQtyMenunggu = (clone $statsBase)
            ->whereNull('shipment_scan_outs.id')
            ->join('qc_resi_scan_items', 'qc_resi_scan_items.qc_resi_scan_id', '=', 'qc_resi_scans.id')
            ->sum('qc_resi_scan_items.scanned_qty');

        $start  = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 25);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $now  = now();

        $data = $query->get()->map(function ($row) use ($now) {
            $isSiap      = is_null($row->scan_out_at);
            $completedAt = $row->completed_at;
            $lamaMenit   = ($isSiap && $completedAt) ? (int) $completedAt->diffInMinutes($now) : null;

            $items = ($row->items ?? collect())->map(fn ($item) => [
                'sku'          => $item->sku ?? '-',
                'expected_qty' => (int) ($item->expected_qty ?? 0),
                'scanned_qty'  => (int) ($item->scanned_qty ?? 0),
            ])->values()->toArray();

            return [
                'id'              => $row->id,
                'completed_at'    => $completedAt?->format('Y-m-d H:i') ?? '-',
                'id_pesanan'      => $row->resi?->id_pesanan ?? '-',
                'no_resi'         => $row->resi?->no_resi ?? '-',
                'kurir'           => $row->resi?->kurir?->name ?? '-',
                'completed_by'    => $row->completer?->name ?? '-',
                'total_sku'       => (int) ($row->total_sku ?? 0),
                'total_qty'       => (int) ($row->total_qty ?? 0),
                'lama_menit'      => $lamaMenit,
                'scan_out_status' => $isSiap ? 'siap_scan_out' : 'scan_out_selesai',
                'scan_out_at'     => $row->scan_out_at ? Carbon::parse($row->scan_out_at)->format('Y-m-d H:i') : null,
                'scan_out_by'     => $row->scan_out_by_name ?? null,
                'items'           => $items,
            ];
        });

        return response()->json([
            'draw'            => (int) $request->input('draw'),
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'summary'         => [
                'siap_scan_out'      => $countSiap,
                'scan_out_selesai'   => $countSelesai,
                'total_qty_menunggu' => (int) $totalQtyMenunggu,
            ],
            'data' => $data,
        ]);
    }
}
