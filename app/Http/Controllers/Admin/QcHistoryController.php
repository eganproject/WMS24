<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\QcResiScan;
use App\Support\QcTransitStatus;
use Illuminate\Http\Request;

class QcHistoryController extends Controller
{
    public function index()
    {
        return view('admin.outbound.qc-history.index', [
            'dataUrl' => route('admin.outbound.qc-history.data'),
            'today' => now()->toDateString(),
            'statusOptions' => [
                ['value' => QcTransitStatus::DRAFT, 'label' => QcTransitStatus::scanStatusLabel(QcTransitStatus::DRAFT)],
                ['value' => QcTransitStatus::PASSED, 'label' => QcTransitStatus::scanStatusLabel(QcTransitStatus::PASSED)],
            ],
        ]);
    }

    public function data(Request $request)
    {
        $baseQuery = QcResiScan::query()
            ->with(['resi', 'scanner', 'items', 'completer', 'lastScanner', 'resetter'])
            ->select('qc_resi_scans.*')
            ->selectSub(function ($q) {
                $q->from('qc_resi_scan_items')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('qc_resi_scan_items.qc_resi_scan_id', 'qc_resi_scans.id');
            }, 'total_sku')
            ->selectSub(function ($q) {
                $q->from('qc_resi_scan_items')
                    ->selectRaw('COALESCE(SUM(expected_qty), 0)')
                    ->whereColumn('qc_resi_scan_items.qc_resi_scan_id', 'qc_resi_scans.id');
            }, 'total_expected_qty')
            ->selectSub(function ($q) {
                $q->from('qc_resi_scan_items')
                    ->selectRaw('COALESCE(SUM(scanned_qty), 0)')
                    ->whereColumn('qc_resi_scan_items.qc_resi_scan_id', 'qc_resi_scans.id');
            }, 'total_scanned_qty')
            ->orderByDesc('started_at')
            ->orderByDesc('id');

        $this->applyDateFilter($baseQuery, $request);
        $this->applyStatusFilter($baseQuery, $request);
        $this->applyAuditUserFilters($baseQuery, $request);

        $query = clone $baseQuery;

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('scan_code', 'like', "%{$search}%")
                    ->orWhere('scan_type', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhere('reset_reason', 'like', "%{$search}%")
                    ->orWhereHas('resi', function ($resiQ) use ($search) {
                        $resiQ->where('id_pesanan', 'like', "%{$search}%")
                            ->orWhere('no_resi', 'like', "%{$search}%");
                    })
                    ->orWhereHas('items', function ($itemQ) use ($search) {
                        $itemQ->where('sku', 'like', "%{$search}%");
                    })
                    ->orWhereHas('scanner', function ($userQ) use ($search) {
                        $userQ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('completer', function ($userQ) use ($search) {
                        $userQ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('lastScanner', function ($userQ) use ($search) {
                        $userQ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('resetter', function ($userQ) use ($search) {
                        $userQ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
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
            $skuList = ($row->items ?? collect())
                ->map(function ($item) {
                    return sprintf(
                        '%s (%d/%d)',
                        $item->sku ?? '-',
                        (int) ($item->scanned_qty ?? 0),
                        (int) ($item->expected_qty ?? 0)
                    );
                })
                ->implode(', ');

            return [
                'id' => $row->id,
                'started_at' => $row->started_at?->format('Y-m-d H:i') ?? '-',
                'completed_at' => $row->completed_at?->format('Y-m-d H:i') ?? '-',
                'scanner' => $row->scanner?->name ?? '-',
                'status' => $row->status ?? 'draft',
                'status_label' => QcTransitStatus::scanStatusLabel($row->status),
                'status_badge' => QcTransitStatus::scanStatusBadgeClass($row->status),
                'scan_type' => $row->scan_type ?? '-',
                'scan_code' => $row->scan_code ?? '-',
                'id_pesanan' => $row->resi?->id_pesanan ?? '-',
                'no_resi' => $row->resi?->no_resi ?? '-',
                'completed_by' => $row->completer?->name ?? '-',
                'last_scanned_by' => $row->lastScanner?->name ?? '-',
                'last_scanned_at' => $row->last_scanned_at?->format('Y-m-d H:i') ?? '-',
                'reset_count' => (int) ($row->reset_count ?? 0),
                'reset_by' => $row->resetter?->name ?? '-',
                'reset_at' => $row->reset_at?->format('Y-m-d H:i') ?? '-',
                'reset_reason' => $row->reset_reason ?? '',
                'sku_list' => $skuList !== '' ? $skuList : '-',
                'total_sku' => (int) ($row->total_sku ?? 0),
                'total_expected_qty' => (int) ($row->total_expected_qty ?? 0),
                'total_scanned_qty' => (int) ($row->total_scanned_qty ?? 0),
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'summary' => [
                'draft' => (clone $baseQuery)->where('status', 'draft')->count(),
                'passed' => (clone $baseQuery)->where('status', 'passed')->count(),
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
            $query->whereDate('started_at', $today);
            return;
        }

        if ($dateFrom) {
            $query->whereDate('started_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('started_at', '<=', $dateTo);
        }
    }

    private function applyStatusFilter($query, Request $request): void
    {
        $status = trim((string) $request->input('status', ''));
        if (!in_array($status, ['draft', 'passed'], true)) {
            return;
        }

        $query->where('status', $status);
    }

    private function applyAuditUserFilters($query, Request $request): void
    {
        $completedBy = trim((string) $request->input('completed_by', ''));
        if ($completedBy !== '') {
            $query->whereHas('completer', function ($userQ) use ($completedBy) {
                $userQ->where('name', 'like', "%{$completedBy}%")
                    ->orWhere('email', 'like', "%{$completedBy}%");
            });
        }

        $resetBy = trim((string) $request->input('reset_by', ''));
        if ($resetBy !== '') {
            $query->whereHas('resetter', function ($userQ) use ($resetBy) {
                $userQ->where('name', 'like', "%{$resetBy}%")
                    ->orWhere('email', 'like', "%{$resetBy}%");
            });
        }
    }
}
