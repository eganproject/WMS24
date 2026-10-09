<?php

namespace App\Exports;

use App\Support\StockRunoutForecastReport;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class StockRunoutForecastExport implements WithMultipleSheets
{
    public function __construct(private StockRunoutForecastReport $report) {}

    public function sheets(): array
    {
        // Detail diambil sekali dan dipakai bersama agar ringkasan dan detail pasti konsisten.
        $rows = $this->report->rows();

        return [
            new StockRunoutForecastSummarySheet($this->report, $rows),
            new StockRunoutForecastDetailSheet($this->report, $rows),
        ];
    }
}
