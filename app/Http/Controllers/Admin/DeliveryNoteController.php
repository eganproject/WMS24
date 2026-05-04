<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OutboundTransaction;
use App\Support\WarehouseService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DeliveryNoteController extends Controller
{
    public function index()
    {
        return view('admin.outbound.delivery-notes.index', [
            'dataUrl' => route('admin.outbound.delivery-notes.data'),
            'showUrlTpl' => route('admin.outbound.delivery-notes.show', ':id'),
            'printUrlTpl' => route('admin.outbound.delivery-notes.print', ':id'),
        ]);
    }

    public function data(Request $request)
    {
        $baseQuery = $this->query();
        $recordsTotal = (clone $baseQuery)->count();

        $filteredQuery = $this->query();
        $this->applyFilters($filteredQuery, $request);
        $recordsFiltered = (clone $filteredQuery)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $filteredQuery->skip($start)->take($length);
        }

        $rows = $filteredQuery
            ->get()
            ->map(fn (OutboundTransaction $transaction) => $this->serialize($transaction))
            ->values();

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows,
        ]);
    }

    public function show(int $id)
    {
        return $this->document($id, false);
    }

    public function print(int $id)
    {
        return $this->document($id, true);
    }

    private function document(int $id, bool $printMode)
    {
        $transaction = $this->query()
            ->where('outbound_transactions.id', $id)
            ->firstOrFail();

        $totalQty = (int) $transaction->items->sum('qty');
        $totalKoli = $transaction->items->sum(function ($row) use ($transaction) {
            $qtyPerKoli = (int) ($row->item?->koli_qty ?? 0);
            if ($qtyPerKoli <= 0) {
                return 0;
            }

            if ($transaction->type === 'return' || (int) $transaction->warehouse_id === WarehouseService::defaultWarehouseId()) {
                return (int) floor(((int) $row->qty) / $qtyPerKoli);
            }

            return 0;
        });

        return view('admin.outbound.delivery-notes.document', [
            'transaction' => $transaction,
            'printMode' => $printMode,
            'totalQty' => $totalQty,
            'totalKoli' => (int) $totalKoli,
            'backUrl' => route('admin.outbound.delivery-notes.index'),
            'printUrl' => route('admin.outbound.delivery-notes.print', $transaction->id),
        ]);
    }

    private function query()
    {
        return OutboundTransaction::query()
            ->with([
                'items.item:id,sku,name,koli_qty',
                'warehouse:id,name,code',
                'supplier:id,name',
                'creator:id,name',
                'approver:id,name',
                'damagedAllocation:id,outbound_transaction_id,code,type,source_ref',
            ])
            ->whereIn('outbound_transactions.type', ['manual', 'return'])
            ->whereNotNull('outbound_transactions.surat_jalan_no')
            ->orderByRaw('COALESCE(outbound_transactions.surat_jalan_at, outbound_transactions.transacted_at) DESC')
            ->orderByDesc('outbound_transactions.id');
    }

    private function applyFilters($query, Request $request): void
    {
        $type = trim((string) $request->input('type', ''));
        if (in_array($type, ['manual', 'return'], true)) {
            $query->where('outbound_transactions.type', $type);
        }

        $status = trim((string) $request->input('status', ''));
        if ($status !== '') {
            $query->where('outbound_transactions.status', $status);
        }

        try {
            if ($request->filled('date_from')) {
                $query->whereRaw(
                    'COALESCE(outbound_transactions.surat_jalan_at, outbound_transactions.transacted_at) >= ?',
                    [Carbon::parse($request->input('date_from'))->startOfDay()]
                );
            }
            if ($request->filled('date_to')) {
                $query->whereRaw(
                    'COALESCE(outbound_transactions.surat_jalan_at, outbound_transactions.transacted_at) <= ?',
                    [Carbon::parse($request->input('date_to'))->endOfDay()]
                );
            }
        } catch (\Throwable) {
            // Invalid date filters are ignored so the listing remains usable.
        }

        $search = trim((string) $request->input('q', ''));
        if ($search === '') {
            return;
        }

        $exact = $this->isExactSearch($request);
        $query->where(function ($q) use ($search, $exact) {
            $this->applyTextSearch($q, 'outbound_transactions.surat_jalan_no', $search, $exact);
            $this->applyTextSearch($q, 'outbound_transactions.code', $search, $exact, 'or');
            $this->applyTextSearch($q, 'outbound_transactions.ref_no', $search, $exact, 'or');
            $this->applyTextSearch($q, 'outbound_transactions.note', $search, $exact, 'or');
            $q->orWhereHas('supplier', function ($supplierQ) use ($search, $exact) {
                $this->applyTextSearch($supplierQ, 'name', $search, $exact);
            })->orWhereHas('warehouse', function ($warehouseQ) use ($search, $exact) {
                $this->applyTextSearch($warehouseQ, 'name', $search, $exact);
                $this->applyTextSearch($warehouseQ, 'code', $search, $exact, 'or');
            })->orWhereHas('items.item', function ($itemQ) use ($search, $exact) {
                $this->applyTextSearch($itemQ, 'sku', $search, $exact);
                $this->applyTextSearch($itemQ, 'name', $search, $exact, 'or');
            })->orWhereHas('damagedAllocation', function ($allocationQ) use ($search, $exact) {
                $this->applyTextSearch($allocationQ, 'code', $search, $exact);
                $this->applyTextSearch($allocationQ, 'source_ref', $search, $exact, 'or');
            });
        });
    }

    private function serialize(OutboundTransaction $transaction): array
    {
        $qty = (int) $transaction->items->sum('qty');
        $items = $transaction->items
            ->take(4)
            ->map(fn ($row) => trim(($row->item?->sku ?? '-').' - '.($row->item?->name ?? '-')).' ('.((int) $row->qty).')')
            ->implode('<br>');

        if ($transaction->items->count() > 4) {
            $items .= '<br><span class="text-muted">+'.($transaction->items->count() - 4).' item lain</span>';
        }

        return [
            'id' => $transaction->id,
            'surat_jalan_no' => $transaction->surat_jalan_no,
            'surat_jalan_at' => $transaction->surat_jalan_at?->format('Y-m-d') ?: $transaction->transacted_at?->format('Y-m-d'),
            'code' => $transaction->code,
            'type' => $transaction->type,
            'type_label' => $transaction->type === 'return' ? 'Retur Outbound' : 'Outbound Manual',
            'status' => $transaction->status ?? 'pending',
            'warehouse' => $transaction->warehouse?->name ?? '-',
            'supplier' => $transaction->supplier?->name ?? '-',
            'ref_no' => $transaction->ref_no ?: '-',
            'qty' => $qty,
            'items' => $items ?: '-',
            'linked_allocation' => $transaction->damagedAllocation?->code,
            'created_by' => $transaction->creator?->name ?? '-',
            'approved_by' => $transaction->approver?->name ?? '-',
        ];
    }

}
