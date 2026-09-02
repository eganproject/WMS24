<?php

namespace App\Exports;

use App\Models\Warehouse;
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
        return 'A5';
    }

    public function collection(): Collection
    {
        if ($this->rows !== null) {
            return $this->rows;
        }

        $this->rows = app(StockBalanceReportService::class)
            ->query($this->filters)
            ->orderBy('warehouses.name')
            ->orderBy('items.name')
            ->get()
            ->values()
            ->map(fn ($row, $index) => [
                $index + 1,
                $row->sku,
                $row->item_name,
                $row->warehouse_code,
                $row->warehouse_name,
                (int) $row->opening_stock,
                (int) $row->stock_in,
                (int) $row->stock_out,
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
            'Kode Gudang',
            'Gudang',
            'Stok Awal',
            'Stok Masuk',
            'Stok Keluar',
            'Saldo Akhir',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $summary = app(StockBalanceReportService::class)
            ->summary(app(StockBalanceReportService::class)->query($this->filters));

        $sheet->mergeCells('A1:I1');
        $sheet->mergeCells('A2:I2');
        $sheet->mergeCells('A3:I3');
        $sheet->setCellValue('A1', 'Laporan Saldo Stok');
        $sheet->setCellValue('A2', $this->filterSummary());
        $sheet->setCellValue('A3', sprintf(
            'Total: stok awal %s | masuk %s | keluar %s | saldo akhir %s',
            number_format((int) ($summary->opening_stock ?? 0), 0, ',', '.'),
            number_format((int) ($summary->stock_in ?? 0), 0, ',', '.'),
            number_format((int) ($summary->stock_out ?? 0), 0, ',', '.'),
            number_format((int) ($summary->ending_stock ?? 0), 0, ',', '.')
        ));

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
                $range = 'A5:I'.$lastRow;

                $sheet->freezePane('A6');
                $sheet->setAutoFilter($range);
                $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E4E6EF');
                $sheet->getStyle('A1:I'.$lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle('F6:I'.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle('F6:I'.$lastRow)->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle('A5:I5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getColumnDimension('B')->setWidth(20);
                $sheet->getColumnDimension('C')->setWidth(38);
                $sheet->getColumnDimension('E')->setWidth(25);
            },
        ];
    }

    private function filterSummary(): string
    {
        $warehouseIds = array_values(array_filter(array_map(
            'intval',
            (array) ($this->filters['warehouse_ids'] ?? [])
        )));
        $warehouse = $warehouseIds === []
            ? 'Seluruh Gudang'
            : Warehouse::query()
                ->whereIn('id', $warehouseIds)
                ->orderBy('name')
                ->pluck('name')
                ->implode(', ');
        $search = trim((string) ($this->filters['q'] ?? ''));

        return sprintf(
            'Periode %s s.d. %s | Gudang: %s%s',
            $this->filters['date_from'],
            $this->filters['date_to'],
            $warehouse,
            $search !== '' ? ' | Pencarian: '.$search : ''
        );
    }
}
