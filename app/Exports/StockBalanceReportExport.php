<?php

namespace App\Exports;

use App\Support\StockBalanceExportReport;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class StockBalanceReportExport implements WithMultipleSheets
{
    private ?array $sheets = null;

    public function __construct(private array $filters) {}

    public function sheets(): array
    {
        if ($this->sheets !== null) {
            return $this->sheets;
        }

        $report = new StockBalanceExportReport($this->filters);

        return $this->sheets = [
            new StockBalanceSummarySheet($report),
            new StockBalanceDetailSheet($report),
            new StockBalanceAttentionSheet($report),
        ];
    }
}
