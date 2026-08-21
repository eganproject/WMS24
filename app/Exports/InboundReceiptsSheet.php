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
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

abstract class InboundReceiptsSheet
{
    protected const HEADER_ROW = 5;

    private ?Collection $transactions = null;

    private ?string $defaultWarehouseLabel = null;

    public function __construct(protected array $filters = [])
    {
    }

    public function startCell(): string
    {
        return 'A'.self::HEADER_ROW;
    }

    abstract protected function reportTitle(): string;

    abstract protected function lastColumn(): string;

    abstract protected function columnWidths(): array;

    /** Kolom angka yang dirata-kanankan dan diberi pemisah ribuan. */
    abstract protected function numericColumns(): array;

    abstract protected function totalLabel(int $rowCount): string;

    abstract protected function rowCount(): int;

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

    protected function filterSummary(): string
    {
        $parts = ['Periode: '.($this->filters['date_from'] ?? '-').' s/d '.($this->filters['date_to'] ?? '-')];

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

    public function styles(Worksheet $sheet): array
    {
        $lastColumn = $this->lastColumn();

        $sheet->mergeCells('A1:'.$lastColumn.'1');
        $sheet->mergeCells('A2:'.$lastColumn.'2');
        $sheet->mergeCells('A3:'.$lastColumn.'3');
        $sheet->setCellValue('A1', $this->reportTitle());
        $sheet->setCellValue('A2', $this->filterSummary());
        $sheet->setCellValue('A3', $this->totalLabel($this->rowCount()).' | Diunduh: '.now()->format('d/m/Y H:i'));

        return [
            1 => ['font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '181C32']]],
            2 => ['font' => ['color' => ['rgb' => '7E8299']]],
            3 => ['font' => ['bold' => true, 'color' => ['rgb' => '3F4254']]],
            self::HEADER_ROW => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '009EF7']],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastColumn = $this->lastColumn();
                $headerRow = self::HEADER_ROW;
                $lastRow = max($headerRow, $headerRow + $this->rowCount());
                $range = 'A'.$headerRow.':'.$lastColumn.$lastRow;

                $sheet->freezePane('A'.($headerRow + 1));
                $sheet->setAutoFilter($range);
                $sheet->getStyle($range)->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E4E6EF');
                $sheet->getStyle('A1:'.$lastColumn.$lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle($range)->getAlignment()->setWrapText(false);
                $sheet->getStyle('A'.$headerRow.':'.$lastColumn.$headerRow)
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);
                $sheet->getRowDimension($headerRow)->setRowHeight(30);

                if ($lastRow > $headerRow) {
                    foreach ($this->numericColumns() as $column) {
                        $cells = $column.($headerRow + 1).':'.$column.$lastRow;
                        $sheet->getStyle($cells)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        $sheet->getStyle($cells)->getNumberFormat()->setFormatCode('#,##0');
                    }
                }

                foreach ($this->columnWidths() as $column => $width) {
                    $sheet->getColumnDimension($column)->setWidth($width);
                }
            },
        ];
    }
}
