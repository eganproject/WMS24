<?php

namespace App\Exports;

use App\Models\DamagedGood;
use App\Models\DamagedGoodItem;
use App\Models\Warehouse;
use App\Support\DamagedStockService;
use App\Support\WarehouseService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DamagedGoodsExport implements FromCollection, WithHeadings, WithTitle, WithCustomStartCell, ShouldAutoSize, WithStyles, WithEvents
{
    private ?Collection $rows = null;

    public function __construct(private array $filters = [])
    {
    }

    public function title(): string
    {
        return 'Barang Rusak';
    }

    public function startCell(): string
    {
        return 'A5';
    }

    public function collection(): Collection
    {
        if ($this->rows !== null) {
            return $this->rows;
        }

        $documents = $this->query()->get();
        $items = $documents->flatMap(fn (DamagedGood $row) => $row->items ?? collect())->values();
        $remainingMap = DamagedStockService::remainingQtyMap($items->pluck('id')->all());
        $sourceLabels = DamagedGood::sourceLabels();

        $this->rows = $documents->flatMap(function (DamagedGood $row) use ($remainingMap, $sourceLabels) {
            $items = ($row->items ?? collect())->isNotEmpty() ? $row->items : collect([null]);

            return $items->map(function (?DamagedGoodItem $itemRow) use ($row, $remainingMap, $sourceLabels) {
                $state = $itemRow
                    ? ($remainingMap[(int) $itemRow->id] ?? [
                        'allocated_qty' => 0,
                        'remaining_qty' => (int) $itemRow->qty,
                    ])
                    : ['allocated_qty' => 0, 'remaining_qty' => 0];
                $ageDays = $row->transacted_at
                    ? (int) Carbon::parse($row->transacted_at)->startOfDay()->diffInDays(now()->startOfDay())
                    : 0;

                return [
                    $row->code,
                    $row->transacted_at?->format('Y-m-d H:i') ?? '',
                    $this->statusLabel((string) ($row->status ?? 'pending')),
                    $row->approved_at?->format('Y-m-d H:i') ?? '',
                    $sourceLabels[$row->source_type] ?? $row->source_type ?? '',
                    $this->sourceWarehouseLabel($row),
                    $row->source_ref ?? '',
                    $itemRow?->item?->sku ?? '',
                    $itemRow?->item?->name ?? '',
                    DamagedGoodItem::reasonLabel($itemRow?->reason_code),
                    (int) ($itemRow?->qty ?? 0),
                    (int) ($state['allocated_qty'] ?? 0),
                    (int) ($state['remaining_qty'] ?? 0),
                    $ageDays,
                    $this->ageBucketLabel($ageDays),
                    $row->creator?->name ?? '',
                    $row->approver?->name ?? '',
                    $row->note ?? '',
                    $itemRow?->note ?? '',
                ];
            });
        })->values();

        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'Kode Barang Rusak',
            'Tanggal Intake',
            'Status',
            'Tanggal Approve',
            'Sumber',
            'Gudang Asal',
            'Ref Sumber',
            'SKU',
            'Nama Barang',
            'Alasan Rusak',
            'Qty Intake',
            'Dialokasikan',
            'Sisa',
            'Aging Hari',
            'Aging Bucket',
            'Submit By',
            'Approved By',
            'Catatan Dokumen',
            'Catatan Item',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $rowCount = $this->collection()->count();
        $sheet->mergeCells('A1:S1');
        $sheet->mergeCells('A2:S2');
        $sheet->mergeCells('A3:S3');
        $sheet->setCellValue('A1', 'Export Barang Rusak');
        $sheet->setCellValue('A2', $this->filterSummary());
        $sheet->setCellValue('A3', 'Total baris item: '.number_format($rowCount, 0, ',', '.'));

        return [
            1 => ['font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '181C32']]],
            2 => ['font' => ['color' => ['rgb' => '7E8299']]],
            3 => ['font' => ['bold' => true, 'color' => ['rgb' => '3F4254']]],
            5 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1416C']],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = max(5, 5 + $this->collection()->count());
                $range = 'A5:S'.$lastRow;

                $sheet->freezePane('A6');
                $sheet->setAutoFilter($range);
                $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E4E6EF');
                $sheet->getStyle('A1:S'.$lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle('K6:N'.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle('K6:N'.$lastRow)->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle('R6:S'.$lastRow)->getAlignment()->setWrapText(true);
                $sheet->getStyle('A5:S5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                foreach ([
                    'A' => 22,
                    'B' => 18,
                    'C' => 16,
                    'D' => 18,
                    'E' => 20,
                    'F' => 22,
                    'G' => 22,
                    'H' => 18,
                    'I' => 32,
                    'J' => 22,
                    'O' => 16,
                    'P' => 20,
                    'Q' => 20,
                    'R' => 36,
                    'S' => 36,
                ] as $column => $width) {
                    $sheet->getColumnDimension($column)->setWidth($width);
                }
            },
        ];
    }

    private function query()
    {
        $query = DamagedGood::query()
            ->with(['items.item', 'creator', 'approver', 'sourceWarehouse'])
            ->orderByDesc('transacted_at')
            ->orderByDesc('id');

        $this->applySearch($query);
        $this->applyReasonFilter($query);
        $this->applyStatusFilter($query);
        $this->applyDateFilter($query);

        return $query;
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
            $q->where('damaged_goods.code', $operator, $value)
                ->orWhere('damaged_goods.source_ref', $operator, $value)
                ->orWhereHas('sourceWarehouse', function ($warehouseQ) use ($operator, $value) {
                    $warehouseQ->where('name', $operator, $value)
                        ->orWhere('code', $operator, $value);
                })
                ->orWhereHas('items', fn ($itemQ) => $itemQ->where('reason_code', $operator, $value))
                ->orWhereHas('items.item', function ($itemQ) use ($operator, $value) {
                    $itemQ->where('sku', $operator, $value)
                        ->orWhere('name', $operator, $value);
                });
        });
    }

    private function applyReasonFilter($query): void
    {
        $reasonCode = trim((string) ($this->filters['reason_code'] ?? ''));
        if ($reasonCode === '') {
            return;
        }

        $query->whereHas('items', fn ($itemQ) => $itemQ->where('reason_code', $reasonCode));
    }

    private function applyStatusFilter($query): void
    {
        $status = trim((string) ($this->filters['status'] ?? ''));
        if (in_array($status, ['pending', 'approved'], true)) {
            $query->where('status', $status);
        }
    }

    private function applyDateFilter($query): void
    {
        try {
            if (!empty($this->filters['date_from'])) {
                $query->where('transacted_at', '>=', Carbon::parse($this->filters['date_from'])->startOfDay());
            }
            if (!empty($this->filters['date_to'])) {
                $query->where('transacted_at', '<=', Carbon::parse($this->filters['date_to'])->endOfDay());
            }
        } catch (\Throwable) {
            // Ignore invalid export date filters and keep the export usable.
        }
    }

    private function filterSummary(): string
    {
        $parts = [];
        if (!empty($this->filters['q'])) {
            $parts[] = 'Pencarian: '.$this->filters['q'];
        }
        if (!empty($this->filters['reason_code'])) {
            $parts[] = 'Alasan: '.DamagedGoodItem::reasonLabel((string) $this->filters['reason_code']);
        }
        if (!empty($this->filters['status'])) {
            $parts[] = 'Status: '.$this->statusLabel((string) $this->filters['status']);
        }
        if (!empty($this->filters['date_from']) || !empty($this->filters['date_to'])) {
            $parts[] = 'Periode: '.($this->filters['date_from'] ?? '-').' s/d '.($this->filters['date_to'] ?? '-');
        }

        return $parts ? implode(' | ', $parts) : 'Semua data';
    }

    private function sourceWarehouseLabel(DamagedGood $damage): string
    {
        if ((string) $damage->source_type === DamagedGood::SOURCE_CUSTOMER_RETURN) {
            return 'Retur Customer';
        }

        $warehouseId = (int) ($damage->source_warehouse_id ?? 0);
        if ($warehouseId <= 0) {
            $warehouseId = match ($damage->source_type) {
                DamagedGood::SOURCE_LEGACY_DISPLAY => WarehouseService::displayWarehouseId(),
                DamagedGood::SOURCE_CUSTOMER_RETURN => 0,
                default => WarehouseService::defaultWarehouseId(),
            };
        }

        return $damage->sourceWarehouse?->name ?? Warehouse::whereKey($warehouseId)->value('name') ?? '-';
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'approved' => 'Disetujui',
            default => 'Menunggu',
        };
    }

    private function ageBucketLabel(int $ageDays): string
    {
        return DamagedStockService::ageBucketLabels()[DamagedStockService::ageBucket($ageDays)] ?? '-';
    }
}
