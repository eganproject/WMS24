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
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StockMutationDetailSheet extends StockMutationTableSheet implements FromCollection, WithHeadings, WithTitle, WithCustomStartCell, WithStyles, WithEvents, WithStrictNullComparison, WithCustomValueBinder
{
    private ?Collection $rows = null;

    public function title(): string
    {
        return 'Detail Mutasi';
    }

    public function headings(): array
    {
        return [
            'No', 'ID Mutasi', 'Tanggal', 'Kode Gudang', 'Gudang', 'SKU', 'Nama Item', 'Referensi Bundle',
            'Arah', 'Qty', 'Mutasi Bersih', 'Stok Sebelum', 'Stok Sesudah', 'Validasi Saldo',
            'Tipe Sumber', 'Subtipe Sumber', 'Kode Dokumen', 'Submit By', 'Catatan',
        ];
    }

    public function collection(): Collection
    {
        if ($this->rows !== null) {
            return $this->rows;
        }

        return $this->rows = $this->report->rows()->values()->map(function (StockMutation $row, int $index) {
            $referenceSku = trim((string) ($row->reference_sku ?: $row->referenceItem?->sku));

            return [
                $index + 1,
                (int) $row->id,
                $row->occurred_at?->format('d/m/Y H:i:s') ?? '',
                $row->warehouse?->code ?? '',
                $row->warehouse?->name ?? '-',
                $row->item?->sku ?? '',
                $row->item?->name ?? '-',
                $referenceSku,
                strtoupper((string) $row->direction),
                (int) $row->qty,
                $row->direction === 'in' ? (int) $row->qty : -(int) $row->qty,
                $row->stock_before !== null ? (int) $row->stock_before : null,
                $row->stock_after !== null ? (int) $row->stock_after : null,
                $this->report->balanceCheck($row),
                strtoupper(str_replace('_', ' ', (string) $row->source_type)),
                strtoupper(str_replace('_', ' ', (string) ($row->source_subtype ?? ''))),
                $row->source_code ?? '',
                $row->creator?->name ?? '-',
                $row->note ?? '',
            ];
        });
    }

    protected function reportTitle(): string
    {
        return 'Laporan Mutasi Stok - Detail Transaksi';
    }

    protected function lastColumn(): string
    {
        return 'S';
    }

    protected function columnWidths(): array
    {
        return [
            'A' => 7, 'B' => 11, 'C' => 20, 'D' => 15, 'E' => 23, 'F' => 20, 'G' => 36,
            'H' => 21, 'I' => 10, 'J' => 12, 'K' => 15, 'L' => 15, 'M' => 15, 'N' => 21,
            'O' => 19, 'P' => 20, 'Q' => 24, 'R' => 22, 'S' => 42,
        ];
    }

    protected function numericColumns(): array
    {
        return ['A', 'B', 'J', 'K', 'L', 'M'];
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

        $sheet->getStyle('G'.(self::HEADER_ROW + 1).':H'.$lastRow)->getAlignment()->setWrapText(true);
        $sheet->getStyle('S'.(self::HEADER_ROW + 1).':S'.$lastRow)->getAlignment()->setWrapText(true);
        $sheet->getStyle('C'.(self::HEADER_ROW + 1).':C'.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        for ($row = self::HEADER_ROW + 1; $row <= $lastRow; $row++) {
            $direction = (string) $sheet->getCell('I'.$row)->getValue();
            $color = $direction === 'IN' ? '00A261' : 'F1416C';
            $sheet->getStyle('I'.$row.':K'.$row)->getFont()->getColor()->setRGB($color);

            if ($sheet->getCell('N'.$row)->getValue() === 'Perlu diperiksa') {
                $sheet->getStyle('N'.$row)->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'B54708']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEF0C7']],
                ]);
            }
        }
    }
}
