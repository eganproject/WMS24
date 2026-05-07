<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class StockTransferReportController extends Controller
{
    public function index()
    {
        return view('admin.reports.stock-transfers.index', [
            'dataUrl' => route('admin.reports.stock-transfers.data'),
            'warehouses' => Warehouse::orderBy('name')->get(['id', 'name']),
            'users' => User::orderBy('name')->get(['id', 'name']),
            'defaultDateFrom' => now()->startOfMonth()->toDateString(),
            'defaultDateTo' => now()->toDateString(),
        ]);
    }

    public function data(Request $request)
    {
        $baseQuery = StockTransferItem::query()
            ->with([
                'item:id,sku,name,koli_qty,address',
                'transfer:id,code,from_warehouse_id,to_warehouse_id,transacted_at,note,status,traceability_mode,legacy_reason,qc_at,qc_by,created_by',
                'transfer.fromWarehouse:id,name',
                'transfer.toWarehouse:id,name',
                'transfer.creator:id,name',
                'transfer.qcBy:id,name',
                'koliScans.koliUnit:id,code,inbound_transaction_id,koli_no,qty,sku',
                'koliScans.koliUnit.transaction:id,code,ref_no,surat_jalan_no,transacted_at',
                'koliScans.scanner:id,name',
            ]);

        $this->applyFilters($baseQuery, $request);

        $recordsFiltered = (clone $baseQuery)->count();
        $summaryQuery = clone $baseQuery;
        $summary = $summaryQuery
            ->selectRaw('COALESCE(SUM(qty), 0) as total_qty')
            ->selectRaw('COALESCE(SUM(qty_ok), 0) as total_ok')
            ->selectRaw('COALESCE(SUM(qty_reject), 0) as total_reject')
            ->selectRaw('COALESCE(SUM(qty_short), 0) as total_short')
            ->first();

        $transferCount = (clone $baseQuery)->distinct('stock_transfer_id')->count('stock_transfer_id');
        $qrCount = (clone $baseQuery)->whereHas('transfer', fn (Builder $q) => $q->where('traceability_mode', 'qr'))
            ->distinct('stock_transfer_id')
            ->count('stock_transfer_id');
        $legacyCount = (clone $baseQuery)->whereHas('transfer', fn (Builder $q) => $q->where('traceability_mode', 'legacy'))
            ->distinct('stock_transfer_id')
            ->count('stock_transfer_id');

        $start = max((int) $request->input('start', 0), 0);
        $length = (int) $request->input('length', 25);

        $dataQuery = clone $baseQuery;
        $dataQuery
            ->orderByDesc(StockTransfer::select('transacted_at')
                ->whereColumn('stock_transfers.id', 'stock_transfer_items.stock_transfer_id')
                ->limit(1))
            ->orderByDesc('id');

        if ($length > 0) {
            $dataQuery->skip($start)->take($length);
        }

        $data = $dataQuery->get()->map(fn (StockTransferItem $row) => $this->mapRow($row))->values();

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsFiltered,
            'recordsFiltered' => $recordsFiltered,
            'period' => [
                'from' => $request->input('date_from'),
                'to' => $request->input('date_to'),
                'printed_at' => now()->format('d/m/Y H:i'),
            ],
            'summary' => [
                'transfer_count' => $transferCount,
                'sku_line_count' => $recordsFiltered,
                'total_qty' => (int) ($summary->total_qty ?? 0),
                'total_ok' => (int) ($summary->total_ok ?? 0),
                'total_reject' => (int) ($summary->total_reject ?? 0),
                'total_short' => (int) ($summary->total_short ?? 0),
                'qr_count' => $qrCount,
                'legacy_count' => $legacyCount,
            ],
            'data' => $data,
        ]);
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $query->whereHas('transfer', function (Builder $transfer) use ($request) {
            $dateFrom = $request->input('date_from');
            $dateTo = $request->input('date_to');

            if ($dateFrom) {
                $transfer->whereDate('transacted_at', '>=', $dateFrom);
            }

            if ($dateTo) {
                $transfer->whereDate('transacted_at', '<=', $dateTo);
            }

            if ($request->filled('from_warehouse_id')) {
                $transfer->where('from_warehouse_id', $request->integer('from_warehouse_id'));
            }

            if ($request->filled('to_warehouse_id')) {
                $transfer->where('to_warehouse_id', $request->integer('to_warehouse_id'));
            }

            if ($request->filled('status')) {
                $transfer->where('status', $request->input('status'));
            }

            if ($request->filled('traceability_mode')) {
                $mode = $request->input('traceability_mode');
                $mode === 'none'
                    ? $transfer->whereNull('traceability_mode')
                    : $transfer->where('traceability_mode', $mode);
            }

            if ($request->filled('created_by')) {
                $transfer->where('created_by', $request->integer('created_by'));
            }

            if ($request->filled('qc_by')) {
                $transfer->where('qc_by', $request->integer('qc_by'));
            }
        });

        if ($request->input('shortage') === 'yes') {
            $query->where('qty_short', '>', 0);
        } elseif ($request->input('shortage') === 'no') {
            $query->where('qty_short', '<=', 0);
        }

        if ($request->input('reject') === 'yes') {
            $query->where('qty_reject', '>', 0);
        } elseif ($request->input('reject') === 'no') {
            $query->where('qty_reject', '<=', 0);
        }

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function (Builder $q) use ($search) {
                $q->whereHas('item', function (Builder $item) use ($search) {
                    $item->where('sku', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                })
                ->orWhereHas('transfer', function (Builder $transfer) use ($search) {
                    $transfer->where('code', 'like', "%{$search}%")
                        ->orWhere('note', 'like', "%{$search}%")
                        ->orWhere('legacy_reason', 'like', "%{$search}%");
                })
                ->orWhereHas('koliScans.koliUnit', function (Builder $koli) use ($search) {
                    $koli->where('code', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhereHas('transaction', function (Builder $inbound) use ($search) {
                            $inbound->where('code', 'like', "%{$search}%")
                                ->orWhere('ref_no', 'like', "%{$search}%")
                                ->orWhere('surat_jalan_no', 'like', "%{$search}%");
                        });
                });
            });
        }
    }

    private function mapRow(StockTransferItem $row): array
    {
        $transfer = $row->transfer;
        $item = $row->item;
        $scans = $row->koliScans;
        $inboundSources = $scans
            ->map(function ($scan) {
                $koli = $scan->koliUnit;
                $transaction = $koli?->transaction;

                if (!$koli && !$transaction) {
                    return null;
                }

                return [
                    'inbound_code' => $transaction?->code ?: '-',
                    'koli_code' => $koli?->code ?: '-',
                    'koli_no' => $koli?->koli_no,
                    'qty' => (int) ($scan->qty ?? 0),
                    'qty_ok' => (int) ($scan->qty_ok ?? 0),
                    'qty_reject' => (int) ($scan->qty_reject ?? 0),
                    'qty_short' => (int) ($scan->qty_short ?? 0),
                    'scanned_at' => $scan->scanned_at?->format('d/m/Y H:i'),
                    'scanner' => $scan->scanner?->name ?: '-',
                ];
            })
            ->filter()
            ->values();

        return [
            'transfer_code' => $transfer?->code ?: '-',
            'date' => $transfer?->transacted_at?->format('d/m/Y H:i') ?: '-',
            'date_sort' => $transfer?->transacted_at?->timestamp ?: 0,
            'from_warehouse' => $transfer?->fromWarehouse?->name ?: '-',
            'to_warehouse' => $transfer?->toWarehouse?->name ?: '-',
            'status' => $transfer?->status ?: '-',
            'status_label' => $this->statusLabel((string) ($transfer?->status ?: '')),
            'traceability_mode' => $transfer?->traceability_mode ?: 'none',
            'traceability_label' => $this->traceabilityLabel($transfer?->traceability_mode, $transfer?->legacy_reason),
            'legacy_reason' => $transfer?->legacy_reason ?: '',
            'sku' => $item?->sku ?: '-',
            'item_name' => $item?->name ?: '-',
            'koli_qty' => (int) ($item?->koli_qty ?? 0),
            'item_address' => $item?->address ?: '-',
            'qty' => (int) ($row->qty ?? 0),
            'qty_ok' => (int) ($row->qty_ok ?? 0),
            'qty_reject' => (int) ($row->qty_reject ?? 0),
            'qty_short' => (int) ($row->qty_short ?? 0),
            'note' => $row->note ?: '',
            'qc_note' => $row->qc_note ?: '',
            'transfer_note' => $transfer?->note ?: '',
            'created_by' => $transfer?->creator?->name ?: '-',
            'qc_by' => $transfer?->qcBy?->name ?: '-',
            'qc_at' => $transfer?->qc_at?->format('d/m/Y H:i') ?: '-',
            'inbound_sources' => $inboundSources,
        ];
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'qc_pending' => 'Menunggu QC',
            'completed' => 'Selesai',
            'canceled' => 'Dibatalkan',
            default => $status ?: '-',
        };
    }

    private function traceabilityLabel(?string $mode, ?string $legacyReason): string
    {
        return match ($mode) {
            'qr' => 'QR Inbound',
            'legacy' => 'Legacy / Tanpa QR' . ($legacyReason ? ': ' . $legacyReason : ''),
            default => '-',
        };
    }
}
