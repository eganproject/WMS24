<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class StockFlowImportGuideSheet implements FromArray, WithEvents, WithTitle
{
    private ?array $layout = null;

    public function __construct(private readonly array $definition) {}

    public function title(): string
    {
        return 'Contoh & Panduan';
    }

    public function array(): array
    {
        return $this->layout()['rows'];
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $sheet = $event->sheet->getDelegate();
            $layout = $this->layout();
            $lastColumn = Coordinate::stringFromColumnIndex(count($this->definition['headings']));
            $sheet->mergeCells('A1:'.$lastColumn.'1');
            $sheet->mergeCells('A2:'.$lastColumn.'2');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(18)->getColor()->setRGB('181C32');
            $sheet->getStyle('A2')->getFont()->getColor()->setRGB('7E8299');
            $sheet->getStyle('A1:A2')->getAlignment()->setWrapText(true);

            foreach ([$layout['field_title'], $layout['rule_title'], $layout['example_title']] as $row) {
                $sheet->mergeCells('A'.$row.':'.$lastColumn.$row);
                $sheet->getStyle('A'.$row.':'.$lastColumn.$row)->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => '181C32']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E4E6EF']],
                ]);
            }

            $this->styleHeader($sheet, 'A'.$layout['field_header'].':C'.$layout['field_header']);
            $this->styleHeader($sheet, 'A'.$layout['example_header'].':'.$lastColumn.$layout['example_header']);
            $sheet->getStyle('A'.$layout['field_header'].':C'.$layout['field_last'])->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E4E6EF');
            $sheet->getStyle('A'.$layout['example_header'].':'.$lastColumn.$layout['example_last'])->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E4E6EF');
            $sheet->getStyle('A1:'.$lastColumn.$layout['last_row'])->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
            $sheet->getColumnDimension('A')->setWidth(22);
            $sheet->getColumnDimension('B')->setWidth(17);
            $sheet->getColumnDimension('C')->setWidth(65);
            for ($index = 4; $index <= count($this->definition['headings']); $index++) {
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index))->setWidth($this->definition['widths'][$index - 1] ?? 18);
            }
            $sheet->freezePane('A4');
            $sheet->getTabColor()->setRGB('50CD89');
            $sheet->setSelectedCell('A1');
        }];
    }

    private function layout(): array
    {
        if ($this->layout !== null) {
            return $this->layout;
        }

        $rows = [[$this->definition['title']], [$this->definition['subtitle']], ['']];
        $fieldTitle = count($rows) + 1;
        $rows[] = ['DAFTAR KOLOM'];
        $fieldHeader = count($rows) + 1;
        $rows[] = ['Kolom', 'Status', 'Penjelasan'];
        foreach ($this->definition['field_guides'] as $guide) {
            $rows[] = $guide;
        }
        $fieldLast = count($rows);
        $rows[] = [''];
        $ruleTitle = count($rows) + 1;
        $rows[] = ['ATURAN PENTING'];
        foreach ($this->definition['rules'] as $index => $rule) {
            $rows[] = [($index + 1).'.', $rule];
        }
        $rows[] = [''];
        $exampleTitle = count($rows) + 1;
        $rows[] = ['CONTOH PENGISIAN — jangan salin contoh tanpa mengganti SKU dan datanya'];
        $exampleHeader = count($rows) + 1;
        $rows[] = $this->definition['headings'];
        foreach ($this->definition['examples'] as $example) {
            $rows[] = $example;
        }

        return $this->layout = [
            'rows' => $rows,
            'field_title' => $fieldTitle,
            'field_header' => $fieldHeader,
            'field_last' => $fieldLast,
            'rule_title' => $ruleTitle,
            'example_title' => $exampleTitle,
            'example_header' => $exampleHeader,
            'example_last' => count($rows),
            'last_row' => count($rows),
        ];
    }

    private function styleHeader($sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '3F4254']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
        ]);
    }
}
