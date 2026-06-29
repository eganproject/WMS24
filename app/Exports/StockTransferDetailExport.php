<?php

namespace App\Exports;

use App\Models\StockTransfer;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StockTransferDetailExport implements FromArray, WithTitle, ShouldAutoSize, WithStyles, WithEvents
{
    private int $itemHeaderRow = 12;
    private int $scanTitleRow;
    private int $scanHeaderRow;

    public function __construct(private StockTransfer $transfer)
    {
        $itemCount = max(1, $this->transfer->items->count());
        $this->scanTitleRow = $this->itemHeaderRow + $itemCount + 2;
        $this->scanHeaderRow = $this->scanTitleRow + 1;
    }

    public function title(): string
    {
        return 'Detail Transfer';
    }

    public function array(): array
    {
        $rows = [
            ['Detail Transfer Gudang'],
            ['Kode', $this->transfer->code, '', 'Status', $this->statusLabel((string) ($this->transfer->status ?? 'qc_pending'))],
            ['Tanggal', $this->transfer->transacted_at?->format('Y-m-d H:i') ?? '-', '', 'Submit By', $this->transfer->creator?->name ?? '-'],
            ['Dari Gudang', $this->transfer->fromWarehouse?->name ?? '-', '', 'Ke Gudang', $this->transfer->toWarehouse?->name ?? '-'],
            ['QC By', $this->transfer->qcBy?->name ?? '-', '', 'QC At', $this->transfer->qc_at?->format('Y-m-d H:i') ?? '-'],
            ['Traceability', $this->traceabilityLabel(), '', 'Alasan Legacy', $this->transfer->legacy_reason ?: '-'],
            ['Catatan', $this->transfer->note ?: '-'],
            [],
            ['Ringkasan Qty'],
            ['Total SKU', $this->transfer->items->count(), '', 'Qty Transfer', $this->transfer->items->sum('qty'), 'Qty OK', $this->transfer->items->sum('qty_ok'), 'Qty Reject', $this->transfer->items->sum('qty_reject'), 'Qty Kurang', $this->transfer->items->sum('qty_short')],
            [],
            ['SKU', 'Nama Item', 'Isi/Koli', 'Qty Transfer', 'Koli Transfer', 'Qty OK', 'Koli OK', 'Qty Reject', 'Koli Reject', 'Qty Kurang', 'Koli Kurang', 'Catatan', 'Catatan QC'],
        ];

        foreach ($this->transfer->items as $itemRow) {
            $qtyPerKoli = (int) ($itemRow->item?->koli_qty ?? 0);
            $rows[] = [
                $itemRow->item?->sku ?? '-',
                $itemRow->item?->name ?? '-',
                $qtyPerKoli > 0 ? $qtyPerKoli : '-',
                (int) $itemRow->qty,
                $this->formatKoliBreakdown((int) $itemRow->qty, $qtyPerKoli) ?: '-',
                (int) $itemRow->qty_ok,
                $this->formatKoliBreakdown((int) $itemRow->qty_ok, $qtyPerKoli) ?: '-',
                (int) $itemRow->qty_reject,
                $this->formatKoliBreakdown((int) $itemRow->qty_reject, $qtyPerKoli) ?: '-',
                (int) ($itemRow->qty_short ?? 0),
                $this->formatKoliBreakdown((int) ($itemRow->qty_short ?? 0), $qtyPerKoli) ?: '-',
                $itemRow->note ?? '',
                $itemRow->qc_note ?? '',
            ];
        }

        $scans = $this->koliScans();
        if ($scans->isNotEmpty()) {
            $rows[] = [];
            $rows[] = ['Jejak QR Dus Inbound'];
            $rows[] = ['SKU', 'QR Dus', 'Inbound Asal', 'Koli Ke', 'Qty Dus', 'OK', 'Reject', 'Kurang', 'Scanned At', 'Catatan QC'];

            foreach ($scans as $scan) {
                $rows[] = [
                    $scan->koliUnit?->sku ?? $scan->item?->sku ?? '-',
                    $scan->koliUnit?->code ?? '-',
                    $scan->koliUnit?->transaction?->code ?? '-',
                    $scan->koliUnit?->koli_no ?? '-',
                    (int) $scan->qty,
                    (int) $scan->qty_ok,
                    (int) $scan->qty_reject,
                    (int) $scan->qty_short,
                    $scan->scanned_at?->format('Y-m-d H:i') ?? '-',
                    $scan->qc_note ?: '',
                ];
            }
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->mergeCells('A1:M1');
        $sheet->mergeCells('B7:M7');
        $sheet->mergeCells('A9:M9');
        $sheet->setCellValue('A9', 'Ringkasan Qty');

        $styles = [
            1 => ['font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '181C32']]],
            9 => ['font' => ['bold' => true, 'color' => ['rgb' => '3F4254']]],
            $this->itemHeaderRow => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1B84FF']],
            ],
        ];

        if ($this->koliScans()->isNotEmpty()) {
            $sheet->mergeCells('A'.$this->scanTitleRow.':M'.$this->scanTitleRow);
            $styles[$this->scanTitleRow] = ['font' => ['bold' => true, 'color' => ['rgb' => '3F4254']]];
            $styles[$this->scanHeaderRow] = [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '50CD89']],
            ];
        }

        return $styles;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $itemLastRow = $this->itemHeaderRow + max(1, $this->transfer->items->count());
                $sheet->freezePane('A13');
                $sheet->getStyle('A1:M'.$sheet->getHighestRow())->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle('A1:M'.$sheet->getHighestRow())->getAlignment()->setWrapText(true);
                $sheet->getStyle('D13:D'.$itemLastRow)->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle('F13:F'.$itemLastRow)->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle('H13:H'.$itemLastRow)->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle('J13:J'.$itemLastRow)->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle('A'.$this->itemHeaderRow.':M'.$itemLastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E4E6EF');
                $sheet->getStyle('A'.$this->itemHeaderRow.':M'.$this->itemHeaderRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                if ($this->koliScans()->isNotEmpty()) {
                    $scanLastRow = $this->scanHeaderRow + $this->koliScans()->count();
                    $sheet->getStyle('A'.$this->scanHeaderRow.':J'.$scanLastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E4E6EF');
                    $sheet->getStyle('D'.($this->scanHeaderRow + 1).':H'.$scanLastRow)->getNumberFormat()->setFormatCode('#,##0');
                    $sheet->getStyle('A'.$this->scanHeaderRow.':J'.$this->scanHeaderRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                foreach (['A' => 18, 'B' => 32, 'C' => 12, 'D' => 14, 'E' => 18, 'F' => 12, 'G' => 18, 'H' => 12, 'I' => 18, 'J' => 12, 'K' => 18, 'L' => 30, 'M' => 30] as $column => $width) {
                    $sheet->getColumnDimension($column)->setWidth($width);
                }
            },
        ];
    }

    private function koliScans(): Collection
    {
        return $this->transfer->items->flatMap(fn ($item) => $item->koliScans ?? collect())->values();
    }

    private function traceabilityLabel(): string
    {
        return match ((string) ($this->transfer->traceability_mode ?? '')) {
            'legacy' => 'Legacy No QR',
            'qr' => 'QR Inbound',
            default => '-',
        };
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'completed' => 'Selesai',
            'canceled' => 'Dibatalkan',
            default => 'Menunggu QC',
        };
    }

    private function formatKoliBreakdown(int $qty, int $qtyPerKoli): string
    {
        if ($qty <= 0 || $qtyPerKoli <= 0) {
            return '';
        }

        $koli = intdiv($qty, $qtyPerKoli);
        $remainder = $qty % $qtyPerKoli;

        return $koli.' koli'.($remainder > 0 ? ' + '.$remainder.' pcs' : '').' x '.$qtyPerKoli;
    }
}
