<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ReturnReportExport;
use App\Http\Controllers\Controller;
use App\Models\CustomerReturn;
use App\Models\CustomerReturnItem;
use App\Models\InboundItem;
use App\Models\InboundTransaction;
use App\Models\OutboundTransaction;
use App\Support\InboundScanStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;

class ReturnReportController extends Controller
{
    public function index()
    {
        return view('admin.reports.returns.index', [
            'dataUrl' => route('admin.reports.returns.data'),
            'exportUrl' => route('admin.reports.returns.export'),
            'defaultDateFrom' => now()->subDays(29)->toDateString(),
            'defaultDateTo' => now()->toDateString(),
        ]);
    }

    public function export(Request $request)
    {
        $source = trim((string) $request->input('source', ''));
        $suffix = match ($source) {
            'customer' => 'customer',
            'inbound' => 'inbound',
            'outbound' => 'outbound',
            default => 'gabungan',
        };

        $filename = 'laporan-retur-'.$suffix.'-'.now()->format('Ymd_His').'.xlsx';

        return Excel::download(new ReturnReportExport($request->query()), $filename);
    }

    public function data(Request $request)
    {
        $customerTotalQuery = $this->buildCustomerQuery($request, false);
        $inboundTotalQuery = $this->buildInboundQuery($request, false);
        $outboundTotalQuery = $this->buildOutboundQuery($request, false);

        $recordsTotal = $customerTotalQuery->count() + $inboundTotalQuery->count() + $outboundTotalQuery->count();

        $customerRows = $this->buildCustomerQuery($request, true)
            ->get()
            ->map(fn (CustomerReturn $row) => $this->serializeCustomerReturn($row));
        $inboundRows = $this->buildInboundQuery($request, true)
            ->get()
            ->map(fn (InboundTransaction $row) => $this->serializeInboundReturn($row));
        $outboundRows = $this->buildOutboundQuery($request, true)
            ->get()
            ->map(fn (OutboundTransaction $row) => $this->serializeOutboundReturn($row));

        $merged = $customerRows
            ->concat($inboundRows)
            ->concat($outboundRows)
            ->sortByDesc('sort_at')
            ->values();

        $recordsFiltered = $merged->count();
        $summary = [
            'total_documents' => $recordsFiltered,
            'customer_documents' => $customerRows->count(),
            'inbound_documents' => $inboundRows->count(),
            'outbound_documents' => $outboundRows->count(),
            'customer_received_qty' => (int) $customerRows->sum('qty_received'),
            'customer_good_qty' => (int) $customerRows->sum('qty_good'),
            'customer_damaged_qty' => (int) $customerRows->sum('qty_damaged'),
            'customer_packaging_damaged_qty' => (int) $customerRows->sum('qty_packaging_damaged'),
            'customer_lost_qty' => (int) $customerRows->sum('qty_lost'),
            'inbound_expected_qty' => (int) $inboundRows->sum('qty_expected'),
            'inbound_scanned_qty' => (int) $inboundRows->sum('qty_received'),
            'outbound_qty' => (int) $outboundRows->sum('qty_total'),
            'unmatched_resi' => (int) $customerRows->where('matched', false)->count(),
        ];

        $start = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', 10);
        $data = $length > 0 ? $merged->slice($start, $length)->values() : $merged;

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'summary' => $summary,
            'analytics' => $this->buildAnalytics(
                trim((string) $request->input('source', '')),
                $customerRows,
                $inboundRows,
                $outboundRows
            ),
            'data' => $data->map(function (array $row) {
                unset($row['sort_at'], $row['_items'], $row['_sku_items']);

                return $row;
            })->values(),
        ]);
    }

    private function buildCustomerQuery(Request $request, bool $applySearch)
    {
        $source = (string) $request->input('source', '');
        if (in_array($source, ['inbound', 'outbound'], true)) {
            return CustomerReturn::query()->whereRaw('1 = 0');
        }

        $query = CustomerReturn::query()
            ->with(['items.item', 'creator', 'inspector', 'finalizer', 'damagedGood'])
            ->orderByDesc('received_at')
            ->orderByDesc('id');

        $status = trim((string) $request->input('status', ''));
        if (in_array($status, [
            CustomerReturn::STATUS_INSPECTED,
            CustomerReturn::STATUS_COMPLETED,
            CustomerReturn::STATUS_NO_RECEIVED,
        ], true)) {
            $query->where('status', $status);
        } elseif ($status !== '') {
            $query->whereRaw('1 = 0');
        }

        $matchState = trim((string) $request->input('match_state', ''));
        if ($matchState === 'matched') {
            $query->whereNotNull('resi_id');
        } elseif ($matchState === 'unmatched') {
            $query->whereNull('resi_id');
        }

        $resiSource = trim((string) $request->input('resi_source', ''));
        if (in_array($resiSource, array_keys(CustomerReturn::resiSourceLabels()), true)) {
            $query->where('resi_source', $resiSource);
        }

        $this->applyDateFilter($query, 'customer_returns.received_at', $request);

        if ($applySearch) {
            $search = trim((string) $request->input('q', ''));
            if ($search !== '') {
                $exact = $this->isExactSearch($request);
                $query->where(function ($q) use ($search, $exact) {
                    $this->applyTextSearch($q, 'customer_returns.code', $search, $exact);
                    $this->applyTextSearch($q, 'customer_returns.resi_no', $search, $exact, 'or');
                    $this->applyTextSearch($q, 'customer_returns.order_ref', $search, $exact, 'or');
                    $this->applyTextSearch($q, 'customer_returns.note', $search, $exact, 'or');
                    $q->orWhereHas('damagedGood', function ($damagedQ) use ($search, $exact) {
                        $this->applyTextSearch($damagedQ, 'code', $search, $exact);
                    })->orWhereHas('items', function ($itemQ) use ($search, $exact) {
                        $this->applyTextSearch($itemQ, 'root_cause', $search, $exact);
                    })->orWhereHas('items.item', function ($itemQ) use ($search, $exact) {
                        $this->applyTextSearch($itemQ, 'sku', $search, $exact);
                        $this->applyTextSearch($itemQ, 'name', $search, $exact, 'or');
                    });
                });
            }
        }

        return $query;
    }

    private function buildInboundQuery(Request $request, bool $applySearch)
    {
        $source = trim((string) $request->input('source', ''));
        if (in_array($source, ['customer', 'outbound'], true)) {
            return InboundTransaction::query()->whereRaw('1 = 0');
        }

        $query = InboundTransaction::query()
            ->with([
                'items.item',
                'warehouse',
                'creator',
                'approver',
                'scanSession.items',
                'scanSession.starter:id,name',
                'scanSession.completer:id,name',
            ])
            ->where('type', 'return')
            ->orderByDesc('transacted_at')
            ->orderByDesc('id');

        $status = trim((string) $request->input('status', ''));
        if ($status === InboundScanStatus::COMPLETED) {
            $query->whereIn('status', [InboundScanStatus::COMPLETED, 'approved']);
        } elseif (in_array($status, [InboundScanStatus::PENDING_SCAN, InboundScanStatus::SCANNING], true)) {
            $query->where('status', $status);
        } elseif ($status !== '') {
            $query->whereRaw('1 = 0');
        }

        $this->applyDateFilter($query, 'inbound_transactions.transacted_at', $request);

        if ($applySearch) {
            $search = trim((string) $request->input('q', ''));
            if ($search !== '') {
                $exact = $this->isExactSearch($request);
                $query->where(function ($q) use ($search, $exact) {
                    $this->applyTextSearch($q, 'inbound_transactions.code', $search, $exact);
                    $this->applyTextSearch($q, 'inbound_transactions.ref_no', $search, $exact, 'or');
                    $this->applyTextSearch($q, 'inbound_transactions.surat_jalan_no', $search, $exact, 'or');
                    $this->applyTextSearch($q, 'inbound_transactions.note', $search, $exact, 'or');
                    $q->orWhereHas('warehouse', function ($warehouseQ) use ($search, $exact) {
                        $this->applyTextSearch($warehouseQ, 'name', $search, $exact);
                    })->orWhereHas('items.item', function ($itemQ) use ($search, $exact) {
                        $this->applyTextSearch($itemQ, 'sku', $search, $exact);
                        $this->applyTextSearch($itemQ, 'name', $search, $exact, 'or');
                    });
                });
            }
        }

        return $query;
    }

    private function buildOutboundQuery(Request $request, bool $applySearch)
    {
        $source = (string) $request->input('source', '');
        if (in_array($source, ['customer', 'inbound'], true)) {
            return OutboundTransaction::query()->whereRaw('1 = 0');
        }

        $query = OutboundTransaction::query()
            ->with(['items.item', 'creator', 'approver', 'supplier', 'warehouse'])
            ->where('type', 'return')
            ->orderByDesc('transacted_at')
            ->orderByDesc('id');

        $status = trim((string) $request->input('status', ''));
        if (in_array($status, ['pending', 'approved'], true)) {
            $query->where('status', $status);
        } elseif ($status !== '') {
            $query->whereRaw('1 = 0');
        }

        $this->applyDateFilter($query, 'outbound_transactions.transacted_at', $request);

        if ($applySearch) {
            $search = trim((string) $request->input('q', ''));
            if ($search !== '') {
                $exact = $this->isExactSearch($request);
                $query->where(function ($q) use ($search, $exact) {
                    $this->applyTextSearch($q, 'outbound_transactions.code', $search, $exact);
                    $this->applyTextSearch($q, 'outbound_transactions.ref_no', $search, $exact, 'or');
                    $this->applyTextSearch($q, 'outbound_transactions.note', $search, $exact, 'or');
                    $q->orWhereHas('supplier', function ($supplierQ) use ($search, $exact) {
                        $this->applyTextSearch($supplierQ, 'name', $search, $exact);
                    })->orWhereHas('items.item', function ($itemQ) use ($search, $exact) {
                        $this->applyTextSearch($itemQ, 'sku', $search, $exact);
                        $this->applyTextSearch($itemQ, 'name', $search, $exact, 'or');
                    });
                });
            }
        }

        return $query;
    }

    private function serializeCustomerReturn(CustomerReturn $row): array
    {
        $items = $row->items ?? collect();
        $qtyExpected = (int) $items->sum('expected_qty');
        $qtyReceived = (int) $items->sum('received_qty');
        $qtyGood = (int) $items->sum('good_qty');
        $qtyPackagingDamaged = (int) $items->sum('packaging_damaged_qty');
        $qtyDamaged = (int) $items->sum('damaged_qty');
        $qtyLost = (int) $items->sum(fn (CustomerReturnItem $item) => max((int) $item->expected_qty - (int) $item->received_qty, 0));
        $skuItems = $items->map(function (CustomerReturnItem $item) use ($row) {
            $target = (int) $item->expected_qty;
            $actual = (int) $item->received_qty;

            return [
                'document_key' => 'customer-'.$row->id,
                'sku' => trim((string) ($item->item?->sku ?? '')),
                'name' => trim((string) ($item->item?->name ?? '')),
                'target_qty' => $target,
                'actual_qty' => $actual,
                'damaged_qty' => (int) ($item->packaging_damaged_qty ?? 0) + (int) $item->damaged_qty,
                'lost_qty' => max($target - $actual, 0),
                'exception_qty' => (int) ($item->packaging_damaged_qty ?? 0)
                    + (int) $item->damaged_qty
                    + max($target - $actual, 0),
            ];
        });

        return [
            'row_key' => 'customer-'.$row->id,
            'report_source' => 'customer',
            'source_label' => 'Retur Customer',
            'source_badge' => 'badge-light-primary',
            'sort_at' => $row->received_at?->timestamp ?? 0,
            'transacted_at' => $row->received_at?->format('Y-m-d H:i') ?? '-',
            'date_key' => $row->received_at?->toDateString() ?? '-',
            'code' => $row->code,
            'ref_primary_label' => 'Resi',
            'ref_primary_value' => $row->resi_no ?: '-',
            'ref_secondary_label' => 'Order Ref',
            'ref_secondary_value' => $row->order_ref ?: '-',
            'counterparty_label' => 'Sumber',
            'counterparty_value' => $row->resiSourceLabel() ?: ($row->resi_id ? 'Marketplace / Match Resi' : 'Input Manual'),
            'extra_reference' => $row->damagedGood?->code,
            'extra_reference_label' => $row->damagedGood?->code ? 'Barang Rusak' : null,
            'status' => $row->status,
            'status_label' => $row->statusLabel(),
            'status_badge' => 'badge-light-'.$row->statusBadgeClass(),
            'matched' => (bool) $row->resi_id,
            'item_summary' => $this->buildCustomerItemSummary($items),
            'sku_count' => $skuItems->pluck('sku')->filter()->unique()->count(),
            'qty_expected' => $qtyExpected,
            'qty_received' => $qtyReceived,
            'qty_good' => $qtyGood,
            'qty_packaging_damaged' => $qtyPackagingDamaged,
            'qty_damaged' => $qtyDamaged,
            'qty_damaged_total' => $qtyPackagingDamaged + $qtyDamaged,
            'qty_lost' => $qtyLost,
            'qty_total' => $qtyReceived,
            'qty_variance' => $qtyReceived - $qtyExpected,
            'quality_rate' => $this->rate($qtyGood, $qtyReceived),
            'submit_by' => $row->creator?->name ?? '-',
            'secondary_by' => $row->inspector?->name ?? '-',
            'secondary_by_label' => 'Inspector',
            'tertiary_by' => $row->finalizer?->name ?? '-',
            'tertiary_by_label' => 'PIC Final',
            'performance_operator' => $row->inspector?->name ?? 'Belum Ada Inspector',
            'is_success' => $row->isCompleted(),
            'has_exception' => ($qtyPackagingDamaged + $qtyDamaged + $qtyLost) > 0,
            'input_lead_minutes' => $this->minutesBetween($row->received_at, $row->created_at),
            'lead_minutes' => $this->minutesBetween($row->received_at, $row->finalized_at),
            'aging_minutes' => $row->finalized_at ? null : $this->minutesBetween($row->received_at, now()),
            'reset_count' => 0,
            'note' => $row->note ?? '',
            'detail_url' => route('admin.inventory.customer-returns.show', $row->id),
            'detail_label' => 'Detail',
            '_items' => $items,
            '_sku_items' => $skuItems,
        ];
    }

    private function serializeInboundReturn(InboundTransaction $row): array
    {
        $items = $row->items ?? collect();
        $scanItems = $row->scanSession?->items ?? collect();
        $qtyExpected = (int) $items->sum('qty');
        $unitExpected = (int) $items->sum(fn (InboundItem $item) => ($item->input_unit ?: 'koli') === 'pcs'
            ? (int) $item->qty
            : (int) ($item->koli ?? 0));
        $qtyScanned = (int) $scanItems->sum('scanned_qty');
        $unitScanned = (int) $scanItems->sum('scanned_koli');
        $status = ($row->status ?? InboundScanStatus::PENDING_SCAN) === 'approved'
            ? InboundScanStatus::COMPLETED
            : ($row->status ?? InboundScanStatus::PENDING_SCAN);

        if ($status === InboundScanStatus::COMPLETED && $scanItems->isEmpty()) {
            $qtyScanned = $qtyExpected;
            $unitScanned = $unitExpected;
        }

        $startedAt = $row->scanSession?->started_at;
        $completedAt = $row->scanSession?->completed_at ?? $row->approved_at;
        $variance = $qtyScanned - $qtyExpected;
        $scanItemsByItem = $scanItems->keyBy('item_id');
        $skuItems = $items->map(function (InboundItem $item) use ($row, $scanItems, $scanItemsByItem, $status) {
            $target = (int) $item->qty;
            $scanItem = $scanItemsByItem->get($item->item_id);
            $actual = $scanItem
                ? (int) $scanItem->scanned_qty
                : ($status === InboundScanStatus::COMPLETED && $scanItems->isEmpty() ? $target : 0);

            return [
                'document_key' => 'inbound-'.$row->id,
                'sku' => trim((string) ($item->item?->sku ?? '')),
                'name' => trim((string) ($item->item?->name ?? '')),
                'target_qty' => $target,
                'actual_qty' => $actual,
                'damaged_qty' => 0,
                'lost_qty' => $status === InboundScanStatus::COMPLETED ? max($target - $actual, 0) : 0,
                'exception_qty' => abs($actual - $target),
            ];
        });

        return [
            'row_key' => 'inbound-'.$row->id,
            'report_source' => 'inbound',
            'source_label' => 'Retur Inbound',
            'source_badge' => 'badge-light-success',
            'sort_at' => $row->transacted_at?->timestamp ?? 0,
            'transacted_at' => $row->transacted_at?->format('Y-m-d H:i') ?? '-',
            'date_key' => $row->transacted_at?->toDateString() ?? '-',
            'code' => $row->code,
            'ref_primary_label' => 'Referensi',
            'ref_primary_value' => $row->ref_no ?: '-',
            'ref_secondary_label' => 'No. Retur',
            'ref_secondary_value' => $row->surat_jalan_no ?: '-',
            'counterparty_label' => 'Gudang Tujuan',
            'counterparty_value' => $row->warehouse?->name ?: '-',
            'extra_reference' => null,
            'extra_reference_label' => null,
            'status' => $status,
            'status_label' => InboundScanStatus::label($status),
            'status_badge' => match ($status) {
                InboundScanStatus::COMPLETED => 'badge-light-success',
                InboundScanStatus::SCANNING => 'badge-light-primary',
                default => 'badge-light-warning',
            },
            'matched' => null,
            'item_summary' => $this->buildInboundItemSummary($items),
            'sku_count' => $skuItems->pluck('sku')->filter()->unique()->count(),
            'qty_expected' => $qtyExpected,
            'qty_received' => $qtyScanned,
            'qty_good' => 0,
            'qty_packaging_damaged' => 0,
            'qty_damaged' => 0,
            'qty_damaged_total' => 0,
            'qty_lost' => max($qtyExpected - $qtyScanned, 0),
            'qty_total' => $qtyScanned,
            'qty_variance' => $variance,
            'unit_expected' => $unitExpected,
            'unit_scanned' => $unitScanned,
            'progress_percent' => $this->rate($qtyScanned, $qtyExpected),
            'submit_by' => $row->creator?->name ?? '-',
            'secondary_by' => $row->scanSession?->starter?->name ?? '-',
            'secondary_by_label' => 'Mulai Scan',
            'tertiary_by' => $row->scanSession?->completer?->name ?? $row->approver?->name ?? '-',
            'tertiary_by_label' => 'Complete Oleh',
            'performance_operator' => $row->scanSession?->completer?->name
                ?? $row->scanSession?->starter?->name
                ?? 'Belum Ada Operator',
            'is_success' => $status === InboundScanStatus::COMPLETED,
            'has_exception' => $status === InboundScanStatus::COMPLETED && $variance !== 0,
            'waiting_minutes' => $this->minutesBetween($row->transacted_at, $startedAt),
            'scan_minutes' => $this->minutesBetween($startedAt, $completedAt),
            'lead_minutes' => $this->minutesBetween($row->transacted_at, $completedAt),
            'aging_minutes' => $completedAt ? null : $this->minutesBetween($row->transacted_at, now()),
            'reset_count' => (int) ($row->scanSession?->reset_count ?? 0),
            'note' => $row->note ?? '',
            'detail_url' => route('admin.inbound.returns.detail', $row->id),
            'detail_label' => 'Detail',
            '_sku_items' => $skuItems,
        ];
    }

    private function serializeOutboundReturn(OutboundTransaction $row): array
    {
        $items = $row->items ?? collect();
        $qtyTotal = (int) $items->sum('qty');
        $approved = ($row->status ?? 'pending') === 'approved';
        $skuItems = $items->map(function ($item) use ($row, $approved) {
            $qty = (int) ($item->qty ?? 0);

            return [
                'document_key' => 'outbound-'.$row->id,
                'sku' => trim((string) ($item->item?->sku ?? '')),
                'name' => trim((string) ($item->item?->name ?? '')),
                'target_qty' => $qty,
                'actual_qty' => $approved ? $qty : 0,
                'damaged_qty' => 0,
                'lost_qty' => 0,
                'exception_qty' => $approved ? 0 : $qty,
            ];
        });

        return [
            'row_key' => 'outbound-'.$row->id,
            'report_source' => 'outbound',
            'source_label' => 'Retur Outbound',
            'source_badge' => 'badge-light-danger',
            'sort_at' => $row->transacted_at?->timestamp ?? 0,
            'transacted_at' => $row->transacted_at?->format('Y-m-d H:i') ?? '-',
            'date_key' => $row->transacted_at?->toDateString() ?? '-',
            'code' => $row->code,
            'ref_primary_label' => 'Supplier',
            'ref_primary_value' => $row->supplier?->name ?: '-',
            'ref_secondary_label' => 'Ref No',
            'ref_secondary_value' => $row->ref_no ?: '-',
            'counterparty_label' => 'Gudang Asal',
            'counterparty_value' => $row->warehouse?->name ?: '-',
            'extra_reference' => null,
            'extra_reference_label' => null,
            'status' => $row->status ?? 'pending',
            'status_label' => $approved ? 'Disetujui' : 'Menunggu Approval',
            'status_badge' => $approved ? 'badge-light-success' : 'badge-light-warning',
            'matched' => null,
            'item_summary' => $this->buildOutboundItemSummary($items),
            'sku_count' => $skuItems->pluck('sku')->filter()->unique()->count(),
            'qty_expected' => 0,
            'qty_received' => 0,
            'qty_good' => 0,
            'qty_packaging_damaged' => 0,
            'qty_damaged' => 0,
            'qty_damaged_total' => 0,
            'qty_lost' => 0,
            'qty_total' => $qtyTotal,
            'qty_variance' => 0,
            'submit_by' => $row->creator?->name ?? '-',
            'secondary_by' => $row->approver?->name ?? '-',
            'secondary_by_label' => 'Approver',
            'tertiary_by' => '-',
            'tertiary_by_label' => null,
            'performance_operator' => $row->approver?->name ?? ($approved ? 'Approver Tidak Tercatat' : 'Belum Diapprove'),
            'is_success' => $approved,
            'has_exception' => ! $approved,
            'lead_minutes' => $this->minutesBetween($row->created_at, $row->approved_at),
            'aging_minutes' => $approved ? null : $this->minutesBetween($row->created_at, now()),
            'reset_count' => 0,
            'note' => $row->note ?? '',
            'detail_url' => route('admin.outbound.returns.detail', $row->id),
            'detail_label' => 'Detail',
            '_sku_items' => $skuItems,
        ];
    }

    private function buildCustomerItemSummary(Collection $items): string
    {
        return $items->map(function (CustomerReturnItem $item) {
            $sku = trim((string) ($item->item?->sku ?? ''));
            if ($sku === '') {
                return '';
            }

            return sprintf(
                '%s (Target %d, Terima %d, Bagus %d, Kemasan %d, Rusak %d, Penyebab: %s)',
                $sku,
                (int) $item->expected_qty,
                (int) $item->received_qty,
                (int) $item->good_qty,
                (int) ($item->packaging_damaged_qty ?? 0),
                (int) $item->damaged_qty,
                $item->rootCauseLabel()
            );
        })->filter()->implode('||');
    }

    private function buildInboundItemSummary(Collection $items): string
    {
        return $items->map(function (InboundItem $item) {
            $sku = trim((string) ($item->item?->sku ?? ''));
            if ($sku === '') {
                return '';
            }

            $unit = ($item->input_unit ?: 'koli') === 'pcs' ? 'unit' : 'koli';
            $unitQty = $unit === 'unit' ? (int) $item->qty : (int) ($item->koli ?? 0);

            return sprintf('%s (Target %d pcs / %d %s)', $sku, (int) $item->qty, $unitQty, $unit);
        })->filter()->implode('||');
    }

    private function buildOutboundItemSummary(Collection $items): string
    {
        return $items->map(function ($item) {
            $sku = trim((string) ($item->item?->sku ?? ''));

            return $sku === '' ? '' : sprintf('%s (%d pcs)', $sku, (int) ($item->qty ?? 0));
        })->filter()->implode('||');
    }

    private function buildAnalytics(
        string $source,
        Collection $customerRows,
        Collection $inboundRows,
        Collection $outboundRows
    ): array {
        return match ($source) {
            'inbound' => $this->inboundAnalytics($inboundRows),
            'outbound' => $this->outboundAnalytics($outboundRows),
            default => $this->customerAnalytics($customerRows),
        };
    }

    private function customerAnalytics(Collection $rows): array
    {
        $items = $rows->flatMap(fn (array $row) => $row['_items'] ?? collect());
        $finalized = $rows->where('is_success', true);
        $received = (int) $rows->sum('qty_received');
        $damaged = (int) $rows->sum('qty_damaged_total');
        $rootCauseFilled = $items->filter(fn (CustomerReturnItem $item) => filled($item->root_cause))->count();
        $rootCauses = $items
            ->groupBy(fn (CustomerReturnItem $item) => $item->rootCauseLabel())
            ->map(fn (Collection $group, string $label) => [
                'label' => $label,
                'total' => (int) $group->sum('received_qty'),
            ])->sortByDesc('total')->values()->all();
        $skuAnalytics = $this->skuAnalytics($rows, 'Qty Resi', 'Qty Diterima', 'Qty Rusak/Hilang');

        return $this->analyticsPayload(
            'customer',
            'Analisis Retur Customer',
            'Kualitas barang kembali, kelengkapan root cause, kecocokan resi, dan kecepatan finalisasi.',
            [
                $this->metric('Dokumen Retur', $rows->count(), 'number', 'primary', 'Dokumen diterima pada periode filter'),
                $this->metric('Finalization Rate', $this->rate($finalized->count(), $rows->count()), 'percent', 'success', $rows->where('status', CustomerReturn::STATUS_INSPECTED)->count().' dokumen belum final'),
                $this->metric('Good Recovery Rate', $this->rate((int) $rows->sum('qty_good'), $received), 'percent', 'info', number_format((int) $rows->sum('qty_good'), 0, ',', '.').' dari '.number_format($received, 0, ',', '.').' qty diterima'),
                $this->metric('Damage Rate', $this->rate($damaged, $received), 'percent', 'danger', 'Termasuk rusak kemasan dan rusak produk'),
            ],
            [
                $this->metric('Qty Diterima', $received, 'number', 'primary'),
                $this->metric('Qty Kemasan Rusak', (int) $rows->sum('qty_packaging_damaged'), 'number', 'warning'),
                $this->metric('Qty Rusak Produk', (int) $rows->sum('qty_damaged'), 'number', 'danger'),
                $this->metric('Qty Tidak Kembali', (int) $rows->sum('qty_lost'), 'number', 'warning'),
                $this->metric('Resi Match Rate', $this->rate($rows->where('matched', true)->count(), $rows->count()), 'percent', 'success'),
                $this->metric('Root Cause Lengkap', $this->rate($rootCauseFilled, $items->count()), 'percent', 'info'),
                $this->metric('Rata-rata Finalisasi', $this->average($finalized->pluck('lead_minutes')), 'duration', 'primary'),
                $this->metric('Open > 24 Jam', $rows->filter(fn (array $row) => ($row['aging_minutes'] ?? 0) > 1440)->count(), 'number', 'danger'),
                $this->metric('SKU Unik', $skuAnalytics['total_unique'], 'number', 'primary'),
                $this->metric('Rata-rata SKU/Dokumen', $skuAnalytics['avg_per_document'], 'decimal', 'info'),
            ],
            $this->dailyAnalytics($rows, 'qty_received', 'qty_damaged_total'),
            $this->statusAnalytics($rows),
            'Penyebab Retur berdasarkan Qty Diterima',
            $rootCauses,
            'Inspector',
            'Qty Disortir',
            'Qty Rusak/Hilang',
            $this->performanceAnalytics(
                $rows,
                'qty_received',
                fn (array $row) => (int) $row['qty_damaged_total'] + (int) $row['qty_lost']
            ),
            $skuAnalytics
        );
    }

    private function inboundAnalytics(Collection $rows): array
    {
        $completed = $rows->where('status', InboundScanStatus::COMPLETED);
        $variance = $completed->filter(fn (array $row) => (int) $row['qty_variance'] !== 0);
        $expected = (int) $rows->sum('qty_expected');
        $scanned = (int) $rows->sum('qty_received');
        $warehouses = $rows
            ->groupBy('counterparty_value')
            ->map(fn (Collection $group, string $label) => [
                'label' => $label,
                'total' => (int) $group->sum('qty_received'),
            ])->sortByDesc('total')->values()->all();
        $skuAnalytics = $this->skuAnalytics($rows, 'Qty Target', 'Qty Discan', 'Selisih Absolut');

        return $this->analyticsPayload(
            'inbound',
            'Analisis Retur Inbound',
            'Kecepatan scan, akurasi penerimaan fisik, variance, reset, dan produktivitas operator.',
            [
                $this->metric('Dokumen Retur', $rows->count(), 'number', 'primary'),
                $this->metric('Completion Rate', $this->rate($completed->count(), $rows->count()), 'percent', 'success', $rows->where('status', InboundScanStatus::PENDING_SCAN)->count().' menunggu · '.$rows->where('status', InboundScanStatus::SCANNING)->count().' scan'),
                $this->metric('Akurasi Qty', $this->rate($scanned, $expected), 'percent', 'info', number_format($scanned, 0, ',', '.').' dari '.number_format($expected, 0, ',', '.').' target'),
                $this->metric('Dokumen Berselisih', $variance->count(), 'number', 'danger', 'Completed dengan hasil scan berbeda dari target'),
            ],
            [
                $this->metric('Target Qty', $expected, 'number', 'primary'),
                $this->metric('Qty Fisik Masuk', $scanned, 'number', 'success'),
                $this->metric('Selisih Qty', $scanned - $expected, 'signed', 'danger'),
                $this->metric('Total Reset Scan', (int) $rows->sum('reset_count'), 'number', 'warning'),
                $this->metric('Rata-rata Tunggu Scan', $this->average($rows->pluck('waiting_minutes')), 'duration', 'warning'),
                $this->metric('Rata-rata Proses Scan', $this->average($completed->pluck('scan_minutes')), 'duration', 'primary'),
                $this->metric('Rata-rata Total Lead', $this->average($completed->pluck('lead_minutes')), 'duration', 'info'),
                $this->metric('Open > 24 Jam', $rows->filter(fn (array $row) => ($row['aging_minutes'] ?? 0) > 1440)->count(), 'number', 'danger'),
                $this->metric('SKU Unik', $skuAnalytics['total_unique'], 'number', 'primary'),
                $this->metric('Rata-rata SKU/Dokumen', $skuAnalytics['avg_per_document'], 'decimal', 'info'),
            ],
            $this->dailyAnalytics($rows, 'qty_received', fn (array $row) => abs((int) $row['qty_variance'])),
            $this->statusAnalytics($rows),
            'Qty Fisik Masuk per Gudang Tujuan',
            $warehouses,
            'Operator Scan',
            'Qty Discan',
            'Dokumen Selisih',
            $this->performanceAnalytics($rows, 'qty_received', fn (array $row) => $row['has_exception'] ? 1 : 0),
            $skuAnalytics
        );
    }

    private function outboundAnalytics(Collection $rows): array
    {
        $approved = $rows->where('is_success', true);
        $pending = $rows->where('is_success', false);
        $suppliers = $rows
            ->groupBy('ref_primary_value')
            ->map(fn (Collection $group, string $label) => [
                'label' => $label,
                'total' => (int) $group->sum('qty_total'),
            ])->sortByDesc('total')->values()->all();
        $skuAnalytics = $this->skuAnalytics($rows, 'Qty Retur', 'Qty Disetujui', 'Qty Pending Approval');

        return $this->analyticsPayload(
            'outbound',
            'Analisis Retur Outbound',
            'Volume retur ke supplier, approval backlog, lead time approval, dan kontribusi supplier/PIC.',
            [
                $this->metric('Dokumen Retur', $rows->count(), 'number', 'primary'),
                $this->metric('Approval Rate', $this->rate($approved->count(), $rows->count()), 'percent', 'success', $pending->count().' dokumen menunggu approval'),
                $this->metric('Qty Retur Supplier', (int) $rows->sum('qty_total'), 'number', 'warning'),
                $this->metric('Backlog > 24 Jam', $pending->filter(fn (array $row) => ($row['aging_minutes'] ?? 0) > 1440)->count(), 'number', 'danger'),
            ],
            [
                $this->metric('Menunggu Approval', $pending->count(), 'number', 'warning'),
                $this->metric('Sudah Disetujui', $approved->count(), 'number', 'success'),
                $this->metric('Rata-rata Approval', $this->average($approved->pluck('lead_minutes')), 'duration', 'info'),
                $this->metric('Approval Terlama', $this->maximum($approved->pluck('lead_minutes')), 'duration', 'danger'),
                $this->metric('Backlog Terlama', $this->maximum($pending->pluck('aging_minutes')), 'duration', 'warning'),
                $this->metric('Supplier Terlibat', $rows->pluck('ref_primary_value')->filter(fn ($value) => $value !== '-')->unique()->count(), 'number', 'primary'),
                $this->metric('Rata-rata Qty/Dokumen', $rows->isNotEmpty() ? round($rows->sum('qty_total') / $rows->count(), 2) : 0, 'decimal', 'info'),
                $this->metric('PIC Approval Aktif', $approved->pluck('performance_operator')->filter()->unique()->count(), 'number', 'primary'),
                $this->metric('SKU Unik', $skuAnalytics['total_unique'], 'number', 'primary'),
                $this->metric('Rata-rata SKU/Dokumen', $skuAnalytics['avg_per_document'], 'decimal', 'info'),
            ],
            $this->dailyAnalytics($rows, 'qty_total', fn (array $row) => $row['is_success'] ? (int) $row['qty_total'] : 0),
            $this->statusAnalytics($rows),
            'Qty Retur per Supplier',
            $suppliers,
            'Approver',
            'Qty Dokumen',
            'Masih Pending',
            $this->performanceAnalytics($rows, 'qty_total', fn (array $row) => $row['is_success'] ? 0 : 1),
            $skuAnalytics
        );
    }

    private function analyticsPayload(
        string $module,
        string $title,
        string $description,
        array $headline,
        array $secondary,
        array $daily,
        array $statuses,
        string $breakdownTitle,
        array $breakdown,
        string $performanceRole,
        string $performanceQtyLabel,
        string $performanceExceptionLabel,
        array $performance,
        array $skuAnalytics
    ): array {
        return [
            'module' => $module,
            'title' => $title,
            'description' => $description,
            'headline' => $headline,
            'secondary' => $secondary,
            'charts' => [
                'daily' => $daily,
                'status' => $statuses,
                'breakdown' => array_slice($breakdown, 0, 10),
                'breakdown_title' => $breakdownTitle,
            ],
            'performance' => $performance,
            'performance_labels' => [
                'role' => $performanceRole,
                'qty' => $performanceQtyLabel,
                'exceptions' => $performanceExceptionLabel,
            ],
            'sku_analytics' => $skuAnalytics,
        ];
    }

    private function skuAnalytics(Collection $rows, string $targetLabel, string $actualLabel, string $exceptionLabel): array
    {
        $items = $rows
            ->flatMap(fn (array $row) => $row['_sku_items'] ?? collect())
            ->filter(fn (array $item) => trim((string) ($item['sku'] ?? '')) !== '')
            ->values();
        $totalVolume = (int) $items->sum(fn (array $item) => max(
            (int) ($item['target_qty'] ?? 0),
            (int) ($item['actual_qty'] ?? 0)
        ));
        $skuRows = $items->groupBy('sku')->map(function (Collection $group, string $sku) use ($totalVolume) {
            $target = (int) $group->sum('target_qty');
            $actual = (int) $group->sum('actual_qty');
            $exception = (int) $group->sum('exception_qty');
            $damaged = (int) $group->sum('damaged_qty');
            $lost = (int) $group->sum('lost_qty');
            $volume = (int) $group->sum(fn (array $item) => max(
                (int) ($item['target_qty'] ?? 0),
                (int) ($item['actual_qty'] ?? 0)
            ));

            return [
                'sku' => $sku,
                'name' => $group->pluck('name')->filter()->first() ?: '-',
                'documents' => $group->pluck('document_key')->unique()->count(),
                'target_qty' => $target,
                'actual_qty' => $actual,
                'variance_qty' => $actual - $target,
                'damaged_qty' => $damaged,
                'damaged_rate' => $this->rate($damaged, $volume),
                'lost_qty' => $lost,
                'lost_rate' => $this->rate($lost, $volume),
                'exception_qty' => $exception,
                'exception_rate' => $this->rate($exception, $volume),
                'volume_qty' => $volume,
                'contribution_rate' => $this->rate($volume, $totalVolume),
            ];
        })->sortByDesc('volume_qty')->values();

        return [
            'total_unique' => $skuRows->count(),
            'total_lines' => $items->count(),
            'avg_per_document' => $rows->isNotEmpty() ? round((float) $rows->avg('sku_count'), 2) : 0,
            'total_target_qty' => (int) $items->sum('target_qty'),
            'total_actual_qty' => (int) $items->sum('actual_qty'),
            'total_damaged_qty' => (int) $items->sum('damaged_qty'),
            'total_lost_qty' => (int) $items->sum('lost_qty'),
            'total_exception_qty' => (int) $items->sum('exception_qty'),
            'labels' => [
                'target' => $targetLabel,
                'actual' => $actualLabel,
                'exception' => $exceptionLabel,
            ],
            'rows' => $skuRows->take(50)->values()->all(),
        ];
    }

    private function metric(string $label, int|float $value, string $format, string $tone, ?string $help = null): array
    {
        return compact('label', 'value', 'format', 'tone', 'help');
    }

    private function dailyAnalytics(Collection $rows, string|callable $qty, string|callable $exception): array
    {
        return $rows->groupBy('date_key')->map(function (Collection $group, string $date) use ($qty, $exception) {
            return [
                'date' => $date,
                'documents' => $group->count(),
                'qty' => (int) $group->sum(fn (array $row) => is_callable($qty) ? $qty($row) : (int) ($row[$qty] ?? 0)),
                'exceptions' => (int) $group->sum(fn (array $row) => is_callable($exception) ? $exception($row) : (int) ($row[$exception] ?? 0)),
            ];
        })->sortKeys()->values()->all();
    }

    private function statusAnalytics(Collection $rows): array
    {
        return $rows->groupBy('status_label')->map(fn (Collection $group, string $label) => [
            'label' => $label,
            'total' => $group->count(),
        ])->sortByDesc('total')->values()->all();
    }

    private function performanceAnalytics(Collection $rows, string $qtyKey, callable $exception): array
    {
        return $rows->groupBy('performance_operator')->map(function (Collection $group, string $operator) use ($qtyKey, $exception) {
            $completed = $group->where('is_success', true);

            return [
                'operator' => $operator,
                'documents' => $group->count(),
                'completed' => $completed->count(),
                'success_rate' => $this->rate($completed->count(), $group->count()),
                'qty' => (int) $group->sum($qtyKey),
                'avg_lead_minutes' => $this->average($completed->pluck('lead_minutes')),
                'exceptions' => (int) $group->sum(fn (array $row) => $exception($row)),
                'last_activity' => $group->sortByDesc('sort_at')->first()['transacted_at'] ?? '-',
            ];
        })->sortByDesc('qty')->values()->all();
    }

    private function rate(int|float $numerator, int|float $denominator): float
    {
        return $denominator > 0 ? round($numerator * 100 / $denominator, 2) : 0;
    }

    private function average(Collection $values): float
    {
        $values = $values->filter(fn ($value) => $value !== null);

        return $values->isNotEmpty() ? round((float) $values->avg(), 2) : 0;
    }

    private function maximum(Collection $values): float
    {
        $values = $values->filter(fn ($value) => $value !== null);

        return $values->isNotEmpty() ? round((float) $values->max(), 2) : 0;
    }

    private function minutesBetween(?Carbon $from, ?Carbon $to): ?float
    {
        if (! $from || ! $to) {
            return null;
        }

        return round(max(0, $to->getTimestamp() - $from->getTimestamp()) / 60, 2);
    }

    private function applyDateFilter($query, string $column, Request $request): void
    {
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        try {
            if ($dateFrom) {
                $query->where($column, '>=', Carbon::parse($dateFrom)->startOfDay());
            }
            if ($dateTo) {
                $query->where($column, '<=', Carbon::parse($dateTo)->endOfDay());
            }
        } catch (\Throwable) {
            // ignore invalid date filters
        }
    }
}
