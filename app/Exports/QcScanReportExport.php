<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class QcScanReportExport implements WithMultipleSheets
{
    public function __construct(private array $report)
    {
    }

    public function sheets(): array
    {
        return [
            new QcScanReportSummarySheet($this->report),
            new QcScanReportHourlySheet($this->report),
            new QcScanReportDetailSheet($this->report),
        ];
    }
}
