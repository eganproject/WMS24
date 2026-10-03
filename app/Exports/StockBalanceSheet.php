<?php

namespace App\Exports;

use App\Support\StockBalanceExportReport;
use Maatwebsite\Excel\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

abstract class StockBalanceSheet extends DefaultValueBinder
{
    /** Angka kosong tetap tampil 0; minus berwarna merah. */
    protected const QTY_FORMAT = '#,##0;[Red]-#,##0;0';
    protected const SIGNED_QTY_FORMAT = '+#,##0;[Red]-#,##0;0';

    public function __construct(protected StockBalanceExportReport $report) {}

    public function bindValue(Cell $cell, $value)
    {
        // Teks berawalan =, +, -, @ dipaksa menjadi teks agar tidak dieksekusi sebagai rumus,
        // kecuali angka (misal "-1") yang tetap ditulis sebagai angka.
        if (is_string($value) && ! is_numeric($value) && preg_match('/^[=+\-@]/', $value) === 1) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    protected function styleHeader(Worksheet $sheet, string $range, string $color = '1B84FF'): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $color]],
            'alignment' => ['horizontal' => 'center', 'vertical' => 'center', 'wrapText' => true],
        ]);
    }

    protected function styleBorders(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E4E6EF');
    }

    protected function styleTotal(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '181C32']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F1F4']],
            'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '3F4254']]],
        ]);
    }

    /** @param array<int, array{0:string,1:string,2:string}> $rules value, fill, font */
    protected function addTextHighlight(Worksheet $sheet, string $range, array $rules): void
    {
        $styles = $sheet->getStyle($range)->getConditionalStyles();
        foreach ($rules as [$value, $fill, $font]) {
            $condition = new Conditional;
            $condition->setConditionType(Conditional::CONDITION_CELLIS)
                ->setOperatorType(Conditional::OPERATOR_EQUAL)
                ->addCondition('"'.$value.'"');
            $condition->getStyle()->getFont()->setBold(true)->getColor()->setRGB($font);
            $condition->getStyle()->getFill()->setFillType(Fill::FILL_SOLID);
            $condition->getStyle()->getFill()->getStartColor()->setRGB($fill);
            $condition->getStyle()->getFill()->getEndColor()->setRGB($fill);
            $styles[] = $condition;
        }
        $sheet->getStyle($range)->setConditionalStyles($styles);
    }

    protected function setPrintLayout(Worksheet $sheet): void
    {
        $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);
        $sheet->getPageMargins()->setTop(0.4)->setRight(0.3)->setBottom(0.4)->setLeft(0.3);
        $sheet->setSelectedCell('A1');
    }
}
