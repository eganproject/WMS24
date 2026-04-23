<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerReturn;
use App\Models\CustomerReturnItem;
use App\Models\OutboundTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ReturnReportController extends Controller
{
    public function index()
    {
        return view('admin.reports.returns.index', [
            'dataUrl' => route('admin.reports.returns.data'),
        ]);
    }

    public function data(Request $request)
    {
        $customerTotalQuery = $this->buildCustomerQuery($request, false);
        $outboundTotalQuery = $this->buildOutboundQuery($request, false);

        $recordsTotal = $customerTotalQuery->count() + $outboundTotalQuery->count();

        $customerRows = $this->buildCustomerQuery($request, true)
            ->get()
            ->map(fn (CustomerReturn $row) => $this->serializeCustomerReturn($row));
        $outboundRows = $this->buildOutboundQuery($request, true)
            ->get()
            ->map(fn (OutboundTransaction $row) => $this->serializeOutboundReturn($row));

        $merged = $customerRows
            ->concat($outboundRows)
            ->sortByDesc('sort_at')
            ->values();

        $recordsFiltered = $merged->count();
        $summary = [
            'total_documents' => $recordsFiltered,
            'customer_documents' => $customerRows->count(),
            'outbound_documents' => $outboundRows->count(),
            'customer_received_qty' => (int) $customerRows->sum('qty_received'),
            'customer_good_qty' => (int) $customerRows->sum('qty_good'),
            'customer_damaged_qty' => (int) $customerRows->sum('qty_damaged'),
            'outbound_qty' => (int) $outboundRows->sum('qty_total'),
            'unmatched_resi' => (int) $customerRows->where('matched', false)->count(),
        ];

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        $data = $length > 0
            ? $merged->slice($start, $length)->values()
            : $merged->values();

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'summary' => $summary,
            'data' => $data->map(function (array $row) {
                unset($row['sort_at']);

                return $row;
            })->values(),
        ]);
    }

    private function buildCustomerQuery(Request $request, bool $applySearch)
    {
        $source = (string) $request->input('source', '');
        if ($source === 'outbound') {
            return CustomerReturn::query()->whereRaw('1 = 0');
        }

        $query = CustomerReturn::query()
            ->with(['items.item', 'creator', 'inspector', 'finalizer', 'damagedGood'])
            ->orderByDesc('received_at')
            ->orderByDesc('id');

        $status = trim((string) $request->input('status', ''));
        if (in_array($status, [CustomerReturn::STATUS_INSPECTED, CustomerReturn::STATUS_COMPLETED], true)) {
            $query->where('status', $status);
        }

        $matchState = trim((string) $request->input('match_state', ''));
        if ($matchState === 'matched') {
            $query->whereNotNull('resi_id');
        } elseif ($matchState === 'unmatched') {
            $query->whereNull('resi_id');
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
        if ($source === 'customer') {
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
        $qtyDamaged = (int) $items->sum('damaged_qty');

        return [
            'row_key' => 'customer-'.$row->id,
            'report_source' => 'customer',
            'source_label' => 'Retur Customer',
            'source_badge' => 'badge-light-primary',
            'sort_at' => $row->received_at?->timestamp ?? 0,
            'transacted_at' => $row->received_at?->format('Y-m-d H:i') ?? '-',
            'code' => $row->code,
            'ref_primary_label' => 'Resi',
            'ref_primary_value' => $row->resi_no ?: '-',
            'ref_secondary_label' => 'Order Ref',
            'ref_secondary_value' => $row->order_ref ?: '-',
            'counterparty_label' => $row->resi_id ? 'Sumber' : 'Status Resi',
            'counterparty_value' => $row->resi_id ? 'Marketplace / Match Resi' : 'Input Manual',
            'extra_reference' => $row->damagedGood?->code,
            'extra_reference_label' => $row->damagedGood?->code ? 'Barang Rusak' : null,
            'status' => $row->status,
            'status_label' => $row->status === CustomerReturn::STATUS_COMPLETED ? 'Selesai' : 'Menunggu Finalisasi',
            'status_badge' => $row->status === CustomerReturn::STATUS_COMPLETED ? 'badge-light-success' : 'badge-light-warning',
            'matched' => (bool) $row->resi_id,
            'item_summary' => $this->buildCustomerItemSummary($items),
            'qty_expected' => $qtyExpected,
            'qty_received' => $qtyReceived,
            'qty_good' => $qtyGood,
            'qty_damaged' => $qtyDamaged,
            'qty_total' => $qtyReceived,
            'submit_by' => $row->creator?->name ?? '-',
            'secondary_by' => $row->inspector?->name ?? '-',
            'secondary_by_label' => 'Inspector',
            'tertiary_by' => $row->finalizer?->name ?? '-',
            'tertiary_by_label' => 'PIC Final',
            'note' => $row->note ?? '',
            'detail_url' => route('admin.inventory.customer-returns.show', $row->id),
            'detail_label' => 'Detail',
        ];
    }

    private function serializeOutboundReturn(OutboundTransaction $row): array
    {
        $items = $row->items ?? collect();
        $qtyTotal = (int) $items->sum('qty');

        return [
            'row_key' => 'outbound-'.$row->id,
            'report_source' => 'outbound',
            'source_label' => 'Retur Outbound',
            'source_badge' => 'badge-light-danger',
            'sort_at' => $row->transacted_at?->timestamp ?? 0,
            'transacted_at' => $row->transacted_at?->format('Y-m-d H:i') ?? '-',
            'code' => $row->code,
            'ref_primary_label' => 'Supplier',
            'ref_primary_value' => $row->supplier?->name ?: '-',
            'ref_secondary_label' => 'Ref No',
            'ref_secondary_value' => $row->ref_no ?: '-',
            'counterparty_label' => 'Gudang',
            'counterparty_value' => $row->warehouse?->name ?: '-',
            'extra_reference' => null,
            'extra_reference_label' => null,
            'status' => $row->status ?? 'pending',
            'status_label' => ($row->status ?? 'pending') === 'approved' ? 'Disetujui' : 'Menunggu Approval',
            'status_badge' => ($row->status ?? 'pending') === 'approved' ? 'badge-light-success' : 'badge-light-warning',
            'matched' => null,
            'item_summary' => $this->buildOutboundItemSummary($items),
            'qty_expected' => 0,
            'qty_received' => 0,
            'qty_good' => 0,
            'qty_damaged' => 0,
            'qty_total' => $qtyTotal,
            'submit_by' => $row->creator?->name ?? '-',
            'secondary_by' => $row->approver?->name ?? '-',
            'secondary_by_label' => 'Approver',
            'tertiary_by' => '-',
            'tertiary_by_label' => null,
            'note' => $row->note ?? '',
            'detail_url' => route('admin.outbound.returns.detail', $row->id),
            'detail_label' => 'Detail',
        ];
    }

    private function buildCustomerItemSummary(Collection $items): string
    {
        return $items->map(function (CustomerReturnItem $itemRow) {
            $sku = trim((string) ($itemRow->item?->sku ?? ''));
            if ($sku === '') {
                return '';
            }

            return sprintf(
                '%s (%d/%d/%d)',
                $sku,
                (int) $itemRow->received_qty,
                (int) $itemRow->good_qty,
                (int) $itemRow->damaged_qty
            );
        })->filter()->implode(', ');
    }

    private function buildOutboundItemSummary(Collection $items): string
    {
        return $items->map(function ($itemRow) {
            $sku = trim((string) ($itemRow->item?->sku ?? ''));
            if ($sku === '') {
                return '';
            }

            return sprintf('%s (%d)', $sku, (int) ($itemRow->qty ?? 0));
        })->filter()->implode(', ');
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
