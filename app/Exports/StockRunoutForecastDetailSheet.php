<?php

namespace App\Exports;

use App\Support\StockRunoutForecastReport;
use Illuminate\Support\Carbon;
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
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class StockRunoutForecastDetailSheet extends StockRunoutForecastSheet implements FromCollection, WithCustomStartCell, WithCustomValueBinder, WithEvents, WithHeadings, WithStrictNullComparison, WithTitle
{
    private const HEADER_ROW = 6;
    private const LAST_COLUMN = 'O';

    public function title(): string
    {
        return 'Detail Restock per SKU';
    }

    public function startCell(): string
    {
        return 'A'.self::HEADER_ROW;
    }

    public function headings(): array
    {
        $forecast = $this->report->forecastDays;
        $history = $this->report->historyDays;

        return [
            'No', 'SKU', 'Nama Barang', 'Kategori', 'Status',
            'Stok Gabungan', "Total Keluar {$history} Hari", 'Keluar QC Scan Resi', 'Keluar Outbound Manual', 'Rata-rata / Hari', "Kebutuhan {$forecast} Hari",
            'Sisa Proyeksi', 'Perlu Restock', 'Estimasi Habis', 'Tanggal Habis',
        ];
    }

    public function collection(): Collection
    {
        return $this->rows->map(fn (array $row, int $index) => [
            $index + 1,
            $row['sku'],
            $row['name'],
            $row['category'],
            $row['status_label'],
            $row['stock'],
            $row['total_outbound'],
            $row['qc_outbound'],
            $row['manual_outbound'],
            $row['daily_average'],
            $row['forecast_demand'],
            $row['forecast_stock'],
            $row['restock_need'],
            $row['days_until_runout'],
            Date::PHPToExcel(Carbon::parse($row['runout_date'])),
        ]);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $last = self::LAST_COLUMN;
                $header = self::HEADER_ROW;
                $firstRow = $header + 1;
                $count = $this->rows->count();
                $lastRow = $header + $count;
                $totalRow = $lastRow + 1;

                foreach ([1, 2, 3, 4] as $row) {
                    $sheet->mergeCells("A{$row}:{$last}{$row}");
                }
                $sheet->setCellValue('A1', 'Forecast Ketahanan Stok - Detail Restock per SKU');
                $sheet->setCellValue('A2', $this->report->filterSummary());
                $sheet->setCellValue('A3', sprintf(
                    'Perlu Restock = (rata-rata penjualan/hari × %d hari) − stok gabungan, dibulatkan ke atas. Urutan: %s (%s). Status Kritis = habis dalam ≤ %d hari.',
                    $this->report->forecastDays,
                    StockRunoutForecastReport::SORT_LABELS[$this->report->sort],
                    $this->report->direction === 'asc' ? 'kecil ke besar' : 'besar ke kecil',
                    StockRunoutForecastReport::CRITICAL_DAYS
                ));
                $sheet->setCellValue('A4', sprintf('Total %s SKU | Diunduh %s', number_format($count, 0, ',', '.'), now()->format('d/m/Y H:i')));
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB('181C32');
                $sheet->getStyle('A2:A3')->getFont()->getColor()->setRGB('7E8299');
                $sheet->getStyle('A4')->getFont()->setBold(true)->getColor()->setRGB('3F4254');

                $this->styleHeader($sheet, "A{$header}:{$last}{$header}");
                // Kolom keputusan restock diberi warna berbeda agar langsung terlihat.
                $this->styleHeader($sheet, "G{$header}:I{$header}", '0063B1');
                $this->styleHeader($sheet, "M{$header}:O{$header}", 'F1416C');
                $sheet->getRowDimension($header)->setRowHeight(34);

                $sheet->setCellValue("A{$totalRow}", 'TOTAL');
                $sheet->mergeCells("A{$totalRow}:E{$totalRow}");
                foreach (range('F', 'M') as $column) {
                    if ($count > 0) {
                        $sheet->setCellValueExplicit("{$column}{$totalRow}", "=SUBTOTAL(109,{$column}{$firstRow}:{$column}{$lastRow})", DataType::TYPE_FORMULA);
                    } else {
                        $sheet->setCellValue("{$column}{$totalRow}", 0);
                    }
                }
                $this->styleTotal($sheet, "A{$totalRow}:{$last}{$totalRow}");

                $sheet->freezePane('D'.$firstRow);
                $sheet->setAutoFilter("A{$header}:{$last}".max($header, $lastRow));
                $this->styleBorders($sheet, "A{$header}:{$last}{$totalRow}");
                $sheet->getStyle("A{$firstRow}:A{$totalRow}")->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle("F{$firstRow}:I{$totalRow}")->getNumberFormat()->setFormatCode(self::QTY_FORMAT);
                $sheet->getStyle("J{$firstRow}:L{$totalRow}")->getNumberFormat()->setFormatCode(self::DECIMAL_FORMAT);
                $sheet->getStyle("M{$firstRow}:M{$totalRow}")->getNumberFormat()->setFormatCode(self::QTY_FORMAT);
                $sheet->getStyle("N{$firstRow}:N{$lastRow}")->getNumberFormat()->setFormatCode(self::DAYS_FORMAT);
                $sheet->getStyle("O{$firstRow}:O{$lastRow}")->getNumberFormat()->setFormatCode('dd/mm/yyyy');
                $sheet->getStyle("M{$firstRow}:M{$totalRow}")->getFont()->setBold(true)->getColor()->setRGB('D9214E');
                $sheet->getStyle("A1:{$last}{$totalRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle("C{$firstRow}:D{$lastRow}")->getAlignment()->setWrapText(true);
                $sheet->getStyle("E{$firstRow}:E{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("O{$firstRow}:O{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                if ($count > 0) {
                    $this->addTextHighlight($sheet, "E{$firstRow}:E{$lastRow}", $this->statusHighlightRules());
                }

                foreach ([
                    'A' => 7, 'B' => 20, 'C' => 40, 'D' => 20, 'E' => 15, 'F' => 13, 'G' => 14, 'H' => 14,
                    'I' => 15, 'J' => 13, 'K' => 14, 'L' => 13, 'M' => 13, 'N' => 14, 'O' => 14,
                ] as $column => $width) {
                    $sheet->getColumnDimension($column)->setWidth($width);
                }
                $this->setPrintLayout($sheet);
                $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($header, $header);
            },
        ];
    }
}
