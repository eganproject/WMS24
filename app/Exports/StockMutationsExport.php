<?php

namespace App\Exports;

use App\Support\StockMutationReport;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class StockMutationsExport implements WithMultipleSheets
{
    private ?array $sheets = null;

    public function __construct(private array $filters = [])
    {
    }

    public function sheets(): array
    {
        if ($this->sheets !== null) {
            return $this->sheets;
        }

        $report = new StockMutationReport($this->filters);

        return $this->sheets = [
            new StockMutationOverviewSheet($report),
            new StockMutationItemSummarySheet($report),
            new StockMutationDetailSheet($report),
        ];
    }
}
