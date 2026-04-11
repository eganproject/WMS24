<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Exports\PackerTransitStatusExport;
use App\Exports\PickerTransitStatusExport;
use App\Exports\QcTransitStatusExport;
use App\Models\PackerTransitHistory;
use App\Models\PickerTransitItem;
use App\Models\QcResiScan;
use App\Support\QcTransitStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class PickerTransitController extends Controller
{
    public function index()
    {
        return view('admin.inventory.picker-transit.index', [
            'dataUrl' => route('admin.inventory.picker-transit.data'),
            'dataUrlQc' => route('admin.inventory.picker-transit.qc-data'),
            'dataUrlPacker' => route('admin.inventory.picker-transit.packer-data'),
            'today' => now()->toDateString(),
        ]);
    }

    public function data(Request $request)
    {
        $baseQuery = PickerTransitItem::query()
            ->with('item')
            ->orderBy('picked_date', 'desc')
            ->orderBy('id', 'desc');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $baseQuery->whereHas('item', function ($itemQ) use ($search) {
                $itemQ->where('sku', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $this->applyDateFilter($baseQuery, $request);

        $recordsTotal = PickerTransitItem::count();
        $summaryQuery = clone $baseQuery;
        $summary = [
            'ongoing' => (clone $summaryQuery)->where('remaining_qty', '>', 0)->count(),
            'done' => (clone $summaryQuery)->where('remaining_qty', '<=', 0)->count(),
        ];

        $query = clone $baseQuery;
        $this->applyPickerStatusFilter($query, $request);
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $data = $query->get()->map(function ($row) {
            $item = $row->item;
            return [
                'date' => $row->picked_date?->format('Y-m-d') ?? '-',
                'sku' => $item?->sku ?? '-',
                'name' => $item?->name ?? '-',
                'qty' => (int) $row->qty,
                'remaining_qty' => (int) $row->remaining_qty,
                'picked_at' => $row->picked_at?->format('Y-m-d H:i') ?? '-',
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'summary' => $summary,
            'data' => $data,
        ]);
    }

    public function dataPacker(Request $request)
    {
        $baseQuery = PackerTransitHistory::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $baseQuery->where(function ($q) use ($search) {
                $q->where('id_pesanan', 'like', "%{$search}%")
                    ->orWhere('no_resi', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%");
            });
        }

        $this->applyPackerDateFilter($baseQuery, $request);

        $recordsTotal = PackerTransitHistory::count();
        $summaryQuery = clone $baseQuery;
        $summary = [
            'pending' => (clone $summaryQuery)->where('status', 'menunggu scan out')->count(),
            'done' => (clone $summaryQuery)->where('status', 'selesai')->count(),
        ];

        $query = clone $baseQuery;
        $this->applyPackerStatusFilter($query, $request);
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $data = $query->get()->map(function ($row) {
            return [
                'created_at' => $row->created_at?->format('Y-m-d H:i') ?? '-',
                'id_pesanan' => $row->id_pesanan ?? '-',
                'no_resi' => $row->no_resi ?? '-',
                'status' => $row->status ?? '-',
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'summary' => $summary,
            'data' => $data,
        ]);
    }

    public function dataQc(Request $request)
    {
        $baseQuery = QcResiScan::query()
            ->with(['resi', 'scanner', 'completer'])
            ->select('qc_resi_scans.*')
            ->selectSub(function ($sub) {
                $sub->from('packer_resi_scans')
                    ->selectRaw('count(1)')
                    ->whereColumn('packer_resi_scans.resi_id', 'qc_resi_scans.resi_id');
            }, 'packer_scan_count')
            ->orderByDesc('started_at')
            ->orderByDesc('id');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $baseQuery->where(function ($q) use ($search) {
                $q->where('scan_code', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhereHas('resi', function ($resiQ) use ($search) {
                        $resiQ->where('id_pesanan', 'like', "%{$search}%")
                            ->orWhere('no_resi', 'like', "%{$search}%");
                    })
                    ->orWhereHas('scanner', function ($userQ) use ($search) {
                        $userQ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('completer', function ($userQ) use ($search) {
                        $userQ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $this->applyQcDateFilter($baseQuery, $request);

        $recordsTotal = QcResiScan::count();
        $summaryQuery = clone $baseQuery;
        $summary = [
            'draft' => (clone $summaryQuery)->where('status', 'draft')->count(),
            'ready_packing' => $this->applyQcReadyPackingFilter(clone $summaryQuery)->count(),
        ];

        $query = clone $baseQuery;
        $this->applyQcStatusFilter($query, $request);
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $data = $query->get()->map(function ($row) {
            $packerScanned = (int) ($row->packer_scan_count ?? 0) > 0;
            $nextStage = QcTransitStatus::nextStageKey($row->status, $packerScanned);

            return [
                'started_at' => $row->started_at?->format('Y-m-d H:i') ?? '-',
                'completed_at' => $row->completed_at?->format('Y-m-d H:i') ?? '-',
                'id_pesanan' => $row->resi?->id_pesanan ?? '-',
                'no_resi' => $row->resi?->no_resi ?? '-',
                'status' => $row->status ?? 'draft',
                'status_label' => QcTransitStatus::scanStatusLabel($row->status),
                'status_badge' => QcTransitStatus::scanStatusBadgeClass($row->status),
                'next_stage' => $nextStage,
                'next_stage_label' => QcTransitStatus::nextStageLabel($nextStage),
                'next_stage_badge' => QcTransitStatus::nextStageBadgeClass($nextStage),
                'scanner' => $row->scanner?->name ?? '-',
                'completed_by' => $row->completer?->name ?? '-',
                'scan_code' => $row->scan_code ?? '-',
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'summary' => $summary,
            'data' => $data,
        ]);
    }

    public function exportPickerStatus(Request $request)
    {
        $filters = [
            'q' => $request->input('q', ''),
            'date' => $request->input('date'),
            'status' => $request->input('status', ''),
        ];

        $date = $filters['date'] ?: now()->toDateString();
        $suffix = $filters['status'] ?: 'all';
        $filename = "picker-transit-{$date}-{$suffix}.xlsx";

        return Excel::download(new PickerTransitStatusExport($filters), $filename);
    }

    public function exportPackerStatus(Request $request)
    {
        $filters = [
            'q' => $request->input('q', ''),
            'date' => $request->input('date'),
            'status' => $request->input('status', ''),
        ];

        $date = $filters['date'] ?: now()->toDateString();
        $suffix = $filters['status'] ?: 'all';
        $filename = "packer-transit-{$date}-{$suffix}.xlsx";

        return Excel::download(new PackerTransitStatusExport($filters), $filename);
    }

    public function exportQcStatus(Request $request)
    {
        $filters = [
            'q' => $request->input('q', ''),
            'date' => $request->input('date'),
            'status' => $request->input('status', ''),
        ];

        $date = $filters['date'] ?: now()->toDateString();
        $suffix = $filters['status'] ?: 'all';
        $filename = "qc-transit-{$date}-{$suffix}.xlsx";

        return Excel::download(new QcTransitStatusExport($filters), $filename);
    }

    private function applyDateFilter($query, Request $request): void
    {
        $date = $request->input('date') ?: now()->toDateString();

        try {
            if ($date) {
                $target = Carbon::parse($date)->toDateString();
                $query->where('picked_date', $target);
            }
        } catch (\Throwable) {
            // ignore invalid date filters
        }
    }

    private function applyPackerDateFilter($query, Request $request): void
    {
        $date = $request->input('date') ?: now()->toDateString();

        try {
            if ($date) {
                $target = Carbon::parse($date)->toDateString();
                $query->whereDate('created_at', $target);
            }
        } catch (\Throwable) {
            // ignore invalid date filters
        }
    }

    private function applyPickerStatusFilter($query, Request $request): void
    {
        $status = (string) $request->input('status', '');
        if ($status === 'ongoing') {
            $query->where('remaining_qty', '>', 0);
        } elseif ($status === 'done') {
            $query->where('remaining_qty', '<=', 0);
        }
    }

    private function applyPackerStatusFilter($query, Request $request): void
    {
        $status = (string) $request->input('status', '');
        if ($status === 'pending') {
            $query->where('status', 'menunggu scan out');
        } elseif ($status === 'done') {
            $query->where('status', 'selesai');
        }
    }

    private function applyQcDateFilter($query, Request $request): void
    {
        $date = $request->input('date') ?: now()->toDateString();

        try {
            if ($date) {
                $target = Carbon::parse($date)->toDateString();
                $query->whereDate('started_at', $target);
            }
        } catch (\Throwable) {
            // ignore invalid date filters
        }
    }

    private function applyQcStatusFilter($query, Request $request): void
    {
        $status = (string) $request->input('status', '');
        if ($status === 'draft') {
            $query->where('status', 'draft');
            return;
        }

        if ($status === 'ready_packing') {
            $this->applyQcReadyPackingFilter($query);
            return;
        }

        if ($status === 'forwarded') {
            $query->whereExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('packer_resi_scans')
                    ->whereColumn('packer_resi_scans.resi_id', 'qc_resi_scans.resi_id');
            });
        }
    }

    private function applyQcReadyPackingFilter($query)
    {
        return $query->where('status', 'passed')
            ->whereNotExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('packer_resi_scans')
                    ->whereColumn('packer_resi_scans.resi_id', 'qc_resi_scans.resi_id');
            });
    }

}
