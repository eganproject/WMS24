<?php

namespace App\Exports;

use App\Support\StockRunoutForecastReport;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StockRunoutForecastSummarySheet extends StockRunoutForecastSheet implements FromArray, WithCustomValueBinder, WithEvents, WithStrictNullComparison, WithTitle
{
    private const LAST_COLUMN = 'F';
    private const TOP_LIMIT = 10;

    private array $sheetRows = [];

    /** Penanda posisi tiap bagian agar bisa diberi gaya setelah data ditulis. */
    private array $layout = ['sections' => [], 'headers' => [], 'tables' => [], 'totals' => [], 'formats' => [], 'merges' => [], 'notes' => [], 'findings' => [], 'status' => []];

    private bool $built = false;

    public function title(): string
    {
        return 'Ringkasan';
    }

    public function array(): array
    {
        $this->build();

        return $this->sheetRows;
    }

    private function build(): void
    {
        if ($this->built) {
            return;
        }
        $this->built = true;

        $report = $this->report;
        $summary = $report->summary();
        $byStatus = $this->rows->groupBy('status');
        $emptyCount = $byStatus->get(StockRunoutForecastReport::STATUS_EMPTY)?->count() ?? 0;
        $criticalCount = $byStatus->get(StockRunoutForecastReport::STATUS_CRITICAL)?->count() ?? 0;
        $categories = $report->categoryBreakdown();

        $this->push(['Forecast Ketahanan Stok - Ringkasan']);
        $this->push([$report->filterSummary()]);
        $this->push(['Diunduh: '.now()->format('d/m/Y H:i').' | Stok = gabungan gudang besar + gudang kecil (gudang barang rusak tidak dihitung)']);
        $this->push(['']);

        // Kartu angka utama (baris 5-7).
        $this->push(['SKU PERLU RESTOCK', 'TOTAL UNIT RESTOCK', 'STOK SUDAH HABIS', 'KRITIS (≤ '.StockRunoutForecastReport::CRITICAL_DAYS.' HARI)', 'PENJUALAN / HARI', 'HABIS PALING CEPAT']);
        $this->push([
            $summary['total_items'], $summary['total_restock_need'], $emptyCount, $criticalCount,
            $summary['total_daily_average'],
            $summary['nearest_runout_days'] === null ? '-' : ($summary['nearest_runout_days'] <= 0 ? 'Sudah habis' : $summary['nearest_runout_days']),
        ]);
        $this->push([
            'Habis dalam '.$report->forecastDays.' hari', 'Unit untuk '.$report->forecastDays.' hari ke depan', 'Stok gabungan 0 / minus',
            'Segera habis', 'Total unit terjual / hari',
            $summary['nearest_runout_date'] ? 'Sekitar '.Carbon::parse($summary['nearest_runout_date'])->format('d/m/Y') : '-',
        ]);
        $this->push(['']);

        $this->section('KESIMPULAN');
        foreach ($this->findings($summary, $emptyCount, $criticalCount, $categories) as $index => $finding) {
            $this->layout['findings'][] = $this->push([($index + 1).'. '.$finding]);
        }
        $this->push(['']);

        // Distribusi status urgensi.
        $this->section('DISTRIBUSI STATUS URGENSI');
        $start = $this->header(['Status', 'Jumlah SKU', 'Porsi SKU', 'Unit Restock', 'Arti / Tindakan', '']);
        $meaning = [
            StockRunoutForecastReport::STATUS_EMPTY => 'Stok sudah 0 / minus, pesanan berisiko tidak terpenuhi. Restock segera.',
            StockRunoutForecastReport::STATUS_CRITICAL => 'Habis dalam '.StockRunoutForecastReport::CRITICAL_DAYS.' hari atau kurang. Prioritaskan pemesanan.',
            StockRunoutForecastReport::STATUS_WARNING => 'Habis sebelum periode forecast berakhir. Jadwalkan restock.',
        ];
        $total = $this->rows->count();
        foreach (StockRunoutForecastReport::STATUS_LABELS as $status => $label) {
            $rows = $byStatus->get($status, collect());
            $row = $this->push([$label, $rows->count(), $total > 0 ? $rows->count() / $total : 0, (int) $rows->sum('restock_need'), $meaning[$status], '']);
            $this->layout['merges'][] = "E{$row}:F{$row}";
            $this->layout['status'][] = "A{$row}";
        }
        $endRow = $this->push(['Total', $total, $total > 0 ? 1 : 0, (int) $this->rows->sum('restock_need'), '', '']);
        $this->layout['merges'][] = "E{$start}:F{$start}";
        $this->layout['merges'][] = "E{$endRow}:F{$endRow}";
        $this->table($start, $endRow);
        $this->layout['totals'][] = "A{$endRow}:F{$endRow}";
        $this->format('B'.($start + 1).":B{$endRow}", '#,##0');
        $this->format('C'.($start + 1).":C{$endRow}", '0.0%');
        $this->format('D'.($start + 1).":D{$endRow}", self::QTY_FORMAT);
        $this->push(['']);

        // Kebutuhan per kategori.
        $this->section('KEBUTUHAN RESTOCK PER KATEGORI');
        $start = $this->header(['Kategori', 'Jumlah SKU', 'SKU Mendesak', 'Penjualan / Hari', 'Unit Restock', 'Porsi Restock']);
        $endRow = $start;
        $totalRestock = (int) $categories->sum('restock_need');
        if ($categories->isEmpty()) {
            $endRow = $this->push(['Tidak ada SKU yang perlu restock', 0, 0, 0, 0, 0]);
        }
        foreach ($categories as $category) {
            $endRow = $this->push([
                $category->category, $category->items, $category->urgent_items, $category->daily_average,
                $category->restock_need, $totalRestock > 0 ? $category->restock_need / $totalRestock : 0,
            ]);
        }
        $endRow = $this->push([
            'Total', (int) $categories->sum('items'), (int) $categories->sum('urgent_items'),
            round((float) $categories->sum('daily_average'), 2), $totalRestock, $totalRestock > 0 ? 1 : 0,
        ]);
        $this->table($start, $endRow);
        $this->layout['totals'][] = "A{$endRow}:F{$endRow}";
        $this->format('B'.($start + 1).":C{$endRow}", '#,##0');
        $this->format('D'.($start + 1).":D{$endRow}", self::DECIMAL_FORMAT);
        $this->format('E'.($start + 1).":E{$endRow}", self::QTY_FORMAT);
        $this->format('F'.($start + 1).":F{$endRow}", '0.0%');
        $this->layout['notes'][] = $this->push(['SKU Mendesak = SKU berstatus Stok Habis atau Kritis.']);
        $this->push(['']);

        $this->topTable(self::TOP_LIMIT.' SKU PALING CEPAT HABIS', $this->rows->sortBy([['days_until_runout', 'asc'], ['restock_need', 'desc']]));
        $this->topTable(self::TOP_LIMIT.' SKU DENGAN KEBUTUHAN RESTOCK TERBESAR', $this->rows->sortBy([['restock_need', 'desc'], ['days_until_runout', 'asc']]));

        // Penjelasan istilah.
        $this->section('CARA MEMBACA LAPORAN');
        $start = $this->header(['Istilah', 'Penjelasan', '', '', '', '']);
        $this->layout['merges'][] = "B{$start}:F{$start}";
        $glossary = [
            'Stok Gabungan' => 'Total stok di gudang besar + gudang kecil saat laporan diunduh. Gudang barang rusak tidak dihitung.',
            'Penjualan Histori' => 'Barang keluar untuk pesanan selama '.$report->historyDays.' hari terakhir: QC scan resi marketplace + outbound manual. Transfer, penyesuaian, dan barang rusak tidak dihitung.',
            'Rata-rata / Hari' => 'Penjualan histori ÷ '.$report->historyDays.' hari.',
            'Kebutuhan Periode' => 'Rata-rata / hari × '.$report->forecastDays.' hari forecast.',
            'Sisa Proyeksi' => 'Stok gabungan − kebutuhan periode. Nilai minus berarti stok tidak cukup sampai akhir periode forecast.',
            'Perlu Restock' => 'Jumlah unit minimal yang perlu ditambahkan agar stok cukup selama periode forecast (dibulatkan ke atas).',
            'Estimasi Habis' => 'Stok gabungan ÷ rata-rata / hari. Tanggal habis dihitung dari tanggal laporan diunduh.',
        ];
        foreach ($glossary as $term => $description) {
            $endRow = $this->push([$term, $description, '', '', '', '']);
            $this->layout['merges'][] = "B{$endRow}:F{$endRow}";
        }
        $this->table($start, $endRow);
        $this->layout['notes'][] = $this->push(['Detail seluruh SKU tersedia di sheet "Detail Restock per SKU" (bisa difilter dan diurutkan).']);
    }

    /** Kalimat kesimpulan otomatis agar laporan bisa langsung dibaca tanpa analisis tambahan. */
    private function findings(array $summary, int $emptyCount, int $criticalCount, $categories): array
    {
        $report = $this->report;
        if ($summary['total_items'] === 0) {
            return [sprintf('Tidak ada SKU yang diproyeksikan habis dalam %d hari ke depan berdasarkan penjualan %d hari terakhir.', $report->forecastDays, $report->historyDays)];
        }

        $findings = [sprintf(
            '%s SKU diproyeksikan habis dalam %d hari ke depan, dengan total kebutuhan restock %s unit.',
            $this->number($summary['total_items']), $report->forecastDays, $this->number($summary['total_restock_need'])
        )];

        if ($emptyCount + $criticalCount > 0) {
            $findings[] = sprintf(
                '%s SKU stoknya sudah habis dan %s SKU kritis (habis ≤ %d hari). SKU ini perlu diprioritaskan.',
                $this->number($emptyCount), $this->number($criticalCount), StockRunoutForecastReport::CRITICAL_DAYS
            );
        } else {
            $findings[] = sprintf('Belum ada SKU yang habis atau kritis (≤ %d hari), sehingga masih ada waktu untuk menjadwalkan restock.', StockRunoutForecastReport::CRITICAL_DAYS);
        }

        $fastest = $this->rows->sortBy('days_until_runout')->first();
        if ($fastest && $fastest['stock'] <= 0) {
            $findings[] = sprintf(
                'SKU paling mendesak: %s (%s), stok sudah habis (%s unit) dengan penjualan rata-rata %s unit/hari.',
                $fastest['sku'], $fastest['name'], $this->number($fastest['stock']), number_format($fastest['daily_average'], 2, ',', '.')
            );
        } elseif ($fastest) {
            $findings[] = sprintf(
                'SKU paling cepat habis: %s (%s), stok %s unit, estimasi habis %s hari lagi (%s).',
                $fastest['sku'], $fastest['name'], $this->number($fastest['stock']),
                number_format($fastest['days_until_runout'], 1, ',', '.'), Carbon::parse($fastest['runout_date'])->format('d/m/Y')
            );
        }

        $topCategory = $categories->first();
        if ($topCategory && $topCategory->restock_need > 0) {
            $findings[] = sprintf(
                'Kategori dengan kebutuhan restock terbesar: %s (%s unit dari %s SKU).',
                $topCategory->category, $this->number($topCategory->restock_need), $this->number($topCategory->items)
            );
        }

        return $findings;
    }

    private function topTable(string $title, $rows): void
    {
        $this->section($title);
        $start = $this->header(['SKU', 'Nama Barang', 'Status', 'Stok', 'Estimasi Habis', 'Perlu Restock']);
        $endRow = $start;
        $rows = $rows->take(self::TOP_LIMIT);
        if ($rows->isEmpty()) {
            $endRow = $this->push(['Tidak ada data', '', '-', 0, 0, 0]);
        }
        foreach ($rows as $row) {
            $endRow = $this->push([$row['sku'], $row['name'], $row['status_label'], $row['stock'], $row['days_until_runout'], $row['restock_need']]);
            $this->layout['status'][] = "C{$endRow}";
        }
        $this->table($start, $endRow);
        $this->format('D'.($start + 1).":D{$endRow}", self::QTY_FORMAT);
        $this->format('E'.($start + 1).":E{$endRow}", self::DAYS_FORMAT);
        $this->format('F'.($start + 1).":F{$endRow}", self::QTY_FORMAT);
        $this->push(['']);
    }

    private function number(int|float $value): string
    {
        return number_format($value, 0, ',', '.');
    }

    private function push(array $row): int
    {
        $this->sheetRows[] = $row;

        return count($this->sheetRows);
    }

    private function section(string $title): void
    {
        $this->layout['sections'][] = $this->push([$title]);
    }

    private function header(array $columns): int
    {
        $row = $this->push($columns);
        $this->layout['headers'][] = "A{$row}:".self::LAST_COLUMN.$row;

        return $row;
    }

    private function table(int $start, int $end): void
    {
        $this->layout['tables'][] = "A{$start}:".self::LAST_COLUMN.$end;
    }

    private function format(string $range, string $format): void
    {
        $this->layout['formats'][] = [$range, $format];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $this->build();
                $sheet = $event->sheet->getDelegate();
                $last = self::LAST_COLUMN;
                $lastRow = count($this->sheetRows);

                foreach ([1, 2, 3] as $row) {
                    $sheet->mergeCells("A{$row}:{$last}{$row}");
                }
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(18)->getColor()->setRGB('181C32');
                $sheet->getStyle('A2:A3')->getFont()->getColor()->setRGB('7E8299');
                $sheet->getStyle('A2:A3')->getAlignment()->setWrapText(true);
                $sheet->getRowDimension(2)->setRowHeight(30);

                $this->styleCards($sheet);

                foreach ($this->layout['sections'] as $row) {
                    $sheet->mergeCells("A{$row}:{$last}{$row}");
                    $sheet->getStyle("A{$row}:{$last}{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '3F4254']],
                    ]);
                    $sheet->getRowDimension($row)->setRowHeight(22);
                }
                foreach ($this->layout['findings'] as $row) {
                    $sheet->mergeCells("A{$row}:{$last}{$row}");
                    $sheet->getStyle("A{$row}")->getAlignment()->setWrapText(true)->setIndent(1);
                    $sheet->getStyle("A{$row}:{$last}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8F9FC');
                    $sheet->getRowDimension($row)->setRowHeight(30);
                }
                foreach ($this->layout['headers'] as $range) {
                    $this->styleHeader($sheet, $range);
                }
                foreach ($this->layout['tables'] as $range) {
                    $this->styleBorders($sheet, $range);
                }
                foreach ($this->layout['totals'] as $range) {
                    $this->styleTotal($sheet, $range);
                }
                foreach ($this->layout['formats'] as [$range, $format]) {
                    $sheet->getStyle($range)->getNumberFormat()->setFormatCode($format);
                }
                foreach ($this->layout['notes'] as $row) {
                    $sheet->mergeCells("A{$row}:{$last}{$row}");
                    $sheet->getStyle("A{$row}")->getFont()->setItalic(true)->setSize(9)->getColor()->setRGB('7E8299');
                }
                foreach ($this->layout['merges'] as $range) {
                    $sheet->mergeCells($range);
                }
                foreach ($this->layout['status'] as $cell) {
                    $this->addTextHighlight($sheet, $cell, $this->statusHighlightRules());
                }

                foreach (['A' => 30, 'B' => 40, 'C' => 18, 'D' => 20, 'E' => 18, 'F' => 20] as $column => $width) {
                    $sheet->getColumnDimension($column)->setWidth($width);
                }
                $sheet->getStyle("A4:{$last}{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
                $this->setPrintLayout($sheet);
                $sheet->getPageSetup()->setOrientation('portrait');
            },
        ];
    }

    private function styleCards(Worksheet $sheet): void
    {
        $sheet->getStyle('A5:F5')->applyFromArray([
            'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '009EF7']],
        ]);
        $sheet->getStyle('B5:D5')->getFill()->getStartColor()->setRGB('F1416C');
        $sheet->getStyle('A6:F6')->applyFromArray([
            'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '181C32']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EAF4FF']],
        ]);
        $sheet->getStyle('B6:D6')->getFont()->getColor()->setRGB('D9214E');
        $sheet->getStyle('A7:F7')->applyFromArray([
            'font' => ['size' => 8, 'color' => ['rgb' => '7E8299']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EAF4FF']],
        ]);
        $sheet->getStyle('A5:F7')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);
        $sheet->getStyle('A5:F7')->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('B9D9FA');
        $sheet->getStyle('A5:F7')->getBorders()->getVertical()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('B9D9FA');
        $sheet->getStyle('A6:D6')->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle('E6')->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('F6')->getNumberFormat()->setFormatCode(self::DAYS_FORMAT);
        $sheet->getRowDimension(5)->setRowHeight(24);
        $sheet->getRowDimension(6)->setRowHeight(32);
    }
}
