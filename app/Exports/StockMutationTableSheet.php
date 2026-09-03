<?php

namespace App\Exports;

use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

abstract class StockMutationTableSheet extends StockMutationSheet
{
    protected const HEADER_ROW = 5;

    public function startCell(): string
    {
        return 'A'.self::HEADER_ROW;
    }

    abstract protected function reportTitle(): string;

    abstract protected function lastColumn(): string;

    abstract protected function columnWidths(): array;

    abstract protected function numericColumns(): array;

    abstract protected function rowCount(): int;

    public function styles(Worksheet $sheet): array
    {
        $lastColumn = $this->lastColumn();
        $sheet->mergeCells('A1:'.$lastColumn.'1');
        $sheet->mergeCells('A2:'.$lastColumn.'2');
        $sheet->mergeCells('A3:'.$lastColumn.'3');
        $sheet->setCellValue('A1', $this->reportTitle());
        $sheet->setCellValue('A2', $this->report->filterSummary());
        $sheet->setCellValue('A3', 'Total baris: '.number_format($this->rowCount(), 0, ',', '.').' | Diunduh: '.now()->format('d/m/Y H:i'));

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
                $lastRow = max(self::HEADER_ROW, self::HEADER_ROW + $this->rowCount());
                $range = 'A'.self::HEADER_ROW.':'.$lastColumn.$lastRow;

                $sheet->freezePane('A'.(self::HEADER_ROW + 1));
                $sheet->setAutoFilter($range);
                $borderRange = $this->rowCount() <= 5000
                    ? $range
                    : 'A'.self::HEADER_ROW.':'.$lastColumn.self::HEADER_ROW;
                $sheet->getStyle($borderRange)->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E4E6EF');
                $sheet->getStyle('A1:'.$lastColumn.$lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle('A'.self::HEADER_ROW.':'.$lastColumn.self::HEADER_ROW)
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);
                $sheet->getRowDimension(self::HEADER_ROW)->setRowHeight(34);

                if ($lastRow > self::HEADER_ROW) {
                    foreach ($this->numericColumns() as $column) {
                        $cells = $column.(self::HEADER_ROW + 1).':'.$column.$lastRow;
                        $sheet->getStyle($cells)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        $sheet->getStyle($cells)->getNumberFormat()->setFormatCode('#,##0');
                    }
                }

                foreach ($this->columnWidths() as $column => $width) {
                    $sheet->getColumnDimension($column)->setWidth($width);
                }

                $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);
                $sheet->getPageMargins()->setTop(0.4)->setRight(0.3)->setBottom(0.4)->setLeft(0.3);
                $this->formatBody($sheet, $lastRow);
                $sheet->setSelectedCell('A1');
            },
        ];
    }

    protected function formatBody(Worksheet $sheet, int $lastRow): void
    {
    }
}
