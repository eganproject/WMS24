<?php

namespace App\Exports;

use App\Models\CustomerReturn;
use App\Models\CustomerReturnItem;
use App\Models\OutboundItem;
use App\Models\OutboundTransaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ReturnReportExport implements FromCollection, WithHeadings, ShouldAutoSize
{
    public function __construct(private array $filters = [])
    {
    }

    public function collection(): Collection
    {
        $source = trim((string) ($this->filters['source'] ?? ''));

        if ($source === 'customer') {
            return $this->withoutSortColumn($this->customerRows());
        }

        if ($source === 'outbound') {
            return $this->withoutSortColumn($this->outboundRows());
        }

        return $this->customerRows()
            ->concat($this->outboundRows())
            ->sortByDesc(fn (array $row) => $row['_sort_at'] ?? 0)
            ->map(function (array $row) {
                unset($row['_sort_at']);

                return $row;
            })
            ->values();
    }

    public function headings(): array
    {
        return [
            'Jenis Retur',
            'Tanggal',
            'Kode Dokumen',
            'Referensi Utama',
            'Referensi Tambahan',
            'Counterparty / Gudang',
            'Status',
            'SKU',
            'Nama Item',
            'Qty Resi',
            'Qty Diterima',
            'Qty Bagus',
            'Qty Rusak',
            'Qty Hilang',
            'Qty Outbound',
            'Dibuat Oleh',
            'PIC 2',
            'PIC 3',
            'Catatan Dokumen',
            'Catatan Item',
        ];
    }

    private function customerRows(): Collection
    {
        return $this->customerQuery()->get()->flatMap(function (CustomerReturn $row) {
            $items = $row->items->isNotEmpty() ? $row->items : collect([null]);

            return $items->map(function (?CustomerReturnItem $itemRow) use ($row) {
                $expectedQty = (int) ($itemRow?->expected_qty ?? 0);
                $receivedQty = (int) ($itemRow?->received_qty ?? 0);

                return [
                    '_sort_at' => $row->received_at?->timestamp ?? 0,
                    'Retur Customer',
                    $row->received_at?->format('Y-m-d H:i') ?? '',
                    $row->code,
                    $row->resi_no ?: '',
                    $row->order_ref ?: '',
                    $row->resi_id ? 'Marketplace / Match Resi' : 'Input Manual',
                    $this->customerStatusLabel((string) $row->status),
                    $itemRow?->item?->sku ?? '',
                    $itemRow?->item?->name ?? '',
                    $expectedQty,
                    $receivedQty,
                    (int) ($itemRow?->good_qty ?? 0),
                    (int) ($itemRow?->damaged_qty ?? 0),
                    max($expectedQty - $receivedQty, 0),
                    0,
                    $row->creator?->name ?? '',
                    $row->inspector?->name ?? '',
                    $row->finalizer?->name ?? '',
                    $row->note ?? '',
                    $itemRow?->note ?? '',
                ];
            });
        })->values();
    }

    private function outboundRows(): Collection
    {
        return $this->outboundQuery()->get()->flatMap(function (OutboundTransaction $row) {
            $items = $row->items->isNotEmpty() ? $row->items : collect([null]);

            return $items->map(function (?OutboundItem $itemRow) use ($row) {
                return [
                    '_sort_at' => $row->transacted_at?->timestamp ?? 0,
                    'Retur Outbound',
                    $row->transacted_at?->format('Y-m-d H:i') ?? '',
                    $row->code,
                    $row->supplier?->name ?: '',
                    $row->ref_no ?: '',
                    $row->warehouse?->name ?: '',
                    (($row->status ?? 'pending') === 'approved') ? 'Disetujui' : 'Menunggu Approval',
                    $itemRow?->item?->sku ?? '',
                    $itemRow?->item?->name ?? '',
                    0,
                    0,
                    0,
                    0,
                    0,
                    (int) ($itemRow?->qty ?? 0),
                    $row->creator?->name ?? '',
                    $row->approver?->name ?? '',
                    '',
                    $row->note ?? '',
                    $itemRow?->note ?? '',
                ];
            });
        })->values();
    }

    private function customerQuery()
    {
        $query = CustomerReturn::query()
            ->with(['items.item', 'creator', 'inspector', 'finalizer', 'damagedGood'])
            ->orderByDesc('received_at')
            ->orderByDesc('id');

        $status = trim((string) ($this->filters['status'] ?? ''));
        if (in_array($status, [
            CustomerReturn::STATUS_INSPECTED,
            CustomerReturn::STATUS_COMPLETED,
            CustomerReturn::STATUS_NO_RECEIVED,
        ], true)) {
            $query->where('status', $status);
        } elseif ($status !== '') {
            $query->whereRaw('1 = 0');
        }

        $matchState = trim((string) ($this->filters['match_state'] ?? ''));
        if ($matchState === 'matched') {
            $query->whereNotNull('resi_id');
        } elseif ($matchState === 'unmatched') {
            $query->whereNull('resi_id');
        }

        $this->applyDateFilter($query, 'customer_returns.received_at');
        $this->applyCustomerSearch($query);

        return $query;
    }

    private function outboundQuery()
    {
        $query = OutboundTransaction::query()
            ->with(['items.item', 'creator', 'approver', 'supplier', 'warehouse'])
            ->where('type', 'return')
            ->orderByDesc('transacted_at')
            ->orderByDesc('id');

        $status = trim((string) ($this->filters['status'] ?? ''));
        if (in_array($status, ['pending', 'approved'], true)) {
            $query->where('status', $status);
        } elseif ($status !== '') {
            $query->whereRaw('1 = 0');
        }

        $this->applyDateFilter($query, 'outbound_transactions.transacted_at');
        $this->applyOutboundSearch($query);

        return $query;
    }

    private function applyDateFilter($query, string $column): void
    {
        try {
            if (!empty($this->filters['date_from'])) {
                $query->where($column, '>=', Carbon::parse($this->filters['date_from'])->startOfDay());
            }
            if (!empty($this->filters['date_to'])) {
                $query->where($column, '<=', Carbon::parse($this->filters['date_to'])->endOfDay());
            }
        } catch (\Throwable) {
            // Ignore invalid date filters and keep the export usable.
        }
    }

    private function applyCustomerSearch($query): void
    {
        $search = trim((string) ($this->filters['q'] ?? ''));
        if ($search === '') {
            return;
        }

        [$operator, $value] = $this->searchOperatorAndValue($search);
        $query->where(function ($q) use ($operator, $value) {
            $q->where('customer_returns.code', $operator, $value)
                ->orWhere('customer_returns.resi_no', $operator, $value)
                ->orWhere('customer_returns.order_ref', $operator, $value)
                ->orWhere('customer_returns.note', $operator, $value)
                ->orWhereHas('damagedGood', fn ($damagedQ) => $damagedQ->where('code', $operator, $value))
                ->orWhereHas('items.item', function ($itemQ) use ($operator, $value) {
                    $itemQ->where('sku', $operator, $value)
                        ->orWhere('name', $operator, $value);
                });
        });
    }

    private function applyOutboundSearch($query): void
    {
        $search = trim((string) ($this->filters['q'] ?? ''));
        if ($search === '') {
            return;
        }

        [$operator, $value] = $this->searchOperatorAndValue($search);
        $query->where(function ($q) use ($operator, $value) {
            $q->where('outbound_transactions.code', $operator, $value)
                ->orWhere('outbound_transactions.ref_no', $operator, $value)
                ->orWhere('outbound_transactions.note', $operator, $value)
                ->orWhereHas('supplier', fn ($supplierQ) => $supplierQ->where('name', $operator, $value))
                ->orWhereHas('warehouse', fn ($warehouseQ) => $warehouseQ->where('name', $operator, $value))
                ->orWhereHas('items.item', function ($itemQ) use ($operator, $value) {
                    $itemQ->where('sku', $operator, $value)
                        ->orWhere('name', $operator, $value);
                });
        });
    }

    private function searchOperatorAndValue(string $search): array
    {
        $exact = filter_var($this->filters['exact'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return $exact ? ['=', $search] : ['LIKE', '%'.$search.'%'];
    }

    private function customerStatusLabel(string $status): string
    {
        return match ($status) {
            CustomerReturn::STATUS_COMPLETED => 'Selesai',
            CustomerReturn::STATUS_NO_RECEIVED => 'Tidak Diterima',
            default => 'Belum Finalisasi',
        };
    }

    private function withoutSortColumn(Collection $rows): Collection
    {
        return $rows->map(function (array $row) {
            unset($row['_sort_at']);

            return $row;
        })->values();
    }
}
