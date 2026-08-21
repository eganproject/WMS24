<?php

namespace App\Exports;

use App\Models\InboundItem;
use App\Models\InboundTransaction;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCharts;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Halaman ringkasan: kartu KPI, tren harian, SKU & supplier terbanyak,
 * dan sebaran status — masing-masing dengan grafiknya.
 */
class InboundReceiptsOverviewSheet extends InboundReceiptsSheet implements FromArray, WithTitle, WithEvents, WithCharts, WithStrictNullComparison
{
    private const SHEET_TITLE = 'Ringkasan Grafik';

    /** Tinggi minimum satu blok agar grafik di sebelahnya tidak saling menimpa. */
    private const CHART_ROWS = 16;

    private const ACCENT = '009EF7';

    /**
     * Baris pemisah. Harus berisi satu sel kosong, bukan array kosong:
     * ArrayHelper::ensureMultipleRows() membuang array kosong sehingga
     * seluruh posisi baris (dan acuan grafik) ikut bergeser.
     */
    private const BLANK_ROW = [''];

    private ?array $layout = null;

    public function title(): string
    {
        return self::SHEET_TITLE;
    }

    public function array(): array
    {
        return $this->layout()['rows'];
    }

    public function charts(): array
    {
        $layout = $this->layout();
        $charts = [];

        $trend = $layout['sections']['trend'];
        if ($trend['count'] > 0) {
            $charts[] = $this->makeChart(
                'chart_trend',
                'Volume Penerimaan per '.$trend['unit'],
                DataSeries::TYPE_BARCHART,
                $this->ref('A', $trend['first'], $trend['last']),
                [
                    ['label' => $this->ref('D', $trend['header']), 'values' => $this->ref('D', $trend['first'], $trend['last']), 'color' => self::ACCENT],
                ],
                $trend['count'],
                'F'.$trend['title'],
                'N'.($trend['title'] + self::CHART_ROWS - 1)
            );
        }

        $sku = $layout['sections']['sku'];
        if ($sku['count'] > 0) {
            $charts[] = $this->makeChart(
                'chart_sku',
                'SKU Terbanyak Diterima (Qty)',
                DataSeries::TYPE_BARCHART,
                $this->ref('B', $sku['first'], $sku['last']),
                [
                    ['label' => $this->ref('D', $sku['header']), 'values' => $this->ref('D', $sku['first'], $sku['last']), 'color' => '50CD89'],
                ],
                $sku['count'],
                'G'.$sku['title'],
                'N'.($sku['title'] + self::CHART_ROWS - 1),
                DataSeries::DIRECTION_BAR
            );
        }

        $supplier = $layout['sections']['supplier'];
        if ($supplier['count'] > 0) {
            $charts[] = $this->makeChart(
                'chart_supplier',
                'Supplier Terbanyak (Qty)',
                DataSeries::TYPE_BARCHART,
                $this->ref('A', $supplier['first'], $supplier['last']),
                [
                    ['label' => $this->ref('C', $supplier['header']), 'values' => $this->ref('C', $supplier['first'], $supplier['last']), 'color' => 'FFC700'],
                ],
                $supplier['count'],
                'F'.$supplier['title'],
                'N'.($supplier['title'] + self::CHART_ROWS - 1),
                DataSeries::DIRECTION_BAR
            );
        }

        $status = $layout['sections']['status'];
        if ($status['count'] > 0) {
            $charts[] = $this->makeChart(
                'chart_status',
                'Sebaran Status Dokumen',
                DataSeries::TYPE_PIECHART,
                $this->ref('A', $status['first'], $status['last']),
                [
                    ['label' => $this->ref('B', $status['header']), 'values' => $this->ref('B', $status['first'], $status['last'])],
                ],
                $status['count'],
                'F'.$status['title'],
                'N'.($status['title'] + self::CHART_ROWS - 1)
            );
        }

        return $charts;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $layout = $this->layout();

                $sheet->mergeCells('A1:N1');
                $sheet->mergeCells('A2:N2');
                $sheet->mergeCells('A3:N3');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(18)->getColor()->setRGB('181C32');
                $sheet->getStyle('A2')->getFont()->getColor()->setRGB('7E8299');
                $sheet->getStyle('A3')->getFont()->getColor()->setRGB('7E8299');

                $this->styleKpiCards($sheet, $layout['sections']['kpi']);

                foreach (['trend', 'sku', 'supplier', 'status'] as $key) {
                    $this->styleSection($sheet, $layout['sections'][$key]);
                }

                foreach (['A' => 26, 'B' => 30, 'C' => 16, 'D' => 16, 'E' => 16] as $column => $width) {
                    $sheet->getColumnDimension($column)->setWidth($width);
                }
                foreach (range('F', 'N') as $column) {
                    $sheet->getColumnDimension($column)->setWidth(11);
                }

                $sheet->setSelectedCell('A1');
            },
        ];
    }

    private function styleKpiCards($sheet, array $kpi): void
    {
        $sheet->mergeCells('A'.$kpi['title'].':N'.$kpi['title']);
        $sheet->getStyle('A'.$kpi['title'])->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('181C32');

        $columns = $kpi['columns'];
        $labelRow = $kpi['label'];
        $valueRow = $kpi['value'];

        foreach ($columns as $index => $column) {
            $isPercent = ($kpi['percent'][$index] ?? false);
            $sheet->getStyle($column.$labelRow)->applyFromArray([
                'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::ACCENT]],
            ]);
            $sheet->getStyle($column.$valueRow)->applyFromArray([
                'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => '181C32']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F5F8FA']],
            ]);
            $sheet->getStyle($column.$valueRow)->getNumberFormat()->setFormatCode($isPercent ? '0.0"%"' : '#,##0');
        }

        $range = $columns[0].$labelRow.':'.end($columns).$valueRow;
        $sheet->getStyle($range)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
        $sheet->getStyle($range)->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E4E6EF');
        $sheet->getRowDimension($labelRow)->setRowHeight(30);
        $sheet->getRowDimension($valueRow)->setRowHeight(26);
    }

    private function styleSection($sheet, array $section): void
    {
        $lastColumn = $section['last_column'];

        $sheet->mergeCells('A'.$section['title'].':'.$lastColumn.$section['title']);
        $sheet->getStyle('A'.$section['title'])->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('181C32');

        $sheet->getStyle('A'.$section['header'].':'.$lastColumn.$section['header'])->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::ACCENT]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        if ($section['count'] < 1) {
            return;
        }

        $body = 'A'.$section['first'].':'.$lastColumn.$section['last'];
        $sheet->getStyle('A'.$section['header'].':'.$lastColumn.$section['last'])->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E4E6EF');
        $sheet->getStyle($body)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        foreach ($section['numeric'] as $column) {
            $cells = $column.$section['first'].':'.$column.$section['last'];
            $sheet->getStyle($cells)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle($cells)->getNumberFormat()->setFormatCode('#,##0');
        }

        foreach ($section['percent'] ?? [] as $column) {
            $cells = $column.$section['first'].':'.$column.$section['last'];
            $sheet->getStyle($cells)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle($cells)->getNumberFormat()->setFormatCode('0.0"%"');
        }
    }

    /**
     * Susun seluruh isi sheet sekaligus posisi tiap blok, supaya grafik
     * (yang dibuat sebelum sheet ditulis) menunjuk ke baris yang benar.
     */
    private function layout(): array
    {
        if ($this->layout !== null) {
            return $this->layout;
        }

        $rows = [];
        $sections = [];

        $rows[] = ['Laporan Penerimaan Barang'];
        $rows[] = [$this->filterSummary()];
        $rows[] = ['Diunduh: '.now()->format('d/m/Y H:i')];
        $rows[] = self::BLANK_ROW;

        $metrics = $this->metrics();
        $sections['kpi'] = [
            'title' => count($rows) + 1,
            'label' => count($rows) + 2,
            'value' => count($rows) + 3,
            'columns' => ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'],
            'percent' => [false, false, false, false, false, false, false, true],
        ];
        $rows[] = ['RINGKASAN PERIODE'];
        $rows[] = array_keys($metrics);
        $rows[] = array_values($metrics);
        $rows[] = self::BLANK_ROW;

        $trend = $this->trend();
        $sections['trend'] = $this->appendSection(
            $rows,
            'TREN PENERIMAAN PER '.strtoupper($trend['unit']),
            [$trend['unit'], 'Dokumen', 'Total Koli', 'Total Qty'],
            $trend['rows'],
            'D',
            ['B', 'C', 'D']
        ) + ['unit' => $trend['unit']];

        $sections['sku'] = $this->appendSection(
            $rows,
            'TOP 10 SKU PENERIMAAN',
            ['SKU', 'Nama Barang', 'Total Koli', 'Total Qty', 'Kontribusi'],
            $this->topSkus(),
            'E',
            ['C', 'D'],
            ['E']
        );

        $sections['supplier'] = $this->appendSection(
            $rows,
            'TOP 10 SUPPLIER',
            ['Supplier', 'Dokumen', 'Total Qty', 'Kontribusi'],
            $this->topSuppliers(),
            'D',
            ['B', 'C'],
            ['D']
        );

        $sections['status'] = $this->appendSection(
            $rows,
            'SEBARAN STATUS DOKUMEN',
            ['Status', 'Dokumen', 'Total Qty'],
            $this->statusBreakdown(),
            'C',
            ['B', 'C']
        );

        return $this->layout = ['rows' => $rows, 'sections' => $sections];
    }

    /**
     * Tambahkan satu blok (judul, header, data) ke $rows lalu sisakan ruang
     * vertikal untuk grafiknya. Mengembalikan posisi baris blok tersebut.
     */
    private function appendSection(
        array &$rows,
        string $title,
        array $headings,
        array $data,
        string $lastColumn,
        array $numeric,
        array $percent = []
    ): array {
        $titleRow = count($rows) + 1;
        $rows[] = [$title];
        $headerRow = count($rows) + 1;
        $rows[] = $headings;

        $firstRow = count($rows) + 1;
        foreach ($data as $row) {
            $rows[] = $row;
        }
        $lastRow = count($rows);

        // Sisakan ruang supaya grafik di kolom sebelah tidak menutupi blok berikutnya.
        while (count($rows) < $titleRow + self::CHART_ROWS) {
            $rows[] = self::BLANK_ROW;
        }
        $rows[] = self::BLANK_ROW;

        return [
            'title' => $titleRow,
            'header' => $headerRow,
            'first' => $firstRow,
            'last' => max($lastRow, $firstRow),
            'count' => count($data),
            'last_column' => $lastColumn,
            'numeric' => $numeric,
            'percent' => $percent,
        ];
    }

    private function metrics(): array
    {
        $transactions = $this->transactions();
        $items = $transactions->flatMap(fn (InboundTransaction $row) => $row->items ?? collect());
        $scanItems = $transactions->flatMap(fn (InboundTransaction $row) => $row->scanSession?->items ?? collect());

        $expectedQty = (int) $items->sum('qty');
        $scannedQty = (int) $scanItems->sum('scanned_qty');

        return [
            'Total Dokumen' => $transactions->count(),
            'SKU Unik' => $items->pluck('item_id')->filter()->unique()->count(),
            'Total Koli' => (int) $items->sum(fn (InboundItem $item) => $this->koliOf($item)),
            'Total Qty (Pcs)' => $expectedQty,
            'Qty Terscan' => $scannedQty,
            'Selisih Qty' => $scannedQty - $expectedQty,
            'Rata-rata Qty / Dokumen' => $transactions->count() > 0 ? (int) round($expectedQty / $transactions->count()) : 0,
            'Akurasi Scan' => $expectedQty > 0 ? round($scannedQty / $expectedQty * 100, 1) : 0.0,
        ];
    }

    /** Tren per hari; otomatis dikelompokkan per bulan bila rentangnya panjang. */
    private function trend(): array
    {
        $transactions = $this->transactions();
        $distinctDays = $transactions
            ->map(fn (InboundTransaction $row) => $row->transacted_at?->format('Y-m-d'))
            ->filter()
            ->unique()
            ->count();

        $perMonth = $distinctDays > 45;
        $unit = $perMonth ? 'Bulan' : 'Tanggal';

        $rows = $transactions
            ->filter(fn (InboundTransaction $row) => $row->transacted_at !== null)
            ->groupBy(fn (InboundTransaction $row) => $row->transacted_at->format($perMonth ? 'Y-m' : 'Y-m-d'))
            ->sortKeys()
            ->map(function ($group, $key) use ($perMonth) {
                $items = $group->flatMap(fn (InboundTransaction $row) => $row->items ?? collect());

                return [
                    $perMonth ? substr($key, 5, 2).'/'.substr($key, 0, 4) : implode('/', array_reverse(explode('-', $key))),
                    $group->count(),
                    (int) $items->sum(fn (InboundItem $item) => $this->koliOf($item)),
                    (int) $items->sum('qty'),
                ];
            })
            ->values()
            ->all();

        return ['unit' => $unit, 'rows' => $rows];
    }

    private function topSkus(): array
    {
        $items = $this->transactions()->flatMap(fn (InboundTransaction $row) => $row->items ?? collect());
        $totalQty = (int) $items->sum('qty');

        return $items
            ->filter(fn (InboundItem $item) => $item->item !== null)
            ->groupBy(fn (InboundItem $item) => (int) $item->item_id)
            ->map(function ($group) use ($totalQty) {
                $first = $group->first();
                $qty = (int) $group->sum('qty');

                return [
                    $first->item?->sku ?? '-',
                    $first->item?->name ?? '-',
                    (int) $group->sum(fn (InboundItem $item) => $this->koliOf($item)),
                    $qty,
                    $totalQty > 0 ? round($qty / $totalQty * 100, 1) : 0.0,
                ];
            })
            ->sortByDesc(fn (array $row) => $row[3])
            ->take(10)
            ->values()
            ->all();
    }

    private function topSuppliers(): array
    {
        $transactions = $this->transactions();
        $totalQty = (int) $transactions->flatMap(fn (InboundTransaction $row) => $row->items ?? collect())->sum('qty');

        return $transactions
            ->groupBy(fn (InboundTransaction $row) => $row->supplier?->name ?: 'Tanpa Supplier')
            ->map(function ($group, $name) use ($totalQty) {
                $qty = (int) $group->flatMap(fn (InboundTransaction $row) => $row->items ?? collect())->sum('qty');

                return [
                    (string) $name,
                    $group->count(),
                    $qty,
                    $totalQty > 0 ? round($qty / $totalQty * 100, 1) : 0.0,
                ];
            })
            ->sortByDesc(fn (array $row) => $row[2])
            ->take(10)
            ->values()
            ->all();
    }

    private function statusBreakdown(): array
    {
        return $this->transactions()
            ->groupBy(fn (InboundTransaction $row) => $this->statusLabel($row->status))
            ->map(fn ($group, $label) => [
                (string) $label,
                $group->count(),
                (int) $group->flatMap(fn (InboundTransaction $row) => $row->items ?? collect())->sum('qty'),
            ])
            ->sortByDesc(fn (array $row) => $row[1])
            ->values()
            ->all();
    }

    private function ref(string $column, int $firstRow, ?int $lastRow = null): string
    {
        $reference = "'".self::SHEET_TITLE."'!\$".$column.'$'.$firstRow;

        return $lastRow === null ? $reference : $reference.':$'.$column.'$'.$lastRow;
    }

    private function makeChart(
        string $name,
        string $title,
        string $type,
        string $categoryRef,
        array $series,
        int $pointCount,
        string $topLeft,
        string $bottomRight,
        string $direction = DataSeries::DIRECTION_COL
    ): Chart {
        $labels = [];
        $values = [];

        foreach ($series as $definition) {
            $labels[] = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, $definition['label'], null, 1);

            $value = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, $definition['values'], null, $pointCount);
            if (!empty($definition['color'])) {
                $value->setFillColor($definition['color']);
            }
            $values[] = $value;
        }

        $categories = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, $categoryRef, null, $pointCount)];

        $dataSeries = new DataSeries(
            $type,
            $type === DataSeries::TYPE_PIECHART ? null : DataSeries::GROUPING_CLUSTERED,
            range(0, count($values) - 1),
            $labels,
            $categories,
            $values
        );
        $dataSeries->setPlotDirection($direction);

        $chart = new Chart(
            $name,
            new Title($title),
            $type === DataSeries::TYPE_PIECHART ? new Legend(Legend::POSITION_RIGHT, null, false) : null,
            new PlotArea(null, [$dataSeries]),
            true,
            DataSeries::EMPTY_AS_GAP
        );
        $chart->setTopLeftPosition($topLeft);
        $chart->setBottomRightPosition($bottomRight);

        return $chart;
    }
}
