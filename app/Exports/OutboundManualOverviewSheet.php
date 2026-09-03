<?php

namespace App\Exports;

use App\Support\OutboundManualQcStatus;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class OutboundManualOverviewSheet extends OutboundManualSheet implements FromArray, WithTitle, WithEvents, WithStrictNullComparison
{
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
        return [AfterSheet::class => function (AfterSheet $event) {
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
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '009EF7']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
                $sheet->getStyle($from.'6:'.$to.'6')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 15, 'color' => ['rgb' => '181C32']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EAF4FF']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
                $sheet->getStyle($from.'5:'.$to.'6')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('B9D9FA');
            }
            $sheet->getStyle('I6')->getNumberFormat()->setFormatCode('0.00%');

            $sheet->mergeCells('A8:J8');
            $this->styleTitle($sheet, 8);
            foreach ([9, 10, 11, 12] as $row) {
                $sheet->mergeCells('A'.$row.':J'.$row);
            }

            foreach ($layout['sections'] as $key => $section) {
                $sheet->mergeCells('A'.$section['title'].':J'.$section['title']);
                $this->styleTitle($sheet, $section['title']);
                $headerRange = 'A'.$section['header'].':H'.$section['header'];
                $sheet->getStyle($headerRange)->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '3F4254']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
                ]);
                $last = max($section['header'], $section['last']);
                $sheet->getStyle('A'.$section['header'].':H'.$last)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E4E6EF');
                if ($section['last'] > $section['header']) {
                    $sheet->getStyle('B'.($section['header'] + 1).':H'.$section['last'])->getNumberFormat()->setFormatCode('#,##0');
                    if (!empty($section['percent_column'])) {
                        $column = $section['percent_column'];
                        $sheet->getStyle($column.($section['header'] + 1).':'.$column.$section['last'])->getNumberFormat()->setFormatCode('0.00%');
                    }
                    if ($key === 'daily') {
                        $sheet->getStyle('A'.($section['header'] + 1).':A'.$section['last'])->getNumberFormat()->setFormatCode('dd/mm/yyyy');
                    }
                }
            }

            foreach (['A' => 25, 'B' => 27, 'C' => 16, 'D' => 16, 'E' => 16, 'F' => 16, 'G' => 16, 'H' => 18, 'I' => 12, 'J' => 12] as $column => $width) {
                $sheet->getColumnDimension($column)->setWidth($width);
            }
            $sheet->getStyle('A1:J'.$layout['last_row'])->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->freezePane('A5');
            $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);
            $sheet->setSelectedCell('A1');
        }];
    }

    private function layout(): array
    {
        if ($this->layout !== null) {
            return $this->layout;
        }

        $totals = $this->report->totals();
        $items = $this->report->itemSummary();
        $daily = $this->report->dailySummary();
        $statuses = $this->report->statusSummary();
        $warehouses = $this->report->warehouseSummary();
        $recipients = $this->report->recipientSummary();
        $expected = (int) ($totals->expected_qty ?? 0);
        $scanned = (int) ($totals->scanned_qty ?? 0);
        $completion = $expected > 0 ? $scanned / $expected : 0;
        $topItem = $items->first();
        $topRecipient = $recipients->first();
        $busiestDay = $daily->sortByDesc('planned_qty')->first();
        $completed = $statuses->firstWhere('status', OutboundManualQcStatus::APPROVED);

        $rows = [
            ['Laporan Outbound Manual - Ringkasan Analisis'],
            [$this->report->filterSummary()],
            ['Diunduh: '.now()->format('d/m/Y H:i').' | Persentase QC = Qty Scan / Target QC'],
            [''],
            ['Total Dokumen', '', 'SKU Unik', '', 'Qty Rencana', '', 'Qty Terscan', '', 'Progres QC', ''],
            [(int) ($totals->document_count ?? 0), '', $items->count(), '', (int) ($totals->planned_qty ?? 0), '', $scanned, '', $completion, ''],
            [''],
            ['SOROTAN ANALISIS'],
            ['SKU dengan volume terbesar: '.($topItem ? trim(($topItem->sku ?? '').' - '.($topItem->item_name ?? ''), ' -').' ('.number_format((int) $topItem->planned_qty, 0, ',', '.').' pcs)' : '-')],
            ['Penerima dengan volume terbesar: '.($topRecipient ? (($topRecipient->recipient_name ?: 'Tanpa Nama').' - '.number_format((int) $topRecipient->planned_qty, 0, ',', '.').' pcs') : '-')],
            ['Hari dengan volume tertinggi: '.($busiestDay ? $busiestDay->period_date.' ('.number_format((int) $busiestDay->planned_qty, 0, ',', '.').' pcs)' : '-')],
            ['Dokumen selesai: '.number_format((int) ($completed->document_count ?? 0), 0, ',', '.').' | Sisa target QC: '.number_format(max(0, $expected - $scanned), 0, ',', '.').' pcs'],
            [''],
        ];

        $sections = [];
        $dailyRows = $daily->map(function ($row) {
            $planned = (int) $row->planned_qty;
            $expected = (int) $row->expected_qty;
            $scanned = (int) $row->scanned_qty;
            return [Date::dateTimeToExcel(Carbon::parse($row->period_date)), (int) $row->document_count, (int) $row->sku_count, $planned, $expected, $scanned, max(0, $expected - $scanned), $expected > 0 ? $scanned / $expected : 0];
        })->all();
        $this->appendSection($rows, $sections, 'daily', 'AKTIVITAS HARIAN', ['Tanggal', 'Dokumen', 'Total Baris SKU', 'Qty Rencana', 'Target QC', 'Qty Scan', 'Sisa QC', 'Progres QC'], $dailyRows, 'H');

        $statusRows = $statuses->map(function ($row) {
            $planned = (int) $row->planned_qty;
            $expected = (int) $row->expected_qty;
            $scanned = (int) $row->scanned_qty;
            return [$this->report->statusLabel($row->status), (int) $row->document_count, $planned, $expected, $scanned, max(0, $expected - $scanned), $expected > 0 ? $scanned / $expected : 0];
        })->all();
        $this->appendSection($rows, $sections, 'status', 'KOMPOSISI STATUS', ['Status', 'Dokumen', 'Qty Rencana', 'Target QC', 'Qty Scan', 'Sisa QC', 'Progres QC', ''], $statusRows, 'G');

        $warehouseRows = $warehouses->map(function ($row) {
            $planned = (int) $row->planned_qty;
            $expected = (int) $row->expected_qty;
            $scanned = (int) $row->scanned_qty;
            return [$row->warehouse_code ?? '', $row->warehouse_name ?? '-', (int) $row->document_count, $planned, $expected, $scanned, max(0, $expected - $scanned), $expected > 0 ? $scanned / $expected : 0];
        })->all();
        $this->appendSection($rows, $sections, 'warehouse', 'AKTIVITAS PER GUDANG', ['Kode', 'Gudang', 'Dokumen', 'Qty Rencana', 'Target QC', 'Qty Scan', 'Sisa QC', 'Progres QC'], $warehouseRows, 'H');

        $totalPlanned = (int) ($totals->planned_qty ?? 0);
        $recipientRows = $recipients->map(function ($row) use ($totalPlanned) {
            $planned = (int) $row->planned_qty;
            return [$row->recipient_name ?: 'Tanpa Nama', (int) $row->document_count, $planned, $totalPlanned > 0 ? $planned / $totalPlanned : 0, (int) $row->document_count > 0 ? round($planned / (int) $row->document_count, 2) : 0];
        })->all();
        $this->appendSection($rows, $sections, 'recipient', '10 PENERIMA DENGAN VOLUME TERBESAR', ['Penerima', 'Dokumen', 'Qty Rencana', 'Kontribusi', 'Rata-rata/Doc', '', '', ''], $recipientRows, 'D');

        return $this->layout = ['rows' => $rows, 'sections' => $sections, 'last_row' => count($rows)];
    }

    private function appendSection(array &$rows, array &$sections, string $key, string $title, array $headings, array $data, ?string $percentColumn = null): void
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
        $sections[$key] = ['title' => $titleRow, 'header' => $headerRow, 'last' => $lastRow, 'percent_column' => $percentColumn];
    }

    private function styleTitle($sheet, int $row): void
    {
        $sheet->getStyle('A'.$row.':J'.$row)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '181C32']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E4E6EF']],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(23);
    }
}
