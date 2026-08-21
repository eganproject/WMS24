<?php

namespace App\Exports;

use App\Models\InboundItem;
use App\Models\InboundTransaction;
use App\Models\Warehouse;
use App\Support\InboundScanStatus;
use App\Support\WarehouseService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Sumber data bersama untuk seluruh sheet export penerimaan barang:
 * satu set filter yang sama dengan filter tabel di halaman penerimaan.
 */
abstract class InboundReceiptsSheet
{
    private ?Collection $transactions = null;

    private ?string $defaultWarehouseLabel = null;

    public function __construct(protected array $filters = [])
    {
    }

    protected function transactions(): Collection
    {
        if ($this->transactions !== null) {
            return $this->transactions;
        }

        return $this->transactions = $this->query()->get();
    }

    protected function query(): Builder
    {
        $query = InboundTransaction::query()
            ->with(['items.item', 'creator', 'warehouse', 'supplier', 'scanSession.items'])
            ->where('inbound_transactions.type', 'receipt')
            ->orderByDesc('inbound_transactions.transacted_at')
            ->orderByDesc('inbound_transactions.id');

        $this->applySearch($query);
        $this->applyDateFilter($query);
        $this->applyWarehouseFilter($query);
        $this->applyStatusFilter($query);

        return $query;
    }

    private function applySearch(Builder $query): void
    {
        $search = trim((string) ($this->filters['q'] ?? ''));
        if ($search === '') {
            return;
        }

        $exact = trim(strtolower((string) ($this->filters['search_mode'] ?? ''))) === 'exact';
        $operator = $exact ? '=' : 'like';
        $value = $exact ? $search : '%'.$search.'%';

        $query->where(function ($q) use ($operator, $value) {
            $q->where('inbound_transactions.code', $operator, $value)
                ->orWhere('inbound_transactions.ref_no', $operator, $value)
                ->orWhere('inbound_transactions.surat_jalan_no', $operator, $value)
                ->orWhereHas('supplier', fn ($supplierQ) => $supplierQ->where('name', $operator, $value))
                ->orWhereHas('items.item', function ($itemQ) use ($operator, $value) {
                    $itemQ->where('sku', $operator, $value)
                        ->orWhere('name', $operator, $value);
                });
        });
    }

    private function applyDateFilter(Builder $query): void
    {
        try {
            if (!empty($this->filters['date_from'])) {
                $query->where('inbound_transactions.transacted_at', '>=', Carbon::parse($this->filters['date_from'])->startOfDay());
            }
            if (!empty($this->filters['date_to'])) {
                $query->where('inbound_transactions.transacted_at', '<=', Carbon::parse($this->filters['date_to'])->endOfDay());
            }
        } catch (\Throwable) {
            // Abaikan filter tanggal tidak valid supaya export tetap bisa dipakai.
        }
    }

    private function applyWarehouseFilter(Builder $query): void
    {
        $warehouseId = $this->filters['warehouse_id'] ?? null;
        if ($warehouseId === null || $warehouseId === '' || $warehouseId === 'all') {
            return;
        }

        $query->where('inbound_transactions.warehouse_id', (int) $warehouseId);
    }

    private function applyStatusFilter(Builder $query): void
    {
        $status = trim((string) ($this->filters['status'] ?? ''));
        if ($status === '' || $status === 'all') {
            return;
        }

        $query->where('inbound_transactions.status', $status);
    }

    protected function statusLabel(?string $status): string
    {
        return $status === 'approved' ? 'Selesai / Approved' : InboundScanStatus::label($status);
    }

    protected function warehouseLabel(InboundTransaction $transaction): string
    {
        if ($transaction->warehouse?->name) {
            return $transaction->warehouse->name;
        }

        if ($this->defaultWarehouseLabel === null) {
            $this->defaultWarehouseLabel = Warehouse::whereKey(WarehouseService::defaultWarehouseId())->value('name') ?? 'Gudang Besar';
        }

        return $this->defaultWarehouseLabel;
    }

    protected function koliOf(InboundItem $item): int
    {
        return ($item->input_unit ?: 'koli') === 'pcs'
            ? (int) ($item->qty ?? 0)
            : (int) ($item->koli ?? 0);
    }

    /** Hasil scan per item transaksi, dipetakan berdasarkan item_id. */
    protected function scanMap(InboundTransaction $transaction): array
    {
        $map = [];
        foreach ($transaction->scanSession?->items ?? [] as $scanItem) {
            $map[(int) $scanItem->item_id] = [
                'scanned_qty' => (int) ($scanItem->scanned_qty ?? 0),
                'scanned_koli' => (int) ($scanItem->scanned_koli ?? 0),
            ];
        }

        return $map;
    }

    protected function periodLabel(): string
    {
        return ($this->filters['date_from'] ?? '-').' s/d '.($this->filters['date_to'] ?? '-');
    }

    protected function filterSummary(): string
    {
        $parts = ['Periode: '.$this->periodLabel()];

        if (!empty($this->filters['q'])) {
            $parts[] = 'Pencarian: '.$this->filters['q'];
        }

        $warehouseId = $this->filters['warehouse_id'] ?? null;
        if ($warehouseId !== null && $warehouseId !== '' && $warehouseId !== 'all') {
            $parts[] = 'Gudang: '.(Warehouse::whereKey((int) $warehouseId)->value('name') ?? $warehouseId);
        }

        $status = trim((string) ($this->filters['status'] ?? ''));
        if ($status !== '' && $status !== 'all') {
            $parts[] = 'Status: '.$this->statusLabel($status);
        }

        return implode(' | ', $parts);
    }
}
