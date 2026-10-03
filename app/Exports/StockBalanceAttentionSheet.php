<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class StockBalanceAttentionSheet extends StockBalanceSheet implements FromCollection, WithCustomStartCell, WithCustomValueBinder, WithEvents, WithHeadings, WithStrictNullComparison, WithTitle
{
    private const HEADER_ROW = 6;

    private ?Collection $rows = null;

    public function title(): string
    {
        return 'Perlu Perhatian';
    }

    public function startCell(): string
    {
        return 'A'.self::HEADER_ROW;
    }

    public function headings(): array
    {
        return [
            'No', 'Masalah', 'SKU', 'Nama Item', 'Saldo Akhir', 'Gudang Besar', 'Gudang Display',
            'Keluar', 'Masuk', 'Mutasi Lain', 'Saran Tindakan',
        ];
    }

    public function collection(): Collection
    {
        return $this->rows ??= $this->report->attentionRows()->values()->map(fn ($row, int $index) => [
            $index + 1,
            $this->report->attentionLabel($row),
            $row->sku,
            $row->item_name,
            $row->ending_stock,
            $row->main_ending_stock,
            $row->display_ending_stock,
            $row->stock_out,
            $row->stock_in,
            $row->other_net,
            $this->report->attentionAction($row),
        ]);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $header = self::HEADER_ROW;
                $firstRow = $header + 1;
                $count = $this->collection()->count();
                $lastRow = max($header, $header + $count);

                foreach ([1, 2, 3, 4] as $row) {
                    $sheet->mergeCells("A{$row}:K{$row}");
                }
                $sheet->setCellValue('A1', 'Laporan Saldo Stok - SKU Perlu Perhatian');
                $sheet->setCellValue('A2', $this->report->filterSummary());
                $sheet->setCellValue('A3', 'Urutan prioritas: 1) Saldo minus  2) Habis setelah terjual  3) Masih ada stok tetapi tidak bergerak selama periode.');
                $sheet->setCellValue('A4', $count > 0
                    ? sprintf('%s SKU perlu ditindaklanjuti', number_format($count, 0, ',', '.'))
                    : 'Tidak ada SKU yang perlu ditindaklanjuti pada periode ini.');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB('181C32');
                $sheet->getStyle('A2:A3')->getFont()->getColor()->setRGB('7E8299');
                $sheet->getStyle('A4')->getFont()->setBold(true)->getColor()->setRGB('D9214E');

                $this->styleHeader($sheet, "A{$header}:K{$header}", 'F1416C');
                $sheet->getRowDimension($header)->setRowHeight(30);
                $sheet->freezePane('C'.$firstRow);
                $sheet->setAutoFilter("A{$header}:K{$lastRow}");
                $this->styleBorders($sheet, "A{$header}:K{$lastRow}");
                $sheet->getStyle("E{$firstRow}:I{$lastRow}")->getNumberFormat()->setFormatCode(self::QTY_FORMAT);
                $sheet->getStyle("J{$firstRow}:J{$lastRow}")->getNumberFormat()->setFormatCode(self::SIGNED_QTY_FORMAT);
                $sheet->getStyle("E{$firstRow}:E{$lastRow}")->getFont()->setBold(true);
                $sheet->getStyle("A1:K{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle("D{$firstRow}:D{$lastRow}")->getAlignment()->setWrapText(true);
                $sheet->getStyle("K{$firstRow}:K{$lastRow}")->getAlignment()->setWrapText(true);

                if ($count > 0) {
                    $this->addTextHighlight($sheet, "B{$firstRow}:B{$lastRow}", [
                        ['Saldo minus', 'FFF5F8', 'D9214E'],
                        ['Habis setelah terjual', 'FFF8DD', '946200'],
                        ['Stok tidak bergerak', 'F1F1F4', '5E6278'],
                    ]);
                }

                foreach ([
                    'A' => 7, 'B' => 22, 'C' => 20, 'D' => 38, 'E' => 13, 'F' => 14,
                    'G' => 14, 'H' => 11, 'I' => 11, 'J' => 12, 'K' => 55,
                ] as $column => $width) {
                    $sheet->getColumnDimension($column)->setWidth($width);
                }
                $this->setPrintLayout($sheet);
                $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($header, $header);
            },
        ];
    }
}
