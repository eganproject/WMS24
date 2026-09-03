<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class StockMutationOverviewSheet extends StockMutationSheet implements FromArray, WithTitle, WithEvents, WithStrictNullComparison
{
    private const ACCENT = '009EF7';

    private ?array $layout = null;

    public function title(): string
    {
        return 'Ringkasan Analisis';
    }

    public function array(): array
    {
        return $this->layout()['rows'];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $layout = $this->layout();

                $sheet->mergeCells('A1:J1');
                $sheet->mergeCells('A2:J2');
                $sheet->mergeCells('A3:J3');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(18)->getColor()->setRGB('181C32');
                $sheet->getStyle('A2:A3')->getFont()->getColor()->setRGB('7E8299');

                foreach ([['A', 'B'], ['C', 'D'], ['E', 'F'], ['G', 'H'], ['I', 'J']] as [$from, $to]) {
                    $sheet->mergeCells($from.'5:'.$to.'5');
                    $sheet->mergeCells($from.'6:'.$to.'6');
                    $sheet->getStyle($from.'5:'.$to.'5')->applyFromArray([
                        'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::ACCENT]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    ]);
                    $sheet->getStyle($from.'6:'.$to.'6')->applyFromArray([
                        'font' => ['bold' => true, 'size' => 15, 'color' => ['rgb' => '181C32']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EAF4FF']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    ]);
                    $sheet->getStyle($from.'5:'.$to.'6')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('B9D9FA');
                }
                $sheet->getRowDimension(5)->setRowHeight(24);
                $sheet->getRowDimension(6)->setRowHeight(30);

                $sheet->mergeCells('A8:J8');
                $this->styleTitle($sheet, 8);
                foreach ([9, 10, 11, 12] as $row) {
                    $sheet->mergeCells('A'.$row.':J'.$row);
                }

                foreach ($layout['sections'] as $section) {
                    $sheet->mergeCells('A'.$section['title'].':J'.$section['title']);
                    $this->styleTitle($sheet, $section['title']);
                    $headerRange = 'A'.$section['header'].':H'.$section['header'];
                    $sheet->getStyle($headerRange)->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '3F4254']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
                    ]);
                    $last = max($section['header'], $section['last']);
                    $tableRange = 'A'.$section['header'].':H'.$last;
                    $sheet->getStyle($tableRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E4E6EF');
                    if ($section['last'] > $section['header']) {
                        $sheet->getStyle('B'.($section['header'] + 1).':H'.$section['last'])->getNumberFormat()->setFormatCode('#,##0');
                        $this->colorNetColumn($sheet, $section['header'] + 1, $section['last'], 'G');
                    }
                }

                $source = $layout['sections']['source'];
                if ($source['last'] > $source['header']) {
                    $sheet->getStyle('H'.($source['header'] + 1).':H'.$source['last'])->getNumberFormat()->setFormatCode('0.00%');
                }

                foreach (['A' => 25, 'B' => 16, 'C' => 16, 'D' => 16, 'E' => 16, 'F' => 16, 'G' => 16, 'H' => 17, 'I' => 12, 'J' => 12] as $column => $width) {
                    $sheet->getColumnDimension($column)->setWidth($width);
                }
                $sheet->getStyle('A1:J'.$layout['last_row'])->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);
                $sheet->freezePane('A5');
                $sheet->setSelectedCell('A1');
            },
        ];
    }

    private function layout(): array
    {
        if ($this->layout !== null) {
            return $this->layout;
        }

        $totals = $this->report->totals();
        $qtyIn = (int) ($totals->qty_in ?? 0);
        $qtyOut = (int) ($totals->qty_out ?? 0);
        $anomalies = (int) ($totals->anomaly_count ?? 0);
        $itemSummary = $this->report->itemWarehouseSummary();
        $sourceSummary = $this->report->sourceSummary();
        $dailySummary = $this->report->dailySummary();
        $warehouseSummary = $this->report->warehouseSummary();

        $topItem = $itemSummary->first();
        $topSource = $sourceSummary->first();
        $busiestDay = $dailySummary->sortByDesc('mutation_count')->first();

        $rows = [
            ['Laporan Mutasi Stok - Ringkasan Analisis'],
            [$this->report->filterSummary()],
            ['Diunduh: '.now()->format('d/m/Y H:i').' | Net mutasi = Qty Masuk - Qty Keluar'],
            [''],
            ['Jumlah Mutasi', '', 'Dokumen Unik', '', 'SKU Unik', '', 'Qty Masuk', '', 'Qty Keluar', ''],
            [(int) ($totals->mutation_count ?? 0), '', (int) ($totals->document_count ?? 0), '', (int) ($totals->sku_count ?? 0), '', $qtyIn, '', $qtyOut, ''],
            [''],
            ['SOROTAN ANALISIS'],
            ['Item dengan aktivitas terbesar: '.($topItem ? trim(($topItem->sku ?? '').' - '.($topItem->item_name ?? ''), ' -').' ('.number_format((int) $topItem->qty_in + (int) $topItem->qty_out, 0, ',', '.').' pcs)' : '-')],
            ['Sumber dengan aktivitas terbesar: '.($topSource ? ($this->report->formatSourceLabel($topSource->source_type, $topSource->source_subtype) ?: 'TANPA SUMBER').' ('.number_format((int) $topSource->qty_in + (int) $topSource->qty_out, 0, ',', '.').' pcs)' : '-')],
            ['Hari tersibuk: '.($busiestDay ? $busiestDay->period_date.' ('.number_format((int) $busiestDay->mutation_count, 0, ',', '.').' mutasi; '.number_format((int) $busiestDay->qty_in + (int) $busiestDay->qty_out, 0, ',', '.').' pcs)' : '-')],
            ['Validasi saldo: '.($anomalies > 0 ? number_format($anomalies, 0, ',', '.').' mutasi perlu diperiksa' : 'tidak ditemukan anomali pada snapshot yang tersedia')],
            [''],
        ];

        $sections = [];
        $daily = $dailySummary->map(function ($row) {
            $in = (int) $row->qty_in;
            $out = (int) $row->qty_out;

            return [$row->period_date, (int) $row->mutation_count, (int) $row->document_count, (int) $row->sku_count, $in, $out, $in - $out, (int) $row->anomaly_count];
        })->all();
        $this->appendSection($rows, $sections, 'daily', 'AKTIVITAS HARIAN', ['Tanggal', 'Mutasi', 'Dokumen', 'SKU Unik', 'Qty Masuk', 'Qty Keluar', 'Net Mutasi', 'Anomali'], $daily);

        $sources = $sourceSummary->map(function ($row) use ($qtyIn, $qtyOut) {
            $in = (int) $row->qty_in;
            $out = (int) $row->qty_out;
            $total = $qtyIn + $qtyOut;

            return [
                $this->report->formatSourceLabel($row->source_type, $row->source_subtype) ?: 'TANPA SUMBER',
                (int) $row->mutation_count,
                (int) $row->document_count,
                (int) $row->sku_count,
                $in,
                $out,
                $in - $out,
                $total > 0 ? ($in + $out) / $total : 0,
            ];
        })->all();
        $this->appendSection($rows, $sections, 'source', 'KOMPOSISI PER SUMBER', ['Sumber', 'Mutasi', 'Dokumen', 'SKU Unik', 'Qty Masuk', 'Qty Keluar', 'Net Mutasi', 'Kontribusi'], $sources);

        $warehouses = $warehouseSummary->map(function ($row) {
            $in = (int) $row->qty_in;
            $out = (int) $row->qty_out;

            return [$row->warehouse_name ?? '-', (int) $row->mutation_count, (int) $row->document_count, (int) $row->sku_count, $in, $out, $in - $out, (int) $row->anomaly_count];
        })->all();
        $this->appendSection($rows, $sections, 'warehouse', 'AKTIVITAS PER GUDANG', ['Gudang', 'Mutasi', 'Dokumen', 'SKU Unik', 'Qty Masuk', 'Qty Keluar', 'Net Mutasi', 'Anomali'], $warehouses);

        return $this->layout = ['rows' => $rows, 'sections' => $sections, 'last_row' => count($rows)];
    }

    private function appendSection(array &$rows, array &$sections, string $key, string $title, array $headings, array $data): void
    {
        $titleRow = count($rows) + 1;
        $rows[] = [$title];
        $headerRow = count($rows) + 1;
        $rows[] = $headings;
        foreach ($data as $row) {
            $rows[] = $row;
        }
        if ($data === []) {
            $rows[] = ['Tidak ada data'];
        }
        $lastRow = count($rows);
        $rows[] = [''];

        $sections[$key] = ['title' => $titleRow, 'header' => $headerRow, 'last' => $lastRow];
    }

    private function styleTitle($sheet, int $row): void
    {
        $sheet->getStyle('A'.$row.':J'.$row)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '181C32']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E4E6EF']],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(23);
    }

    private function colorNetColumn($sheet, int $firstRow, int $lastRow, string $column): void
    {
        for ($row = $firstRow; $row <= $lastRow; $row++) {
            $value = (float) $sheet->getCell($column.$row)->getValue();
            if ($value < 0) {
                $sheet->getStyle($column.$row)->getFont()->getColor()->setRGB('F1416C');
            } elseif ($value > 0) {
                $sheet->getStyle($column.$row)->getFont()->getColor()->setRGB('00A261');
            }
        }
    }
}
