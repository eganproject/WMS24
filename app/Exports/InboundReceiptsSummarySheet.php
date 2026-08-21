<?php

namespace App\Exports;

use App\Models\InboundTransaction;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;

class InboundReceiptsSummarySheet extends InboundReceiptsSheet implements FromCollection, WithHeadings, WithTitle, WithCustomStartCell, WithStyles, WithStrictNullComparison, WithEvents
{
    private ?Collection $rows = null;

    public function title(): string
    {
        return 'Ringkasan Penerimaan';
    }

    public function headings(): array
    {
        return [
            'No',
            'Kode Penerimaan',
            'Tanggal Transaksi',
            'Status',
            'Supplier',
            'No Surat Jalan',
            'Tanggal Surat Jalan',
            'No Referensi',
            'Gudang',
            'Jumlah SKU',
            'Total Koli',
            'Total Qty (Pcs)',
            'Koli Terscan',
            'Qty Terscan',
            'Selisih Qty',
            'Submit By',
            'Catatan',
        ];
    }

    public function collection(): Collection
    {
        if ($this->rows !== null) {
            return $this->rows;
        }

        $number = 0;

        return $this->rows = $this->transactions()->map(function (InboundTransaction $row) use (&$number) {
            $items = $row->items ?? collect();
            $scanItems = $row->scanSession?->items ?? collect();

            $expectedQty = (int) $items->sum('qty');
            $expectedKoli = (int) $items->sum(fn ($item) => $this->koliOf($item));
            $scannedQty = (int) $scanItems->sum('scanned_qty');
            $scannedKoli = (int) $scanItems->sum('scanned_koli');

            return [
                ++$number,
                $row->code,
                $row->transacted_at?->format('d/m/Y H:i') ?? '',
                $this->statusLabel($row->status),
                $row->supplier?->name ?? '',
                $row->surat_jalan_no ?? '',
                $row->surat_jalan_at?->format('d/m/Y') ?? '',
                $row->ref_no ?? '',
                $this->warehouseLabel($row),
                $items->count(),
                $expectedKoli,
                $expectedQty,
                $scannedKoli,
                $scannedQty,
                $scannedQty - $expectedQty,
                $row->creator?->name ?? '',
                $row->note ?? '',
            ];
        })->values();
    }

    protected function reportTitle(): string
    {
        return 'Laporan Penerimaan Barang';
    }

    protected function lastColumn(): string
    {
        return 'Q';
    }

    protected function rowCount(): int
    {
        return $this->collection()->count();
    }

    protected function totalLabel(int $rowCount): string
    {
        return 'Total dokumen penerimaan: '.number_format($rowCount, 0, ',', '.');
    }

    protected function numericColumns(): array
    {
        return ['J', 'K', 'L', 'M', 'N', 'O'];
    }

    protected function columnWidths(): array
    {
        return [
            'A' => 6,
            'B' => 24,
            'C' => 18,
            'D' => 20,
            'E' => 26,
            'F' => 22,
            'G' => 18,
            'H' => 20,
            'I' => 20,
            'J' => 12,
            'K' => 12,
            'L' => 15,
            'M' => 14,
            'N' => 14,
            'O' => 13,
            'P' => 22,
            'Q' => 36,
        ];
    }
}
