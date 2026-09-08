<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class StockMovementAnalysisSummarySheet extends StockMovementAnalysisSheet implements FromArray, WithCustomValueBinder, WithEvents, WithStrictNullComparison, WithTitle
{
    public function title(): string
    {
        return 'Ringkasan Analisis';
    }

    public function array(): array
    {
        $summary = $this->report->summary();
        $rows = [
            ['Laporan Analisis Pergerakan Stok'],
            [$this->report->filterSummary()],
            ['Diunduh: '.now()->format('d/m/Y H:i').' | Basis demand: pengiriman marketplace dan outbound manual'],
            [''],
            ['TOTAL SKU', '', 'KELUAR AKTUAL', '', 'SALDO AKHIR', '', 'PERLU PERHATIAN', ''],
            [$summary->total_items, '', $summary->demand_out, '', $summary->ending_stock, '', $summary->attention_items, ''],
            [''],
            ['KOMPOSISI KATEGORI'],
            ['Kategori', 'Jumlah SKU', 'Persentase SKU', 'Keluar Aktual', 'Saldo Akhir'],
        ];

        foreach ($this->report->categoryBreakdown() as $category) {
            $rows[] = [
                $category->label,
                $category->items,
                $category->percentage,
                $category->demand_out,
                $category->ending_stock,
            ];
        }

        $rows[] = [''];
        $rows[] = ['DEFINISI KLASIFIKASI'];
        $rows[] = ['Fast Moving', 'Rata-rata keluar minimal 1 unit per hari pada periode terpilih.'];
        $rows[] = ['Medium Moving', 'Rata-rata keluar minimal 1 unit per minggu, tetapi kurang dari 1 unit per hari.'];
        $rows[] = ['Slow Moving', 'Masih memiliki permintaan keluar, tetapi kurang dari 1 unit per minggu.'];
        $rows[] = ['Dead Stock', 'Tidak memiliki permintaan keluar selama periode dan saldo akhir masih tersedia.'];
        $rows[] = ['Tanpa Stok', 'Tidak memiliki permintaan keluar selama periode dan saldo akhir nol atau negatif.'];
        $rows[] = [''];
        $rows[] = ['INDIKATOR TINDAK LANJUT'];
        $rows[] = ['SKU demand aktif tanpa stok', $summary->demand_items_without_stock, 'Prioritaskan pengecekan replenishment.'];
        $rows[] = ['SKU dengan ketahanan ≤ 14 hari', $summary->low_coverage_items, 'Siapkan pembelian atau transfer stok sesuai lead time.'];
        $rows[] = ['Unit tertahan pada Dead Stock', $summary->dead_stock_units, 'Evaluasi promo, redistribusi, atau retur supplier.'];
        $rows[] = ['Unit pada Slow Moving', $summary->slow_stock_units, 'Review pembelian dan strategi penjualan.'];

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                foreach ([1, 2, 3, 8, 16, 23] as $row) {
                    $sheet->mergeCells('A'.$row.':H'.$row);
                }
                foreach ([1, 2, 3, 8, 16, 23] as $row) {
                    $sheet->getStyle('A'.$row)->getAlignment()->setWrapText(true);
                }

                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(18)->getColor()->setRGB('181C32');
                $sheet->getStyle('A2:A3')->getFont()->getColor()->setRGB('7E8299');
                foreach ([8, 16, 23] as $row) {
                    $sheet->getStyle('A'.$row.':H'.$row)->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '3F4254']],
                    ]);
                }

                foreach ([['A', 'B'], ['C', 'D'], ['E', 'F'], ['G', 'H']] as [$from, $to]) {
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

                $sheet->getStyle('A9:E9')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1B84FF']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
                $sheet->getStyle('A9:E14')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E4E6EF');
                $sheet->getStyle('C10:C14')->getNumberFormat()->setFormatCode('0.00%');
                $sheet->getStyle('B10:B14')->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle('D10:E14')->getNumberFormat()->setFormatCode('#,##0');

                $sheet->getStyle('A17:B21')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E4E6EF');
                $sheet->getStyle('A17:A21')->getFont()->setBold(true);
                $sheet->getStyle('B17:B21')->getAlignment()->setWrapText(true);
                $sheet->getStyle('A24:C27')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E4E6EF');
                $sheet->getStyle('A24:A27')->getFont()->setBold(true);
                $sheet->getStyle('B24:B27')->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle('C24:C27')->getAlignment()->setWrapText(true);

                foreach (['A' => 31, 'B' => 20, 'C' => 27, 'D' => 18, 'E' => 18, 'F' => 14, 'G' => 20, 'H' => 14] as $column => $width) {
                    $sheet->getColumnDimension($column)->setWidth($width);
                }
                $sheet->getStyle('A1:H27')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);
                $sheet->freezePane('A5');
                $sheet->setSelectedCell('A1');
            },
        ];
    }
}
