<?php

namespace App\Exports;

use App\Support\StockBalanceReportService;
use Illuminate\Support\Collection;
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

class StockBalanceReportExport implements FromCollection, WithHeadings, WithTitle, WithCustomStartCell, ShouldAutoSize, WithStyles, WithEvents
{
    private ?Collection $rows = null;

    public function __construct(private array $filters)
    {
    }

    public function title(): string
    {
        return 'Saldo Stok';
    }

    public function startCell(): string
    {
        return 'A6';
    }

    public function collection(): Collection
    {
        if ($this->rows !== null) {
            return $this->rows;
        }

        $this->rows = app(StockBalanceReportService::class)
            ->consolidatedQuery($this->filters)
            ->orderBy('items.name')
            ->get()
            ->values()
            ->map(fn ($row, $index) => [
                $index + 1,
                $row->sku,
                $row->item_name,
                (int) $row->opening_stock,
                (int) $row->stock_in,
                (int) $row->stock_out,
                (int) $row->other_net,
                (int) $row->ending_stock,
            ]);

        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'No',
            'SKU',
            'Nama Item',
            'Stok Awal',
            'Masuk (Inbound Gudang Besar)',
            'Keluar (Outbound Manual + QC Resi)',
            'Mutasi Lain (Net)',
            'Saldo Akhir',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $service = app(StockBalanceReportService::class);
        $summary = $service->consolidatedSummary($service->consolidatedQuery($this->filters));
        $format = fn ($value) => number_format((int) $value, 0, ',', '.');

        foreach (['A1:H1', 'A2:H2', 'A3:H3', 'A4:H4'] as $range) {
            $sheet->mergeCells($range);
        }
        $sheet->setCellValue('A1', 'Laporan Saldo Stok (Gudang Besar + Gudang Display)');
        $sheet->setCellValue('A2', $this->filterSummary());
        $sheet->setCellValue('A3', 'Saldo akhir = stok awal + masuk - keluar + mutasi lain. Masuk: inbound ke Gudang Besar. '
            .'Keluar: outbound manual + QC scan import resi. Mutasi lain: retur, opname, penyesuaian, barang rusak, transfer ke/dari gudang lain.');
        $sheet->setCellValue('A4', sprintf(
            'Total %s item: stok awal %s | masuk %s | keluar %s | mutasi lain %s | saldo akhir %s',
            $format($summary->total_items ?? 0),
            $format($summary->opening_stock ?? 0),
            $format($summary->stock_in ?? 0),
            $format($summary->stock_out ?? 0),
            $format($summary->other_net ?? 0),
            $format($summary->ending_stock ?? 0)
        ));

        return [
            1 => ['font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '181C32']]],
            2 => ['font' => ['color' => ['rgb' => '7E8299']]],
            3 => ['font' => ['italic' => true, 'color' => ['rgb' => '7E8299']]],
            4 => ['font' => ['bold' => true, 'color' => ['rgb' => '3F4254']]],
            6 => [
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
                $lastRow = max(6, 6 + $this->collection()->count());
                $range = 'A6:H'.$lastRow;

                $sheet->freezePane('A7');
                $sheet->setAutoFilter($range);
                $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E4E6EF');
                $sheet->getStyle('A1:H'.$lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle('A3')->getAlignment()->setWrapText(true);
                $sheet->getRowDimension(3)->setRowHeight(30);
                $sheet->getStyle('D7:H'.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle('D7:H'.$lastRow)->getNumberFormat()->setFormatCode('#,##0;-#,##0;0');
                $sheet->getStyle('A6:H6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);
                $sheet->getColumnDimension('B')->setWidth(20);
                $sheet->getColumnDimension('C')->setWidth(38);
            },
        ];
    }

    private function filterSummary(): string
    {
        $search = trim((string) ($this->filters['q'] ?? ''));

        return sprintf(
            'Periode %s s.d. %s | Gudang: Gudang Besar + Gudang Display%s',
            $this->filters['date_from'],
            $this->filters['date_to'],
            $search !== '' ? ' | Pencarian: '.$search : ''
        );
    }
}
