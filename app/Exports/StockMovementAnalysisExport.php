<?php

namespace App\Exports;

use App\Support\StockMovementExportReport;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class StockMovementAnalysisExport implements WithMultipleSheets
{
    private ?array $sheets = null;

    public function __construct(private array $filters)
    {
    }

    public function sheets(): array
    {
        if ($this->sheets !== null) {
            return $this->sheets;
        }

        $report = new StockMovementExportReport($this->filters);

        return $this->sheets = [
            new StockMovementAnalysisSummarySheet($report),
            new StockMovementAnalysisTableSheet($report, 'Detail Semua SKU'),
            new StockMovementAnalysisTableSheet($report, 'Fokus Tindak Lanjut', true),
        ];
    }
}
