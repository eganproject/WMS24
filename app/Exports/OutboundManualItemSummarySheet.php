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
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OutboundManualItemSummarySheet extends OutboundManualTableSheet implements FromCollection, WithHeadings, WithTitle, WithCustomStartCell, WithStyles, WithEvents, WithStrictNullComparison, WithCustomValueBinder
{
    private ?Collection $rows = null;

    public function title(): string
    {
        return 'Rekap Item';
    }

    public function headings(): array
    {
        return ['No', 'SKU', 'Nama Item', 'Dokumen', 'Qty Rencana', 'Target QC', 'Qty Scan', 'Sisa QC', 'Progres QC', 'Rata-rata Qty', 'Qty Terbesar', 'Outbound Terakhir', 'Kontribusi'];
    }

    public function collection(): Collection
    {
        if ($this->rows !== null) {
            return $this->rows;
        }

        $source = $this->report->itemSummary();
        $total = (int) $source->sum('planned_qty');

        return $this->rows = $source->map(function ($row, int $index) use ($total) {
            $expected = (int) $row->expected_qty;
            $scanned = (int) $row->scanned_qty;
            $planned = (int) $row->planned_qty;
            return [
                $index + 1,
                $row->sku ?? '',
                $row->item_name ?? '-',
                (int) $row->document_count,
                $planned,
                $expected,
                $scanned,
                max(0, $expected - $scanned),
                $expected > 0 ? $scanned / $expected : 0,
                round((float) $row->average_qty, 2),
                (int) $row->maximum_qty,
                $row->last_outbound_at ? Date::dateTimeToExcel(Carbon::parse($row->last_outbound_at)) : null,
                $total > 0 ? $planned / $total : 0,
            ];
        });
    }

    protected function reportTitle(): string
    {
        return 'Laporan Outbound Manual - Rekap Item';
    }

    protected function lastColumn(): string
    {
        return 'M';
    }

    protected function columnWidths(): array
    {
        return ['A' => 7, 'B' => 20, 'C' => 38, 'D' => 12, 'E' => 15, 'F' => 14, 'G' => 14, 'H' => 13, 'I' => 13, 'J' => 16, 'K' => 15, 'L' => 20, 'M' => 13];
    }

    protected function numericColumns(): array
    {
        return ['A', 'D', 'E', 'F', 'G', 'H', 'J', 'K'];
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
        $first = self::HEADER_ROW + 1;
        $sheet->getStyle('I'.$first.':I'.$lastRow)->getNumberFormat()->setFormatCode('0.00%');
        $sheet->getStyle('M'.$first.':M'.$lastRow)->getNumberFormat()->setFormatCode('0.00%');
        $sheet->getStyle('J'.$first.':J'.$lastRow)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('L'.$first.':L'.$lastRow)->getNumberFormat()->setFormatCode('dd/mm/yyyy hh:mm');

        $remaining = new Conditional();
        $remaining->setConditionType(Conditional::CONDITION_CELLIS)->setOperatorType(Conditional::OPERATOR_GREATERTHAN)->addCondition('0');
        $remaining->getStyle()->getFont()->setBold(true)->getColor()->setRGB('B54708');
        $remaining->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEF0C7');
        $sheet->getStyle('H'.$first.':H'.$lastRow)->setConditionalStyles([$remaining]);
    }
}
