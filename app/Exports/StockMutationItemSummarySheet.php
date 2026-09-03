<?php

namespace App\Exports;

use App\Models\StockMutation;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StockMutationItemSummarySheet extends StockMutationTableSheet implements FromCollection, WithHeadings, WithTitle, WithCustomStartCell, WithStyles, WithEvents, WithStrictNullComparison, WithCustomValueBinder
{
    private ?Collection $rows = null;

    public function title(): string
    {
        return 'Rekap Item-Gudang';
    }

    public function headings(): array
    {
        return [
            'No', 'Kode Gudang', 'Gudang', 'SKU', 'Nama Item', 'Jumlah Mutasi', 'Dokumen Unik',
            'Qty Masuk', 'Qty Keluar', 'Net Mutasi', 'Total Aktivitas', 'Rata-rata Qty',
            'Qty Mutasi Terbesar', 'Mutasi Terakhir', 'Stok Terakhir', 'Anomali Saldo',
        ];
    }

    public function collection(): Collection
    {
        if ($this->rows !== null) {
            return $this->rows;
        }

        $summaries = $this->report->rows()
            ->groupBy(fn (StockMutation $row) => (int) $row->warehouse_id.'|'.(int) $row->item_id)
            ->map(function (Collection $mutations) {
                /** @var StockMutation $latest */
                $latest = $mutations->first();
                $qtyIn = (int) $mutations->where('direction', 'in')->sum('qty');
                $qtyOut = (int) $mutations->where('direction', 'out')->sum('qty');
                $totalActivity = $qtyIn + $qtyOut;

                return [
                    'warehouse_code' => $latest->warehouse?->code ?? '',
                    'warehouse_name' => $latest->warehouse?->name ?? '-',
                    'sku' => $latest->item?->sku ?? '',
                    'item_name' => $latest->item?->name ?? '-',
                    'mutation_count' => $mutations->count(),
                    'document_count' => $mutations->map(fn (StockMutation $row) => ($row->source_type ?? '').'|'.($row->source_id ?? ''))->unique()->count(),
                    'qty_in' => $qtyIn,
                    'qty_out' => $qtyOut,
                    'net' => $qtyIn - $qtyOut,
                    'activity' => $totalActivity,
                    'average' => $mutations->count() > 0 ? round($totalActivity / $mutations->count(), 2) : 0,
                    'maximum' => (int) $mutations->max('qty'),
                    'last_at' => $latest->occurred_at?->format('d/m/Y H:i') ?? '',
                    'last_stock' => $latest->stock_after !== null ? (int) $latest->stock_after : null,
                    'anomaly_count' => $mutations->filter(fn (StockMutation $row) => $this->report->balanceCheck($row) === 'Perlu diperiksa')->count(),
                ];
            })
            ->sortByDesc('activity')
            ->values();

        return $this->rows = $summaries->map(fn (array $row, int $index) => [
            $index + 1,
            $row['warehouse_code'],
            $row['warehouse_name'],
            $row['sku'],
            $row['item_name'],
            $row['mutation_count'],
            $row['document_count'],
            $row['qty_in'],
            $row['qty_out'],
            $row['net'],
            $row['activity'],
            $row['average'],
            $row['maximum'],
            $row['last_at'],
            $row['last_stock'],
            $row['anomaly_count'],
        ]);
    }

    protected function reportTitle(): string
    {
        return 'Laporan Mutasi Stok - Rekap per Item dan Gudang';
    }

    protected function lastColumn(): string
    {
        return 'P';
    }

    protected function columnWidths(): array
    {
        return [
            'A' => 7, 'B' => 15, 'C' => 23, 'D' => 20, 'E' => 36, 'F' => 14, 'G' => 14,
            'H' => 14, 'I' => 14, 'J' => 14, 'K' => 15, 'L' => 15, 'M' => 18, 'N' => 19,
            'O' => 14, 'P' => 15,
        ];
    }

    protected function numericColumns(): array
    {
        return ['F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'O', 'P'];
    }

    protected function rowCount(): int
    {
        return $this->collection()->count();
    }

    protected function formatBody(Worksheet $sheet, int $lastRow): void
    {
        if ($lastRow <= self::HEADER_ROW) {
            return;
        }

        for ($row = self::HEADER_ROW + 1; $row <= $lastRow; $row++) {
            $net = (float) $sheet->getCell('J'.$row)->getValue();
            if ($net < 0) {
                $sheet->getStyle('J'.$row)->getFont()->getColor()->setRGB('F1416C');
            } elseif ($net > 0) {
                $sheet->getStyle('J'.$row)->getFont()->getColor()->setRGB('00A261');
            }
            if ((int) $sheet->getCell('P'.$row)->getValue() > 0) {
                $sheet->getStyle('P'.$row)->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'B54708']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEF0C7']],
                ]);
            }
        }
        $sheet->getStyle('L'.(self::HEADER_ROW + 1).':L'.$lastRow)->getNumberFormat()->setFormatCode('#,##0.00');
    }
}
