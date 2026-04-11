<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PackerResiScan;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PackerHistoryController extends Controller
{
    public function index()
    {
        return view('admin.outbound.packer-history.index', [
            'dataUrl' => route('admin.outbound.packer-history.data'),
            'today' => now()->toDateString(),
        ]);
    }

    public function data(Request $request)
    {
        $baseQuery = PackerResiScan::query()
            ->with(['resi.qcScan.items', 'resi.details', 'scanner'])
            ->orderByDesc('scanned_at');

        $this->applyDateFilter($baseQuery, $request);

        $query = clone $baseQuery;

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('scan_code', 'like', "%{$search}%")
                    ->orWhere('scan_type', 'like', "%{$search}%")
                    ->orWhereHas('resi', function ($resiQ) use ($search) {
                        $resiQ->where('id_pesanan', 'like', "%{$search}%")
                            ->orWhere('no_resi', 'like', "%{$search}%");
                    })
                    ->orWhereHas('scanner', function ($userQ) use ($search) {
                        $userQ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('resi.qcScan.items', function ($itemQ) use ($search) {
                        $itemQ->where('sku', 'like', "%{$search}%");
                    })
                    ->orWhere(function ($fallbackQ) use ($search) {
                        $fallbackQ->whereDoesntHave('resi.qcScan')
                            ->whereHas('resi.details', function ($detailQ) use ($search) {
                                $detailQ->where('sku', 'like', "%{$search}%");
                            });
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
            $scanDate = $row->scan_date ? Carbon::parse($row->scan_date)->format('Y-m-d') : '';
            $scannedAt = $row->scanned_at ? Carbon::parse($row->scanned_at)->format('Y-m-d H:i') : '';
            $details = $this->resolvePackingItems($row);
            $skuTotals = [];
            foreach ($details as $detail) {
                $sku = trim((string) $detail->sku);
                $qty = (int) ($detail->qty ?? $detail->expected_qty ?? 0);
                if ($sku === '' || $qty <= 0) {
                    continue;
                }
                $skuTotals[$sku] = ($skuTotals[$sku] ?? 0) + $qty;
            }
            $skuList = collect($skuTotals)->map(function ($qty, $sku) {
                return sprintf('%s (%d)', $sku, $qty);
            })->implode(', ');

            return [
                'id' => $row->id,
                'scan_date' => $scanDate,
                'scanned_at' => $scannedAt,
                'scanner' => $row->scanner?->name ?? '-',
                'scan_type' => $row->scan_type ?? '-',
                'scan_code' => $row->scan_code ?? '-',
                'id_pesanan' => $row->resi?->id_pesanan ?? '-',
                'no_resi' => $row->resi?->no_resi ?? '-',
                'sku_list' => $skuList !== '' ? $skuList : '-',
                'total_sku' => count($skuTotals),
                'total_qty' => array_sum($skuTotals),
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

    private function resolvePackingItems(PackerResiScan $scan)
    {
        if ($scan->resi?->qcScan) {
            return $scan->resi->qcScan->items ?? collect();
        }

        return $scan->resi?->details ?? collect();
    }
}
