<?php

namespace App\Exports;

use App\Support\StockMovementExportReport;
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
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StockMovementAnalysisTableSheet extends StockMovementAnalysisSheet implements FromCollection, WithHeadings, WithTitle, WithCustomStartCell, WithStyles, WithEvents, WithStrictNullComparison, WithCustomValueBinder
{
    private ?Collection $rows = null;

    public function __construct(
        StockMovementExportReport $report,
        private string $sheetTitle,
        private bool $attentionOnly = false
    ) {
        parent::__construct($report);
    }

    public function title(): string
    {
        return $this->sheetTitle;
    }

    public function startCell(): string
    {
        return 'A5';
    }

    public function headings(): array
    {
        return [
            'No', 'SKU', 'Nama Item', 'Status Item', 'Kategori', 'Prioritas', 'Keluar Aktual',
            'Dokumen', 'Rata-rata / Hari', 'Stok Awal', 'Total Masuk', 'Total Keluar',
            'Saldo Akhir', 'Turnover', 'Ketahanan Stok (Hari)', 'Terakhir Keluar', 'Rekomendasi',
        ];
    }

    public function collection(): Collection
    {
        if ($this->rows !== null) {
            return $this->rows;
        }

        $source = $this->attentionOnly ? $this->report->attentionRows() : $this->report->rows();

        return $this->rows = $source->values()->map(fn ($row, int $index) => [
            $index + 1,
            $row->sku,
            $row->item_name,
            ($row->item_status ?: 'active') === 'active' ? 'Aktif' : 'Nonaktif',
            $this->report->categoryLabel((string) $row->movement_category),
            $this->report->priority($row),
            (int) $row->demand_out,
            (int) $row->demand_documents,
            round((float) $row->average_daily_out, 2),
            (int) $row->opening_stock,
            (int) $row->stock_in,
            (int) $row->stock_out,
            (int) $row->ending_stock,
            $row->turnover_rate !== null ? round((float) $row->turnover_rate, 2) : null,
            $row->stock_coverage_days !== null ? round((float) $row->stock_coverage_days, 1) : null,
            $row->last_out_at ? Carbon::parse($row->last_out_at)->format('d/m/Y H:i') : 'Belum pernah',
            $this->report->recommendation($row),
        ]);
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->mergeCells('A1:Q1');
        $sheet->mergeCells('A2:Q2');
        $sheet->mergeCells('A3:Q3');
        $sheet->setCellValue('A1', $this->attentionOnly
            ? 'Analisis Pergerakan Stok - Fokus Tindak Lanjut'
            : 'Analisis Pergerakan Stok - Detail Seluruh SKU');
        $sheet->setCellValue('A2', $this->report->filterSummary());
        $sheet->setCellValue('A3', sprintf(
            'Total baris: %s | Diunduh: %s%s',
            number_format($this->collection()->count(), 0, ',', '.'),
            now()->format('d/m/Y H:i'),
            $this->attentionOnly ? ' | Fokus: dead/slow stock, tanpa stok, atau ketahanan maksimal 14 hari' : ''
        ));

        return [
            1 => ['font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '181C32']]],
            2 => ['font' => ['color' => ['rgb' => '7E8299']]],
            3 => ['font' => ['bold' => true, 'color' => ['rgb' => '3F4254']]],
            5 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $this->attentionOnly ? 'F1416C' : '009EF7']],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = max(5, 5 + $this->collection()->count());
                $range = 'A5:Q'.$lastRow;

                $sheet->freezePane('A6');
                $sheet->setAutoFilter($range);
                $borderRange = $this->collection()->count() <= 5000 ? $range : 'A5:Q5';
                $sheet->getStyle($borderRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E4E6EF');
                $sheet->getStyle('A1:Q'.$lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle('A5:Q5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);
                $sheet->getRowDimension(5)->setRowHeight(36);

                if ($lastRow > 5) {
                    foreach (['A', 'G', 'H', 'J', 'K', 'L', 'M'] as $column) {
                        $sheet->getStyle($column.'6:'.$column.$lastRow)->getNumberFormat()->setFormatCode('#,##0');
                        $sheet->getStyle($column.'6:'.$column.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    }
                    foreach (['I', 'N'] as $column) {
                        $sheet->getStyle($column.'6:'.$column.$lastRow)->getNumberFormat()->setFormatCode('#,##0.00');
                    }
                    $sheet->getStyle('O6:O'.$lastRow)->getNumberFormat()->setFormatCode('#,##0.0');
                    $sheet->getStyle('C6:C'.$lastRow)->getAlignment()->setWrapText(true);
                    $sheet->getStyle('Q6:Q'.$lastRow)->getAlignment()->setWrapText(true);
                    $this->addCategoryFormatting($sheet, $lastRow);
                    $this->addPriorityFormatting($sheet, $lastRow);
                }

                foreach ([
                    'A' => 7, 'B' => 20, 'C' => 38, 'D' => 13, 'E' => 18, 'F' => 13,
                    'G' => 16, 'H' => 12, 'I' => 17, 'J' => 14, 'K' => 14, 'L' => 14,
                    'M' => 14, 'N' => 13, 'O' => 20, 'P' => 20, 'Q' => 55,
                ] as $column => $width) {
                    $sheet->getColumnDimension($column)->setWidth($width);
                }

                $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);
                $sheet->getPageMargins()->setTop(0.4)->setRight(0.3)->setBottom(0.4)->setLeft(0.3);
                $sheet->setSelectedCell('A1');
            },
        ];
    }

    private function addCategoryFormatting(Worksheet $sheet, int $lastRow): void
    {
        foreach ([
            ['Fast Moving', 'E8FFF3', '00875A'],
            ['Medium Moving', 'EAF4FF', '0063B1'],
            ['Slow Moving', 'FFF8DD', '946200'],
            ['Dead Stock', 'FFF0F3', 'C9284E'],
            ['Tanpa Stok', 'F1F1F2', '5E6278'],
        ] as [$value, $fill, $font]) {
            $condition = new Conditional;
            $condition->setConditionType(Conditional::CONDITION_CELLIS)->setOperatorType(Conditional::OPERATOR_EQUAL)->addCondition('"'.$value.'"');
            $condition->getStyle()->getFont()->setBold(true)->getColor()->setRGB($font);
            $condition->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($fill);
            $styles = $sheet->getStyle('E6:E'.$lastRow)->getConditionalStyles();
            $styles[] = $condition;
            $sheet->getStyle('E6:E'.$lastRow)->setConditionalStyles($styles);
        }
    }

    private function addPriorityFormatting(Worksheet $sheet, int $lastRow): void
    {
        foreach ([
            ['Kritis', 'F1416C'],
            ['Tinggi', 'F79009'],
            ['Menengah', 'FFC700'],
            ['Normal', '50CD89'],
        ] as [$value, $color]) {
            $condition = new Conditional;
            $condition->setConditionType(Conditional::CONDITION_CELLIS)->setOperatorType(Conditional::OPERATOR_EQUAL)->addCondition('"'.$value.'"');
            $condition->getStyle()->getFont()->setBold(true)->getColor()->setRGB($color);
            $styles = $sheet->getStyle('F6:F'.$lastRow)->getConditionalStyles();
            $styles[] = $condition;
            $sheet->getStyle('F6:F'.$lastRow)->setConditionalStyles($styles);
        }
    }
}
