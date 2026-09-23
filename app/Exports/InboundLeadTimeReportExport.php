<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class InboundLeadTimeReportExport implements WithMultipleSheets
{
    public function __construct(private array $report)
    {
    }

    public function sheets(): array
    {
        return [
            new InboundLeadTimeReportSummarySheet($this->report),
            new InboundLeadTimeReportDetailSheet($this->report),
        ];
    }
}
