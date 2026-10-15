<?php

namespace App\Exports;

use App\Support\ResiStoreFilter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ScanOutMissingResiSheet implements FromCollection, WithHeadings, WithTitle, WithCustomStartCell, ShouldAutoSize, WithStyles, WithEvents
{
    private ?Collection $rows = null;

    public function __construct(private array $filters = [])
    {
    }

    public function title(): string
    {
        return 'Belum Scan Out';
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

        $query = DB::table('resis')
            ->leftJoin('shipment_scan_outs as so', 'so.resi_id', '=', 'resis.id')
            ->leftJoin('kurirs', 'kurirs.id', '=', 'resis.kurir_id')
            ->leftJoin('stores', 'stores.id', '=', 'resis.store_id')
            ->whereNull('so.id')
            ->where(fn ($q) => $q->whereNull('resis.status')->orWhere('resis.status', '!=', 'canceled'))
            ->select('resis.id_pesanan', 'resis.no_resi', 'stores.name as store_name', 'kurirs.name as expedition', 'resis.tanggal_upload')
            ->orderByDesc('resis.tanggal_upload')
            ->orderBy('resis.id_pesanan');

        if ($dateFrom = $this->parseDate($this->filters['date_from'] ?? null)) {
            $query->whereDate('resis.tanggal_upload', '>=', $dateFrom);
        }
        if ($dateTo = $this->parseDate($this->filters['date_to'] ?? null)) {
            $query->whereDate('resis.tanggal_upload', '<=', $dateTo);
        }
        ResiStoreFilter::apply($query, 'resis.store_id', ResiStoreFilter::normalize($this->filters['store_id'] ?? null));

        $this->rows = $query->get()->map(fn ($row) => [
            (string) ($row->id_pesanan ?? ''),
            (string) ($row->no_resi ?? ''),
            (string) ($row->store_name ?? ResiStoreFilter::NONE_LABEL),
            (string) ($row->expedition ?? '-'),
            $row->tanggal_upload ? Carbon::parse($row->tanggal_upload)->format('Y-m-d') : '',
        ])->values();

        return $this->rows;
    }

    public function headings(): array
    {
        return ['ID Pesanan', 'No. Resi', 'Toko', 'Ekspedisi', 'Tanggal Upload'];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->mergeCells('A1:E1');
        $sheet->mergeCells('A2:E2');
        $sheet->mergeCells('A3:E3');
        $sheet->setCellValue('A1', 'Resi Aktif Belum Scan Out');
        $sheet->setCellValue('A2', $this->filterSummary());
        $sheet->setCellValue('A3', 'Total resi: '.number_format($this->collection()->count(), 0, ',', '.'));

        return [
            1 => ['font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '181C32']]],
            2 => ['font' => ['color' => ['rgb' => '7E8299']]],
            3 => ['font' => ['bold' => true, 'color' => ['rgb' => '3F4254']]],
            5 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F6C000']],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = max(5, 5 + $this->collection()->count());
                $range = 'A5:E'.$lastRow;

                $sheet->freezePane('A6');
                $sheet->setAutoFilter($range);
                $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E4E6EF');
                $sheet->getStyle('A1:E'.$lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle('A5:E5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A6:B'.$lastRow)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
                $sheet->getColumnDimension('A')->setWidth(24);
                $sheet->getColumnDimension('B')->setWidth(28);
                $sheet->getColumnDimension('C')->setWidth(24);
                $sheet->getColumnDimension('D')->setWidth(24);
                $sheet->getColumnDimension('E')->setWidth(18);
            },
        ];
    }

    private function filterSummary(): string
    {
        $from = $this->parseDate($this->filters['date_from'] ?? null) ?? 'Semua tanggal';
        $to = $this->parseDate($this->filters['date_to'] ?? null) ?? 'Semua tanggal';

        return 'Periode tanggal upload: '.$from.' s.d. '.$to.' | Toko: '.ResiStoreFilter::label(ResiStoreFilter::normalize($this->filters['store_id'] ?? null));
    }

    private function parseDate(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
