<?php

namespace App\Exports;

use App\Support\StockBalanceExportReport;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class StockBalanceDetailSheet extends StockBalanceSheet implements FromCollection, WithCustomStartCell, WithCustomValueBinder, WithEvents, WithHeadings, WithStrictNullComparison, WithTitle
{
    private const HEADER_ROW = 6;

    private ?Collection $rows = null;

    public function title(): string
    {
        return 'Detail Saldo per SKU';
    }

    public function startCell(): string
    {
        return 'A'.self::HEADER_ROW;
    }

    public function headings(): array
    {
        return [
            'No', 'SKU', 'Nama Item', 'Status Item',
            'Stok Awal', 'Masuk (Inbound Gudang Besar)', 'Keluar (Outbound Manual + QC Resi)', 'Mutasi Lain (Net)', 'Saldo Akhir',
            'Saldo Akhir Gudang Besar', 'Saldo Akhir Gudang Display', 'Perubahan (Akhir - Awal)', 'Kondisi Stok', 'Keterangan',
        ];
    }

    public function collection(): Collection
    {
        return $this->rows ??= $this->report->rows()->values()->map(fn ($row, int $index) => [
            $index + 1,
            $row->sku,
            $row->item_name,
            ($row->item_status ?: 'active') === 'active' ? 'Aktif' : 'Nonaktif',
            $row->opening_stock,
            $row->stock_in,
            $row->stock_out,
            $row->other_net,
            $row->ending_stock,
            $row->main_ending_stock,
            $row->display_ending_stock,
            $row->change,
            $row->condition,
            $row->note,
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
                $lastRow = $header + $count;
                $totalRow = $lastRow + 1;

                foreach ([1, 2, 3, 4] as $row) {
                    $sheet->mergeCells("A{$row}:N{$row}");
                }
                $sheet->setCellValue('A1', 'Laporan Saldo Stok - Detail per SKU');
                $sheet->setCellValue('A2', $this->report->filterSummary());
                $sheet->setCellValue('A3', 'Saldo Akhir = Stok Awal + Masuk - Keluar + Mutasi Lain. Angka merah = minus. Baris TOTAL mengikuti filter yang dipilih.');
                $sheet->setCellValue('A4', sprintf('Total %s SKU | Diunduh %s', number_format($count, 0, ',', '.'), now()->format('d/m/Y H:i')));
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB('181C32');
                $sheet->getStyle('A2:A3')->getFont()->getColor()->setRGB('7E8299');
                $sheet->getStyle('A4')->getFont()->setBold(true)->getColor()->setRGB('3F4254');

                $this->styleHeader($sheet, "A{$header}:N{$header}");
                // Kolom saldo diberi warna berbeda agar mudah dibedakan dari pergerakan.
                $this->styleHeader($sheet, "I{$header}:K{$header}", '0063B1');
                $sheet->getRowDimension($header)->setRowHeight(42);

                $sheet->setCellValue("A{$totalRow}", 'TOTAL');
                $sheet->mergeCells("A{$totalRow}:D{$totalRow}");
                foreach (range('E', 'L') as $column) {
                    if ($count > 0) {
                        $sheet->setCellValueExplicit("{$column}{$totalRow}", "=SUBTOTAL(109,{$column}{$firstRow}:{$column}{$lastRow})", DataType::TYPE_FORMULA);
                    } else {
                        $sheet->setCellValue("{$column}{$totalRow}", 0);
                    }
                }
                $this->styleTotal($sheet, "A{$totalRow}:N{$totalRow}");

                $sheet->freezePane('D'.$firstRow);
                $sheet->setAutoFilter("A{$header}:N".max($header, $lastRow));
                $this->styleBorders($sheet, "A{$header}:N{$totalRow}");
                $sheet->getStyle("A{$firstRow}:A{$totalRow}")->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle("E{$firstRow}:L{$totalRow}")->getNumberFormat()->setFormatCode(self::QTY_FORMAT);
                foreach (['H', 'L'] as $column) {
                    $sheet->getStyle("{$column}{$firstRow}:{$column}{$totalRow}")->getNumberFormat()->setFormatCode(self::SIGNED_QTY_FORMAT);
                }
                $sheet->getStyle("I{$firstRow}:I{$totalRow}")->getFont()->setBold(true);
                $sheet->getStyle("A1:N{$totalRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle("C{$firstRow}:C{$lastRow}")->getAlignment()->setWrapText(true);
                $sheet->getStyle("D{$firstRow}:D{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("M{$firstRow}:M{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                if ($count > 0) {
                    $this->addTextHighlight($sheet, "M{$firstRow}:M{$lastRow}", [
                        [StockBalanceExportReport::CONDITION_AVAILABLE, 'E8FFF3', '00875A'],
                        [StockBalanceExportReport::CONDITION_EMPTY, 'FFF8DD', '946200'],
                        [StockBalanceExportReport::CONDITION_MINUS, 'FFF5F8', 'D9214E'],
                    ]);
                }

                foreach ([
                    'A' => 7, 'B' => 20, 'C' => 40, 'D' => 11, 'E' => 13, 'F' => 16, 'G' => 18,
                    'H' => 14, 'I' => 14, 'J' => 15, 'K' => 15, 'L' => 15, 'M' => 13, 'N' => 30,
                ] as $column => $width) {
                    $sheet->getColumnDimension($column)->setWidth($width);
                }
                $this->setPrintLayout($sheet);
                $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($header, $header);
            },
        ];
    }
}
