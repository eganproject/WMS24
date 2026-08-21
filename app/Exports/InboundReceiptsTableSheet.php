<?php

namespace App\Exports;

use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Sheet tabular: judul, ringkasan filter, lalu satu tabel dengan header berwarna. */
abstract class InboundReceiptsTableSheet extends InboundReceiptsSheet
{
    protected const HEADER_ROW = 5;

    public function startCell(): string
    {
        return 'A'.self::HEADER_ROW;
    }

    abstract protected function reportTitle(): string;

    abstract protected function lastColumn(): string;

    abstract protected function columnWidths(): array;

    /** Kolom angka yang dirata-kanankan dan diberi pemisah ribuan. */
    abstract protected function numericColumns(): array;

    abstract protected function totalLabel(int $rowCount): string;

    abstract protected function rowCount(): int;

    public function styles(Worksheet $sheet): array
    {
        $lastColumn = $this->lastColumn();

        $sheet->mergeCells('A1:'.$lastColumn.'1');
        $sheet->mergeCells('A2:'.$lastColumn.'2');
        $sheet->mergeCells('A3:'.$lastColumn.'3');
        $sheet->setCellValue('A1', $this->reportTitle());
        $sheet->setCellValue('A2', $this->filterSummary());
        $sheet->setCellValue('A3', $this->totalLabel($this->rowCount()).' | Diunduh: '.now()->format('d/m/Y H:i'));

        return [
            1 => ['font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '181C32']]],
            2 => ['font' => ['color' => ['rgb' => '7E8299']]],
            3 => ['font' => ['bold' => true, 'color' => ['rgb' => '3F4254']]],
            self::HEADER_ROW => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '009EF7']],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastColumn = $this->lastColumn();
                $headerRow = self::HEADER_ROW;
                $lastRow = max($headerRow, $headerRow + $this->rowCount());
                $range = 'A'.$headerRow.':'.$lastColumn.$lastRow;

                $sheet->freezePane('A'.($headerRow + 1));
                $sheet->setAutoFilter($range);
                $sheet->getStyle($range)->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E4E6EF');
                $sheet->getStyle('A1:'.$lastColumn.$lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle($range)->getAlignment()->setWrapText(false);
                $sheet->getStyle('A'.$headerRow.':'.$lastColumn.$headerRow)
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);
                $sheet->getRowDimension($headerRow)->setRowHeight(30);

                if ($lastRow > $headerRow) {
                    foreach ($this->numericColumns() as $column) {
                        $cells = $column.($headerRow + 1).':'.$column.$lastRow;
                        $sheet->getStyle($cells)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        $sheet->getStyle($cells)->getNumberFormat()->setFormatCode('#,##0');
                    }
                }

                foreach ($this->columnWidths() as $column => $width) {
                    $sheet->getColumnDimension($column)->setWidth($width);
                }
            },
        ];
    }
}
