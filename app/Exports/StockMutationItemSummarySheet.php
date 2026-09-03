<?php

namespace App\Exports;

use Illuminate\Support\Carbon;
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
use PhpOffice\PhpSpreadsheet\Style\Conditional;
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
            'Qty Mutasi Terbesar', 'Mutasi Terakhir', 'Snapshot Saldo Tersedia', 'Anomali Saldo',
        ];
    }

    public function collection(): Collection
    {
        if ($this->rows !== null) {
            return $this->rows;
        }

        return $this->rows = $this->report->itemWarehouseSummary()->map(function ($row, int $index) {
            $qtyIn = (int) $row->qty_in;
            $qtyOut = (int) $row->qty_out;
            $activity = $qtyIn + $qtyOut;

            return [
                $index + 1,
                $row->warehouse_code ?? '',
                $row->warehouse_name ?? '-',
                $row->sku ?? '',
                $row->item_name ?? '-',
                (int) $row->mutation_count,
                (int) $row->document_count,
                $qtyIn,
                $qtyOut,
                $qtyIn - $qtyOut,
                $activity,
                (int) $row->mutation_count > 0 ? round($activity / (int) $row->mutation_count, 2) : 0,
                (int) $row->maximum_qty,
                $row->last_mutation_at ? Carbon::parse($row->last_mutation_at)->format('d/m/Y H:i') : '',
                (int) $row->snapshot_count,
                (int) $row->anomaly_count,
            ];
        });
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

        $positive = new Conditional();
        $positive->setConditionType(Conditional::CONDITION_CELLIS)->setOperatorType(Conditional::OPERATOR_GREATERTHAN)->addCondition('0');
        $positive->getStyle()->getFont()->getColor()->setRGB('00A261');
        $negative = new Conditional();
        $negative->setConditionType(Conditional::CONDITION_CELLIS)->setOperatorType(Conditional::OPERATOR_LESSTHAN)->addCondition('0');
        $negative->getStyle()->getFont()->getColor()->setRGB('F1416C');
        $sheet->getStyle('J'.(self::HEADER_ROW + 1).':J'.$lastRow)->setConditionalStyles([$positive, $negative]);

        $anomaly = new Conditional();
        $anomaly->setConditionType(Conditional::CONDITION_CELLIS)->setOperatorType(Conditional::OPERATOR_GREATERTHAN)->addCondition('0');
        $anomaly->getStyle()->getFont()->setBold(true)->getColor()->setRGB('B54708');
        $anomaly->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEF0C7');
        $sheet->getStyle('P'.(self::HEADER_ROW + 1).':P'.$lastRow)->setConditionalStyles([$anomaly]);
        $sheet->getStyle('L'.(self::HEADER_ROW + 1).':L'.$lastRow)->getNumberFormat()->setFormatCode('#,##0.00');
    }
}
