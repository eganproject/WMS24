<?php

namespace App\Exports;

use App\Http\Controllers\Admin\KpiScoreReportController;
use App\Models\KpiScoreItem;
use Illuminate\Http\Request;
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

class KpiScoreReportExport implements FromCollection, WithHeadings, WithTitle, WithCustomStartCell, ShouldAutoSize, WithStyles, WithEvents
{
    public function __construct(private array $filters = [])
    {
    }

    public function title(): string
    {
        return 'KPI Score';
    }

    public function startCell(): string
    {
        return 'A5';
    }

    public function collection()
    {
        $request = new Request($this->filters);
        $controller = app(KpiScoreReportController::class);
        $query = KpiScoreItem::query()
            ->with(['snapshot:id,code,period_start,period_end,status', 'employee:id,employee_code,name'])
            ->join('kpi_score_snapshots', 'kpi_score_snapshots.id', '=', 'kpi_score_items.kpi_score_snapshot_id')
            ->select('kpi_score_items.*')
            ->orderByDesc('kpi_score_snapshots.period_start')
            ->orderBy('kpi_score_items.role_name')
            ->orderBy('kpi_score_items.metric_name');

        $controller->applyFilters($query, $request);

        return $query->get()->map(function (KpiScoreItem $item) use ($controller) {
            $row = $controller->mapRow($item);

            return [
                $row['snapshot_code'],
                $row['period'],
                $row['snapshot_status'],
                $row['employee'],
                $row['role_name'],
                $row['metric_name'],
                $row['target'],
                $row['actual_value'],
                $row['achievement_percent'],
                $row['score'],
                $row['weight'],
                $row['weighted_score'],
                $row['source_type'],
                $row['formula_key'],
                $row['calculated_at'],
                $row['note'],
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Snapshot',
            'Periode',
            'Status',
            'Karyawan',
            'Role/Jabatan',
            'KPI',
            'Target',
            'Actual',
            'Achievement %',
            'Score',
            'Bobot %',
            'Weighted Score',
            'Sumber',
            'Formula Key',
            'Calculated At',
            'Catatan',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $rowCount = $this->collection()->count();
        $sheet->mergeCells('A1:P1');
        $sheet->mergeCells('A2:P2');
        $sheet->mergeCells('A3:P3');
        $sheet->setCellValue('A1', 'Laporan KPI Score');
        $sheet->setCellValue('A2', $this->filterSummary());
        $sheet->setCellValue('A3', 'Total baris: '.number_format($rowCount, 0, ',', '.'));

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
                $range = 'A5:P'.$lastRow;

                $sheet->freezePane('A6');
                $sheet->setAutoFilter($range);
                $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E4E6EF');
                $sheet->getStyle('A1:P'.$lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle('H6:L'.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle('H6:L'.$lastRow)->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle('P6:P'.$lastRow)->getAlignment()->setWrapText(true);
            },
        ];
    }

    private function filterSummary(): string
    {
        $parts = [];
        if (!empty($this->filters['date_from']) || !empty($this->filters['date_to'])) {
            $parts[] = 'Periode: '.($this->filters['date_from'] ?? '-').' s/d '.($this->filters['date_to'] ?? '-');
        }
        if (!empty($this->filters['role_name'])) {
            $parts[] = 'Role: '.$this->filters['role_name'];
        }
        if (!empty($this->filters['status'])) {
            $parts[] = 'Status snapshot: '.$this->filters['status'];
        }
        if (!empty($this->filters['actual_status'])) {
            $parts[] = 'Status actual: '.$this->filters['actual_status'];
        }

        return $parts ? implode(' | ', $parts) : 'Semua data';
    }
}
