<?php

namespace App\Exports;

use App\Models\StockMutation;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithCustomChunkSize;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StockMutationDetailSheet extends StockMutationTableSheet implements FromQuery, WithMapping, WithHeadings, WithTitle, WithCustomStartCell, WithStyles, WithEvents, WithStrictNullComparison, WithCustomValueBinder, WithCustomChunkSize
{
    private int $number = 0;

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

    public function query()
    {
        return $this->report->query();
    }

    public function map($row): array
    {
        /** @var StockMutation $row */
        $referenceSku = trim((string) ($row->reference_sku ?: $row->referenceItem?->sku));

        return [
            ++$this->number,
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
    }

    /** Helper untuk pengujian dan pemakaian langsung di luar writer Excel. */
    public function collection(): Collection
    {
        $this->number = 0;

        return $this->report->rows()->map(fn (StockMutation $row) => $this->map($row));
    }

    public function chunkSize(): int
    {
        return 2000;
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
        return $this->report->count();
    }

    protected function formatBody(Worksheet $sheet, int $lastRow): void
    {
        if ($lastRow <= self::HEADER_ROW) {
            return;
        }

        $sheet->getStyle('G'.(self::HEADER_ROW + 1).':H'.$lastRow)->getAlignment()->setWrapText(true);
        $sheet->getStyle('S'.(self::HEADER_ROW + 1).':S'.$lastRow)->getAlignment()->setWrapText(true);
        $sheet->getStyle('C'.(self::HEADER_ROW + 1).':C'.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $in = new Conditional();
        $in->setConditionType(Conditional::CONDITION_EXPRESSION)->addCondition('$I'.(self::HEADER_ROW + 1).'="IN"');
        $in->getStyle()->getFont()->getColor()->setRGB('00A261');
        $out = new Conditional();
        $out->setConditionType(Conditional::CONDITION_EXPRESSION)->addCondition('$I'.(self::HEADER_ROW + 1).'="OUT"');
        $out->getStyle()->getFont()->getColor()->setRGB('F1416C');
        $sheet->getStyle('I'.(self::HEADER_ROW + 1).':K'.$lastRow)->setConditionalStyles([$in, $out]);

        $anomaly = new Conditional();
        $anomaly->setConditionType(Conditional::CONDITION_CELLIS)->setOperatorType(Conditional::OPERATOR_EQUAL)->addCondition('"Perlu diperiksa"');
        $anomaly->getStyle()->getFont()->setBold(true)->getColor()->setRGB('B54708');
        $anomaly->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEF0C7');
        $sheet->getStyle('N'.(self::HEADER_ROW + 1).':N'.$lastRow)->setConditionalStyles([$anomaly]);
    }
}
