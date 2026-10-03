<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StockBalanceSummarySheet extends StockBalanceSheet implements FromArray, WithCustomValueBinder, WithEvents, WithStrictNullComparison, WithTitle
{
    private const LAST_COLUMN = 'F';

    private array $rows = [];

    /** Penanda posisi tiap bagian agar bisa diberi gaya setelah data ditulis. */
    private array $layout = ['sections' => [], 'headers' => [], 'tables' => [], 'totals' => [], 'formats' => [], 'merges' => [], 'formulas' => [], 'notes' => []];

    private bool $built = false;

    public function title(): string
    {
        return 'Ringkasan';
    }

    public function array(): array
    {
        $this->build();

        return $this->rows;
    }

    private function build(): void
    {
        if ($this->built) {
            return;
        }
        $this->built = true;

        $summary = $this->report->summary();

        $this->push(['Laporan Saldo Stok - Ringkasan']);
        $this->push([$this->report->filterSummary()]);
        $this->push(['Diunduh: '.now()->format('d/m/Y H:i').' | Saldo Akhir = Stok Awal + Masuk - Keluar + Mutasi Lain']);
        $this->push(['']);

        // Kartu angka utama.
        $this->push(['STOK AWAL', 'MASUK', 'KELUAR', 'MUTASI LAIN (NET)', 'SALDO AKHIR', 'JUMLAH SKU']);
        $this->push([
            $summary->opening_stock, $summary->stock_in, $summary->stock_out,
            $summary->other_net, $summary->ending_stock, $summary->total_items,
        ]);
        $this->push(['Awal periode', 'Inbound Gudang Besar', 'Outbound manual + QC resi', 'Retur, opname, dll.', 'Akhir periode', 'SKU dalam laporan']);
        $this->push(['']);

        // Rekonsiliasi saldo.
        $this->section('REKONSILIASI SALDO (GUDANG BESAR + GUDANG DISPLAY)');
        $start = $this->header(['Komponen', 'Qty', 'Keterangan']);
        $this->push(['Stok Awal', $summary->opening_stock, 'Saldo gabungan pada awal periode']);
        $this->push(['(+) Masuk', $summary->stock_in, 'Hanya dokumen inbound yang masuk ke Gudang Besar']);
        $this->push(['(-) Keluar', $summary->stock_out, 'Hanya outbound manual dan QC scan hasil import resi']);
        $this->push(['(+/-) Mutasi Lain', $summary->other_net, 'Rinciannya ada di tabel "Rincian Mutasi Lain"']);
        $first = $start + 1;
        $endRow = $this->push(['(=) Saldo Akhir', $summary->ending_stock, '']);
        // Rumus ditulis langsung ke sel (bukan lewat value binder yang menolak teks berawalan "=").
        $this->layout['formulas'][] = [
            "C{$endRow}",
            sprintf('=IF(B%d+B%d-B%d+B%d=B%d,"Seimbang","Periksa kembali")', $first, $first + 1, $first + 2, $first + 3, $endRow),
        ];
        $this->stretch($start, $endRow, 'C');
        $this->layout['totals'][] = "A{$endRow}:F{$endRow}";
        $this->format("B{$first}:B{$endRow}", self::QTY_FORMAT);
        $this->format('B'.($first + 3), self::SIGNED_QTY_FORMAT);
        $this->push(['']);

        // Posisi per gudang.
        $this->section('POSISI SALDO AKHIR PER GUDANG');
        $start = $this->header(['Gudang', 'Saldo Akhir', 'Porsi']);
        $total = $summary->ending_stock;
        $this->push(['Gudang Besar', $summary->main_ending_stock, $total > 0 ? $summary->main_ending_stock / $total : 0]);
        $this->push(['Gudang Display', $summary->display_ending_stock, $total > 0 ? $summary->display_ending_stock / $total : 0]);
        $endRow = $this->push(['Total', $total, $total > 0 ? 1 : 0]);
        $this->table($start, $endRow, 'C');
        $this->layout['totals'][] = "A{$endRow}:C{$endRow}";
        $this->format('B'.($start + 1).":B{$endRow}", self::QTY_FORMAT);
        $this->format('C'.($start + 1).":C{$endRow}", '0.0%');
        $this->push(['']);

        // Kondisi dan aktivitas SKU.
        $this->section('KONDISI STOK AKHIR');
        $start = $this->header(['Kondisi', 'Jumlah SKU', 'Unit Stok', 'Arti']);
        $meaning = [
            'Tersedia' => 'Saldo akhir lebih dari 0',
            'Habis' => 'Saldo akhir 0',
            'Minus' => 'Saldo akhir di bawah 0, perlu dicek',
        ];
        foreach ($this->report->conditionBreakdown() as $condition) {
            $endRow = $this->push([$condition->label, $condition->items, $condition->units, $meaning[$condition->label] ?? '']);
        }
        $this->stretch($start, $endRow, 'D');
        $this->format('B'.($start + 1).":C{$endRow}", self::QTY_FORMAT);
        $this->push(['']);

        $this->section('AKTIVITAS SKU SELAMA PERIODE');
        $start = $this->header(['Indikator', 'Jumlah SKU', 'Keterangan']);
        $this->push(['SKU dengan barang masuk', $summary->items_in, 'Ada inbound ke Gudang Besar']);
        $this->push(['SKU dengan barang keluar', $summary->items_out, 'Ada outbound manual / QC resi']);
        $this->push(['SKU tanpa pergerakan', $summary->items_idle, 'Tidak ada masuk, keluar, maupun mutasi lain']);
        foreach ($this->report->changeBreakdown() as $change) {
            $this->push([$change->label, $change->items, 'Dibandingkan stok awal periode']);
        }
        $endRow = $this->push(['SKU perlu perhatian', $summary->attention_items, 'Lihat sheet "Perlu Perhatian"']);
        $this->stretch($start, $endRow, 'C');
        $this->format('B'.($start + 1).":B{$endRow}", '#,##0');
        $this->push(['']);

        // Rincian mutasi lain.
        $this->section('RINCIAN MUTASI LAIN');
        $start = $this->header(['Jenis Mutasi', 'Tambah', 'Kurang', 'Net']);
        $others = $this->report->otherMutations();
        if ($others->isEmpty()) {
            $this->push(['Tidak ada mutasi lain pada periode ini', 0, 0, 0]);
        }
        foreach ($others as $other) {
            $this->push([$other->label, $other->qty_in, $other->qty_out, $other->net]);
        }
        $endRow = $this->push(['Total', (int) $others->sum('qty_in'), (int) $others->sum('qty_out'), (int) $others->sum('net')]);
        $this->table($start, $endRow, 'D');
        $this->layout['totals'][] = "A{$endRow}:D{$endRow}";
        $this->format('B'.($start + 1).":C{$endRow}", self::QTY_FORMAT);
        $this->format('D'.($start + 1).":D{$endRow}", self::SIGNED_QTY_FORMAT);
        $this->layout['notes'][] = $this->push(['Catatan: transfer antara Gudang Besar dan Gudang Display saling meniadakan sehingga net-nya 0.']);
        $this->push(['']);

        // SKU teratas.
        $this->topTable('10 SKU KELUAR TERBANYAK', 'stock_out', 'Keluar');
        $this->topTable('10 SKU MASUK TERBANYAK', 'stock_in', 'Masuk');
    }

    private function topTable(string $title, string $field, string $label): void
    {
        $this->section($title);
        $start = $this->header(['SKU', 'Nama Item', '', $label, 'Saldo Akhir', 'Kondisi']);
        $this->layout['merges'][] = "B{$start}:C{$start}";
        $rows = $this->report->topBy($field);
        $endRow = $start;
        if ($rows->isEmpty()) {
            $endRow = $this->push(['Tidak ada data pada periode ini', '', '', 0, 0, '-']);
            $this->layout['merges'][] = "B{$endRow}:C{$endRow}";
        }
        foreach ($rows as $row) {
            $endRow = $this->push([$row->sku, $row->item_name, '', $row->{$field}, $row->ending_stock, $row->condition]);
            $this->layout['merges'][] = "B{$endRow}:C{$endRow}";
        }
        $this->table($start, $endRow, 'F');
        $this->format('D'.($start + 1).":E{$endRow}", self::QTY_FORMAT);
        $this->push(['']);
    }

    private function push(array $row): int
    {
        $this->rows[] = $row;

        return count($this->rows);
    }

    private function section(string $title): void
    {
        $this->layout['sections'][] = $this->push([$title]);
    }

    private function header(array $columns): int
    {
        $row = $this->push($columns);
        $this->layout['headers'][] = 'A'.$row.':'.chr(ord('A') + count($columns) - 1).$row;

        return $row;
    }

    private function table(int $start, int $end, string $lastColumn): void
    {
        $this->layout['tables'][] = "A{$start}:{$lastColumn}{$end}";
    }

    /** Tabel dengan kolom teks terakhir digabung sampai kolom F agar keterangan panjang tetap satu baris. */
    private function stretch(int $start, int $end, string $fromColumn): void
    {
        for ($row = $start; $row <= $end; $row++) {
            $this->layout['merges'][] = "{$fromColumn}{$row}:".self::LAST_COLUMN.$row;
        }
        $this->layout['headers'] = array_map(
            fn (string $range) => str_starts_with($range, "A{$start}:") ? "A{$start}:".self::LAST_COLUMN.$start : $range,
            $this->layout['headers']
        );
        $this->table($start, $end, self::LAST_COLUMN);
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
                $lastRow = count($this->rows);

                foreach ([1, 2, 3] as $row) {
                    $sheet->mergeCells("A{$row}:{$last}{$row}");
                }
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(18)->getColor()->setRGB('181C32');
                $sheet->getStyle('A2:A3')->getFont()->getColor()->setRGB('7E8299');

                $this->styleCards($sheet);

                foreach ($this->layout['sections'] as $row) {
                    $sheet->mergeCells("A{$row}:{$last}{$row}");
                    $sheet->getStyle("A{$row}:{$last}{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '3F4254']],
                    ]);
                    $sheet->getRowDimension($row)->setRowHeight(22);
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
                foreach ($this->layout['formulas'] as [$cell, $formula]) {
                    $sheet->setCellValueExplicit($cell, $formula, DataType::TYPE_FORMULA);
                }
                foreach ($this->layout['merges'] as $range) {
                    $sheet->mergeCells($range);
                }

                $this->addTextHighlight($sheet, "A1:{$last}{$lastRow}", [
                    ['Seimbang', 'E8FFF3', '00875A'],
                    ['Periksa kembali', 'FFF5F8', 'D9214E'],
                ]);

                foreach (['A' => 34, 'B' => 20, 'C' => 22, 'D' => 22, 'E' => 18, 'F' => 18] as $column => $width) {
                    $sheet->getColumnDimension($column)->setWidth($width);
                }
                $sheet->getStyle("A1:{$last}{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
                $sheet->getStyle('A2:A3')->getAlignment()->setWrapText(false);
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
        $sheet->getStyle('A6:F6')->applyFromArray([
            'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '181C32']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EAF4FF']],
        ]);
        $sheet->getStyle('A7:F7')->applyFromArray([
            'font' => ['size' => 8, 'color' => ['rgb' => '7E8299']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EAF4FF']],
        ]);
        $sheet->getStyle('A5:F7')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A5:F7')->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('B9D9FA');
        $sheet->getStyle('A5:F7')->getBorders()->getVertical()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('B9D9FA');
        $sheet->getStyle('A6:E6')->getNumberFormat()->setFormatCode(self::QTY_FORMAT);
        $sheet->getStyle('D6')->getNumberFormat()->setFormatCode(self::SIGNED_QTY_FORMAT);
        $sheet->getStyle('F6')->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getRowDimension(6)->setRowHeight(30);
    }
}
