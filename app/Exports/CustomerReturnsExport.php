<?php

namespace App\Exports;

use App\Models\CustomerReturn;
use App\Models\CustomerReturnItem;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class CustomerReturnsExport implements FromCollection, WithHeadings, ShouldAutoSize
{
    public function __construct(private array $filters = [])
    {
    }

    public function collection(): Collection
    {
        return $this->query()->get()->flatMap(function (CustomerReturn $row) {
            $items = $row->items->isNotEmpty() ? $row->items : collect([null]);

            return $items->map(function (?CustomerReturnItem $itemRow) use ($row) {
                $expectedQty = (int) ($itemRow?->expected_qty ?? 0);
                $receivedQty = (int) ($itemRow?->received_qty ?? 0);

                return [
                    $row->code,
                    $row->received_at?->format('Y-m-d H:i') ?? '',
                    $row->resi_no,
                    $row->order_ref ?? '',
                    $row->resi_id ? 'Resi Ditemukan' : 'Input Manual',
                    $this->statusLabel((string) $row->status),
                    $itemRow?->item?->sku ?? '',
                    $itemRow?->item?->name ?? '',
                    $expectedQty,
                    $receivedQty,
                    (int) ($itemRow?->good_qty ?? 0),
                    (int) ($itemRow?->damaged_qty ?? 0),
                    max($expectedQty - $receivedQty, 0),
                    $row->creator?->name ?? '',
                    $row->inspector?->name ?? '',
                    $row->finalizer?->name ?? '',
                    $row->damagedGood?->code ?? '',
                    $row->note ?? '',
                    $itemRow?->note ?? '',
                ];
            });
        })->values();
    }

    public function headings(): array
    {
        return [
            'Kode Retur',
            'Tanggal Terima',
            'No Resi',
            'Order Ref',
            'Status Resi',
            'Status Retur',
            'SKU',
            'Nama Item',
            'Qty Resi',
            'Qty Diterima',
            'Qty Bagus',
            'Qty Rusak',
            'Qty Hilang',
            'Dibuat Oleh',
            'Inspector',
            'PIC Finalisasi',
            'Kode Barang Rusak',
            'Catatan Dokumen',
            'Catatan Item',
        ];
    }

    private function query()
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
        $this->applySearch($query);

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
            // Ignore invalid export date filters and keep the export usable.
        }
    }

    private function applySearch($query): void
    {
        $search = trim((string) ($this->filters['q'] ?? ''));
        if ($search === '') {
            return;
        }

        $exact = filter_var($this->filters['exact'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $operator = $exact ? '=' : 'LIKE';
        $value = $exact ? $search : '%'.$search.'%';

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

    private function statusLabel(string $status): string
    {
        return match ($status) {
            CustomerReturn::STATUS_COMPLETED => 'Selesai',
            CustomerReturn::STATUS_NO_RECEIVED => 'Tidak Diterima',
            default => 'Belum Finalisasi',
        };
    }
}
