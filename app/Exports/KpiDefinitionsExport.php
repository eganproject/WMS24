<?php

namespace App\Exports;

use App\Models\KpiDefinition;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class KpiDefinitionsExport implements FromCollection, WithHeadings, WithTitle, WithCustomStartCell, ShouldAutoSize, WithStyles, WithEvents
{
    public function title(): string
    {
        return 'KPI Master';
    }

    public function startCell(): string
    {
        return 'A5';
    }

    public function collection()
    {
        return KpiDefinition::query()
            ->orderBy('role_name')
            ->orderBy('metric_name')
            ->get()
            ->map(fn (KpiDefinition $row) => [
                $row->role_name,
                $row->metric_name,
                $row->description,
                $row->target_operator,
                (float) $row->target_value,
                $row->unit,
                (float) $row->weight,
                $row->period_type,
                $row->source_type,
                $row->formula_key,
                $row->is_active ? 'Aktif' : 'Nonaktif',
                $row->created_at?->format('Y-m-d H:i') ?? '',
                $row->updated_at?->format('Y-m-d H:i') ?? '',
            ]);
    }

    public function headings(): array
    {
        return [
            'Role/Jabatan',
            'KPI',
            'Deskripsi',
            'Operator Target',
            'Target',
            'Unit',
            'Bobot %',
            'Periode',
            'Sumber',
            'Formula Key',
            'Status',
            'Created At',
            'Updated At',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $rowCount = $this->collection()->count();
        $sheet->mergeCells('A1:M1');
        $sheet->mergeCells('A2:M2');
        $sheet->mergeCells('A3:M3');
        $sheet->setCellValue('A1', 'KPI Master');
        $sheet->setCellValue('A2', 'Export master KPI dari sistem');
        $sheet->setCellValue('A3', 'Total KPI: '.number_format($rowCount, 0, ',', '.'));

        return [
            1 => ['font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '181C32']]],
            2 => ['font' => ['color' => ['rgb' => '7E8299']]],
            3 => ['font' => ['bold' => true, 'color' => ['rgb' => '3F4254']]],
            5 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1B84FF']],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = max(5, 5 + $this->collection()->count());
                $range = 'A5:M'.$lastRow;

                $sheet->freezePane('A6');
                $sheet->setAutoFilter($range);
                $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E4E6EF');
                $sheet->getStyle('A1:M'.$lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle('C6:C'.$lastRow)->getAlignment()->setWrapText(true);
                $sheet->getStyle('E6:G'.$lastRow)->getNumberFormat()->setFormatCode('#,##0.00');

                foreach (['A' => 24, 'B' => 34, 'C' => 60, 'J' => 34] as $column => $width) {
                    $sheet->getColumnDimension($column)->setWidth($width);
                }
            },
        ];
    }
}
