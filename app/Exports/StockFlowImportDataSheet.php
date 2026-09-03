<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class StockFlowImportDataSheet implements FromArray, WithEvents, WithTitle
{
    public function __construct(private readonly array $definition) {}

    public function title(): string
    {
        return 'Data Import';
    }

    public function array(): array
    {
        return [$this->definition['headings']];
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $sheet = $event->sheet->getDelegate();
            $lastColumn = Coordinate::stringFromColumnIndex(count($this->definition['headings']));
            $sheet->freezePane('A2');
            $sheet->setAutoFilter('A1:'.$lastColumn.'1');
            $sheet->getSheetView()->setZoomScale(90);
            $sheet->getTabColor()->setRGB('009EF7');
            $sheet->getRowDimension(1)->setRowHeight(34);
            $sheet->getStyle('A1:'.$lastColumn.'1')->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '009EF7']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'B9D9FA']]],
            ]);

            foreach ($this->definition['widths'] as $index => $width) {
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index + 1))->setWidth($width);
            }
            foreach ($this->definition['text_columns'] as $column) {
                $sheet->getStyle($column.'2:'.$column.'5000')->getNumberFormat()->setFormatCode('@');
            }
            foreach ($this->definition['field_guides'] as $guide) {
                $index = array_search($guide[0], $this->definition['headings'], true);
                if ($index !== false) {
                    $sheet->getComment(Coordinate::stringFromColumnIndex($index + 1).'1')->getText()->createTextRun($guide[1].': '.$guide[2]);
                }
            }

            $values = $this->definition['validation_values'];
            if (is_string($values)) {
                $values = $this->definition[$values] ?? [];
            }
            $formula = implode(',', array_map(fn ($value) => str_replace(',', ' ', (string) $value), $values));
            if ($formula !== '' && strlen($formula) <= 250) {
                $validation = new DataValidation;
                $validation->setType(DataValidation::TYPE_LIST);
                $validation->setErrorStyle(DataValidation::STYLE_STOP);
                $validation->setAllowBlank(true);
                $validation->setShowDropDown(true);
                $validation->setShowErrorMessage(true);
                $validation->setError('Pilih nilai yang tersedia pada daftar.');
                $validation->setFormula1('"'.$formula.'"');
                $column = $this->definition['validation_column'];
                for ($row = 2; $row <= 1000; $row++) {
                    $sheet->getCell($column.$row)->setDataValidation(clone $validation);
                }
            }

            $sheet->setSelectedCell('A2');
        }];
    }
}
