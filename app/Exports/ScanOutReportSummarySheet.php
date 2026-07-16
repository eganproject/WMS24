<?php

namespace App\Exports;

use App\Models\Resi;
use App\Models\ShipmentScanOut;
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
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ScanOutReportSummarySheet implements FromCollection, WithHeadings, WithTitle, WithCustomStartCell, ShouldAutoSize, WithStyles, WithEvents
{
    private ?Collection $rows = null;

    public function __construct(private array $filters = [])
    {
    }

    public function title(): string
    {
        return 'Ringkasan Scan Out';
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

        $this->rows = $this->query()->get()->map(function ($row) {
            $first = $row->first_scan ? Carbon::parse($row->first_scan) : null;
            $last = $row->last_scan ? Carbon::parse($row->last_scan) : null;
            $durationHours = $first && $last
                ? max(0, $first->diffInMinutes($last)) / 60
                : 0;
            $avgPerHour = $durationHours > 0
                ? round($row->total_scan / $durationHours, 2)
                : (int) $row->total_scan;

            return [
                Carbon::parse($row->scan_date)->format('Y-m-d'),
                $row->operator_name ?? '-',
                $row->expedition ?? '-',
                (int) $row->total_scan,
                (int) $row->unique_scan,
                $avgPerHour,
                $first?->format('H:i') ?? '-',
                $last?->format('H:i') ?? '-',
            ];
        })->values();

        return $this->rows;
    }

    public function headings(): array
    {
        return ['Tanggal Scan', 'Operator', 'Ekspedisi', 'Total Scan', 'Resi Unik', 'Rata-rata / Jam', 'Scan Pertama', 'Scan Terakhir'];
    }

    public function styles(Worksheet $sheet): array
    {
        $comparison = $this->comparison();
        $sheet->mergeCells('A1:H1');
        $sheet->mergeCells('A2:H2');
        $sheet->mergeCells('A3:H3');
        $sheet->mergeCells('A4:H4');
        $sheet->setCellValue('A1', 'Laporan Scan Out');
        $sheet->setCellValue('A2', $this->filterSummary());
        $sheet->setCellValue('A3', 'Total baris operator: '.number_format($this->collection()->count(), 0, ',', '.'));
        $sheet->setCellValue('A4', sprintf(
            'Komparasi resi (berdasarkan tanggal upload): Import aktif %s | Sudah scan out %s | Belum scan out %s | Canceled %s',
            number_format($comparison['import_total'], 0, ',', '.'),
            number_format($comparison['scanned_total'], 0, ',', '.'),
            number_format($comparison['missing_total'], 0, ',', '.'),
            number_format($comparison['canceled_total'], 0, ',', '.')
        ));

        return [
            1 => ['font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '181C32']]],
            2 => ['font' => ['color' => ['rgb' => '7E8299']]],
            3 => ['font' => ['bold' => true, 'color' => ['rgb' => '3F4254']]],
            4 => ['font' => ['color' => ['rgb' => '3F4254']]],
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
                $sheet->getStyle('D7:F'.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle('D7:E'.$lastRow)->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle('F7:F'.$lastRow)->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle('A6:H6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                foreach (['A' => 16, 'B' => 28, 'C' => 24, 'D' => 16, 'E' => 16, 'F' => 20, 'G' => 16, 'H' => 16] as $column => $width) {
                    $sheet->getColumnDimension($column)->setWidth($width);
                }
            },
        ];
    }

    private function query()
    {
        $query = DB::table('shipment_scan_outs as so')
            ->join('users as u', 'u.id', '=', 'so.scanned_by')
            ->leftJoin('resis as r', 'r.id', '=', 'so.resi_id')
            ->leftJoin('kurirs as so_kurir', 'so_kurir.id', '=', 'so.kurir_id')
            ->leftJoin('kurirs as resi_kurir', 'resi_kurir.id', '=', 'r.kurir_id')
            ->selectRaw("so.scan_date, so.scanned_by, u.name as operator_name, COALESCE(so_kurir.name, resi_kurir.name, '-') as expedition, COUNT(*) as total_scan, COUNT(DISTINCT so.scan_code) as unique_scan, MIN(so.scanned_at) as first_scan, MAX(so.scanned_at) as last_scan")
            ->groupBy('so.scan_date', 'so.scanned_by', 'u.name', 'so_kurir.name', 'resi_kurir.name');

        $search = trim((string) ($this->filters['q'] ?? ''));
        if ($search !== '') {
            $exact = filter_var($this->filters['exact'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $operator = $exact ? '=' : 'like';
            $value = $exact ? $search : '%'.$search.'%';
            $query->where(function ($q) use ($operator, $value) {
                $q->where('u.name', $operator, $value)
                    ->orWhere('so.scan_date', $operator, $value);
            });
        }

        if ($operatorId = $this->filters['operator_id'] ?? null) {
            $query->where('so.scanned_by', $operatorId);
        }
        if ($dateFrom = $this->parseDate($this->filters['date_from'] ?? null)) {
            $query->where('so.scan_date', '>=', $dateFrom);
        }
        if ($dateTo = $this->parseDate($this->filters['date_to'] ?? null)) {
            $query->where('so.scan_date', '<=', $dateTo);
        }

        return $query->orderByDesc('so.scan_date')->orderBy('operator_name');
    }

    private function comparison(): array
    {
        $resiQuery = Resi::query();
        if ($dateFrom = $this->parseDate($this->filters['date_from'] ?? null)) {
            $resiQuery->whereDate('tanggal_upload', '>=', $dateFrom);
        }
        if ($dateTo = $this->parseDate($this->filters['date_to'] ?? null)) {
            $resiQuery->whereDate('tanggal_upload', '<=', $dateTo);
        }

        $canceledTotal = (clone $resiQuery)->where('status', 'canceled')->count();
        $active = (clone $resiQuery)->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', 'canceled'));
        $importTotal = (clone $active)->count();
        $scannedTotal = $importTotal > 0
            ? ShipmentScanOut::query()->whereIn('resi_id', (clone $active)->select('id'))->distinct('resi_id')->count('resi_id')
            : 0;

        return [
            'import_total' => $importTotal,
            'scanned_total' => $scannedTotal,
            'missing_total' => max(0, $importTotal - $scannedTotal),
            'canceled_total' => $canceledTotal,
        ];
    }

    private function filterSummary(): string
    {
        $from = $this->parseDate($this->filters['date_from'] ?? null) ?? 'Semua tanggal';
        $to = $this->parseDate($this->filters['date_to'] ?? null) ?? 'Semua tanggal';
        $operator = trim((string) ($this->filters['operator_name'] ?? '')) ?: 'Semua operator';
        $search = trim((string) ($this->filters['q'] ?? ''));

        return 'Periode scan: '.$from.' s.d. '.$to.' | Operator: '.$operator.($search !== '' ? ' | Pencarian: '.$search : '');
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
