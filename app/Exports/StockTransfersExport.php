<?php

namespace App\Exports;

use App\Models\StockTransfer;
use App\Models\Warehouse;
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

class StockTransfersExport implements FromCollection, WithHeadings, WithTitle, WithCustomStartCell, ShouldAutoSize, WithStyles, WithEvents
{
    public function __construct(private Collection $transfers, private array $filters = [])
    {
    }

    public function title(): string
    {
        return 'Transfer Gudang';
    }

    public function startCell(): string
    {
        return 'A5';
    }

    public function collection(): Collection
    {
        return $this->transfers->map(function (StockTransfer $transfer) {
            $items = $transfer->items ?? collect();

            return [
                $transfer->code,
                $transfer->transacted_at?->format('Y-m-d H:i') ?? '',
                $transfer->fromWarehouse?->name ?? '-',
                $transfer->toWarehouse?->name ?? '-',
                $this->statusLabel((string) ($transfer->status ?? 'qc_pending')),
                $transfer->creator?->name ?? '-',
                (int) $items->count(),
                (int) $items->sum('qty'),
                (int) $items->sum('qty_ok'),
                (int) $items->sum('qty_reject'),
                (int) $items->sum('qty_short'),
                $items->map(fn ($row) => trim(($row->item?->sku ?? '').' - '.($row->item?->name ?? '')))
                    ->filter()
                    ->implode("\n"),
                $transfer->note ?? '',
            ];
        })->values();
    }

    public function headings(): array
    {
        return [
            'Kode',
            'Tanggal',
            'Dari Gudang',
            'Ke Gudang',
            'Status',
            'Submit By',
            'Jumlah SKU',
            'Qty Transfer',
            'Qty OK',
            'Qty Reject',
            'Qty Kurang',
            'Daftar Item',
            'Catatan',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $rowCount = $this->collection()->count();
        $sheet->mergeCells('A1:M1');
        $sheet->mergeCells('A2:M2');
        $sheet->mergeCells('A3:M3');
        $sheet->setCellValue('A1', 'Daftar Transfer Gudang');
        $sheet->setCellValue('A2', $this->filterSummary());
        $sheet->setCellValue('A3', 'Total transfer: '.number_format($rowCount, 0, ',', '.'));

        return [
            1 => ['font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '181C32']]],
            2 => ['font' => ['color' => ['rgb' => '7E8299']]],
            3 => ['font' => ['bold' => true, 'color' => ['rgb' => '3F4254']]],
            5 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1B84FF']],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = max(5, 5 + $this->collection()->count());
                $range = 'A5:M'.$lastRow;

                $sheet->freezePane('A6');
                $sheet->setAutoFilter($range);
                $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E4E6EF');
                $sheet->getStyle('A1:M'.$lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle('G6:K'.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle('G6:K'.$lastRow)->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle('L6:M'.$lastRow)->getAlignment()->setWrapText(true);
                $sheet->getStyle('A5:M5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                foreach (['A' => 24, 'B' => 18, 'C' => 24, 'D' => 24, 'E' => 16, 'F' => 22, 'L' => 42, 'M' => 36] as $column => $width) {
                    $sheet->getColumnDimension($column)->setWidth($width);
                }
            },
        ];
    }

    private function filterSummary(): string
    {
        $parts = [];
        if (!empty($this->filters['q'])) {
            $parts[] = 'Pencarian: '.$this->filters['q'];
        }
        if (!empty($this->filters['status'])) {
            $parts[] = 'Status: '.$this->statusLabel((string) $this->filters['status']);
        }
        if (!empty($this->filters['from_warehouse_id'])) {
            $parts[] = 'Dari: '.(Warehouse::whereKey($this->filters['from_warehouse_id'])->value('name') ?? $this->filters['from_warehouse_id']);
        }
        if (!empty($this->filters['to_warehouse_id'])) {
            $parts[] = 'Ke: '.(Warehouse::whereKey($this->filters['to_warehouse_id'])->value('name') ?? $this->filters['to_warehouse_id']);
        }
        if (!empty($this->filters['date_from']) || !empty($this->filters['date_to'])) {
            $parts[] = 'Periode: '.($this->filters['date_from'] ?? '-').' s/d '.($this->filters['date_to'] ?? '-');
        }

        return $parts ? implode(' | ', $parts) : 'Semua data';
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'completed' => 'Selesai',
            'canceled' => 'Dibatalkan',
            default => 'Menunggu QC',
        };
    }
}
